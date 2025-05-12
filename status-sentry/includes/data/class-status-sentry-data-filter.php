<?php
/**
 * Data filter class.
 *
 * @since      1.0.0
 * @package    Status_Sentry
 * @subpackage Status_Sentry/includes/data
 */

/**
 * Data filter class.
 *
 * This class filters and sanitizes captured data.
 *
 * @since      1.0.0
 * @package    Status_Sentry
 * @subpackage Status_Sentry/includes/data
 */
class Status_Sentry_Data_Filter {

    /**
     * Filter and sanitize captured data.
     *
     * @since    1.0.0
     * @param    array     $data       The data to filter.
     * @param    string    $feature    The feature this hook belongs to.
     * @param    string    $hook       The name of the WordPress hook.
     * @return   array                 The filtered data.
     */
    public function filter($data, $feature, $hook) {
        // Apply general filters
        $data = $this->sanitize_data($data);
        $data = $this->remove_sensitive_data($data);
        $data = $this->truncate_large_data($data);
        
        // Apply feature-specific filters
        switch ($feature) {
            case 'core_monitoring':
                $data = $this->filter_core_monitoring_data($data, $hook);
                break;
            case 'db_monitoring':
                $data = $this->filter_db_monitoring_data($data, $hook);
                break;
            case 'conflict_detection':
                $data = $this->filter_conflict_detection_data($data, $hook);
                break;
            case 'performance_monitoring':
                $data = $this->filter_performance_monitoring_data($data, $hook);
                break;
        }
        
        return $data;
    }

    /**
     * Sanitize data.
     *
     * @since    1.0.0
     * @access   private
     * @param    array     $data    The data to sanitize.
     * @return   array              The sanitized data.
     */
    private function sanitize_data($data) {
        // Recursively sanitize data
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->sanitize_data($value);
            } elseif (is_string($value)) {
                $data[$key] = sanitize_text_field($value);
            }
        }
        
        return $data;
    }

    /**
     * Remove sensitive data.
     *
     * @since    1.0.0
     * @access   private
     * @param    array     $data    The data to remove sensitive data from.
     * @return   array              The data with sensitive data removed.
     */
    private function remove_sensitive_data($data) {
        // Define sensitive keys to remove
        $sensitive_keys = [
            'password', 'pass', 'pwd', 'auth', 'key', 'secret', 'token',
            'api_key', 'apikey', 'access_token', 'auth_token', 'credentials',
        ];
        
        // Recursively remove sensitive data
        foreach ($data as $key => $value) {
            // Check if key contains sensitive information
            foreach ($sensitive_keys as $sensitive_key) {
                if (stripos($key, $sensitive_key) !== false) {
                    $data[$key] = '[REDACTED]';
                    break;
                }
            }
            
            // Recursively process arrays
            if (is_array($value)) {
                $data[$key] = $this->remove_sensitive_data($value);
            }
        }
        
        return $data;
    }

    /**
     * Truncate large data.
     *
     * @since    1.0.0
     * @access   private
     * @param    array     $data    The data to truncate.
     * @return   array              The truncated data.
     */
    private function truncate_large_data($data) {
        // Define max sizes
        $max_string_length = 1000;
        $max_array_items = 100;
        
        // Recursively truncate data
        foreach ($data as $key => $value) {
            if (is_string($value) && strlen($value) > $max_string_length) {
                $data[$key] = substr($value, 0, $max_string_length) . '... [TRUNCATED]';
            } elseif (is_array($value)) {
                if (count($value) > $max_array_items) {
                    $data[$key] = array_slice($value, 0, $max_array_items);
                    $data[$key]['_truncated'] = 'Array truncated. Original size: ' . count($value);
                } else {
                    $data[$key] = $this->truncate_large_data($value);
                }
            }
        }
        
        return $data;
    }

    /**
     * Filter core monitoring data.
     *
     * @since    1.0.0
     * @access   private
     * @param    array     $data    The data to filter.
     * @param    string    $hook    The name of the WordPress hook.
     * @return   array              The filtered data.
     */
    private function filter_core_monitoring_data($data, $hook) {
        // Apply hook-specific filters
        switch ($hook) {
            case 'plugins_loaded':
                // Remove unnecessary plugin data
                if (isset($data['loaded_plugins'])) {
                    foreach ($data['loaded_plugins'] as $plugin => $plugin_data) {
                        // Keep only essential plugin information
                        $data['loaded_plugins'][$plugin] = [
                            'Name' => $plugin_data['Name'] ?? '',
                            'Version' => $plugin_data['Version'] ?? '',
                            'Author' => $plugin_data['Author'] ?? '',
                        ];
                    }
                }
                break;
                
            case 'wp_loaded':
                // Simplify script and style data
                if (isset($data['loaded_scripts'])) {
                    foreach ($data['loaded_scripts'] as $handle => $script) {
                        $data['loaded_scripts'][$handle] = [
                            'src' => $script['src'],
                            'ver' => $script['ver'],
                        ];
                    }
                }
                
                if (isset($data['loaded_styles'])) {
                    foreach ($data['loaded_styles'] as $handle => $style) {
                        $data['loaded_styles'][$handle] = [
                            'src' => $style['src'],
                            'ver' => $style['ver'],
                        ];
                    }
                }
                break;
        }
        
        return $data;
    }

    /**
     * Filter database monitoring data.
     *
     * @since    1.0.0
     * @access   private
     * @param    array     $data    The data to filter.
     * @param    string    $hook    The name of the WordPress hook.
     * @return   array              The filtered data.
     */
    private function filter_db_monitoring_data($data, $hook) {
        // Anonymize queries
        if (isset($data['query'])) {
            // Remove potentially sensitive data from queries
            $data['query'] = $this->anonymize_query($data['query']);
        }
        
        return $data;
    }

    /**
     * Filter conflict detection data.
     *
     * @since    1.0.0
     * @access   private
     * @param    array     $data    The data to filter.
     * @param    string    $hook    The name of the WordPress hook.
     * @return   array              The filtered data.
     */
    private function filter_conflict_detection_data($data, $hook) {
        // No specific filtering needed for now
        return $data;
    }

    /**
     * Filter performance monitoring data.
     *
     * @since    1.0.0
     * @access   private
     * @param    array     $data    The data to filter.
     * @param    string    $hook    The name of the WordPress hook.
     * @return   array              The filtered data.
     */
    private function filter_performance_monitoring_data($data, $hook) {
        // Format memory usage
        if (isset($data['memory_usage'])) {
            $data['memory_usage_formatted'] = size_format($data['memory_usage'], 2);
        }
        
        if (isset($data['peak_memory_usage'])) {
            $data['peak_memory_usage_formatted'] = size_format($data['peak_memory_usage'], 2);
        }
        
        return $data;
    }

    /**
     * Anonymize a database query.
     *
     * @since    1.0.0
     * @access   private
     * @param    string    $query    The query to anonymize.
     * @return   string              The anonymized query.
     */
    private function anonymize_query($query) {
        // Replace values in WHERE clauses
        $query = preg_replace('/WHERE\s+(\w+)\s*=\s*[\'"](.+?)[\'"]/i', 'WHERE $1 = \'[REDACTED]\'', $query);
        
        // Replace values in INSERT statements
        $query = preg_replace('/VALUES\s*\((.+?)\)/i', 'VALUES ([REDACTED])', $query);
        
        // Replace values in UPDATE statements
        $query = preg_replace('/SET\s+(\w+)\s*=\s*[\'"](.+?)[\'"]/i', 'SET $1 = \'[REDACTED]\'', $query);
        
        return $query;
    }
}
