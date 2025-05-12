<?php
/**
 * Run Core Framework Script
 *
 * This script demonstrates how to use the Status Sentry core framework.
 * It initializes the components and runs a simple test to capture and process events.
 *
 * @package    Status_Sentry
 */

// Load WordPress
require_once dirname(__DIR__) . '/wp-load.php';

// Define the plugin constants if not already defined
if (!defined('STATUS_SENTRY_VERSION')) {
    define('STATUS_SENTRY_VERSION', '1.3.0');
}
if (!defined('STATUS_SENTRY_PLUGIN_DIR')) {
    define('STATUS_SENTRY_PLUGIN_DIR', __DIR__ . '/');
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

// Run database migrations
echo "Running database migrations...\n";
$migrator = new Status_Sentry_DB_Migrator();
$migrator->run_migrations();

// Initialize the components
echo "Initializing components...\n";
$hook_config = new Status_Sentry_Hook_Config();
$sampling_manager = new Status_Sentry_Sampling_Manager();
$data_capture = new Status_Sentry_Data_Capture();
$hook_manager = new Status_Sentry_Hook_Manager($hook_config, $sampling_manager, $data_capture);

// Register hooks
echo "Registering hooks...\n";
$hook_manager->register_hooks();

// Initialize the scheduler
echo "Initializing scheduler...\n";
Status_Sentry_Scheduler::init();
Status_Sentry_Scheduler::schedule_tasks();

// Capture a test event
echo "Capturing a test event...\n";
$data = [
    'test_key' => 'test_value',
    'timestamp' => microtime(true),
];
$data_capture->capture('test_feature', 'test_hook', $data);

// Process events
echo "Processing events...\n";
$event_processor = new Status_Sentry_Event_Processor();
$processed = $event_processor->process_events(10);

echo "Processed $processed events.\n";

// Display a summary
echo "\nCore Framework Test Complete\n";
echo "===========================\n";
echo "Database migrations: " . ($migrator->get_migration_version() > 0 ? "Success" : "Failed") . "\n";
echo "Hooks registered: " . (count($hook_config->get_hooks()) > 0 ? "Success" : "Failed") . "\n";
echo "Scheduler initialized: " . (wp_next_scheduled('status_sentry_process_queue') !== false ? "Success" : "Failed") . "\n";
echo "Events processed: $processed\n";

echo "\nTest complete.\n";
