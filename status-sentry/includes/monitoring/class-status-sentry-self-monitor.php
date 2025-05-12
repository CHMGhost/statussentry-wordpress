<?php
/**
 * Self-monitoring class.
 *
 * @since      1.1.0
 * @package    Status_Sentry
 * @subpackage Status_Sentry/includes/monitoring
 */

/**
 * Self-monitoring class.
 *
 * This class monitors the plugin's own performance and behavior, tracking
 * metrics like task execution time, memory usage, and error rates. It helps
 * detect issues with the plugin itself and provides diagnostic information.
 *
 * Key responsibilities:
 * - Track task execution metrics
 * - Monitor memory usage and execution time
 * - Detect and log errors and exceptions
 * - Provide diagnostic information for troubleshooting
 * - Implement the Monitoring_Interface for centralized monitoring
 *
 * @since      1.1.0
 * @package    Status_Sentry
 * @subpackage Status_Sentry/includes/monitoring
 */
class Status_Sentry_Self_Monitor implements Status_Sentry_Monitoring_Interface, Status_Sentry_Monitoring_Handler_Interface {

    /**
     * The task runs table name.
     *
     * @since    1.1.0
     * @access   private
     * @var      string    $table_name    The table name.
     */
    private $table_name;

    /**
     * The baseline instance.
     *
     * @since    1.1.0
     * @access   private
     * @var      Status_Sentry_Baseline    $baseline    The baseline instance.
     */
    private $baseline;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.1.0
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'status_sentry_task_runs';

        require_once STATUS_SENTRY_PLUGIN_DIR . 'includes/monitoring/class-status-sentry-baseline.php';
        $this->baseline = new Status_Sentry_Baseline();
    }

    /**
     * Start monitoring a task.
     *
     * This method creates a new task run record and returns its ID.
     * It should be called at the beginning of a task execution.
     *
     * @since    1.1.0
     * @param    string    $task_name    The name of the task.
     * @param    string    $tier         The tier of the task (critical, standard, intensive, report).
     * @param    array     $metadata     Optional. Additional metadata to store.
     * @return   int|false               The task run ID or false on failure.
     */
    public function start_task($task_name, $tier, $metadata = []) {
        global $wpdb;

        // Ensure the table exists
        if (!$this->ensure_table_exists()) {
            return false;
        }

        // Insert a new task run record
        $result = $wpdb->insert(
            $this->table_name,
            [
                'task_name' => $task_name,
                'tier' => $tier,
                'start_time' => current_time('mysql'),
                'memory_start' => memory_get_usage(),
                'status' => 'running',
                'metadata' => !empty($metadata) ? json_encode($metadata) : null,
            ],
            ['%s', '%s', '%s', '%d', '%s', '%s']
        );

        if ($result === false) {
            error_log('Status Sentry: Failed to start task monitoring - ' . $wpdb->last_error);
            return false;
        }

        return $wpdb->insert_id;
    }

    /**
     * End monitoring a task.
     *
     * This method updates the task run record with end time, duration,
     * memory usage, and status. It should be called at the end of a task execution.
     *
     * @since    1.1.0
     * @param    int       $task_run_id     The task run ID.
     * @param    string    $status          The status of the task (completed, failed, aborted).
     * @param    string    $error_message   Optional. The error message if the task failed.
     * @return   bool                       Whether the task run was successfully updated.
     */
    public function end_task($task_run_id, $status = 'completed', $error_message = null) {
        global $wpdb;

        // Get the task run record
        $task_run = $this->get_task_run($task_run_id);
        if (!$task_run) {
            error_log('Status Sentry: Failed to end task monitoring - Task run not found');
            return false;
        }

        // Calculate duration
        $start_time = strtotime($task_run['start_time']);
        $end_time = time();
        $duration = $end_time - $start_time;

        // Update the task run record
        $result = $wpdb->update(
            $this->table_name,
            [
                'end_time' => current_time('mysql'),
                'duration' => $duration,
                'memory_peak' => memory_get_peak_usage(),
                'memory_end' => memory_get_usage(),
                'status' => $status,
                'error_message' => $error_message,
            ],
            ['id' => $task_run_id],
            ['%s', '%f', '%d', '%d', '%s', '%s'],
            ['%d']
        );

        if ($result === false) {
            error_log('Status Sentry: Failed to end task monitoring - ' . $wpdb->last_error);
            return false;
        }

        // Record metrics for baseline
        $this->baseline->record_metric('task_duration', $task_run['task_name'], $duration);
        $this->baseline->record_metric('task_memory_usage', $task_run['task_name'], memory_get_peak_usage() - $task_run['memory_start']);

        return true;
    }

    /**
     * Get a task run record.
     *
     * @since    1.1.0
     * @param    int       $task_run_id    The task run ID.
     * @return   array|false               The task run record or false if not found.
     */
    public function get_task_run($task_run_id) {
        global $wpdb;

        $query = $wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d",
            $task_run_id
        );

        return $wpdb->get_row($query, ARRAY_A);
    }

    /**
     * Get recent task runs.
     *
     * @since    1.1.0
     * @param    string    $task_name    Optional. Filter by task name.
     * @param    string    $status       Optional. Filter by status.
     * @param    int       $limit        Optional. The maximum number of records to return. Default 10.
     * @return   array                   The task run records.
     */
    public function get_recent_task_runs($task_name = null, $status = null, $limit = 10) {
        global $wpdb;

        $where = [];
        $prepare_args = [];

        if ($task_name) {
            $where[] = 'task_name = %s';
            $prepare_args[] = $task_name;
        }

        if ($status) {
            $where[] = 'status = %s';
            $prepare_args[] = $status;
        }

        $where_clause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        $prepare_args[] = absint($limit);

        $query = $wpdb->prepare(
            "SELECT * FROM {$this->table_name} $where_clause ORDER BY start_time DESC LIMIT %d",
            ...$prepare_args
        );

        return $wpdb->get_results($query, ARRAY_A);
    }

    /**
     * Ensure the task runs table exists.
     *
     * @since    1.1.0
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

    /**
     * Initialize the monitoring component.
     *
     * @since    1.3.0
     * @return   void
     */
    public function init() {
        // Nothing to initialize here, as the constructor already sets up everything
    }

    /**
     * Register event handlers with the monitoring manager.
     *
     * @since    1.3.0
     * @param    Status_Sentry_Monitoring_Manager    $manager    The monitoring manager instance.
     * @return   void
     */
    public function register_handlers($manager) {
        $manager->register_handler($this);
    }

    /**
     * Process a monitoring event.
     *
     * @since    1.3.0
     * @param    Status_Sentry_Monitoring_Event    $event    The monitoring event to process.
     * @return   void
     */
    public function process_event($event) {
        // This method is called by the monitoring manager when an event is dispatched
        // We'll handle events in the handle() method instead
    }

    /**
     * Get the monitoring component's status.
     *
     * @since    1.3.0
     * @return   array    The component status as an associative array.
     */
    public function get_status() {
        global $wpdb;

        // Get counts of task runs by status
        $counts = [
            'total' => 0,
            'running' => 0,
            'completed' => 0,
            'failed' => 0,
            'partial' => 0,
        ];

        if ($this->ensure_table_exists()) {
            $counts['total'] = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name}");
            $counts['running'] = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name} WHERE status = 'running'");
            $counts['completed'] = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name} WHERE status = 'completed'");
            $counts['failed'] = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name} WHERE status = 'failed'");
            $counts['partial'] = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name} WHERE status = 'partial'");
        }

        // Get recent failures
        $recent_failures = $this->get_recent_task_runs(null, 'failed', 5);

        return [
            'task_run_counts' => $counts,
            'recent_failures' => $recent_failures,
            'memory_usage' => memory_get_usage(),
            'memory_peak' => memory_get_peak_usage(),
        ];
    }

    /**
     * Get the monitoring component's configuration.
     *
     * @since    1.3.0
     * @return   array    The component configuration as an associative array.
     */
    public function get_config() {
        return [
            'enabled' => true, // Self-monitoring is always enabled
            'retention_days' => 7, // Keep task runs for 7 days
        ];
    }

    /**
     * Update the monitoring component's configuration.
     *
     * @since    1.3.0
     * @param    array    $config    The new configuration as an associative array.
     * @return   bool                Whether the configuration was successfully updated.
     */
    public function update_config($config) {
        // Self-monitoring doesn't have any configurable options yet
        return true;
    }

    /**
     * Get the handler's priority.
     *
     * @since    1.3.0
     * @return   int    The handler's priority (0-100).
     */
    public function get_priority() {
        return 50; // Medium priority
    }

    /**
     * Get the event types this handler can process.
     *
     * @since    1.3.0
     * @return   array    An array of event types.
     */
    public function get_handled_types() {
        return [
            Status_Sentry_Monitoring_Event::TYPE_PERFORMANCE,
            Status_Sentry_Monitoring_Event::TYPE_ERROR,
            Status_Sentry_Monitoring_Event::TYPE_CRITICAL,
        ];
    }

    /**
     * Check if this handler can handle the given event.
     *
     * @since    1.3.0
     * @param    Status_Sentry_Monitoring_Event    $event    The event to check.
     * @return   bool                                        Whether this handler can handle the event.
     */
    public function can_handle($event) {
        // We handle performance events related to tasks
        if ($event->get_type() === Status_Sentry_Monitoring_Event::TYPE_PERFORMANCE &&
            $event->get_source() === 'scheduler') {
            return true;
        }

        // We handle error events related to tasks
        if (($event->get_type() === Status_Sentry_Monitoring_Event::TYPE_ERROR ||
             $event->get_type() === Status_Sentry_Monitoring_Event::TYPE_CRITICAL) &&
            $event->get_source() === 'scheduler') {
            return true;
        }

        return false;
    }

    /**
     * Handle a monitoring event.
     *
     * @since    1.3.0
     * @param    Status_Sentry_Monitoring_Event    $event    The event to handle.
     * @return   bool                                        Whether the event was successfully handled.
     */
    public function handle($event) {
        $data = $event->get_data();

        // Handle task performance events
        if ($event->get_type() === Status_Sentry_Monitoring_Event::TYPE_PERFORMANCE &&
            $event->get_source() === 'scheduler' &&
            isset($data['task_name'])) {

            // Record performance metrics
            if (isset($data['execution_time'])) {
                $this->baseline->record_metric('task_duration', $data['task_name'], $data['execution_time']);
            }

            if (isset($data['memory_used'])) {
                $this->baseline->record_metric('task_memory_usage', $data['task_name'], $data['memory_used']);
            }

            return true;
        }

        // Handle task error events
        if (($event->get_type() === Status_Sentry_Monitoring_Event::TYPE_ERROR ||
             $event->get_type() === Status_Sentry_Monitoring_Event::TYPE_CRITICAL) &&
            $event->get_source() === 'scheduler' &&
            isset($data['task_name'])) {

            // Record error occurrence
            $this->baseline->record_metric('task_errors', $data['task_name'], 1, [
                'last_error' => $event->get_message(),
                'last_error_time' => date('Y-m-d H:i:s'),
            ]);

            return true;
        }

        return false;
    }
}
