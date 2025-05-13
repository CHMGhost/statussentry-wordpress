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
 * Test the Resource Manager performance.
 *
 * @param array $targets Performance targets.
 * @return bool Whether the test passed.
 */
function test_resource_manager_performance($targets) {
    echo "Testing Resource Manager performance...\n";
    
    // Create an instance of the Resource Manager
    $resource_manager = new Status_Sentry_Resource_Manager();
    
    // Measure memory usage and execution time for should_continue method
    $start_memory = memory_get_usage();
    $start_time = microtime(true);
    
    // Run the method multiple times to get a good average
    for ($i = 0; $i < 100; $i++) {
        $resource_manager->should_continue('standard', $start_time, $start_memory, 10);
    }
    
    $end_time = microtime(true);
    $end_memory = memory_get_usage();
    
    // Calculate memory usage and execution time
    $memory_used_mb = ($end_memory - $start_memory) / (1024 * 1024);
    $time_used_sec = ($end_time - $start_time) / 100; // Average time per call
    
    // Check if the performance meets the targets
    $target = $targets['resource_manager_should_continue'];
    $memory_passed = $memory_used_mb <= $target['max_memory_mb'];
    $time_passed = $time_used_sec <= $target['max_time_sec'];
    $passed = $memory_passed && $time_passed;
    
    // Log the results
    echo "Resource Manager should_continue() performance:\n";
    echo "  Memory usage: " . number_format($memory_used_mb, 4) . " MB (target: " . $target['max_memory_mb'] . " MB) - " . ($memory_passed ? "PASSED" : "FAILED") . "\n";
    echo "  Execution time: " . number_format($time_used_sec * 1000, 4) . " ms (target: " . ($target['max_time_sec'] * 1000) . " ms) - " . ($time_passed ? "PASSED" : "FAILED") . "\n";
    
    return $passed;
}

/**
 * Test the Event Processor performance.
 *
 * @param array $targets Performance targets.
 * @return bool Whether the test passed.
 */
function test_event_processor_performance($targets) {
    echo "Testing Event Processor performance...\n";
    
    // Create an instance of the Event Processor
    $event_processor = new Status_Sentry_Event_Processor();
    
    // Create a mock event queue with test data
    $event_queue = new Status_Sentry_Event_Queue();
    
    // Enqueue some test events
    for ($i = 0; $i < 10; $i++) {
        $event_queue->enqueue(
            [
                'test_key' => 'test_value_' . $i,
                'timestamp' => microtime(true),
            ],
            'test_feature',
            'test_hook'
        );
    }
    
    // Measure memory usage and execution time for process_events method
    $start_memory = memory_get_usage();
    $start_time = microtime(true);
    
    // Process the events
    $event_processor->process_events(10);
    
    $end_time = microtime(true);
    $end_memory = memory_get_usage();
    
    // Calculate memory usage and execution time
    $memory_used_mb = ($end_memory - $start_memory) / (1024 * 1024);
    $time_used_sec = $end_time - $start_time;
    
    // Check if the performance meets the targets
    $target = $targets['event_processor_process_events'];
    $memory_passed = $memory_used_mb <= $target['max_memory_mb'];
    $time_passed = $time_used_sec <= $target['max_time_sec'];
    $passed = $memory_passed && $time_passed;
    
    // Log the results
    echo "Event Processor process_events() performance:\n";
    echo "  Memory usage: " . number_format($memory_used_mb, 4) . " MB (target: " . $target['max_memory_mb'] . " MB) - " . ($memory_passed ? "PASSED" : "FAILED") . "\n";
    echo "  Execution time: " . number_format($time_used_sec * 1000, 4) . " ms (target: " . ($target['max_time_sec'] * 1000) . " ms) - " . ($time_passed ? "PASSED" : "FAILED") . "\n";
    
    return $passed;
}

/**
 * Test the Query Cache performance.
 *
 * @param array $targets Performance targets.
 * @return bool Whether the test passed.
 */
function test_query_cache_performance($targets) {
    echo "Testing Query Cache performance...\n";
    
    // Create an instance of the Query Cache
    $query_cache = new Status_Sentry_Query_Cache();
    
    // Test set method
    $start_memory = memory_get_usage();
    $start_time = microtime(true);
    
    // Set multiple cache entries
    for ($i = 0; $i < 100; $i++) {
        $query_cache->set('test_key_' . $i, 'test_value_' . $i, 'test_group');
    }
    
    $end_time = microtime(true);
    $end_memory = memory_get_usage();
    
    // Calculate memory usage and execution time for set
    $memory_used_set_mb = ($end_memory - $start_memory) / (1024 * 1024);
    $time_used_set_sec = ($end_time - $start_time) / 100; // Average time per call
    
    // Test get method
    $start_memory = memory_get_usage();
    $start_time = microtime(true);
    
    // Get multiple cache entries
    for ($i = 0; $i < 100; $i++) {
        $query_cache->get('test_key_' . $i, 'test_group');
    }
    
    $end_time = microtime(true);
    $end_memory = memory_get_usage();
    
    // Calculate memory usage and execution time for get
    $memory_used_get_mb = ($end_memory - $start_memory) / (1024 * 1024);
    $time_used_get_sec = ($end_time - $start_time) / 100; // Average time per call
    
    // Check if the performance meets the targets
    $set_target = $targets['query_cache_set'];
    $get_target = $targets['query_cache_get'];
    
    $set_memory_passed = $memory_used_set_mb <= $set_target['max_memory_mb'];
    $set_time_passed = $time_used_set_sec <= $set_target['max_time_sec'];
    $get_memory_passed = $memory_used_get_mb <= $get_target['max_memory_mb'];
    $get_time_passed = $time_used_get_sec <= $get_target['max_time_sec'];
    
    $passed = $set_memory_passed && $set_time_passed && $get_memory_passed && $get_time_passed;
    
    // Log the results
    echo "Query Cache set() performance:\n";
    echo "  Memory usage: " . number_format($memory_used_set_mb, 4) . " MB (target: " . $set_target['max_memory_mb'] . " MB) - " . ($set_memory_passed ? "PASSED" : "FAILED") . "\n";
    echo "  Execution time: " . number_format($time_used_set_sec * 1000, 4) . " ms (target: " . ($set_target['max_time_sec'] * 1000) . " ms) - " . ($set_time_passed ? "PASSED" : "FAILED") . "\n";
    
    echo "Query Cache get() performance:\n";
    echo "  Memory usage: " . number_format($memory_used_get_mb, 4) . " MB (target: " . $get_target['max_memory_mb'] . " MB) - " . ($get_memory_passed ? "PASSED" : "FAILED") . "\n";
    echo "  Execution time: " . number_format($time_used_get_sec * 1000, 4) . " ms (target: " . ($get_target['max_time_sec'] * 1000) . " ms) - " . ($get_time_passed ? "PASSED" : "FAILED") . "\n";
    
    return $passed;
}

/**
 * Run all performance benchmark tests.
 */
function run_performance_benchmark_tests() {
    global $performance_targets;
    
    echo "Running performance benchmark tests...\n";
    
    $tests = [
        'test_resource_manager_performance',
        'test_event_processor_performance',
        'test_query_cache_performance',
    ];
    
    $passed = 0;
    $total = count($tests);
    
    foreach ($tests as $test) {
        if (function_exists($test)) {
            $result = call_user_func($test, $performance_targets);
            if ($result) {
                $passed++;
            }
        }
    }
    
    echo "\nPerformance benchmark results: $passed/$total tests passed.\n";
}

// Run the performance benchmark tests
run_performance_benchmark_tests();
