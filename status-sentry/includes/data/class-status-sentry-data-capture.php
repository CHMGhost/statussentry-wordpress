<?php
/**
 * Data capture class.
 *
 * @since      1.0.0
 * @package    Status_Sentry
 * @subpackage Status_Sentry/includes/data
 */

/**
 * Data capture class.
 *
 * This class captures data from hooks and passes it through the data pipeline.
 *
 * @since      1.0.0
 * @package    Status_Sentry
 * @subpackage Status_Sentry/includes/data
 */
class Status_Sentry_Data_Capture {

    /**
     * The data filter instance.
     *
     * @since    1.0.0
     * @access   private
     * @var      Status_Sentry_Data_Filter    $data_filter    The data filter instance.
     */
    private $data_filter;

    /**
     * The event queue instance.
     *
     * @since    1.0.0
     * @access   private
     * @var      Status_Sentry_Event_Queue    $event_queue    The event queue instance.
     */
    private $event_queue;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     */
    public function __construct() {
        $this->data_filter = new Status_Sentry_Data_Filter();
        $this->event_queue = new Status_Sentry_Event_Queue();
    }

    /**
     * Capture data from a hook and pass it through the data pipeline.
     *
     * @since    1.0.0
     * @param    string    $feature    The feature this hook belongs to.
     * @param    string    $hook       The name of the WordPress hook.
     * @param    array     $data       The data to capture.
     */
    public function capture($feature, $hook, $data) {
        // Add metadata
        $data = $this->add_metadata($data, $feature, $hook);
        
        // Filter the data
        $filtered_data = $this->data_filter->filter($data, $feature, $hook);
        
        // Queue the event
        $this->event_queue->enqueue($filtered_data, $feature, $hook);
    }

    /**
     * Add metadata to the captured data.
     *
     * @since    1.0.0
     * @access   private
     * @param    array     $data       The data to add metadata to.
     * @param    string    $feature    The feature this hook belongs to.
     * @param    string    $hook       The name of the WordPress hook.
     * @return   array                 The data with metadata.
     */
    private function add_metadata($data, $feature, $hook) {
        // Add basic metadata
        $data['_meta'] = [
            'feature' => $feature,
            'hook' => $hook,
            'site_url' => get_site_url(),
            'plugin_version' => STATUS_SENTRY_VERSION,
            'wp_version' => get_bloginfo('version'),
            'php_version' => phpversion(),
            'server' => $_SERVER['SERVER_SOFTWARE'] ?? 'unknown',
            'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
            'request_method' => $_SERVER['REQUEST_METHOD'] ?? '',
            'user_id' => get_current_user_id(),
            'user_roles' => $this->get_current_user_roles(),
            'is_admin' => is_admin(),
            'is_ajax' => wp_doing_ajax(),
            'is_cli' => defined('WP_CLI') && WP_CLI,
            'is_cron' => defined('DOING_CRON') && DOING_CRON,
            'is_rest' => defined('REST_REQUEST') && REST_REQUEST,
        ];
        
        return $data;
    }

    /**
     * Get the current user's roles.
     *
     * @since    1.0.0
     * @access   private
     * @return   array    The current user's roles.
     */
    private function get_current_user_roles() {
        $user = wp_get_current_user();
        
        return $user->exists() ? $user->roles : [];
    }
}
