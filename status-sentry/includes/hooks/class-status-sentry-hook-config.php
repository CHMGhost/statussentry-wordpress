<?php
/**
 * Hook configuration class.
 *
 * @since      1.0.0
 * @package    Status_Sentry
 * @subpackage Status_Sentry/includes/hooks
 */

/**
 * Hook configuration class.
 *
 * This class defines and stores hook configurations.
 *
 * @since      1.0.0
 * @package    Status_Sentry
 * @subpackage Status_Sentry/includes/hooks
 */
class Status_Sentry_Hook_Config {

    /**
     * The array of hook definitions.
     *
     * @since    1.0.0
     * @access   private
     * @var      array    $hooks    The array of hook definitions.
     */
    private $hooks = [];

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     */
    public function __construct() {
        $this->define_hooks();
    }

    /**
     * Define the hooks to be registered.
     *
     * @since    1.0.0
     * @access   private
     */
    private function define_hooks() {
        // Core monitoring hooks
        $this->add_hook('core_monitoring', 'plugins_loaded', [
            'callback' => 'capture_plugins_loaded',
            'priority' => 999,
            'args' => 0,
            'sampling_rate' => 1.0, // 100% sampling
            'group' => 'core',
        ]);

        $this->add_hook('core_monitoring', 'init', [
            'callback' => 'capture_init',
            'priority' => 999,
            'args' => 0,
            'sampling_rate' => 0.1, // 10% sampling
            'group' => 'core',
        ]);

        $this->add_hook('core_monitoring', 'wp_loaded', [
            'callback' => 'capture_wp_loaded',
            'priority' => 999,
            'args' => 0,
            'sampling_rate' => 0.1, // 10% sampling
            'group' => 'core',
        ]);

        // Database monitoring hooks
        $this->add_hook('db_monitoring', 'query', [
            'callback' => 'capture_db_query',
            'priority' => 999,
            'args' => 1,
            'sampling_rate' => 0.05, // 5% sampling
            'group' => 'database',
        ]);

        // Plugin conflict detection hooks
        $this->add_hook('conflict_detection', 'activated_plugin', [
            'callback' => 'capture_activated_plugin',
            'priority' => 10,
            'args' => 1,
            'sampling_rate' => 1.0, // 100% sampling
            'group' => 'plugins',
        ]);

        $this->add_hook('conflict_detection', 'deactivated_plugin', [
            'callback' => 'capture_deactivated_plugin',
            'priority' => 10,
            'args' => 1,
            'sampling_rate' => 1.0, // 100% sampling
            'group' => 'plugins',
        ]);

        // Performance monitoring hooks
        $this->add_hook('performance_monitoring', 'shutdown', [
            'callback' => 'capture_performance_metrics',
            'priority' => 0, // Very high priority (runs first)
            'args' => 0,
            'sampling_rate' => 0.1, // 10% sampling
            'group' => 'performance',
        ]);
    }

    /**
     * Add a hook definition.
     *
     * @since    1.0.0
     * @access   private
     * @param    string    $feature     The feature this hook belongs to.
     * @param    string    $hook        The name of the WordPress hook.
     * @param    array     $config      The hook configuration.
     */
    private function add_hook($feature, $hook, $config) {
        if (!isset($this->hooks[$feature])) {
            $this->hooks[$feature] = [];
        }
        
        $this->hooks[$feature][$hook] = $config;
    }

    /**
     * Get all hook definitions.
     *
     * @since    1.0.0
     * @return   array    The array of hook definitions.
     */
    public function get_hooks() {
        return $this->hooks;
    }

    /**
     * Get hook definitions for a specific feature.
     *
     * @since    1.0.0
     * @param    string    $feature    The feature to get hooks for.
     * @return   array                 The array of hook definitions for the feature.
     */
    public function get_feature_hooks($feature) {
        return isset($this->hooks[$feature]) ? $this->hooks[$feature] : [];
    }

    /**
     * Check if a feature is enabled.
     *
     * @since    1.0.0
     * @param    string    $feature    The feature to check.
     * @return   bool                  Whether the feature is enabled.
     */
    public function is_feature_enabled($feature) {
        // In a real implementation, this would check options or other configuration
        $enabled_features = [
            'core_monitoring' => true,
            'db_monitoring' => true,
            'conflict_detection' => true,
            'performance_monitoring' => true,
        ];
        
        return isset($enabled_features[$feature]) ? $enabled_features[$feature] : false;
    }
}
