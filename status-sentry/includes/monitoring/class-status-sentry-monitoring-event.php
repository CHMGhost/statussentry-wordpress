<?php
declare(strict_types=1);

/**
 * Monitoring Event Class
 *
 * @since      1.3.0
 * @package    Status_Sentry
 * @subpackage Status_Sentry/includes/monitoring
 */

/**
 * Monitoring Event Class
 *
 * This class represents a monitoring event in the Status Sentry plugin.
 * It is designed to be immutable, meaning that once created, its properties
 * cannot be changed. This ensures data integrity throughout the event lifecycle.
 *
 * Key features:
 * - Immutable value object pattern
 * - Standardized event structure
 * - Support for event types, priorities, and contexts
 * - Rich metadata support
 * - Serialization/deserialization for storage
 *
 * @since      1.3.0
 * @package    Status_Sentry
 * @subpackage Status_Sentry/includes/monitoring
 */
class Status_Sentry_Monitoring_Event {

    /**
     * Event type constants.
     */
    const TYPE_INFO = 'info';
    const TYPE_WARNING = 'warning';
    const TYPE_ERROR = 'error';
    const TYPE_CRITICAL = 'critical';
    const TYPE_PERFORMANCE = 'performance';
    const TYPE_SECURITY = 'security';
    const TYPE_CONFLICT = 'conflict';
    const TYPE_HEALTH = 'health';

    /**
     * Priority constants.
     */
    const PRIORITY_LOW = 10;
    const PRIORITY_NORMAL = 50;
    const PRIORITY_HIGH = 80;
    const PRIORITY_CRITICAL = 100;

    /**
     * The event ID.
     *
     * @since    1.3.0
     * @access   private
     * @var      string    $id    The event ID.
     */
    private $id;

    /**
     * The event type.
     *
     * @since    1.3.0
     * @access   private
     * @var      string    $type    The event type.
     */
    private $type;

    /**
     * The event priority.
     *
     * @since    1.3.0
     * @access   private
     * @var      int    $priority    The event priority.
     */
    private $priority;

    /**
     * The event source.
     *
     * @since    1.3.0
     * @access   private
     * @var      string    $source    The event source.
     */
    private $source;

    /**
     * The event context.
     *
     * @since    1.3.0
     * @access   private
     * @var      string    $context    The event context.
     */
    private $context;

    /**
     * The event message.
     *
     * @since    1.3.0
     * @access   private
     * @var      string    $message    The event message.
     */
    private $message;

    /**
     * The event data.
     *
     * @since    1.3.0
     * @access   private
     * @var      array    $data    The event data.
     */
    private $data;

    /**
     * The event timestamp.
     *
     * @since    1.3.0
     * @access   private
     * @var      float    $timestamp    The event timestamp.
     */
    private $timestamp;

    /**
     * Initialize a new monitoring event.
     *
     * @since    1.3.0
     * @param    string    $type       The event type.
     * @param    string    $source     The event source.
     * @param    string    $context    The event context.
     * @param    string    $message    The event message.
     * @param    array     $data       Optional. The event data. Default empty array.
     * @param    int       $priority   Optional. The event priority. Default PRIORITY_NORMAL.
     */
    public function __construct(string $type, string $source, string $context, string $message, array $data = [], int $priority = self::PRIORITY_NORMAL) {
        $this->id = $this->generate_id();
        $this->type = $this->validate_type($type);
        $this->priority = $this->validate_priority($priority);
        $this->source = $source;
        $this->context = $context;
        $this->message = $message;
        $this->data = $data;
        $this->timestamp = microtime(true);
    }

    /**
     * Generate a unique event ID.
     *
     * @since    1.3.0
     * @access   private
     * @return   string    A unique event ID.
     */
    private function generate_id() {
        return uniqid('event_', true);
    }

    /**
     * Validate the event type.
     *
     * @since    1.3.0
     * @access   private
     * @param    string    $type    The event type to validate.
     * @return   string             The validated event type.
     */
    private function validate_type(string $type): string {
        $valid_types = [
            self::TYPE_INFO,
            self::TYPE_WARNING,
            self::TYPE_ERROR,
            self::TYPE_CRITICAL,
            self::TYPE_PERFORMANCE,
            self::TYPE_SECURITY,
            self::TYPE_CONFLICT,
            self::TYPE_HEALTH,
        ];

        if (!in_array($type, $valid_types)) {
            return self::TYPE_INFO; // Default to info if invalid
        }

        return $type;
    }

    /**
     * Validate the event priority.
     *
     * @since    1.3.0
     * @access   private
     * @param    int    $priority    The event priority to validate.
     * @return   int                 The validated event priority.
     */
    private function validate_priority(int $priority): int {
        $priority = intval($priority);

        if ($priority < 0) {
            return self::PRIORITY_LOW;
        }

        if ($priority > 100) {
            return self::PRIORITY_CRITICAL;
        }

        return $priority;
    }

    /**
     * Get the event ID.
     *
     * @since    1.3.0
     * @return   string    The event ID.
     */
    public function get_id() {
        return $this->id;
    }

    /**
     * Get the event type.
     *
     * @since    1.3.0
     * @return   string    The event type.
     */
    public function get_type() {
        return $this->type;
    }

    /**
     * Get the event priority.
     *
     * @since    1.3.0
     * @return   int    The event priority.
     */
    public function get_priority() {
        return $this->priority;
    }

    /**
     * Get the event source.
     *
     * @since    1.3.0
     * @return   string    The event source.
     */
    public function get_source() {
        return $this->source;
    }

    /**
     * Get the event context.
     *
     * @since    1.3.0
     * @return   string    The event context.
     */
    public function get_context() {
        return $this->context;
    }

    /**
     * Get the event message.
     *
     * @since    1.3.0
     * @return   string    The event message.
     */
    public function get_message() {
        return $this->message;
    }

    /**
     * Get the event data.
     *
     * @since    1.3.0
     * @return   array    The event data.
     */
    public function get_data() {
        return $this->data;
    }

    /**
     * Get the event timestamp.
     *
     * @since    1.3.0
     * @return   float    The event timestamp.
     */
    public function get_timestamp() {
        return $this->timestamp;
    }

    /**
     * Convert the event to an array.
     *
     * @since    1.3.0
     * @return   array    The event as an array.
     */
    public function to_array() {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'priority' => $this->priority,
            'source' => $this->source,
            'context' => $this->context,
            'message' => $this->message,
            'data' => $this->data,
            'timestamp' => $this->timestamp,
        ];
    }

    /**
     * Create an event from an array.
     *
     * @since    1.3.0
     * @param    array    $data    The event data as an array.
     * @return   Status_Sentry_Monitoring_Event    A new event instance.
     */
    public static function from_array($data) {
        $event = new self(
            $data['type'],
            $data['source'],
            $data['context'],
            $data['message'],
            $data['data'] ?? [],
            $data['priority'] ?? self::PRIORITY_NORMAL
        );

        // Override the generated ID and timestamp with the provided values
        if (isset($data['id'])) {
            $event->id = $data['id'];
        }

        if (isset($data['timestamp'])) {
            $event->timestamp = $data['timestamp'];
        }

        return $event;
    }
}
