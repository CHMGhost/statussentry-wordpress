<?php
/**
 * Migration to create the queue table.
 *
 * @since      1.0.0
 * @package    Status_Sentry
 * @subpackage Status_Sentry/includes/db/migrations
 */

/**
 * Migration to create the queue table.
 *
 * @since      1.0.0
 * @package    Status_Sentry
 * @subpackage Status_Sentry/includes/db/migrations
 */
class Status_Sentry_Migration_CreateQueueTable {

    /**
     * Run the migration.
     *
     * @since    1.0.0
     * @return   bool    Whether the migration was successfully run.
     */
    public function up() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'status_sentry_queue';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            feature varchar(50) NOT NULL,
            hook varchar(100) NOT NULL,
            data longtext NOT NULL,
            created_at datetime NOT NULL,
            status varchar(20) NOT NULL,
            PRIMARY KEY  (id),
            KEY status (status),
            KEY feature (feature),
            KEY hook (hook),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        
        return dbDelta($sql) ? true : false;
    }

    /**
     * Reverse the migration.
     *
     * @since    1.0.0
     * @return   bool    Whether the migration was successfully reversed.
     */
    public function down() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'status_sentry_queue';
        
        $sql = "DROP TABLE IF EXISTS $table_name;";
        
        return $wpdb->query($sql) !== false;
    }
}
