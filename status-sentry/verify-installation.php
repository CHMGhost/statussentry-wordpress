<?php
/**
 * Verify Installation Script
 *
 * This script verifies that the Status Sentry plugin is installed correctly
 * and that all required components are available.
 *
 * @package    Status_Sentry
 */

// Define the plugin constants if not already defined
if (!defined('STATUS_SENTRY_VERSION')) {
    define('STATUS_SENTRY_VERSION', '1.3.0');
}
if (!defined('STATUS_SENTRY_PLUGIN_DIR')) {
    define('STATUS_SENTRY_PLUGIN_DIR', __DIR__ . '/');
}

// List of required files
$required_files = [
    'includes/hooks/class-status-sentry-hook-config.php',
    'includes/hooks/class-status-sentry-hook-manager.php',
    'includes/data/class-status-sentry-data-capture.php',
    'includes/data/class-status-sentry-data-filter.php',
    'includes/data/class-status-sentry-sampling-manager.php',
    'includes/data/class-status-sentry-event-queue.php',
    'includes/data/class-status-sentry-event-processor.php',
    'includes/db/class-status-sentry-db-migrator.php',
    'includes/db/migrations/001_create_queue_table.php',
    'includes/db/migrations/002_create_events_table.php',
    'includes/class-status-sentry-scheduler.php',
    'includes/monitoring/interface-status-sentry-monitoring.php',
    'includes/monitoring/interface-status-sentry-monitoring-handler.php',
    'includes/monitoring/class-status-sentry-monitoring-event.php',
    'includes/monitoring/class-status-sentry-monitoring-manager.php',
];

// Check if all required files exist
$missing_files = [];
foreach ($required_files as $file) {
    if (!file_exists(STATUS_SENTRY_PLUGIN_DIR . $file)) {
        $missing_files[] = $file;
    }
}

// Display results
echo "Status Sentry Installation Verification\n";
echo "======================================\n\n";

if (empty($missing_files)) {
    echo "All required files are present.\n";
} else {
    echo "The following files are missing:\n";
    foreach ($missing_files as $file) {
        echo "- $file\n";
    }
}

// Check if the plugin is activated in WordPress
if (function_exists('is_plugin_active')) {
    if (is_plugin_active('status-sentry/status-sentry-wp.php')) {
        echo "\nThe plugin is activated in WordPress.\n";
    } else {
        echo "\nThe plugin is not activated in WordPress.\n";
    }
} else {
    echo "\nCould not determine if the plugin is activated in WordPress.\n";
}

// Check database tables
if (function_exists('get_option')) {
    global $wpdb;
    
    $tables = [
        $wpdb->prefix . 'status_sentry_queue',
        $wpdb->prefix . 'status_sentry_events',
    ];
    
    $missing_tables = [];
    foreach ($tables as $table) {
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
            $missing_tables[] = $table;
        }
    }
    
    if (empty($missing_tables)) {
        echo "\nAll required database tables exist.\n";
    } else {
        echo "\nThe following database tables are missing:\n";
        foreach ($missing_tables as $table) {
            echo "- $table\n";
        }
        echo "\nYou may need to run the database migrations.\n";
    }
} else {
    echo "\nCould not check database tables (WordPress not loaded).\n";
}

echo "\nVerification complete.\n";
