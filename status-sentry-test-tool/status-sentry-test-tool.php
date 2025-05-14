<?php
/**
 * Plugin Name: Status Sentry Test Tool
 * Plugin URI: https://example.com/status-sentry-test-tool
 * Description: A simple tool to test Status Sentry's monitoring system
 * Version: 1.0.0
 * Author: Status Sentry Team
 * Author URI: https://example.com
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: status-sentry-test-tool
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Add admin menu page.
 */
function sstt_add_admin_menu() {
    add_management_page(
        'Status Sentry Test Tool',
        'SS Test Tool',
        'manage_options',
        'status-sentry-test-tool',
        'sstt_render_admin_page'
    );
}
add_action('admin_menu', 'sstt_add_admin_menu');

/**
 * Render the admin page.
 */
function sstt_render_admin_page() {
    // Check if we should generate events
    if (isset($_GET['generate'])) {
        $event_type = sanitize_text_field($_GET['generate']);
        sstt_generate_test_event($event_type);
    }
    
    ?>
    <div class="wrap">
        <h1>Status Sentry Test Tool</h1>
        
        <?php if (isset($_GET['generated'])) : ?>
            <div class="notice notice-success is-dismissible">
                <p>Test event(s) generated successfully!</p>
            </div>
        <?php endif; ?>
        
        <?php if (!class_exists('Status_Sentry_Monitoring_Manager') || !class_exists('Status_Sentry_Monitoring_Event')) : ?>
            <div class="notice notice-error">
                <p>Status Sentry plugin is not active or its monitoring classes are not available. Please activate the Status Sentry plugin first.</p>
            </div>
        <?php else : ?>
            <div class="card" style="max-width: 800px; padding: 20px; margin-bottom: 20px;">
                <h2>Generate Test Events</h2>
                <p>Click one of the buttons below to generate test events for Status Sentry:</p>
                
                <p>
                    <a href="<?php echo admin_url('tools.php?page=status-sentry-test-tool&generate=info'); ?>" class="button">Generate Info Event</a>
                    <a href="<?php echo admin_url('tools.php?page=status-sentry-test-tool&generate=warning'); ?>" class="button" style="background: #ffb900; border-color: #ffb900; color: #fff;">Generate Warning Event</a>
                    <a href="<?php echo admin_url('tools.php?page=status-sentry-test-tool&generate=error'); ?>" class="button" style="background: #dc3232; border-color: #dc3232; color: #fff;">Generate Error Event</a>
                    <a href="<?php echo admin_url('tools.php?page=status-sentry-test-tool&generate=critical'); ?>" class="button" style="background: #b32d2e; border-color: #b32d2e; color: #fff;">Generate Critical Event</a>
                    <a href="<?php echo admin_url('tools.php?page=status-sentry-test-tool&generate=conflict'); ?>" class="button" style="background: #2271b1; border-color: #2271b1; color: #fff;">Generate Conflict Event</a>
                    <a href="<?php echo admin_url('tools.php?page=status-sentry-test-tool&generate=cron_error'); ?>" class="button" style="background: #826eb4; border-color: #826eb4; color: #fff;">Generate Cron Error</a>
                    <a href="<?php echo admin_url('tools.php?page=status-sentry-test-tool&generate=all'); ?>" class="button button-primary">Generate All Events</a>
                </p>
                
                <h3>Event Details</h3>
                <table class="widefat" style="margin-top: 10px;">
                    <thead>
                        <tr>
                            <th>Event Type</th>
                            <th>Source</th>
                            <th>Context</th>
                            <th>Message</th>
                            <th>Priority</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Info</td>
                            <td>test_tool</td>
                            <td>manual_test</td>
                            <td>Test info event from Status Sentry Test Tool</td>
                            <td>NORMAL</td>
                        </tr>
                        <tr>
                            <td>Warning</td>
                            <td>test_tool</td>
                            <td>manual_test</td>
                            <td>Test warning event from Status Sentry Test Tool</td>
                            <td>NORMAL</td>
                        </tr>
                        <tr>
                            <td>Error</td>
                            <td>test_tool</td>
                            <td>manual_test</td>
                            <td>Test error event from Status Sentry Test Tool</td>
                            <td>HIGH</td>
                        </tr>
                        <tr>
                            <td>Critical</td>
                            <td>test_tool</td>
                            <td>manual_test</td>
                            <td>Test critical event from Status Sentry Test Tool</td>
                            <td>CRITICAL</td>
                        </tr>
                        <tr>
                            <td>Conflict</td>
                            <td>test_tool</td>
                            <td>manual_test</td>
                            <td>Test conflict event from Status Sentry Test Tool</td>
                            <td>HIGH</td>
                        </tr>
                        <tr>
                            <td>Cron Error</td>
                            <td>test_tool</td>
                            <td>cron_test</td>
                            <td>Test cron error event from Status Sentry Test Tool</td>
                            <td>HIGH</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <div class="card" style="max-width: 800px; padding: 20px;">
                <h2>Verify Results</h2>
                <p>After generating events, you can verify them in the following ways:</p>
                <ol>
                    <li>Check the Status Sentry monitoring dashboard in WordPress admin</li>
                    <li>Look at the WordPress database in the <code>wp_status_sentry_monitoring_events</code> table</li>
                    <li>Check if any notifications were triggered based on your Status Sentry configuration</li>
                </ol>
            </div>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * Generate a test event.
 */
function sstt_generate_test_event($event_type) {
    // Check if Status Sentry is active
    if (!class_exists('Status_Sentry_Monitoring_Manager') || !class_exists('Status_Sentry_Monitoring_Event')) {
        return false;
    }
    
    // Get the monitoring manager
    $manager = Status_Sentry_Monitoring_Manager::get_instance();
    
    if (!$manager) {
        return false;
    }
    
    // Get event type and priority constants
    $info_type = defined('Status_Sentry_Monitoring_Event::TYPE_INFO') ? Status_Sentry_Monitoring_Event::TYPE_INFO : 'info';
    $warning_type = defined('Status_Sentry_Monitoring_Event::TYPE_WARNING') ? Status_Sentry_Monitoring_Event::TYPE_WARNING : 'warning';
    $error_type = defined('Status_Sentry_Monitoring_Event::TYPE_ERROR') ? Status_Sentry_Monitoring_Event::TYPE_ERROR : 'error';
    $critical_type = defined('Status_Sentry_Monitoring_Event::TYPE_CRITICAL') ? Status_Sentry_Monitoring_Event::TYPE_CRITICAL : 'critical';
    $conflict_type = defined('Status_Sentry_Monitoring_Event::TYPE_CONFLICT') ? Status_Sentry_Monitoring_Event::TYPE_CONFLICT : 'conflict';
    
    $normal_priority = defined('Status_Sentry_Monitoring_Event::PRIORITY_NORMAL') ? Status_Sentry_Monitoring_Event::PRIORITY_NORMAL : 50;
    $high_priority = defined('Status_Sentry_Monitoring_Event::PRIORITY_HIGH') ? Status_Sentry_Monitoring_Event::PRIORITY_HIGH : 80;
    $critical_priority = defined('Status_Sentry_Monitoring_Event::PRIORITY_CRITICAL') ? Status_Sentry_Monitoring_Event::PRIORITY_CRITICAL : 100;
    
    $success = false;
    
    // Info event
    if ($event_type === 'all' || $event_type === 'info') {
        $manager->emit(
            $info_type,
            'test_tool',
            'manual_test',
            'Test info event from Status Sentry Test Tool',
            [
                'event_id' => 'info_' . time(),
                'timestamp' => current_time('mysql'),
                'test_tool' => true,
            ],
            $normal_priority
        );
        $success = true;
    }
    
    // Warning event
    if ($event_type === 'all' || $event_type === 'warning') {
        $manager->emit(
            $warning_type,
            'test_tool',
            'manual_test',
            'Test warning event from Status Sentry Test Tool',
            [
                'event_id' => 'warning_' . time(),
                'timestamp' => current_time('mysql'),
                'test_tool' => true,
            ],
            $normal_priority
        );
        $success = true;
    }
    
    // Error event
    if ($event_type === 'all' || $event_type === 'error') {
        $manager->emit(
            $error_type,
            'test_tool',
            'manual_test',
            'Test error event from Status Sentry Test Tool',
            [
                'event_id' => 'error_' . time(),
                'error_code' => 'E001',
                'details' => 'This is a simulated error for testing purposes',
                'timestamp' => current_time('mysql'),
                'test_tool' => true,
            ],
            $high_priority
        );
        $success = true;
    }
    
    // Critical event
    if ($event_type === 'all' || $event_type === 'critical') {
        $manager->emit(
            $critical_type,
            'test_tool',
            'manual_test',
            'Test critical event from Status Sentry Test Tool',
            [
                'event_id' => 'critical_' . time(),
                'error_code' => 'E002',
                'details' => 'This is a simulated critical error for testing purposes',
                'timestamp' => current_time('mysql'),
                'test_tool' => true,
            ],
            $critical_priority
        );
        $success = true;
    }
    
    // Conflict event
    if ($event_type === 'all' || $event_type === 'conflict') {
        $manager->emit(
            $conflict_type,
            'test_tool',
            'manual_test',
            'Test conflict event from Status Sentry Test Tool',
            [
                'event_id' => 'conflict_' . time(),
                'conflicting_plugin' => 'some-other-plugin',
                'conflict_type' => 'resource_conflict',
                'timestamp' => current_time('mysql'),
                'test_tool' => true,
            ],
            $high_priority
        );
        $success = true;
    }
    
    // Cron error event
    if ($event_type === 'all' || $event_type === 'cron_error') {
        $manager->emit(
            'cron_error', // Special event type for cron errors
            'test_tool',
            'cron_test',
            'Test cron error event from Status Sentry Test Tool',
            [
                'event_id' => 'cron_error_' . time(),
                'hook' => 'test_cron_hook',
                'task_name' => 'test_cron_task',
                'error_message' => 'Simulated cron failure from test tool',
                'scheduled_time' => current_time('mysql'),
                'timestamp' => current_time('mysql'),
                'test_tool' => true,
            ],
            $high_priority
        );
        $success = true;
    }
    
    // Redirect back to the admin page with a success message
    if ($success) {
        wp_redirect(admin_url('tools.php?page=status-sentry-test-tool&generated=1'));
        exit;
    }
    
    return $success;
}
