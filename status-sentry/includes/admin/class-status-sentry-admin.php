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
     * Setup wizard instance.
     *
     * @since    1.5.0
     * @access   private
     * @var      Status_Sentry_Setup_Wizard    $setup_wizard    The setup wizard instance.
     */
    private $setup_wizard;

    /**
     * Initialize the class.
     *
     * @since    1.0.0
     */
    public function init() {
        // Ensure the setup wizard form handler is registered before admin_init
        $this->setup_wizard = new Status_Sentry_Setup_Wizard();

        // Add admin menu
        add_action('admin_menu', [$this, 'add_admin_menu']);

        // Add admin scripts and styles
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);

        // Add dashboard widget
        add_action('wp_dashboard_setup', [$this, 'add_dashboard_widget']);

        // Register AJAX handlers
        add_action('wp_ajax_status_sentry_get_event', [$this, 'ajax_get_event']);
        add_action('wp_ajax_status_sentry_clear_events', [$this, 'ajax_clear_events']);
        add_action('wp_ajax_status_sentry_dismiss_legacy_notice', [$this, 'ajax_dismiss_legacy_notice']);

        // Check if setup wizard needs to be run
        add_action('admin_init', [$this, 'maybe_redirect_to_setup_wizard']);

        // Add admin notice about legacy events tab deprecation
        add_action('admin_notices', [$this, 'legacy_events_deprecation_notice']);
    }

    /**
     * Display a notice about the legacy events tab deprecation.
     *
     * @since    1.6.0
     */
    public function legacy_events_deprecation_notice() {
        // Only show on Status Sentry pages
        $screen = get_current_screen();
        if (!$screen || strpos($screen->id, 'status-sentry') === false) {
            return;
        }

        // Only show on the events page with legacy tab parameter
        if ($screen->id !== 'status-sentry_page_status-sentry-events' || !isset($_GET['tab']) || $_GET['tab'] !== 'legacy') {
            return;
        }

        // Check if the user has dismissed this notice
        $dismissed = get_user_meta(get_current_user_id(), 'status_sentry_legacy_notice_dismissed', true);
        if ($dismissed) {
            return;
        }

        ?>
        <div class="notice notice-warning is-dismissible status-sentry-legacy-notice">
            <p>
                <strong><?php echo esc_html__('Legacy Events System Deprecated', 'status-sentry-wp'); ?></strong>
            </p>
            <p>
                <?php echo esc_html__('The Legacy Events system has been deprecated in favor of the new Monitoring Events system. In future versions, this tab will be hidden by default.', 'status-sentry-wp'); ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=status-sentry-events&tab=monitoring')); ?>">
                    <?php echo esc_html__('Switch to Monitoring Events', 'status-sentry-wp'); ?>
                </a>
            </p>
            <p>
                <a href="<?php echo esc_url(admin_url('admin.php?page=status-sentry-events&tab=monitoring')); ?>" class="button button-primary">
                    <?php echo esc_html__('Switch to Monitoring Events', 'status-sentry-wp'); ?>
                </a>
                <a href="<?php echo esc_url(plugin_dir_url(STATUS_SENTRY_PLUGIN_BASENAME) . 'docs/LEGACY-EVENTS.md'); ?>" class="button" target="_blank">
                    <?php echo esc_html__('Learn More', 'status-sentry-wp'); ?>
                </a>
            </p>
        </div>
        <script>
            jQuery(document).ready(function($) {
                // Handle notice dismissal
                $(document).on('click', '.status-sentry-legacy-notice .notice-dismiss', function() {
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'status_sentry_dismiss_legacy_notice',
                            nonce: '<?php echo wp_create_nonce('status-sentry-dismiss-legacy-notice'); ?>'
                        }
                    });
                });
            });
        </script>
        <?php
    }

    /**
     * Check if setup wizard needs to be run and redirect if necessary.
     *
     * @since    1.4.0
     */
    public function maybe_redirect_to_setup_wizard() {
        // Skip redirect if the skip_setup flag is present
        if (isset($_GET['skip_setup'])) {
            return;
        }

        // Only redirect if setup is not complete, user has permissions, and we're not already on the setup page
        if (!get_option('status_sentry_setup_complete') &&
            current_user_can('manage_options') &&
            (!isset($_GET['page']) || $_GET['page'] !== 'status-sentry-setup')) {

            wp_redirect(admin_url('admin.php?page=status-sentry-setup'));
            exit;
        }
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

        // Add setup wizard submenu (hidden from menu)
        add_submenu_page(
            null, // No parent menu
            __('Setup Wizard', 'status-sentry-wp'),
            __('Setup Wizard', 'status-sentry-wp'),
            'manage_options',
            'status-sentry-setup',
            [$this, 'render_setup_wizard_page']
        );
    }

    /**
     * Enqueue admin scripts and styles.
     *
     * @since    1.0.0
     * @param    string    $hook_suffix    The current admin page.
     */
    public function enqueue_scripts($hook_suffix) {
        // Enqueue Chart.js on the WordPress dashboard page
        if ($hook_suffix === 'index.php') {
            // Enqueue Chart.js
            wp_enqueue_script(
                'chartjs',
                'https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js',
                [],
                '3.9.1',
                true
            );

            // Enqueue admin styles for the widget
            wp_enqueue_style(
                'status-sentry-admin',
                STATUS_SENTRY_PLUGIN_URL . 'assets/css/admin.css',
                [],
                STATUS_SENTRY_VERSION
            );

            // Enqueue admin script for the widget
            wp_enqueue_script(
                'status-sentry-admin',
                STATUS_SENTRY_PLUGIN_URL . 'assets/js/admin.js',
                ['jquery', 'chartjs'],
                STATUS_SENTRY_VERSION,
                true
            );

            // Localize script with REST API info for the widget
            wp_localize_script(
                'status-sentry-admin',
                'statusSentry',
                [
                    'ajaxUrl' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('status-sentry-admin'),
                    'restUrl' => esc_url_raw(rest_url('status-sentry/v1/dashboard/')),
                    'dashboardDataEndpoint' => esc_url_raw(rest_url('status-sentry/v1/dashboard/data')),
                    'restNonce' => wp_create_nonce('wp_rest'),
                    'adminUrl' => admin_url(),
                    'pluginVersion' => STATUS_SENTRY_VERSION,
                    'debug' => defined('WP_DEBUG') && WP_DEBUG,
                ]
            );

            return;
        }

        // Only enqueue on plugin pages
        if (strpos($hook_suffix, 'status-sentry') === false) {
            return;
        }

        // Enqueue jQuery UI styles
        wp_enqueue_style(
            'jquery-ui-style',
            'https://code.jquery.com/ui/1.13.2/themes/smoothness/jquery-ui.css',
            [],
            '1.13.2'
        );

        // Enqueue styles
        wp_enqueue_style(
            'status-sentry-admin',
            STATUS_SENTRY_PLUGIN_URL . 'assets/css/admin.css',
            ['jquery-ui-style'],
            STATUS_SENTRY_VERSION
        );

        // Enqueue jQuery UI scripts
        wp_enqueue_script('jquery-ui-core');
        wp_enqueue_script('jquery-ui-dialog');

        // Enqueue scripts
        wp_enqueue_script(
            'status-sentry-admin',
            STATUS_SENTRY_PLUGIN_URL . 'assets/js/admin.js',
            ['jquery', 'jquery-ui-dialog'],
            STATUS_SENTRY_VERSION,
            true
        );

        // Localize script with REST API info
        wp_localize_script(
            'status-sentry-admin',
            'statusSentry',
            [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('status-sentry-admin'),
                'restUrl' => esc_url_raw(rest_url('status-sentry/v1/dashboard/')),
                'dashboardDataEndpoint' => esc_url_raw(rest_url('status-sentry/v1/dashboard/data')),
                'restNonce' => wp_create_nonce('wp_rest'),
                'adminUrl' => admin_url(),
                'pluginVersion' => STATUS_SENTRY_VERSION,
                'debug' => defined('WP_DEBUG') && WP_DEBUG,
            ]
        );

        // Enqueue dashboard-specific assets on the main dashboard page
        if ($hook_suffix === 'toplevel_page_status-sentry') {
            // Enqueue Chart.js
            wp_enqueue_script(
                'chartjs',
                'https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js',
                [],
                '3.9.1',
                true
            );

            // Enqueue dashboard script
            wp_enqueue_script(
                'status-sentry-dashboard',
                STATUS_SENTRY_PLUGIN_URL . 'assets/js/dashboard.js',
                ['jquery', 'chartjs', 'status-sentry-admin'],
                STATUS_SENTRY_VERSION,
                true
            );

            // Enqueue dashboard styles
            wp_enqueue_style(
                'status-sentry-dashboard',
                STATUS_SENTRY_PLUGIN_URL . 'assets/css/dashboard.css',
                ['status-sentry-admin'],
                STATUS_SENTRY_VERSION
            );
        }
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
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Status Sentry Dashboard', 'status-sentry-wp'); ?></h1>

            <p>
                <a href="<?php echo esc_url(admin_url('admin.php?page=status-sentry-benchmark')); ?>" class="button">
                    <?php echo esc_html__('Run Benchmark', 'status-sentry-wp'); ?>
                </a>
            </p>

            <div id="status-sentry-dashboard-app">
                <div class="status-sentry-loading">
                    <span class="spinner is-active"></span>
                    <p><?php echo esc_html__('Loading dashboard data...', 'status-sentry-wp'); ?></p>
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

            // Check if a preset was selected
            if (isset($_POST['status_sentry_preset'])) {
                $preset = sanitize_text_field($_POST['status_sentry_preset']);

                // If a non-custom preset was selected, apply it
                if ($preset !== 'custom') {
                    // Get the config manager instance
                    $config_manager = Status_Sentry_Config_Manager::get_instance();

                    // Apply the preset configuration
                    $config_manager->apply_preset($preset);

                    // Show success message
                    echo '<div class="notice notice-success"><p>' . esc_html__('Preset applied successfully.', 'status-sentry-wp') . '</p></div>';

                    // Refresh settings from options
                    $settings = $this->get_settings();
                } else {
                    // Update feature settings
                    $settings['core_monitoring'] = isset($_POST['core_monitoring']) ? 1 : 0;
                    $settings['db_monitoring'] = isset($_POST['db_monitoring']) ? 1 : 0;
                    $settings['conflict_detection'] = isset($_POST['conflict_detection']) ? 1 : 0;
                    $settings['performance_monitoring'] = isset($_POST['performance_monitoring']) ? 1 : 0;

                    // Update performance settings
                    $settings['db_batch_size'] = isset($_POST['db_batch_size']) ? max(10, min(500, intval($_POST['db_batch_size']))) : 100;
                    $settings['memory_threshold'] = isset($_POST['memory_threshold']) ? max(50, min(95, intval($_POST['memory_threshold']))) : 80;
                    $settings['gc_cycles'] = isset($_POST['gc_cycles']) ? max(1, min(10, intval($_POST['gc_cycles']))) : 3;
                    $settings['cpu_threshold'] = isset($_POST['cpu_threshold']) ? max(30, min(90, intval($_POST['cpu_threshold']))) : 70;
                    $settings['enable_query_cache'] = isset($_POST['enable_query_cache']) ? 1 : 0;
                    $settings['query_cache_ttl'] = isset($_POST['query_cache_ttl']) ? max(300, min(86400, intval($_POST['query_cache_ttl']))) : 3600;
                    $settings['enable_resumable_tasks'] = isset($_POST['enable_resumable_tasks']) ? 1 : 0;

                    // Update retention settings
                    $settings['events_retention_days'] = isset($_POST['events_retention_days']) ? max(1, min(365, intval($_POST['events_retention_days']))) : 30;
                    $settings['processed_queue_retention_days'] = isset($_POST['processed_queue_retention_days']) ? max(1, min(30, intval($_POST['processed_queue_retention_days']))) : 7;
                    $settings['failed_queue_retention_days'] = isset($_POST['failed_queue_retention_days']) ? max(1, min(90, intval($_POST['failed_queue_retention_days']))) : 14;
                    $settings['task_runs_retention_days'] = isset($_POST['task_runs_retention_days']) ? max(1, min(90, intval($_POST['task_runs_retention_days']))) : 30;

                    // Save settings
                    update_option('status_sentry_settings', $settings);

                    // Set preset to custom
                    update_option('status_sentry_preset', 'custom');

                    // Show success message
                    echo '<div class="notice notice-success"><p>' . esc_html__('Custom settings saved.', 'status-sentry-wp') . '</p></div>';
                }
            }
        }

        // Render the settings form
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Status Sentry Settings', 'status-sentry-wp'); ?></h1>

            <p>
                <a href="<?php echo esc_url(admin_url('admin.php?page=status-sentry-setup&step=1')); ?>" class="button">
                    <?php echo esc_html__('Re-run Setup Wizard', 'status-sentry-wp'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=status-sentry-benchmark')); ?>" class="button">
                    <?php echo esc_html__('Run Benchmark', 'status-sentry-wp'); ?>
                </a>
            </p>

            <form method="post" action="">
                <?php wp_nonce_field('status_sentry_settings', 'status_sentry_settings_nonce'); ?>

                <?php
                // Get the current preset
                $current_preset = get_option('status_sentry_preset', 'balanced');
                ?>

                <div class="status-sentry-preset-selector">
                    <h2><?php echo esc_html__('Configuration Preset', 'status-sentry-wp'); ?></h2>
                    <p><?php echo esc_html__('Choose a preset configuration or customize settings manually.', 'status-sentry-wp'); ?></p>

                    <div class="preset-options">
                        <label>
                            <input type="radio" name="status_sentry_preset" value="basic" <?php checked($current_preset, 'basic'); ?>>
                            <strong><?php echo esc_html__('Basic', 'status-sentry-wp'); ?></strong>
                        </label>
                        <p class="description"><?php echo esc_html__('Minimal monitoring with low resource usage. Best for small sites or shared hosting.', 'status-sentry-wp'); ?></p>

                        <label>
                            <input type="radio" name="status_sentry_preset" value="balanced" <?php checked($current_preset, 'balanced'); ?>>
                            <strong><?php echo esc_html__('Balanced', 'status-sentry-wp'); ?></strong>
                        </label>
                        <p class="description"><?php echo esc_html__('Comprehensive monitoring with moderate resource usage. Suitable for most sites.', 'status-sentry-wp'); ?></p>

                        <label>
                            <input type="radio" name="status_sentry_preset" value="comprehensive" <?php checked($current_preset, 'comprehensive'); ?>>
                            <strong><?php echo esc_html__('Comprehensive', 'status-sentry-wp'); ?></strong>
                        </label>
                        <p class="description"><?php echo esc_html__('Maximum monitoring with higher resource usage. Best for larger sites with dedicated hosting.', 'status-sentry-wp'); ?></p>

                        <label>
                            <input type="radio" name="status_sentry_preset" value="custom" <?php checked($current_preset, 'custom'); ?>>
                            <strong><?php echo esc_html__('Custom', 'status-sentry-wp'); ?></strong>
                        </label>
                        <p class="description"><?php echo esc_html__('Manually configure all monitoring settings to your exact specifications.', 'status-sentry-wp'); ?></p>
                    </div>

                    <p>
                        <input type="submit" name="apply_preset" class="button button-secondary" value="<?php echo esc_attr__('Apply Preset', 'status-sentry-wp'); ?>">
                    </p>
                </div>

                <hr>

                <h2 class="nav-tab-wrapper">
                    <a href="#features" class="nav-tab nav-tab-active"><?php echo esc_html__('Features', 'status-sentry-wp'); ?></a>
                    <a href="#performance" class="nav-tab"><?php echo esc_html__('Performance', 'status-sentry-wp'); ?></a>
                    <a href="#retention" class="nav-tab"><?php echo esc_html__('Data Retention', 'status-sentry-wp'); ?></a>
                </h2>

                <div id="features" class="tab-content">
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php echo esc_html__('Monitoring Features', 'status-sentry-wp'); ?></th>
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
                </div>

                <div id="performance" class="tab-content" style="display: none;">
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php echo esc_html__('Database Operations', 'status-sentry-wp'); ?></th>
                            <td>
                                <label for="db_batch_size">
                                    <?php echo esc_html__('Batch Size', 'status-sentry-wp'); ?>
                                    <input type="number" name="db_batch_size" id="db_batch_size" value="<?php echo esc_attr($settings['db_batch_size']); ?>" min="10" max="500" step="10">
                                </label>
                                <p class="description"><?php echo esc_html__('Number of items to process in a single database operation. Higher values may improve performance but use more memory.', 'status-sentry-wp'); ?></p>

                                <br>

                                <label for="enable_query_cache">
                                    <input type="checkbox" name="enable_query_cache" id="enable_query_cache" value="1" <?php checked($settings['enable_query_cache'], 1); ?>>
                                    <?php echo esc_html__('Enable Query Cache', 'status-sentry-wp'); ?>
                                </label>
                                <p class="description"><?php echo esc_html__('Cache frequent database queries to reduce database load.', 'status-sentry-wp'); ?></p>

                                <br>

                                <label for="query_cache_ttl">
                                    <?php echo esc_html__('Query Cache TTL (seconds)', 'status-sentry-wp'); ?>
                                    <input type="number" name="query_cache_ttl" id="query_cache_ttl" value="<?php echo esc_attr($settings['query_cache_ttl']); ?>" min="300" max="86400" step="300">
                                </label>
                                <p class="description"><?php echo esc_html__('Time to live for cached queries in seconds. Default is 3600 (1 hour).', 'status-sentry-wp'); ?></p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><?php echo esc_html__('Memory Management', 'status-sentry-wp'); ?></th>
                            <td>
                                <label for="memory_threshold">
                                    <?php echo esc_html__('Memory Threshold (%)', 'status-sentry-wp'); ?>
                                    <input type="number" name="memory_threshold" id="memory_threshold" value="<?php echo esc_attr($settings['memory_threshold']); ?>" min="50" max="95" step="5">
                                </label>
                                <p class="description"><?php echo esc_html__('Percentage of memory limit at which garbage collection is triggered. Default is 80%.', 'status-sentry-wp'); ?></p>

                                <br>

                                <label for="gc_cycles">
                                    <?php echo esc_html__('Garbage Collection Cycles', 'status-sentry-wp'); ?>
                                    <input type="number" name="gc_cycles" id="gc_cycles" value="<?php echo esc_attr($settings['gc_cycles']); ?>" min="1" max="10" step="1">
                                </label>
                                <p class="description"><?php echo esc_html__('Number of garbage collection cycles to run when triggered. Default is 3.', 'status-sentry-wp'); ?></p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><?php echo esc_html__('CPU Management', 'status-sentry-wp'); ?></th>
                            <td>
                                <label for="cpu_threshold">
                                    <?php echo esc_html__('CPU Threshold (%)', 'status-sentry-wp'); ?>
                                    <input type="number" name="cpu_threshold" id="cpu_threshold" value="<?php echo esc_attr($settings['cpu_threshold']); ?>" min="30" max="90" step="5">
                                </label>
                                <p class="description"><?php echo esc_html__('Percentage of CPU load at which tasks are delayed. Default is 70%.', 'status-sentry-wp'); ?></p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><?php echo esc_html__('Task Processing', 'status-sentry-wp'); ?></th>
                            <td>
                                <label for="enable_resumable_tasks">
                                    <input type="checkbox" name="enable_resumable_tasks" id="enable_resumable_tasks" value="1" <?php checked($settings['enable_resumable_tasks'], 1); ?>>
                                    <?php echo esc_html__('Enable Resumable Tasks', 'status-sentry-wp'); ?>
                                </label>
                                <p class="description"><?php echo esc_html__('Allow long-running tasks to be resumed if they exceed resource limits.', 'status-sentry-wp'); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>

                <div id="retention" class="tab-content" style="display: none;">
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php echo esc_html__('Data Retention', 'status-sentry-wp'); ?></th>
                            <td>
                                <label for="events_retention_days">
                                    <?php echo esc_html__('Events Retention (days)', 'status-sentry-wp'); ?>
                                    <input type="number" name="events_retention_days" id="events_retention_days" value="<?php echo esc_attr($settings['events_retention_days']); ?>" min="1" max="365" step="1">
                                </label>
                                <p class="description"><?php echo esc_html__('Number of days to keep events in the database. Default is 30 days.', 'status-sentry-wp'); ?></p>

                                <br>

                                <label for="processed_queue_retention_days">
                                    <?php echo esc_html__('Processed Queue Items Retention (days)', 'status-sentry-wp'); ?>
                                    <input type="number" name="processed_queue_retention_days" id="processed_queue_retention_days" value="<?php echo esc_attr($settings['processed_queue_retention_days']); ?>" min="1" max="30" step="1">
                                </label>
                                <p class="description"><?php echo esc_html__('Number of days to keep processed queue items. Default is 7 days.', 'status-sentry-wp'); ?></p>

                                <br>

                                <label for="failed_queue_retention_days">
                                    <?php echo esc_html__('Failed Queue Items Retention (days)', 'status-sentry-wp'); ?>
                                    <input type="number" name="failed_queue_retention_days" id="failed_queue_retention_days" value="<?php echo esc_attr($settings['failed_queue_retention_days']); ?>" min="1" max="90" step="1">
                                </label>
                                <p class="description"><?php echo esc_html__('Number of days to keep failed queue items. Default is 14 days.', 'status-sentry-wp'); ?></p>

                                <br>

                                <label for="task_runs_retention_days">
                                    <?php echo esc_html__('Task Runs Retention (days)', 'status-sentry-wp'); ?>
                                    <input type="number" name="task_runs_retention_days" id="task_runs_retention_days" value="<?php echo esc_attr($settings['task_runs_retention_days']); ?>" min="1" max="90" step="1">
                                </label>
                                <p class="description"><?php echo esc_html__('Number of days to keep task run history. Default is 30 days.', 'status-sentry-wp'); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>

                <script>
                jQuery(document).ready(function($) {
                    // Tab navigation
                    $('.nav-tab').on('click', function(e) {
                        e.preventDefault();

                        // Hide all tab content
                        $('.tab-content').hide();

                        // Remove active class from all tabs
                        $('.nav-tab').removeClass('nav-tab-active');

                        // Show the selected tab content
                        $($(this).attr('href')).show();

                        // Add active class to the clicked tab
                        $(this).addClass('nav-tab-active');
                    });

                    // Preset selector
                    $('input[name="status_sentry_preset"]').on('change', function() {
                        if ($(this).val() === 'custom') {
                            $('.nav-tab-wrapper, .tab-content').show();
                        } else {
                            // If a preset is selected, you might want to hide the detailed settings
                            // Uncomment the line below to hide the tabs when a preset is selected
                            // $('.nav-tab-wrapper, .tab-content').hide();
                        }
                    });
                });
                </script>

                <style>
                .status-sentry-preset-selector {
                    background: #fff;
                    border: 1px solid #ccd0d4;
                    padding: 15px;
                    margin-bottom: 20px;
                }
                .status-sentry-preset-selector h2 {
                    margin-top: 0;
                }
                .preset-options {
                    margin: 15px 0;
                }
                .preset-options label {
                    display: block;
                    margin: 10px 0 5px;
                    font-weight: 600;
                }
                .preset-options input[type="radio"] {
                    margin-right: 8px;
                }
                .preset-options .description {
                    margin: 0 0 15px 24px;
                    color: #646970;
                }
                </style>

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
        // Check if legacy tab should be shown
        $show_legacy_tab = apply_filters('status_sentry_show_legacy_events_tab', false);

        // Get tab - default to 'monitoring' instead of 'legacy'
        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'monitoring';

        // If legacy tab is hidden and active tab is legacy, switch to monitoring
        if (!$show_legacy_tab && $active_tab === 'legacy') {
            $active_tab = 'monitoring';
        }

        // Render the events page
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Status Sentry Events', 'status-sentry-wp'); ?></h1>

            <p>
                <a href="<?php echo esc_url(admin_url('admin.php?page=status-sentry-benchmark')); ?>" class="button">
                    <?php echo esc_html__('Run Benchmark', 'status-sentry-wp'); ?>
                </a>
            </p>

            <h2 class="nav-tab-wrapper">
                <?php if ($show_legacy_tab) : ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=status-sentry-events&tab=legacy')); ?>" class="nav-tab <?php echo $active_tab === 'legacy' ? 'nav-tab-active' : ''; ?>">
                    <?php echo esc_html__('Legacy Events', 'status-sentry-wp'); ?>
                </a>
                <?php endif; ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=status-sentry-events&tab=monitoring')); ?>" class="nav-tab <?php echo $active_tab === 'monitoring' ? 'nav-tab-active' : ''; ?>">
                    <?php echo esc_html__('Monitoring Events', 'status-sentry-wp'); ?>
                </a>
            </h2>

            <?php if ($active_tab === 'monitoring' || !$show_legacy_tab) : ?>
                <?php $this->render_monitoring_events_tab(); ?>
            <?php else : ?>
                <?php $this->render_legacy_events_tab(); ?>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render legacy events tab.
     *
     * @since    1.6.0
     */
    private function render_legacy_events_tab() {
        // Get events
        $events = $this->get_events(20);

        // Render the legacy events tab
        ?>
        <div class="status-sentry-tab-actions">
            <button type="button" class="button status-sentry-clear-events" data-type="legacy">
                <?php echo esc_html__('Clear Legacy Events', 'status-sentry-wp'); ?>
            </button>
        </div>

        <?php if (empty($events)) : ?>
            <p><?php echo esc_html__('No legacy events found.', 'status-sentry-wp'); ?></p>
        <?php else : ?>
            <table class="widefat status-sentry-table">
                <thead>
                    <tr>
                        <th><?php echo esc_html__('ID', 'status-sentry-wp'); ?></th>
                        <th><?php echo esc_html__('Feature', 'status-sentry-wp'); ?></th>
                        <th><?php echo esc_html__('Hook', 'status-sentry-wp'); ?></th>
                        <th><?php echo esc_html__('Data', 'status-sentry-wp'); ?></th>
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
                            <td>
                                <?php
                                if (isset($event->data)) {
                                    $data = json_decode($event->data, true);
                                    if (is_array($data)) {
                                        // Show a snippet of the data (first 3 keys)
                                        $keys = array_keys($data);
                                        $snippet = array_slice($keys, 0, 3);
                                        echo esc_html(implode(', ', $snippet));
                                        if (count($keys) > 3) {
                                            echo esc_html('...');
                                        }
                                    } else {
                                        echo esc_html(substr($event->data, 0, 50));
                                        if (strlen($event->data) > 50) {
                                            echo esc_html('...');
                                        }
                                    }
                                } else {
                                    echo esc_html__('No data', 'status-sentry-wp');
                                }
                                ?>
                            </td>
                            <td><?php echo esc_html(human_time_diff(strtotime($event->event_time), time()) . ' ago'); ?></td>
                            <td>
                                <a href="#" class="status-sentry-view-event" data-id="<?php echo esc_attr($event->id); ?>" data-type="legacy">
                                    <?php echo esc_html__('View', 'status-sentry-wp'); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif;
    }

    /**
     * Render monitoring events tab.
     *
     * @since    1.6.0
     */
    private function render_monitoring_events_tab() {
        // Get monitoring events
        $repository = $this->get_monitoring_events_repository();
        $events = $repository->get_events(20);

        // Render the monitoring events tab
        ?>
        <div class="status-sentry-tab-actions">
            <button type="button" class="button status-sentry-clear-events status-sentry-clear-monitoring-events" data-type="monitoring" data-nonce="<?php echo esc_attr(wp_create_nonce('status-sentry-admin')); ?>">
                <?php echo esc_html__('Clear Monitoring Events', 'status-sentry-wp'); ?>
            </button>
        </div>

        <?php if (empty($events)) : ?>
            <p><?php echo esc_html__('No monitoring events found.', 'status-sentry-wp'); ?></p>
        <?php else : ?>
            <table class="widefat status-sentry-table">
                <thead>
                    <tr>
                        <th><?php echo esc_html__('ID', 'status-sentry-wp'); ?></th>
                        <th><?php echo esc_html__('Type', 'status-sentry-wp'); ?></th>
                        <th><?php echo esc_html__('Source', 'status-sentry-wp'); ?></th>
                        <th><?php echo esc_html__('Context', 'status-sentry-wp'); ?></th>
                        <th><?php echo esc_html__('Message', 'status-sentry-wp'); ?></th>
                        <th><?php echo esc_html__('Time', 'status-sentry-wp'); ?></th>
                        <th><?php echo esc_html__('Actions', 'status-sentry-wp'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($events as $event) : ?>
                        <tr>
                            <td><?php echo esc_html($event->id); ?></td>
                            <td>
                                <span class="status-sentry-event-type-<?php echo esc_attr($event->event_type); ?>">
                                    <?php echo esc_html(ucfirst($event->event_type)); ?>
                                </span>
                            </td>
                            <td><?php echo esc_html($event->source); ?></td>
                            <td><?php echo esc_html($event->context); ?></td>
                            <td><?php echo esc_html(substr($event->message, 0, 50) . (strlen($event->message) > 50 ? '...' : '')); ?></td>
                            <td><?php echo esc_html(human_time_diff(strtotime($event->timestamp), time()) . ' ago'); ?></td>
                            <td>
                                <a href="#" class="status-sentry-view-event" data-id="<?php echo esc_attr($event->id); ?>" data-type="monitoring">
                                    <?php echo esc_html__('View', 'status-sentry-wp'); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <style>
                .status-sentry-event-type-info { color: #0073aa; }
                .status-sentry-event-type-warning { color: #ffb900; }
                .status-sentry-event-type-error { color: #dc3232; }
                .status-sentry-event-type-critical { color: #dc3232; font-weight: bold; }
                .status-sentry-event-type-performance { color: #46b450; }
                .status-sentry-event-type-security { color: #826eb4; }
                .status-sentry-event-type-conflict { color: #00a0d2; }
                .status-sentry-event-type-health { color: #00a0d2; }
                .status-sentry-tab-actions { margin: 15px 0; text-align: right; }
                .status-sentry-tab-actions .button { margin-left: 10px; }
            </style>
        <?php endif;
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

        // Get monitoring events repository to get event type counts
        $repository = $this->get_monitoring_events_repository();
        $event_type_counts = $repository->get_event_counts();

        // Determine overall status based on event types
        $overall_status = 'good';
        if (isset($event_type_counts['critical']) && $event_type_counts['critical'] > 0) {
            $overall_status = 'error';
        } elseif ((isset($event_type_counts['error']) && $event_type_counts['error'] > 0) ||
                 (isset($event_type_counts['warning']) && $event_type_counts['warning'] > 0)) {
            $overall_status = 'warning';
        }

        // Render the widget
        ?>
        <div class="status-sentry-dashboard-widget">
            <div class="status-sentry-dashboard-widget-header">
                <div class="status-sentry-dashboard-widget-status">
                    <span class="status-sentry-widget-status-indicator status-<?php echo esc_attr($overall_status); ?>"></span>
                    <span class="status-sentry-widget-status-text">
                        <?php
                        if ($overall_status === 'error') {
                            echo esc_html__('Critical issues detected', 'status-sentry-wp');
                        } elseif ($overall_status === 'warning') {
                            echo esc_html__('Warnings detected', 'status-sentry-wp');
                        } else {
                            echo esc_html__('System healthy', 'status-sentry-wp');
                        }
                        ?>
                    </span>
                </div>
                <div class="status-sentry-widget-status-dots">
                    <?php foreach (['info', 'warning', 'error', 'critical'] as $type) : ?>
                        <?php $count = isset($event_type_counts[$type]) ? $event_type_counts[$type] : 0; ?>
                        <?php if ($count > 0) : ?>
                            <span class="status-sentry-widget-status-item status-<?php echo $type === 'info' ? 'good' : $type; ?>" title="<?php echo esc_attr(ucfirst($type) . ': ' . $count); ?>"></span>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="status-sentry-dashboard-widget-sparkline">
                <canvas id="status-sentry-widget-sparkline" height="30"></canvas>
            </div>

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
                                <?php
                                // Determine if this is a monitoring event or legacy event
                                $is_monitoring = isset($event->is_monitoring_event) && $event->is_monitoring_event;

                                // Get feature name
                                $feature = $event->feature;
                                $feature_name = ucfirst(str_replace('_', ' ', $feature));

                                // Get hook/source
                                $hook = $event->hook;

                                // Get time ago
                                $time_field = $is_monitoring ? $event->event_time : $event->event_time;
                                $time_ago = human_time_diff(strtotime($time_field), time()) . ' ago';
                                ?>
                                <span class="status-sentry-dashboard-widget-event-feature"><?php echo esc_html($feature_name); ?></span>
                                <span class="status-sentry-dashboard-widget-event-hook"><?php echo esc_html($hook); ?></span>
                                <span class="status-sentry-dashboard-widget-event-time"><?php echo esc_html($time_ago); ?></span>
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

        <script>
            // This script will be executed when the widget is loaded
            jQuery(document).ready(function($) {
                // Initialize the sparkline chart if Chart.js is loaded
                if (typeof Chart !== 'undefined' && $('#status-sentry-widget-sparkline').length) {
                    // Fetch data from the dashboard data endpoint
                    $.ajax({
                        url: statusSentry.dashboardDataEndpoint,
                        method: 'GET',
                        beforeSend: function(xhr) {
                            xhr.setRequestHeader('X-WP-Nonce', statusSentry.restNonce);
                        },
                        success: function(response) {
                            if (response && response.timeline && response.timeline.labels) {
                                // Create a simplified dataset for the sparkline
                                var sparklineData = {
                                    labels: response.timeline.labels,
                                    datasets: [{
                                        label: 'Events',
                                        data: [],
                                        borderColor: '#0073aa',
                                        backgroundColor: 'rgba(0, 115, 170, 0.2)',
                                        borderWidth: 1,
                                        fill: true,
                                        tension: 0.4
                                    }]
                                };

                                // Sum all event types for each day
                                for (var i = 0; i < response.timeline.labels.length; i++) {
                                    var daySum = 0;
                                    response.timeline.datasets.forEach(function(dataset) {
                                        daySum += dataset.data[i];
                                    });
                                    sparklineData.datasets[0].data.push(daySum);
                                }

                                // Create the sparkline chart
                                var ctx = document.getElementById('status-sentry-widget-sparkline').getContext('2d');
                                new Chart(ctx, {
                                    type: 'line',
                                    data: sparklineData,
                                    options: {
                                        responsive: true,
                                        maintainAspectRatio: false,
                                        plugins: {
                                            legend: {
                                                display: false
                                            },
                                            tooltip: {
                                                enabled: true
                                            }
                                        },
                                        scales: {
                                            x: {
                                                display: false
                                            },
                                            y: {
                                                display: false,
                                                beginAtZero: true
                                            }
                                        },
                                        elements: {
                                            point: {
                                                radius: 0
                                            }
                                        }
                                    }
                                });
                            }
                        },
                        error: function(error) {
                            console.error('Error fetching dashboard data:', error);
                        }
                    });
                }
            });
        </script>
        <?php
    }



    /**
     * Render setup wizard page.
     *
     * @since    1.4.0
     */
    public function render_setup_wizard_page() {
        // Debug: Log setup wizard page render
        error_log('Status Sentry Admin: Rendering setup wizard page');
        error_log('GET data: ' . print_r($_GET, true));
        error_log('POST data: ' . print_r($_POST, true));

        // Handle skip setup action
        if (isset($_GET['skip_setup'])) {
            error_log('Status Sentry Admin: Skipping setup wizard');
            update_option('status_sentry_setup_complete', true);
            wp_redirect(admin_url('admin.php?page=status-sentry'));
            exit;
        }

        // Use the existing setup wizard instance that was created in init()
        // This ensures the same instance that registered the process_form hook is used
        if (!isset($this->setup_wizard)) {
            error_log('Status Sentry Admin: Setup wizard instance not found, creating new instance');
            $this->setup_wizard = new Status_Sentry_Setup_Wizard();
        }

        // Render the wizard
        $this->setup_wizard->render();
    }

    /**
     * Get settings.
     *
     * @since    1.0.0
     * @return   array    The settings.
     */
    private function get_settings() {
        $defaults = [
            // Feature settings
            'core_monitoring' => 1,
            'db_monitoring' => 1,
            'conflict_detection' => 1,
            'performance_monitoring' => 1,

            // Performance settings
            'db_batch_size' => 100,
            'memory_threshold' => 80,
            'gc_cycles' => 3,
            'cpu_threshold' => 70,
            'enable_query_cache' => 1,
            'query_cache_ttl' => 3600,
            'enable_resumable_tasks' => 1,
            'events_retention_days' => 30,
            'processed_queue_retention_days' => 7,
            'failed_queue_retention_days' => 14,
            'task_runs_retention_days' => 30,
        ];

        $settings = get_option('status_sentry_settings', []);

        return wp_parse_args($settings, $defaults);
    }

    /**
     * Get events repository.
     *
     * @since    1.5.0
     * @return   Status_Sentry_Events_Repository    The events repository.
     */
    private function get_events_repository() {
        static $repository = null;

        if ($repository === null) {
            require_once STATUS_SENTRY_PLUGIN_DIR . 'includes/data/class-status-sentry-events-repository.php';
            $repository = new Status_Sentry_Events_Repository();
        }

        return $repository;
    }

    /**
     * Get monitoring events repository.
     *
     * @since    1.6.0
     * @return   Status_Sentry_Monitoring_Events_Repository    The monitoring events repository.
     */
    private function get_monitoring_events_repository() {
        static $repository = null;

        if ($repository === null) {
            require_once STATUS_SENTRY_PLUGIN_DIR . 'includes/data/class-status-sentry-monitoring-events-repository.php';
            $repository = new Status_Sentry_Monitoring_Events_Repository();
        }

        return $repository;
    }

    /**
     * Get event counts.
     *
     * @since    1.0.0
     * @return   array    The event counts.
     */
    private function get_event_counts() {
        $repository = $this->get_monitoring_events_repository();
        $counts = $repository->get_event_counts();

        // Map monitoring event type counts to legacy feature keys
        return [
            'core_monitoring' => ($counts['info'] ?? 0) + ($counts['warning'] ?? 0) + ($counts['error'] ?? 0),
            'db_monitoring' => ($counts['critical'] ?? 0),
            'conflict_detection' => ($counts['conflict'] ?? 0),
            'performance_monitoring' => ($counts['performance'] ?? 0),
        ];
    }

    /**
     * Get recent events.
     *
     * @since    1.0.0
     * @param    int       $limit    The maximum number of events to get.
     * @return   array               The recent events.
     */
    private function get_recent_events($limit = 5) {
        $repository = $this->get_monitoring_events_repository();
        $events = $repository->get_recent_events($limit);

        // Log the events for debugging
        error_log('Status Sentry: Retrieved ' . count($events) . ' monitoring events for dashboard widget');

        // Transform monitoring events to match legacy event structure for backward compatibility
        $transformed_events = [];
        foreach ($events as $event) {
            $feature = $this->map_event_type_to_feature($event->event_type);

            // Create a properly formatted event object with all required fields
            $transformed_event = (object) [
                'id' => $event->id,
                'feature' => $feature,
                'hook' => $event->source . '/' . $event->context,
                'event_time' => $event->timestamp,
                'data' => $event->data,
                // Add monitoring-specific fields
                'event_type' => $event->event_type,
                'priority' => $event->priority,
                'source' => $event->source,
                'context' => $event->context,
                'message' => $event->message,
                'is_monitoring_event' => true
            ];

            $transformed_events[] = $transformed_event;
        }

        // If no monitoring events were found, try to get legacy events as a fallback
        if (empty($transformed_events)) {
            error_log('Status Sentry: No monitoring events found, trying legacy events');
            $legacy_repository = $this->get_events_repository();
            $legacy_events = $legacy_repository->get_recent_events($limit);

            if (!empty($legacy_events)) {
                error_log('Status Sentry: Found ' . count($legacy_events) . ' legacy events');
                return $legacy_events;
            }
        }

        return $transformed_events;
    }

    /**
     * Map event type to legacy feature.
     *
     * @since    1.6.0
     * @param    string    $event_type    The event type.
     * @return   string                   The legacy feature.
     */
    private function map_event_type_to_feature($event_type) {
        switch ($event_type) {
            case 'info':
            case 'warning':
            case 'error':
                return 'core_monitoring';
            case 'critical':
                return 'db_monitoring';
            case 'conflict':
                return 'conflict_detection';
            case 'performance':
                return 'performance_monitoring';
            default:
                return 'core_monitoring';
        }
    }

    /**
     * Get events.
     *
     * @since    1.0.0
     * @param    int       $limit    The maximum number of events to get.
     * @return   array               The events.
     */
    private function get_events($limit = 20) {
        $repository = $this->get_monitoring_events_repository();
        $events = $repository->get_events($limit);

        // Transform monitoring events to match legacy event structure for backward compatibility
        $transformed_events = [];
        foreach ($events as $event) {
            $feature = $this->map_event_type_to_feature($event->event_type);

            $transformed_event = (object) [
                'id' => $event->id,
                'feature' => $feature,
                'hook' => $event->source . '/' . $event->context,
                'event_time' => $event->timestamp,
                'data' => $event->data,
                // Add monitoring-specific fields
                'event_type' => $event->event_type,
                'priority' => $event->priority,
                'source' => $event->source,
                'context' => $event->context,
                'message' => $event->message,
                'is_monitoring_event' => true
            ];

            $transformed_events[] = $transformed_event;
        }

        return $transformed_events;
    }

    /**
     * AJAX handler for getting a single event.
     *
     * @since    1.5.0
     */
    public function ajax_get_event() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'status-sentry-admin')) {
            wp_send_json_error('Security check failed.');
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error('You do not have permission to view this event.');
        }

        // Get event ID
        $event_id = isset($_POST['event_id']) ? intval($_POST['event_id']) : 0;
        if ($event_id <= 0) {
            wp_send_json_error('Invalid event ID.');
        }

        // Check if this is a monitoring event
        $event_type = isset($_POST['event_type']) ? sanitize_text_field($_POST['event_type']) : 'legacy';

        if ($event_type === 'monitoring') {
            // Get monitoring event
            $repository = $this->get_monitoring_events_repository();
            $event = $repository->get_event($event_id);

            // Check if event exists
            if (!$event) {
                wp_send_json_error('Monitoring event not found.');
            }

            // Format monitoring event data
            $event_data = [
                'id' => $event->id,
                'event_id' => $event->event_id,
                'type' => $event->event_type,
                'priority' => $event->priority,
                'source' => $event->source,
                'context' => $event->context,
                'message' => $event->message,
                'event_time' => human_time_diff(strtotime($event->timestamp), time()) . ' ago',
                'timestamp' => $event->timestamp,
                'created_at' => $event->created_at,
                'is_monitoring_event' => true,
                'data' => null
            ];

            // Parse JSON data if available
            if (isset($event->data) && !empty($event->data)) {
                $data = json_decode($event->data, true);
                $event_data['data'] = $data ? $data : $event->data;
            }

            // Get performance metrics if this is a performance event
            if ($event->event_type === 'performance' && isset($event_data['data'])) {
                $event_data['performance_metrics'] = $this->extract_performance_metrics($event_data['data']);
            }
        } else {
            // Get legacy event
            $repository = $this->get_events_repository();
            $event = $repository->get_event($event_id);

            // Check if event exists
            if (!$event) {
                wp_send_json_error('Event not found.');
            }

            // Format legacy event data
            $event_data = [
                'id' => $event->id,
                'feature' => ucfirst(str_replace('_', ' ', $event->feature)),
                'hook' => $event->hook,
                'event_time' => human_time_diff(strtotime($event->event_time), time()) . ' ago',
                'is_monitoring_event' => false,
                'data' => null
            ];

            // Parse JSON data if available
            if (isset($event->data) && !empty($event->data)) {
                $data = json_decode($event->data, true);
                $event_data['data'] = $data ? $data : $event->data;
            }
        }

        // Send response
        wp_send_json_success($event_data);
    }

    /**
     * Extract performance metrics from event data.
     *
     * @since    1.6.0
     * @param    array    $data    The event data.
     * @return   array             The performance metrics.
     */
    private function extract_performance_metrics($data) {
        $metrics = [];

        // Common performance metrics to extract
        $metric_keys = [
            'memory_usage', 'memory_peak', 'memory_limit', 'memory_usage_percent',
            'cpu_load', 'execution_time', 'query_count', 'query_time',
            'http_requests', 'http_time', 'cache_hits', 'cache_misses'
        ];

        foreach ($metric_keys as $key) {
            if (isset($data[$key])) {
                $metrics[$key] = $data[$key];
            }
        }

        return $metrics;
    }

    /**
     * AJAX handler for clearing events.
     *
     * @since    1.6.0
     */
    public function ajax_clear_events() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'status-sentry-admin')) {
            wp_send_json_error('Security check failed.');
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error('You do not have permission to clear events.');
        }

        // Get event type
        $type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : 'legacy';

        // Clear events based on type
        if ($type === 'monitoring') {
            // Clear monitoring events
            $repository = $this->get_monitoring_events_repository();
            $count = $repository->clear_all_events();

            // Log the operation
            error_log('Status Sentry: Cleared ' . $count . ' monitoring events');

            // Delete dashboard transients to refresh all cached data
            delete_transient('status_sentry_dashboard_recent');
            delete_transient('status_sentry_dashboard_overview');
            delete_transient('status_sentry_dashboard_trends');

            wp_send_json_success([
                'message' => sprintf(__('Successfully cleared %d monitoring events.', 'status-sentry-wp'), $count),
                'deleted' => $count
            ]);
        } else if ($type === 'legacy') {
            // Clear legacy events
            $repository = $this->get_events_repository();
            $count = $repository->clear_all_events();

            // Log the operation
            error_log('Status Sentry: Cleared ' . $count . ' legacy events');

            // Delete dashboard transients to refresh all cached data
            delete_transient('status_sentry_dashboard_recent');
            delete_transient('status_sentry_dashboard_overview');
            delete_transient('status_sentry_dashboard_trends');

            wp_send_json_success([
                'message' => sprintf(__('Successfully cleared %d legacy events.', 'status-sentry-wp'), $count),
                'deleted' => $count
            ]);
        } else if ($type === 'all') {
            // Clear both monitoring and legacy events
            $monitoring_repository = $this->get_monitoring_events_repository();
            $monitoring_count = $monitoring_repository->clear_all_events();

            $legacy_repository = $this->get_events_repository();
            $legacy_count = $legacy_repository->clear_all_events();

            $total_count = $monitoring_count + $legacy_count;

            // Log the operation
            error_log('Status Sentry: Cleared ' . $monitoring_count . ' monitoring events and ' . $legacy_count . ' legacy events');

            // Delete dashboard transients to refresh all cached data
            delete_transient('status_sentry_dashboard_recent');
            delete_transient('status_sentry_dashboard_overview');
            delete_transient('status_sentry_dashboard_trends');

            wp_send_json_success([
                'message' => sprintf(__('Successfully cleared %d events (%d monitoring, %d legacy).', 'status-sentry-wp'), $total_count, $monitoring_count, $legacy_count),
                'deleted' => $total_count
            ]);
        } else {
            // Invalid type
            wp_send_json_error('Invalid event type specified.');
        }
    }

    /**
     * AJAX handler for dismissing the legacy events notice.
     *
     * @since    1.6.0
     */
    public function ajax_dismiss_legacy_notice() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'status-sentry-dismiss-legacy-notice')) {
            wp_send_json_error('Security check failed.');
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error('You do not have permission to dismiss this notice.');
        }

        // Update user meta to mark notice as dismissed
        update_user_meta(get_current_user_id(), 'status_sentry_legacy_notice_dismissed', true);

        wp_send_json_success();
    }
}
