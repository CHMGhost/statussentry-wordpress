<?php
/**
 * Fired during plugin deactivation.
 *
 * @since      1.0.0
 * @package    Status_Sentry
 * @subpackage Status_Sentry/includes
 */

/**
 * Fired during plugin deactivation.
 *
 * This class defines all code necessary to run during the plugin's deactivation.
 *
 * @since      1.0.0
 * @package    Status_Sentry
 * @subpackage Status_Sentry/includes
 */
class Status_Sentry_Deactivator {

    /**
     * Deactivate the plugin.
     *
     * Clean up scheduled tasks and perform any necessary cleanup.
     *
     * @since    1.0.0
     */
    public static function deactivate() {
        // Clean up scheduled tasks
        require_once STATUS_SENTRY_PLUGIN_DIR . 'includes/class-status-sentry-scheduler.php';
        Status_Sentry_Scheduler::unschedule_tasks();
    }
}
