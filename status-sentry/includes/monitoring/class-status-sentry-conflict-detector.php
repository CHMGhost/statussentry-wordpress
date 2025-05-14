<?php
/**
 * Conflict Detector Class
 *
 * @since      1.3.0
 * @package    Status_Sentry
 * @subpackage Status_Sentry/includes/monitoring
 */

/**
 * Conflict Detector Class
 *
 * This class is responsible for detecting conflicts between plugins and themes.
 * It monitors for abnormal behavior patterns that may indicate conflicts and
 * reports them through the monitoring system.
 *
 * Key responsibilities:
 * - Monitor plugin and theme activations/deactivations
 * - Compare performance metrics against baselines
 * - Detect abnormal behavior patterns
 * - Report potential conflicts through the monitoring system
 * - Provide conflict resolution suggestions
 *
 * @since      1.3.0
 * @package    Status_Sentry
 * @subpackage Status_Sentry/includes/monitoring
 */
class Status_Sentry_Conflict_Detector implements Status_Sentry_Monitoring_Interface, Status_Sentry_Monitoring_Handler_Interface {

    /**
     * The baseline instance.
     *
     * @since    1.3.0
     * @access   private
     * @var      Status_Sentry_Baseline    $baseline    The baseline instance.
     */
    private $baseline;

    /**
     * Known conflict patterns.
     *
     * @since    1.3.0
     * @access   private
     * @var      array    $conflict_patterns    Known conflict patterns.
     */
    private $conflict_patterns = [];

    /**
     * Configuration options.
     *
     * @since    1.3.0
     * @access   private
     * @var      array    $config    Configuration options.
     */
    private $config = [
        'enabled' => true,
        'detection_threshold' => 0.5, // 50% deviation from baseline
        'monitor_plugin_activation' => true,
        'monitor_theme_switching' => true,
        'monitor_performance_metrics' => true,
        'monitor_error_rates' => true,
    ];

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.3.0
     */
    public function __construct() {
        $this->baseline = new Status_Sentry_Baseline();
        $this->load_conflict_patterns();
    }

    /**
     * Initialize the monitoring component.
     *
     * @since    1.3.0
     * @return   void
     */
    public function init() {
        // Register hooks for plugin activation/deactivation
        if ($this->config['monitor_plugin_activation']) {
            // Remove the old hooks that emit INFO events for every activation/deactivation
            // add_action('activated_plugin', [$this, 'on_plugin_activated'], 10, 1);
            // add_action('deactivated_plugin', [$this, 'on_plugin_deactivated'], 10, 1);

            // Add new hooks for taking snapshots before and after plugin activation
            add_action('activate_plugin', [$this, 'pre_plugin_activation'], 10, 1);
            add_action('activated_plugin', [$this, 'post_plugin_activation'], 20, 1);
        }

        // Register hooks for theme switching
        if ($this->config['monitor_theme_switching']) {
            add_action('switch_theme', [$this, 'on_theme_switched'], 10, 3);
        }

        // Load saved configuration
        $saved_config = get_option('status_sentry_conflict_detector_config', []);
        if (!empty($saved_config)) {
            $this->config = array_merge($this->config, $saved_config);
        }
    }

    /**
     * Register event handlers with the monitoring manager.
     *
     * @since    1.3.0
     * @param    Status_Sentry_Monitoring_Manager    $manager    The monitoring manager instance.
     * @return   void
     */
    public function register_handlers($manager) {
        $manager->register_handler($this);
    }

    /**
     * Get the handler's priority.
     *
     * @since    1.3.0
     * @return   int    The handler's priority (0-100).
     */
    public function get_priority() {
        return 80; // High priority
    }

    /**
     * Get the event types this handler can process.
     *
     * @since    1.3.0
     * @return   array    An array of event types.
     */
    public function get_handled_types() {
        return [
            Status_Sentry_Monitoring_Event::TYPE_PERFORMANCE,
            Status_Sentry_Monitoring_Event::TYPE_ERROR,
            Status_Sentry_Monitoring_Event::TYPE_WARNING,
        ];
    }

    /**
     * Check if this handler can handle the given event.
     *
     * @since    1.3.0
     * @param    Status_Sentry_Monitoring_Event    $event    The event to check.
     * @return   bool                                        Whether this handler can handle the event.
     */
    public function can_handle($event) {
        // Only handle events if conflict detection is enabled
        if (!$this->config['enabled']) {
            return false;
        }

        // Check if this is a performance event and we're monitoring performance
        if ($event->get_type() === Status_Sentry_Monitoring_Event::TYPE_PERFORMANCE && !$this->config['monitor_performance_metrics']) {
            return false;
        }

        // Check if this is an error event and we're monitoring errors
        if (($event->get_type() === Status_Sentry_Monitoring_Event::TYPE_ERROR || $event->get_type() === Status_Sentry_Monitoring_Event::TYPE_WARNING) && !$this->config['monitor_error_rates']) {
            return false;
        }

        return true;
    }

    /**
     * Handle a monitoring event.
     *
     * @since    1.3.0
     * @param    Status_Sentry_Monitoring_Event    $event    The event to handle.
     * @return   bool                                        Whether the event was successfully handled.
     */
    public function handle($event) {
        switch ($event->get_type()) {
            case Status_Sentry_Monitoring_Event::TYPE_PERFORMANCE:
                return $this->handle_performance_event($event);

            case Status_Sentry_Monitoring_Event::TYPE_ERROR:
            case Status_Sentry_Monitoring_Event::TYPE_WARNING:
                return $this->handle_error_event($event);

            default:
                return false;
        }
    }

    /**
     * Handle a performance event.
     *
     * @since    1.3.0
     * @access   private
     * @param    Status_Sentry_Monitoring_Event    $event    The event to handle.
     * @return   bool                                        Whether the event was successfully handled.
     */
    private function handle_performance_event($event) {
        $data = $event->get_data();

        // Check if this event has performance metrics
        if (!isset($data['metric_name']) || !isset($data['metric_value'])) {
            return false;
        }

        $metric_name = $data['metric_name'];
        $metric_value = $data['metric_value'];
        $metric_context = $data['metric_context'] ?? 'default';

        // Check if this metric has a baseline
        $baseline = $this->baseline->get_baseline($metric_name, $metric_context);
        if (!$baseline) {
            // No baseline yet, nothing to compare against
            return false;
        }

        // Check if the value deviates significantly from the baseline
        if ($this->baseline->is_significant_deviation($metric_name, $metric_context, $metric_value, $this->config['detection_threshold'])) {
            // This is a significant deviation, check for conflict patterns
            $conflicts = $this->check_conflict_patterns($metric_name, $metric_value, $baseline['value']);

            if (!empty($conflicts)) {
                // We found potential conflicts, emit a conflict event
                $this->emit_conflict_event(
                    'performance_deviation',
                    sprintf('Significant performance deviation detected for %s', $metric_name),
                    [
                        'metric_name' => $metric_name,
                        'metric_context' => $metric_context,
                        'current_value' => $metric_value,
                        'baseline_value' => $baseline['value'],
                        'deviation_percent' => (($metric_value - $baseline['value']) / $baseline['value']) * 100,
                        'potential_conflicts' => $conflicts,
                    ]
                );

                return true;
            }
        }

        return false;
    }

    /**
     * Handle an error event.
     *
     * @since    1.3.0
     * @access   private
     * @param    Status_Sentry_Monitoring_Event    $event    The event to handle.
     * @return   bool                                        Whether the event was successfully handled.
     */
    private function handle_error_event($event) {
        $data = $event->get_data();

        // Check if this error matches any known conflict patterns
        $conflicts = $this->check_error_conflict_patterns($event->get_message(), $data);

        if (!empty($conflicts)) {
            // We found potential conflicts, emit a conflict event
            $this->emit_conflict_event(
                'error_pattern',
                'Error pattern matches known plugin conflict',
                [
                    'error_message' => $event->get_message(),
                    'error_data' => $data,
                    'potential_conflicts' => $conflicts,
                ]
            );

            return true;
        }

        return false;
    }

    /**
     * Process a monitoring event.
     *
     * @since    1.3.0
     * @param    Status_Sentry_Monitoring_Event    $event    The monitoring event to process.
     * @return   void
     */
    public function process_event($event) {
        // This method is required by the Monitoring_Interface but is not used
        // since we implement the Handler interface directly
    }

    /**
     * Get the monitoring component's status.
     *
     * @since    1.3.0
     * @return   array    The component status as an associative array.
     */
    public function get_status() {
        return [
            'enabled' => $this->config['enabled'],
            'conflict_patterns_loaded' => count($this->conflict_patterns),
            'detection_threshold' => $this->config['detection_threshold'],
        ];
    }

    /**
     * Get the monitoring component's configuration.
     *
     * @since    1.3.0
     * @return   array    The component configuration as an associative array.
     */
    public function get_config() {
        return $this->config;
    }

    /**
     * Update the monitoring component's configuration.
     *
     * @since    1.3.0
     * @param    array    $config    The new configuration as an associative array.
     * @return   bool                Whether the configuration was successfully updated.
     */
    public function update_config($config) {
        // Validate configuration
        if (isset($config['enabled'])) {
            $this->config['enabled'] = (bool)$config['enabled'];
        }

        if (isset($config['detection_threshold'])) {
            $threshold = floatval($config['detection_threshold']);
            if ($threshold >= 0.1 && $threshold <= 1.0) {
                $this->config['detection_threshold'] = $threshold;
            }
        }

        if (isset($config['monitor_plugin_activation'])) {
            $this->config['monitor_plugin_activation'] = (bool)$config['monitor_plugin_activation'];
        }

        if (isset($config['monitor_theme_switching'])) {
            $this->config['monitor_theme_switching'] = (bool)$config['monitor_theme_switching'];
        }

        if (isset($config['monitor_performance_metrics'])) {
            $this->config['monitor_performance_metrics'] = (bool)$config['monitor_performance_metrics'];
        }

        if (isset($config['monitor_error_rates'])) {
            $this->config['monitor_error_rates'] = (bool)$config['monitor_error_rates'];
        }

        // Save configuration
        update_option('status_sentry_conflict_detector_config', $this->config);

        return true;
    }

    /**
     * Load known conflict patterns.
     *
     * @since    1.3.0
     * @access   private
     * @return   void
     */
    private function load_conflict_patterns() {
        // Load built-in patterns
        $this->conflict_patterns = [
            'performance' => [
                [
                    'metric' => 'memory_usage',
                    'pattern' => 'increase',
                    'threshold' => 50, // 50% increase
                    'conflicts' => [
                        ['plugin' => 'woocommerce', 'plugin' => 'jetpack'],
                        ['plugin' => 'elementor', 'plugin' => 'wp-rocket'],
                    ],
                ],
                [
                    'metric' => 'execution_time',
                    'pattern' => 'increase',
                    'threshold' => 100, // 100% increase (2x slower)
                    'conflicts' => [
                        ['plugin' => 'wordfence', 'plugin' => 'wp-optimize'],
                        ['plugin' => 'yoast-seo', 'plugin' => 'all-in-one-seo-pack'],
                    ],
                ],
            ],
            'errors' => [
                [
                    'pattern' => '/Call to undefined function jetpack_/',
                    'conflicts' => [
                        ['plugin' => 'jetpack', 'plugin' => 'various'],
                    ],
                ],
                [
                    'pattern' => '/Uncaught Error: Call to undefined method WC_/',
                    'conflicts' => [
                        ['plugin' => 'woocommerce', 'plugin' => 'various'],
                    ],
                ],
            ],
        ];

        // Allow plugins to add their own patterns
        $this->conflict_patterns = apply_filters('status_sentry_conflict_patterns', $this->conflict_patterns);
    }

    /**
     * Check if a metric matches any known conflict patterns.
     *
     * @since    1.3.0
     * @access   private
     * @param    string    $metric_name     The metric name.
     * @param    float     $current_value   The current metric value.
     * @param    float     $baseline_value  The baseline metric value.
     * @return   array                      An array of potential conflicts.
     */
    private function check_conflict_patterns($metric_name, $current_value, $baseline_value) {
        $conflicts = [];

        // Calculate the percent change
        $percent_change = (($current_value - $baseline_value) / $baseline_value) * 100;

        // Check performance patterns
        foreach ($this->conflict_patterns['performance'] as $pattern) {
            if ($pattern['metric'] === $metric_name) {
                if ($pattern['pattern'] === 'increase' && $percent_change >= $pattern['threshold']) {
                    $conflicts = array_merge($conflicts, $pattern['conflicts']);
                } elseif ($pattern['pattern'] === 'decrease' && $percent_change <= -$pattern['threshold']) {
                    $conflicts = array_merge($conflicts, $pattern['conflicts']);
                }
            }
        }

        return $conflicts;
    }

    /**
     * Check if an error message matches any known conflict patterns.
     *
     * @since    1.3.0
     * @access   private
     * @param    string    $error_message   The error message.
     * @param    array     $error_data      The error data.
     * @return   array                      An array of potential conflicts.
     */
    private function check_error_conflict_patterns($error_message, $error_data) {
        $conflicts = [];

        // Check error patterns
        foreach ($this->conflict_patterns['errors'] as $pattern) {
            if (preg_match($pattern['pattern'], $error_message)) {
                $conflicts = array_merge($conflicts, $pattern['conflicts']);
            }
        }

        return $conflicts;
    }

    /**
     * Emit a conflict event.
     *
     * @since    1.3.0
     * @access   private
     * @param    string    $context     The conflict context.
     * @param    string    $message     The conflict message.
     * @param    array     $data        The conflict data.
     * @return   void
     */
    private function emit_conflict_event($context, $message, $data) {
        $manager = Status_Sentry_Monitoring_Manager::get_instance();

        $manager->emit(
            Status_Sentry_Monitoring_Event::TYPE_CONFLICT,
            'conflict_detector',
            $context,
            $message,
            $data,
            Status_Sentry_Monitoring_Event::PRIORITY_HIGH
        );
    }

    /**
     * Take a snapshot before plugin activation.
     *
     * @since    1.6.0
     * @param    string    $plugin    The plugin being activated.
     * @return   void
     */
    public function pre_plugin_activation($plugin) {
        // Take a snapshot of the system state before plugin activation
        $this->baseline->snapshot_before($plugin);
    }

    /**
     * Check for conflicts after plugin activation.
     *
     * @since    1.6.0
     * @param    string    $plugin    The plugin that was activated.
     * @return   void
     */
    public function post_plugin_activation($plugin) {
        // Take a snapshot of the system state after plugin activation
        $this->baseline->snapshot_after($plugin);

        // Get the plugin name for snapshot comparison
        $plugin_name = basename($plugin, '.php');

        // Compare the before and after snapshots to detect conflicts
        $conflicts = $this->baseline->diff('before_' . $plugin_name, 'after_' . $plugin_name);

        // If conflicts were detected, emit a conflict event
        if (!empty($conflicts)) {
            $this->emit_conflict_event(
                'plugin_conflict',
                sprintf('Conflict detected on activation of %s', $plugin),
                [
                    'plugin' => $plugin,
                    'active_plugins' => get_option('active_plugins'),
                    'conflicts' => $conflicts,
                ]
            );
        }
    }

    /**
     * Handle plugin deactivation.
     *
     * @since    1.3.0
     * @param    string    $plugin    The deactivated plugin.
     * @return   void
     */
    public function on_plugin_deactivated($plugin) {
        // This method is kept for backward compatibility but is no longer used
        // Plugin deactivation events are now only emitted if actual conflicts are detected
    }

    /**
     * Handle theme switching.
     *
     * @since    1.3.0
     * @param    string    $new_name    The new theme name.
     * @param    WP_Theme  $new_theme   The new theme object.
     * @param    WP_Theme  $old_theme   The old theme object.
     * @return   void
     */
    public function on_theme_switched($new_name, $new_theme, $old_theme) {
        // Record the theme switch
        $manager = Status_Sentry_Monitoring_Manager::get_instance();

        $manager->emit(
            Status_Sentry_Monitoring_Event::TYPE_INFO,
            'conflict_detector',
            'theme_switched',
            sprintf('Theme switched from %s to %s', $old_theme->get('Name'), $new_name),
            [
                'new_theme' => [
                    'name' => $new_name,
                    'version' => $new_theme->get('Version'),
                ],
                'old_theme' => [
                    'name' => $old_theme->get('Name'),
                    'version' => $old_theme->get('Version'),
                ],
            ],
            Status_Sentry_Monitoring_Event::PRIORITY_NORMAL
        );
    }
}
