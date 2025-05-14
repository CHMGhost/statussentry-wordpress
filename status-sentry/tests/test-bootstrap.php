<?php
/**
 * Test Bootstrap File
 *
 * This file sets up the necessary environment for running tests without
 * requiring a full WordPress installation. It defines the required WordPress
 * constants and functions needed for the Status Sentry plugin tests.
 *
 * @package    Status_Sentry
 * @subpackage Status_Sentry/tests
 */

// Define WordPress constants
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__) . '/tests/mocks/');
}
if (!defined('WP_DEBUG')) {
    define('WP_DEBUG', true);
}
if (!defined('WP_DEBUG_LOG')) {
    define('WP_DEBUG_LOG', true);
}
if (!defined('WP_DEBUG_DISPLAY')) {
    define('WP_DEBUG_DISPLAY', true);
}

// Define plugin constants
if (!defined('STATUS_SENTRY_VERSION')) {
    define('STATUS_SENTRY_VERSION', '1.3.0');
}
if (!defined('STATUS_SENTRY_PLUGIN_DIR')) {
    define('STATUS_SENTRY_PLUGIN_DIR', dirname(__DIR__) . '/');
}

// Set up global $wpdb
global $wpdb;
if (!isset($wpdb)) {
    $wpdb = new class {
        public $prefix = 'wp_';
        public $status_sentry_events;
        public $status_sentry_monitoring_events;
        public $charset = 'utf8mb4';
        public $collate = 'utf8mb4_unicode_ci';

        public function __construct() {
            $this->status_sentry_events = $this->prefix . 'status_sentry_events';
            $this->status_sentry_monitoring_events = $this->prefix . 'status_sentry_monitoring_events';
        }

        public function prepare($query, ...$args) {
            if (empty($args)) {
                return $query;
            }

            // Simple implementation of prepare
            $i = 0;
            $query = preg_replace_callback('/(%s|%d|%f|%%)/', function($matches) use (&$i, $args) {
                if ($matches[0] === '%%') {
                    return '%';
                }

                if (!isset($args[$i])) {
                    return $matches[0];
                }

                $value = $args[$i++];

                switch ($matches[0]) {
                    case '%d':
                        return (int) $value;
                    case '%f':
                        return (float) $value;
                    case '%s':
                        return "'" . addslashes($value) . "'";
                    default:
                        return $value;
                }
            }, $query);

            return $query;
        }

        public function get_var($query) {
            // For testing, return some sample data based on the query
            if (strpos($query, 'COUNT(*)') !== false) {
                return 10; // Return 10 as count for any count query
            }

            if (strpos($query, 'MAX(id)') !== false) {
                return 100; // Return 100 as max ID
            }

            return null;
        }

        public function get_results($query, $output = OBJECT) {
            // Return sample data for different queries
            if (strpos($query, 'status_sentry_events') !== false) {
                $results = [];
                for ($i = 1; $i <= 5; $i++) {
                    $results[] = (object) [
                        'id' => $i,
                        'feature' => 'test_feature',
                        'hook' => 'test_hook',
                        'data' => json_encode(['test_data' => $i]),
                        'timestamp' => date('Y-m-d H:i:s'),
                        'status' => 'pending'
                    ];
                }
                return $results;
            }

            if (strpos($query, 'status_sentry_monitoring_events') !== false) {
                $results = [];
                for ($i = 1; $i <= 5; $i++) {
                    $results[] = (object) [
                        'id' => $i,
                        'type' => $i % 2 ? 'warning' : 'error',
                        'priority' => $i,
                        'source' => 'test_source',
                        'context' => 'test_context',
                        'message' => 'Test message ' . $i,
                        'timestamp' => date('Y-m-d H:i:s'),
                        'data' => json_encode(['test_data' => $i]),
                        'handlers' => 'default'
                    ];
                }
                return $results;
            }

            return [];
        }

        public function query($query) {
            return true;
        }

        public function insert($table, $data, $format = null) {
            return 1;
        }

        public function update($table, $data, $where, $format = null, $where_format = null) {
            return 1;
        }

        public function delete($table, $where, $where_format = null) {
            return 1;
        }

        public function get_charset_collate() {
            return "DEFAULT CHARACTER SET {$this->charset} COLLATE {$this->collate}";
        }
    };
}

// Define WordPress functions if they don't exist
if (!function_exists('add_filter')) {
    function add_filter($tag, $function_to_add, $priority = 10, $accepted_args = 1) {
        return true;
    }
}

if (!function_exists('apply_filters')) {
    function apply_filters($tag, $value, ...$args) {
        return $value;
    }
}

if (!function_exists('add_action')) {
    function add_action($tag, $function_to_add, $priority = 10, $accepted_args = 1) {
        return true;
    }
}

if (!function_exists('get_option')) {
    function get_option($option, $default = false) {
        static $options = [
            'status_sentry_settings' => [
                'db_batch_size' => 100,
                'memory_threshold' => 75,
                'gc_cycles' => 3,
                'cpu_threshold' => 65,
                'enable_query_cache' => 1,
                'query_cache_ttl' => 3600,
                'enable_resumable_tasks' => 1
            ]
        ];

        return isset($options[$option]) ? $options[$option] : $default;
    }
}

if (!function_exists('update_option')) {
    function update_option($option, $value, $autoload = null) {
        return true;
    }
}

if (!function_exists('wp_next_scheduled')) {
    function wp_next_scheduled($hook) {
        return time() + 3600; // Return a future timestamp
    }
}

if (!function_exists('is_user_logged_in')) {
    function is_user_logged_in() {
        return false;
    }
}

if (!function_exists('get_current_user_id')) {
    function get_current_user_id() {
        return 0;
    }
}

if (!function_exists('get_site_url')) {
    function get_site_url($blog_id = null, $path = '', $scheme = null) {
        return 'https://example.com' . ($path ? '/' . ltrim($path, '/') : '');
    }
}

if (!function_exists('wp_parse_args')) {
    function wp_parse_args($args, $defaults = []) {
        if (is_object($args)) {
            $args = get_object_vars($args);
        }
        if (is_array($args)) {
            return array_merge($defaults, $args);
        }
        return $defaults;
    }
}

if (!function_exists('current_time')) {
    function current_time($type, $gmt = 0) {
        return date('Y-m-d H:i:s');
    }
}

if (!function_exists('absint')) {
    function absint($number) {
        return abs(intval($number));
    }
}

if (!function_exists('maybe_serialize')) {
    function maybe_serialize($data) {
        if (is_array($data) || is_object($data)) {
            return serialize($data);
        }
        return $data;
    }
}

if (!function_exists('maybe_unserialize')) {
    function maybe_unserialize($data) {
        if (is_serialized($data)) {
            return @unserialize($data);
        }
        return $data;
    }
}

if (!function_exists('is_serialized')) {
    function is_serialized($data) {
        // If it isn't a string, it isn't serialized.
        if (!is_string($data)) {
            return false;
        }
        $data = trim($data);
        if ('N;' === $data) {
            return true;
        }
        if (strlen($data) < 4 || ':' !== $data[1]) {
            return false;
        }
        if ('s:' === substr($data, 0, 2)) {
            if ('"' !== substr($data, -2, 1)) {
                return false;
            }
        } elseif ('a:' === substr($data, 0, 2) || 'O:' === substr($data, 0, 2)) {
            if ('}' !== substr($data, -1)) {
                return false;
            }
        } elseif ('b:' === substr($data, 0, 2) || 'i:' === substr($data, 0, 2) || 'd:' === substr($data, 0, 2)) {
            if (';' !== substr($data, -1)) {
                return false;
            }
        }
        return true;
    }
}

if (!function_exists('wp_json_encode')) {
    function wp_json_encode($data, $options = 0, $depth = 512) {
        return json_encode($data, $options, $depth);
    }
}

if (!function_exists('dbDelta')) {
    function dbDelta($queries, $execute = true) {
        return true;
    }
}

if (!function_exists('get_bloginfo')) {
    function get_bloginfo($show = '', $filter = 'raw') {
        $output = '';

        switch ($show) {
            case 'name':
                $output = 'Test Site';
                break;
            case 'description':
                $output = 'Just another WordPress site';
                break;
            case 'wpurl':
            case 'url':
                $output = 'https://example.com';
                break;
            case 'version':
                $output = '6.4.3';
                break;
            case 'admin_email':
                $output = 'admin@example.com';
                break;
            case 'charset':
                $output = 'UTF-8';
                break;
            case 'language':
                $output = 'en-US';
                break;
            default:
                $output = '';
        }

        return $output;
    }
}

if (!function_exists('wp_get_current_user')) {
    function wp_get_current_user() {
        static $user = null;

        if ($user === null) {
            $user = new class {
                public $ID = 0;
                public $user_login = '';
                public $user_email = '';
                public $display_name = 'Anonymous';
                public $roles = [];

                public function exists() {
                    return $this->ID > 0;
                }
            };
        }

        return $user;
    }
}

if (!function_exists('wp_get_theme')) {
    function wp_get_theme() {
        return (object) [
            'Name' => 'Test Theme',
            'Version' => '1.0.0',
            'get' => function($key) {
                $data = [
                    'Name' => 'Test Theme',
                    'Version' => '1.0.0',
                    'ThemeURI' => '',
                    'Description' => 'Test Theme Description',
                    'Author' => 'Test Author',
                    'AuthorURI' => '',
                    'Template' => '',
                    'Status' => 'active',
                    'Tags' => [],
                    'TextDomain' => 'test-theme',
                    'DomainPath' => ''
                ];
                return isset($data[$key]) ? $data[$key] : '';
            }
        ];
    }
}

if (!function_exists('get_plugins')) {
    function get_plugins() {
        return [
            'test-plugin/test-plugin.php' => [
                'Name' => 'Test Plugin',
                'Version' => '1.0.0',
                'Description' => 'Test Plugin Description',
                'Author' => 'Test Author',
                'PluginURI' => '',
                'AuthorURI' => '',
                'TextDomain' => 'test-plugin',
                'DomainPath' => ''
            ]
        ];
    }
}

if (!function_exists('is_plugin_active')) {
    function is_plugin_active($plugin) {
        return true;
    }
}

if (!function_exists('is_admin')) {
    function is_admin() {
        return false;
    }
}

if (!function_exists('wp_doing_ajax')) {
    function wp_doing_ajax() {
        return false;
    }
}

if (!defined('WP_CLI')) {
    define('WP_CLI', false);
}

if (!defined('DOING_CRON')) {
    define('DOING_CRON', false);
}

if (!defined('REST_REQUEST')) {
    define('REST_REQUEST', false);
}

if (!function_exists('wp_schedule_event')) {
    function wp_schedule_event($timestamp, $recurrence, $hook, $args = []) {
        return true;
    }
}

if (!function_exists('wp_clear_scheduled_hook')) {
    function wp_clear_scheduled_hook($hook, $args = []) {
        return true;
    }
}

if (!function_exists('wp_unschedule_event')) {
    function wp_unschedule_event($timestamp, $hook, $args = []) {
        return true;
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($str) {
        return trim(strip_tags($str));
    }
}

if (!function_exists('sanitize_email')) {
    function sanitize_email($email) {
        return filter_var($email, FILTER_SANITIZE_EMAIL);
    }
}

if (!function_exists('sanitize_key')) {
    function sanitize_key($key) {
        return preg_replace('/[^a-z0-9_\-]/', '', strtolower($key));
    }
}

if (!function_exists('sanitize_title')) {
    function sanitize_title($title) {
        return strtolower(trim(preg_replace('/[^a-zA-Z0-9\s]/', '', $title)));
    }
}

if (!function_exists('esc_url')) {
    function esc_url($url) {
        return filter_var($url, FILTER_SANITIZE_URL);
    }
}

if (!function_exists('esc_html')) {
    function esc_html($text) {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_attr')) {
    function esc_attr($text) {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('do_action')) {
    function do_action($tag, ...$args) {
        return;
    }
}

if (!function_exists('did_action')) {
    function did_action($tag) {
        return 0;
    }
}

if (!function_exists('has_action')) {
    function has_action($tag, $function_to_check = false) {
        return false;
    }
}

if (!function_exists('wp_schedule_single_event')) {
    function wp_schedule_single_event($timestamp, $hook, $args = []) {
        return true;
    }
}

if (!function_exists('wp_get_schedules')) {
    function wp_get_schedules() {
        return [
            'hourly' => [
                'interval' => 3600,
                'display' => 'Once Hourly'
            ],
            'daily' => [
                'interval' => 86400,
                'display' => 'Once Daily'
            ],
            'weekly' => [
                'interval' => 604800,
                'display' => 'Once Weekly'
            ]
        ];
    }
}

if (!function_exists('wp_mkdir_p')) {
    function wp_mkdir_p($dir) {
        return true;
    }
}

// Add $wpdb->last_error property
global $wpdb;
if (!isset($wpdb->last_error)) {
    $wpdb->last_error = '';
}

if (!function_exists('_get_cron_array')) {
    function _get_cron_array() {
        $crons = [
            time() + 3600 => [
                'status_sentry_process_queue' => [
                    md5(serialize([])) => [
                        'schedule' => 'hourly',
                        'args' => [],
                        'interval' => 3600,
                    ],
                ],
                'status_sentry_cleanup' => [
                    md5(serialize([])) => [
                        'schedule' => 'daily',
                        'args' => [],
                        'interval' => 86400,
                    ],
                ],
                'status_sentry_cleanup_expired_cache' => [
                    md5(serialize([])) => [
                        'schedule' => 'hourly',
                        'args' => [],
                        'interval' => 3600,
                    ],
                ],
                'status_sentry_cleanup_expired_task_state' => [
                    md5(serialize([])) => [
                        'schedule' => 'daily',
                        'args' => [],
                        'interval' => 86400,
                    ],
                ],
                'status_sentry_update_baselines' => [
                    md5(serialize([])) => [
                        'schedule' => 'daily',
                        'args' => [],
                        'interval' => 86400,
                    ],
                ],
            ],
        ];

        return $crons;
    }
}

if (!function_exists('wp_get_scheduled_event')) {
    function wp_get_scheduled_event($hook, $args = [], $timestamp = null) {
        $crons = _get_cron_array();

        if ($timestamp) {
            if (isset($crons[$timestamp][$hook][md5(serialize($args))])) {
                $event = $crons[$timestamp][$hook][md5(serialize($args))];
                $event['hook'] = $hook;
                $event['timestamp'] = $timestamp;
                $event['args'] = $args;
                return (object) $event;
            }
            return false;
        }

        foreach ($crons as $timestamp => $hooks) {
            if (isset($hooks[$hook][md5(serialize($args))])) {
                $event = $hooks[$hook][md5(serialize($args))];
                $event['hook'] = $hook;
                $event['timestamp'] = $timestamp;
                $event['args'] = $args;
                return (object) $event;
            }
        }

        return false;
    }
}

// Include any other necessary WordPress functions here
