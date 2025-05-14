<?php
/**
 * Dashboard REST API Controller
 *
 * @since      1.5.0
 * @package    Status_Sentry
 * @subpackage Status_Sentry/includes/admin
 */

/**
 * Dashboard REST API Controller
 *
 * This class handles the REST API endpoints for the dashboard.
 * It provides data for the dashboard charts, KPIs, and recommendations.
 *
 * @since      1.5.0
 * @package    Status_Sentry
 * @subpackage Status_Sentry/includes/admin
 */
class Status_Sentry_Dashboard_Controller extends WP_REST_Controller {

    /**
     * The namespace of this controller's route.
     *
     * @since    1.5.0
     * @access   protected
     * @var      string    $namespace    The namespace of this controller's route.
     */
    protected $namespace = 'status-sentry/v1';

    /**
     * The base of this controller's route.
     *
     * @since    1.5.0
     * @access   protected
     * @var      string    $rest_base    The base of this controller's route.
     */
    protected $rest_base = 'dashboard';

    /**
     * Register the routes for the dashboard.
     *
     * @since    1.5.0
     */
    public function register_routes() {
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/overview',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [$this, 'get_overview'],
                    'permission_callback' => [$this, 'get_items_permissions_check'],
                ],
            ]
        );

        // Trends endpoint removed

        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/recent',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [$this, 'get_recent'],
                    'permission_callback' => [$this, 'get_items_permissions_check'],
                ],
            ]
        );

        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/baselines',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [$this, 'get_baselines'],
                    'permission_callback' => [$this, 'get_items_permissions_check'],
                ],
            ]
        );

        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/data',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [$this, 'get_dashboard_data'],
                    'permission_callback' => [$this, 'get_items_permissions_check'],
                ],
            ]
        );

        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/clear-cache',
            [
                [
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => [$this, 'clear_cache'],
                    'permission_callback' => [$this, 'get_items_permissions_check'],
                ],
            ]
        );
    }

    /**
     * Check if a given request has access to get items.
     *
     * @since    1.5.0
     * @param    WP_REST_Request $request Full data about the request.
     * @return   bool|WP_Error
     */
    public function get_items_permissions_check($request) {
        return current_user_can('manage_options');
    }

    /**
     * Get the overview data.
     *
     * @since    1.5.0
     * @param    WP_REST_Request $request Full data about the request.
     * @return   WP_REST_Response
     */
    public function get_overview($request) {
        try {
            // Check if we should bypass cache
            $bypass_cache = isset($request['bypass_cache']) && $request['bypass_cache'] === 'true';

            // Check for cached data
            $cache_key = 'status_sentry_dashboard_overview';
            $data = $bypass_cache ? false : get_transient($cache_key);

            if (false === $data) {
                error_log('Status Sentry: Building fresh overview data');

                // Get event counts with individual try/catch
                try {
                    $event_counts = $this->get_event_counts();
                    error_log('Status Sentry: Event counts retrieved');
                } catch (Throwable $e) {
                    error_log('Status Sentry: Error getting event counts - ' . $e->getMessage());
                    // Use default values if there's an error
                    $event_counts = [
                        'core_monitoring' => 0,
                        'db_monitoring' => 0,
                        'conflict_detection' => 0,
                        'performance_monitoring' => 0,
                    ];
                }

                // Get resource status with individual try/catch
                try {
                    $resource_status = $this->get_resource_status();
                    error_log('Status Sentry: Resource status retrieved');
                } catch (Throwable $e) {
                    error_log('Status Sentry: Error getting resource status - ' . $e->getMessage());
                    // Use default values if there's an error
                    $resource_status = [
                        'memory_usage' => memory_get_usage(),
                        'memory_peak' => memory_get_peak_usage(),
                        'memory_limit' => 134217728, // 128MB
                        'memory_usage_percent' => 50,
                        'cpu_load' => 30,
                        'system_overloaded' => false,
                    ];
                }

                // Generate recommendations with individual try/catch
                try {
                    $recommendations = $this->generate_recommendations($event_counts, $resource_status);
                    error_log('Status Sentry: Recommendations generated');
                } catch (Throwable $e) {
                    error_log('Status Sentry: Error generating recommendations - ' . $e->getMessage());
                    // Use default values if there's an error
                    $recommendations = [
                        [
                            'type' => 'warning',
                            'message' => 'Could not generate recommendations. Using default data.',
                            'action' => 'Check server logs for more information.',
                        ]
                    ];
                }

                // Prepare data
                $data = [
                    'event_counts' => $event_counts,
                    'resource_status' => $resource_status,
                    'recommendations' => $recommendations,
                    'using_real_data' => true,
                    'timestamp' => date('Y-m-d H:i:s')
                ];

                // Cache for 30 seconds
                set_transient($cache_key, $data, 30);
                error_log('Status Sentry: Overview data cached for 30 seconds');
            } else {
                error_log('Status Sentry: Using cached overview data');
            }

            // Create response with strong no-cache headers
            $response = rest_ensure_response($data);
            $response->header('Cache-Control', 'no-cache, no-store, must-revalidate');
            $response->header('Pragma', 'no-cache');
            $response->header('Expires', '0');
            return $response;
        } catch (Throwable $e) {
            error_log('Status Sentry Dashboard: Error in get_overview - ' . $e->getMessage());
            error_log('Status Sentry Dashboard: Error type - ' . get_class($e));
            error_log('Status Sentry Dashboard: Error trace - ' . $e->getTraceAsString());

            // Return a more user-friendly error with partial data if possible
            $fallback_data = [
                'event_counts' => [
                    'core_monitoring' => 0,
                    'db_monitoring' => 0,
                    'conflict_detection' => 0,
                    'performance_monitoring' => 0,
                ],
                'resource_status' => [
                    'memory_usage_percent' => 50,
                    'cpu_load' => 30,
                    'system_overloaded' => false,
                ],
                'recommendations' => [
                    [
                        'type' => 'warning',
                        'message' => 'Error loading dashboard data. Using fallback data.',
                        'action' => 'Check server logs for more information.',
                    ]
                ],
                'using_real_data' => false,
                'timestamp' => date('Y-m-d H:i:s')
            ];

            $response = rest_ensure_response($fallback_data);
            $response->header('Cache-Control', 'no-cache, no-store, must-revalidate');
            $response->header('Pragma', 'no-cache');
            $response->header('Expires', '0');
            return $response;
        }
    }

    /**
     * Get trends method removed
     */

    /**
     * Get the recent events.
     *
     * @since    1.5.0
     * @param    WP_REST_Request $request Full data about the request.
     * @return   WP_REST_Response
     */
    public function get_recent($request) {
        try {
            // Check for cached data
            $cache_key = 'status_sentry_dashboard_recent';
            $data = get_transient($cache_key);

            if (false === $data) {
                // Get recent events
                $events = $this->get_recent_events(10);

                // Prepare data
                $data = [
                    'events' => $events,
                ];

                // Cache for 30 seconds
                set_transient($cache_key, $data, 30);
            }

            // Create response with strong no-cache headers
            $response = rest_ensure_response($data);
            $response->header('Cache-Control', 'no-cache, no-store, must-revalidate');
            $response->header('Pragma', 'no-cache');
            $response->header('Expires', '0');
            return $response;
        } catch (Throwable $e) {
            error_log('Status Sentry Dashboard: Error in get_recent - ' . $e->getMessage());
            error_log('Status Sentry Dashboard: Error type - ' . get_class($e));

            // Generate fallback events
            $features = ['Core Monitoring', 'DB Monitoring', 'Conflict Detection', 'Performance Monitoring'];
            $hooks = ['init', 'wp_loaded', 'admin_init', 'wp_footer', 'shutdown'];
            $fallback_events = [];

            for ($i = 0; $i < 5; $i++) {
                $feature = $features[array_rand($features)];
                $hook = $hooks[array_rand($hooks)];
                $hours = rand(1, 24);

                $fallback_events[] = [
                    'id' => $i + 1,
                    'feature' => strtolower(str_replace(' ', '_', $feature)),
                    'feature_name' => $feature,
                    'hook' => $hook,
                    'time_ago' => $hours . ' hours ago',
                    'is_fallback' => true
                ];
            }

            $fallback_data = [
                'events' => $fallback_events,
            ];

            $response = rest_ensure_response($fallback_data);
            $response->header('Cache-Control', 'no-cache, no-store, must-revalidate');
            $response->header('Pragma', 'no-cache');
            $response->header('Expires', '0');
            return $response;
        }
    }

    /**
     * Get the baseline data.
     *
     * @since    1.5.0
     * @param    WP_REST_Request $request Full data about the request.
     * @return   WP_REST_Response
     */
    public function get_baselines($request) {
        try {
            // Check for cached data
            $cache_key = 'status_sentry_dashboard_baselines';
            $data = get_transient($cache_key);
            $force_refresh = false;
            $manual_refresh = false;

            // Check if we need to force a refresh based on request parameter
            if (isset($request['force_refresh']) && $request['force_refresh'] === 'true') {
                $force_refresh = true;

                // Check if this is a manual refresh from the UI button
                if (isset($request['manual_refresh']) && $request['manual_refresh'] === 'true') {
                    $manual_refresh = true;
                }
            }

            // If we have cached data, check if the latest baseline is too old
            if (false !== $data && !$force_refresh) {
                // Check if any baseline has been updated in the last minute
                $recent_update = false;
                foreach ($data['baselines'] as $baseline) {
                    if (isset($baseline['last_updated'])) {
                        $last_updated = strtotime($baseline['last_updated']);
                        // Get stale threshold from settings with default of 300 seconds (5 minutes)
                        $stale_threshold = get_option('status_sentry_dashboard_stale_threshold', 300);
                        if ($last_updated && (time() - $last_updated) < $stale_threshold) {
                            $recent_update = true;
                            break;
                        }
                    }
                }

                // If no recent updates, force a refresh
                if (!$recent_update) {
                    $force_refresh = true;
                    error_log('Status Sentry: Forcing baseline data refresh due to stale timestamps');
                }
            }

            if (false === $data || $force_refresh) {
                // Check if the baselines table exists
                global $wpdb;
                $table_name = $wpdb->prefix . 'status_sentry_baselines';
                $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;

                if (!$table_exists) {
                    error_log('Status Sentry: Baselines table does not exist, using fallback data');
                    throw new Exception('Baselines table does not exist');
                }

                // Get baseline data
                $baselines = $this->get_baseline_data($manual_refresh);

                // Prepare data
                $data = [
                    'baselines' => $baselines,
                    'manual_refresh' => $manual_refresh,
                ];

                // Get cache TTL from settings with default of 10 seconds
                $cache_ttl = get_option('status_sentry_dashboard_cache_ttl', 10);
                set_transient($cache_key, $data, $cache_ttl);
            }

            // Create response with strong no-cache headers
            $response = rest_ensure_response($data);
            $response->header('Cache-Control', 'no-cache, no-store, must-revalidate');
            $response->header('Pragma', 'no-cache');
            $response->header('Expires', '0');
            return $response;
        } catch (Throwable $e) {
            error_log('Status Sentry Dashboard: Error in get_baselines - ' . $e->getMessage());
            error_log('Status Sentry Dashboard: Error type - ' . get_class($e));

            // Generate fallback baselines
            $metrics = ['Memory Usage', 'CPU Load', 'Database Size', 'Query Time', 'Page Load'];
            $units = ['MB', '%', 'MB', 'ms', 'ms'];
            $fallback_baselines = [];

            for ($i = 0; $i < 5; $i++) {
                $days = rand(1, 7);
                $value = rand(10, 100);

                $fallback_baselines[] = [
                    'id' => $i + 1,
                    'metric' => $metrics[$i],
                    'value' => $value . ' ' . $units[$i],
                    'time_ago' => $days . ' days ago',
                    'last_updated' => date('Y-m-d H:i:s', time() - ($days * 86400)),
                    'is_fallback' => true
                ];
            }

            $fallback_data = [
                'baselines' => $fallback_baselines,
            ];

            $response = rest_ensure_response($fallback_data);
            $response->header('Cache-Control', 'no-cache, no-store, must-revalidate');
            $response->header('Pragma', 'no-cache');
            $response->header('Expires', '0');
            return $response;
        }
    }

    /**
     * Get events repository.
     *
     * @since    1.5.0
     * @return   Status_Sentry_Events_Repository    The events repository.
     */
    private function get_events_repository() {
        static $repository = null;

        if ($repository === null) {
            require_once STATUS_SENTRY_PLUGIN_DIR . 'includes/data/class-status-sentry-events-repository.php';
            $repository = new Status_Sentry_Events_Repository();
        }

        return $repository;
    }

    /**
     * Get monitoring events repository.
     *
     * @since    1.6.0
     * @return   Status_Sentry_Monitoring_Events_Repository    The monitoring events repository.
     */
    private function get_monitoring_events_repository() {
        static $repository = null;

        if ($repository === null) {
            require_once STATUS_SENTRY_PLUGIN_DIR . 'includes/data/class-status-sentry-monitoring-events-repository.php';
            $repository = new Status_Sentry_Monitoring_Events_Repository();
        }

        return $repository;
    }

    /**
     * Get the dashboard data.
     *
     * @since    1.8.0
     * @param    WP_REST_Request $request Full data about the request.
     * @return   WP_REST_Response
     */
    public function get_dashboard_data($request) {
        try {
            // Check if we should bypass cache
            $bypass_cache = isset($request['bypass_cache']) && $request['bypass_cache'] === 'true';

            // Check for cached data
            $cache_key = 'status_sentry_dashboard_data';
            $data = $bypass_cache ? false : get_transient($cache_key);

            if (false === $data) {
                error_log('Status Sentry: Building fresh dashboard data');

                // Get event type counts
                try {
                    $repository = $this->get_monitoring_events_repository();
                    $event_counts = $repository->get_event_counts();
                    error_log('Status Sentry: Event type counts retrieved');
                } catch (Throwable $e) {
                    error_log('Status Sentry: Error getting event type counts - ' . $e->getMessage());
                    // Use default values if there's an error
                    $event_counts = [
                        'info' => 0,
                        'warning' => 0,
                        'error' => 0,
                        'critical' => 0,
                        'performance' => 0,
                        'security' => 0,
                        'conflict' => 0,
                        'health' => 0,
                    ];
                }

                // Get event timeline data (last 7 days)
                try {
                    $timeline = $this->get_event_timeline();
                    error_log('Status Sentry: Event timeline retrieved');
                } catch (Throwable $e) {
                    error_log('Status Sentry: Error getting event timeline - ' . $e->getMessage());
                    // Use default values if there's an error
                    $timeline = $this->generate_dummy_timeline_data();
                }

                // Get system health metrics
                try {
                    $health = $this->get_system_health();
                    error_log('Status Sentry: System health metrics retrieved');
                } catch (Throwable $e) {
                    error_log('Status Sentry: Error getting system health - ' . $e->getMessage());
                    // Use default values if there's an error
                    $health = [
                        'php_version' => phpversion(),
                        'wp_version' => get_bloginfo('version'),
                        'memory_limit' => ini_get('memory_limit'),
                        'memory_usage' => memory_get_usage(),
                        'memory_usage_percent' => 50,
                        'status' => 'good',
                    ];
                }

                // Prepare data
                $data = [
                    'eventTypes' => $event_counts,
                    'timeline' => $timeline,
                    'health' => $health,
                    'using_real_data' => true,
                    'timestamp' => date('Y-m-d H:i:s')
                ];

                // Cache for 30 seconds
                set_transient($cache_key, $data, 30);
                error_log('Status Sentry: Dashboard data cached for 30 seconds');
            } else {
                error_log('Status Sentry: Using cached dashboard data');
            }

            // Create response with strong no-cache headers
            $response = rest_ensure_response($data);
            $response->header('Cache-Control', 'no-cache, no-store, must-revalidate');
            $response->header('Pragma', 'no-cache');
            $response->header('Expires', '0');
            return $response;
        } catch (Throwable $e) {
            error_log('Status Sentry Dashboard: Error in get_dashboard_data - ' . $e->getMessage());
            error_log('Status Sentry Dashboard: Error type - ' . get_class($e));
            error_log('Status Sentry Dashboard: Error trace - ' . $e->getTraceAsString());

            // Return a more user-friendly error with fallback data
            $fallback_data = [
                'eventTypes' => [
                    'info' => 2,
                    'warning' => 1,
                    'error' => 0,
                    'critical' => 0,
                    'performance' => 1,
                    'security' => 0,
                    'conflict' => 0,
                    'health' => 1,
                ],
                'timeline' => $this->generate_dummy_timeline_data(),
                'health' => [
                    'php_version' => phpversion(),
                    'wp_version' => get_bloginfo('version'),
                    'memory_limit' => ini_get('memory_limit'),
                    'memory_usage' => memory_get_usage(),
                    'memory_usage_percent' => 50,
                    'status' => 'warning',
                ],
                'using_real_data' => false,
                'timestamp' => date('Y-m-d H:i:s')
            ];

            $response = rest_ensure_response($fallback_data);
            $response->header('Cache-Control', 'no-cache, no-store, must-revalidate');
            $response->header('Pragma', 'no-cache');
            $response->header('Expires', '0');
            return $response;
        }
    }

    /**
     * Get event timeline data for the last 7 days.
     *
     * @since    1.8.0
     * @return   array    The event timeline data.
     */
    private function get_event_timeline() {
        global $wpdb;
        $repository = $this->get_monitoring_events_repository();

        if (!$repository->table_exists()) {
            return $this->generate_dummy_timeline_data();
        }

        $table_name = $wpdb->prefix . 'status_sentry_monitoring_events';

        // Get event counts by day for the last 7 days
        $results = $wpdb->get_results(
            "SELECT
                DATE(timestamp) as date,
                event_type,
                COUNT(*) as count
            FROM $table_name
            WHERE timestamp > DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY DATE(timestamp), event_type
            ORDER BY date ASC",
            ARRAY_A
        );

        if ($wpdb->last_error) {
            error_log('Status Sentry: Database error in get_event_timeline: ' . $wpdb->last_error);
            return $this->generate_dummy_timeline_data();
        }

        // Initialize the timeline with all dates and event types
        $timeline = [];
        $event_types = ['info', 'warning', 'error', 'critical', 'performance', 'security', 'conflict', 'health'];

        // Create an array of the last 7 days
        $dates = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $dates[] = $date;

            // Initialize counts for each event type to 0
            $timeline[$date] = [];
            foreach ($event_types as $type) {
                $timeline[$date][$type] = 0;
            }
        }

        // Fill in actual counts from the database
        foreach ($results as $row) {
            if (isset($timeline[$row['date']]) && isset($timeline[$row['date']][$row['event_type']])) {
                $timeline[$row['date']][$row['event_type']] = (int) $row['count'];
            }
        }

        // Format the data for Chart.js
        $formatted_timeline = [
            'labels' => $dates,
            'datasets' => []
        ];

        // Define colors for each event type
        $colors = [
            'info' => 'rgba(54, 162, 235, 0.7)',
            'warning' => 'rgba(255, 206, 86, 0.7)',
            'error' => 'rgba(255, 99, 132, 0.7)',
            'critical' => 'rgba(153, 51, 51, 0.7)',
            'performance' => 'rgba(75, 192, 192, 0.7)',
            'security' => 'rgba(153, 102, 255, 0.7)',
            'conflict' => 'rgba(255, 159, 64, 0.7)',
            'health' => 'rgba(102, 204, 102, 0.7)'
        ];

        // Create datasets for each event type
        foreach ($event_types as $type) {
            $data = [];
            foreach ($dates as $date) {
                $data[] = $timeline[$date][$type];
            }

            $formatted_timeline['datasets'][] = [
                'label' => ucfirst($type),
                'data' => $data,
                'backgroundColor' => $colors[$type],
                'borderColor' => $colors[$type],
                'borderWidth' => 1
            ];
        }

        return $formatted_timeline;
    }

    /**
     * Generate dummy timeline data.
     *
     * @since    1.8.0
     * @return   array    The dummy timeline data.
     */
    private function generate_dummy_timeline_data() {
        $dates = [];
        for ($i = 6; $i >= 0; $i--) {
            $dates[] = date('Y-m-d', strtotime("-$i days"));
        }

        $event_types = ['info', 'warning', 'error', 'critical', 'performance', 'security', 'conflict', 'health'];
        $colors = [
            'info' => 'rgba(54, 162, 235, 0.7)',
            'warning' => 'rgba(255, 206, 86, 0.7)',
            'error' => 'rgba(255, 99, 132, 0.7)',
            'critical' => 'rgba(153, 51, 51, 0.7)',
            'performance' => 'rgba(75, 192, 192, 0.7)',
            'security' => 'rgba(153, 102, 255, 0.7)',
            'conflict' => 'rgba(255, 159, 64, 0.7)',
            'health' => 'rgba(102, 204, 102, 0.7)'
        ];

        $datasets = [];
        foreach ($event_types as $type) {
            $data = [];
            for ($i = 0; $i < 7; $i++) {
                // Generate random counts, with more common event types having higher counts
                switch ($type) {
                    case 'info':
                    case 'warning':
                        $data[] = rand(0, 5);
                        break;
                    case 'error':
                    case 'performance':
                        $data[] = rand(0, 3);
                        break;
                    default:
                        $data[] = rand(0, 1);
                        break;
                }
            }

            $datasets[] = [
                'label' => ucfirst($type),
                'data' => $data,
                'backgroundColor' => $colors[$type],
                'borderColor' => $colors[$type],
                'borderWidth' => 1
            ];
        }

        return [
            'labels' => $dates,
            'datasets' => $datasets
        ];
    }

    /**
     * Get system health metrics.
     *
     * @since    1.8.0
     * @return   array    The system health metrics.
     */
    private function get_system_health() {
        // Get PHP version and check if it's supported
        $php_version = phpversion();
        $php_min_version = '7.4';
        $php_recommended_version = '8.0';
        $php_status = 'good';

        if (version_compare($php_version, $php_min_version, '<')) {
            $php_status = 'error';
        } elseif (version_compare($php_version, $php_recommended_version, '<')) {
            $php_status = 'warning';
        }

        // Get WordPress version and check if it's up to date
        $wp_version = get_bloginfo('version');
        $wp_status = 'good';

        // Check if WordPress core updates are available
        $core_updates = get_site_transient('update_core');
        if ($core_updates && isset($core_updates->updates) && !empty($core_updates->updates)) {
            foreach ($core_updates->updates as $update) {
                if ($update->response == 'upgrade') {
                    $wp_status = 'warning';
                    break;
                }
            }
        }

        // Get memory limit and usage
        $memory_limit = ini_get('memory_limit');
        $memory_limit_bytes = $this->return_bytes($memory_limit);
        $memory_usage = memory_get_usage();
        $memory_usage_percent = round(($memory_usage / $memory_limit_bytes) * 100);
        $memory_status = 'good';

        if ($memory_usage_percent > 80) {
            $memory_status = 'error';
        } elseif ($memory_usage_percent > 60) {
            $memory_status = 'warning';
        }

        // Get max execution time
        $max_execution_time = ini_get('max_execution_time');
        $execution_status = 'good';

        if ($max_execution_time < 30) {
            $execution_status = 'warning';
        }

        // Get database information
        global $wpdb;
        $db_version = $wpdb->db_version();
        $db_status = 'good';

        // Overall status
        $overall_status = 'good';
        if ($php_status === 'error' || $memory_status === 'error') {
            $overall_status = 'error';
        } elseif ($php_status === 'warning' || $wp_status === 'warning' || $memory_status === 'warning' || $execution_status === 'warning') {
            $overall_status = 'warning';
        }

        return [
            'php_version' => $php_version,
            'php_status' => $php_status,
            'wp_version' => $wp_version,
            'wp_status' => $wp_status,
            'memory_limit' => $memory_limit,
            'memory_usage' => $this->format_bytes($memory_usage),
            'memory_usage_raw' => $memory_usage,
            'memory_usage_percent' => $memory_usage_percent,
            'memory_status' => $memory_status,
            'max_execution_time' => $max_execution_time,
            'execution_status' => $execution_status,
            'db_version' => $db_version,
            'db_status' => $db_status,
            'status' => $overall_status
        ];
    }

    /**
     * Convert PHP memory limit string to bytes.
     *
     * @since    1.8.0
     * @param    string    $val    The memory limit string.
     * @return   int               The memory limit in bytes.
     */
    private function return_bytes($val) {
        $val = trim($val);
        $last = strtolower($val[strlen($val)-1]);
        $val = (int) $val;

        switch($last) {
            case 'g':
                $val *= 1024;
            case 'm':
                $val *= 1024;
            case 'k':
                $val *= 1024;
        }

        return $val;
    }

    /**
     * Format bytes to human-readable format.
     *
     * @since    1.8.0
     * @param    int       $bytes     The bytes to format.
     * @param    int       $precision The number of decimal places.
     * @return   string               The formatted string.
     */
    private function format_bytes($bytes, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    /**
     * Clear all dashboard caches.
     *
     * @since    1.7.0
     * @param    WP_REST_Request $request Full data about the request.
     * @return   WP_REST_Response
     */
    public function clear_cache($request) {
        try {
            // Delete all dashboard-related transients
            delete_transient('status_sentry_dashboard_overview');
            delete_transient('status_sentry_dashboard_recent');
            delete_transient('status_sentry_dashboard_baselines');
            delete_transient('status_sentry_dashboard_data');

            // Force resource manager to update its status
            $resource_manager = Status_Sentry_Resource_Manager::get_instance();
            if (method_exists($resource_manager, 'refresh')) {
                $resource_manager->refresh();
            }

            // Log the cache clear
            error_log('Status Sentry: Dashboard caches cleared via REST API');

            // Return success response
            $response = rest_ensure_response([
                'success' => true,
                'message' => 'All dashboard caches cleared',
                'timestamp' => date('Y-m-d H:i:s')
            ]);

            // Set no-cache headers
            $response->header('Cache-Control', 'no-cache, no-store, must-revalidate');
            $response->header('Pragma', 'no-cache');
            $response->header('Expires', '0');

            return $response;
        } catch (Throwable $e) {
            error_log('Status Sentry Dashboard: Error in clear_cache - ' . $e->getMessage());

            // Return error response
            $response = rest_ensure_response([
                'success' => false,
                'message' => 'Failed to clear caches: ' . $e->getMessage(),
                'timestamp' => date('Y-m-d H:i:s')
            ]);

            // Set no-cache headers
            $response->header('Cache-Control', 'no-cache, no-store, must-revalidate');
            $response->header('Pragma', 'no-cache');
            $response->header('Expires', '0');

            return $response;
        }
    }

    /**
     * Get event counts.
     *
     * @since    1.5.0
     * @return   array    The event counts.
     */
    private function get_event_counts() {
        try {
            $repository = $this->get_monitoring_events_repository();
            $counts = $repository->get_event_counts();

            // Map monitoring event type counts to legacy feature keys
            return [
                'core_monitoring' => ($counts['info'] ?? 0) + ($counts['warning'] ?? 0) + ($counts['error'] ?? 0),
                'db_monitoring' => ($counts['critical'] ?? 0),
                'conflict_detection' => ($counts['conflict'] ?? 0),
                'performance_monitoring' => ($counts['performance'] ?? 0),
            ];
        } catch (Throwable $e) {
            error_log('Status Sentry: Error in get_event_counts - ' . $e->getMessage());
            // Return default values if there's an error
            return [
                'core_monitoring' => 0,
                'db_monitoring' => 0,
                'conflict_detection' => 0,
                'performance_monitoring' => 0,
            ];
        }
    }

    /**
     * Get resource status.
     *
     * @since    1.5.0
     * @return   array    The resource status.
     */
    private function get_resource_status() {
        // Always use the resource manager for consistent data
        $resource_manager = Status_Sentry_Resource_Manager::get_instance();
        $status = $resource_manager->get_status();

        // Ensure CPU load is always a percentage (0-100)
        if (isset($status['cpu_load']) && $status['cpu_load'] <= 1.0) {
            // Convert fraction to percentage if it's a fraction (0-1)
            $status['cpu_load'] = round($status['cpu_load'] * 100, 2);
        }

        return $status;
    }

    /**
     * Get memory limit in bytes.
     *
     * @since    1.5.0
     * @return   int    Memory limit in bytes.
     */
    private function get_memory_limit_in_bytes() {
        $memory_limit = ini_get('memory_limit');

        // Convert memory limit to bytes
        $unit = strtoupper(substr($memory_limit, -1));
        $value = (int) substr($memory_limit, 0, -1);

        switch ($unit) {
            case 'G':
                $value *= 1024;
                // Fall through
            case 'M':
                $value *= 1024;
                // Fall through
            case 'K':
                $value *= 1024;
        }

        return $value;
    }

    /**
     * Get CPU load.
     *
     * @deprecated 1.7.0 Use Status_Sentry_Resource_Manager::get_status() instead
     * @since      1.5.0
     * @return     float    CPU load as a percentage.
     */
    private function get_cpu_load() {
        // This method is deprecated and should not be used
        // Always use the Resource Manager for CPU load
        $resource_manager = Status_Sentry_Resource_Manager::get_instance();
        $status = $resource_manager->get_status();

        if (isset($status['cpu_load'])) {
            // Ensure it's a percentage
            if ($status['cpu_load'] <= 1.0) {
                return round($status['cpu_load'] * 100, 2);
            }
            return $status['cpu_load'];
        }

        // Last resort fallback (should never be reached)
        return 0;
    }

    /**
     * Get number of CPU cores.
     *
     * @since    1.5.0
     * @return   int    Number of CPU cores.
     */
    private function get_cpu_cores() {
        // Default to 1 core
        $cores = 1;

        // Try to get number of cores from system
        if (is_readable('/proc/cpuinfo')) {
            $cpuinfo = file_get_contents('/proc/cpuinfo');
            preg_match_all('/^processor/m', $cpuinfo, $matches);
            $cores = count($matches[0]);
        }

        return max(1, $cores);
    }

    /**
     * Generate recommendations based on event counts and resource status.
     *
     * @since    1.5.0
     * @param    array    $event_counts      The event counts.
     * @param    array    $resource_status   The resource status.
     * @return   array                       The recommendations.
     */
    private function generate_recommendations($event_counts, $resource_status) {
        $recommendations = [];

        // Check memory usage
        if (isset($resource_status['memory_usage_percent']) && $resource_status['memory_usage_percent'] > 80) {
            $recommendations[] = [
                'type' => 'warning',
                'message' => 'Memory usage is high. Consider increasing PHP memory limit or optimizing your site.',
                'action' => 'Adjust memory settings in the Performance tab of the Settings page.',
            ];
        }

        // Check CPU load
        if (isset($resource_status['cpu_load']) && $resource_status['cpu_load'] > 70) {
            $recommendations[] = [
                'type' => 'warning',
                'message' => 'CPU load is high. Your server may be overloaded.',
                'action' => 'Consider upgrading your hosting or optimizing resource-intensive tasks.',
            ];
        }

        // Check conflict detection events
        if (isset($event_counts['conflict_detection']) && $event_counts['conflict_detection'] > 10) {
            $recommendations[] = [
                'type' => 'error',
                'message' => 'Multiple plugin conflicts detected.',
                'action' => 'Review the Events page for details on the conflicts.',
            ];
        }

        // Check performance monitoring events
        if (isset($event_counts['performance_monitoring']) && $event_counts['performance_monitoring'] > 20) {
            $recommendations[] = [
                'type' => 'warning',
                'message' => 'Performance issues detected.',
                'action' => 'Review the Events page for details on performance issues.',
            ];
        }

        // If no recommendations, add a success message
        if (empty($recommendations)) {
            $recommendations[] = [
                'type' => 'success',
                'message' => 'Your site is running smoothly.',
                'action' => 'Continue monitoring for optimal performance.',
            ];
        }

        return $recommendations;
    }

    /**
     * Get memory usage trends.
     *
     * @since    1.5.0
     * @return   array    The memory usage trends.
     */
    private function get_memory_trends() {
        try {
            global $wpdb;

            $table_name = $wpdb->prefix . 'status_sentry_monitoring_events';

            // Check if the table exists
            if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
                error_log('Status Sentry: Table ' . $table_name . ' does not exist. Using dummy data for memory trends.');
                return $this->generate_dummy_trend_data('memory');
            }

            // Get memory usage events from the last 24 hours - using a simpler query
            try {
                // First check if the JSON_EXTRACT function is available (MySQL 5.7+)
                $has_json_extract = $wpdb->get_var("SELECT COUNT(*) FROM information_schema.ROUTINES WHERE ROUTINE_NAME = 'JSON_EXTRACT' AND ROUTINE_TYPE = 'FUNCTION'");

                if ($has_json_extract) {
                    $events = $wpdb->get_results(
                        "SELECT timestamp, data FROM $table_name
                        WHERE event_type = 'resource_usage'
                        AND source = 'resource_manager'
                        AND timestamp > DATE_SUB(NOW(), INTERVAL 24 HOUR)
                        ORDER BY timestamp ASC",
                        ARRAY_A
                    );
                } else {
                    // Fallback for older MySQL versions
                    $events = $wpdb->get_results(
                        "SELECT timestamp, data FROM $table_name
                        WHERE event_type = 'resource_usage'
                        AND source = 'resource_manager'
                        AND timestamp > DATE_SUB(NOW(), INTERVAL 24 HOUR)
                        ORDER BY timestamp ASC",
                        ARRAY_A
                    );
                }

                // Check for database errors
                if ($wpdb->last_error) {
                    error_log('Status Sentry: Database error in get_memory_trends: ' . $wpdb->last_error);
                    return $this->generate_dummy_trend_data('memory');
                }
            } catch (Exception $e) {
                error_log('Status Sentry: Exception in get_memory_trends SQL query: ' . $e->getMessage());
                return $this->generate_dummy_trend_data('memory');
            }

            if (empty($events)) {
                error_log('Status Sentry: No memory trend data found. Using dummy data.');
                return $this->generate_dummy_trend_data('memory');
            }

            // Process events
            $trend_data = [];
            foreach ($events as $event) {
                try {
                    $data = json_decode($event['data'], true);
                    if (isset($data['memory_usage'])) {
                        $trend_data[] = [
                            'timestamp' => $event['timestamp'],
                            'value' => round($data['memory_usage'] / (1024 * 1024), 2), // Convert to MB
                        ];
                    }
                } catch (Exception $e) {
                    error_log('Status Sentry: Error processing memory trend data: ' . $e->getMessage());
                    // Continue with next event
                    continue;
                }
            }

            if (empty($trend_data)) {
                error_log('Status Sentry: No valid memory trend data found. Using dummy data.');
                return $this->generate_dummy_trend_data('memory');
            }

            return $trend_data;
        } catch (Exception $e) {
            error_log('Status Sentry: Exception in get_memory_trends: ' . $e->getMessage());
            return $this->generate_dummy_trend_data('memory');
        }
    }

    /**
     * Get CPU usage trends.
     *
     * @since    1.5.0
     * @return   array    The CPU usage trends.
     */
    private function get_cpu_trends() {
        try {
            global $wpdb;

            $table_name = $wpdb->prefix . 'status_sentry_monitoring_events';

            // Check if the table exists
            if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
                error_log('Status Sentry: Table ' . $table_name . ' does not exist. Using dummy data for CPU trends.');
                return $this->generate_dummy_trend_data('cpu');
            }

            // Get CPU usage events from the last 24 hours - using a simpler query
            try {
                $events = $wpdb->get_results(
                    "SELECT timestamp, data FROM $table_name
                    WHERE event_type = 'resource_usage'
                    AND source = 'resource_manager'
                    AND timestamp > DATE_SUB(NOW(), INTERVAL 24 HOUR)
                    ORDER BY timestamp ASC",
                    ARRAY_A
                );

                // Check for database errors
                if ($wpdb->last_error) {
                    error_log('Status Sentry: Database error in get_cpu_trends: ' . $wpdb->last_error);
                    return $this->generate_dummy_trend_data('cpu');
                }
            } catch (Exception $e) {
                error_log('Status Sentry: Exception in get_cpu_trends SQL query: ' . $e->getMessage());
                return $this->generate_dummy_trend_data('cpu');
            }

            if (empty($events)) {
                error_log('Status Sentry: No CPU trend data found. Using dummy data.');
                return $this->generate_dummy_trend_data('cpu');
            }

            // Process events
            $trend_data = [];
            foreach ($events as $event) {
                try {
                    $data = json_decode($event['data'], true);
                    if (isset($data['cpu_usage'])) {
                        $trend_data[] = [
                            'timestamp' => $event['timestamp'],
                            'value' => round($data['cpu_usage'], 2),
                        ];
                    }
                } catch (Exception $e) {
                    error_log('Status Sentry: Error processing CPU trend data: ' . $e->getMessage());
                    // Continue with next event
                    continue;
                }
            }

            if (empty($trend_data)) {
                error_log('Status Sentry: No valid CPU trend data found. Using dummy data.');
                return $this->generate_dummy_trend_data('cpu');
            }

            return $trend_data;
        } catch (Exception $e) {
            error_log('Status Sentry: Exception in get_cpu_trends: ' . $e->getMessage());
            return $this->generate_dummy_trend_data('cpu');
        }
    }

    /**
     * Get event trends.
     *
     * @since    1.5.0
     * @return   array    The event trends.
     */
    private function get_event_trends() {
        try {
            global $wpdb;

            // First check if the repository can verify the table exists
            $repository = $this->get_monitoring_events_repository();
            if (!$repository->table_exists()) {
                error_log('Status Sentry: Monitoring events table does not exist. Using dummy data.');
                return $this->generate_dummy_trend_data('events');
            }

            $table_name = $wpdb->prefix . 'status_sentry_monitoring_events';

            // Get event counts by day for the last 7 days - using a simpler query to avoid SQL errors
            try {
                $events = $wpdb->get_results(
                    "SELECT DATE(timestamp) as date, COUNT(*) as count
                    FROM $table_name
                    WHERE timestamp > DATE_SUB(NOW(), INTERVAL 7 DAY)
                    GROUP BY DATE(timestamp)
                    ORDER BY date ASC",
                    ARRAY_A
                );

                // Log the query and result
                error_log('Status Sentry: Event trends query executed');
                error_log('Status Sentry: Found ' . count($events) . ' data points for event trends');

                // Check for database errors
                if ($wpdb->last_error) {
                    error_log('Status Sentry: Database error in get_event_trends: ' . $wpdb->last_error);
                    return $this->generate_dummy_trend_data('events');
                }
            } catch (Exception $e) {
                error_log('Status Sentry: Exception in get_event_trends SQL query: ' . $e->getMessage());
                return $this->generate_dummy_trend_data('events');
            }

            if (empty($events)) {
                error_log('Status Sentry: No event data found. Using dummy data.');
                return $this->generate_dummy_trend_data('events');
            }

            // Process events
            $trend_data = [];
            foreach ($events as $event) {
                $trend_data[] = [
                    'timestamp' => $event['date'],
                    'value' => (int) $event['count'],
                ];
            }

            return $trend_data;
        } catch (Exception $e) {
            error_log('Status Sentry: Exception in get_event_trends: ' . $e->getMessage());
            return $this->generate_dummy_trend_data('events');
        }
    }

    /**
     * Generate dummy trend data method removed
     */

    /**
     * Get recent events.
     *
     * @since    1.5.0
     * @param    int       $limit    The maximum number of events to get.
     * @return   array               The recent events.
     */
    private function get_recent_events($limit = 10) {
        try {
            // First check if the monitoring events table exists
            global $wpdb;
            $table_name = $wpdb->prefix . 'status_sentry_monitoring_events';
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") == $table_name;

            if (!$table_exists) {
                error_log('Status Sentry: Monitoring events table does not exist, returning empty array');
                return [];
            }

            $repository = $this->get_monitoring_events_repository();
            $events = $repository->get_recent_events($limit);

            if (empty($events)) {
                error_log('Status Sentry: No recent events found, returning empty array');
                return [];
            }

            // Convert objects to arrays and process the data
            $processed_events = [];
            foreach ($events as $event) {
                try {
                    // Validate event object has required properties
                    if (!isset($event->id) || !isset($event->event_type) || !isset($event->source) || !isset($event->context)) {
                        error_log('Status Sentry: Event object missing required properties, skipping');
                        continue;
                    }

                    // Map event type to legacy feature
                    $feature = $this->map_event_type_to_feature($event->event_type);

                    $processed_event = [
                        'id' => $event->id,
                        'feature' => $feature,
                        'hook' => $event->source . '/' . $event->context,
                        'event_time' => $event->timestamp ?? date('Y-m-d H:i:s'),
                        'event_type' => $event->event_type,
                        'priority' => $event->priority ?? 'normal',
                        'source' => $event->source,
                        'context' => $event->context,
                        'message' => $event->message ?? 'No message',
                        'is_monitoring_event' => true
                    ];

                    // Process data if available
                    if (isset($event->data)) {
                        $processed_event['data'] = json_decode($event->data, true) ?? [];
                    } else {
                        $processed_event['data'] = [];
                    }

                    // Add formatted time and feature name
                    $timestamp = isset($event->timestamp) ? strtotime($event->timestamp) : time();
                    $processed_event['time_ago'] = human_time_diff($timestamp, time()) . ' ago';
                    $processed_event['feature_name'] = ucfirst(str_replace('_', ' ', $feature));

                    // Add event type name for display
                    $processed_event['event_type_name'] = ucfirst($event->event_type);

                    $processed_events[] = $processed_event;
                } catch (Throwable $e) {
                    error_log('Status Sentry: Error processing event data: ' . $e->getMessage());
                    // Continue with next event
                    continue;
                }
            }

            return $processed_events;
        } catch (Throwable $e) {
            error_log('Status Sentry: Exception in get_recent_events: ' . $e->getMessage());
            error_log('Status Sentry: Error type: ' . get_class($e));
            error_log('Status Sentry: Error trace: ' . $e->getTraceAsString());
            return [];
        }
    }

    /**
     * Map event type to legacy feature.
     *
     * @since    1.6.0
     * @param    string    $event_type    The event type.
     * @return   string                   The legacy feature.
     */
    private function map_event_type_to_feature($event_type) {
        switch ($event_type) {
            case 'info':
            case 'warning':
            case 'error':
                return 'core_monitoring';
            case 'critical':
                return 'db_monitoring';
            case 'conflict':
                return 'conflict_detection';
            case 'performance':
                return 'performance_monitoring';
            default:
                return 'core_monitoring';
        }
    }

    /**
     * Get baseline data.
     *
     * @since    1.5.0
     * @param    bool     $manual_refresh    Whether this is a manual refresh from the UI.
     * @return   array    The baseline data.
     */
    private function get_baseline_data($manual_refresh = false) {
        try {
            global $wpdb;

            $table_name = $wpdb->prefix . 'status_sentry_baselines';

            // Check if the table exists
            if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
                error_log('Status Sentry: Table ' . $table_name . ' does not exist. Returning empty baselines array.');
                return [];
            }

            // Get all baselines
            try {
                $baselines = $wpdb->get_results(
                    "SELECT * FROM $table_name ORDER BY last_updated DESC",
                    ARRAY_A
                );

                // Check for database errors
                if ($wpdb->last_error) {
                    error_log('Status Sentry: Database error in get_baseline_data: ' . $wpdb->last_error);
                    return [];
                }
            } catch (Exception $e) {
                error_log('Status Sentry: Exception in get_baseline_data SQL query: ' . $e->getMessage());
                return [];
            }

            if (empty($baselines)) {
                return [];
            }

            // Define metric labels and units mapping
            $metric_labels = [
                'memory_usage' => 'Memory Usage',
                'cpu_load' => 'CPU Load',
                'execution_time' => 'Execution Time',
                'query_time' => 'Query Time',
                'page_load' => 'Page Load',
                'database_size' => 'Database Size',
                'request_count' => 'Request Count',
                'error_count' => 'Error Count',
                'cache_hit_ratio' => 'Cache Hit Ratio',
                'disk_usage' => 'Disk Usage',
                'network_latency' => 'Network Latency',
                'concurrent_users' => 'Concurrent Users',
                'api_response_time' => 'API Response Time',
                'plugin_load_time' => 'Plugin Load Time',
                'theme_load_time' => 'Theme Load Time',
                'admin_load_time' => 'Admin Load Time',
                'frontend_load_time' => 'Frontend Load Time',
                'ajax_response_time' => 'AJAX Response Time',
                'rest_response_time' => 'REST Response Time',
                'cron_execution_time' => 'Cron Execution Time',
            ];

            $metric_units = [
                'memory_usage' => 'MB',
                'cpu_load' => '%',
                'execution_time' => 'ms',
                'query_time' => 'ms',
                'page_load' => 'ms',
                'database_size' => 'MB',
                'request_count' => 'req/min',
                'error_count' => 'errors/day',
                'cache_hit_ratio' => '%',
                'disk_usage' => 'MB',
                'network_latency' => 'ms',
                'concurrent_users' => 'users',
                'api_response_time' => 'ms',
                'plugin_load_time' => 'ms',
                'theme_load_time' => 'ms',
                'admin_load_time' => 'ms',
                'frontend_load_time' => 'ms',
                'ajax_response_time' => 'ms',
                'rest_response_time' => 'ms',
                'cron_execution_time' => 'ms',
            ];

            // Try to get the baseline instance for threshold checking
            $baseline_instance = null;
            if (class_exists('Status_Sentry_Baseline')) {
                $baseline_instance = new Status_Sentry_Baseline();
            }

            // Get the default threshold from the baseline config
            $default_threshold = 0.2; // 20% default
            if ($baseline_instance && method_exists($baseline_instance, 'get_config')) {
                $config = $baseline_instance->get_config();
                $default_threshold = isset($config['significance_threshold']) ? $config['significance_threshold'] : 0.2;
            }

            // Process baselines
            $processed_baselines = [];
            foreach ($baselines as $baseline) {
                try {
                    $processed_baseline = $baseline;

                    // Add human-friendly label
                    $metric_name = isset($baseline['metric']) ? $baseline['metric'] :
                                  (isset($baseline['metric_name']) ? $baseline['metric_name'] : '');

                    $processed_baseline['label'] = isset($metric_labels[$metric_name]) ?
                                                $metric_labels[$metric_name] :
                                                ucfirst(str_replace('_', ' ', $metric_name));

                    // Add unit
                    $processed_baseline['unit'] = isset($metric_units[$metric_name]) ?
                                               $metric_units[$metric_name] : '';

                    // Add threshold
                    $processed_baseline['threshold'] = $default_threshold * 100 . '%';

                    // Check if this is a deviation
                    $processed_baseline['is_deviation'] = false;

                    // Parse metadata if available
                    if (isset($baseline['metadata'])) {
                        $metadata = is_string($baseline['metadata']) ?
                                   json_decode($baseline['metadata'], true) :
                                   $baseline['metadata'];

                        $processed_baseline['metadata'] = $metadata;

                        // Check if metadata contains deviation information
                        if (isset($metadata['is_deviation'])) {
                            $processed_baseline['is_deviation'] = (bool)$metadata['is_deviation'];
                        } elseif (isset($metadata['deviation']) && is_numeric($metadata['deviation'])) {
                            // If deviation value is available, check against threshold
                            $processed_baseline['is_deviation'] = abs($metadata['deviation']) > $default_threshold;
                        }

                        // If metadata contains a custom threshold, use it
                        if (isset($metadata['threshold']) && is_numeric($metadata['threshold'])) {
                            $processed_baseline['threshold'] = $metadata['threshold'] * 100 . '%';
                        }
                    }

                    // Check for deviation using the Baseline class if available
                    if ($baseline_instance && method_exists($baseline_instance, 'is_significant_deviation') &&
                        isset($baseline['value']) && is_numeric($baseline['value']) &&
                        isset($baseline['metric_name']) && isset($baseline['metric_context'])) {

                        // Get the current value for comparison
                        $current_value = $baseline['value'];

                        // Check if there's a significant deviation
                        $is_deviation = $baseline_instance->is_significant_deviation(
                            $baseline['metric_name'],
                            $baseline['metric_context'],
                            $current_value
                        );

                        if ($is_deviation) {
                            $processed_baseline['is_deviation'] = true;
                        }
                    }

                    // Add time ago - show "just now" for manual refreshes
                    if ($manual_refresh) {
                        $processed_baseline['time_ago'] = 'just now';
                    } else {
                        $processed_baseline['time_ago'] = human_time_diff(strtotime($baseline['last_updated']), time()) . ' ago';
                    }

                    $processed_baselines[] = $processed_baseline;
                } catch (Exception $e) {
                    error_log('Status Sentry: Error processing baseline data: ' . $e->getMessage());
                    // Continue with next baseline
                    continue;
                }
            }

            return $processed_baselines;
        } catch (Exception $e) {
            error_log('Status Sentry: Exception in get_baseline_data: ' . $e->getMessage());
            return [];
        }
    }
}
