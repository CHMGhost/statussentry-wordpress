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
 *
 * @since      1.1.0
 * @package    Status_Sentry
 * @subpackage Status_Sentry/includes/monitoring
 */
class Status_Sentry_Self_Monitor {

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
}
