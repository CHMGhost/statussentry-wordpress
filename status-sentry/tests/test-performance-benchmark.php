<?php
/**
 * Performance Benchmark Test Script
 *
 * This script tests the performance of key components of the Status Sentry plugin.
 * It measures memory usage and execution time for core operations and compares
 * them against defined targets to ensure the plugin remains efficient.
 *
 * @package    Status_Sentry
 * @subpackage Status_Sentry/tests
 */

// Ensure WordPress is loaded
if (!function_exists('add_filter')) {
    die('WordPress environment is required for benchmarking. Please run through the WordPress bootstrap.');
}

// Define the plugin constants if not already defined
if (!defined('STATUS_SENTRY_VERSION')) {
    define('STATUS_SENTRY_VERSION', '1.3.0');
}
if (!defined('STATUS_SENTRY_PLUGIN_DIR')) {
    define('STATUS_SENTRY_PLUGIN_DIR', dirname(__DIR__) . '/');
}

// Include the necessary files
require_once STATUS_SENTRY_PLUGIN_DIR . 'includes/monitoring/interface-status-sentry-monitoring.php';
require_once STATUS_SENTRY_PLUGIN_DIR . 'includes/monitoring/interface-status-sentry-monitoring-handler.php';
require_once STATUS_SENTRY_PLUGIN_DIR . 'includes/monitoring/class-status-sentry-monitoring-event.php';
require_once STATUS_SENTRY_PLUGIN_DIR . 'includes/monitoring/class-status-sentry-baseline.php';
require_once STATUS_SENTRY_PLUGIN_DIR . 'includes/monitoring/class-status-sentry-resource-manager.php';
require_once STATUS_SENTRY_PLUGIN_DIR . 'includes/data/class-status-sentry-data-capture.php';
require_once STATUS_SENTRY_PLUGIN_DIR . 'includes/data/class-status-sentry-data-filter.php';
require_once STATUS_SENTRY_PLUGIN_DIR . 'includes/data/class-status-sentry-sampling-manager.php';
require_once STATUS_SENTRY_PLUGIN_DIR . 'includes/data/class-status-sentry-event-queue.php';
require_once STATUS_SENTRY_PLUGIN_DIR . 'includes/data/class-status-sentry-event-processor.php';
require_once STATUS_SENTRY_PLUGIN_DIR . 'includes/db/class-status-sentry-query-cache.php';
require_once STATUS_SENTRY_PLUGIN_DIR . 'includes/benchmarking/class-status-sentry-benchmark-runner.php';

/**
 * Define performance targets
 */
$performance_targets = [
    'resource_manager_should_continue' => [
        'max_memory_mb' => 2,
        'max_time_sec' => 0.01,
        'description' => 'Resource Manager should_continue() method'
    ],
    'event_processor_process_events' => [
        'max_memory_mb' => 10,
        'max_time_sec' => 0.5,
        'description' => 'Event Processor process_events() method with 10 events'
    ],
    'event_queue_enqueue' => [
        'max_memory_mb' => 1,
        'max_time_sec' => 0.05,
        'description' => 'Event Queue enqueue() method'
    ],
    'query_cache_get' => [
        'max_memory_mb' => 1,
        'max_time_sec' => 0.01,
        'description' => 'Query Cache get() method'
    ],
    'query_cache_set' => [
        'max_memory_mb' => 1,
        'max_time_sec' => 0.01,
        'description' => 'Query Cache set() method'
    ],
    'data_capture_capture' => [
        'max_memory_mb' => 2,
        'max_time_sec' => 0.05,
        'description' => 'Data Capture capture() method'
    ]
];

/**
 * Run all performance benchmark tests.
 */
function run_performance_benchmark_tests() {
    global $performance_targets;

    // Create a benchmark runner with default configuration
    $runner = new Status_Sentry_Benchmark_Runner($performance_targets);

    // Run all benchmarks with output
    $runner->run_all(true);
}

// Run the performance benchmark tests
run_performance_benchmark_tests();
