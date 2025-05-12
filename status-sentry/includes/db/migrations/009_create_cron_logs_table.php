<?php
/**
 * Migration to create the cron_logs table.
 *
 * @since      1.4.0
 * @package    Status_Sentry
 * @subpackage Status_Sentry/includes/db/migrations
 */

/**
 * Migration to create the cron_logs table.
 *
 * This table stores centralized logs for all cron job executions, providing
 * a unified view of cron activity across the system. It tracks execution times,
 * success/failure status, and other metadata to help diagnose cron-related issues.
 *
 * @since      1.4.0
 * @package    Status_Sentry
 * @subpackage Status_Sentry/includes/db/migrations
 */
class Status_Sentry_Migration_CreateCronLogsTable {

    /**
     * Run the migration.
     *
     * @since    1.4.0
     * @return   bool    Whether the migration was successfully run.
     */
    public function up() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'status_sentry_cron_logs';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            hook varchar(100) NOT NULL,
            task_name varchar(100) NULL,
            scheduled_time datetime NOT NULL,
            execution_time datetime NOT NULL,
            completion_time datetime NULL,
            duration float NULL,
            status varchar(20) NOT NULL DEFAULT 'running',
            error_message text NULL,
            memory_used int(11) NULL,
            dependencies longtext NULL,
            metadata longtext NULL,
            PRIMARY KEY  (id),
            KEY hook (hook),
            KEY task_name (task_name),
            KEY status (status),
            KEY execution_time (execution_time),
            KEY scheduled_time (scheduled_time)
        ) $charset_collate;";
        
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        
        return dbDelta($sql) ? true : false;
    }

    /**
     * Reverse the migration.
     *
     * @since    1.4.0
     * @return   bool    Whether the migration was successfully reversed.
     */
    public function down() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'status_sentry_cron_logs';
        
        $sql = "DROP TABLE IF EXISTS $table_name;";
        
        return $wpdb->query($sql) !== false;
    }
}
