<?php
/**
 * Plugin Name: Status Sentry Test Errors
 * Plugin URI: https://example.com/status-sentry-test-errors
 * Description: A test plugin to generate various error events for Status Sentry monitoring system
 * Version: 1.0.0
 * Author: Status Sentry Team
 * Author URI: https://example.com
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: status-sentry-test-errors
 * Domain Path: /languages
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Currently plugin version.
 */
define('SSTEST_VERSION', '1.0.0');

/**
 * The code that runs during plugin activation.
 */
function sstest_activate() {
    // Schedule a cron event to run every minute
    if (!wp_next_scheduled('status_sentry_test_error_cron')) {
        wp_schedule_event(time(), 'minute', 'status_sentry_test_error_cron');
    }
}

/**
 * The code that runs during plugin deactivation.
 */
function sstest_deactivate() {
    // Clear the scheduled cron event
    wp_clear_scheduled_hook('status_sentry_test_error_cron');
}

register_activation_hook(__FILE__, 'sstest_activate');
register_deactivation_hook(__FILE__, 'sstest_deactivate');

/**
 * Trigger manual error events when requested via query parameter.
 */
function sstest_trigger_errors() {
    // Check if we should trigger errors
    if (isset($_GET['sstest_run']) && $_GET['sstest_run'] == '1') {
        // Get the Status Sentry Monitoring Manager
        $manager = Status_Sentry_Monitoring_Manager::get_instance();

        if (!$manager) {
            wp_die('Status Sentry Monitoring Manager not found. Is Status Sentry plugin active?');
        }

        // Get event type constants
        $error_type = Status_Sentry_Monitoring_Event::TYPE_ERROR;
        $critical_type = Status_Sentry_Monitoring_Event::TYPE_CRITICAL;
        $conflict_type = Status_Sentry_Monitoring_Event::TYPE_CONFLICT;

        // Get priority constants
        $high_priority = Status_Sentry_Monitoring_Event::PRIORITY_HIGH;
        $critical_priority = Status_Sentry_Monitoring_Event::PRIORITY_CRITICAL;

        // Emit a general error event
        $manager->emit(
            $error_type,
            'test_plugin',
            'manual_test',
            'Simulated general error',
            [
                'error_code' => 'E001',
                'details' => 'Manual test error triggered via query parameter',
                'timestamp' => current_time('mysql'),
            ],
            $high_priority
        );

        // Emit a critical error event
        $manager->emit(
            $critical_type,
            'test_plugin',
            'manual_test',
            'Simulated critical error',
            [
                'error_code' => 'E002',
                'details' => 'Manual test critical error triggered via query parameter',
                'timestamp' => current_time('mysql'),
            ],
            $critical_priority
        );

        // Emit a conflict error event
        $manager->emit(
            $conflict_type,
            'test_plugin',
            'manual_test',
            'Simulated plugin conflict',
            [
                'conflicting_plugin' => 'some-other-plugin',
                'conflict_type' => 'resource_conflict',
                'timestamp' => current_time('mysql'),
            ],
            $high_priority
        );

        // Redirect to admin dashboard with a success message
        wp_redirect(admin_url('admin.php?page=sstest-dashboard&triggered=1'));
        exit;
    }
}
add_action('admin_init', 'sstest_trigger_errors');

/**
 * Simulate a cron error event.
 */
function sstest_cron_error() {
    // Get the Status Sentry Monitoring Manager
    $manager = Status_Sentry_Monitoring_Manager::get_instance();

    if (!$manager) {
        return;
    }

    // Simulate a cron job failure
    $manager->emit(
        'cron_error', // Special event type for cron errors
        'test_plugin',
        'cron_test',
        'Simulated cron job failure',
        [
            'hook' => 'status_sentry_test_error_cron',
            'task_name' => 'test_error_cron',
            'error_message' => 'Cron test failed as expected',
            'scheduled_time' => wp_next_scheduled('status_sentry_test_error_cron'),
            'timestamp' => current_time('mysql'),
        ],
        Status_Sentry_Monitoring_Event::PRIORITY_HIGH
    );
}
add_action('status_sentry_test_error_cron', 'sstest_cron_error');

/**
 * Enqueue admin styles.
 */
function sstest_enqueue_admin_styles() {
    $screen = get_current_screen();

    // Only enqueue on our plugin's admin page
    if ($screen && $screen->id === 'toplevel_page_sstest-dashboard') {
        wp_enqueue_style(
            'sstest-admin-css',
            plugin_dir_url(__FILE__) . 'admin/css/test-status-sentry-errors-admin.css',
            [],
            SSTEST_VERSION,
            'all'
        );
    }
}
add_action('admin_enqueue_scripts', 'sstest_enqueue_admin_styles');

/**
 * Add admin menu page.
 */
function sstest_add_admin_menu() {
    add_menu_page(
        'Status Sentry Test',
        'SS Test',
        'manage_options',
        'sstest-dashboard',
        'sstest_dashboard_page',
        'dashicons-warning',
        99
    );
}
add_action('admin_menu', 'sstest_add_admin_menu');

/**
 * Render the admin dashboard page.
 */
function sstest_dashboard_page() {
    ?>
    <div class="wrap sstest-dashboard">
        <h1>Status Sentry Test Errors</h1>

        <?php if (isset($_GET['triggered']) && $_GET['triggered'] == '1') : ?>
            <div class="notice notice-success is-dismissible">
                <p>Test errors have been triggered successfully!</p>
            </div>
        <?php endif; ?>

        <div class="card">
            <h2>Trigger Test Errors</h2>
            <p>Click the button below to trigger test error events for Status Sentry:</p>
            <div class="button-container">
                <a href="<?php echo admin_url('admin.php?sstest_run=1'); ?>" class="button button-primary">Trigger Test Errors</a>
            </div>

            <h3>Events that will be triggered:</h3>
            <div class="event-list">
                <div class="event-item">
                    <span class="error-type error">ERROR</span>
                    <strong>Simulated general error</strong>
                    <div class="event-details">
                        <pre>Source: test_plugin
Context: manual_test
Priority: HIGH
Data: { error_code: 'E001', details: 'Manual test error...' }</pre>
                    </div>
                </div>

                <div class="event-item">
                    <span class="error-type critical">CRITICAL</span>
                    <strong>Simulated critical error</strong>
                    <div class="event-details">
                        <pre>Source: test_plugin
Context: manual_test
Priority: CRITICAL
Data: { error_code: 'E002', details: 'Manual test critical error...' }</pre>
                    </div>
                </div>

                <div class="event-item">
                    <span class="error-type conflict">CONFLICT</span>
                    <strong>Simulated plugin conflict</strong>
                    <div class="event-details">
                        <pre>Source: test_plugin
Context: manual_test
Priority: HIGH
Data: { conflicting_plugin: 'some-other-plugin', conflict_type: 'resource_conflict' }</pre>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <h2>About Cron Errors</h2>
            <p>This plugin also schedules a cron job that runs every minute and generates a cron error event.</p>
            <p>Next scheduled run: <span class="next-run"><?php echo wp_next_scheduled('status_sentry_test_error_cron') ? date('Y-m-d H:i:s', wp_next_scheduled('status_sentry_test_error_cron')) : 'Not scheduled'; ?></span></p>

            <div class="event-item">
                <span class="error-type cron">CRON ERROR</span>
                <strong>Simulated cron job failure</strong>
                <div class="event-details">
                    <pre>Source: test_plugin
Context: cron_test
Priority: HIGH
Data: { hook: 'status_sentry_test_error_cron', task_name: 'test_error_cron', error_message: 'Cron test failed as expected' }</pre>
                </div>
            </div>
        </div>
    </div>
    <?php
}
