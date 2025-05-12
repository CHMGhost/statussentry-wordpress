<?php
/**
 * Baseline Monitoring Component
 *
 * @since      1.3.0
 * @package    Status_Sentry
 * @subpackage Status_Sentry/includes/monitoring
 */

/**
 * Baseline Monitoring Component
 *
 * This class implements the baseline monitoring component for Status Sentry.
 * It establishes performance baselines and tracks deviations from those baselines.
 *
 * Key responsibilities:
 * - Establish performance baselines for various operations
 * - Track deviations from established baselines
 * - Alert when significant deviations are detected
 * - Provide baseline data for other monitoring components
 *
 * @since      1.3.0
 * @package    Status_Sentry
 * @subpackage Status_Sentry/includes/monitoring
 */
class Status_Sentry_Baseline implements Status_Sentry_Monitoring_Interface {

    /**
     * The baseline data table name.
     *
     * @since    1.3.0
     * @access   private
     * @var      string    $table_name    The table name.
     */
    private $table_name;

    /**
     * Configuration options for the baseline component.
     *
     * @since    1.3.0
     * @access   private
     * @var      array    $config    Configuration options.
     */
    private $config;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.3.0
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'status_sentry_baselines';

        // Default configuration
        $this->config = [
            'enabled' => true,
            'deviation_threshold' => 0.2, // 20% deviation threshold
            'min_samples' => 5, // Minimum samples needed to establish a baseline
            'max_samples' => 100, // Maximum samples to keep for a baseline
            'auto_update' => true, // Automatically update baselines
        ];

        // Load saved configuration
        $saved_config = get_option('status_sentry_baseline_config', []);
        if (!empty($saved_config)) {
            $this->config = array_merge($this->config, $saved_config);
        }

        // Ensure the baseline table exists
        $this->ensure_table_exists();
    }

    /**
     * Initialize the monitoring component.
     *
     * @since    1.3.0
     * @return   void
     */
    public function init() {
        // Nothing to initialize here, as the constructor already sets up everything
    }

    /**
     * Register event handlers with the monitoring manager.
     *
     * @since    1.3.0
     * @param    Status_Sentry_Monitoring_Manager    $manager    The monitoring manager instance.
     * @return   void
     */
    public function register_handlers($manager) {
        // Register handlers for performance events
        $manager->register_handler('performance', [$this, 'process_event']);
        $manager->register_handler('resource_usage', [$this, 'process_event']);
    }

    /**
     * Process a monitoring event.
     *
     * @since    1.3.0
     * @param    Status_Sentry_Monitoring_Event    $event    The monitoring event to process.
     * @return   void
     */
    public function process_event($event) {
        // Skip processing if baseline monitoring is disabled
        if (!$this->config['enabled']) {
            return;
        }

        $data = $event->get_data();
        $context = $event->get_context();

        // Only process events with performance metrics
        if (!isset($data['metric']) || !isset($data['value'])) {
            return;
        }

        $metric = $data['metric'];
        $value = $data['value'];

        // Update the baseline for this metric
        $this->update_baseline($context, $metric, $value);

        // Check for deviations
        $deviation = $this->check_deviation($context, $metric, $value);
        if ($deviation !== false && abs($deviation) > $this->config['deviation_threshold']) {
            // Create a deviation event
            $deviation_event = new Status_Sentry_Monitoring_Event(
                'baseline_deviation',
                'baseline',
                $context,
                sprintf('Significant deviation detected for %s: %.2f%%', $metric, $deviation * 100),
                [
                    'metric' => $metric,
                    'value' => $value,
                    'baseline' => $this->get_baseline($context, $metric),
                    'deviation' => $deviation,
                ],
                Status_Sentry_Monitoring_Event::PRIORITY_HIGH
            );

            // Dispatch the deviation event
            Status_Sentry_Monitoring_Manager::get_instance()->dispatch($deviation_event);
        }
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
            'baseline_count' => $this->count_baselines(),
            'deviation_threshold' => $this->config['deviation_threshold'],
            'auto_update' => $this->config['auto_update'],
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
        if (isset($config['deviation_threshold'])) {
            $config['deviation_threshold'] = max(0.05, min(0.5, floatval($config['deviation_threshold'])));
        }

        if (isset($config['min_samples'])) {
            $config['min_samples'] = max(3, min(50, intval($config['min_samples'])));
        }

        if (isset($config['max_samples'])) {
            $config['max_samples'] = max(10, min(1000, intval($config['max_samples'])));
        }

        // Update configuration
        $this->config = array_merge($this->config, $config);

        // Save configuration
        update_option('status_sentry_baseline_config', $this->config);

        return true;
    }

    /**
     * Ensure the baseline table exists.
     *
     * @since    1.3.0
     * @access   private
     * @return   bool    Whether the table exists or was successfully created.
     */
    private function ensure_table_exists() {
        global $wpdb;

        if ($wpdb->get_var("SHOW TABLES LIKE '{$this->table_name}'") != $this->table_name) {
            // Table doesn't exist, create it
            require_once STATUS_SENTRY_PLUGIN_DIR . 'includes/db/migrations/007_create_baselines_table.php';
            $migration = new Status_Sentry_Migration_CreateBaselinesTable();
            return $migration->up();
        }

        return true;
    }

    /**
     * Update the baseline for a metric.
     *
     * @since    1.3.0
     * @access   private
     * @param    string    $context    The context of the metric.
     * @param    string    $metric     The metric name.
     * @param    float     $value      The metric value.
     * @return   bool                  Whether the baseline was successfully updated.
     */
    private function update_baseline($context, $metric, $value) {
        // Skip if auto-update is disabled
        if (!$this->config['auto_update']) {
            return false;
        }

        global $wpdb;

        // Check if a baseline exists for this metric
        $baseline = $this->get_baseline($context, $metric);

        if ($baseline === false) {
            // No baseline exists, create a new one
            $result = $wpdb->insert(
                $this->table_name,
                [
                    'context' => $context,
                    'metric' => $metric,
                    'avg_value' => $value,
                    'min_value' => $value,
                    'max_value' => $value,
                    'samples' => 1,
                    'last_updated' => current_time('mysql'),
                ],
                ['%s', '%s', '%f', '%f', '%f', '%d', '%s']
            );

            return ($result !== false);
        } else {
            // Update existing baseline
            $new_samples = min($baseline['samples'] + 1, $this->config['max_samples']);
            $weight = 1 / $new_samples;
            $new_avg = $baseline['avg_value'] * (1 - $weight) + $value * $weight;
            $new_min = min($baseline['min_value'], $value);
            $new_max = max($baseline['max_value'], $value);

            $result = $wpdb->update(
                $this->table_name,
                [
                    'avg_value' => $new_avg,
                    'min_value' => $new_min,
                    'max_value' => $new_max,
                    'samples' => $new_samples,
                    'last_updated' => current_time('mysql'),
                ],
                [
                    'context' => $context,
                    'metric' => $metric,
                ],
                ['%f', '%f', '%f', '%d', '%s'],
                ['%s', '%s']
            );

            return ($result !== false);
        }
    }

    /**
     * Get the baseline for a metric.
     *
     * @since    1.3.0
     * @access   public
     * @param    string    $context    The context of the metric.
     * @param    string    $metric     The metric name.
     * @return   array|bool            The baseline data, or false if no baseline exists.
     */
    public function get_baseline($context, $metric) {
        global $wpdb;

        $baseline = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE context = %s AND metric = %s",
                $context,
                $metric
            ),
            ARRAY_A
        );

        return $baseline;
    }

    /**
     * Check the deviation of a value from its baseline.
     *
     * @since    1.3.0
     * @access   private
     * @param    string    $context    The context of the metric.
     * @param    string    $metric     The metric name.
     * @param    float     $value      The metric value.
     * @return   float|bool            The deviation as a fraction, or false if no baseline exists.
     */
    private function check_deviation($context, $metric, $value) {
        $baseline = $this->get_baseline($context, $metric);

        if ($baseline === false || $baseline['samples'] < $this->config['min_samples']) {
            return false;
        }

        // Calculate deviation as a fraction of the average
        if ($baseline['avg_value'] == 0) {
            return ($value == 0) ? 0 : 1; // Avoid division by zero
        }

        return ($value - $baseline['avg_value']) / $baseline['avg_value'];
    }

    /**
     * Count the number of baselines.
     *
     * @since    1.3.0
     * @access   private
     * @return   int    The number of baselines.
     */
    private function count_baselines() {
        global $wpdb;

        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name}");
    }
}
