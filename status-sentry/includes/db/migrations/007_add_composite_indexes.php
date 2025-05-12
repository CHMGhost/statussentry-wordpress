<?php
/**
 * Migration to add composite indexes to existing tables.
 *
 * @since      1.2.0
 * @package    Status_Sentry
 * @subpackage Status_Sentry/includes/db/migrations
 */

/**
 * Migration to add composite indexes to existing tables.
 *
 * This migration adds composite indexes to existing tables to improve
 * query performance for common operations. These indexes are carefully
 * chosen based on the most frequent query patterns.
 *
 * @since      1.2.0
 * @package    Status_Sentry
 * @subpackage Status_Sentry/includes/db/migrations
 */
class Status_Sentry_Migration_AddCompositeIndexes {
    
    /**
     * Run the migration.
     *
     * @since    1.2.0
     * @return   bool    Whether the migration was successfully run.
     */
    public function up() {
        global $wpdb;
        $success = true;
        
        // Add composite indexes to the events table
        $events_table = $wpdb->prefix . 'status_sentry_events';
        if ($wpdb->get_var("SHOW TABLES LIKE '$events_table'") == $events_table) {
            // Check if the index already exists
            $index_exists = $wpdb->get_var("SHOW INDEX FROM $events_table WHERE Key_name = 'feature_event_time'");
            
            if (!$index_exists) {
                $result = $wpdb->query("ALTER TABLE $events_table ADD INDEX feature_event_time (feature, event_time)");
                if ($result === false) {
                    error_log('Status Sentry: Failed to add feature_event_time index to events table - ' . $wpdb->last_error);
                    $success = false;
                }
            }
        }
        
        // Add composite indexes to the queue table
        $queue_table = $wpdb->prefix . 'status_sentry_queue';
        if ($wpdb->get_var("SHOW TABLES LIKE '$queue_table'") == $queue_table) {
            // Check if the index already exists
            $index_exists = $wpdb->get_var("SHOW INDEX FROM $queue_table WHERE Key_name = 'status_created_at'");
            
            if (!$index_exists) {
                $result = $wpdb->query("ALTER TABLE $queue_table ADD INDEX status_created_at (status, created_at)");
                if ($result === false) {
                    error_log('Status Sentry: Failed to add status_created_at index to queue table - ' . $wpdb->last_error);
                    $success = false;
                }
            }
        }
        
        // Add composite indexes to the task_runs table
        $task_runs_table = $wpdb->prefix . 'status_sentry_task_runs';
        if ($wpdb->get_var("SHOW TABLES LIKE '$task_runs_table'") == $task_runs_table) {
            // Check if the index already exists
            $index_exists = $wpdb->get_var("SHOW INDEX FROM $task_runs_table WHERE Key_name = 'task_name_status'");
            
            if (!$index_exists) {
                $result = $wpdb->query("ALTER TABLE $task_runs_table ADD INDEX task_name_status (task_name, status)");
                if ($result === false) {
                    error_log('Status Sentry: Failed to add task_name_status index to task_runs table - ' . $wpdb->last_error);
                    $success = false;
                }
            }
        }
        
        return $success;
    }
    
    /**
     * Reverse the migration.
     *
     * @since    1.2.0
     * @return   bool    Whether the migration was successfully reversed.
     */
    public function down() {
        global $wpdb;
        $success = true;
        
        // Remove composite indexes from the events table
        $events_table = $wpdb->prefix . 'status_sentry_events';
        if ($wpdb->get_var("SHOW TABLES LIKE '$events_table'") == $events_table) {
            $result = $wpdb->query("ALTER TABLE $events_table DROP INDEX IF EXISTS feature_event_time");
            if ($result === false) {
                error_log('Status Sentry: Failed to remove feature_event_time index from events table - ' . $wpdb->last_error);
                $success = false;
            }
        }
        
        // Remove composite indexes from the queue table
        $queue_table = $wpdb->prefix . 'status_sentry_queue';
        if ($wpdb->get_var("SHOW TABLES LIKE '$queue_table'") == $queue_table) {
            $result = $wpdb->query("ALTER TABLE $queue_table DROP INDEX IF EXISTS status_created_at");
            if ($result === false) {
                error_log('Status Sentry: Failed to remove status_created_at index from queue table - ' . $wpdb->last_error);
                $success = false;
            }
        }
        
        // Remove composite indexes from the task_runs table
        $task_runs_table = $wpdb->prefix . 'status_sentry_task_runs';
        if ($wpdb->get_var("SHOW TABLES LIKE '$task_runs_table'") == $task_runs_table) {
            $result = $wpdb->query("ALTER TABLE $task_runs_table DROP INDEX IF EXISTS task_name_status");
            if ($result === false) {
                error_log('Status Sentry: Failed to remove task_name_status index from task_runs table - ' . $wpdb->last_error);
                $success = false;
            }
        }
        
        return $success;
    }
}
