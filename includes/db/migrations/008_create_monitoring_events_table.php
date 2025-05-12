<?php
/**
 * Migration to create the monitoring_events table.
 *
 * @since      1.3.0
 * @package    Status_Sentry
 * @subpackage Status_Sentry/includes/db/migrations
 */

/**
 * Migration to create the monitoring_events table.
 *
 * This table stores monitoring events from the centralized monitoring system.
 * It provides a unified storage location for all types of monitoring events,
 * allowing for consistent querying and analysis.
 *
 * @since      1.3.0
 * @package    Status_Sentry
 * @subpackage Status_Sentry/includes/db/migrations
 */
class Status_Sentry_Migration_CreateMonitoringEventsTable {

    /**
     * Run the migration.
     *
     * @since    1.3.0
     * @return   bool    Whether the migration was successfully run.
     */
    public function up() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'status_sentry_monitoring_events';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            event_id varchar(50) NOT NULL,
            event_type varchar(20) NOT NULL,
            priority int(11) NOT NULL,
            source varchar(100) NOT NULL,
            context varchar(100) NOT NULL,
            message text NOT NULL,
            data longtext NOT NULL,
            timestamp datetime NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY event_id (event_id),
            KEY event_type (event_type),
            KEY priority (priority),
            KEY source (source),
            KEY context (context),
            KEY timestamp (timestamp),
            KEY type_priority (event_type, priority)
        ) $charset_collate;";
        
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        
        return dbDelta($sql) ? true : false;
    }

    /**
     * Reverse the migration.
     *
     * @since    1.3.0
     * @return   bool    Whether the migration was successfully reversed.
     */
    public function down() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'status_sentry_monitoring_events';
        
        $sql = "DROP TABLE IF EXISTS $table_name";
        
        return $wpdb->query($sql) !== false;
    }
}
