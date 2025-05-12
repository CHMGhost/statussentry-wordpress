<?php
/**
 * Monitoring Interface
 *
 * @since      1.3.0
 * @package    Status_Sentry
 * @subpackage Status_Sentry/includes/monitoring
 */

/**
 * Monitoring Interface
 *
 * This interface defines the standard methods that all monitoring components
 * must implement. It ensures a consistent API across different monitoring
 * components and allows them to be used interchangeably by the Monitoring Manager.
 *
 * Key responsibilities of monitoring components:
 * - Initialize monitoring capabilities
 * - Register event handlers with the monitoring manager
 * - Process monitoring events
 * - Report monitoring status
 * - Handle monitoring-specific configuration
 *
 * @since      1.3.0
 * @package    Status_Sentry
 * @subpackage Status_Sentry/includes/monitoring
 */
interface Status_Sentry_Monitoring_Interface {

    /**
     * Initialize the monitoring component.
     *
     * This method is called when the monitoring component is first created.
     * It should set up any necessary hooks, filters, or other initialization tasks.
     *
     * @since    1.3.0
     * @return   void
     */
    public function init();

    /**
     * Register event handlers with the monitoring manager.
     *
     * This method is called by the monitoring manager to allow the component
     * to register its event handlers. The component should use the provided
     * monitoring manager to register handlers for specific event types.
     *
     * @since    1.3.0
     * @param    Status_Sentry_Monitoring_Manager    $manager    The monitoring manager instance.
     * @return   void
     */
    public function register_handlers($manager);

    /**
     * Process a monitoring event.
     *
     * This method is called by the monitoring manager when an event that
     * this component has registered to handle is triggered. The component
     * should process the event and take appropriate action.
     *
     * @since    1.3.0
     * @param    Status_Sentry_Monitoring_Event    $event    The monitoring event to process.
     * @return   void
     */
    public function process_event($event);

    /**
     * Get the monitoring component's status.
     *
     * This method returns the current status of the monitoring component,
     * including any relevant metrics or state information.
     *
     * @since    1.3.0
     * @return   array    The component status as an associative array.
     */
    public function get_status();

    /**
     * Get the monitoring component's configuration.
     *
     * This method returns the current configuration of the monitoring component.
     * It can be used to expose configuration options to the admin interface.
     *
     * @since    1.3.0
     * @return   array    The component configuration as an associative array.
     */
    public function get_config();

    /**
     * Update the monitoring component's configuration.
     *
     * This method updates the configuration of the monitoring component.
     * It should validate the provided configuration before applying it.
     *
     * @since    1.3.0
     * @param    array    $config    The new configuration as an associative array.
     * @return   bool                Whether the configuration was successfully updated.
     */
    public function update_config($config);
}
