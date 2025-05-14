<?php
/**
 * Dashboard Settings
 *
 * @since      1.7.0
 * @package    Status_Sentry
 * @subpackage Status_Sentry/includes/admin
 */

/**
 * Dashboard Settings Class
 *
 * This class handles the settings for the dashboard, including cache TTL and stale threshold.
 *
 * @since      1.7.0
 * @package    Status_Sentry
 * @subpackage Status_Sentry/includes/admin
 */
class Status_Sentry_Dashboard_Settings {

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.7.0
     */
    public function __construct() {
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_menu', [$this, 'add_settings_page']);
    }

    /**
     * Register the settings.
     *
     * @since    1.7.0
     */
    public function register_settings() {
        // Register settings
        register_setting(
            'status_sentry_dashboard_settings',
            'status_sentry_dashboard_cache_ttl',
            [
                'type' => 'integer',
                'description' => 'Dashboard cache TTL in seconds',
                'sanitize_callback' => [$this, 'sanitize_positive_integer'],
                'default' => 10,
            ]
        );

        register_setting(
            'status_sentry_dashboard_settings',
            'status_sentry_dashboard_stale_threshold',
            [
                'type' => 'integer',
                'description' => 'Dashboard stale threshold in seconds',
                'sanitize_callback' => [$this, 'sanitize_positive_integer'],
                'default' => 300,
            ]
        );

        // Add settings section
        add_settings_section(
            'status_sentry_dashboard_settings_section',
            __('Dashboard Settings', 'status-sentry-wp'),
            [$this, 'render_settings_section'],
            'status_sentry_dashboard_settings'
        );

        // Add settings fields
        add_settings_field(
            'status_sentry_dashboard_cache_ttl',
            __('Dashboard Cache TTL (seconds)', 'status-sentry-wp'),
            [$this, 'render_cache_ttl_field'],
            'status_sentry_dashboard_settings',
            'status_sentry_dashboard_settings_section'
        );

        add_settings_field(
            'status_sentry_dashboard_stale_threshold',
            __('Stale Threshold (seconds)', 'status-sentry-wp'),
            [$this, 'render_stale_threshold_field'],
            'status_sentry_dashboard_settings',
            'status_sentry_dashboard_settings_section'
        );
    }

    /**
     * Add the settings page to the admin menu.
     *
     * @since    1.7.0
     */
    public function add_settings_page() {
        add_submenu_page(
            'options-general.php',
            __('Status Sentry Dashboard Settings', 'status-sentry-wp'),
            __('Status Sentry', 'status-sentry-wp'),
            'manage_options',
            'status-sentry-dashboard-settings',
            [$this, 'render_settings_page']
        );
    }

    /**
     * Render the settings page.
     *
     * @since    1.7.0
     */
    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Status Sentry Dashboard Settings', 'status-sentry-wp'); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('status_sentry_dashboard_settings');
                do_settings_sections('status_sentry_dashboard_settings');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render the settings section.
     *
     * @since    1.7.0
     */
    public function render_settings_section() {
        ?>
        <p><?php echo esc_html__('Configure the dashboard cache and refresh settings.', 'status-sentry-wp'); ?></p>
        <?php
    }

    /**
     * Render the cache TTL field.
     *
     * @since    1.7.0
     */
    public function render_cache_ttl_field() {
        $value = get_option('status_sentry_dashboard_cache_ttl', 10);
        ?>
        <input type="number" name="status_sentry_dashboard_cache_ttl" value="<?php echo esc_attr($value); ?>" min="1" max="3600" step="1" />
        <p class="description"><?php echo esc_html__('How long to cache dashboard data in seconds. Lower values mean more frequent database queries but fresher data. Default: 10', 'status-sentry-wp'); ?></p>
        <?php
    }

    /**
     * Render the stale threshold field.
     *
     * @since    1.7.0
     */
    public function render_stale_threshold_field() {
        $value = get_option('status_sentry_dashboard_stale_threshold', 300);
        ?>
        <input type="number" name="status_sentry_dashboard_stale_threshold" value="<?php echo esc_attr($value); ?>" min="10" max="86400" step="1" />
        <p class="description"><?php echo esc_html__('How old baseline data can be (in seconds) before it\'s considered stale and needs refreshing. Default: 300 (5 minutes)', 'status-sentry-wp'); ?></p>
        <?php
    }

    /**
     * Sanitize a positive integer.
     *
     * @since    1.7.0
     * @param    mixed    $value    The value to sanitize.
     * @return   int                The sanitized value.
     */
    public function sanitize_positive_integer($value) {
        $value = absint($value);
        return $value > 0 ? $value : 1;
    }
}

// Initialize the settings class
new Status_Sentry_Dashboard_Settings();
