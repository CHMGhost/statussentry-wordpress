<?php
/**
 * Sampling manager class.
 *
 * @since      1.0.0
 * @package    Status_Sentry
 * @subpackage Status_Sentry/includes/data
 */

/**
 * Sampling manager class.
 *
 * This class handles sampling logic for data capture.
 *
 * @since      1.0.0
 * @package    Status_Sentry
 * @subpackage Status_Sentry/includes/data
 */
class Status_Sentry_Sampling_Manager {

    /**
     * Determine if an event should be sampled.
     *
     * @since    1.0.0
     * @param    string    $feature         The feature this hook belongs to.
     * @param    string    $hook            The name of the WordPress hook.
     * @param    float     $sampling_rate   The sampling rate (0.0 to 1.0).
     * @return   bool                       Whether the event should be sampled.
     */
    public function should_sample($feature, $hook, $sampling_rate) {
        // Always sample if sampling rate is 1.0 (100%)
        if ($sampling_rate >= 1.0) {
            return true;
        }
        
        // Never sample if sampling rate is 0.0 (0%)
        if ($sampling_rate <= 0.0) {
            return false;
        }
        
        // Check if this is a high-priority event that should always be sampled
        if ($this->is_high_priority_event($feature, $hook)) {
            return true;
        }
        
        // Apply adaptive sampling based on system load
        $adjusted_rate = $this->adjust_sampling_rate($sampling_rate);
        
        // Generate a random number and compare with the sampling rate
        return (mt_rand() / mt_getrandmax()) < $adjusted_rate;
    }

    /**
     * Check if an event is high priority and should always be sampled.
     *
     * @since    1.0.0
     * @access   private
     * @param    string    $feature    The feature this hook belongs to.
     * @param    string    $hook       The name of the WordPress hook.
     * @return   bool                  Whether the event is high priority.
     */
    private function is_high_priority_event($feature, $hook) {
        // Define high-priority events
        $high_priority_events = [
            'conflict_detection' => ['activated_plugin', 'deactivated_plugin'],
            'core_monitoring' => ['plugins_loaded'],
        ];
        
        // Check if the feature and hook combination is high priority
        return isset($high_priority_events[$feature]) && in_array($hook, $high_priority_events[$feature]);
    }

    /**
     * Adjust the sampling rate based on system load.
     *
     * @since    1.0.0
     * @access   private
     * @param    float     $sampling_rate    The original sampling rate.
     * @return   float                       The adjusted sampling rate.
     */
    private function adjust_sampling_rate($sampling_rate) {
        // Get memory usage as a percentage of the limit
        $memory_limit = $this->get_memory_limit_in_bytes();
        $memory_usage = memory_get_usage();
        $memory_usage_percent = ($memory_usage / $memory_limit);
        
        // Reduce sampling rate if memory usage is high
        if ($memory_usage_percent > 0.8) {
            // If memory usage is above 80%, reduce sampling rate by 90%
            return $sampling_rate * 0.1;
        } elseif ($memory_usage_percent > 0.6) {
            // If memory usage is above 60%, reduce sampling rate by 50%
            return $sampling_rate * 0.5;
        }
        
        // Check if we're in a high-traffic situation
        if ($this->is_high_traffic()) {
            // Reduce sampling rate by 75% during high traffic
            return $sampling_rate * 0.25;
        }
        
        return $sampling_rate;
    }

    /**
     * Get the PHP memory limit in bytes.
     *
     * @since    1.0.0
     * @access   private
     * @return   int    The memory limit in bytes.
     */
    private function get_memory_limit_in_bytes() {
        $memory_limit = ini_get('memory_limit');
        
        // Convert memory limit to bytes
        $unit = strtolower(substr($memory_limit, -1));
        $value = (int) substr($memory_limit, 0, -1);
        
        switch ($unit) {
            case 'g':
                $value *= 1024;
                // Fall through
            case 'm':
                $value *= 1024;
                // Fall through
            case 'k':
                $value *= 1024;
        }
        
        return $value;
    }

    /**
     * Check if the site is experiencing high traffic.
     *
     * @since    1.0.0
     * @access   private
     * @return   bool    Whether the site is experiencing high traffic.
     */
    private function is_high_traffic() {
        // Get the number of active connections
        // This is a simplified implementation and would need to be adapted for production
        
        // Check if we can get the number of active connections from the server status
        if (function_exists('apache_get_modules') && in_array('mod_status', apache_get_modules())) {
            // This is a placeholder - in a real implementation, you would need to
            // query the server status to get the number of active connections
            return false;
        }
        
        // Alternative approach: check recent traffic in the database
        // This is a simplified implementation and would need to be adapted for production
        global $wpdb;
        
        // Get the number of requests in the last minute
        $table_name = $wpdb->prefix . 'status_sentry_events';
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name) {
            $one_minute_ago = time() - 60;
            $count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table_name WHERE event_time > %d",
                $one_minute_ago
            ));
            
            // If there are more than 100 requests in the last minute, consider it high traffic
            return $count > 100;
        }
        
        return false;
    }
}
