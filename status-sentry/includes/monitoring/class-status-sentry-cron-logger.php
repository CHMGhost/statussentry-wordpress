<?php
/**
 * Cron Logger Component
 *
 * This class implements centralized logging for WordPress cron jobs.
 * It tracks execution times, success/failure status, and other metadata
 * to help diagnose cron-related issues.
 *
 * Key responsibilities:
 * - Log cron job executions to a central database table
 * - Track execution times and memory usage
 * - Record success/failure status and error messages
 * - Provide methods for querying cron execution history
 * - Implement cleanup of old log entries
 * - Support the Monitoring_Interface for centralized monitoring
 *
 * @since      1.4.0
 * @package    Status_Sentry
 * @subpackage Status_Sentry/includes/monitoring
 */
class Status_Sentry_Cron_Logger implements Status_Sentry_Monitoring_Interface {

    /**
     * The cron logs table name.
     *
     * @since    1.4.0
     * @access   private
     * @var      string    $table_name    The table name.
     */
    private $table_name;

    /**
     * Configuration options.
     *
     * @since    1.4.0
     * @access   private
     * @var      array    $config    Configuration options.
     */
    private $config;

    /**
     * Active log entries.
     *
     * @since    1.4.0
     * @access   private
     * @var      array    $active_logs    Active log entries.
     */
    private $active_logs = [];

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.4.0
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'status_sentry_cron_logs';

        // Default configuration
        $this->config = [
            'enabled' => true,
            'log_retention_days' => 30,
            'cleanup_interval' => 86400, // 24 hours
        ];

        // Load saved configuration
        $saved_config = get_option('status_sentry_cron_logger_config', []);
        if (!empty($saved_config)) {
            $this->config = array_merge($this->config, $saved_config);
        }

        // Ensure the cron logs table exists
        $this->ensure_table_exists();
    }

    /**
     * Initialize the monitoring component.
     *
     * @since    1.4.0
     * @return   void
     */
    public function init() {
        // Schedule cleanup of old log entries
        if (!wp_next_scheduled('status_sentry_cleanup_cron_logs')) {
            wp_schedule_event(time(), 'daily', 'status_sentry_cleanup_cron_logs');
        }
        add_action('status_sentry_cleanup_cron_logs', [$this, 'cleanup_old_logs']);

        // Register hooks for cron job tracking
        add_action('status_sentry_before_task_execution', [$this, 'on_task_start'], 10, 2);
        add_action('status_sentry_after_task_execution', [$this, 'on_task_end'], 10, 3);
    }

    /**
     * Register event handlers with the monitoring manager.
     *
     * @since    1.4.0
     * @param    Status_Sentry_Monitoring_Manager    $manager    The monitoring manager instance.
     * @return   void
     */
    public function register_handlers($manager) {
        $manager->register_handler('cron_execution', [$this, 'process_event']);
        $manager->register_handler('cron_error', [$this, 'process_event']);
    }

    /**
     * Process a monitoring event.
     *
     * @since    1.4.0
     * @param    Status_Sentry_Monitoring_Event    $event    The monitoring event to process.
     * @return   void
     */
    public function process_event($event) {
        // Skip processing if cron logging is disabled
        if (!$this->config['enabled']) {
            return;
        }

        $type = $event->get_type();
        $data = $event->get_data();

        if ($type === 'cron_execution') {
            // Process cron execution event
            if (isset($data['hook'])) {
                if ($data['status'] === 'start') {
                    $this->start_log($data['hook'], $data['task_name'] ?? null, $data['scheduled_time'] ?? null, $data['dependencies'] ?? null);
                } elseif ($data['status'] === 'complete' || $data['status'] === 'failed') {
                    $this->end_log($data['hook'], $data['status'], $data['error_message'] ?? null, $data['memory_used'] ?? null, $data['metadata'] ?? null);
                }
            }
        }
    }

    /**
     * Start logging a cron job execution.
     *
     * @since    1.4.0
     * @param    string    $hook             The WordPress hook being executed.
     * @param    string    $task_name        Optional. The task name. Default null.
     * @param    string    $scheduled_time   Optional. The scheduled time. Default current time.
     * @param    array     $dependencies     Optional. Task dependencies. Default null.
     * @return   int|false                   The log ID if successful, false otherwise.
     */
    public function start_log($hook, $task_name = null, $scheduled_time = null, $dependencies = null) {
        global $wpdb;

        // Skip if logging is disabled
        if (!$this->config['enabled']) {
            return false;
        }

        // Ensure the table exists
        if (!$this->ensure_table_exists()) {
            return false;
        }

        // Set default scheduled time if not provided
        if ($scheduled_time === null) {
            $scheduled_time = current_time('mysql');
        }

        // Serialize dependencies if provided
        $dependencies_json = null;
        if ($dependencies !== null) {
            $dependencies_json = wp_json_encode($dependencies);
        }

        // Insert log entry
        $result = $wpdb->insert(
            $this->table_name,
            [
                'hook' => $hook,
                'task_name' => $task_name,
                'scheduled_time' => $scheduled_time,
                'execution_time' => current_time('mysql'),
                'status' => 'running',
                'dependencies' => $dependencies_json,
            ],
            ['%s', '%s', '%s', '%s', '%s', '%s']
        );

        if ($result === false) {
            error_log('Status Sentry: Failed to create cron log entry - ' . $wpdb->last_error);
            return false;
        }

        $log_id = $wpdb->insert_id;
        $this->active_logs[$hook] = $log_id;

        return $log_id;
    }

    /**
     * End logging a cron job execution.
     *
     * @since    1.4.0
     * @param    string    $hook           The WordPress hook being executed.
     * @param    string    $status         The status of the execution (complete, failed).
     * @param    string    $error_message  Optional. Error message if the job failed. Default null.
     * @param    int       $memory_used    Optional. Memory used in bytes. Default null.
     * @param    array     $metadata       Optional. Additional metadata. Default null.
     * @return   bool                      Whether the log was successfully updated.
     */
    public function end_log($hook, $status, $error_message = null, $memory_used = null, $metadata = null) {
        global $wpdb;

        // Skip if logging is disabled
        if (!$this->config['enabled']) {
            return false;
        }

        // Ensure the table exists
        if (!$this->ensure_table_exists()) {
            return false;
        }

        // Get the log ID
        $log_id = $this->active_logs[$hook] ?? null;
        if ($log_id === null) {
            // Try to find the most recent running log for this hook
            $log_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$this->table_name} WHERE hook = %s AND status = 'running' ORDER BY execution_time DESC LIMIT 1",
                $hook
            ));

            if ($log_id === null) {
                error_log(sprintf('Status Sentry: No active log found for hook "%s"', $hook));
                return false;
            }
        }

        // Serialize metadata if provided
        $metadata_json = null;
        if ($metadata !== null) {
            $metadata_json = wp_json_encode($metadata);
        }

        // Get the execution time
        $execution_time = $wpdb->get_var($wpdb->prepare(
            "SELECT execution_time FROM {$this->table_name} WHERE id = %d",
            $log_id
        ));

        if ($execution_time === null) {
            error_log(sprintf('Status Sentry: Failed to get execution time for log ID %d', $log_id));
            return false;
        }

        // Calculate duration
        $start_time = strtotime($execution_time);
        $duration = microtime(true) - $start_time;

        // Update log entry
        $result = $wpdb->update(
            $this->table_name,
            [
                'completion_time' => current_time('mysql'),
                'duration' => $duration,
                'status' => $status,
                'error_message' => $error_message,
                'memory_used' => $memory_used,
                'metadata' => $metadata_json,
            ],
            ['id' => $log_id],
            ['%s', '%f', '%s', '%s', '%d', '%s'],
            ['%d']
        );

        if ($result === false) {
            error_log('Status Sentry: Failed to update cron log entry - ' . $wpdb->last_error);
            return false;
        }

        // Remove from active logs
        unset($this->active_logs[$hook]);

        return true;
    }

    /**
     * Handle task start event.
     *
     * @since    1.4.0
     * @param    string    $task_name    The name of the task.
     * @param    string    $tier         The tier of the task.
     * @return   void
     */
    public function on_task_start($task_name, $tier) {
        // Get the hook from the task registry
        $hook = Status_Sentry_Scheduler::get_task_hook($task_name);
        if ($hook === null) {
            return;
        }

        // Get scheduled time
        $scheduled_time = wp_next_scheduled($hook);
        $scheduled_time_str = $scheduled_time ? date('Y-m-d H:i:s', $scheduled_time) : null;

        // Get dependencies
        $dependencies = Status_Sentry_Scheduler::get_task_dependencies($task_name);

        // Start logging
        $this->start_log($hook, $task_name, $scheduled_time_str, $dependencies);
    }

    /**
     * Handle task end event.
     *
     * @since    1.4.0
     * @param    string    $task_name       The name of the task.
     * @param    string    $tier            The tier of the task.
     * @param    array     $execution_data  Execution data.
     * @return   void
     */
    public function on_task_end($task_name, $tier, $execution_data) {
        // Get the hook from the task registry
        $hook = Status_Sentry_Scheduler::get_task_hook($task_name);
        if ($hook === null) {
            return;
        }

        // End logging
        $this->end_log(
            $hook,
            $execution_data['status'],
            $execution_data['error_message'] ?? null,
            $execution_data['memory_used'] ?? null,
            $execution_data
        );
    }

    /**
     * Clean up old log entries.
     *
     * @since    1.4.0
     * @return   int    The number of log entries deleted.
     */
    public function cleanup_old_logs() {
        global $wpdb;

        // Skip if logging is disabled
        if (!$this->config['enabled']) {
            return 0;
        }

        // Ensure the table exists
        if (!$this->ensure_table_exists()) {
            return 0;
        }

        // Calculate cutoff date
        $cutoff_date = date('Y-m-d H:i:s', strtotime('-' . $this->config['log_retention_days'] . ' days'));

        // Delete old log entries
        $result = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->table_name} WHERE execution_time < %s",
            $cutoff_date
        ));

        if ($result === false) {
            error_log('Status Sentry: Failed to clean up old cron log entries - ' . $wpdb->last_error);
            return 0;
        }

        return $result;
    }

    /**
     * Ensure the table exists.
     *
     * @since    1.4.0
     * @access   private
     * @return   bool    Whether the table exists.
     */
    private function ensure_table_exists() {
        global $wpdb;

        if ($wpdb->get_var("SHOW TABLES LIKE '{$this->table_name}'") != $this->table_name) {
            // Table doesn't exist, create it
            require_once STATUS_SENTRY_PLUGIN_DIR . 'includes/db/migrations/009_create_cron_logs_table.php';
            $migration = new Status_Sentry_Migration_CreateCronLogsTable();
            return $migration->up();
        }

        return true;
    }

    /**
     * Get the monitoring component's status.
     *
     * @since    1.4.0
     * @return   array    The component status as an associative array.
     */
    public function get_status() {
        global $wpdb;

        // Get counts of logs by status
        $counts = [
            'total' => 0,
            'running' => 0,
            'complete' => 0,
            'failed' => 0,
            'skipped' => 0,
        ];

        if ($this->ensure_table_exists()) {
            $counts['total'] = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name}");
            $counts['running'] = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name} WHERE status = 'running'");
            $counts['complete'] = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name} WHERE status = 'complete'");
            $counts['failed'] = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name} WHERE status = 'failed'");
            $counts['skipped'] = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name} WHERE status = 'skipped'");
        }

        // Get recent failures
        $recent_failures = [];
        if ($this->ensure_table_exists()) {
            $failures = $wpdb->get_results(
                "SELECT * FROM {$this->table_name} WHERE status = 'failed' ORDER BY completion_time DESC LIMIT 5"
            );

            if ($failures) {
                foreach ($failures as $failure) {
                    $recent_failures[] = [
                        'hook' => $failure->hook,
                        'task_name' => $failure->task_name,
                        'execution_time' => $failure->execution_time,
                        'completion_time' => $failure->completion_time,
                        'error_message' => $failure->error_message,
                    ];
                }
            }
        }

        return [
            'enabled' => $this->config['enabled'],
            'log_counts' => $counts,
            'recent_failures' => $recent_failures,
            'log_retention_days' => $this->config['log_retention_days'],
        ];
    }

    /**
     * Get the monitoring component's configuration.
     *
     * @since    1.4.0
     * @return   array    The component configuration as an associative array.
     */
    public function get_config() {
        return $this->config;
    }

    /**
     * Update the monitoring component's configuration.
     *
     * @since    1.4.0
     * @param    array    $config    The new configuration as an associative array.
     * @return   bool                Whether the configuration was successfully updated.
     */
    public function update_config($config) {
        $updated = false;

        // Update enabled status if provided
        if (isset($config['enabled'])) {
            $this->config['enabled'] = (bool)$config['enabled'];
            $updated = true;
        }

        // Update log retention days if provided
        if (isset($config['log_retention_days']) && is_numeric($config['log_retention_days'])) {
            $this->config['log_retention_days'] = max(1, (int)$config['log_retention_days']);
            $updated = true;
        }

        // Update cleanup interval if provided
        if (isset($config['cleanup_interval']) && is_numeric($config['cleanup_interval'])) {
            $this->config['cleanup_interval'] = max(3600, (int)$config['cleanup_interval']);
            $updated = true;
        }

        // Save the updated configuration
        if ($updated) {
            update_option('status_sentry_cron_logger_config', $this->config);
        }

        return $updated;
    }
}
