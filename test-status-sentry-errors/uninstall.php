<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @since      1.0.0
 * @package    Status_Sentry_Test_Errors
 */

// If uninstall not called from WordPress, then exit.
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Clear any scheduled cron events
wp_clear_scheduled_hook('status_sentry_test_error_cron');

// Remove any plugin options if needed
// delete_option('sstest_option_name');
