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
     * Schedule background tasks.
     *
     * This method sets up the recurring background tasks needed by the plugin:
     * 1. Event queue processing (every 5 minutes)
     * 2. Data cleanup (daily)
     *
     * It also registers the necessary hooks for custom cron schedules and
     * task handlers. This method is typically called during plugin activation.
     *
     * @since    1.0.0
     * @return   bool    Whether all tasks were successfully scheduled.
     */
    public static function schedule_tasks() {
        $success = true;

        // Register custom cron schedules
        add_filter('cron_schedules', [self::class, 'add_cron_schedules']);

        // Schedule event processing
        if (!wp_next_scheduled('status_sentry_process_queue')) {
            $result = wp_schedule_event(time(), 'five_minutes', 'status_sentry_process_queue');
            if ($result === false) {
                error_log('Status Sentry: Failed to schedule process_queue task');
                $success = false;
            } else {
                error_log('Status Sentry: Successfully scheduled process_queue task (every 5 minutes)');
            }
        } else {
            error_log('Status Sentry: process_queue task already scheduled');
        }

        // Schedule cleanup
        if (!wp_next_scheduled('status_sentry_cleanup')) {
            $result = wp_schedule_event(time(), 'daily', 'status_sentry_cleanup');
            if ($result === false) {
                error_log('Status Sentry: Failed to schedule cleanup task');
                $success = false;
            } else {
                error_log('Status Sentry: Successfully scheduled cleanup task (daily)');
            }
        } else {
            error_log('Status Sentry: cleanup task already scheduled');
        }

        // Register cron handlers
        add_action('status_sentry_process_queue', [self::class, 'process_queue']);
        add_action('status_sentry_cleanup', [self::class, 'cleanup']);

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

        // Check for process_queue task
        $process_queue_scheduled = false;
        foreach ($cron_array as $timestamp => $cron_job) {
            if (isset($cron_job['status_sentry_process_queue'])) {
                $process_queue_scheduled = true;
                $next_run = date('Y-m-d H:i:s', $timestamp);
                error_log("Status Sentry: process_queue task scheduled to run at $next_run");
                break;
            }
        }

        if (!$process_queue_scheduled) {
            error_log('Status Sentry: process_queue task not found in scheduled cron jobs');
            $all_scheduled = false;
        }

        // Check for cleanup task
        $cleanup_scheduled = false;
        foreach ($cron_array as $timestamp => $cron_job) {
            if (isset($cron_job['status_sentry_cleanup'])) {
                $cleanup_scheduled = true;
                $next_run = date('Y-m-d H:i:s', $timestamp);
                error_log("Status Sentry: cleanup task scheduled to run at $next_run");
                break;
            }
        }

        if (!$cleanup_scheduled) {
            error_log('Status Sentry: cleanup task not found in scheduled cron jobs');
            $all_scheduled = false;
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

        // Unschedule event processing
        $timestamp = wp_next_scheduled('status_sentry_process_queue');
        if ($timestamp) {
            $result = wp_unschedule_event($timestamp, 'status_sentry_process_queue');
            if ($result === false) {
                error_log('Status Sentry: Failed to unschedule process_queue task');
                $success = false;
            } else {
                error_log('Status Sentry: Successfully unscheduled process_queue task');
            }
        } else {
            error_log('Status Sentry: No process_queue task found to unschedule');
        }

        // Unschedule cleanup
        $timestamp = wp_next_scheduled('status_sentry_cleanup');
        if ($timestamp) {
            $result = wp_unschedule_event($timestamp, 'status_sentry_cleanup');
            if ($result === false) {
                error_log('Status Sentry: Failed to unschedule cleanup task');
                $success = false;
            } else {
                error_log('Status Sentry: Successfully unscheduled cleanup task');
            }
        } else {
            error_log('Status Sentry: No cleanup task found to unschedule');
        }

        // Alternative approach: clear all hooks
        // This is more thorough but might remove hooks we didn't intend to
        $result = wp_clear_scheduled_hook('status_sentry_process_queue');
        if ($result > 0) {
            error_log(sprintf('Status Sentry: Cleared %d process_queue tasks using wp_clear_scheduled_hook', $result));
        }

        $result = wp_clear_scheduled_hook('status_sentry_cleanup');
        if ($result > 0) {
            error_log(sprintf('Status Sentry: Cleared %d cleanup tasks using wp_clear_scheduled_hook', $result));
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
        error_log('Status Sentry: Starting queue processing');

        try {
            // Validate batch size
            $batch_size = absint($batch_size);
            if ($batch_size <= 0) {
                $batch_size = 100; // Default to 100 if invalid
            }

            // Create an event processor
            $processor = new Status_Sentry_Event_Processor();

            // Process events
            $processed_count = $processor->process_events($batch_size);

            // Calculate execution time
            $execution_time = microtime(true) - $start_time;

            // Log the result
            if ($processed_count > 0) {
                error_log(sprintf(
                    'Status Sentry: Processed %d events from the queue in %.2f seconds.',
                    $processed_count,
                    $execution_time
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
        $stats = [
            'events_deleted' => 0,
            'processed_queue_items_deleted' => 0,
            'failed_queue_items_deleted' => 0,
            'errors' => 0,
        ];

        error_log('Status Sentry: Starting database cleanup');

        try {
            // Get retention settings (could be made configurable in the future)
            $events_retention_days = apply_filters('status_sentry_events_retention_days', 30);
            $processed_queue_retention_days = apply_filters('status_sentry_processed_queue_retention_days', 7);
            $failed_queue_retention_days = apply_filters('status_sentry_failed_queue_retention_days', 14);

            error_log(sprintf(
                'Status Sentry: Using retention periods - Events: %d days, Processed queue: %d days, Failed queue: %d days',
                $events_retention_days,
                $processed_queue_retention_days,
                $failed_queue_retention_days
            ));

            // Clean up old events
            $events_table = $wpdb->prefix . 'status_sentry_events';
            if ($wpdb->get_var("SHOW TABLES LIKE '$events_table'") == $events_table) {
                // Get table size before cleanup
                $table_size_before = $wpdb->get_var("SELECT COUNT(*) FROM $events_table");

                // Delete events older than retention period
                $cutoff_date = date('Y-m-d H:i:s', time() - ($events_retention_days * 86400));
                $deleted = $wpdb->query($wpdb->prepare(
                    "DELETE FROM $events_table WHERE event_time < %s",
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
            } else {
                error_log('Status Sentry: Events table does not exist, skipping cleanup');
            }

            // Clean up old queue items
            $queue_table = $wpdb->prefix . 'status_sentry_queue';
            if ($wpdb->get_var("SHOW TABLES LIKE '$queue_table'") == $queue_table) {
                // Get table size before cleanup
                $table_size_before = $wpdb->get_var("SELECT COUNT(*) FROM $queue_table");

                // Delete processed queue items older than retention period
                $processed_cutoff_date = date('Y-m-d H:i:s', time() - ($processed_queue_retention_days * 86400));
                $deleted = $wpdb->query($wpdb->prepare(
                    "DELETE FROM $queue_table WHERE status = 'processed' AND created_at < %s",
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
                    "DELETE FROM $queue_table WHERE status = 'failed' AND created_at < %s",
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
            } else {
                error_log('Status Sentry: Queue table does not exist, skipping cleanup');
            }

            // Optimize tables if possible
            if (method_exists($wpdb, 'query')) {
                $wpdb->query("OPTIMIZE TABLE $events_table");
                $wpdb->query("OPTIMIZE TABLE $queue_table");
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
            error_log(sprintf(
                'Status Sentry: Cleanup completed in %.2f seconds. Stats: %s',
                $total_time,
                json_encode($stats)
            ));
        }

        return $stats;
    }
}
