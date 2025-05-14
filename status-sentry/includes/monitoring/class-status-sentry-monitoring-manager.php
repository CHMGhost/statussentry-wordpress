<?php
declare(strict_types=1);

/**
 * Monitoring Manager Class
 *
 * @since      1.3.0
 * @package    Status_Sentry
 * @subpackage Status_Sentry/includes/monitoring
 */

/**
 * Monitoring Manager Class
 *
 * This class serves as the central hub for the Status Sentry monitoring system.
 * It manages the registration of monitoring components and event handlers,
 * dispatches events to the appropriate handlers, and provides a unified API
 * for monitoring operations.
 *
 * Key responsibilities:
 * - Register monitoring components
 * - Register event handlers
 * - Dispatch events to appropriate handlers
 * - Track monitoring system health
 * - Provide access to monitoring components
 * - Manage monitoring configuration
 * - Implement circuit breakers and throttling
 *
 * @since      1.3.0
 * @package    Status_Sentry
 * @subpackage Status_Sentry/includes/monitoring
 */
class Status_Sentry_Monitoring_Manager {

    /**
     * The singleton instance of this class.
     *
     * @since    1.3.0
     * @access   private
     * @var      Status_Sentry_Monitoring_Manager    $instance    The singleton instance.
     */
    private static $instance = null;

    /**
     * The registered monitoring components.
     *
     * @since    1.3.0
     * @access   private
     * @var      array    $components    The registered monitoring components.
     */
    private $components = [];

    /**
     * The registered event handlers.
     *
     * @since    1.3.0
     * @access   private
     * @var      array    $handlers    The registered event handlers.
     */
    private $handlers = [];

    /**
     * The event table name.
     *
     * @since    1.3.0
     * @access   private
     * @var      string    $table_name    The event table name.
     */
    private $table_name;

    /**
     * Circuit breaker state.
     *
     * @since    1.3.0
     * @access   private
     * @var      array    $circuit_breakers    The circuit breaker state.
     */
    private $circuit_breakers = [];

    /**
     * Throttling state.
     *
     * @since    1.3.0
     * @access   private
     * @var      array    $throttles    The throttling state.
     */
    private $throttles = [];

    /**
     * Get the singleton instance of this class.
     *
     * @since    1.3.0
     * @return   Status_Sentry_Monitoring_Manager    The singleton instance.
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.3.0
     */
    private function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'status_sentry_monitoring_events';

        // Initialize circuit breakers
        $this->circuit_breakers = [
            'global' => [
                'tripped' => false,
                'trip_count' => 0,
                'last_trip' => 0,
                'reset_after' => 300, // 5 minutes
            ],
        ];

        // Initialize throttles
        $this->throttles = [
            'global' => [
                'count' => 0,
                'window_start' => time(),
                'window_size' => 60, // 1 minute
                'limit' => 1000, // 1000 events per minute
            ],
        ];

        // Ensure the events table exists
        $this->ensure_table_exists();
    }

    /**
     * Register a monitoring component.
     *
     * @since    1.3.0
     * @param    string                               $name        The component name.
     * @param    Status_Sentry_Monitoring_Interface   $component   The component instance.
     * @return   bool                                              Whether the component was successfully registered.
     */
    public function register_component($name, $component) {
        if (!($component instanceof Status_Sentry_Monitoring_Interface)) {
            error_log('Status Sentry: Cannot register component - must implement Status_Sentry_Monitoring_Interface');
            return false;
        }

        $this->components[$name] = $component;

        // Initialize the component
        $component->init();

        // Register the component's handlers
        $component->register_handlers($this);

        return true;
    }

    /**
     * Get a registered monitoring component.
     *
     * @since    1.3.0
     * @param    string    $name    The component name.
     * @return   Status_Sentry_Monitoring_Interface|null    The component instance or null if not found.
     */
    public function get_component($name) {
        return $this->components[$name] ?? null;
    }

    /**
     * Get all registered monitoring components.
     *
     * @since    1.3.0
     * @return   array    The registered monitoring components.
     */
    public function get_components() {
        return $this->components;
    }

    /**
     * Register an event handler.
     *
     * @since    1.3.0
     * @param    mixed     $handler    The handler instance or event type.
     * @param    mixed     $callback   Optional. The callback if $handler is an event type.
     * @return   bool                  Whether the handler was successfully registered.
     */
    public function register_handler($handler, $callback = null) {
        // If we're registering a handler instance
        if ($callback === null) {
            if (!($handler instanceof Status_Sentry_Monitoring_Handler_Interface)) {
                error_log('Status Sentry: Cannot register handler - must implement Status_Sentry_Monitoring_Handler_Interface');
                return false;
            }

            $priority = $handler->get_priority();
            $types = $handler->get_handled_types();

            foreach ($types as $type) {
                if (!isset($this->handlers[$type])) {
                    $this->handlers[$type] = [];
                }

                $this->handlers[$type][] = [
                    'handler' => $handler,
                    'priority' => $priority,
                ];

                // Sort handlers by priority (highest first)
                usort($this->handlers[$type], function($a, $b) {
                    return $b['priority'] - $a['priority'];
                });
            }

            return true;
        }

        // If we're registering a callback for a specific event type
        if (is_string($handler) && is_callable($callback)) {
            $type = $handler;

            if (!isset($this->handlers[$type])) {
                $this->handlers[$type] = [];
            }

            // Use a default priority of 50 (middle)
            $priority = 50;

            $this->handlers[$type][] = [
                'callback' => $callback,
                'priority' => $priority,
            ];

            // Sort handlers by priority (highest first)
            usort($this->handlers[$type], function($a, $b) {
                return $b['priority'] - $a['priority'];
            });

            return true;
        }

        error_log('Status Sentry: Cannot register handler - invalid parameters');
        return false;
    }

    /**
     * Dispatch a monitoring event.
     *
     * @since    1.3.0
     * @param    Status_Sentry_Monitoring_Event    $event    The event to dispatch.
     * @return   bool                                        Whether the event was successfully dispatched.
     */
    public function dispatch(Status_Sentry_Monitoring_Event $event): bool {
        // Check if monitoring is globally disabled
        if (!$this->is_monitoring_enabled()) {
            return false;
        }

        // Check circuit breakers
        if ($this->is_circuit_open('global')) {
            // Log that we're dropping events due to open circuit
            error_log('Status Sentry: Dropping event - global circuit breaker is open');
            return false;
        }

        // Check throttling
        if ($this->is_throttled('global')) {
            // Log that we're dropping events due to throttling
            error_log('Status Sentry: Dropping event - global throttle limit reached');
            return false;
        }

        // Increment throttle counter
        $this->increment_throttle('global');

        // Store the event
        $this->store_event($event);

        // Get handlers for this event type
        $type = $event->get_type();
        $handlers = $this->handlers[$type] ?? [];

        // Track if any handler processed the event
        $handled = false;

        // Dispatch to handlers
        foreach ($handlers as $handler_data) {
            if (isset($handler_data['handler'])) {
                $handler = $handler_data['handler'];

                // Check if this handler can handle this specific event
                if ($handler->can_handle($event)) {
                    try {
                        $result = $handler->handle($event);
                        if ($result) {
                            $handled = true;
                        }
                    } catch (Exception $e) {
                        error_log('Status Sentry: Error in event handler - ' . $e->getMessage());

                        // If a handler throws an exception, consider tripping the circuit breaker
                        $this->increment_trip_count('global');
                    }
                }
            } elseif (isset($handler_data['callback'])) {
                // For callback-based handlers
                try {
                    $result = call_user_func($handler_data['callback'], $event);
                    if ($result) {
                        $handled = true;
                    }
                } catch (Exception $e) {
                    error_log('Status Sentry: Error in event callback - ' . $e->getMessage());

                    // If a callback throws an exception, consider tripping the circuit breaker
                    $this->increment_trip_count('global');
                }
            }
        }

        return $handled;
    }

    /**
     * Create and dispatch a monitoring event.
     *
     * @since    1.3.0
     * @param    string    $type       The event type.
     * @param    string    $source     The event source.
     * @param    string    $context    The event context.
     * @param    string    $message    The event message.
     * @param    array     $data       Optional. The event data. Default empty array.
     * @param    int       $priority   Optional. The event priority. Default PRIORITY_NORMAL.
     * @return   bool                  Whether the event was successfully dispatched.
     */
    public function emit(string $type, string $source, string $context, string $message, array $data = [], int $priority = Status_Sentry_Monitoring_Event::PRIORITY_NORMAL): bool {
        $event = new Status_Sentry_Monitoring_Event($type, $source, $context, $message, $data, $priority);
        return $this->dispatch($event);
    }

    /**
     * Store a monitoring event in the database.
     *
     * @since    1.3.0
     * @access   private
     * @param    Status_Sentry_Monitoring_Event    $event    The event to store.
     * @return   bool                                        Whether the event was successfully stored.
     */
    private function store_event(Status_Sentry_Monitoring_Event $event): bool {
        global $wpdb;

        // Ensure the table exists
        if (!$this->ensure_table_exists()) {
            return false;
        }

        // Convert event to array
        $event_data = $event->to_array();

        // Prepare data for database
        $data = [
            'event_id' => $event_data['id'],
            'event_type' => $event_data['type'],
            'priority' => $event_data['priority'],
            'source' => $event_data['source'],
            'context' => $event_data['context'],
            'message' => $event_data['message'],
            'data' => wp_json_encode($event_data['data']),
            'timestamp' => date('Y-m-d H:i:s', (int)$event_data['timestamp']),
            'created_at' => current_time('mysql'),
        ];

        // Insert into database
        $result = $wpdb->insert(
            $this->table_name,
            $data,
            [
                '%s', // event_id
                '%s', // event_type
                '%d', // priority
                '%s', // source
                '%s', // context
                '%s', // message
                '%s', // data
                '%s', // timestamp
                '%s', // created_at
            ]
        );

        if ($result === false) {
            error_log('Status Sentry: Failed to store monitoring event - ' . $wpdb->last_error);
            return false;
        } else {
            error_log('Status Sentry: Stored monitoring event ID ' . $wpdb->insert_id . ' of type ' . $event_data['type'] . ' from source ' . $event_data['source']);
        }

        return true;
    }

    /**
     * Ensure the monitoring events table exists.
     *
     * @since    1.3.0
     * @access   private
     * @return   bool    Whether the table exists or was successfully created.
     */
    private function ensure_table_exists() {
        global $wpdb;

        if ($wpdb->get_var("SHOW TABLES LIKE '{$this->table_name}'") != $this->table_name) {
            // Table doesn't exist, create it
            require_once STATUS_SENTRY_PLUGIN_DIR . 'includes/db/migrations/008_create_monitoring_events_table.php';
            $migration = new Status_Sentry_Migration_CreateMonitoringEventsTable();
            return $migration->up();
        }

        return true;
    }

    /**
     * Check if monitoring is enabled.
     *
     * @since    1.3.0
     * @return   bool    Whether monitoring is enabled.
     */
    public function is_monitoring_enabled() {
        return apply_filters('status_sentry_monitoring_enabled', true);
    }

    /**
     * Check if a circuit breaker is open.
     *
     * @since    1.3.0
     * @param    string    $name    The circuit breaker name.
     * @return   bool               Whether the circuit breaker is open.
     */
    public function is_circuit_open($name) {
        if (!isset($this->circuit_breakers[$name])) {
            return false;
        }

        $breaker = $this->circuit_breakers[$name];

        // If the circuit is not tripped, it's closed
        if (!$breaker['tripped']) {
            return false;
        }

        // Check if it's time to reset the circuit
        $now = time();
        if ($now - $breaker['last_trip'] > $breaker['reset_after']) {
            // Reset the circuit
            $this->reset_circuit($name);
            return false;
        }

        return true;
    }

    /**
     * Trip a circuit breaker.
     *
     * @since    1.3.0
     * @param    string    $name    The circuit breaker name.
     * @return   void
     */
    public function trip_circuit($name) {
        if (!isset($this->circuit_breakers[$name])) {
            $this->circuit_breakers[$name] = [
                'tripped' => false,
                'trip_count' => 0,
                'last_trip' => 0,
                'reset_after' => 300, // 5 minutes
            ];
        }

        $this->circuit_breakers[$name]['tripped'] = true;
        $this->circuit_breakers[$name]['last_trip'] = time();
        $this->circuit_breakers[$name]['trip_count']++;

        error_log("Status Sentry: Circuit breaker '{$name}' tripped");
    }

    /**
     * Reset a circuit breaker.
     *
     * @since    1.3.0
     * @param    string    $name    The circuit breaker name.
     * @return   void
     */
    public function reset_circuit($name) {
        if (!isset($this->circuit_breakers[$name])) {
            return;
        }

        $this->circuit_breakers[$name]['tripped'] = false;

        error_log("Status Sentry: Circuit breaker '{$name}' reset");
    }

    /**
     * Increment the trip count for a circuit breaker.
     *
     * @since    1.3.0
     * @param    string    $name    The circuit breaker name.
     * @return   void
     */
    public function increment_trip_count($name) {
        if (!isset($this->circuit_breakers[$name])) {
            $this->circuit_breakers[$name] = [
                'tripped' => false,
                'trip_count' => 0,
                'last_trip' => 0,
                'reset_after' => 300, // 5 minutes
            ];
        }

        $this->circuit_breakers[$name]['trip_count']++;

        // Trip the circuit if the count exceeds the threshold
        if ($this->circuit_breakers[$name]['trip_count'] >= 5) {
            $this->trip_circuit($name);
        }
    }

    /**
     * Check if a throttle limit has been reached.
     *
     * @since    1.3.0
     * @param    string    $name    The throttle name.
     * @return   bool               Whether the throttle limit has been reached.
     */
    public function is_throttled($name) {
        if (!isset($this->throttles[$name])) {
            return false;
        }

        $throttle = $this->throttles[$name];

        // Check if we need to reset the window
        $now = time();
        if ($now - $throttle['window_start'] > $throttle['window_size']) {
            // Reset the window
            $this->throttles[$name]['count'] = 0;
            $this->throttles[$name]['window_start'] = $now;
            return false;
        }

        // Check if we've reached the limit
        return $throttle['count'] >= $throttle['limit'];
    }

    /**
     * Increment a throttle counter.
     *
     * @since    1.3.0
     * @param    string    $name    The throttle name.
     * @return   void
     */
    public function increment_throttle($name) {
        if (!isset($this->throttles[$name])) {
            $this->throttles[$name] = [
                'count' => 0,
                'window_start' => time(),
                'window_size' => 60, // 1 minute
                'limit' => 1000, // 1000 events per minute
            ];
        }

        $this->throttles[$name]['count']++;
    }
}
