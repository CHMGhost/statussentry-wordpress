<?php
/**
 * Monitoring Handler Interface
 *
 * @since      1.3.0
 * @package    Status_Sentry
 * @subpackage Status_Sentry/includes/monitoring
 */

/**
 * Monitoring Handler Interface
 *
 * This interface defines the standard methods that all monitoring event handlers
 * must implement. It ensures a consistent API across different handlers and
 * allows them to be registered with the Monitoring Manager.
 *
 * Key responsibilities of monitoring handlers:
 * - Specify which event types they can handle
 * - Define their priority relative to other handlers
 * - Process monitoring events
 * - Determine whether they can handle a specific event
 *
 * @since      1.3.0
 * @package    Status_Sentry
 * @subpackage Status_Sentry/includes/monitoring
 */
interface Status_Sentry_Monitoring_Handler_Interface {

    /**
     * Get the handler's priority.
     *
     * This method returns the handler's priority, which determines the order
     * in which handlers are called for a given event. Higher priority handlers
     * are called first.
     *
     * @since    1.3.0
     * @return   int    The handler's priority (0-100).
     */
    public function get_priority();

    /**
     * Get the event types this handler can process.
     *
     * This method returns an array of event types that this handler can process.
     * The Monitoring Manager uses this information to determine which handlers
     * to call for a given event.
     *
     * @since    1.3.0
     * @return   array    An array of event types.
     */
    public function get_handled_types();

    /**
     * Check if this handler can handle the given event.
     *
     * This method determines whether this handler can process the given event.
     * It should check the event type, source, context, and any other relevant
     * properties to make this determination.
     *
     * @since    1.3.0
     * @param    Status_Sentry_Monitoring_Event    $event    The event to check.
     * @return   bool                                        Whether this handler can handle the event.
     */
    public function can_handle($event);

    /**
     * Handle a monitoring event.
     *
     * This method processes a monitoring event. It is called by the Monitoring
     * Manager when an event that this handler can process is triggered.
     *
     * @since    1.3.0
     * @param    Status_Sentry_Monitoring_Event    $event    The event to handle.
     * @return   bool                                        Whether the event was successfully handled.
     */
    public function handle($event);
}
