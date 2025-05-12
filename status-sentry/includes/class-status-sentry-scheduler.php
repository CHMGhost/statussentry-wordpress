<?php
/**
 * Scheduler class.
 *
 * @since      1.0.0
 * @package    Status_Sentry
 * @subpackage Status_Sentry/includes
 */

/**
 * Scheduler Class
 *
 * This class manages WordPress cron jobs for the Status Sentry plugin. It handles
 * scheduling, unscheduling, and execution of background tasks such as processing
 * the event queue and cleaning up old data.
 *
 * Key responsibilities:
 * - Register custom cron schedules (e.g., every 5 minutes)
 * - Schedule recurring tasks during plugin activation
 * - Unschedule tasks during plugin deactivation
 * - Process the event queue at regular intervals
 * - Clean up old data to prevent database bloat
 * - Handle errors and provide detailed logging
 * - Enforce resource budgets for tasks
 * - Track task execution history
 * - Implement tiered scheduling with different priorities
 * - Provide per-task locking to prevent concurrent execution
 *
 * The scheduler uses WordPress's built-in cron system (WP-Cron) which runs
 * when a page is loaded. For high-traffic sites, this works well. For low-traffic
 * sites, consider setting up a server cron job to trigger WP-Cron regularly.
 *
 * @since      1.0.0
 * @package    Status_Sentry
 * @subpackage Status_Sentry/includes
 * @author     Status Sentry Team
 */
class Status_Sentry_Scheduler {

    /**
     * Task registry.
     *
     * @since    1.1.0
     * @access   private
     * @var      array    $tasks    The registered tasks.
     */
    private static $tasks = [];

    /**
     * Self-monitor instance.
     *
     * @since    1.1.0
     * @access   private
     * @var      Status_Sentry_Self_Monitor    $self_monitor    The self-monitor instance.
     */
    private static $self_monitor = null;

    /**
     * Resource manager instance.
     *
     * @since    1.1.0
     * @access   private
     * @var      Status_Sentry_Resource_Manager    $resource_manager    The resource manager instance.
     */
    private static $resource_manager = null;

    /**
     * Initialize the scheduler.
     *
     * @since    1.1.0
     */
    public static function init() {
        // Load dependencies
        require_once STATUS_SENTRY_PLUGIN_DIR . 'includes/monitoring/class-status-sentry-self-monitor.php';
        require_once STATUS_SENTRY_PLUGIN_DIR . 'includes/monitoring/class-status-sentry-resource-manager.php';

        // Initialize monitoring components
        self::$self_monitor = new Status_Sentry_Self_Monitor();
        self::$resource_manager = new Status_Sentry_Resource_Manager();

        // Register default tasks
        self::register_task('process_queue', 'status_sentry_process_queue', 'standard', 'five_minutes');
        self::register_task('cleanup', 'status_sentry_cleanup', 'intensive', 'daily');

        // Allow other components to register tasks
        do_action('status_sentry_register_tasks');
    }

    /**
     * Register a task.
     *
     * @since    1.1.0
     * @param    string    $task_name      The name of the task.
     * @param    string    $hook           The WordPress hook to trigger.
     * @param    string    $tier           The tier of the task (critical, standard, intensive, report).
     * @param    string    $schedule       The schedule for the task (e.g., 'five_minutes', 'hourly', 'daily').
     * @param    array     $callback       Optional. The callback function to execute. Default is [self::class, $task_name].
     * @param    array     $args           Optional. Arguments to pass to the callback. Default is [].
     * @return   bool                      Whether the task was successfully registered.
     */
    public static function register_task($task_name, $hook, $tier, $schedule, $callback = null, $args = []) {
        // Validate tier
        $valid_tiers = ['critical', 'standard', 'intensive', 'report'];
        if (!in_array($tier, $valid_tiers)) {
            error_log(sprintf('Status Sentry: Invalid tier "%s" for task "%s", defaulting to "standard"', $tier, $task_name));
            $tier = 'standard';
        }

        // Set default callback if not provided
        if ($callback === null) {
            $callback = [self::class, $task_name];
        }

        // Register the task
        self::$tasks[$task_name] = [
            'hook' => $hook,
            'tier' => $tier,
            'schedule' => $schedule,
            'callback' => $callback,
            'args' => $args,
        ];

        // Register the callback
        add_action($hook, function() use ($task_name, $callback, $args) {
            self::execute_task($task_name, $callback, $args);
        });

        return true;
    }

    /**
     * Execute a task with monitoring and resource management.
     *
     * @since    1.1.0
     * @access   private
     * @param    string    $task_name    The name of the task.
     * @param    callable  $callback     The callback function to execute.
     * @param    array     $args         Arguments to pass to the callback.
     * @return   mixed                   The result of the callback.
     */
    private static function execute_task($task_name, $callback, $args = []) {
        // Check if task is registered
        if (!isset(self::$tasks[$task_name])) {
            error_log(sprintf('Status Sentry: Task "%s" not found in registry', $task_name));
            return false;
        }

        $task = self::$tasks[$task_name];
        $tier = $task['tier'];

        // Check if task is already running (locking)
        if (self::is_task_running($task_name)) {
            error_log(sprintf('Status Sentry: Task "%s" is already running, skipping execution', $task_name));
            return false;
        }

        // Start monitoring
        $task_run_id = self::$self_monitor->start_task($task_name, $tier);
        $start_time = microtime(true);
        $memory_start = memory_get_usage();

        try {
            // Execute the callback
            error_log(sprintf('Status Sentry: Starting task "%s" (%s tier)', $task_name, $tier));
            $result = call_user_func_array($callback, $args);

            // End monitoring with success status
            self::$self_monitor->end_task($task_run_id, 'completed');

            // Log completion
            $execution_time = microtime(true) - $start_time;
            error_log(sprintf(
                'Status Sentry: Task "%s" completed successfully in %.2f seconds',
                $task_name,
                $execution_time
            ));

            return $result;
        } catch (Exception $e) {
            // End monitoring with failure status
            self::$self_monitor->end_task($task_run_id, 'failed', $e->getMessage());

            // Log error
            error_log(sprintf(
                'Status Sentry: Task "%s" failed with exception: %s',
                $task_name,
                $e->getMessage()
            ));

            // Try to log a stack trace if available
            if (method_exists($e, 'getTraceAsString')) {
                error_log('Status Sentry: Stack trace - ' . $e->getTraceAsString());
            }

            return false;
        } catch (Error $e) {
            // End monitoring with failure status
            self::$self_monitor->end_task($task_run_id, 'failed', $e->getMessage());

            // Log error
            error_log(sprintf(
                'Status Sentry: Task "%s" failed with error: %s',
                $task_name,
                $e->getMessage()
            ));

            // Try to log a stack trace if available
            if (method_exists($e, 'getTraceAsString')) {
                error_log('Status Sentry: Stack trace - ' . $e->getTraceAsString());
            }

            return false;
        }
    }

    /**
     * Check if a task is already running.
     *
     * @since    1.1.0
     * @access   private
     * @param    string    $task_name    The name of the task.
     * @return   bool                    Whether the task is already running.
     */
    private static function is_task_running($task_name) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'status_sentry_task_runs';

        // Check if the table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            return false;
        }

        // Check for running tasks
        $running_tasks = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE task_name = %s AND status = 'running' AND start_time > %s",
            $task_name,
            date('Y-m-d H:i:s', time() - 3600) // Only consider tasks started in the last hour
        ));

        return $running_tasks > 0;
    }

    /**
     * Schedule background tasks.
     *
     * This method sets up the recurring background tasks needed by the plugin.
     * It registers custom cron schedules and schedules all registered tasks.
     *
     * @since    1.0.0
     * @return   bool    Whether all tasks were successfully scheduled.
     */
    public static function schedule_tasks() {
        $success = true;

        // Initialize the scheduler
        self::init();

        // Register custom cron schedules
        add_filter('cron_schedules', [self::class, 'add_cron_schedules']);

        // Schedule all registered tasks
        foreach (self::$tasks as $task_name => $task) {
            // Add random offset to prevent all tasks from running at the same time
            $offset = mt_rand(0, 60); // Random offset between 0 and 60 seconds

            if (!wp_next_scheduled($task['hook'])) {
                $result = wp_schedule_event(time() + $offset, $task['schedule'], $task['hook']);
                if ($result === false) {
                    error_log(sprintf('Status Sentry: Failed to schedule task "%s"', $task_name));
                    $success = false;
                } else {
                    error_log(sprintf('Status Sentry: Successfully scheduled task "%s" (every %s)', $task_name, $task['schedule']));
                }
            } else {
                error_log(sprintf('Status Sentry: Task "%s" already scheduled', $task_name));
            }
        }

        // Verify that the tasks were scheduled
        self::verify_scheduled_tasks();

        return $success;
    }

    /**
     * Verify that tasks are properly scheduled.
     *
     * This method checks if the required cron tasks are properly scheduled
     * and logs any issues it finds. It's useful for debugging cron problems.
     *
     * @since    1.0.0
     * @access   private
     * @return   bool    Whether all required tasks are properly scheduled.
     */
    private static function verify_scheduled_tasks() {
        $all_scheduled = true;
        $cron_array = _get_cron_array();

        if (empty($cron_array)) {
            error_log('Status Sentry: No cron jobs found in WordPress');
            return false;
        }

        // Check each registered task
        foreach (self::$tasks as $task_name => $task) {
            $task_scheduled = false;
            foreach ($cron_array as $timestamp => $cron_job) {
                if (isset($cron_job[$task['hook']])) {
                    $task_scheduled = true;
                    $next_run = date('Y-m-d H:i:s', $timestamp);
                    error_log(sprintf('Status Sentry: Task "%s" scheduled to run at %s', $task_name, $next_run));
                    break;
                }
            }

            if (!$task_scheduled) {
                error_log(sprintf('Status Sentry: Task "%s" not found in scheduled cron jobs', $task_name));
                $all_scheduled = false;
            }
        }

        return $all_scheduled;
    }

    /**
     * Unschedule background tasks.
     *
     * This method removes all scheduled tasks created by the plugin.
     * It's typically called during plugin deactivation to clean up
     * any scheduled cron jobs.
     *
     * @since    1.0.0
     * @return   bool    Whether all tasks were successfully unscheduled.
     */
    public static function unschedule_tasks() {
        $success = true;

        // Initialize the scheduler to ensure tasks are registered
        self::init();

        // Unschedule all registered tasks
        foreach (self::$tasks as $task_name => $task) {
            $timestamp = wp_next_scheduled($task['hook']);
            if ($timestamp) {
                $result = wp_unschedule_event($timestamp, $task['hook']);
                if ($result === false) {
                    error_log(sprintf('Status Sentry: Failed to unschedule task "%s"', $task_name));
                    $success = false;
                } else {
                    error_log(sprintf('Status Sentry: Successfully unscheduled task "%s"', $task_name));
                }
            } else {
                error_log(sprintf('Status Sentry: No task "%s" found to unschedule', $task_name));
            }

            // Alternative approach: clear all hooks
            $result = wp_clear_scheduled_hook($task['hook']);
            if ($result > 0) {
                error_log(sprintf('Status Sentry: Cleared %d "%s" tasks using wp_clear_scheduled_hook', $result, $task_name));
            }
        }

        return $success;
    }

    /**
     * Add custom cron schedules.
     *
     * @since    1.0.0
     * @param    array    $schedules    The existing cron schedules.
     * @return   array                  The modified cron schedules.
     */
    public static function add_cron_schedules($schedules) {
        // Add a 5-minute schedule
        $schedules['five_minutes'] = [
            'interval' => 300, // 5 minutes in seconds
            'display' => __('Every 5 Minutes', 'status-sentry-wp'),
        ];

        // Add a 15-minute schedule
        $schedules['fifteen_minutes'] = [
            'interval' => 900, // 15 minutes in seconds
            'display' => __('Every 15 Minutes', 'status-sentry-wp'),
        ];

        // Add a 30-minute schedule
        $schedules['thirty_minutes'] = [
            'interval' => 1800, // 30 minutes in seconds
            'display' => __('Every 30 Minutes', 'status-sentry-wp'),
        ];

        // Add a weekly schedule
        $schedules['weekly'] = [
            'interval' => 604800, // 7 days in seconds
            'display' => __('Once Weekly', 'status-sentry-wp'),
        ];

        return $schedules;
    }

    /**
     * Process the event queue.
     *
     * This method is called by WordPress cron to process events in the queue.
     * It creates an instance of the EventProcessor class and calls its
     * process_events method to handle the actual processing.
     *
     * The method includes error handling to catch and log any exceptions
     * that occur during processing, ensuring that the cron job doesn't
     * fail silently.
     *
     * @since    1.0.0
     * @param    int     $batch_size    Optional. The number of events to process in a batch. Default 100.
     * @return   int                    The number of events processed.
     */
    public static function process_queue($batch_size = 100) {
        $start_time = microtime(true);
        $memory_start = memory_get_usage();
        $db_queries_start = get_num_queries();

        error_log('Status Sentry: Starting queue processing');

        try {
            // Validate batch size
            $batch_size = absint($batch_size);
            if ($batch_size <= 0) {
                $batch_size = 100; // Default to 100 if invalid
            }

            // Create an event processor
            $processor = new Status_Sentry_Event_Processor();

            // Process events with resource monitoring
            $processed_count = 0;
            $remaining = $batch_size;

            // Process in smaller batches to allow for resource checks
            $batch_chunk_size = min(25, $batch_size);

            while ($remaining > 0) {
                // Check if we should continue based on resource usage
                if (!self::$resource_manager->should_continue(
                    'standard',
                    $start_time,
                    $memory_start,
                    get_num_queries() - $db_queries_start
                )) {
                    error_log('Status Sentry: Stopping queue processing early due to resource constraints');
                    break;
                }

                // Process a chunk of events
                $chunk_size = min($batch_chunk_size, $remaining);
                $chunk_processed = $processor->process_events($chunk_size);
                $processed_count += $chunk_processed;

                // If we processed fewer events than requested, the queue is empty
                if ($chunk_processed < $chunk_size) {
                    break;
                }

                $remaining -= $chunk_size;
            }

            // Calculate execution time
            $execution_time = microtime(true) - $start_time;
            $memory_used = memory_get_usage() - $memory_start;
            $db_queries = get_num_queries() - $db_queries_start;

            // Log the result
            if ($processed_count > 0) {
                error_log(sprintf(
                    'Status Sentry: Processed %d events from the queue in %.2f seconds (memory: %d MB, queries: %d).',
                    $processed_count,
                    $execution_time,
                    $memory_used / (1024 * 1024),
                    $db_queries
                ));
            } else {
                error_log(sprintf(
                    'Status Sentry: No events processed from the queue (took %.2f seconds).',
                    $execution_time
                ));
            }

            return $processed_count;
        } catch (Exception $e) {
            error_log('Status Sentry: Exception during queue processing - ' . $e->getMessage());

            // Try to log a stack trace if available
            if (method_exists($e, 'getTraceAsString')) {
                error_log('Status Sentry: Stack trace - ' . $e->getTraceAsString());
            }

            return 0;
        } catch (Error $e) {
            error_log('Status Sentry: Error during queue processing - ' . $e->getMessage());

            // Try to log a stack trace if available
            if (method_exists($e, 'getTraceAsString')) {
                error_log('Status Sentry: Stack trace - ' . $e->getTraceAsString());
            }

            return 0;
        } finally {
            // Always log completion, even if an error occurred
            $total_time = microtime(true) - $start_time;
            error_log(sprintf('Status Sentry: Queue processing completed in %.2f seconds', $total_time));

            // Record metrics for baseline
            if (self::$self_monitor !== null) {
                self::$self_monitor->baseline->record_metric('queue_processing_time', 'standard', $total_time);
                self::$self_monitor->baseline->record_metric('queue_processing_memory', 'standard', memory_get_peak_usage() - $memory_start);
                self::$self_monitor->baseline->record_metric('queue_processing_queries', 'standard', get_num_queries() - $db_queries_start);
            }
        }
    }

    /**
     * Clean up old data.
     *
     * This method is called by WordPress cron to clean up old data from the database.
     * It removes:
     * - Events older than 30 days from the events table
     * - Processed queue items older than 7 days from the queue table
     * - Failed queue items older than 14 days from the queue table
     * - Task runs older than 30 days from the task_runs table
     *
     * The method includes error handling and detailed logging to track
     * the cleanup process and any issues that occur.
     *
     * @since    1.0.0
     * @return   array    Statistics about the cleanup operation.
     */
    public static function cleanup() {
        global $wpdb;
        $start_time = microtime(true);
        $memory_start = memory_get_usage();
        $db_queries_start = get_num_queries();

        $stats = [
            'events_deleted' => 0,
            'processed_queue_items_deleted' => 0,
            'failed_queue_items_deleted' => 0,
            'task_runs_deleted' => 0,
            'errors' => 0,
        ];

        error_log('Status Sentry: Starting database cleanup');

        try {
            // Get retention settings (could be made configurable in the future)
            $events_retention_days = apply_filters('status_sentry_events_retention_days', 30);
            $processed_queue_retention_days = apply_filters('status_sentry_processed_queue_retention_days', 7);
            $failed_queue_retention_days = apply_filters('status_sentry_failed_queue_retention_days', 14);
            $task_runs_retention_days = apply_filters('status_sentry_task_runs_retention_days', 30);

            error_log(sprintf(
                'Status Sentry: Using retention periods - Events: %d days, Processed queue: %d days, Failed queue: %d days, Task runs: %d days',
                $events_retention_days,
                $processed_queue_retention_days,
                $failed_queue_retention_days,
                $task_runs_retention_days
            ));

            // Clean up old events
            $events_table = $wpdb->prefix . 'status_sentry_events';
            if ($wpdb->get_var("SHOW TABLES LIKE '$events_table'") == $events_table) {
                // Check if we should continue based on resource usage
                if (!self::$resource_manager->should_continue('intensive', $start_time, $memory_start, get_num_queries() - $db_queries_start)) {
                    error_log('Status Sentry: Skipping events cleanup due to resource constraints');
                } else {
                    // Get table size before cleanup
                    $table_size_before = $wpdb->get_var("SELECT COUNT(*) FROM $events_table");

                    // Delete events older than retention period
                    $cutoff_date = date('Y-m-d H:i:s', time() - ($events_retention_days * 86400));
                    $deleted = $wpdb->query($wpdb->prepare(
                        "DELETE FROM $events_table WHERE event_time < %s LIMIT 5000",
                        $cutoff_date
                    ));

                    if ($deleted === false) {
                        error_log('Status Sentry: Error deleting old events - ' . $wpdb->last_error);
                        $stats['errors']++;
                    } else {
                        $stats['events_deleted'] = $deleted;
                        error_log(sprintf(
                            'Status Sentry: Deleted %d old events (older than %s).',
                            $deleted,
                            $cutoff_date
                        ));

                        // Get table size after cleanup
                        $table_size_after = $wpdb->get_var("SELECT COUNT(*) FROM $events_table");
                        error_log(sprintf(
                            'Status Sentry: Events table size: %d rows before, %d rows after cleanup',
                            $table_size_before,
                            $table_size_after
                        ));
                    }
                }
            } else {
                error_log('Status Sentry: Events table does not exist, skipping cleanup');
            }

            // Clean up old queue items
            $queue_table = $wpdb->prefix . 'status_sentry_queue';
            if ($wpdb->get_var("SHOW TABLES LIKE '$queue_table'") == $queue_table) {
                // Check if we should continue based on resource usage
                if (!self::$resource_manager->should_continue('intensive', $start_time, $memory_start, get_num_queries() - $db_queries_start)) {
                    error_log('Status Sentry: Skipping queue cleanup due to resource constraints');
                } else {
                    // Get table size before cleanup
                    $table_size_before = $wpdb->get_var("SELECT COUNT(*) FROM $queue_table");

                    // Delete processed queue items older than retention period
                    $processed_cutoff_date = date('Y-m-d H:i:s', time() - ($processed_queue_retention_days * 86400));
                    $deleted = $wpdb->query($wpdb->prepare(
                        "DELETE FROM $queue_table WHERE status = 'processed' AND created_at < %s LIMIT 5000",
                        $processed_cutoff_date
                    ));

                    if ($deleted === false) {
                        error_log('Status Sentry: Error deleting old processed queue items - ' . $wpdb->last_error);
                        $stats['errors']++;
                    } else {
                        $stats['processed_queue_items_deleted'] = $deleted;
                        error_log(sprintf(
                            'Status Sentry: Deleted %d old processed queue items (older than %s).',
                            $deleted,
                            $processed_cutoff_date
                        ));
                    }

                    // Delete failed queue items older than retention period
                    $failed_cutoff_date = date('Y-m-d H:i:s', time() - ($failed_queue_retention_days * 86400));
                    $deleted = $wpdb->query($wpdb->prepare(
                        "DELETE FROM $queue_table WHERE status = 'failed' AND created_at < %s LIMIT 5000",
                        $failed_cutoff_date
                    ));

                    if ($deleted === false) {
                        error_log('Status Sentry: Error deleting old failed queue items - ' . $wpdb->last_error);
                        $stats['errors']++;
                    } else {
                        $stats['failed_queue_items_deleted'] = $deleted;
                        error_log(sprintf(
                            'Status Sentry: Deleted %d old failed queue items (older than %s).',
                            $deleted,
                            $failed_cutoff_date
                        ));
                    }

                    // Get table size after cleanup
                    $table_size_after = $wpdb->get_var("SELECT COUNT(*) FROM $queue_table");
                    error_log(sprintf(
                        'Status Sentry: Queue table size: %d rows before, %d rows after cleanup',
                        $table_size_before,
                        $table_size_after
                    ));
                }
            } else {
                error_log('Status Sentry: Queue table does not exist, skipping cleanup');
            }

            // Clean up old task runs
            $task_runs_table = $wpdb->prefix . 'status_sentry_task_runs';
            if ($wpdb->get_var("SHOW TABLES LIKE '$task_runs_table'") == $task_runs_table) {
                // Check if we should continue based on resource usage
                if (!self::$resource_manager->should_continue('intensive', $start_time, $memory_start, get_num_queries() - $db_queries_start)) {
                    error_log('Status Sentry: Skipping task runs cleanup due to resource constraints');
                } else {
                    // Get table size before cleanup
                    $table_size_before = $wpdb->get_var("SELECT COUNT(*) FROM $task_runs_table");

                    // Delete task runs older than retention period
                    $cutoff_date = date('Y-m-d H:i:s', time() - ($task_runs_retention_days * 86400));
                    $deleted = $wpdb->query($wpdb->prepare(
                        "DELETE FROM $task_runs_table WHERE start_time < %s LIMIT 5000",
                        $cutoff_date
                    ));

                    if ($deleted === false) {
                        error_log('Status Sentry: Error deleting old task runs - ' . $wpdb->last_error);
                        $stats['errors']++;
                    } else {
                        $stats['task_runs_deleted'] = $deleted;
                        error_log(sprintf(
                            'Status Sentry: Deleted %d old task runs (older than %s).',
                            $deleted,
                            $cutoff_date
                        ));

                        // Get table size after cleanup
                        $table_size_after = $wpdb->get_var("SELECT COUNT(*) FROM $task_runs_table");
                        error_log(sprintf(
                            'Status Sentry: Task runs table size: %d rows before, %d rows after cleanup',
                            $table_size_before,
                            $table_size_after
                        ));
                    }
                }
            } else {
                error_log('Status Sentry: Task runs table does not exist, skipping cleanup');
            }

            // Optimize tables if possible
            if (method_exists($wpdb, 'query') && self::$resource_manager->should_continue('intensive', $start_time, $memory_start, get_num_queries() - $db_queries_start)) {
                $wpdb->query("OPTIMIZE TABLE $events_table");
                $wpdb->query("OPTIMIZE TABLE $queue_table");
                $wpdb->query("OPTIMIZE TABLE $task_runs_table");
                error_log('Status Sentry: Optimized database tables');
            }
        } catch (Exception $e) {
            error_log('Status Sentry: Exception during cleanup - ' . $e->getMessage());
            $stats['errors']++;
        } catch (Error $e) {
            error_log('Status Sentry: Error during cleanup - ' . $e->getMessage());
            $stats['errors']++;
        } finally {
            // Always log completion, even if an error occurred
            $total_time = microtime(true) - $start_time;
            $memory_used = memory_get_peak_usage() - $memory_start;
            $db_queries = get_num_queries() - $db_queries_start;

            error_log(sprintf(
                'Status Sentry: Cleanup completed in %.2f seconds (memory: %d MB, queries: %d). Stats: %s',
                $total_time,
                $memory_used / (1024 * 1024),
                $db_queries,
                json_encode($stats)
            ));

            // Record metrics for baseline
            if (self::$self_monitor !== null) {
                self::$self_monitor->baseline->record_metric('cleanup_time', 'intensive', $total_time);
                self::$self_monitor->baseline->record_metric('cleanup_memory', 'intensive', $memory_used);
                self::$self_monitor->baseline->record_metric('cleanup_queries', 'intensive', $db_queries);
            }
        }

        return $stats;
    }
}
