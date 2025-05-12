<?php
/**
 * Self Monitor Component
 *
 * @since      1.3.0
 * @package    Status_Sentry
 * @subpackage Status_Sentry/includes/monitoring
 */

/**
 * Self Monitor Component
 *
 * This class implements the self-monitoring component for Status Sentry.
 * It monitors the plugin's own performance and health.
 *
 * Key responsibilities:
 * - Track task execution times and success rates
 * - Monitor plugin resource usage
 * - Detect anomalies in plugin behavior
 * - Report on plugin health
 *
 * @since      1.3.0
 * @package    Status_Sentry
 * @subpackage Status_Sentry/includes/monitoring
 */
class Status_Sentry_Self_Monitor implements Status_Sentry_Monitoring_Interface {

    /**
     * The task runs table name.
     *
     * @since    1.3.0
     * @access   private
     * @var      string    $table_name    The table name.
     */
    private $table_name;

    /**
     * The baseline instance.
     *
     * @since    1.3.0
     * @access   private
     * @var      Status_Sentry_Baseline    $baseline    The baseline instance.
     */
    private $baseline;

    /**
     * Configuration options for the self-monitor component.
     *
     * @since    1.3.0
     * @access   private
     * @var      array    $config    Configuration options.
     */
    private $config;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.3.0
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'status_sentry_task_runs';

        // Default configuration
        $this->config = [
            'enabled' => true,
            'track_task_execution' => true,
            'track_resource_usage' => true,
            'track_error_rates' => true,
            'retention_days' => 7, // Keep task run data for 7 days
        ];

        // Load saved configuration
        $saved_config = get_option('status_sentry_self_monitor_config', []);
        if (!empty($saved_config)) {
            $this->config = array_merge($this->config, $saved_config);
        }

        // Ensure the task runs table exists
        $this->ensure_table_exists();
    }

    /**
     * Initialize the monitoring component.
     *
     * @since    1.3.0
     * @return   void
     */
    public function init() {
        // Load the baseline component if needed
        if (!isset($this->baseline)) {
            require_once STATUS_SENTRY_PLUGIN_DIR . 'includes/monitoring/class-status-sentry-baseline.php';
            $this->baseline = new Status_Sentry_Baseline();
        }

        // Register hooks for task execution monitoring
        if ($this->config['track_task_execution']) {
            add_action('status_sentry_before_task_execution', [$this, 'on_task_start'], 10, 2);
            add_action('status_sentry_after_task_execution', [$this, 'on_task_end'], 10, 3);
        }

        // Schedule cleanup of old task run data
        if (!wp_next_scheduled('status_sentry_cleanup_task_runs')) {
            wp_schedule_event(time(), 'daily', 'status_sentry_cleanup_task_runs');
        }
        add_action('status_sentry_cleanup_task_runs', [$this, 'cleanup_old_task_runs']);
    }

    /**
     * Register event handlers with the monitoring manager.
     *
     * @since    1.3.0
     * @param    Status_Sentry_Monitoring_Manager    $manager    The monitoring manager instance.
     * @return   void
     */
    public function register_handlers($manager) {
        // Register handlers for self-monitoring events
        $manager->register_handler('task_execution', [$this, 'process_event']);
        $manager->register_handler('plugin_error', [$this, 'process_event']);
    }

    /**
     * Process a monitoring event.
     *
     * @since    1.3.0
     * @param    Status_Sentry_Monitoring_Event    $event    The monitoring event to process.
     * @return   void
     */
    public function process_event($event) {
        // Skip processing if self-monitoring is disabled
        if (!$this->config['enabled']) {
            return;
        }

        $type = $event->get_type();
        $data = $event->get_data();

        if ($type === 'task_execution') {
            // Process task execution event
            if (isset($data['task_id'], $data['duration'], $data['success'])) {
                $this->record_task_execution(
                    $data['task_id'],
                    $data['duration'],
                    $data['success'],
                    $data['memory_usage'] ?? 0,
                    $data['error'] ?? ''
                );
            }
        } elseif ($type === 'plugin_error') {
            // Process plugin error event
            if (isset($data['error'], $data['component'])) {
                $this->record_plugin_error(
                    $data['component'],
                    $data['error'],
                    $data['context'] ?? []
                );
            }
        }
    }

    /**
     * Get the monitoring component's status.
     *
     * @since    1.3.0
     * @return   array    The component status as an associative array.
     */
    public function get_status() {
        global $wpdb;

        // Get task execution statistics
        $total_tasks = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name}");
        $failed_tasks = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name} WHERE success = 0");
        $success_rate = ($total_tasks > 0) ? (($total_tasks - $failed_tasks) / $total_tasks) * 100 : 100;

        // Get average task duration
        $avg_duration = (float) $wpdb->get_var("SELECT AVG(duration) FROM {$this->table_name}");

        return [
            'enabled' => $this->config['enabled'],
            'task_runs' => $total_tasks,
            'failed_tasks' => $failed_tasks,
            'success_rate' => round($success_rate, 2),
            'avg_duration' => round($avg_duration, 2),
            'tracking' => [
                'task_execution' => $this->config['track_task_execution'],
                'resource_usage' => $this->config['track_resource_usage'],
                'error_rates' => $this->config['track_error_rates'],
            ],
        ];
    }

    /**
     * Get the monitoring component's configuration.
     *
     * @since    1.3.0
     * @return   array    The component configuration as an associative array.
     */
    public function get_config() {
        return $this->config;
    }

    /**
     * Update the monitoring component's configuration.
     *
     * @since    1.3.0
     * @param    array    $config    The new configuration as an associative array.
     * @return   bool                Whether the configuration was successfully updated.
     */
    public function update_config($config) {
        // Validate configuration
        if (isset($config['retention_days'])) {
            $config['retention_days'] = max(1, min(30, intval($config['retention_days'])));
        }

        // Update configuration
        $this->config = array_merge($this->config, $config);

        // Save configuration
        update_option('status_sentry_self_monitor_config', $this->config);

        return true;
    }

    /**
     * Handle task start event.
     *
     * @since    1.3.0
     * @param    string    $task_id    The task ID.
     * @param    array     $args       The task arguments.
     * @return   void
     */
    public function on_task_start($task_id, $args) {
        // Store start time in a transient
        set_transient('status_sentry_task_start_' . $task_id, [
            'time' => microtime(true),
            'memory' => memory_get_usage(),
        ], 3600); // 1 hour expiration
    }

    /**
     * Handle task end event.
     *
     * @since    1.3.0
     * @param    string    $task_id    The task ID.
     * @param    array     $args       The task arguments.
     * @param    bool      $success    Whether the task was successful.
     * @return   void
     */
    public function on_task_end($task_id, $args, $success) {
        // Get start time from transient
        $start_data = get_transient('status_sentry_task_start_' . $task_id);
        delete_transient('status_sentry_task_start_' . $task_id);

        if ($start_data) {
            $end_time = microtime(true);
            $duration = $end_time - $start_data['time'];
            $memory_usage = memory_get_usage() - $start_data['memory'];

            // Record task execution
            $this->record_task_execution(
                $task_id,
                $duration,
                $success,
                $memory_usage,
                $success ? '' : 'Task failed'
            );

            // Create a task execution event
            $event = new Status_Sentry_Monitoring_Event(
                'task_execution',
                'self_monitor',
                'scheduler',
                sprintf('Task %s %s in %.2f seconds', $task_id, $success ? 'completed' : 'failed', $duration),
                [
                    'task_id' => $task_id,
                    'duration' => $duration,
                    'success' => $success,
                    'memory_usage' => $memory_usage,
                    'error' => $success ? '' : 'Task failed',
                ],
                $success ? Status_Sentry_Monitoring_Event::PRIORITY_NORMAL : Status_Sentry_Monitoring_Event::PRIORITY_HIGH
            );

            // Dispatch the event
            Status_Sentry_Monitoring_Manager::get_instance()->dispatch($event);
        }
    }

    /**
     * Record task execution.
     *
     * @since    1.3.0
     * @access   private
     * @param    string    $task_id        The task ID.
     * @param    float     $duration       The task duration in seconds.
     * @param    bool      $success        Whether the task was successful.
     * @param    int       $memory_usage   The memory usage in bytes.
     * @param    string    $error          The error message, if any.
     * @return   bool                      Whether the task execution was successfully recorded.
     */
    private function record_task_execution($task_id, $duration, $success, $memory_usage, $error) {
        global $wpdb;

        $result = $wpdb->insert(
            $this->table_name,
            [
                'task_id' => $task_id,
                'start_time' => date('Y-m-d H:i:s', time() - $duration),
                'end_time' => current_time('mysql'),
                'duration' => $duration,
                'success' => $success ? 1 : 0,
                'memory_usage' => $memory_usage,
                'error' => $error,
            ],
            ['%s', '%s', '%s', '%f', '%d', '%d', '%s']
        );

        return ($result !== false);
    }

    /**
     * Record plugin error.
     *
     * @since    1.3.0
     * @access   private
     * @param    string    $component    The component that generated the error.
     * @param    string    $error        The error message.
     * @param    array     $context      The error context.
     * @return   void
     */
    private function record_plugin_error($component, $error, $context) {
        // Log the error
        error_log(sprintf('Status Sentry Error (%s): %s', $component, $error));

        // Create an error event
        $event = new Status_Sentry_Monitoring_Event(
            'plugin_error',
            'self_monitor',
            $component,
            sprintf('Error in %s: %s', $component, $error),
            [
                'component' => $component,
                'error' => $error,
                'context' => $context,
            ],
            Status_Sentry_Monitoring_Event::PRIORITY_HIGH
        );

        // Dispatch the event
        Status_Sentry_Monitoring_Manager::get_instance()->dispatch($event);
    }

    /**
     * Clean up old task run data.
     *
     * @since    1.3.0
     * @return   int    The number of rows deleted.
     */
    public function cleanup_old_task_runs() {
        global $wpdb;

        $cutoff_date = date('Y-m-d H:i:s', strtotime('-' . $this->config['retention_days'] . ' days'));

        $result = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$this->table_name} WHERE start_time < %s",
                $cutoff_date
            )
        );

        return $result;
    }

    /**
     * Ensure the task runs table exists.
     *
     * @since    1.3.0
     * @access   private
     * @return   bool    Whether the table exists or was successfully created.
     */
    private function ensure_table_exists() {
        global $wpdb;

        if ($wpdb->get_var("SHOW TABLES LIKE '{$this->table_name}'") != $this->table_name) {
            // Table doesn't exist, create it
            require_once STATUS_SENTRY_PLUGIN_DIR . 'includes/db/migrations/003_create_task_runs_table.php';
            $migration = new Status_Sentry_Migration_CreateTaskRunsTable();
            return $migration->up();
        }

        return true;
    }
}
