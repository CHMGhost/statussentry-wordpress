<?php
/**
 * Migration to create the task_state table.
 *
 * @since      1.2.0
 * @package    Status_Sentry
 * @subpackage Status_Sentry/includes/db/migrations
 */

/**
 * Migration to create the task_state table.
 *
 * This table stores the state of long-running tasks, allowing them to be
 * resumed if they are interrupted or exceed their resource budget. This
 * enables more resilient processing of large datasets.
 *
 * @since      1.2.0
 * @package    Status_Sentry
 * @subpackage Status_Sentry/includes/db/migrations
 */
class Status_Sentry_Migration_CreateTaskStateTable {
    
    /**
     * Run the migration.
     *
     * @since    1.2.0
     * @return   bool    Whether the migration was successfully run.
     */
    public function up() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'status_sentry_task_state';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            task_name varchar(100) NOT NULL,
            task_key varchar(100) NOT NULL,
            state longtext NOT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            expires_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY task_key (task_name, task_key),
            KEY expires_at (expires_at)
        ) $charset_collate;";
        
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        
        return dbDelta($sql) ? true : false;
    }
    
    /**
     * Reverse the migration.
     *
     * @since    1.2.0
     * @return   bool    Whether the migration was successfully reversed.
     */
    public function down() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'status_sentry_task_state';
        
        $sql = "DROP TABLE IF EXISTS $table_name";
        
        return $wpdb->query($sql) !== false;
    }
}
