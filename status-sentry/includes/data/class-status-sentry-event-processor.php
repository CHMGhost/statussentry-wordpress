<?php
/**
 * Event processor class.
 *
 * @since      1.0.0
 * @package    Status_Sentry
 * @subpackage Status_Sentry/includes/data
 */

/**
 * Event Processor Class
 *
 * This class is responsible for processing events from the queue, enriching them
 * with additional data, and storing them in the events table for later analysis.
 * It is a critical component of the data pipeline, serving as the final step
 * before data is permanently stored.
 *
 * Key responsibilities:
 * - Retrieve events from the queue in batches
 * - Process each event with appropriate error handling
 * - Enrich events with additional context and metadata
 * - Store processed events in the events table
 * - Clean up old events to prevent database bloat
 * - Handle database errors gracefully
 *
 * The processor uses feature-specific enrichment methods to add relevant
 * context to different types of events, making the data more valuable for
 * analysis and troubleshooting.
 *
 * @since      1.0.0
 * @package    Status_Sentry
 * @subpackage Status_Sentry/includes/data
 * @author     Status Sentry Team
 */
class Status_Sentry_Event_Processor {

    /**
     * The event queue instance.
     *
     * @since    1.0.0
     * @access   private
     * @var      Status_Sentry_Event_Queue    $event_queue    The event queue instance.
     */
    private $event_queue;

    /**
     * The events table name.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $table_name    The events table name.
     */
    private $table_name;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     */
    public function __construct() {
        $this->event_queue = new Status_Sentry_Event_Queue();

        global $wpdb;
        $this->table_name = $wpdb->prefix . 'status_sentry_events';
    }

    /**
     * Process events from the queue.
     *
     * This method retrieves a batch of events from the queue and processes them
     * one by one. It handles errors gracefully and ensures that each event is
     * properly marked as processed or failed. It also performs periodic cleanup
     * of old events to prevent database bloat.
     *
     * The method uses a transaction-based approach to ensure database consistency
     * when processing multiple events. If a transaction is not available, it falls
     * back to processing events individually.
     *
     * @since    1.0.0
     * @param    int    $batch_size    The number of events to process in a batch.
     * @return   int                   The number of events processed.
     */
    public function process_events($batch_size = 100) {
        global $wpdb;

        // Validate input
        $batch_size = absint($batch_size);
        if ($batch_size <= 0) {
            $batch_size = 100; // Default to 100 if invalid
        }

        // Get events from the queue
        try {
            $events = $this->event_queue->get_events($batch_size);
        } catch (Exception $e) {
            error_log('Status Sentry: Error retrieving events from queue - ' . $e->getMessage());
            return 0;
        }

        if (empty($events)) {
            return 0;
        }

        // Check if we can use transactions
        $use_transaction = method_exists($wpdb, 'query') &&
                          method_exists($wpdb, 'begin') &&
                          method_exists($wpdb, 'commit') &&
                          method_exists($wpdb, 'rollback');

        // Start transaction if supported
        if ($use_transaction && count($events) > 1) {
            $wpdb->query('START TRANSACTION');
        }

        // Process each event
        $processed_count = 0;
        $failed_count = 0;
        $start_time = microtime(true);
        $memory_start = memory_get_usage();

        foreach ($events as $event) {
            // Skip invalid events
            if (!isset($event['id']) || !isset($event['feature']) || !isset($event['hook'])) {
                error_log('Status Sentry: Skipping invalid event - missing required fields');
                continue;
            }

            // Process the event
            try {
                $success = $this->process_event($event);

                // Update the event status
                if ($success) {
                    $this->event_queue->update_event_status($event['id'], 'processed');
                    $processed_count++;
                } else {
                    $this->event_queue->update_event_status($event['id'], 'failed');
                    $failed_count++;
                }
            } catch (Exception $e) {
                error_log('Status Sentry: Error processing event - ' . $e->getMessage());

                // Try to update the event status
                try {
                    $this->event_queue->update_event_status($event['id'], 'failed');
                } catch (Exception $update_error) {
                    error_log('Status Sentry: Error updating event status - ' . $update_error->getMessage());
                }

                $failed_count++;
            }

            // Check if we're approaching memory limit or time limit
            if ($this->should_stop_processing($start_time, $memory_start)) {
                error_log('Status Sentry: Stopping batch processing early due to resource constraints');
                break;
            }
        }

        // Commit transaction if used
        if ($use_transaction && count($events) > 1) {
            $wpdb->query('COMMIT');
        }

        // Log processing statistics
        error_log(sprintf(
            'Status Sentry: Processed %d events, %d failed, %d total, in %.2f seconds',
            $processed_count,
            $failed_count,
            count($events),
            microtime(true) - $start_time
        ));

        // Clean up old processed events (only if we successfully processed some events)
        if ($processed_count > 0) {
            try {
                $deleted = $this->event_queue->delete_events('processed', 86400); // 24 hours
                if ($deleted > 0) {
                    error_log(sprintf('Status Sentry: Cleaned up %d old processed events', $deleted));
                }
            } catch (Exception $e) {
                error_log('Status Sentry: Error cleaning up old events - ' . $e->getMessage());
            }
        }

        return $processed_count;
    }

    /**
     * Check if processing should stop due to resource constraints.
     *
     * @since    1.0.0
     * @access   private
     * @param    float    $start_time    The time when processing started.
     * @param    int      $memory_start  The memory usage when processing started.
     * @return   bool                    Whether processing should stop.
     */
    private function should_stop_processing($start_time, $memory_start) {
        // Check execution time (stop if over 20 seconds)
        $max_execution_time = 20; // seconds
        if ((microtime(true) - $start_time) > $max_execution_time) {
            return true;
        }

        // Check memory usage (stop if we've used more than 75% of available memory)
        $memory_limit = $this->get_memory_limit_in_bytes();
        $current_memory = memory_get_usage();
        $memory_used_percent = ($current_memory / $memory_limit);

        if ($memory_used_percent > 0.75) {
            return true;
        }

        // Check memory growth (stop if we've used more than 50MB in this batch)
        $memory_growth = $current_memory - $memory_start;
        if ($memory_growth > (50 * 1024 * 1024)) { // 50MB
            return true;
        }

        return false;
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
     * Process a single event.
     *
     * This method processes a single event from the queue, enriches it with
     * additional data, and stores it in the events table. It includes validation
     * and error handling to ensure that the event is processed correctly.
     *
     * The method performs the following steps:
     * 1. Validates the event data
     * 2. Ensures the events table exists
     * 3. Enriches the event data with additional context
     * 4. Stores the enriched event in the events table
     *
     * @since    1.0.0
     * @access   private
     * @param    array    $event    The event to process.
     * @return   bool               Whether the event was successfully processed.
     */
    private function process_event($event) {
        global $wpdb;

        // Validate event data
        if (!isset($event['data']) || !is_array($event['data'])) {
            error_log('Status Sentry: Invalid event data - data is missing or not an array');
            return false;
        }

        if (empty($event['feature']) || !is_string($event['feature'])) {
            error_log('Status Sentry: Invalid event data - feature is missing or invalid');
            return false;
        }

        if (empty($event['hook']) || !is_string($event['hook'])) {
            error_log('Status Sentry: Invalid event data - hook is missing or invalid');
            return false;
        }

        // Check if the events table exists (with caching to avoid repeated checks)
        static $table_exists = null;

        if ($table_exists === null) {
            $table_exists = ($wpdb->get_var("SHOW TABLES LIKE '{$this->table_name}'") == $this->table_name);

            if (!$table_exists) {
                // Table doesn't exist, try to create it
                $create_result = $this->create_events_table();

                // Check again if the table exists
                $table_exists = ($wpdb->get_var("SHOW TABLES LIKE '{$this->table_name}'") == $this->table_name);

                if (!$table_exists) {
                    // Still doesn't exist, log error and return
                    error_log('Status Sentry: Failed to create events table - ' . $wpdb->last_error);
                    return false;
                }
            }
        }

        // Enrich the event data
        try {
            $enriched_data = $this->enrich_event_data($event['data'], $event['feature'], $event['hook']);
        } catch (Exception $e) {
            error_log('Status Sentry: Error enriching event data - ' . $e->getMessage());

            // Use original data if enrichment fails
            $enriched_data = $event['data'];
            $enriched_data['_enrichment_error'] = $e->getMessage();
        }

        // JSON encode the enriched data with error handling
        $json_data = wp_json_encode($enriched_data);
        if ($json_data === false) {
            error_log('Status Sentry: Failed to JSON encode enriched event data - ' . json_last_error_msg());

            // Try to encode a simplified version of the data
            $simplified_data = [
                'error' => 'Original data could not be encoded',
                'feature' => $event['feature'],
                'hook' => $event['hook'],
                'timestamp' => time(),
            ];
            $json_data = wp_json_encode($simplified_data);

            if ($json_data === false) {
                error_log('Status Sentry: Failed to encode simplified event data');
                return false;
            }
        }

        // Prepare the event data for storage
        $event_data = [
            'feature' => $event['feature'],
            'hook' => $event['hook'],
            'data' => $json_data,
            'event_time' => isset($event['created_at']) ? $event['created_at'] : current_time('mysql'),
            'processed_time' => current_time('mysql'),
        ];

        // Insert the event into the events table
        $result = $wpdb->insert(
            $this->table_name,
            $event_data,
            ['%s', '%s', '%s', '%s', '%s']
        );

        if ($result === false) {
            // Insert failed, log error with detailed information
            error_log(sprintf(
                'Status Sentry: Failed to store event - MySQL Error: %s, Query: %s',
                $wpdb->last_error,
                $wpdb->last_query
            ));
            return false;
        }

        return true;
    }

    /**
     * Enrich event data.
     *
     * @since    1.0.0
     * @access   private
     * @param    array     $data       The event data.
     * @param    string    $feature    The feature this event belongs to.
     * @param    string    $hook       The name of the WordPress hook.
     * @return   array                 The enriched event data.
     */
    private function enrich_event_data($data, $feature, $hook) {
        // Add additional metadata
        $data['_enriched'] = [
            'site_name' => get_bloginfo('name'),
            'admin_email' => get_bloginfo('admin_email'),
            'timezone' => wp_timezone_string(),
            'locale' => get_locale(),
            'active_theme' => $this->get_active_theme_info(),
            'active_plugins_count' => $this->count_active_plugins(),
        ];

        // Add feature-specific enrichments
        switch ($feature) {
            case 'core_monitoring':
                $data = $this->enrich_core_monitoring_data($data, $hook);
                break;
            case 'db_monitoring':
                $data = $this->enrich_db_monitoring_data($data, $hook);
                break;
            case 'conflict_detection':
                $data = $this->enrich_conflict_detection_data($data, $hook);
                break;
            case 'performance_monitoring':
                $data = $this->enrich_performance_monitoring_data($data, $hook);
                break;
        }

        return $data;
    }

    /**
     * Enrich core monitoring data.
     *
     * @since    1.0.0
     * @access   private
     * @param    array     $data    The event data.
     * @param    string    $hook    The name of the WordPress hook.
     * @return   array              The enriched event data.
     */
    private function enrich_core_monitoring_data($data, $hook) {
        // Add WordPress constants
        $data['_enriched']['constants'] = [
            'WP_DEBUG' => defined('WP_DEBUG') ? WP_DEBUG : false,
            'WP_DEBUG_LOG' => defined('WP_DEBUG_LOG') ? WP_DEBUG_LOG : false,
            'WP_DEBUG_DISPLAY' => defined('WP_DEBUG_DISPLAY') ? WP_DEBUG_DISPLAY : false,
            'SCRIPT_DEBUG' => defined('SCRIPT_DEBUG') ? SCRIPT_DEBUG : false,
            'WP_CACHE' => defined('WP_CACHE') ? WP_CACHE : false,
            'CONCATENATE_SCRIPTS' => defined('CONCATENATE_SCRIPTS') ? CONCATENATE_SCRIPTS : false,
            'COMPRESS_SCRIPTS' => defined('COMPRESS_SCRIPTS') ? COMPRESS_SCRIPTS : false,
            'COMPRESS_CSS' => defined('COMPRESS_CSS') ? COMPRESS_CSS : false,
        ];

        return $data;
    }

    /**
     * Enrich database monitoring data.
     *
     * @since    1.0.0
     * @access   private
     * @param    array     $data    The event data.
     * @param    string    $hook    The name of the WordPress hook.
     * @return   array              The enriched event data.
     */
    private function enrich_db_monitoring_data($data, $hook) {
        global $wpdb;

        // Add database information
        $data['_enriched']['db'] = [
            'prefix' => $wpdb->prefix,
            'charset' => $wpdb->charset,
            'collate' => $wpdb->collate,
            'table_prefix' => $wpdb->prefix,
        ];

        return $data;
    }

    /**
     * Enrich conflict detection data.
     *
     * @since    1.0.0
     * @access   private
     * @param    array     $data    The event data.
     * @param    string    $hook    The name of the WordPress hook.
     * @return   array              The enriched event data.
     */
    private function enrich_conflict_detection_data($data, $hook) {
        // Add plugin information
        if (isset($data['plugin'])) {
            $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $data['plugin']);
            $data['_enriched']['plugin_info'] = [
                'name' => $plugin_data['Name'] ?? '',
                'version' => $plugin_data['Version'] ?? '',
                'author' => $plugin_data['Author'] ?? '',
                'description' => $plugin_data['Description'] ?? '',
            ];
        }

        return $data;
    }

    /**
     * Enrich performance monitoring data.
     *
     * @since    1.0.0
     * @access   private
     * @param    array     $data    The event data.
     * @param    string    $hook    The name of the WordPress hook.
     * @return   array              The enriched event data.
     */
    private function enrich_performance_monitoring_data($data, $hook) {
        // Add server information
        $data['_enriched']['server'] = [
            'php_version' => phpversion(),
            'os' => PHP_OS,
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? '',
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'post_max_size' => ini_get('post_max_size'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'max_input_vars' => ini_get('max_input_vars'),
        ];

        return $data;
    }

    /**
     * Get active theme information.
     *
     * @since    1.0.0
     * @access   private
     * @return   array    The active theme information.
     */
    private function get_active_theme_info() {
        $theme = wp_get_theme();

        return [
            'name' => $theme->get('Name'),
            'version' => $theme->get('Version'),
            'author' => $theme->get('Author'),
            'template' => $theme->get_template(),
            'stylesheet' => $theme->get_stylesheet(),
        ];
    }

    /**
     * Count active plugins.
     *
     * @since    1.0.0
     * @access   private
     * @return   int    The number of active plugins.
     */
    private function count_active_plugins() {
        $active_plugins = get_option('active_plugins');

        return count($active_plugins);
    }

    /**
     * Create the events table.
     *
     * @since    1.0.0
     * @access   private
     * @return   bool    Whether the table was successfully created.
     */
    private function create_events_table() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$this->table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            feature varchar(50) NOT NULL,
            hook varchar(100) NOT NULL,
            data longtext NOT NULL,
            event_time datetime NOT NULL,
            processed_time datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY feature (feature),
            KEY hook (hook),
            KEY event_time (event_time)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        return dbDelta($sql) ? true : false;
    }
}
