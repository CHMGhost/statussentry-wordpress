<?php
/**
 * Config Manager Class
 *
 * This class manages preset configurations for Status Sentry monitoring components.
 *
 * @since      1.5.0
 * @package    Status_Sentry
 * @subpackage Status_Sentry/includes/monitoring
 */

/**
 * Config Manager Class
 *
 * This class defines and manages preset configurations for all monitoring components.
 * It provides methods to get and set the selected preset, and to retrieve the
 * configuration for a specific preset.
 *
 * @since      1.5.0
 * @package    Status_Sentry
 * @subpackage Status_Sentry/includes/monitoring
 */
class Status_Sentry_Config_Manager {

    /**
     * The single instance of the class.
     *
     * @since    1.5.0
     * @access   private
     * @var      Status_Sentry_Config_Manager    $instance    The single instance of the class.
     */
    private static $instance = null;

    /**
     * The preset definitions.
     *
     * @since    1.5.0
     * @access   private
     * @var      array    $presets    The preset definitions.
     */
    private $presets;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.5.0
     */
    private function __construct() {
        $this->define_presets();
    }

    /**
     * Get the single instance of the class.
     *
     * @since    1.5.0
     * @return   Status_Sentry_Config_Manager    The single instance of the class.
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Define the preset configurations.
     *
     * @since    1.5.0
     * @access   private
     */
    private function define_presets() {
        $this->presets = [
            'basic' => [
                'self_monitor' => [
                    'enabled' => true,
                    'track_task_execution' => true,
                    'track_resource_usage' => false,
                    'track_error_rates' => true,
                    'retention_days' => 7,
                ],
                'resource_manager' => [
                    'enabled' => true,
                    'monitor_memory' => true,
                    'monitor_cpu' => false,
                    'enforce_budgets' => false,
                    'auto_gc' => true,
                ],
                'task_state_manager' => [
                    'enabled' => true,
                    'retention_days' => 7,
                ],
                'conflict_detector' => [
                    'enabled' => true,
                    'monitor_plugin_activation' => true,
                    'monitor_theme_switching' => true,
                    'monitor_plugin_updates' => false,
                    'monitor_core_updates' => false,
                ],
                'cron_logger' => [
                    'enabled' => true,
                    'retention_days' => 7,
                ],
                'health_checker' => [
                    'enabled' => true,
                    'check_interval' => 'hourly',
                ],
                // General settings
                'settings' => [
                    'core_monitoring' => 1,
                    'db_monitoring' => 0,
                    'conflict_detection' => 1,
                    'performance_monitoring' => 0,
                    'db_batch_size' => 50,
                    'memory_threshold' => 80,
                    'gc_cycles' => 2,
                    'cpu_threshold' => 80,
                    'enable_query_cache' => 0,
                    'query_cache_ttl' => 1800,
                    'enable_resumable_tasks' => 0,
                    'events_retention_days' => 7,
                    'processed_queue_retention_days' => 3,
                    'failed_queue_retention_days' => 7,
                    'task_runs_retention_days' => 7,
                ],
            ],
            'balanced' => [
                'self_monitor' => [
                    'enabled' => true,
                    'track_task_execution' => true,
                    'track_resource_usage' => true,
                    'track_error_rates' => true,
                    'retention_days' => 14,
                ],
                'resource_manager' => [
                    'enabled' => true,
                    'monitor_memory' => true,
                    'monitor_cpu' => true,
                    'enforce_budgets' => true,
                    'auto_gc' => true,
                ],
                'task_state_manager' => [
                    'enabled' => true,
                    'retention_days' => 14,
                ],
                'conflict_detector' => [
                    'enabled' => true,
                    'monitor_plugin_activation' => true,
                    'monitor_theme_switching' => true,
                    'monitor_plugin_updates' => true,
                    'monitor_core_updates' => true,
                ],
                'cron_logger' => [
                    'enabled' => true,
                    'retention_days' => 14,
                ],
                'health_checker' => [
                    'enabled' => true,
                    'check_interval' => 'thirty_minutes',
                ],
                // General settings
                'settings' => [
                    'core_monitoring' => 1,
                    'db_monitoring' => 1,
                    'conflict_detection' => 1,
                    'performance_monitoring' => 1,
                    'db_batch_size' => 100,
                    'memory_threshold' => 80,
                    'gc_cycles' => 3,
                    'cpu_threshold' => 70,
                    'enable_query_cache' => 1,
                    'query_cache_ttl' => 3600,
                    'enable_resumable_tasks' => 1,
                    'events_retention_days' => 30,
                    'processed_queue_retention_days' => 7,
                    'failed_queue_retention_days' => 14,
                    'task_runs_retention_days' => 30,
                ],
            ],
            'comprehensive' => [
                'self_monitor' => [
                    'enabled' => true,
                    'track_task_execution' => true,
                    'track_resource_usage' => true,
                    'track_error_rates' => true,
                    'retention_days' => 30,
                ],
                'resource_manager' => [
                    'enabled' => true,
                    'monitor_memory' => true,
                    'monitor_cpu' => true,
                    'enforce_budgets' => true,
                    'auto_gc' => true,
                ],
                'task_state_manager' => [
                    'enabled' => true,
                    'retention_days' => 30,
                ],
                'conflict_detector' => [
                    'enabled' => true,
                    'monitor_plugin_activation' => true,
                    'monitor_theme_switching' => true,
                    'monitor_plugin_updates' => true,
                    'monitor_core_updates' => true,
                ],
                'cron_logger' => [
                    'enabled' => true,
                    'retention_days' => 30,
                ],
                'health_checker' => [
                    'enabled' => true,
                    'check_interval' => 'fifteen_minutes',
                ],
                // General settings
                'settings' => [
                    'core_monitoring' => 1,
                    'db_monitoring' => 1,
                    'conflict_detection' => 1,
                    'performance_monitoring' => 1,
                    'db_batch_size' => 200,
                    'memory_threshold' => 75,
                    'gc_cycles' => 5,
                    'cpu_threshold' => 60,
                    'enable_query_cache' => 1,
                    'query_cache_ttl' => 7200,
                    'enable_resumable_tasks' => 1,
                    'events_retention_days' => 90,
                    'processed_queue_retention_days' => 14,
                    'failed_queue_retention_days' => 30,
                    'task_runs_retention_days' => 90,
                ],
            ],
            'custom' => [
                // Custom preset is empty by default, as it uses the user's custom settings
            ],
        ];

        // Allow developers to filter preset definitions
        $this->presets = apply_filters('status_sentry_preset_definitions', $this->presets);
    }

    /**
     * Get the preset configuration.
     *
     * @since    1.5.0
     * @param    string    $preset_key    The preset key.
     * @return   array                    The preset configuration.
     */
    public function get_preset_config($preset_key = null) {
        // If no preset key is provided, get the selected preset
        if (null === $preset_key) {
            $preset_key = $this->get_selected_preset();
        }

        // If the preset doesn't exist, use the balanced preset
        if (!isset($this->presets[$preset_key])) {
            $preset_key = 'balanced';
        }

        return $this->presets[$preset_key];
    }

    /**
     * Get the selected preset.
     *
     * @since    1.5.0
     * @return   string    The selected preset key.
     */
    public function get_selected_preset() {
        return get_option('status_sentry_preset', 'balanced');
    }

    /**
     * Set the selected preset.
     *
     * @since    1.5.0
     * @param    string    $preset_key    The preset key.
     * @return   bool                     Whether the preset was successfully set.
     */
    public function set_selected_preset($preset_key) {
        // If the preset doesn't exist, use the balanced preset
        if (!isset($this->presets[$preset_key])) {
            $preset_key = 'balanced';
        }

        // Update the option
        $result = update_option('status_sentry_preset', $preset_key);

        // Fire an action when a preset is selected
        if ($result) {
            do_action('status_sentry_preset_selected', $preset_key);
        }

        return $result;
    }

    /**
     * Apply the selected preset configuration to all components.
     *
     * @since    1.5.0
     * @param    string    $preset_key    The preset key.
     * @return   bool                     Whether the preset was successfully applied.
     */
    public function apply_preset($preset_key = null) {
        // If no preset key is provided, get the selected preset
        if (null === $preset_key) {
            $preset_key = $this->get_selected_preset();
        }

        // Get the preset configuration
        $config = $this->get_preset_config($preset_key);

        // If the preset is custom, don't apply any changes
        if ('custom' === $preset_key) {
            return true;
        }

        // Update component configurations
        foreach ($config as $component => $component_config) {
            if ($component === 'settings') {
                // Update general settings
                update_option('status_sentry_settings', $component_config);
            } else {
                // Update component-specific settings
                update_option("status_sentry_{$component}_config", $component_config);
            }
        }

        // Update the selected preset
        $this->set_selected_preset($preset_key);

        return true;
    }
}
