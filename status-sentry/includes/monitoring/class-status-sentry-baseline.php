<?php
/**
 * Baseline monitoring class.
 *
 * @since      1.1.0
 * @package    Status_Sentry
 * @subpackage Status_Sentry/includes/monitoring
 */

/**
 * Baseline monitoring class.
 *
 * This class establishes and tracks system performance baselines for various metrics,
 * allowing the plugin to detect abnormal behavior and adjust its resource usage accordingly.
 *
 * Key responsibilities:
 * - Record baseline metrics for various system aspects
 * - Update baselines over time with new measurements
 * - Provide access to baseline data for other components
 * - Detect significant deviations from established baselines
 * - Implement the Monitoring_Interface for centralized monitoring
 *
 * @since      1.1.0
 * @package    Status_Sentry
 * @subpackage Status_Sentry/includes/monitoring
 */
class Status_Sentry_Baseline implements Status_Sentry_Monitoring_Interface {

    /**
     * The table name.
     *
     * @since    1.1.0
     * @access   private
     * @var      string    $table_name    The table name.
     */
    private $table_name;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.1.0
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'status_sentry_baselines';
    }

    /**
     * Record a metric value and update the baseline.
     *
     * This method records a new metric value and updates the baseline
     * using an exponential moving average approach. If the baseline
     * doesn't exist yet, it creates a new one.
     *
     * @since    1.1.0
     * @param    string    $metric_name      The name of the metric.
     * @param    string    $metric_context   The context of the metric (e.g., task name).
     * @param    float     $value            The metric value to record.
     * @param    array     $metadata         Optional. Additional metadata to store.
     * @return   bool                        Whether the baseline was successfully updated.
     */
    public function record_metric($metric_name, $metric_context, $value, $metadata = []) {
        global $wpdb;

        // Ensure the table exists
        if (!$this->ensure_table_exists()) {
            return false;
        }

        // Check if the baseline exists
        $baseline = $this->get_baseline($metric_name, $metric_context);

        if ($baseline) {
            // Update existing baseline with exponential moving average
            // Weight new value at 20% to allow for more responsive updates
            $weight = 0.2; // Increased from 0.1 to make baselines more responsive
            $new_value = ($baseline['value'] * (1 - $weight)) + ($value * $weight);
            $sample_count = $baseline['sample_count'] + 1;

            // Update the metadata if provided
            if (!empty($metadata)) {
                $merged_metadata = json_decode($baseline['metadata'], true) ?: [];
                $merged_metadata = array_merge($merged_metadata, $metadata);
                $metadata_json = json_encode($merged_metadata);
            } else {
                $metadata_json = $baseline['metadata'];
            }

            // Always update the last_updated timestamp, even if the value hasn't changed much
            $current_time = current_time('mysql');

            // Update the baseline
            $result = $wpdb->update(
                $this->table_name,
                [
                    'value' => $new_value,
                    'sample_count' => $sample_count,
                    'last_updated' => $current_time,
                    'metadata' => $metadata_json,
                ],
                [
                    'id' => $baseline['id'],
                ],
                ['%f', '%d', '%s', '%s'],
                ['%d']
            );
        } else {
            // Create new baseline
            $result = $wpdb->insert(
                $this->table_name,
                [
                    'metric_name' => $metric_name,
                    'metric_context' => $metric_context,
                    'value' => $value,
                    'sample_count' => 1,
                    'last_updated' => current_time('mysql'),
                    'metadata' => !empty($metadata) ? json_encode($metadata) : null,
                ],
                ['%s', '%s', '%f', '%d', '%s', '%s']
            );
        }

        if ($result === false) {
            error_log('Status Sentry: Failed to update baseline - ' . $wpdb->last_error);
            return false;
        }

        return true;
    }

    /**
     * Get a baseline.
     *
     * @since    1.1.0
     * @param    string    $metric_name      The name of the metric.
     * @param    string    $metric_context   The context of the metric.
     * @return   array|false                 The baseline data or false if not found.
     */
    public function get_baseline($metric_name, $metric_context) {
        global $wpdb;

        // Ensure the table exists
        if (!$this->ensure_table_exists()) {
            return false;
        }

        $query = $wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE metric_name = %s AND metric_context = %s",
            $metric_name,
            $metric_context
        );

        $baseline = $wpdb->get_row($query, ARRAY_A);

        return $baseline;
    }

    /**
     * Check if a value is significantly different from the baseline.
     *
     * @since    1.1.0
     * @param    string    $metric_name      The name of the metric.
     * @param    string    $metric_context   The context of the metric.
     * @param    float     $value            The value to check.
     * @param    float     $threshold        The threshold for significant difference (default: 0.5 or 50%).
     * @return   bool                        Whether the value is significantly different.
     */
    public function is_significant_deviation($metric_name, $metric_context, $value, $threshold = 0.5) {
        $baseline = $this->get_baseline($metric_name, $metric_context);

        if (!$baseline || $baseline['sample_count'] < 5) {
            // Not enough data to determine significance
            return false;
        }

        $baseline_value = $baseline['value'];

        // Calculate the relative difference
        if ($baseline_value == 0) {
            // Avoid division by zero
            return $value > 0;
        }

        $relative_diff = abs(($value - $baseline_value) / $baseline_value);

        return $relative_diff > $threshold;
    }

    /**
     * Ensure the baselines table exists.
     *
     * @since    1.1.0
     * @access   private
     * @return   bool    Whether the table exists or was successfully created.
     */
    private function ensure_table_exists() {
        global $wpdb;

        if ($wpdb->get_var("SHOW TABLES LIKE '{$this->table_name}'") != $this->table_name) {
            // Table doesn't exist, create it
            require_once STATUS_SENTRY_PLUGIN_DIR . 'includes/db/migrations/004_create_baselines_table.php';
            $migration = new Status_Sentry_Migration_CreateBaselinesTable();
            return $migration->up();
        }

        return true;
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
        // Register handlers for resource usage and performance events
        $manager->register_handler('resource_usage', [$this, 'process_event']);
        $manager->register_handler('performance', [$this, 'process_event']);
    }

    /**
     * Process a monitoring event.
     *
     * @since    1.3.0
     * @param    Status_Sentry_Monitoring_Event    $event    The monitoring event to process.
     * @return   bool                              Whether the event was processed successfully.
     */
    public function process_event($event) {
        $type = $event->get_type();
        $data = $event->get_data();
        $context = $event->get_context();
        $source = $event->get_source();

        // Process resource usage events
        if ($type === 'resource_usage') {
            // Record memory usage if available
            if (isset($data['memory_usage'])) {
                $this->record_metric('memory_usage', $context, $data['memory_usage']);
            }

            // Record CPU usage if available
            if (isset($data['cpu_usage'])) {
                $this->record_metric('cpu_usage', $context, $data['cpu_usage']);
            }

            return true;
        }

        // Process performance events
        if ($type === 'performance') {
            // Record execution time if available
            if (isset($data['execution_time'])) {
                $this->record_metric('execution_time', $context, $data['execution_time']);
            }

            return true;
        }

        return false;
    }

    /**
     * Get the monitoring component's status.
     *
     * @since    1.3.0
     * @return   array    The component status as an associative array.
     */
    public function get_status() {
        global $wpdb;

        // Get baseline metrics count
        $count = 0;
        if ($this->ensure_table_exists()) {
            $count = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name}");
        }

        return [
            'metrics_count' => $count,
            'table_exists' => $this->ensure_table_exists(),
        ];
    }

    /**
     * Get the monitoring component's configuration.
     *
     * @since    1.3.0
     * @return   array    The component configuration as an associative array.
     */
    public function get_config() {
        return [
            'ema_weight' => 0.1, // Exponential moving average weight
            'significance_threshold' => 0.5, // Default significance threshold
            'min_samples' => 5, // Minimum samples needed for significance testing
        ];
    }

    /**
     * Update the monitoring component's configuration.
     *
     * @since    1.3.0
     * @param    array    $config    The new configuration as an associative array.
     * @return   bool                Whether the configuration was successfully updated.
     */
    public function update_config($config) {
        // Baseline doesn't have configurable options yet
        return true;
    }

    /**
     * Take a snapshot of the current system state.
     *
     * @since    1.6.0
     * @param    string    $snapshot_name    The name of the snapshot.
     * @return   array                       The snapshot data.
     */
    public function snapshot($snapshot_name) {
        // Collect system metrics
        $snapshot = [
            'memory_usage' => memory_get_usage(),
            'memory_peak' => memory_get_peak_usage(),
            'timestamp' => microtime(true),
            'active_plugins' => get_option('active_plugins'),
            'name' => $snapshot_name,
        ];

        // Add database metrics
        global $wpdb;
        $snapshot['db_queries'] = $wpdb->num_queries;

        // Add additional metrics if available
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            $snapshot['cpu_load'] = $load[0];
        }

        // Store the snapshot in a transient
        set_transient('status_sentry_snapshot_' . $snapshot_name, $snapshot, 3600); // 1 hour expiration

        return $snapshot;
    }

    /**
     * Take a snapshot before plugin activation.
     *
     * @since    1.6.0
     * @param    string    $plugin    The plugin being activated.
     * @return   array                The snapshot data.
     */
    public function snapshot_before($plugin) {
        $plugin_name = basename($plugin, '.php');
        return $this->snapshot('before_' . $plugin_name);
    }

    /**
     * Take a snapshot after plugin activation.
     *
     * @since    1.6.0
     * @param    string    $plugin    The plugin that was activated.
     * @return   array                The snapshot data.
     */
    public function snapshot_after($plugin) {
        $plugin_name = basename($plugin, '.php');
        return $this->snapshot('after_' . $plugin_name);
    }

    /**
     * Compare two snapshots and identify differences.
     *
     * @since    1.6.0
     * @param    string    $before_name    The name of the before snapshot.
     * @param    string    $after_name     The name of the after snapshot.
     * @return   array                     The differences between snapshots.
     */
    public function diff($before_name, $after_name) {
        // Get the snapshots
        $before = get_transient('status_sentry_snapshot_' . $before_name);
        $after = get_transient('status_sentry_snapshot_' . $after_name);

        // If either snapshot is missing, return empty diff
        if (!$before || !$after) {
            return [];
        }

        $diff = [];

        // Compare memory usage
        if (isset($before['memory_usage']) && isset($after['memory_usage'])) {
            $memory_diff = $after['memory_usage'] - $before['memory_usage'];
            $memory_diff_percent = ($before['memory_usage'] > 0) ? ($memory_diff / $before['memory_usage']) * 100 : 0;

            if (abs($memory_diff_percent) > 20) { // 20% threshold
                $diff['memory_usage'] = [
                    'before' => $before['memory_usage'],
                    'after' => $after['memory_usage'],
                    'diff' => $memory_diff,
                    'diff_percent' => $memory_diff_percent,
                ];
            }
        }

        // Compare memory peak
        if (isset($before['memory_peak']) && isset($after['memory_peak'])) {
            $memory_peak_diff = $after['memory_peak'] - $before['memory_peak'];
            $memory_peak_diff_percent = ($before['memory_peak'] > 0) ? ($memory_peak_diff / $before['memory_peak']) * 100 : 0;

            if (abs($memory_peak_diff_percent) > 20) { // 20% threshold
                $diff['memory_peak'] = [
                    'before' => $before['memory_peak'],
                    'after' => $after['memory_peak'],
                    'diff' => $memory_peak_diff,
                    'diff_percent' => $memory_peak_diff_percent,
                ];
            }
        }

        // Compare CPU load
        if (isset($before['cpu_load']) && isset($after['cpu_load'])) {
            $cpu_diff = $after['cpu_load'] - $before['cpu_load'];
            $cpu_diff_percent = ($before['cpu_load'] > 0) ? ($cpu_diff / $before['cpu_load']) * 100 : 0;

            if (abs($cpu_diff_percent) > 30) { // 30% threshold
                $diff['cpu_load'] = [
                    'before' => $before['cpu_load'],
                    'after' => $after['cpu_load'],
                    'diff' => $cpu_diff,
                    'diff_percent' => $cpu_diff_percent,
                ];
            }
        }

        // Compare DB queries
        if (isset($before['db_queries']) && isset($after['db_queries'])) {
            $queries_diff = $after['db_queries'] - $before['db_queries'];

            if ($queries_diff > 50) { // 50 queries threshold
                $diff['db_queries'] = [
                    'before' => $before['db_queries'],
                    'after' => $after['db_queries'],
                    'diff' => $queries_diff,
                ];
            }
        }

        return $diff;
    }
}
