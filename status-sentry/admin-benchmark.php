<?php
/**
 * Admin Benchmark
 *
 * This script adds a benchmark page to the WordPress admin.
 */

// Debug: Log when this file is loaded
error_log('Status Sentry: admin-benchmark.php is being loaded');

// Add the benchmark page to the admin menu
// Use a later priority to ensure it runs after the main menu is registered
add_action('admin_menu', 'status_sentry_add_benchmark_page', 20);

// Add a direct link to the benchmark page in the admin bar
add_action('admin_bar_menu', 'status_sentry_add_benchmark_admin_bar_link', 100);

/**
 * Add a direct link to the benchmark page in the admin bar.
 *
 * @param WP_Admin_Bar $admin_bar The admin bar object.
 */
function status_sentry_add_benchmark_admin_bar_link($admin_bar) {
    if (!current_user_can('manage_options')) {
        return;
    }

    $admin_bar->add_node([
        'id'    => 'status-sentry-benchmark',
        'title' => 'Status Sentry Benchmark',
        'href'  => admin_url('admin.php?page=status-sentry-benchmark'),
        'meta'  => [
            'title' => 'Run Status Sentry Benchmark',
        ],
    ]);
}

/**
 * Add the benchmark pages to the admin menu.
 */
function status_sentry_add_benchmark_page() {
    // Debug: Log when benchmark pages are being added
    error_log('Status Sentry: Adding benchmark pages to admin menu');

    // Check if the main menu exists by looking for the global $submenu array
    global $submenu;
    if (!isset($submenu['status-sentry'])) {
        error_log('Status Sentry: Main menu not found, cannot add benchmark pages');
        return;
    }

    error_log('Status Sentry: Main menu found, adding benchmark pages');

    // Add main benchmark page
    add_submenu_page(
        'status-sentry', // Parent slug must match the main menu slug
        'Status Sentry Benchmark',
        'Benchmark',
        'manage_options',
        'status-sentry-benchmark',
        'status_sentry_benchmark_page'
    );

    // Add benchmark history page
    add_submenu_page(
        'status-sentry', // Parent slug must match the main menu slug
        'Benchmark History',
        'Benchmark History',
        'manage_options',
        'status-sentry-benchmark-history',
        'status_sentry_benchmark_history_page'
    );

    // Debug: Log the current screen to help diagnose issues
    add_action('current_screen', function($screen) {
        if (isset($_GET['page']) && ($_GET['page'] === 'status-sentry-benchmark' || $_GET['page'] === 'status-sentry-benchmark-history')) {
            error_log('Status Sentry: Current screen for benchmark page: ' . print_r($screen, true));
        }
    });
}

/**
 * Display the benchmark page.
 */
function status_sentry_benchmark_page() {
    // Debug: Log when benchmark page function is called
    error_log('Status Sentry: status_sentry_benchmark_page function called');
    error_log('Status Sentry: $_GET = ' . print_r($_GET, true));
    error_log('Status Sentry: $_POST = ' . print_r($_POST, true));

    // Check user capabilities
    if (!current_user_can('manage_options')) {
        error_log('Status Sentry: User does not have manage_options capability');
        return;
    }

    // Check if the benchmark should be run
    $run_benchmark = isset($_POST['run_benchmark']) && $_POST['run_benchmark'] === '1';
    $benchmark_type = isset($_POST['benchmark_type']) ? sanitize_text_field($_POST['benchmark_type']) : 'all';

    // Debug: Log benchmark run parameters
    if ($run_benchmark) {
        error_log('Status Sentry: Benchmark run requested, type: ' . $benchmark_type);
    }

    // Enqueue Chart.js
    wp_enqueue_script('chartjs', 'https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.js', [], '3.7.1', true);

    // Enqueue benchmark CSS and JS
    wp_enqueue_style('status-sentry-benchmark-css', plugin_dir_url(STATUS_SENTRY_PLUGIN_DIR . 'status-sentry.php') . 'assets/css/benchmark.css', [], '1.6.0');
    wp_enqueue_script('status-sentry-benchmark-js', plugin_dir_url(STATUS_SENTRY_PLUGIN_DIR . 'status-sentry.php') . 'assets/js/benchmark.js', ['jquery', 'chartjs'], '1.6.0', true);

    // Add inline script to ensure toggle button works
    wp_add_inline_script('status-sentry-benchmark-js', '
        jQuery(document).ready(function($) {
            console.log("Benchmark page loaded");
            // Ensure toggle button is properly initialized
            if (localStorage.getItem("status_sentry_fullwidth_mode") === "true") {
                $("body").addClass("status-sentry-fullwidth-mode");
                $("#status-sentry-toggle-fullwidth").html("<span class=\"dashicons dashicons-editor-contract\" style=\"margin-top: 3px;\"></span> Exit Full Width");
            }
        });
    ');

    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

        <div class="card" style="max-width: none; width: 100%;">
            <h2>Status Sentry Performance Benchmarking</h2>
            <p>This page allows you to run performance benchmarks for the Status Sentry plugin using your real WordPress environment. The benchmarks measure the performance of key components of the plugin and provide insights into how they perform with your actual WordPress data.</p>

            <p><strong>Why benchmark with real data?</strong> Using real WordPress data provides more accurate performance measurements than simulated data. This helps identify potential bottlenecks and optimize the plugin for your specific WordPress installation.</p>
        </div>

        <div class="card" style="margin-top: 20px; max-width: none; width: 100%;">
            <h2>Run Benchmark</h2>
            <form method="post">
                <input type="hidden" name="run_benchmark" value="1">
                <?php wp_nonce_field('status_sentry_benchmark'); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row">Benchmark Type</th>
                        <td>
                            <select name="benchmark_type">
                                <option value="all" <?php selected($benchmark_type, 'all'); ?>>All Components</option>
                                <option value="resource_manager" <?php selected($benchmark_type, 'resource_manager'); ?>>Resource Manager</option>
                                <option value="event_queue" <?php selected($benchmark_type, 'event_queue'); ?>>Event Queue</option>
                                <option value="query_cache" <?php selected($benchmark_type, 'query_cache'); ?>>Query Cache</option>
                                <option value="data_capture" <?php selected($benchmark_type, 'data_capture'); ?>>Data Capture</option>
                                <option value="event_processor" <?php selected($benchmark_type, 'event_processor'); ?>>Event Processor</option>
                            </select>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <button type="submit" class="button button-primary">Run Benchmark</button>
                </p>
            </form>
        </div>

        <?php
        // Debug: Log nonce verification
        if ($run_benchmark) {
            error_log('Status Sentry: Checking nonce for benchmark run');
            if (!isset($_POST['_wpnonce'])) {
                error_log('Status Sentry: Nonce is missing in POST data');
            } else {
                error_log('Status Sentry: Nonce value: ' . $_POST['_wpnonce']);
                $nonce_result = wp_verify_nonce($_POST['_wpnonce'], 'status_sentry_benchmark');
                error_log('Status Sentry: Nonce verification result: ' . ($nonce_result ? 'success' : 'failure'));
            }
        }

        if ($run_benchmark && check_admin_referer('status_sentry_benchmark')):
            error_log('Status Sentry: Nonce verification passed, running benchmark');
            // Include the benchmark runner class
            require_once STATUS_SENTRY_PLUGIN_DIR . 'includes/benchmarking/class-status-sentry-benchmark-runner.php';

            // Create an instance of the benchmark runner
            $benchmark_runner = new Status_Sentry_Benchmark_Runner();

            // Store benchmark results
            $all_results = [];

            // Start output buffering to capture benchmark output
            ob_start();

            echo "Running benchmarks with real WordPress environment...\n\n";

            // Run the selected benchmarks
            if ($benchmark_type === 'all' || $benchmark_type === 'resource_manager') {
                echo "1. Resource Manager Benchmark\n";
                echo "----------------------------\n";
                $results = $benchmark_runner->test_resource_manager_performance(true);
                $all_results['resource_manager'] = $results;
                echo "Memory Usage: " . number_format(max($results['memory_usage'], 0)) . " bytes\n";
                echo "Execution Time: " . number_format($results['execution_time'], 4) . " seconds\n";
                echo "Operations Per Second: " . number_format($results['operations_per_second']) . "\n\n";
            }

            if ($benchmark_type === 'all' || $benchmark_type === 'event_queue') {
                echo "2. Event Queue Benchmark\n";
                echo "----------------------\n";
                $results = $benchmark_runner->test_event_queue_performance(true);
                $all_results['event_queue'] = $results;
                echo "Memory Usage: " . number_format(max($results['memory_usage'], 0)) . " bytes\n";
                echo "Execution Time: " . number_format($results['execution_time'], 4) . " seconds\n";
                echo "Operations Per Second: " . number_format($results['operations_per_second']) . "\n\n";
            }

            if ($benchmark_type === 'all' || $benchmark_type === 'query_cache') {
                echo "3. Query Cache Benchmark\n";
                echo "----------------------\n";
                $results = $benchmark_runner->test_query_cache_performance(true);
                $all_results['query_cache'] = $results;
                echo "Memory Usage: " . number_format(max($results['memory_usage'], 0)) . " bytes\n";
                echo "Execution Time: " . number_format($results['execution_time'], 4) . " seconds\n";
                echo "Operations Per Second: " . number_format($results['operations_per_second']) . "\n\n";
            }

            if ($benchmark_type === 'all' || $benchmark_type === 'data_capture') {
                echo "4. Data Capture Benchmark\n";
                echo "-----------------------\n";
                $results = $benchmark_runner->test_data_capture_performance(true);
                $all_results['data_capture'] = $results;
                echo "Memory Usage: " . number_format(max($results['memory_usage'], 0)) . " bytes\n";
                echo "Execution Time: " . number_format($results['execution_time'], 4) . " seconds\n";
                echo "Operations Per Second: " . number_format($results['operations_per_second']) . "\n\n";
            }

            if ($benchmark_type === 'all' || $benchmark_type === 'event_processor') {
                echo "5. Event Processor Benchmark\n";
                echo "--------------------------\n";
                $results = $benchmark_runner->test_event_processor_performance(true);
                $all_results['event_processor'] = $results;
                echo "Memory Usage: " . number_format(max($results['memory_usage'], 0)) . " bytes\n";
                if (isset($results['peak_memory_usage'])) {
                    echo "Peak Memory Usage: " . number_format(max($results['peak_memory_usage'], 0)) . " bytes\n";
                }
                echo "Execution Time: " . number_format($results['execution_time'], 4) . " seconds\n";
                echo "Operations Per Second: " . number_format($results['operations_per_second']) . "\n\n";
            }

            echo "Benchmarks completed successfully.\n";

            // Get the benchmark output
            $benchmark_output = ob_get_clean();

            // Save the benchmark results in the database
            $current_results = [
                'timestamp' => current_time('mysql'),
                'results' => $all_results
            ];
            update_option('status_sentry_benchmark_results', $current_results);

            // Add to benchmark history
            $benchmark_history = get_option('status_sentry_benchmark_history', []);
            array_unshift($benchmark_history, $current_results);

            // Limit history to 10 entries
            if (count($benchmark_history) > 10) {
                $benchmark_history = array_slice($benchmark_history, 0, 10);
            }

            // Save updated history
            update_option('status_sentry_benchmark_history', $benchmark_history);

            // Display the results
            ?>
            <div class="card status-sentry-benchmark-container" style="margin-top: 20px; max-width: none; width: 100%;">
                <h2>Benchmark Results</h2>
                <p>Benchmark completed at <?php echo current_time('mysql'); ?> - <a href="<?php echo admin_url('admin.php?page=status-sentry-benchmark-history'); ?>">View Benchmark History</a></p>

                <div class="status-sentry-benchmark-grid">
                    <div class="status-sentry-benchmark-actions">
                        <button id="status-sentry-toggle-fullwidth" class="button status-sentry-toggle-fullwidth">
                            <span class="dashicons dashicons-editor-expand" style="margin-top: 3px;"></span> Toggle Full Width
                        </button>
                        <button class="button status-sentry-print-results">
                            <span class="dashicons dashicons-printer" style="margin-top: 3px;"></span> Print Results
                        </button>
                        <button class="button status-sentry-export-csv">
                            <span class="dashicons dashicons-media-spreadsheet" style="margin-top: 3px;"></span> Export as CSV
                        </button>
                    </div>

                    <?php if (count($all_results) > 1): ?>
                    <div class="status-sentry-charts-wrapper">
                        <div class="status-sentry-charts">
                            <div class="status-sentry-chart-container">
                                <h3>Memory Usage (bytes)</h3>
                                <canvas id="memoryChart"></canvas>
                            </div>
                            <div class="status-sentry-chart-container">
                                <h3>Execution Time (seconds)</h3>
                                <canvas id="timeChart"></canvas>
                            </div>
                            <div class="status-sentry-chart-container">
                                <h3>Operations Per Second</h3>
                                <canvas id="opsChart"></canvas>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="status-sentry-detailed-results">
                        <h3>Detailed Results</h3>

                        <table class="widefat status-sentry-table">
                            <thead>
                                <tr>
                                    <th>Component</th>
                                    <th>Memory Usage</th>
                                    <th>Execution Time</th>
                                    <th>Operations Per Second</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($all_results as $component => $results): ?>
                                <tr>
                                    <td><?php echo esc_html(ucwords(str_replace('_', ' ', $component))); ?></td>
                                    <td><?php echo number_format(max($results['memory_usage'], 0)); ?> bytes</td>
                                    <td><?php echo number_format($results['execution_time'], 4); ?> seconds</td>
                                    <td><?php echo number_format($results['operations_per_second']); ?></td>
                                    <td>
                                        <?php if ($results['passed']): ?>
                                        <span class="status-sentry-status status-sentry-status-passed">Passed</span>
                                        <?php else: ?>
                                        <span class="status-sentry-status status-sentry-status-failed">Failed</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>

                        <h4>Raw Output</h4>
                        <div class="status-sentry-raw-output"><?php echo $benchmark_output; ?></div>
                    </div>
                </div>
            </div>

            <?php if (count($all_results) > 1): ?>
            <script>
            // Prepare data for charts
            window.statusSentryBenchmarkData = {
                labels: <?php echo json_encode(array_map(function($key) {
                    return ucwords(str_replace('_', ' ', $key));
                }, array_keys($all_results))); ?>,
                memoryData: <?php echo json_encode(array_map(function($result) {
                    return $result['memory_usage'];
                }, $all_results)); ?>,
                timeData: <?php echo json_encode(array_map(function($result) {
                    return $result['execution_time'];
                }, $all_results)); ?>,
                opsData: <?php echo json_encode(array_map(function($result) {
                    return $result['operations_per_second'];
                }, $all_results)); ?>
            };
            </script>
            <?php endif; ?>

        <?php endif; ?>
    </div>
    <?php
}

/**
 * Display the benchmark history page.
 */
function status_sentry_benchmark_history_page() {
    // Debug: Log when benchmark history page function is called
    error_log('Status Sentry: status_sentry_benchmark_history_page function called');
    error_log('Status Sentry: $_GET = ' . print_r($_GET, true));

    // Check user capabilities
    if (!current_user_can('manage_options')) {
        error_log('Status Sentry: User does not have manage_options capability');
        return;
    }

    // Handle benchmark deletion
    if (isset($_POST['delete_benchmark']) && isset($_POST['timestamp']) && isset($_POST['component']) && isset($_POST['_wpnonce'])) {
        if (wp_verify_nonce($_POST['_wpnonce'], 'status_sentry_delete_benchmark')) {
            $timestamp = sanitize_text_field($_POST['timestamp']);
            $component = sanitize_text_field($_POST['component']);

            // Get benchmark history
            $benchmark_history = get_option('status_sentry_benchmark_history', []);

            // Find the entry to delete
            foreach ($benchmark_history as $key => $entry) {
                if ($entry['timestamp'] === $timestamp) {
                    if (isset($entry['results'][$component])) {
                        // Remove just the component
                        unset($benchmark_history[$key]['results'][$component]);

                        // If no more results, remove the entire entry
                        if (empty($benchmark_history[$key]['results'])) {
                            unset($benchmark_history[$key]);
                        }

                        // Save updated history
                        update_option('status_sentry_benchmark_history', array_values($benchmark_history));

                        // Add admin notice
                        add_action('admin_notices', function() {
                            echo '<div class="notice notice-success is-dismissible"><p>Benchmark entry deleted successfully.</p></div>';
                        });

                        break;
                    }
                }
            }
        }
    }

    // Handle clearing all benchmarks
    if (isset($_POST['clear_all_benchmarks']) && isset($_POST['_wpnonce'])) {
        if (wp_verify_nonce($_POST['_wpnonce'], 'status_sentry_clear_all_benchmarks')) {
            global $wpdb;

            // Delete all benchmark history
            delete_option('status_sentry_benchmark_history');

            // Delete current benchmark results
            delete_option('status_sentry_benchmark_results');

            // Delete any other benchmark-related options
            $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '%status_sentry_benchmark%'");

            // Reset any transients related to benchmarks
            $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '%_transient_%status_sentry_benchmark%'");

            // Add admin notice
            add_action('admin_notices', function() {
                echo '<div class="notice notice-success is-dismissible"><p>All benchmark history has been cleared.</p></div>';
            });
        }
    }

    // Get benchmark history
    $benchmark_history = get_option('status_sentry_benchmark_history', []);
    $current_results = get_option('status_sentry_benchmark_results', []);

    // Check if there's any real benchmark data
    $has_benchmark_data = false;
    foreach ($benchmark_history as $entry) {
        if (!empty($entry['results'])) {
            $has_benchmark_data = true;
            break;
        }
    }

    // Add current results to history if they exist and aren't already in history
    if (!empty($current_results) && !empty($current_results['timestamp'])) {
        $found = false;
        foreach ($benchmark_history as $entry) {
            if ($entry['timestamp'] === $current_results['timestamp']) {
                $found = true;
                break;
            }
        }

        if (!$found) {
            // Add to the beginning of the array
            array_unshift($benchmark_history, $current_results);

            // Limit history to 10 entries
            if (count($benchmark_history) > 10) {
                $benchmark_history = array_slice($benchmark_history, 0, 10);
            }

            // Save updated history
            update_option('status_sentry_benchmark_history', $benchmark_history);
        }
    }

    // Enqueue Chart.js
    wp_enqueue_script('chartjs', 'https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.js', [], '3.7.1', true);

    // Enqueue benchmark CSS and JS
    wp_enqueue_style('status-sentry-benchmark-css', plugin_dir_url(STATUS_SENTRY_PLUGIN_DIR . 'status-sentry.php') . 'assets/css/benchmark.css', [], '1.6.0');
    wp_enqueue_script('status-sentry-benchmark-js', plugin_dir_url(STATUS_SENTRY_PLUGIN_DIR . 'status-sentry.php') . 'assets/js/benchmark.js', ['jquery', 'chartjs'], '1.6.0', true);

    // Add inline script to ensure toggle button works
    wp_add_inline_script('status-sentry-benchmark-js', '
        jQuery(document).ready(function($) {
            console.log("Benchmark history page loaded");
            // Ensure toggle button is properly initialized
            if (localStorage.getItem("status_sentry_fullwidth_mode") === "true") {
                $("body").addClass("status-sentry-fullwidth-mode");
                $("#status-sentry-toggle-fullwidth").html("<span class=\"dashicons dashicons-editor-contract\" style=\"margin-top: 3px;\"></span> Exit Full Width");
            }
        });
    ');

    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

        <div class="card" style="max-width: none; width: 100%;">
            <h2>Benchmark History</h2>
            <p>This page shows the history of benchmark results for the Status Sentry plugin. You can compare performance over time to identify trends and improvements.</p>
        </div>

        <?php if (empty($benchmark_history)): ?>
            <div class="card" style="margin-top: 20px; max-width: none; width: 100%;">
                <p>No benchmark history available. Run a benchmark first.</p>
                <p><a href="<?php echo admin_url('admin.php?page=status-sentry-benchmark'); ?>" class="button button-primary">Run Benchmark</a></p>
            </div>
        <?php else: ?>
            <div class="card status-sentry-benchmark-container" style="margin-top: 20px; max-width: none; width: 100%;">
                <h2>Performance Trends</h2>

                <div class="status-sentry-benchmark-grid">
                    <div class="status-sentry-benchmark-actions">
                        <button id="status-sentry-toggle-fullwidth" class="button status-sentry-toggle-fullwidth">
                            <span class="dashicons dashicons-editor-expand" style="margin-top: 3px;"></span> Toggle Full Width
                        </button>
                        <button class="button status-sentry-print-results">
                            <span class="dashicons dashicons-printer" style="margin-top: 3px;"></span> Print Results
                        </button>
                        <button class="button status-sentry-export-csv">
                            <span class="dashicons dashicons-media-spreadsheet" style="margin-top: 3px;"></span> Export as CSV
                        </button>
                    </div>

                    <?php if ($has_benchmark_data): ?>
                    <div class="status-sentry-history-chart-container" style="width: 100%; max-width: 100%;">
                        <h3>Operations Per Second Over Time</h3>
                        <canvas id="historyChart" style="width: 100%; min-width: 100%;"></canvas>
                    </div>
                    <?php endif; ?>

                    <div class="status-sentry-detailed-results">
                        <h3>Benchmark History</h3>

                        <div class="status-sentry-search-container" style="margin-bottom: 15px;">
                            <div class="status-sentry-search-form">
                                <input type="text" id="status-sentry-search-input" placeholder="Search benchmarks..." class="regular-text">
                                <select id="status-sentry-search-field">
                                    <option value="all">All Fields</option>
                                    <option value="date">Date</option>
                                    <option value="component">Component</option>
                                    <option value="status">Status</option>
                                </select>
                                <button id="status-sentry-search-button" class="button">Search</button>
                                <button id="status-sentry-reset-search" class="button">Reset</button>
                                <button id="status-sentry-clear-all" class="button button-link-delete" style="margin-left: auto;">
                                    <span class="dashicons dashicons-trash" style="margin-top: 3px;"></span> Clear All Benchmarks
                                </button>
                            </div>
                        </div>

                        <?php if ($has_benchmark_data): ?>
                        <table class="widefat status-sentry-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Component</th>
                                    <th>Memory Usage</th>
                                    <th>Execution Time</th>
                                    <th>Operations Per Second</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($benchmark_history as $entry): ?>
                                    <?php if (!empty($entry['results'])): ?>
                                        <?php foreach ($entry['results'] as $component => $results): ?>
                                            <tr>
                                                <td><?php echo esc_html($entry['timestamp']); ?></td>
                                                <td><?php echo esc_html(ucwords(str_replace('_', ' ', $component))); ?></td>
                                                <td><?php echo number_format(max($results['memory_usage'], 0)); ?> bytes</td>
                                                <td><?php echo number_format($results['execution_time'], 4); ?> seconds</td>
                                                <td><?php echo number_format($results['operations_per_second']); ?></td>
                                                <td>
                                                    <?php if ($results['passed']): ?>
                                                    <span class="status-sentry-status status-sentry-status-passed">Passed</span>
                                                    <?php else: ?>
                                                    <span class="status-sentry-status status-sentry-status-failed">Failed</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <button class="button button-small status-sentry-delete-benchmark"
                                                            data-timestamp="<?php echo esc_attr($entry['timestamp']); ?>"
                                                            data-component="<?php echo esc_attr($component); ?>">
                                                        <span class="dashicons dashicons-trash" style="margin-top: 3px;"></span>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php else: ?>
                        <div class="status-sentry-no-data" style="background-color: #f8f9fa; padding: 30px; border-radius: 4px; text-align: center; margin: 20px 0;">
                            <p style="margin-bottom: 15px; font-size: 16px;">No benchmark data available. Run a benchmark to see results here.</p>
                            <p><a href="<?php echo admin_url('admin.php?page=status-sentry-benchmark'); ?>" class="button button-primary">Run Benchmark</a></p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <?php if ($has_benchmark_data): ?>
            <script>
            // Prepare data for history chart
            window.statusSentryBenchmarkHistoryData = {
                labels: [],
                datasets: []
            };

            // Define components and their colors
            const components = {
                'resource_manager': {
                    label: 'Resource Manager',
                    color: 'rgba(54, 162, 235, 0.5)',
                    borderColor: 'rgba(54, 162, 235, 1)'
                },
                'event_queue': {
                    label: 'Event Queue',
                    color: 'rgba(255, 99, 132, 0.5)',
                    borderColor: 'rgba(255, 99, 132, 1)'
                },
                'query_cache': {
                    label: 'Query Cache',
                    color: 'rgba(75, 192, 192, 0.5)',
                    borderColor: 'rgba(75, 192, 192, 1)'
                },
                'data_capture': {
                    label: 'Data Capture',
                    color: 'rgba(255, 206, 86, 0.5)',
                    borderColor: 'rgba(255, 206, 86, 1)'
                },
                'event_processor': {
                    label: 'Event Processor',
                    color: 'rgba(153, 102, 255, 0.5)',
                    borderColor: 'rgba(153, 102, 255, 1)'
                }
            };

            // Initialize datasets
            Object.keys(components).forEach(component => {
                window.statusSentryBenchmarkHistoryData.datasets.push({
                    label: components[component].label,
                    data: [],
                    backgroundColor: components[component].color,
                    borderColor: components[component].borderColor,
                    borderWidth: 1,
                    fill: false
                });
            });

            // Process benchmark history data
            const history = <?php echo json_encode(array_reverse($benchmark_history)); ?>;

            // Extract timestamps for labels
            history.forEach(entry => {
                if (entry && entry.timestamp) {
                    window.statusSentryBenchmarkHistoryData.labels.push(entry.timestamp);
                }
            });

            // Extract data for each component
            history.forEach((entry, index) => {
                if (entry && entry.results) {
                    Object.keys(components).forEach((component, componentIndex) => {
                        if (entry.results[component]) {
                            window.statusSentryBenchmarkHistoryData.datasets[componentIndex].data[index] = entry.results[component].operations_per_second;
                        } else {
                            window.statusSentryBenchmarkHistoryData.datasets[componentIndex].data[index] = null;
                        }
                    });
                }
            });

            // Initialize the benchmark UI
            jQuery(document).ready(function($) {
                // Initialize the benchmark UI
                if (window.statusSentryBenchmark) {
                    console.log('Initializing benchmark UI');
                    window.statusSentryBenchmark.init();
                } else {
                    console.error('statusSentryBenchmark not found');
                }
            });
            </script>
            <?php endif; ?>

            <!-- Add JavaScript for delete functionality and search -->
            <script>
            jQuery(document).ready(function($) {
                // Handle delete button click
                $('.status-sentry-delete-benchmark').on('click', function(e) {
                    e.preventDefault();

                    const timestamp = $(this).data('timestamp');
                    const component = $(this).data('component');

                    if (confirm('Are you sure you want to delete this benchmark entry? This action cannot be undone.')) {
                        // Create a form and submit it
                        const form = $('<form>', {
                            'method': 'post',
                            'action': window.location.href
                        });

                        form.append($('<input>', {
                            'type': 'hidden',
                            'name': 'delete_benchmark',
                            'value': '1'
                        }));

                        form.append($('<input>', {
                            'type': 'hidden',
                            'name': 'timestamp',
                            'value': timestamp
                        }));

                        form.append($('<input>', {
                            'type': 'hidden',
                            'name': 'component',
                            'value': component
                        }));

                        form.append($('<input>', {
                            'type': 'hidden',
                            'name': '_wpnonce',
                            'value': '<?php echo wp_create_nonce('status_sentry_delete_benchmark'); ?>'
                        }));

                        $('body').append(form);
                        form.submit();
                    }
                });

                // Handle search functionality
                $('#status-sentry-search-button').on('click', function(e) {
                    e.preventDefault();
                    performSearch();
                });

                // Handle reset search
                $('#status-sentry-reset-search').on('click', function(e) {
                    e.preventDefault();
                    $('#status-sentry-search-input').val('');
                    $('#status-sentry-search-field').val('all');
                    $('.status-sentry-table tbody tr').show();
                });

                // Handle clear all benchmarks
                $('#status-sentry-clear-all').on('click', function(e) {
                    e.preventDefault();

                    if (confirm('Are you sure you want to clear all benchmark history? This action cannot be undone.')) {
                        // Create a form and submit it
                        const form = $('<form>', {
                            'method': 'post',
                            'action': window.location.href
                        });

                        form.append($('<input>', {
                            'type': 'hidden',
                            'name': 'clear_all_benchmarks',
                            'value': '1'
                        }));

                        form.append($('<input>', {
                            'type': 'hidden',
                            'name': '_wpnonce',
                            'value': '<?php echo wp_create_nonce('status_sentry_clear_all_benchmarks'); ?>'
                        }));

                        $('body').append(form);
                        form.submit();
                    }
                });

                // Handle enter key in search input
                $('#status-sentry-search-input').on('keypress', function(e) {
                    if (e.which === 13) {
                        e.preventDefault();
                        performSearch();
                    }
                });

                // Search function
                function performSearch() {
                    const searchTerm = $('#status-sentry-search-input').val().toLowerCase();
                    const searchField = $('#status-sentry-search-field').val();

                    if (searchTerm === '') {
                        $('.status-sentry-table tbody tr').show();
                        return;
                    }

                    $('.status-sentry-table tbody tr').each(function() {
                        let found = false;

                        if (searchField === 'all' || searchField === 'date') {
                            if ($(this).find('td:eq(0)').text().toLowerCase().indexOf(searchTerm) > -1) {
                                found = true;
                            }
                        }

                        if ((searchField === 'all' || searchField === 'component') && !found) {
                            if ($(this).find('td:eq(1)').text().toLowerCase().indexOf(searchTerm) > -1) {
                                found = true;
                            }
                        }

                        if ((searchField === 'all' || searchField === 'status') && !found) {
                            if ($(this).find('td:eq(5)').text().toLowerCase().indexOf(searchTerm) > -1) {
                                found = true;
                            }
                        }

                        if (found) {
                            $(this).show();
                        } else {
                            $(this).hide();
                        }
                    });
                }
            });
            </script>
        <?php endif; ?>
    </div>
    <?php
}
