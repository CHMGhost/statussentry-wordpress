<?php
/**
 * Performance Comparison Test Script
 *
 * This script compares the performance of key components of the Status Sentry plugin
 * under different configurations. It runs benchmarks with various settings and
 * generates a comparative report to help identify the impact of configuration changes.
 *
 * @package    Status_Sentry
 * @subpackage Status_Sentry/tests
 */

// Define ABSPATH if it doesn't exist (for standalone testing)
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__) . '/');
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
 * Define test configurations
 */
$test_configurations = [
    'default' => [
        'label' => 'Default',
        'description' => 'Default plugin configuration',
        'settings' => []
    ],
    'high_performance' => [
        'label' => 'High Performance',
        'description' => 'Configuration optimized for performance',
        'settings' => [
            'db_batch_size' => 200,
            'memory_threshold' => 70,
            'gc_cycles' => 5,
            'cpu_threshold' => 60,
            'enable_query_cache' => 1,
            'query_cache_ttl' => 7200,
            'enable_resumable_tasks' => 1
        ]
    ],
    'low_memory' => [
        'label' => 'Low Memory',
        'description' => 'Configuration optimized for low memory usage',
        'settings' => [
            'db_batch_size' => 50,
            'memory_threshold' => 60,
            'gc_cycles' => 5,
            'cpu_threshold' => 70,
            'enable_query_cache' => 0,
            'enable_resumable_tasks' => 0
        ]
    ],
    'balanced' => [
        'label' => 'Balanced',
        'description' => 'Balanced configuration for general use',
        'settings' => [
            'db_batch_size' => 100,
            'memory_threshold' => 75,
            'gc_cycles' => 3,
            'cpu_threshold' => 65,
            'enable_query_cache' => 1,
            'query_cache_ttl' => 3600,
            'enable_resumable_tasks' => 1
        ]
    ]
];

/**
 * Run performance comparison tests.
 *
 * @param array $configurations Test configurations.
 * @param bool  $output         Whether to output results.
 * @return array Comparison results.
 */
function run_performance_comparison($configurations, $output = true) {
    $results = [];

    if ($output) {
        echo "Running performance comparison tests...\n\n";
    }

    foreach ($configurations as $key => $config) {
        if ($output) {
            echo "Testing configuration: {$config['label']} ({$config['description']})\n";
            echo "------------------------------------------------------------\n";
        }

        $runner = new Status_Sentry_Benchmark_Runner([], $config);
        $config_results = $runner->run_all($output);

        $results[$key] = [
            'label' => $config['label'],
            'description' => $config['description'],
            'results' => $config_results
        ];

        if ($output) {
            echo "\n";
        }
    }

    return $results;
}

/**
 * Generate a comparison table from benchmark results.
 *
 * @param array $results Benchmark results.
 * @return void
 */
function generate_comparison_table($results) {
    echo "Performance Comparison Results\n";
    echo "=============================\n\n";

    // Get all operations from the first configuration
    $first_config = reset($results);
    $operations = [];

    foreach ($first_config['results'] as $test => $result) {
        if (isset($result['operation'])) {
            $operations[] = $result['operation'];
        }
    }

    // Generate table header
    echo "| Operation | Metric | ";
    foreach ($results as $config_key => $config_data) {
        echo "{$config_data['label']} | ";
    }
    echo "\n";

    echo "|-----------|--------|";
    foreach ($results as $config_key => $config_data) {
        echo "------------|";
    }
    echo "\n";

    // Generate table rows for each operation
    foreach ($operations as $operation) {
        // Skip Query Cache operations as it has special handling
        if ($operation === 'Query Cache operations') {
            continue;
        }

        // Memory usage row
        echo "| $operation | Memory (MB) | ";
        foreach ($results as $config_key => $config_data) {
            $found = false;
            foreach ($config_data['results'] as $test => $result) {
                if (isset($result['operation']) && $result['operation'] === $operation) {
                    echo number_format($result['memory_mb'], 4) . " | ";
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                echo "N/A | ";
            }
        }
        echo "\n";

        // Execution time row
        echo "| $operation | Time (ms) | ";
        foreach ($results as $config_key => $config_data) {
            $found = false;
            foreach ($config_data['results'] as $test => $result) {
                if (isset($result['operation']) && $result['operation'] === $operation) {
                    echo number_format($result['time_ms'], 4) . " | ";
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                echo "N/A | ";
            }
        }
        echo "\n";
    }

    // Special handling for Query Cache which has multiple metrics
    echo "| Query Cache set() | Memory (MB) | ";
    foreach ($results as $config_key => $config_data) {
        $found = false;
        foreach ($config_data['results'] as $test => $result) {
            if ($test === 'test_query_cache_performance') {
                echo number_format($result['set_memory_mb'], 4) . " | ";
                $found = true;
                break;
            }
        }
        if (!$found) {
            echo "N/A | ";
        }
    }
    echo "\n";

    echo "| Query Cache set() | Time (ms) | ";
    foreach ($results as $config_key => $config_data) {
        $found = false;
        foreach ($config_data['results'] as $test => $result) {
            if ($test === 'test_query_cache_performance') {
                echo number_format($result['set_time_ms'], 4) . " | ";
                $found = true;
                break;
            }
        }
        if (!$found) {
            echo "N/A | ";
        }
    }
    echo "\n";

    echo "| Query Cache get() | Memory (MB) | ";
    foreach ($results as $config_key => $config_data) {
        $found = false;
        foreach ($config_data['results'] as $test => $result) {
            if ($test === 'test_query_cache_performance') {
                echo number_format($result['get_memory_mb'], 4) . " | ";
                $found = true;
                break;
            }
        }
        if (!$found) {
            echo "N/A | ";
        }
    }
    echo "\n";

    echo "| Query Cache get() | Time (ms) | ";
    foreach ($results as $config_key => $config_data) {
        $found = false;
        foreach ($config_data['results'] as $test => $result) {
            if ($test === 'test_query_cache_performance') {
                echo number_format($result['get_time_ms'], 4) . " | ";
                $found = true;
                break;
            }
        }
        if (!$found) {
            echo "N/A | ";
        }
    }
    echo "\n\n";

    // Summary
    echo "Summary\n";
    echo "-------\n\n";

    foreach ($results as $config_key => $config_data) {
        $passed = 0;
        $total = 0;

        foreach ($config_data['results'] as $test => $result) {
            if (isset($result['passed'])) {
                $total++;
                if ($result['passed']) {
                    $passed++;
                }
            }
        }

        echo "{$config_data['label']}: $passed/$total tests passed\n";
    }
}

// Run the performance comparison tests
$comparison_results = run_performance_comparison($test_configurations);

// Generate a comparison table
generate_comparison_table($comparison_results);
