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
 *
 * @since      1.1.0
 * @package    Status_Sentry
 * @subpackage Status_Sentry/includes/monitoring
 */
class Status_Sentry_Baseline {

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
            // Weight new value at 10% to avoid rapid fluctuations
            $weight = 0.1;
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

            // Update the baseline
            $result = $wpdb->update(
                $this->table_name,
                [
                    'value' => $new_value,
                    'sample_count' => $sample_count,
                    'last_updated' => current_time('mysql'),
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
}
