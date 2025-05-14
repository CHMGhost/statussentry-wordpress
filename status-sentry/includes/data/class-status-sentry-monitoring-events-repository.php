<?php
/**
 * Monitoring Events Repository Class
 *
 * @since      1.6.0
 * @package    Status_Sentry
 * @subpackage Status_Sentry/includes/data
 */

/**
 * Monitoring Events Repository Class
 *
 * This class provides a centralized repository for accessing monitoring events data.
 * It handles table existence checks, retrieving events, and counting events.
 *
 * @since      1.6.0
 * @package    Status_Sentry
 * @subpackage Status_Sentry/includes/data
 */
class Status_Sentry_Monitoring_Events_Repository {

    /**
     * The monitoring events table name.
     *
     * @since    1.6.0
     * @access   private
     * @var      string    $table_name    The monitoring events table name.
     */
    private $table_name;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.6.0
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'status_sentry_monitoring_events';
    }

    /**
     * Check if the monitoring events table exists.
     *
     * @since    1.6.0
     * @return   bool    Whether the monitoring events table exists.
     */
    public function table_exists() {
        global $wpdb;

        $result = $wpdb->get_var("SHOW TABLES LIKE '{$this->table_name}'");
        $exists = ($result == $this->table_name);

        if (!$exists) {
            error_log("Status Sentry: Monitoring events table {$this->table_name} does not exist");
        }

        return $exists;
    }

    /**
     * Get monitoring events.
     *
     * @since    1.6.0
     * @param    int       $limit    The maximum number of events to get.
     * @return   array               The monitoring events.
     */
    public function get_events($limit = 20) {
        global $wpdb;

        // Check if the table exists
        if (!$this->table_exists()) {
            return [];
        }

        error_log("Status Sentry: Fetching up to {$limit} monitoring events from {$this->table_name}");

        // Get events
        $query = $wpdb->prepare(
            "SELECT id, event_id, event_type, priority, source, context, message, data, timestamp, created_at
            FROM {$this->table_name}
            ORDER BY timestamp DESC
            LIMIT %d",
            $limit
        );

        $events = $wpdb->get_results($query);

        // Log the query and result count
        error_log("Status Sentry: Monitoring events query: {$query}");
        error_log("Status Sentry: Found " . count($events) . " monitoring events");

        if ($wpdb->last_error) {
            error_log("Status Sentry: Database error in get_events: {$wpdb->last_error}");
        }

        return $events;
    }

    /**
     * Get recent monitoring events.
     *
     * @since    1.6.0
     * @param    int       $limit    The maximum number of events to get.
     * @return   array               The recent monitoring events.
     */
    public function get_recent_events($limit = 5) {
        global $wpdb;

        // Check if the table exists
        if (!$this->table_exists()) {
            return [];
        }

        error_log("Status Sentry: Fetching up to {$limit} recent monitoring events from {$this->table_name}");

        // Get recent events
        $query = $wpdb->prepare(
            "SELECT id, event_id, event_type, priority, source, context, message, data, timestamp, created_at
            FROM {$this->table_name}
            ORDER BY timestamp DESC
            LIMIT %d",
            $limit
        );

        $events = $wpdb->get_results($query);

        // Log the query and result count
        error_log("Status Sentry: Recent monitoring events query: {$query}");
        error_log("Status Sentry: Found " . count($events) . " recent monitoring events");

        if ($wpdb->last_error) {
            error_log("Status Sentry: Database error in get_recent_events: {$wpdb->last_error}");
        }

        return $events;
    }

    /**
     * Get event counts by type.
     *
     * @since    1.6.0
     * @return   array    The event counts.
     */
    public function get_event_counts() {
        global $wpdb;

        // Check if the table exists
        if (!$this->table_exists()) {
            return [
                'info' => 0,
                'warning' => 0,
                'error' => 0,
                'critical' => 0,
                'performance' => 0,
                'security' => 0,
                'conflict' => 0,
                'health' => 0,
            ];
        }

        error_log("Status Sentry: Fetching monitoring event counts from {$this->table_name}");

        // Get counts for each event type
        $counts = [];
        $event_types = [
            'info', 'warning', 'error', 'critical',
            'performance', 'security', 'conflict', 'health'
        ];

        foreach ($event_types as $type) {
            $query = $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_name} WHERE event_type = %s",
                $type
            );

            $count = $wpdb->get_var($query);

            // Log the query and result
            error_log("Status Sentry: Monitoring event count query for {$type}: {$query}");
            error_log("Status Sentry: Found {$count} monitoring events for {$type}");

            if ($wpdb->last_error) {
                error_log("Status Sentry: Database error in get_event_counts for {$type}: {$wpdb->last_error}");
                $count = 0;
            }

            $counts[$type] = (int) $count;
        }

        return $counts;
    }

    /**
     * Get a single monitoring event by ID.
     *
     * @since    1.6.0
     * @param    int       $id    The event ID.
     * @return   object|null     The event or null if not found.
     */
    public function get_event($id) {
        global $wpdb;

        // Check if the table exists
        if (!$this->table_exists()) {
            return null;
        }

        error_log("Status Sentry: Fetching monitoring event with ID {$id} from {$this->table_name}");

        // Get the event
        $query = $wpdb->prepare(
            "SELECT id, event_id, event_type, priority, source, context, message, data, timestamp, created_at
            FROM {$this->table_name}
            WHERE id = %d",
            $id
        );

        $event = $wpdb->get_row($query);

        // Log the query and result
        error_log("Status Sentry: Monitoring event query: {$query}");
        error_log("Status Sentry: Monitoring event found: " . ($event ? 'yes' : 'no'));

        if ($wpdb->last_error) {
            error_log("Status Sentry: Database error in get_event: {$wpdb->last_error}");
        }

        return $event;
    }

    /**
     * Get a single monitoring event by event_id.
     *
     * @since    1.6.0
     * @param    string    $event_id    The event_id.
     * @return   object|null            The event or null if not found.
     */
    public function get_event_by_event_id($event_id) {
        global $wpdb;

        // Check if the table exists
        if (!$this->table_exists()) {
            return null;
        }

        error_log("Status Sentry: Fetching monitoring event with event_id {$event_id} from {$this->table_name}");

        // Get the event
        $query = $wpdb->prepare(
            "SELECT id, event_id, event_type, priority, source, context, message, data, timestamp, created_at
            FROM {$this->table_name}
            WHERE event_id = %s",
            $event_id
        );

        $event = $wpdb->get_row($query);

        // Log the query and result
        error_log("Status Sentry: Monitoring event query: {$query}");
        error_log("Status Sentry: Monitoring event found: " . ($event ? 'yes' : 'no'));

        if ($wpdb->last_error) {
            error_log("Status Sentry: Database error in get_event_by_event_id: {$wpdb->last_error}");
        }

        return $event;
    }

    /**
     * Clear all monitoring events from the table.
     *
     * @since    1.6.0
     * @return   int    The number of rows deleted.
     */
    public function clear_all_events() {
        global $wpdb;

        // Check if the table exists
        if (!$this->table_exists()) {
            return 0;
        }

        error_log("Status Sentry: Clearing all monitoring events from {$this->table_name}");

        // Get the count of rows before deletion
        $count_query = "SELECT COUNT(*) FROM {$this->table_name}";
        $count = (int) $wpdb->get_var($count_query);

        // Delete all rows
        $result = $wpdb->query("DELETE FROM {$this->table_name}");

        // Log the result
        if ($result === false) {
            error_log("Status Sentry: Database error in clear_all_events: {$wpdb->last_error}");
            return 0;
        }

        error_log("Status Sentry: Deleted {$count} monitoring events from {$this->table_name}");

        return $count;
    }
}
