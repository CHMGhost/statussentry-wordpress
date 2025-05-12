<?php
/**
 * Admin class.
 *
 * @since      1.0.0
 * @package    Status_Sentry
 * @subpackage Status_Sentry/includes/admin
 */

/**
 * Admin class.
 *
 * This class handles the admin interface.
 *
 * @since      1.0.0
 * @package    Status_Sentry
 * @subpackage Status_Sentry/includes/admin
 */
class Status_Sentry_Admin {

    /**
     * Initialize the class.
     *
     * @since    1.0.0
     */
    public function init() {
        // Add admin menu
        add_action('admin_menu', [$this, 'add_admin_menu']);
        
        // Add admin scripts and styles
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
        
        // Add dashboard widget
        add_action('wp_dashboard_setup', [$this, 'add_dashboard_widget']);
    }

    /**
     * Add admin menu.
     *
     * @since    1.0.0
     */
    public function add_admin_menu() {
        // Add main menu
        add_menu_page(
            __('Status Sentry', 'status-sentry-wp'),
            __('Status Sentry', 'status-sentry-wp'),
            'manage_options',
            'status-sentry',
            [$this, 'render_dashboard_page'],
            'dashicons-shield',
            100
        );
        
        // Add dashboard submenu
        add_submenu_page(
            'status-sentry',
            __('Dashboard', 'status-sentry-wp'),
            __('Dashboard', 'status-sentry-wp'),
            'manage_options',
            'status-sentry',
            [$this, 'render_dashboard_page']
        );
        
        // Add settings submenu
        add_submenu_page(
            'status-sentry',
            __('Settings', 'status-sentry-wp'),
            __('Settings', 'status-sentry-wp'),
            'manage_options',
            'status-sentry-settings',
            [$this, 'render_settings_page']
        );
        
        // Add events submenu
        add_submenu_page(
            'status-sentry',
            __('Events', 'status-sentry-wp'),
            __('Events', 'status-sentry-wp'),
            'manage_options',
            'status-sentry-events',
            [$this, 'render_events_page']
        );
    }

    /**
     * Enqueue admin scripts and styles.
     *
     * @since    1.0.0
     * @param    string    $hook_suffix    The current admin page.
     */
    public function enqueue_scripts($hook_suffix) {
        // Only enqueue on plugin pages
        if (strpos($hook_suffix, 'status-sentry') === false) {
            return;
        }
        
        // Enqueue styles
        wp_enqueue_style(
            'status-sentry-admin',
            STATUS_SENTRY_PLUGIN_URL . 'assets/css/admin.css',
            [],
            STATUS_SENTRY_VERSION
        );
        
        // Enqueue scripts
        wp_enqueue_script(
            'status-sentry-admin',
            STATUS_SENTRY_PLUGIN_URL . 'assets/js/admin.js',
            ['jquery'],
            STATUS_SENTRY_VERSION,
            true
        );
        
        // Localize script
        wp_localize_script(
            'status-sentry-admin',
            'statusSentry',
            [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('status-sentry-admin'),
            ]
        );
    }

    /**
     * Add dashboard widget.
     *
     * @since    1.0.0
     */
    public function add_dashboard_widget() {
        wp_add_dashboard_widget(
            'status_sentry_dashboard_widget',
            __('Status Sentry', 'status-sentry-wp'),
            [$this, 'render_dashboard_widget']
        );
    }

    /**
     * Render dashboard page.
     *
     * @since    1.0.0
     */
    public function render_dashboard_page() {
        // Get event counts
        $event_counts = $this->get_event_counts();
        
        // Get recent events
        $recent_events = $this->get_recent_events(5);
        
        // Render the dashboard
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Status Sentry Dashboard', 'status-sentry-wp'); ?></h1>
            
            <div class="status-sentry-dashboard">
                <div class="status-sentry-dashboard-section">
                    <h2><?php echo esc_html__('Event Summary', 'status-sentry-wp'); ?></h2>
                    
                    <div class="status-sentry-dashboard-cards">
                        <?php foreach ($event_counts as $feature => $count) : ?>
                            <div class="status-sentry-dashboard-card">
                                <h3><?php echo esc_html(ucfirst(str_replace('_', ' ', $feature))); ?></h3>
                                <p class="status-sentry-dashboard-card-count"><?php echo esc_html($count); ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="status-sentry-dashboard-section">
                    <h2><?php echo esc_html__('Recent Events', 'status-sentry-wp'); ?></h2>
                    
                    <?php if (empty($recent_events)) : ?>
                        <p><?php echo esc_html__('No events found.', 'status-sentry-wp'); ?></p>
                    <?php else : ?>
                        <table class="widefat status-sentry-table">
                            <thead>
                                <tr>
                                    <th><?php echo esc_html__('Feature', 'status-sentry-wp'); ?></th>
                                    <th><?php echo esc_html__('Hook', 'status-sentry-wp'); ?></th>
                                    <th><?php echo esc_html__('Time', 'status-sentry-wp'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_events as $event) : ?>
                                    <tr>
                                        <td><?php echo esc_html(ucfirst(str_replace('_', ' ', $event->feature))); ?></td>
                                        <td><?php echo esc_html($event->hook); ?></td>
                                        <td><?php echo esc_html(human_time_diff(strtotime($event->event_time), time()) . ' ago'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        
                        <p>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=status-sentry-events')); ?>" class="button">
                                <?php echo esc_html__('View All Events', 'status-sentry-wp'); ?>
                            </a>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render settings page.
     *
     * @since    1.0.0
     */
    public function render_settings_page() {
        // Get current settings
        $settings = $this->get_settings();
        
        // Handle form submission
        if (isset($_POST['status_sentry_settings_nonce']) && wp_verify_nonce($_POST['status_sentry_settings_nonce'], 'status_sentry_settings')) {
            // Update settings
            $settings['core_monitoring'] = isset($_POST['core_monitoring']) ? 1 : 0;
            $settings['db_monitoring'] = isset($_POST['db_monitoring']) ? 1 : 0;
            $settings['conflict_detection'] = isset($_POST['conflict_detection']) ? 1 : 0;
            $settings['performance_monitoring'] = isset($_POST['performance_monitoring']) ? 1 : 0;
            
            // Save settings
            update_option('status_sentry_settings', $settings);
            
            // Show success message
            echo '<div class="notice notice-success"><p>' . esc_html__('Settings saved.', 'status-sentry-wp') . '</p></div>';
        }
        
        // Render the settings form
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Status Sentry Settings', 'status-sentry-wp'); ?></h1>
            
            <form method="post" action="">
                <?php wp_nonce_field('status_sentry_settings', 'status_sentry_settings_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php echo esc_html__('Features', 'status-sentry-wp'); ?></th>
                        <td>
                            <fieldset>
                                <legend class="screen-reader-text"><?php echo esc_html__('Features', 'status-sentry-wp'); ?></legend>
                                
                                <label for="core_monitoring">
                                    <input type="checkbox" name="core_monitoring" id="core_monitoring" value="1" <?php checked($settings['core_monitoring'], 1); ?>>
                                    <?php echo esc_html__('Core Monitoring', 'status-sentry-wp'); ?>
                                </label>
                                <br>
                                
                                <label for="db_monitoring">
                                    <input type="checkbox" name="db_monitoring" id="db_monitoring" value="1" <?php checked($settings['db_monitoring'], 1); ?>>
                                    <?php echo esc_html__('Database Monitoring', 'status-sentry-wp'); ?>
                                </label>
                                <br>
                                
                                <label for="conflict_detection">
                                    <input type="checkbox" name="conflict_detection" id="conflict_detection" value="1" <?php checked($settings['conflict_detection'], 1); ?>>
                                    <?php echo esc_html__('Conflict Detection', 'status-sentry-wp'); ?>
                                </label>
                                <br>
                                
                                <label for="performance_monitoring">
                                    <input type="checkbox" name="performance_monitoring" id="performance_monitoring" value="1" <?php checked($settings['performance_monitoring'], 1); ?>>
                                    <?php echo esc_html__('Performance Monitoring', 'status-sentry-wp'); ?>
                                </label>
                            </fieldset>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render events page.
     *
     * @since    1.0.0
     */
    public function render_events_page() {
        // Get events
        $events = $this->get_events(20);
        
        // Render the events page
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Status Sentry Events', 'status-sentry-wp'); ?></h1>
            
            <?php if (empty($events)) : ?>
                <p><?php echo esc_html__('No events found.', 'status-sentry-wp'); ?></p>
            <?php else : ?>
                <table class="widefat status-sentry-table">
                    <thead>
                        <tr>
                            <th><?php echo esc_html__('ID', 'status-sentry-wp'); ?></th>
                            <th><?php echo esc_html__('Feature', 'status-sentry-wp'); ?></th>
                            <th><?php echo esc_html__('Hook', 'status-sentry-wp'); ?></th>
                            <th><?php echo esc_html__('Time', 'status-sentry-wp'); ?></th>
                            <th><?php echo esc_html__('Actions', 'status-sentry-wp'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($events as $event) : ?>
                            <tr>
                                <td><?php echo esc_html($event->id); ?></td>
                                <td><?php echo esc_html(ucfirst(str_replace('_', ' ', $event->feature))); ?></td>
                                <td><?php echo esc_html($event->hook); ?></td>
                                <td><?php echo esc_html(human_time_diff(strtotime($event->event_time), time()) . ' ago'); ?></td>
                                <td>
                                    <a href="#" class="status-sentry-view-event" data-id="<?php echo esc_attr($event->id); ?>">
                                        <?php echo esc_html__('View', 'status-sentry-wp'); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render dashboard widget.
     *
     * @since    1.0.0
     */
    public function render_dashboard_widget() {
        // Get event counts
        $event_counts = $this->get_event_counts();
        
        // Get recent events
        $recent_events = $this->get_recent_events(3);
        
        // Render the widget
        ?>
        <div class="status-sentry-dashboard-widget">
            <div class="status-sentry-dashboard-widget-counts">
                <?php foreach ($event_counts as $feature => $count) : ?>
                    <div class="status-sentry-dashboard-widget-count">
                        <span class="status-sentry-dashboard-widget-count-label"><?php echo esc_html(ucfirst(str_replace('_', ' ', $feature))); ?></span>
                        <span class="status-sentry-dashboard-widget-count-value"><?php echo esc_html($count); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <?php if (!empty($recent_events)) : ?>
                <div class="status-sentry-dashboard-widget-events">
                    <h3><?php echo esc_html__('Recent Events', 'status-sentry-wp'); ?></h3>
                    
                    <ul>
                        <?php foreach ($recent_events as $event) : ?>
                            <li>
                                <span class="status-sentry-dashboard-widget-event-feature"><?php echo esc_html(ucfirst(str_replace('_', ' ', $event->feature))); ?></span>
                                <span class="status-sentry-dashboard-widget-event-hook"><?php echo esc_html($event->hook); ?></span>
                                <span class="status-sentry-dashboard-widget-event-time"><?php echo esc_html(human_time_diff(strtotime($event->event_time), time()) . ' ago'); ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <p>
                <a href="<?php echo esc_url(admin_url('admin.php?page=status-sentry')); ?>" class="button button-small">
                    <?php echo esc_html__('View Dashboard', 'status-sentry-wp'); ?>
                </a>
            </p>
        </div>
        <?php
    }

    /**
     * Get settings.
     *
     * @since    1.0.0
     * @return   array    The settings.
     */
    private function get_settings() {
        $defaults = [
            'core_monitoring' => 1,
            'db_monitoring' => 1,
            'conflict_detection' => 1,
            'performance_monitoring' => 1,
        ];
        
        $settings = get_option('status_sentry_settings', []);
        
        return wp_parse_args($settings, $defaults);
    }

    /**
     * Get event counts.
     *
     * @since    1.0.0
     * @return   array    The event counts.
     */
    private function get_event_counts() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'status_sentry_events';
        
        // Check if the table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            return [
                'core_monitoring' => 0,
                'db_monitoring' => 0,
                'conflict_detection' => 0,
                'performance_monitoring' => 0,
            ];
        }
        
        // Get counts for each feature
        $counts = [];
        $features = ['core_monitoring', 'db_monitoring', 'conflict_detection', 'performance_monitoring'];
        
        foreach ($features as $feature) {
            $count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table_name WHERE feature = %s",
                $feature
            ));
            
            $counts[$feature] = $count;
        }
        
        return $counts;
    }

    /**
     * Get recent events.
     *
     * @since    1.0.0
     * @param    int       $limit    The maximum number of events to get.
     * @return   array               The recent events.
     */
    private function get_recent_events($limit = 5) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'status_sentry_events';
        
        // Check if the table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            return [];
        }
        
        // Get recent events
        $events = $wpdb->get_results($wpdb->prepare(
            "SELECT id, feature, hook, event_time FROM $table_name ORDER BY event_time DESC LIMIT %d",
            $limit
        ));
        
        return $events;
    }

    /**
     * Get events.
     *
     * @since    1.0.0
     * @param    int       $limit    The maximum number of events to get.
     * @return   array               The events.
     */
    private function get_events($limit = 20) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'status_sentry_events';
        
        // Check if the table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            return [];
        }
        
        // Get events
        $events = $wpdb->get_results($wpdb->prepare(
            "SELECT id, feature, hook, event_time FROM $table_name ORDER BY event_time DESC LIMIT %d",
            $limit
        ));
        
        return $events;
    }
}
