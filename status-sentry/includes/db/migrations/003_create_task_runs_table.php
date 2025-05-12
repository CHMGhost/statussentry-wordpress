<?php
/**
 * Migration to create the task_runs table.
 *
 * @since      1.1.0
 * @package    Status_Sentry
 * @subpackage Status_Sentry/includes/db/migrations
 */

/**
 * Migration to create the task_runs table.
 *
 * This table tracks the execution history of scheduled tasks, including
 * start time, end time, execution duration, memory usage, and status.
 * It's used for monitoring task performance and detecting issues.
 *
 * @since      1.1.0
 * @package    Status_Sentry
 * @subpackage Status_Sentry/includes/db/migrations
 */
class Status_Sentry_Migration_CreateTaskRunsTable {

    /**
     * Run the migration.
     *
     * @since    1.1.0
     * @return   bool    Whether the migration was successfully run.
     */
    public function up() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'status_sentry_task_runs';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            task_name varchar(100) NOT NULL,
            tier varchar(20) NOT NULL,
            start_time datetime NOT NULL,
            end_time datetime NULL,
            duration float NULL,
            memory_start int(11) NOT NULL,
            memory_peak int(11) NULL,
            memory_end int(11) NULL,
            status varchar(20) NOT NULL DEFAULT 'running',
            error_message text NULL,
            metadata longtext NULL,
            PRIMARY KEY  (id),
            KEY task_name (task_name),
            KEY tier (tier),
            KEY status (status),
            KEY start_time (start_time)
        ) $charset_collate;";
        
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        
        return dbDelta($sql) ? true : false;
    }

    /**
     * Reverse the migration.
     *
     * @since    1.1.0
     * @return   bool    Whether the migration was successfully reversed.
     */
    public function down() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'status_sentry_task_runs';
        
        $sql = "DROP TABLE IF EXISTS $table_name;";
        
        return $wpdb->query($sql) !== false;
    }
}
