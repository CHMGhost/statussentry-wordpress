<?php
declare(strict_types=1);

/**
 * Event queue class.
 *
 * @since      1.0.0
 * @version    1.1.0
 * @package    Status_Sentry
 * @subpackage Status_Sentry/includes/data
 */

/**
 * Event Queue Class
 *
 * This class is responsible for managing the event queue, which is a critical
 * component of the Status Sentry data pipeline. It handles the temporary storage
 * of captured events before they are processed and stored permanently.
 *
 * Key responsibilities:
 * - Enqueue events with validation and error handling
 * - Retrieve events from the queue for processing
 * - Update event status (pending, processed, failed)
 * - Delete old events to prevent database bloat
 * - Create and manage the queue database table
 * - Schedule immediate processing when the queue gets too large
 * - Support batch operations for improved performance
 * - Support resumable processing with ID-based retrieval
 * - Implement efficient cleanup strategies
 *
 * The queue uses a database table with the following structure:
 * - id: Unique identifier for the queue item
 * - feature: The monitoring feature (e.g., 'core_monitoring')
 * - hook: The WordPress hook that triggered the event
 * - data: JSON-encoded event data
 * - created_at: When the event was captured
 * - status: Processing status ('pending', 'processed', 'failed')
 *
 * @since      1.0.0
 * @version    1.2.0
 * @package    Status_Sentry
 * @subpackage Status_Sentry/includes/data
 * @author     Status Sentry Team
 */
class Status_Sentry_Event_Queue {

    /**
     * The queue table name.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $table_name    The queue table name.
     */
    private $table_name;

    /**
     * Batch size threshold for immediate processing.
     *
     * @since    1.5.0
     * @access   private
     * @var      int    $batch_threshold    Batch size threshold for immediate processing.
     */
    private $batch_threshold;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'status_sentry_queue';

        // Set default batch threshold (100) and allow it to be filtered
        $this->batch_threshold = apply_filters('status_sentry_queue_threshold', 100);
    }

    /**
     * Enqueue an event.
     *
     * This method adds an event to the queue for later processing. It performs several
     * validation and safety checks:
     * 1. Verifies the queue table exists or creates it
     * 2. Validates input data
     * 3. Handles JSON encoding errors
     * 4. Manages database insertion errors
     * 5. Schedules immediate processing if the queue gets too large
     *
     * @since    1.0.0
     * @param    array     $data       The event data to be stored.
     * @param    string    $feature    The feature this event belongs to (e.g., 'core_monitoring').
     * @param    string    $hook       The name of the WordPress hook that triggered the event.
     * @return   bool                  Whether the event was successfully enqueued.
     */
    public function enqueue(array $data, string $feature, string $hook): bool {
        global $wpdb;

        // Validate input parameters
        if (!is_array($data)) {
            error_log('Status Sentry: Failed to enqueue event - data must be an array');
            return false;
        }

        if (empty($feature) || !is_string($feature)) {
            error_log('Status Sentry: Failed to enqueue event - invalid feature');
            return false;
        }

        if (empty($hook) || !is_string($hook)) {
            error_log('Status Sentry: Failed to enqueue event - invalid hook');
            return false;
        }

        // Check if the queue table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '{$this->table_name}'") != $this->table_name) {
            // Table doesn't exist, try to create it
            $create_result = $this->create_queue_table();

            // Check again if the table exists
            if (!$create_result || $wpdb->get_var("SHOW TABLES LIKE '{$this->table_name}'") != $this->table_name) {
                // Still doesn't exist, log error and return
                error_log('Status Sentry: Failed to create queue table - ' . $wpdb->last_error);
                return false;
            }
        }

        // JSON encode the data with error handling
        $json_data = wp_json_encode($data);
        if ($json_data === false) {
            error_log('Status Sentry: Failed to JSON encode event data - ' . json_last_error_msg());

            // Try to encode a simplified version of the data
            $simplified_data = ['error' => 'Original data could not be encoded', 'partial_data' => $this->simplify_data($data)];
            $json_data = wp_json_encode($simplified_data);

            if ($json_data === false) {
                error_log('Status Sentry: Failed to encode simplified event data');
                return false;
            }
        }

        // Prepare the event data
        $event = [
            'feature' => $feature,
            'hook' => $hook,
            'data' => $json_data,
            'created_at' => current_time('mysql'),
            'status' => 'pending',
        ];

        // Insert the event into the queue
        $result = $wpdb->insert(
            $this->table_name,
            $event,
            ['%s', '%s', '%s', '%s', '%s']
        );

        if ($result === false) {
            // Insert failed, log error with detailed information
            error_log(sprintf(
                'Status Sentry: Failed to enqueue event - MySQL Error: %s, Query: %s',
                $wpdb->last_error,
                $wpdb->last_query
            ));
            return false;
        }

        // Check if we need to schedule immediate processing
        try {
            $this->maybe_schedule_processing();
        } catch (Exception $e) {
            // Log the error but don't fail the enqueue operation
            error_log('Status Sentry: Error scheduling processing - ' . $e->getMessage());
        }

        return true;
    }

    /**
     * Simplify complex data for JSON encoding.
     *
     * This method attempts to simplify complex data structures that might
     * cause JSON encoding to fail.
     *
     * @since    1.0.0
     * @access   private
     * @param    mixed     $data    The data to simplify.
     * @return   mixed              The simplified data.
     */
    private function simplify_data($data) {
        if (is_array($data)) {
            $result = [];
            foreach ($data as $key => $value) {
                // Skip circular references and resources
                if (is_resource($value)) {
                    $result[$key] = '[RESOURCE]';
                } elseif (is_object($value)) {
                    $result[$key] = '[OBJECT: ' . get_class($value) . ']';
                } elseif (is_array($value)) {
                    $result[$key] = $this->simplify_data($value);
                } else {
                    $result[$key] = $value;
                }
            }
            return $result;
        } elseif (is_object($data)) {
            return '[OBJECT: ' . get_class($data) . ']';
        } elseif (is_resource($data)) {
            return '[RESOURCE]';
        } else {
            return $data;
        }
    }

    /**
     * Get events from the queue.
     *
     * This method retrieves events from the queue for processing. It performs
     * the following operations:
     * 1. Verifies the queue table exists
     * 2. Retrieves events with the specified status
     * 3. Decodes the JSON data for each event
     * 4. Returns the events as an array
     *
     * The events are returned in ascending order by ID to ensure that older
     * events are processed first (FIFO - First In, First Out).
     *
     * @since    1.0.0
     * @version  1.1.0
     * @param    int       $limit     The maximum number of events to get.
     * @param    string    $status    The status of events to get (default: 'pending').
     * @return   array                The events as associative arrays.
     * @throws   Exception            If there is an error retrieving events.
     */
    public function get_events(int $limit = 100, string $status = 'pending'): array {
        global $wpdb;

        // Validate input parameters
        $limit = absint($limit);
        if ($limit <= 0) {
            $limit = 100; // Default to 100 if invalid
        }

        if (!in_array($status, ['pending', 'processed', 'failed'])) {
            $status = 'pending'; // Default to 'pending' if invalid
        }

        // Check if the queue table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '{$this->table_name}'") != $this->table_name) {
            error_log('Status Sentry: Queue table does not exist');
            return [];
        }

        try {
            // Get events from the queue
            $events = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$this->table_name} WHERE status = %s ORDER BY id ASC LIMIT %d",
                    $status,
                    $limit
                ),
                ARRAY_A
            );

            if ($wpdb->last_error) {
                error_log('Status Sentry: Error retrieving events from queue - ' . $wpdb->last_error);
                return [];
            }

            // Decode the data with error handling
            foreach ($events as &$event) {
                try {
                    $event['data'] = json_decode($event['data'], true);

                    // Check for JSON decoding errors
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        error_log('Status Sentry: Error decoding JSON data for event ' . $event['id'] . ' - ' . json_last_error_msg());
                        $event['data'] = [
                            'error' => 'JSON decoding failed: ' . json_last_error_msg(),
                            'raw_data' => substr($event['data'], 0, 100) . '...' // Include a snippet of the raw data
                        ];
                    }
                } catch (Exception $e) {
                    error_log('Status Sentry: Exception decoding JSON data for event ' . $event['id'] . ' - ' . $e->getMessage());
                    $event['data'] = ['error' => 'Exception during JSON decoding: ' . $e->getMessage()];
                }
            }

            return $events;
        } catch (Exception $e) {
            error_log('Status Sentry: Exception retrieving events from queue - ' . $e->getMessage());
            throw $e; // Re-throw to be caught by the caller
        }
    }

    /**
     * Update event status.
     *
     * This method updates the status of an event in the queue. It is typically
     * used by the EventProcessor to mark events as 'processed' or 'failed'
     * after attempting to process them.
     *
     * Valid status values are:
     * - 'pending': The event is waiting to be processed
     * - 'processed': The event has been successfully processed
     * - 'failed': The event processing failed
     *
     * @since    1.0.0
     * @version  1.1.0
     * @param    int       $id        The event ID.
     * @param    string    $status    The new status ('pending', 'processed', or 'failed').
     * @return   bool                 Whether the status was successfully updated.
     * @throws   Exception            If there is an error updating the status.
     */
    public function update_event_status(int $id, string $status): bool {
        global $wpdb;

        // Validate input parameters
        $id = absint($id);
        if ($id <= 0) {
            error_log('Status Sentry: Invalid event ID for status update');
            return false;
        }

        if (!in_array($status, ['pending', 'processed', 'failed'])) {
            error_log('Status Sentry: Invalid status for event update: ' . $status);
            return false;
        }

        try {
            // Update the event status
            $result = $wpdb->update(
                $this->table_name,
                ['status' => $status],
                ['id' => $id],
                ['%s'],
                ['%d']
            );

            if ($result === false) {
                error_log('Status Sentry: Error updating event status - ' . $wpdb->last_error);
                return false;
            }

            // Check if any rows were affected
            if ($result === 0) {
                // No rows were updated, but the query was successful
                // This could mean the event doesn't exist or already has the specified status
                error_log('Status Sentry: No rows affected when updating event ' . $id . ' to status ' . $status);

                // Check if the event exists
                $exists = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$this->table_name} WHERE id = %d",
                    $id
                ));

                if ($exists == 0) {
                    error_log('Status Sentry: Event ' . $id . ' does not exist');
                } else {
                    // Event exists but status wasn't changed, which is fine
                    return true;
                }
            }

            return $result !== false;
        } catch (Exception $e) {
            error_log('Status Sentry: Exception updating event status - ' . $e->getMessage());
            throw $e; // Re-throw to be caught by the caller
        }
    }

    /**
     * Delete processed events.
     *
     * This method deletes events from the queue based on their status and age.
     * It is typically used by the cleanup process to remove old processed or
     * failed events to prevent database bloat.
     *
     * The method performs the following operations:
     * 1. Verifies the queue table exists
     * 2. Calculates the cutoff time based on the specified age
     * 3. Deletes events with the specified status that are older than the cutoff time
     * 4. Returns the number of events deleted
     *
     * @since    1.0.0
     * @version  1.1.0
     * @param    string    $status    The status of events to delete ('processed', 'failed').
     * @param    int       $age       The minimum age of events to delete in seconds (default: 86400 = 1 day).
     * @return   int                  The number of events deleted.
     * @throws   Exception            If there is an error deleting events.
     */
    public function delete_events(string $status = 'processed', int $age = 86400): int {
        global $wpdb;

        // Validate input parameters
        if (!in_array($status, ['processed', 'failed'])) {
            error_log('Status Sentry: Invalid status for event deletion: ' . $status);
            return 0;
        }

        $age = absint($age);
        if ($age <= 0) {
            error_log('Status Sentry: Invalid age for event deletion: ' . $age);
            return 0;
        }

        try {
            // Check if the queue table exists
            if ($wpdb->get_var("SHOW TABLES LIKE '{$this->table_name}'") != $this->table_name) {
                error_log('Status Sentry: Queue table does not exist for event deletion');
                return 0;
            }

            // Get count before deletion for logging
            $count_before = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_name} WHERE status = %s",
                $status
            ));

            // Calculate the cutoff time
            $cutoff_time = date('Y-m-d H:i:s', time() - $age);

            // Log the deletion operation
            error_log(sprintf(
                'Status Sentry: Deleting %s events older than %s (age: %d seconds)',
                $status,
                $cutoff_time,
                $age
            ));

            // Delete events
            $result = $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$this->table_name} WHERE status = %s AND created_at < %s",
                    $status,
                    $cutoff_time
                )
            );

            if ($result === false) {
                error_log('Status Sentry: Error deleting events - ' . $wpdb->last_error);
                return 0;
            }

            // Log the result
            error_log(sprintf(
                'Status Sentry: Deleted %d %s events (out of %d total)',
                $result,
                $status,
                $count_before
            ));

            return $result;
        } catch (Exception $e) {
            error_log('Status Sentry: Exception deleting events - ' . $e->getMessage());
            throw $e; // Re-throw to be caught by the caller
        }
    }

    /**
     * Create the queue table.
     *
     * @since    1.0.0
     * @access   private
     * @return   bool    Whether the table was successfully created.
     */
    private function create_queue_table(): bool {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$this->table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            feature varchar(50) NOT NULL,
            hook varchar(100) NOT NULL,
            data longtext NOT NULL,
            created_at datetime NOT NULL,
            status varchar(20) NOT NULL,
            PRIMARY KEY  (id),
            KEY status (status),
            KEY feature (feature),
            KEY hook (hook),
            KEY created_at (created_at)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        return dbDelta($sql) ? true : false;
    }

    /**
     * Schedule immediate processing if necessary.
     *
     * This method checks if the number of pending events exceeds the batch threshold
     * and schedules immediate processing if needed. The threshold is configurable
     * via the 'status_sentry_queue_threshold' filter.
     *
     * @since    1.0.0
     * @access   private
     */
    private function maybe_schedule_processing(): void {
        global $wpdb;

        // Count pending events
        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_name} WHERE status = %s",
                'pending'
            )
        );

        // If there are more than the threshold of pending events, schedule immediate processing
        if ($count > $this->batch_threshold && !wp_next_scheduled('status_sentry_process_queue')) {
            error_log(sprintf(
                'Status Sentry: Queue size (%d) exceeds threshold (%d), scheduling immediate processing',
                $count,
                $this->batch_threshold
            ));
            wp_schedule_single_event(time(), 'status_sentry_process_queue');
        }
    }

    /**
     * Get events from the queue after a specific ID.
     *
     * This method retrieves events from the queue that have an ID greater than
     * the specified ID. It is used for resumable processing of large datasets.
     *
     * @since    1.2.0
     * @param    int       $limit     The maximum number of events to get.
     * @param    int       $after_id  The ID to start from (exclusive).
     * @param    string    $status    The status of events to get (default: 'pending').
     * @return   array                The events as associative arrays.
     * @throws   Exception            If there is an error retrieving events.
     */
    public function get_events_after_id($limit = 100, $after_id = 0, $status = 'pending') {
        global $wpdb;

        // Validate input parameters
        $limit = absint($limit);
        if ($limit <= 0) {
            $limit = 100; // Default to 100 if invalid
        }

        $after_id = absint($after_id);

        if (!in_array($status, ['pending', 'processed', 'failed'])) {
            $status = 'pending'; // Default to 'pending' if invalid
        }

        // Check if the queue table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '{$this->table_name}'") != $this->table_name) {
            error_log('Status Sentry: Queue table does not exist');
            return [];
        }

        try {
            // Get events from the queue
            $events = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$this->table_name} WHERE status = %s AND id > %d ORDER BY id ASC LIMIT %d",
                    $status,
                    $after_id,
                    $limit
                ),
                ARRAY_A
            );

            if ($wpdb->last_error) {
                error_log('Status Sentry: Error retrieving events from queue - ' . $wpdb->last_error);
                return [];
            }

            // Decode the data with error handling
            foreach ($events as &$event) {
                try {
                    $event['data'] = json_decode($event['data'], true);

                    // Check for JSON decoding errors
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        error_log('Status Sentry: Error decoding JSON data for event ' . $event['id'] . ' - ' . json_last_error_msg());
                        $event['data'] = [
                            'error' => 'JSON decoding failed: ' . json_last_error_msg(),
                            'raw_data' => substr($event['data'], 0, 100) . '...' // Include a snippet of the raw data
                        ];
                    }
                } catch (Exception $e) {
                    error_log('Status Sentry: Exception decoding JSON data for event ' . $event['id'] . ' - ' . $e->getMessage());
                    $event['data'] = ['error' => 'Exception during JSON decoding: ' . $e->getMessage()];
                }
            }

            return $events;
        } catch (Exception $e) {
            error_log('Status Sentry: Exception retrieving events from queue - ' . $e->getMessage());
            throw $e; // Re-throw to be caught by the caller
        }
    }

    /**
     * Update multiple event statuses at once.
     *
     * This method updates the status of multiple events in the queue in a single
     * database operation, improving performance for batch processing.
     *
     * @since    1.2.0
     * @param    array     $ids       The event IDs.
     * @param    string    $status    The new status ('pending', 'processed', or 'failed').
     * @return   int                  The number of events updated.
     * @throws   Exception            If there is an error updating the statuses.
     */
    public function update_event_statuses($ids, $status) {
        global $wpdb;

        // Validate input parameters
        if (empty($ids) || !is_array($ids)) {
            error_log('Status Sentry: Invalid event IDs for status update');
            return 0;
        }

        if (!in_array($status, ['pending', 'processed', 'failed'])) {
            error_log('Status Sentry: Invalid status for event update: ' . $status);
            return 0;
        }

        try {
            // Sanitize IDs
            $ids = array_map('absint', $ids);
            $ids = array_filter($ids); // Remove any zero values

            if (empty($ids)) {
                return 0;
            }

            // Convert IDs to a comma-separated string for the IN clause
            $id_string = implode(',', $ids);

            // Update the event statuses
            $result = $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$this->table_name} SET status = %s WHERE id IN ({$id_string})",
                    $status
                )
            );

            if ($result === false) {
                error_log('Status Sentry: Error updating event statuses - ' . $wpdb->last_error);
                return 0;
            }

            return $result;
        } catch (Exception $e) {
            error_log('Status Sentry: Exception updating event statuses - ' . $e->getMessage());
            throw $e; // Re-throw to be caught by the caller
        }
    }
}
