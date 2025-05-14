<?php
/**
 * Fired during plugin activation.
 *
 * @since      1.0.0
 * @package    Status_Sentry
 * @subpackage Status_Sentry/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    Status_Sentry
 * @subpackage Status_Sentry/includes
 */
class Status_Sentry_Activator {

    /**
     * Activate the plugin.
     *
     * Run database migrations and set up scheduled tasks.
     *
     * @since    1.0.0
     */
    public static function activate() {
        // Run database migrations
        require_once STATUS_SENTRY_PLUGIN_DIR . 'includes/db/class-status-sentry-db-migrator.php';
        $migrator = new Status_Sentry_DB_Migrator();
        $migration_result = $migrator->run_migrations();

        // If migrations failed or we need to ensure critical tables exist
        if (!$migration_result) {
            error_log('Status Sentry: Migrations failed or incomplete, ensuring critical tables exist');

            // Ensure monitoring events table exists
            self::ensure_monitoring_events_table();

            // Ensure baselines table exists
            self::ensure_baselines_table();
        }

        // Set up scheduled tasks
        require_once STATUS_SENTRY_PLUGIN_DIR . 'includes/class-status-sentry-scheduler.php';
        Status_Sentry_Scheduler::schedule_tasks();

        // Set version in options
        update_option('status_sentry_version', STATUS_SENTRY_VERSION);

        // Set setup flag to indicate setup wizard needs to be run
        add_option('status_sentry_setup_complete', false);
    }

    /**
     * Ensure the monitoring events table exists.
     *
     * This is a critical table for the dashboard to function.
     *
     * @since    1.6.0
     */
    private static function ensure_monitoring_events_table() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'status_sentry_monitoring_events';

        // Check if table already exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;

        if (!$table_exists) {
            error_log("Status Sentry: Creating monitoring events table {$table_name}");

            $charset_collate = $wpdb->get_charset_collate();

            $sql = "CREATE TABLE {$table_name} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                event_id varchar(64) NOT NULL,
                event_type varchar(32) NOT NULL,
                priority varchar(16) NOT NULL DEFAULT 'normal',
                source varchar(64) NOT NULL,
                context varchar(64) NOT NULL,
                message text NOT NULL,
                data longtext DEFAULT NULL,
                timestamp datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                KEY event_type (event_type),
                KEY source (source),
                KEY timestamp (timestamp)
            ) {$charset_collate};";

            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            dbDelta($sql);

            // Verify table was created
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;
            if ($table_exists) {
                error_log("Status Sentry: Successfully created monitoring events table");
            } else {
                error_log("Status Sentry: Failed to create monitoring events table");
            }
        }
    }

    /**
     * Ensure the baselines table exists.
     *
     * This is a critical table for the dashboard to function.
     *
     * @since    1.6.0
     */
    private static function ensure_baselines_table() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'status_sentry_baselines';

        // Check if table already exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;

        if (!$table_exists) {
            error_log("Status Sentry: Creating baselines table {$table_name}");

            $charset_collate = $wpdb->get_charset_collate();

            $sql = "CREATE TABLE {$table_name} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                metric varchar(64) NOT NULL,
                value text NOT NULL,
                metadata longtext DEFAULT NULL,
                created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                last_updated datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                UNIQUE KEY metric (metric)
            ) {$charset_collate};";

            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            dbDelta($sql);

            // Verify table was created
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;
            if ($table_exists) {
                error_log("Status Sentry: Successfully created baselines table");
            } else {
                error_log("Status Sentry: Failed to create baselines table");
            }
        }
    }
}
