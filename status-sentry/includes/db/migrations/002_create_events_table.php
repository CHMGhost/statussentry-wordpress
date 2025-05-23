<?php
/**
 * Migration to create the events table.
 *
 * @since      1.0.0
 * @package    Status_Sentry
 * @subpackage Status_Sentry/includes/db/migrations
 */

/**
 * Migration to create the events table.
 *
 * @since      1.0.0
 * @package    Status_Sentry
 * @subpackage Status_Sentry/includes/db/migrations
 */
class Status_Sentry_Migration_CreateEventsTable {

    /**
     * Run the migration.
     *
     * @since    1.0.0
     * @return   bool    Whether the migration was successfully run.
     */
    public function up() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'status_sentry_events';
        $charset_collate = $wpdb->get_charset_collate();

        error_log("Status Sentry: Creating events table {$table_name}");

        // Check if the table already exists
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") == $table_name) {
            error_log("Status Sentry: Events table {$table_name} already exists");
            return true;
        }

        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            feature varchar(50) NOT NULL,
            hook varchar(100) NOT NULL,
            data longtext NOT NULL,
            event_time datetime NOT NULL,
            processed_time datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY feature (feature),
            KEY hook (hook),
            KEY event_time (event_time)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $result = dbDelta($sql);

        // Check if the table was created successfully
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") == $table_name) {
            error_log("Status Sentry: Events table {$table_name} created successfully");
            error_log("Status Sentry: dbDelta result: " . print_r($result, true));
            return true;
        } else {
            error_log("Status Sentry: Failed to create events table {$table_name}");
            error_log("Status Sentry: dbDelta result: " . print_r($result, true));
            error_log("Status Sentry: MySQL error: " . $wpdb->last_error);
            return false;
        }
    }

    /**
     * Reverse the migration.
     *
     * @since    1.0.0
     * @return   bool    Whether the migration was successfully reversed.
     */
    public function down() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'status_sentry_events';

        $sql = "DROP TABLE IF EXISTS $table_name;";

        return $wpdb->query($sql) !== false;
    }
}
