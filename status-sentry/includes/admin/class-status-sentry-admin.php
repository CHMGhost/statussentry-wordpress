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

        // Check if setup wizard needs to be run
        add_action('admin_init', [$this, 'maybe_redirect_to_setup_wizard']);
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
