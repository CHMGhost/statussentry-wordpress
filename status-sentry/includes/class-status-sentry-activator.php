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
        $migrator->run_migrations();

        // Set up scheduled tasks
        require_once STATUS_SENTRY_PLUGIN_DIR . 'includes/class-status-sentry-scheduler.php';
        Status_Sentry_Scheduler::schedule_tasks();

        // Set version in options
        update_option('status_sentry_version', STATUS_SENTRY_VERSION);
    }
}
