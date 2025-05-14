<?php
/**
 * Events Repository Class
 *
 * This class provides a centralized repository for accessing events data.
 * It handles table existence checks, retrieving events, and counting events.
 *
 * @since      1.5.0
 * @package    Status_Sentry
 * @subpackage Status_Sentry/includes/data
 */

/**
 * Events Repository Class
 *
 * @since      1.5.0
 * @package    Status_Sentry
 * @subpackage Status_Sentry/includes/data
 */
class Status_Sentry_Events_Repository {

    /**
     * The events table name.
     *
     * @since    1.5.0
     * @access   private
     * @var      string    $table_name    The events table name.
     */
    private $table_name;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.5.0
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'status_sentry_events';
    }

    /**
     * Check if the events table exists.
     *
     * @since    1.5.0
     * @return   bool    Whether the events table exists.
     */
    public function table_exists() {
        global $wpdb;

        $result = $wpdb->get_var("SHOW TABLES LIKE '{$this->table_name}'");
        $exists = ($result == $this->table_name);

        if (!$exists) {
            error_log("Status Sentry: Events table {$this->table_name} does not exist");
        }

        return $exists;
    }

    /**
     * Get events.
     *
     * @since    1.5.0
     * @param    int       $limit    The maximum number of events to get.
     * @return   array               The events.
     */
    public function get_events($limit = 20) {
        global $wpdb;

        // Check if the table exists
        if (!$this->table_exists()) {
            return [];
        }

        error_log("Status Sentry: Fetching up to {$limit} events from {$this->table_name}");

        // Get events
        $query = $wpdb->prepare(
            "SELECT id, feature, hook, data, event_time FROM {$this->table_name} ORDER BY event_time DESC LIMIT %d",
            $limit
        );

        $events = $wpdb->get_results($query);

        // Log the query and result count
        error_log("Status Sentry: Events query: {$query}");
        error_log("Status Sentry: Found " . count($events) . " events");

        if ($wpdb->last_error) {
            error_log("Status Sentry: Database error in get_events: {$wpdb->last_error}");
        }

        return $events;
    }

    /**
     * Get recent events.
     *
     * @since    1.5.0
     * @param    int       $limit    The maximum number of events to get.
     * @return   array               The recent events.
     */
    public function get_recent_events($limit = 5) {
        global $wpdb;

        // Check if the table exists
        if (!$this->table_exists()) {
            return [];
        }

        error_log("Status Sentry: Fetching up to {$limit} recent events from {$this->table_name}");

        // Get recent events
        $query = $wpdb->prepare(
            "SELECT id, feature, hook, data, event_time FROM {$this->table_name} ORDER BY event_time DESC LIMIT %d",
            $limit
        );

        $events = $wpdb->get_results($query);

        // Log the query and result count
        error_log("Status Sentry: Recent events query: {$query}");
        error_log("Status Sentry: Found " . count($events) . " recent events");

        if ($wpdb->last_error) {
            error_log("Status Sentry: Database error in get_recent_events: {$wpdb->last_error}");
        }

        return $events;
    }

    /**
     * Get event counts by feature.
     *
     * @since    1.5.0
     * @return   array    The event counts.
     */
    public function get_event_counts() {
        global $wpdb;

        // Check if the table exists
        if (!$this->table_exists()) {
            return [
                'core_monitoring' => 0,
                'db_monitoring' => 0,
                'conflict_detection' => 0,
                'performance_monitoring' => 0,
            ];
        }

        error_log("Status Sentry: Fetching event counts from {$this->table_name}");

        // Get counts for each feature
        $counts = [];
        $features = ['core_monitoring', 'db_monitoring', 'conflict_detection', 'performance_monitoring'];

        foreach ($features as $feature) {
            $query = $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_name} WHERE feature = %s",
                $feature
            );

            $count = $wpdb->get_var($query);

            // Log the query and result
            error_log("Status Sentry: Event count query for {$feature}: {$query}");
            error_log("Status Sentry: Found {$count} events for {$feature}");

            if ($wpdb->last_error) {
                error_log("Status Sentry: Database error in get_event_counts for {$feature}: {$wpdb->last_error}");
                $count = 0;
            }

            $counts[$feature] = (int) $count;
        }

        return $counts;
    }

    /**
     * Get a single event by ID.
     *
     * @since    1.5.0
     * @param    int       $id    The event ID.
     * @return   object|null     The event or null if not found.
     */
    public function get_event($id) {
        global $wpdb;

        // Check if the table exists
        if (!$this->table_exists()) {
            return null;
        }

        error_log("Status Sentry: Fetching event with ID {$id} from {$this->table_name}");

        // Get the event
        $query = $wpdb->prepare(
            "SELECT id, feature, hook, data, event_time, processed_time FROM {$this->table_name} WHERE id = %d",
            $id
        );

        $event = $wpdb->get_row($query);

        // Log the query and result
        error_log("Status Sentry: Event query: {$query}");
        error_log("Status Sentry: Event found: " . ($event ? 'yes' : 'no'));

        if ($wpdb->last_error) {
            error_log("Status Sentry: Database error in get_event: {$wpdb->last_error}");
        }

        return $event;
    }

    /**
     * Clear all events from the table.
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

        error_log("Status Sentry: Clearing all events from {$this->table_name}");

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

        error_log("Status Sentry: Deleted {$count} events from {$this->table_name}");

        return $count;
    }
}
