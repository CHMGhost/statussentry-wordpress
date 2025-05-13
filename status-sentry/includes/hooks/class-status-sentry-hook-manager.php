<?php
declare(strict_types=1);

/**
 * Hook manager class.
 *
 * @since      1.0.0
 * @package    Status_Sentry
 * @subpackage Status_Sentry/includes/hooks
 */

/**
 * Hook Manager Class
 *
 * The Hook Manager is a core component of the Status Sentry plugin that handles
 * the registration and execution of WordPress hooks for monitoring purposes.
 * It works in conjunction with the Hook Config class to determine which hooks
 * to register and how they should be processed.
 *
 * Key responsibilities:
 * - Register hooks based on configuration and feature flags
 * - Apply sampling logic to reduce performance impact
 * - Capture data from various WordPress hooks
 * - Pass captured data to the data pipeline for processing
 *
 * This class implements a sophisticated hook registration system that allows for:
 * - Prioritized hook registration
 * - Conditional execution based on context
 * - Sampling to reduce performance impact
 * - Feature-based organization of hooks
 *
 * @since      1.0.0
 * @package    Status_Sentry
 * @subpackage Status_Sentry/includes/hooks
 * @author     Status Sentry Team
 */
class Status_Sentry_Hook_Manager {

    /**
     * The hook configuration.
     *
     * @since    1.0.0
     * @access   private
     * @var      Status_Sentry_Hook_Config    $hook_config    The hook configuration.
     */
    private $hook_config;

    /**
     * The data capture instance.
     *
     * @since    1.0.0
     * @access   private
     * @var      Status_Sentry_Data_Capture    $data_capture    The data capture instance.
     */
    private $data_capture;

    /**
     * The sampling manager instance.
     *
     * @since    1.0.0
     * @access   private
     * @var      Status_Sentry_Sampling_Manager    $sampling_manager    The sampling manager instance.
     */
    private $sampling_manager;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     * @param    Status_Sentry_Hook_Config    $hook_config    The hook configuration.
     */
    public function __construct($hook_config) {
        $this->hook_config = $hook_config;
        $this->data_capture = new Status_Sentry_Data_Capture();
        $this->sampling_manager = new Status_Sentry_Sampling_Manager();
    }

    /**
     * Register all hooks defined in the hook configuration.
     *
     * This method iterates through all hooks defined in the hook configuration
     * and registers them if their associated feature is enabled. It provides
     * a centralized way to manage all monitoring hooks in the plugin.
     *
     * The registration process follows these steps:
     * 1. Get all hooks from the configuration
     * 2. Check if each feature is enabled
     * 3. Register each hook for enabled features
     *
     * @since    1.0.0
     * @return   void
     */
    public function register_hooks(): void {
        foreach ($this->hook_config->get_hooks() as $feature => $hooks) {
            // Skip if feature is not enabled
            if (!$this->hook_config->is_feature_enabled($feature)) {
                continue;
            }

            foreach ($hooks as $hook => $config) {
                $this->register_hook($feature, $hook, $config);
            }
        }
    }

    /**
     * Register a single hook.
     *
     * This method registers a single WordPress hook with the specified configuration.
     * It creates an anonymous function that wraps the actual callback to provide
     * additional functionality:
     *
     * - Sampling: Only execute the callback based on the configured sampling rate
     * - Context passing: Pass feature, hook, and configuration to the callback
     * - Return value handling: Preserve the original return value when needed
     *
     * The method uses WordPress's add_filter function, which works for both actions
     * and filters. For actions, the return value is ignored by WordPress.
     *
     * @since    1.0.0
     * @access   private
     * @param    string    $feature    The feature this hook belongs to (e.g., 'core_monitoring').
     * @param    string    $hook       The name of the WordPress hook (e.g., 'init', 'wp_loaded').
     * @param    array     $config     The hook configuration with keys:
     *                                 - callback: The method name to call
     *                                 - priority: The hook priority (default: 10)
     *                                 - args: Number of arguments the hook accepts (default: 1)
     *                                 - sampling_rate: Rate from 0.0 to 1.0 (default: 1.0)
     * @return   void
     */
    private function register_hook(string $feature, string $hook, array $config): void {
        $callback = [$this, $config['callback']];
        $priority = isset($config['priority']) ? $config['priority'] : 10;
        $args = isset($config['args']) ? $config['args'] : 1;

        add_filter($hook, function() use ($callback, $config, $feature, $hook) {
            $args = func_get_args();

            // Check if this event should be sampled
            $sampling_rate = isset($config['sampling_rate']) ? $config['sampling_rate'] : 1.0;
            if (!$this->sampling_manager->should_sample($feature, $hook, $sampling_rate)) {
                // Return the original first argument if it exists, or an empty string to avoid null
                return isset($args[0]) ? $args[0] : '';
            }

            // Call the actual callback
            return call_user_func_array($callback, array_merge($args, [$feature, $hook, $config]));
        }, $priority, $args);
    }

    /**
     * Capture plugins loaded event.
     *
     * @since    1.0.0
     * @param    string    $feature    The feature this hook belongs to.
     * @param    string    $hook       The name of the WordPress hook.
     * @param    array     $config     The hook configuration.
     * @return   mixed                 The original return value.
     */
    public function capture_plugins_loaded($feature, $hook, $config) {
        $data = [
            'loaded_plugins' => $this->get_loaded_plugins(),
            'timestamp' => microtime(true),
        ];

        $this->data_capture->capture($feature, $hook, $data);

        return null;
    }

    /**
     * Capture init event.
     *
     * @since    1.0.0
     * @param    string    $feature    The feature this hook belongs to.
     * @param    string    $hook       The name of the WordPress hook.
     * @param    array     $config     The hook configuration.
     * @return   mixed                 The original return value.
     */
    public function capture_init($feature, $hook, $config) {
        $data = [
            'timestamp' => microtime(true),
            'memory_usage' => memory_get_usage(),
        ];

        $this->data_capture->capture($feature, $hook, $data);

        return null;
    }

    /**
     * Capture wp_loaded event.
     *
     * @since    1.0.0
     * @param    string    $feature    The feature this hook belongs to.
     * @param    string    $hook       The name of the WordPress hook.
     * @param    array     $config     The hook configuration.
     * @return   mixed                 The original return value.
     */
    public function capture_wp_loaded($feature, $hook, $config) {
        $data = [
            'timestamp' => microtime(true),
            'memory_usage' => memory_get_usage(),
            'loaded_scripts' => $this->get_loaded_scripts(),
            'loaded_styles' => $this->get_loaded_styles(),
        ];

        $this->data_capture->capture($feature, $hook, $data);

        return null;
    }

    /**
     * Capture database query event.
     *
     * @since    1.0.0
     * @param    string    $query      The database query.
     * @param    string    $feature    The feature this hook belongs to.
     * @param    string    $hook       The name of the WordPress hook.
     * @param    array     $config     The hook configuration.
     * @return   string                The original query.
     */
    public function capture_db_query($query, $feature, $hook, $config) {
        $data = [
            'query' => $query,
            'timestamp' => microtime(true),
        ];

        $this->data_capture->capture($feature, $hook, $data);

        return $query;
    }

    /**
     * Capture activated plugin event.
     *
     * @since    1.0.0
     * @param    string    $plugin     The activated plugin.
     * @param    string    $feature    The feature this hook belongs to.
     * @param    string    $hook       The name of the WordPress hook.
     * @param    array     $config     The hook configuration.
     * @return   string                The original plugin.
     */
    public function capture_activated_plugin($plugin, $feature, $hook, $config) {
        $data = [
            'plugin' => $plugin,
            'timestamp' => microtime(true),
        ];

        $this->data_capture->capture($feature, $hook, $data);

        return $plugin;
    }

    /**
     * Capture deactivated plugin event.
     *
     * @since    1.0.0
     * @param    string    $plugin     The deactivated plugin.
     * @param    string    $feature    The feature this hook belongs to.
     * @param    string    $hook       The name of the WordPress hook.
     * @param    array     $config     The hook configuration.
     * @return   string                The original plugin.
     */
    public function capture_deactivated_plugin($plugin, $feature, $hook, $config) {
        $data = [
            'plugin' => $plugin,
            'timestamp' => microtime(true),
        ];

        $this->data_capture->capture($feature, $hook, $data);

        return $plugin;
    }

    /**
     * Capture performance metrics.
     *
     * @since    1.0.0
     * @param    string    $feature    The feature this hook belongs to.
     * @param    string    $hook       The name of the WordPress hook.
     * @param    array     $config     The hook configuration.
     * @return   mixed                 The original return value.
     */
    public function capture_performance_metrics($feature, $hook, $config) {
        $data = [
            'memory_usage' => memory_get_usage(),
            'peak_memory_usage' => memory_get_peak_usage(),
            'execution_time' => microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'],
            'timestamp' => microtime(true),
        ];

        $this->data_capture->capture($feature, $hook, $data);

        return null;
    }

    /**
     * Get loaded plugins.
     *
     * @since    1.0.0
     * @access   private
     * @return   array    The array of loaded plugins.
     */
    private function get_loaded_plugins() {
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $all_plugins = get_plugins();
        $active_plugins = get_option('active_plugins', []);

        $loaded_plugins = [];
        foreach ($active_plugins as $plugin) {
            if (isset($all_plugins[$plugin])) {
                $loaded_plugins[$plugin] = $all_plugins[$plugin];
            }
        }

        return $loaded_plugins;
    }

    /**
     * Get loaded scripts.
     *
     * @since    1.0.0
     * @access   private
     * @return   array    The array of loaded scripts.
     */
    private function get_loaded_scripts() {
        global $wp_scripts;

        if (!$wp_scripts) {
            return [];
        }

        $loaded_scripts = [];
        foreach ($wp_scripts->queue as $handle) {
            if (isset($wp_scripts->registered[$handle])) {
                $loaded_scripts[$handle] = [
                    'src' => $wp_scripts->registered[$handle]->src,
                    'deps' => $wp_scripts->registered[$handle]->deps,
                    'ver' => $wp_scripts->registered[$handle]->ver,
                ];
            }
        }

        return $loaded_scripts;
    }

    /**
     * Get loaded styles.
     *
     * @since    1.0.0
     * @access   private
     * @return   array    The array of loaded styles.
     */
    private function get_loaded_styles() {
        global $wp_styles;

        if (!$wp_styles) {
            return [];
        }

        $loaded_styles = [];
        foreach ($wp_styles->queue as $handle) {
            if (isset($wp_styles->registered[$handle])) {
                $loaded_styles[$handle] = [
                    'src' => $wp_styles->registered[$handle]->src,
                    'deps' => $wp_styles->registered[$handle]->deps,
                    'ver' => $wp_styles->registered[$handle]->ver,
                ];
            }
        }

        return $loaded_styles;
    }
}
