<?php
/**
 * Core Framework Test Script
 *
 * This script tests the core framework components of the Status Sentry plugin.
 * It verifies that hooks are registered correctly, data is captured and processed,
 * database migrations run correctly, and scheduled tasks are registered.
 *
 * @package    Status_Sentry
 * @subpackage Status_Sentry/tests
 */

// Define the plugin constants if not already defined
if (!defined('STATUS_SENTRY_VERSION')) {
    define('STATUS_SENTRY_VERSION', '1.3.0');
}
if (!defined('STATUS_SENTRY_PLUGIN_DIR')) {
    define('STATUS_SENTRY_PLUGIN_DIR', dirname(__DIR__) . '/');
}

// Include the necessary files
require_once STATUS_SENTRY_PLUGIN_DIR . 'includes/hooks/class-status-sentry-hook-config.php';
require_once STATUS_SENTRY_PLUGIN_DIR . 'includes/hooks/class-status-sentry-hook-manager.php';
require_once STATUS_SENTRY_PLUGIN_DIR . 'includes/data/class-status-sentry-data-capture.php';
require_once STATUS_SENTRY_PLUGIN_DIR . 'includes/data/class-status-sentry-data-filter.php';
require_once STATUS_SENTRY_PLUGIN_DIR . 'includes/data/class-status-sentry-sampling-manager.php';
require_once STATUS_SENTRY_PLUGIN_DIR . 'includes/data/class-status-sentry-event-queue.php';
require_once STATUS_SENTRY_PLUGIN_DIR . 'includes/data/class-status-sentry-event-processor.php';
require_once STATUS_SENTRY_PLUGIN_DIR . 'includes/db/class-status-sentry-db-migrator.php';
require_once STATUS_SENTRY_PLUGIN_DIR . 'includes/class-status-sentry-scheduler.php';

/**
 * Test the hook management system.
 *
 * @return bool Whether the test passed.
 */
function test_hook_management() {
    echo "Testing hook management system...\n";
    
    // Create instances of the required classes
    $hook_config = new Status_Sentry_Hook_Config();
    $sampling_manager = new Status_Sentry_Sampling_Manager();
    $data_capture = new Status_Sentry_Data_Capture();
    
    // Create the hook manager
    $hook_manager = new Status_Sentry_Hook_Manager($hook_config, $sampling_manager, $data_capture);
    
    // Register hooks
    $hook_manager->register_hooks();
    
    // Verify that hooks are registered
    $hooks = $hook_config->get_hooks();
    $success = !empty($hooks);
    
    echo $success ? "Hook management test passed.\n" : "Hook management test failed.\n";
    
    return $success;
}

/**
 * Test the data pipeline.
 *
 * @return bool Whether the test passed.
 */
function test_data_pipeline() {
    echo "Testing data pipeline...\n";
    
    // Create instances of the required classes
    $data_filter = new Status_Sentry_Data_Filter();
    $event_queue = new Status_Sentry_Event_Queue();
    $data_capture = new Status_Sentry_Data_Capture();
    
    // Capture some test data
    $feature = 'test_feature';
    $hook = 'test_hook';
    $data = [
        'test_key' => 'test_value',
        'timestamp' => microtime(true),
    ];
    
    // Capture the data
    $data_capture->capture($feature, $hook, $data);
    
    // Verify that the data was enqueued
    $events = $event_queue->get_events(10, 'pending');
    $success = !empty($events);
    
    echo $success ? "Data pipeline test passed.\n" : "Data pipeline test failed.\n";
    
    return $success;
}

/**
 * Test the event processor.
 *
 * @return bool Whether the test passed.
 */
function test_event_processor() {
    echo "Testing event processor...\n";
    
    // Create an instance of the event processor
    $event_processor = new Status_Sentry_Event_Processor();
    
    // Process events
    $processed = $event_processor->process_events(10);
    
    // Verify that events were processed
    $success = $processed !== false;
    
    echo $success ? "Event processor test passed.\n" : "Event processor test failed.\n";
    
    return $success;
}

/**
 * Test the database migrator.
 *
 * @return bool Whether the test passed.
 */
function test_database_migrator() {
    echo "Testing database migrator...\n";
    
    // Create an instance of the database migrator
    $migrator = new Status_Sentry_DB_Migrator();
    
    // Run migrations
    $success = $migrator->run_migrations();
    
    echo $success ? "Database migrator test passed.\n" : "Database migrator test failed.\n";
    
    return $success;
}

/**
 * Test the scheduler.
 *
 * @return bool Whether the test passed.
 */
function test_scheduler() {
    echo "Testing scheduler...\n";
    
    // Initialize the scheduler
    Status_Sentry_Scheduler::init();
    
    // Schedule tasks
    Status_Sentry_Scheduler::schedule_tasks();
    
    // Verify that tasks are scheduled
    $success = wp_next_scheduled('status_sentry_process_queue') !== false;
    
    echo $success ? "Scheduler test passed.\n" : "Scheduler test failed.\n";
    
    // Clean up
    Status_Sentry_Scheduler::unschedule_tasks();
    
    return $success;
}

/**
 * Run all tests.
 */
function run_all_tests() {
    echo "Running all tests...\n";
    
    $tests = [
        'test_hook_management',
        'test_data_pipeline',
        'test_event_processor',
        'test_database_migrator',
        'test_scheduler',
    ];
    
    $passed = 0;
    $total = count($tests);
    
    foreach ($tests as $test) {
        if (function_exists($test)) {
            $result = call_user_func($test);
            if ($result) {
                $passed++;
            }
        }
    }
    
    echo "\nTest results: $passed/$total tests passed.\n";
}

// Run the tests
run_all_tests();
