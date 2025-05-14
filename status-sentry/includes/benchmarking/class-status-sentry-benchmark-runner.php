<?php
/**
 * Benchmark Runner Class
 *
 * This class provides a programmatic interface for running performance benchmarks
 * on key components of the Status Sentry plugin. It can be used to measure
 * performance under different configurations and compare results.
 *
 * @package    Status_Sentry
 * @subpackage Status_Sentry/includes/benchmarking
 * @since      1.4.0
 */

// Ensure WordPress is loaded
if (!function_exists('add_filter')) {
    die('WordPress environment is required for benchmarking. Please run through the WordPress bootstrap.');
}

// Ensure we're in a WordPress environment with database access
global $wpdb;

// Log that we're using the real WordPress environment
error_log('Status Sentry Benchmark: Using real WordPress environment');

class Status_Sentry_Benchmark_Runner {

    /**
     * Performance targets for benchmarks.
     *
     * @since    1.4.0
     * @access   private
     * @var      array    $targets    Performance targets.
     */
    private $targets;

    /**
     * Results of benchmark runs.
     *
     * @since    1.4.0
     * @access   private
     * @var      array    $results    Benchmark results.
     */
    private $results = [];

    /**
     * Configuration used for the benchmark.
     *
     * @since    1.4.0
     * @access   private
     * @var      array    $config     Benchmark configuration.
     */
    private $config = [];

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.4.0
     * @param    array    $targets    Optional. Performance targets. Default empty array.
     * @param    array    $config     Optional. Benchmark configuration. Default empty array.
     */
    public function __construct($targets = [], $config = []) {
        // Set default targets if none provided
        if (empty($targets)) {
            $this->targets = [
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
        } else {
            $this->targets = $targets;
        }

        // Set configuration
        $this->config = array_merge([
            'label' => 'Default',
            'description' => 'Default configuration',
            'settings' => []
        ], $config);
    }

    /**
     * Run all benchmarks.
     *
     * @since    1.4.0
     * @param    bool    $output    Optional. Whether to output results. Default false.
     * @return   array              Benchmark results.
     */
    public function run_all($output = false) {
        if ($output) {
            echo "Running performance benchmark tests with configuration: {$this->config['label']}...\n";
        }

        // Apply configuration settings if any
        $this->apply_configuration();

        // Run individual benchmarks
        $tests = [
            'test_resource_manager_performance',
            'test_event_processor_performance',
            'test_query_cache_performance',
            'test_event_queue_performance',
            'test_data_capture_performance',
        ];

        $passed = 0;
        $total = count($tests);
        $this->results = [];

        foreach ($tests as $test) {
            if (method_exists($this, $test)) {
                $result = $this->$test($output);
                if ($result['passed']) {
                    $passed++;
                }
                $this->results[$test] = $result;
            }
        }

        if ($output) {
            echo "\nPerformance benchmark results: $passed/$total tests passed.\n";
        }

        // Restore original configuration
        $this->restore_configuration();

        return $this->results;
    }

    /**
     * Apply configuration settings for benchmarking.
     *
     * @since    1.4.0
     * @access   private
     * @return   void
     */
    private function apply_configuration() {
        if (empty($this->config['settings'])) {
            return;
        }

        // Save current settings
        $this->config['original_settings'] = get_option('status_sentry_settings', []);

        // Apply new settings
        $current_settings = get_option('status_sentry_settings', []);
        $new_settings = array_merge($current_settings, $this->config['settings']);
        update_option('status_sentry_settings', $new_settings);
    }

    /**
     * Restore original configuration after benchmarking.
     *
     * @since    1.4.0
     * @access   private
     * @return   void
     */
    private function restore_configuration() {
        if (isset($this->config['original_settings'])) {
            update_option('status_sentry_settings', $this->config['original_settings']);
        }
    }

    /**
     * Test the Resource Manager performance.
     *
     * @since    1.4.0
     * @param    bool    $output    Optional. Whether to output results. Default false.
     * @return   array              Test results.
     */
    public function test_resource_manager_performance($output = false) {
        if ($output) {
            echo "Testing Resource Manager performance...\n";
        }

        // Create an instance of the Resource Manager
        $resource_manager = new Status_Sentry_Resource_Manager();

        // Measure memory usage and execution time for should_continue method
        $start_memory = memory_get_usage();
        $start_time = microtime(true);

        // Test the real Resource Manager should_continue method
        for ($i = 0; $i < 100; $i++) {
            // Use different tiers to test performance
            $tiers = ['critical', 'standard', 'intensive', 'report'];
            $tier = $tiers[$i % count($tiers)];

            // Set task start time and memory to simulate a running task
            $task_start_time = $start_time - rand(1, 10);
            $task_start_memory = $start_memory - (rand(1, 10) * 1024 * 1024);

            // Call the actual should_continue method
            $resource_manager->should_continue($tier, $task_start_time, $task_start_memory);
        }

        $end_time = microtime(true);
        $end_memory = memory_get_usage();

        // Calculate memory usage and execution time
        $memory_used_mb = ($end_memory - $start_memory) / (1024 * 1024);
        $time_used_sec = ($end_time - $start_time) / 100; // Average time per call

        // Check if the performance meets the targets
        $target = $this->targets['resource_manager_should_continue'];
        $memory_passed = $memory_used_mb <= $target['max_memory_mb'];
        $time_passed = $time_used_sec <= $target['max_time_sec'];
        $passed = $memory_passed && $time_passed;

        // Log the results
        if ($output) {
            echo "Resource Manager should_continue() performance:\n";
            echo "  Memory usage: " . number_format($memory_used_mb, 4) . " MB (target: " . $target['max_memory_mb'] . " MB) - " . ($memory_passed ? "PASSED" : "FAILED") . "\n";
            echo "  Execution time: " . number_format($time_used_sec * 1000, 4) . " ms (target: " . ($target['max_time_sec'] * 1000) . " ms) - " . ($time_passed ? "PASSED" : "FAILED") . "\n";
        }

        // Calculate operations per second (100 operations / total time in seconds)
        $operations_per_second = 100 / ($end_time - $start_time);

        return [
            'operation' => 'Resource Manager should_continue()',
            'memory_mb' => $memory_used_mb,
            'time_ms' => $time_used_sec * 1000,
            'memory_target_mb' => $target['max_memory_mb'],
            'time_target_ms' => $target['max_time_sec'] * 1000,
            'memory_passed' => $memory_passed,
            'time_passed' => $time_passed,
            'passed' => $passed,
            'config' => $this->config['label'],
            // Add keys expected by admin UI
            'memory_usage' => $memory_used_mb * 1024 * 1024, // Convert MB to bytes
            'execution_time' => $time_used_sec,
            'operations_per_second' => $operations_per_second
        ];
    }

    /**
     * Get benchmark results.
     *
     * @since    1.4.0
     * @return   array    Benchmark results.
     */
    public function get_results() {
        return $this->results;
    }

    /**
     * Get configuration label.
     *
     * @since    1.4.0
     * @return   string    Configuration label.
     */
    public function get_config_label() {
        return $this->config['label'];
    }

    /**
     * Get configuration description.
     *
     * @since    1.4.0
     * @return   string    Configuration description.
     */
    public function get_config_description() {
        return $this->config['description'];
    }

    /**
     * Test the Event Processor performance.
     *
     * @since    1.4.0
     * @param    bool    $output    Optional. Whether to output results. Default false.
     * @return   array              Test results.
     */
    public function test_event_processor_performance($output = false) {
        if ($output) {
            echo "Testing Event Processor performance...\n";
        }

        // Create an instance of the Event Processor
        $event_processor = new Status_Sentry_Event_Processor();

        // Create an instance of the Event Queue to add test events
        $event_queue = new Status_Sentry_Event_Queue();

        // Get real WordPress data for testing
        global $wpdb;
        $post_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'publish'");
        $user_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->users}");
        $plugin_count = count(get_option('active_plugins', []));

        if ($output) {
            echo "  - Using real WordPress data: {$post_count} posts, {$user_count} users, {$plugin_count} active plugins\n";
        }

        // Add some realistic test events to the queue
        $features = ['posts', 'users', 'plugins', 'themes', 'options', 'comments', 'media'];
        $hooks = ['save_post', 'wp_login', 'activate_plugin', 'switch_theme', 'update_option', 'wp_insert_comment', 'add_attachment'];

        for ($i = 0; $i < 10; $i++) {
            $feature = $features[$i % count($features)];
            $hook = $hooks[$i % count($hooks)];

            // Create realistic test data based on the feature
            $data = [];
            switch ($feature) {
                case 'posts':
                    $recent_posts = get_posts(['numberposts' => 5, 'post_status' => 'publish']);
                    if (!empty($recent_posts)) {
                        $post = $recent_posts[0];
                        $data = [
                            'post_id' => $post->ID,
                            'post_title' => $post->post_title,
                            'post_status' => $post->post_status,
                            'post_type' => $post->post_type,
                            'timestamp' => current_time('mysql')
                        ];
                    } else {
                        $data = [
                            'post_id' => $i + 1,
                            'post_title' => 'Test Post ' . ($i + 1),
                            'post_status' => 'publish',
                            'post_type' => 'post',
                            'timestamp' => current_time('mysql')
                        ];
                    }
                    break;
                case 'users':
                    $data = [
                        'user_count' => $user_count,
                        'current_user' => is_user_logged_in() ? get_current_user_id() : 0,
                        'timestamp' => current_time('mysql')
                    ];
                    break;
                default:
                    $data = [
                        'feature' => $feature,
                        'hook' => $hook,
                        'test_id' => $i + 1,
                        'timestamp' => current_time('mysql')
                    ];
            }

            $event_queue->enqueue($data, $feature, $hook);
        }

        // Trigger garbage collection to ensure a clean state before measuring
        if (function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }

        // Start measuring execution time
        $start_time = microtime(true);

        // Measure memory usage right before processing to isolate processing memory
        $start_memory = memory_get_usage();
        $start_peak_memory = memory_get_peak_usage();

        // Process the events using the real Event Processor
        $processed_count = $event_processor->process_events(10);

        if ($output) {
            echo "  - Processed {$processed_count} events\n";
        }

        $end_time = microtime(true);
        $end_memory = memory_get_usage();
        $end_peak_memory = memory_get_peak_usage();

        // Calculate memory usage and execution time
        $memory_used_mb = ($end_memory - $start_memory) / (1024 * 1024);
        $peak_memory_used_mb = ($end_peak_memory - $start_peak_memory) / (1024 * 1024);
        $time_used_sec = $end_time - $start_time;

        // Clamp negative memory delta to zero (can happen due to garbage collection during processing)
        if ($memory_used_mb < 0) {
            if ($output) {
                echo "  - Note: Negative memory delta detected, clamping to zero (garbage collection occurred during processing)\n";
            }
            $memory_used_mb = 0;
        }

        // Check if the performance meets the targets
        $target = $this->targets['event_processor_process_events'];
        $memory_passed = $memory_used_mb <= $target['max_memory_mb'];
        $time_passed = $time_used_sec <= $target['max_time_sec'];
        $passed = $memory_passed && $time_passed;

        // Log the results
        if ($output) {
            echo "Event Processor process_events() performance:\n";
            echo "  Memory usage: " . number_format($memory_used_mb, 4) . " MB (target: " . $target['max_memory_mb'] . " MB) - " . ($memory_passed ? "PASSED" : "FAILED") . "\n";
            echo "  Execution time: " . number_format($time_used_sec * 1000, 4) . " ms (target: " . ($target['max_time_sec'] * 1000) . " ms) - " . ($time_passed ? "PASSED" : "FAILED") . "\n";
        }

        // Calculate operations per second (number of events processed / total time in seconds)
        $operations_per_second = $processed_count / $time_used_sec;

        return [
            'operation' => 'Event Processor process_events()',
            'memory_mb' => $memory_used_mb,
            'time_ms' => $time_used_sec * 1000,
            'memory_target_mb' => $target['max_memory_mb'],
            'time_target_ms' => $target['max_time_sec'] * 1000,
            'memory_passed' => $memory_passed,
            'time_passed' => $time_passed,
            'passed' => $passed,
            'config' => $this->config['label'],
            'peak_memory_mb' => $peak_memory_used_mb,
            // Add keys expected by admin UI
            'memory_usage' => $memory_used_mb * 1024 * 1024, // Convert MB to bytes
            'execution_time' => $time_used_sec,
            'operations_per_second' => $operations_per_second,
            'peak_memory_usage' => $peak_memory_used_mb * 1024 * 1024 // Convert MB to bytes
        ];
    }

    /**
     * Test the Query Cache performance.
     *
     * @since    1.4.0
     * @param    bool    $output    Optional. Whether to output results. Default false.
     * @return   array              Test results.
     */
    public function test_query_cache_performance($output = false) {
        if ($output) {
            echo "Testing Query Cache performance...\n";
        }

        // Create an instance of the Query Cache
        $query_cache = new Status_Sentry_Query_Cache();

        // Test set method
        $start_memory = memory_get_usage();
        $start_time = microtime(true);

        // Set cache entries using the real Query Cache
        for ($i = 0; $i < 100; $i++) {
            $key = 'test_key_' . $i;
            $value = 'test_value_' . $i;
            $group = 'test_group';
            $query_cache->set($key, $value, $group);
        }

        $end_time = microtime(true);
        $end_memory = memory_get_usage();

        // Calculate memory usage and execution time for set
        $memory_used_set_mb = ($end_memory - $start_memory) / (1024 * 1024);
        $time_used_set_sec = ($end_time - $start_time) / 100; // Average time per call

        // Test get method
        $start_memory = memory_get_usage();
        $start_time = microtime(true);

        // Get cache entries using the real Query Cache
        for ($i = 0; $i < 100; $i++) {
            $key = 'test_key_' . $i;
            $group = 'test_group';
            $value = $query_cache->get($key, $group);
        }

        $end_time = microtime(true);
        $end_memory = memory_get_usage();

        // Calculate memory usage and execution time for get
        $memory_used_get_mb = ($end_memory - $start_memory) / (1024 * 1024);
        $time_used_get_sec = ($end_time - $start_time) / 100; // Average time per call

        // Check if the performance meets the targets
        $set_target = $this->targets['query_cache_set'];
        $get_target = $this->targets['query_cache_get'];

        $set_memory_passed = $memory_used_set_mb <= $set_target['max_memory_mb'];
        $set_time_passed = $time_used_set_sec <= $set_target['max_time_sec'];
        $get_memory_passed = $memory_used_get_mb <= $get_target['max_memory_mb'];
        $get_time_passed = $time_used_get_sec <= $get_target['max_time_sec'];

        $passed = $set_memory_passed && $set_time_passed && $get_memory_passed && $get_time_passed;

        // Log the results
        if ($output) {
            echo "Query Cache set() performance:\n";
            echo "  Memory usage: " . number_format($memory_used_set_mb, 4) . " MB (target: " . $set_target['max_memory_mb'] . " MB) - " . ($set_memory_passed ? "PASSED" : "FAILED") . "\n";
            echo "  Execution time: " . number_format($time_used_set_sec * 1000, 4) . " ms (target: " . ($set_target['max_time_sec'] * 1000) . " ms) - " . ($set_time_passed ? "PASSED" : "FAILED") . "\n";

            echo "Query Cache get() performance:\n";
            echo "  Memory usage: " . number_format($memory_used_get_mb, 4) . " MB (target: " . $get_target['max_memory_mb'] . " MB) - " . ($get_memory_passed ? "PASSED" : "FAILED") . "\n";
            echo "  Execution time: " . number_format($time_used_get_sec * 1000, 4) . " ms (target: " . ($get_target['max_time_sec'] * 1000) . " ms) - " . ($get_time_passed ? "PASSED" : "FAILED") . "\n";
        }

        // Calculate average memory usage and execution time
        $avg_memory_mb = ($memory_used_set_mb + $memory_used_get_mb) / 2;
        $avg_time_sec = ($time_used_set_sec + $time_used_get_sec) / 2;

        // Calculate operations per second (200 operations / total time in seconds)
        $operations_per_second = 200 / (($end_time - $start_time) + ($time_used_set_sec * 100));

        return [
            'operation' => 'Query Cache operations',
            'set_memory_mb' => $memory_used_set_mb,
            'set_time_ms' => $time_used_set_sec * 1000,
            'get_memory_mb' => $memory_used_get_mb,
            'get_time_ms' => $time_used_get_sec * 1000,
            'set_memory_target_mb' => $set_target['max_memory_mb'],
            'set_time_target_ms' => $set_target['max_time_sec'] * 1000,
            'get_memory_target_mb' => $get_target['max_memory_mb'],
            'get_time_target_ms' => $get_target['max_time_sec'] * 1000,
            'set_memory_passed' => $set_memory_passed,
            'set_time_passed' => $set_time_passed,
            'get_memory_passed' => $get_memory_passed,
            'get_time_passed' => $get_time_passed,
            'passed' => $passed,
            'config' => $this->config['label'],
            // Add keys expected by admin UI
            'memory_usage' => $avg_memory_mb * 1024 * 1024, // Convert MB to bytes
            'execution_time' => $avg_time_sec,
            'operations_per_second' => $operations_per_second
        ];
    }

    /**
     * Test the Event Queue performance.
     *
     * @since    1.4.0
     * @param    bool    $output    Optional. Whether to output results. Default false.
     * @return   array              Test results.
     */
    public function test_event_queue_performance($output = false) {
        if ($output) {
            echo "Testing Event Queue performance...\n";
        }

        // Create an instance of the Event Queue
        $event_queue = new Status_Sentry_Event_Queue();

        // Measure memory usage and execution time for enqueue method
        $start_memory = memory_get_usage();
        $start_time = microtime(true);

        // Enqueue events using the real Event Queue
        for ($i = 0; $i < 10; $i++) {
            $data = [
                'test_data' => $i,
                'timestamp' => microtime(true)
            ];
            $event_queue->enqueue($data, 'test_feature', 'test_hook');
        }

        $end_time = microtime(true);
        $end_memory = memory_get_usage();

        // Calculate memory usage and execution time
        $memory_used_mb = ($end_memory - $start_memory) / (1024 * 1024);
        $time_used_sec = ($end_time - $start_time) / 10; // Average time per call

        // Check if the performance meets the targets
        $target = $this->targets['event_queue_enqueue'];
        $memory_passed = $memory_used_mb <= $target['max_memory_mb'];
        $time_passed = $time_used_sec <= $target['max_time_sec'];
        $passed = $memory_passed && $time_passed;

        // Log the results
        if ($output) {
            echo "Event Queue enqueue() performance:\n";
            echo "  Memory usage: " . number_format($memory_used_mb, 4) . " MB (target: " . $target['max_memory_mb'] . " MB) - " . ($memory_passed ? "PASSED" : "FAILED") . "\n";
            echo "  Execution time: " . number_format($time_used_sec * 1000, 4) . " ms (target: " . ($target['max_time_sec'] * 1000) . " ms) - " . ($time_passed ? "PASSED" : "FAILED") . "\n";
        }

        // Calculate operations per second (10 operations / total time in seconds)
        $operations_per_second = 10 / ($end_time - $start_time);

        return [
            'operation' => 'Event Queue enqueue()',
            'memory_mb' => $memory_used_mb,
            'time_ms' => $time_used_sec * 1000,
            'memory_target_mb' => $target['max_memory_mb'],
            'time_target_ms' => $target['max_time_sec'] * 1000,
            'memory_passed' => $memory_passed,
            'time_passed' => $time_passed,
            'passed' => $passed,
            'config' => $this->config['label'],
            // Add keys expected by admin UI
            'memory_usage' => $memory_used_mb * 1024 * 1024, // Convert MB to bytes
            'execution_time' => $time_used_sec,
            'operations_per_second' => $operations_per_second
        ];
    }

    /**
     * Test the Data Capture performance.
     *
     * @since    1.4.0
     * @param    bool    $output    Optional. Whether to output results. Default false.
     * @return   array              Test results.
     */
    public function test_data_capture_performance($output = false) {
        if ($output) {
            echo "Testing Data Capture performance...\n";
        }

        // Create an instance of the Data Capture
        $data_capture = new Status_Sentry_Data_Capture();

        // Get real WordPress data for testing
        global $wpdb;
        $post_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'publish'");
        $user_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->users}");
        $plugin_count = count(get_option('active_plugins', []));

        if ($output) {
            echo "  - Using real WordPress data: {$post_count} posts, {$user_count} users, {$plugin_count} active plugins\n";
        }

        // Measure memory usage and execution time for capture method
        $start_memory = memory_get_usage();
        $start_time = microtime(true);

        // Capture data using the real Data Capture with real WordPress data
        for ($i = 0; $i < 10; $i++) {
            // Use different features and hooks for more realistic testing
            $features = ['posts', 'users', 'plugins', 'themes', 'options'];
            $hooks = ['save_post', 'wp_login', 'activate_plugin', 'switch_theme', 'update_option'];

            $feature = $features[$i % count($features)];
            $hook = $hooks[$i % count($hooks)];

            // Create realistic test data based on the feature
            $test_data = [];
            switch ($feature) {
                case 'posts':
                    $recent_posts = get_posts(['numberposts' => 5, 'post_status' => 'publish']);
                    $test_data = !empty($recent_posts) ? (array)$recent_posts[0] : ['post_title' => 'Test Post'];
                    break;
                case 'users':
                    $test_data = [
                        'user_count' => $user_count,
                        'current_user' => is_user_logged_in() ? get_current_user_id() : 0,
                        'timestamp' => current_time('mysql')
                    ];
                    break;
                case 'plugins':
                    $test_data = [
                        'active_plugins' => $plugin_count,
                        'plugin_name' => 'Status Sentry WP',
                        'timestamp' => current_time('mysql')
                    ];
                    break;
                default:
                    $test_data = [
                        'test_data' => $i,
                        'timestamp' => current_time('mysql')
                    ];
            }

            $data_capture->capture($feature, $hook, $test_data);
        }

        $end_time = microtime(true);
        $end_memory = memory_get_usage();

        // Calculate memory usage and execution time
        $memory_used_mb = ($end_memory - $start_memory) / (1024 * 1024);
        $time_used_sec = ($end_time - $start_time) / 10; // Average time per call

        // Check if the performance meets the targets
        $target = $this->targets['data_capture_capture'];
        $memory_passed = $memory_used_mb <= $target['max_memory_mb'];
        $time_passed = $time_used_sec <= $target['max_time_sec'];
        $passed = $memory_passed && $time_passed;

        // Log the results
        if ($output) {
            echo "Data Capture capture() performance:\n";
            echo "  Memory usage: " . number_format($memory_used_mb, 4) . " MB (target: " . $target['max_memory_mb'] . " MB) - " . ($memory_passed ? "PASSED" : "FAILED") . "\n";
            echo "  Execution time: " . number_format($time_used_sec * 1000, 4) . " ms (target: " . ($target['max_time_sec'] * 1000) . " ms) - " . ($time_passed ? "PASSED" : "FAILED") . "\n";
        }

        // Calculate operations per second (10 operations / total time in seconds)
        $operations_per_second = 10 / ($end_time - $start_time);

        return [
            'operation' => 'Data Capture capture()',
            'memory_mb' => $memory_used_mb,
            'time_ms' => $time_used_sec * 1000,
            'memory_target_mb' => $target['max_memory_mb'],
            'time_target_ms' => $target['max_time_sec'] * 1000,
            'memory_passed' => $memory_passed,
            'time_passed' => $time_passed,
            'passed' => $passed,
            'config' => $this->config['label'],
            // Add keys expected by admin UI
            'memory_usage' => $memory_used_mb * 1024 * 1024, // Convert MB to bytes
            'execution_time' => $time_used_sec,
            'operations_per_second' => $operations_per_second
        ];
    }
}
