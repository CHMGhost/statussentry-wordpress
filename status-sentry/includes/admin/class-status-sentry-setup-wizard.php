<?php
/**
 * Setup Wizard class.
 *
 * @since      1.4.0
 * @package    Status_Sentry
 * @subpackage Status_Sentry/includes/admin
 */

/**
 * Setup Wizard class.
 *
 * This class handles the setup wizard for first-time plugin configuration.
 *
 * @since      1.4.0
 * @package    Status_Sentry
 * @subpackage Status_Sentry/includes/admin
 */
class Status_Sentry_Setup_Wizard {

    /**
     * The current step of the wizard.
     *
     * @since    1.4.0
     * @access   private
     * @var      int    $step    The current step of the wizard.
     */
    private $step = 1;

    /**
     * Initialize the class.
     *
     * @since    1.4.0
     */
    public function __construct() {
        // Get the current step from the URL
        if (isset($_GET['step']) && is_numeric($_GET['step'])) {
            $this->step = intval($_GET['step']);
        }

        // Process form submissions
        add_action('admin_init', [$this, 'process_form']);
    }

    /**
     * Process form submissions.
     *
     * @since    1.4.0
     */
    public function process_form() {
        // Get the output buffer instance
        $buffer = \Status_Sentry_Output_Buffer::get_instance();

        // Only process if we're on the setup wizard page
        if (!isset($_GET['page']) || $_GET['page'] !== 'status-sentry-setup') {
            return;
        }

        // Debug: Log form submission
        error_log('Status Sentry Setup Wizard: Form submission detected');
        error_log('POST data: ' . print_r($_POST, true));
        error_log('Current step: ' . $this->step);

        // Check if form was submitted
        if (!isset($_POST['status_sentry_setup_nonce'])) {
            error_log('Status Sentry Setup Wizard: Nonce not found in POST data');
            return;
        }

        // Verify nonce
        if (!wp_verify_nonce($_POST['status_sentry_setup_nonce'], 'status_sentry_setup')) {
            error_log('Status Sentry Setup Wizard: Nonce verification failed');
            wp_die(__('Security check failed. Please try again.', 'status-sentry-wp'));
        }

        error_log('Status Sentry Setup Wizard: Nonce verified successfully');

        // Check if quick setup was requested
        if (isset($_POST['quick_setup'])) {
            error_log('Status Sentry Setup Wizard: Quick setup requested');
            $this->quick_setup();
            return;
        }

        // Check for the hidden step field
        $submitted_step = isset($_POST['status_sentry_setup_step']) ? intval($_POST['status_sentry_setup_step']) : 0;
        error_log('Status Sentry Setup Wizard: Submitted step from form: ' . $submitted_step);

        // Use the submitted step if available, otherwise use the URL step
        $process_step = ($submitted_step > 0) ? $submitted_step : $this->step;
        error_log('Status Sentry Setup Wizard: Processing step: ' . $process_step);

        // Process based on the step
        switch ($process_step) {
            case 1:
                // Welcome step - just proceed to next step
                error_log('Status Sentry Setup Wizard: Processing step 1, redirecting to step 2');
                // End output buffer before redirect
                $buffer->end();
                wp_redirect(admin_url('admin.php?page=status-sentry-setup&step=2'));
                exit;
                break;

            case 2:
                // Preset selection step
                error_log('Status Sentry Setup Wizard: Processing step 2, saving preset selection');
                $preset = isset($_POST['status_sentry_preset']) ? sanitize_text_field($_POST['status_sentry_preset']) : 'balanced';

                // Save the selected preset
                update_option('status_sentry_preset', $preset);

                // Apply the preset configuration
                $config_manager = Status_Sentry_Config_Manager::get_instance();
                $config_manager->apply_preset($preset);

                // If custom preset is selected, go to features step, otherwise skip to completion
                if ($preset === 'custom') {
                    error_log('Status Sentry Setup Wizard: Custom preset selected, redirecting to features step');
                    $buffer->end();
                    wp_redirect(admin_url('admin.php?page=status-sentry-setup&step=3'));
                } else {
                    error_log('Status Sentry Setup Wizard: Preset ' . $preset . ' selected, redirecting to completion step');
                    $buffer->end();
                    wp_redirect(admin_url('admin.php?page=status-sentry-setup&step=5'));
                }
                exit;
                break;

            case 3:
                // Feature selection step
                error_log('Status Sentry Setup Wizard: Processing step 3, saving features and redirecting to step 4');
                $this->save_feature_settings();
                // End output buffer before redirect
                $buffer->end();
                wp_redirect(admin_url('admin.php?page=status-sentry-setup&step=4'));
                exit;
                break;

            case 4:
                // Retention settings step
                error_log('Status Sentry Setup Wizard: Processing step 4, saving retention settings and redirecting to step 5');
                $this->save_retention_settings();

                // Mark as custom preset
                update_option('status_sentry_preset', 'custom');

                // End output buffer before redirect
                $buffer->end();
                wp_redirect(admin_url('admin.php?page=status-sentry-setup&step=5'));
                exit;
                break;

            case 5:
                // Final step - complete setup
                error_log('Status Sentry Setup Wizard: Processing step 5, completing setup and redirecting to dashboard');
                $this->complete_setup();
                // End output buffer before redirect
                $buffer->end();
                wp_redirect(admin_url('admin.php?page=status-sentry'));
                exit;
                break;

            default:
                // Invalid step - redirect to step 1
                error_log('Status Sentry Setup Wizard: Invalid step, redirecting to step 1');
                // End output buffer before redirect
                $buffer->end();
                wp_redirect(admin_url('admin.php?page=status-sentry-setup&step=1'));
                exit;
                break;
        }
    }

    /**
     * Save feature settings.
     *
     * @since    1.4.0
     */
    private function save_feature_settings() {
        // Get existing settings
        $settings = get_option('status_sentry_settings', []);

        // Update feature settings
        $settings['core_monitoring'] = isset($_POST['core_monitoring']) ? 1 : 0;
        $settings['db_monitoring'] = isset($_POST['db_monitoring']) ? 1 : 0;
        $settings['conflict_detection'] = isset($_POST['conflict_detection']) ? 1 : 0;
        $settings['performance_monitoring'] = isset($_POST['performance_monitoring']) ? 1 : 0;

        // Save settings
        update_option('status_sentry_settings', $settings);
    }

    /**
     * Save retention settings.
     *
     * @since    1.4.0
     */
    private function save_retention_settings() {
        // Get existing settings
        $settings = get_option('status_sentry_settings', []);

        // Update retention settings
        $settings['events_retention_days'] = isset($_POST['events_retention_days']) ?
            max(1, min(365, intval($_POST['events_retention_days']))) : 30;

        $settings['processed_queue_retention_days'] = isset($_POST['processed_queue_retention_days']) ?
            max(1, min(30, intval($_POST['processed_queue_retention_days']))) : 7;

        $settings['failed_queue_retention_days'] = isset($_POST['failed_queue_retention_days']) ?
            max(1, min(90, intval($_POST['failed_queue_retention_days']))) : 14;

        $settings['task_runs_retention_days'] = isset($_POST['task_runs_retention_days']) ?
            max(1, min(90, intval($_POST['task_runs_retention_days']))) : 30;

        // Save settings
        update_option('status_sentry_settings', $settings);
    }

    /**
     * Complete the setup.
     *
     * @since    1.4.0
     */
    private function complete_setup() {
        // Mark setup as complete
        update_option('status_sentry_setup_complete', true);
    }

    /**
     * Perform quick setup with balanced preset.
     *
     * This method applies the balanced preset and marks setup as complete,
     * allowing users to skip the full setup wizard.
     *
     * @since    1.5.0
     */
    public function quick_setup() {
        // Get the output buffer instance
        $buffer = \Status_Sentry_Output_Buffer::get_instance();

        error_log('Status Sentry Setup Wizard: Performing quick setup with balanced preset');

        // Apply the balanced preset
        $config_manager = Status_Sentry_Config_Manager::get_instance();
        $config_manager->apply_preset('balanced');

        // Save the preset selection
        update_option('status_sentry_preset', 'balanced');

        // Mark setup as complete
        $this->complete_setup();

        // Redirect to the dashboard
        error_log('Status Sentry Setup Wizard: Quick setup complete, redirecting to dashboard');
        $buffer->end();
        wp_redirect(admin_url('admin.php?page=status-sentry'));
        exit;
    }

    /**
     * Render the setup wizard.
     *
     * @since    1.4.0
     */
    public function render() {
        // Get settings
        $settings = $this->get_settings();

        // Render header
        $this->render_header();

        // Render step content
        switch ($this->step) {
            case 1:
                $this->render_welcome_step();
                break;
            case 2:
                $this->render_presets_step();
                break;
            case 3:
                $this->render_features_step($settings);
                break;
            case 4:
                $this->render_retention_step($settings);
                break;
            case 5:
                $this->render_complete_step($settings);
                break;
            default:
                $this->render_welcome_step();
                break;
        }

        // Render footer
        $this->render_footer();
    }

    /**
     * Render the presets step.
     *
     * @since    1.5.0
     */
    private function render_presets_step() {
        // Get the current preset
        $current_preset = get_option('status_sentry_preset', 'balanced');

        ?>
        <div class="setup-section">
            <h2><?php echo esc_html__('Choose Monitoring Preset', 'status-sentry-wp'); ?></h2>
            <p><?php echo esc_html__('Select a monitoring preset that best fits your site\'s needs. You can customize these settings later.', 'status-sentry-wp'); ?></p>

            <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=status-sentry-setup')); ?>">
                <?php wp_nonce_field('status_sentry_setup', 'status_sentry_setup_nonce'); ?>
                <input type="hidden" name="status_sentry_setup_step" value="2">

                <div class="preset-options">
                    <div class="preset-option">
                        <label>
                            <input type="radio" name="status_sentry_preset" value="basic" <?php checked($current_preset, 'basic'); ?>>
                            <strong><?php echo esc_html__('Basic', 'status-sentry-wp'); ?></strong>
                        </label>
                        <p><?php echo esc_html__('Minimal monitoring with low resource usage. Best for small sites or shared hosting.', 'status-sentry-wp'); ?></p>
                        <ul>
                            <li><?php echo esc_html__('Core monitoring enabled', 'status-sentry-wp'); ?></li>
                            <li><?php echo esc_html__('Conflict detection enabled', 'status-sentry-wp'); ?></li>
                            <li><?php echo esc_html__('7-day data retention', 'status-sentry-wp'); ?></li>
                            <li><?php echo esc_html__('Minimal resource usage', 'status-sentry-wp'); ?></li>
                        </ul>
                    </div>

                    <div class="preset-option">
                        <label>
                            <input type="radio" name="status_sentry_preset" value="balanced" <?php checked($current_preset, 'balanced'); ?>>
                            <strong><?php echo esc_html__('Balanced (Recommended)', 'status-sentry-wp'); ?></strong>
                        </label>
                        <p><?php echo esc_html__('Comprehensive monitoring with moderate resource usage. Suitable for most sites.', 'status-sentry-wp'); ?></p>
                        <ul>
                            <li><?php echo esc_html__('All monitoring features enabled', 'status-sentry-wp'); ?></li>
                            <li><?php echo esc_html__('14-30 day data retention', 'status-sentry-wp'); ?></li>
                            <li><?php echo esc_html__('Resource management enabled', 'status-sentry-wp'); ?></li>
                            <li><?php echo esc_html__('Query caching enabled', 'status-sentry-wp'); ?></li>
                        </ul>
                    </div>

                    <div class="preset-option">
                        <label>
                            <input type="radio" name="status_sentry_preset" value="comprehensive" <?php checked($current_preset, 'comprehensive'); ?>>
                            <strong><?php echo esc_html__('Comprehensive', 'status-sentry-wp'); ?></strong>
                        </label>
                        <p><?php echo esc_html__('Maximum monitoring with higher resource usage. Best for larger sites with dedicated hosting.', 'status-sentry-wp'); ?></p>
                        <ul>
                            <li><?php echo esc_html__('All monitoring features enabled', 'status-sentry-wp'); ?></li>
                            <li><?php echo esc_html__('30-90 day data retention', 'status-sentry-wp'); ?></li>
                            <li><?php echo esc_html__('Advanced resource management', 'status-sentry-wp'); ?></li>
                            <li><?php echo esc_html__('More frequent health checks', 'status-sentry-wp'); ?></li>
                        </ul>
                    </div>

                    <div class="preset-option">
                        <label>
                            <input type="radio" name="status_sentry_preset" value="custom" <?php checked($current_preset, 'custom'); ?>>
                            <strong><?php echo esc_html__('Custom', 'status-sentry-wp'); ?></strong>
                        </label>
                        <p><?php echo esc_html__('Manually configure all monitoring settings to your exact specifications.', 'status-sentry-wp'); ?></p>
                    </div>
                </div>

                <div class="setup-actions">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=status-sentry-setup&step=1')); ?>" class="button"><?php echo esc_html__('Back', 'status-sentry-wp'); ?></a>
                    <button type="submit" class="button button-primary"><?php echo esc_html__('Continue', 'status-sentry-wp'); ?></button>
                </div>
            </form>
        </div>

        <style>
            .preset-options {
                display: flex;
                flex-wrap: wrap;
                gap: 20px;
                margin-top: 20px;
            }
            .preset-option {
                flex: 1 1 45%;
                min-width: 250px;
                border: 1px solid #ccc;
                border-radius: 5px;
                padding: 15px;
                background: #f9f9f9;
            }
            .preset-option label {
                font-size: 16px;
                display: block;
                margin-bottom: 10px;
            }
            .preset-option p {
                margin-top: 5px;
                margin-bottom: 10px;
            }
            .preset-option ul {
                margin-left: 20px;
                list-style-type: disc;
            }
            .preset-option input[type="radio"] {
                margin-right: 8px;
            }
        </style>
        <?php
    }

    /**
     * Render the header.
     *
     * @since    1.4.0
     */
    private function render_header() {
        ?>
        <div class="wrap status-sentry-setup-wizard">
            <h1><?php echo esc_html__('Status Sentry Setup Wizard', 'status-sentry-wp'); ?></h1>

            <div class="status-sentry-setup-steps">
                <ul class="step-indicator">
                    <li class="<?php echo $this->step >= 1 ? 'active' : ''; ?>"><?php echo esc_html__('Welcome', 'status-sentry-wp'); ?></li>
                    <li class="<?php echo $this->step >= 2 ? 'active' : ''; ?>"><?php echo esc_html__('Presets', 'status-sentry-wp'); ?></li>
                    <li class="<?php echo $this->step >= 3 ? 'active' : ''; ?>"><?php echo esc_html__('Features', 'status-sentry-wp'); ?></li>
                    <li class="<?php echo $this->step >= 4 ? 'active' : ''; ?>"><?php echo esc_html__('Retention', 'status-sentry-wp'); ?></li>
                    <li class="<?php echo $this->step >= 5 ? 'active' : ''; ?>"><?php echo esc_html__('Complete', 'status-sentry-wp'); ?></li>
                </ul>
            </div>

            <div class="status-sentry-setup-content">
        <?php
    }

    /**
     * Render the footer.
     *
     * @since    1.4.0
     */
    private function render_footer() {
        ?>
            </div><!-- .status-sentry-setup-content -->

            <!-- Skip Setup link -->
            <div class="skip-setup-link" style="margin-top: 10px;">
                <a href="<?php echo esc_url(add_query_arg('skip_setup', 1, admin_url('admin.php?page=status-sentry-setup'))); ?>" class="button-secondary" style="float:left;">
                    <?php echo esc_html__('Skip Setup', 'status-sentry-wp'); ?>
                </a>
                <div style="clear:both;"></div>
            </div>
        </div><!-- .wrap -->

        <style>
            .status-sentry-setup-wizard {
                max-width: 800px;
                margin: 0 auto;
            }
            .status-sentry-setup-steps {
                margin: 30px 0;
            }
            .step-indicator {
                display: flex;
                justify-content: space-between;
                margin: 0;
                padding: 0;
                list-style: none;
            }
            .step-indicator li {
                flex: 1;
                text-align: center;
                padding: 10px;
                background: #f0f0f1;
                border-right: 1px solid #c3c4c7;
                color: #646970;
            }
            .step-indicator li:last-child {
                border-right: none;
            }
            .step-indicator li.active {
                background: #2271b1;
                color: #fff;
            }
            .status-sentry-setup-content {
                background: #fff;
                padding: 20px;
                border: 1px solid #c3c4c7;
                margin-bottom: 20px;
            }
            .setup-section {
                margin-bottom: 20px;
            }
            .setup-section h2 {
                margin-top: 0;
            }
            .setup-actions {
                margin-top: 30px;
                text-align: right;
            }
            .feature-option {
                margin-bottom: 15px;
            }
            .feature-option label {
                font-weight: bold;
            }
            .feature-option p {
                margin: 5px 0 0 25px;
                color: #646970;
            }
            .skip-setup-link {
                margin-bottom: 20px;
            }
        </style>
        <?php
    }

    /**
     * Render the welcome step.
     *
     * @since    1.4.0
     */
    private function render_welcome_step() {
        ?>
        <div class="setup-section">
            <h2><?php echo esc_html__('Welcome to Status Sentry', 'status-sentry-wp'); ?></h2>
            <p><?php echo esc_html__('Thank you for installing Status Sentry! This wizard will help you configure the plugin for optimal performance on your site.', 'status-sentry-wp'); ?></p>
            <p><?php echo esc_html__('Status Sentry provides comprehensive monitoring for your WordPress site, helping you identify and resolve issues before they affect your users.', 'status-sentry-wp'); ?></p>
            <p><?php echo esc_html__('Let\'s get started by configuring the basic settings for your site.', 'status-sentry-wp'); ?></p>
        </div>

        <div class="setup-options">
            <div class="setup-option">
                <h3><?php echo esc_html__('Custom Setup', 'status-sentry-wp'); ?></h3>
                <p><?php echo esc_html__('Walk through the setup wizard to customize all monitoring settings.', 'status-sentry-wp'); ?></p>
                <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=status-sentry-setup')); ?>">
                    <?php wp_nonce_field('status_sentry_setup', 'status_sentry_setup_nonce'); ?>
                    <input type="hidden" name="status_sentry_setup_step" value="1">
                    <div class="setup-actions">
                        <button type="submit" class="button button-primary"><?php echo esc_html__('Let\'s Go!', 'status-sentry-wp'); ?></button>
                    </div>
                </form>
            </div>

            <div class="setup-option">
                <h3><?php echo esc_html__('Quick Setup', 'status-sentry-wp'); ?></h3>
                <p><?php echo esc_html__('Apply recommended balanced settings and start monitoring immediately.', 'status-sentry-wp'); ?></p>
                <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=status-sentry-setup')); ?>">
                    <?php wp_nonce_field('status_sentry_setup', 'status_sentry_setup_nonce'); ?>
                    <input type="hidden" name="quick_setup" value="1">
                    <div class="setup-actions">
                        <button type="submit" class="button button-secondary"><?php echo esc_html__('Monitor My Site (Quick Setup)', 'status-sentry-wp'); ?></button>
                    </div>
                </form>
            </div>
        </div>

        <style>
            .setup-options {
                display: flex;
                flex-wrap: wrap;
                gap: 20px;
                margin-top: 30px;
            }
            .setup-option {
                flex: 1 1 45%;
                min-width: 250px;
                border: 1px solid #ccc;
                border-radius: 5px;
                padding: 20px;
                background: #f9f9f9;
            }
            .setup-option h3 {
                margin-top: 0;
                margin-bottom: 10px;
            }
            .setup-option p {
                margin-bottom: 20px;
            }
            .setup-option .setup-actions {
                text-align: center;
                margin-top: 20px;
            }
        </style>
        <?php
    }

    /**
     * Render the features step.
     *
     * @since    1.4.0
     * @param    array    $settings    The current settings.
     */
    private function render_features_step($settings) {
        ?>
        <div class="setup-section">
            <h2><?php echo esc_html__('Choose Monitoring Features', 'status-sentry-wp'); ?></h2>
            <p><?php echo esc_html__('Select which monitoring features you want to enable. You can change these settings later from the plugin settings page.', 'status-sentry-wp'); ?></p>

            <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=status-sentry-setup')); ?>">
                <?php wp_nonce_field('status_sentry_setup', 'status_sentry_setup_nonce'); ?>
                <input type="hidden" name="status_sentry_setup_step" value="3">

                <div class="feature-option">
                    <label>
                        <input type="checkbox" name="core_monitoring" value="1" <?php checked($settings['core_monitoring'], 1); ?>>
                        <?php echo esc_html__('Core Monitoring', 'status-sentry-wp'); ?>
                    </label>
                    <p><?php echo esc_html__('Monitor WordPress core events and performance.', 'status-sentry-wp'); ?></p>
                </div>

                <div class="feature-option">
                    <label>
                        <input type="checkbox" name="db_monitoring" value="1" <?php checked($settings['db_monitoring'], 1); ?>>
                        <?php echo esc_html__('Database Monitoring', 'status-sentry-wp'); ?>
                    </label>
                    <p><?php echo esc_html__('Track database performance and issues.', 'status-sentry-wp'); ?></p>
                </div>

                <div class="feature-option">
                    <label>
                        <input type="checkbox" name="conflict_detection" value="1" <?php checked($settings['conflict_detection'], 1); ?>>
                        <?php echo esc_html__('Conflict Detection', 'status-sentry-wp'); ?>
                    </label>
                    <p><?php echo esc_html__('Detect conflicts between plugins and themes.', 'status-sentry-wp'); ?></p>
                </div>

                <div class="feature-option">
                    <label>
                        <input type="checkbox" name="performance_monitoring" value="1" <?php checked($settings['performance_monitoring'], 1); ?>>
                        <?php echo esc_html__('Performance Monitoring', 'status-sentry-wp'); ?>
                    </label>
                    <p><?php echo esc_html__('Monitor site performance and resource usage.', 'status-sentry-wp'); ?></p>
                </div>

                <div class="setup-actions">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=status-sentry-setup&step=2')); ?>" class="button"><?php echo esc_html__('Back', 'status-sentry-wp'); ?></a>
                    <button type="submit" class="button button-primary"><?php echo esc_html__('Continue', 'status-sentry-wp'); ?></button>
                </div>
            </form>
        </div>
        <?php
    }

    /**
     * Render the retention step.
     *
     * @since    1.4.0
     * @param    array    $settings    The current settings.
     */
    private function render_retention_step($settings) {
        ?>
        <div class="setup-section">
            <h2><?php echo esc_html__('Data Retention Settings', 'status-sentry-wp'); ?></h2>
            <p><?php echo esc_html__('Configure how long Status Sentry should keep data in your database. Longer retention periods provide more historical data but use more database space.', 'status-sentry-wp'); ?></p>

            <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=status-sentry-setup')); ?>">
                <?php wp_nonce_field('status_sentry_setup', 'status_sentry_setup_nonce'); ?>
                <input type="hidden" name="status_sentry_setup_step" value="4">

                <table class="form-table">
                    <tr>
                        <th scope="row"><?php echo esc_html__('Events Retention', 'status-sentry-wp'); ?></th>
                        <td>
                            <input type="number" name="events_retention_days" value="<?php echo esc_attr($settings['events_retention_days']); ?>" min="1" max="365" step="1"> <?php echo esc_html__('days', 'status-sentry-wp'); ?>
                            <p class="description"><?php echo esc_html__('Number of days to keep monitoring events in the database.', 'status-sentry-wp'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php echo esc_html__('Processed Queue Retention', 'status-sentry-wp'); ?></th>
                        <td>
                            <input type="number" name="processed_queue_retention_days" value="<?php echo esc_attr($settings['processed_queue_retention_days']); ?>" min="1" max="30" step="1"> <?php echo esc_html__('days', 'status-sentry-wp'); ?>
                            <p class="description"><?php echo esc_html__('Number of days to keep processed queue items.', 'status-sentry-wp'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php echo esc_html__('Failed Queue Retention', 'status-sentry-wp'); ?></th>
                        <td>
                            <input type="number" name="failed_queue_retention_days" value="<?php echo esc_attr($settings['failed_queue_retention_days']); ?>" min="1" max="90" step="1"> <?php echo esc_html__('days', 'status-sentry-wp'); ?>
                            <p class="description"><?php echo esc_html__('Number of days to keep failed queue items for troubleshooting.', 'status-sentry-wp'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><?php echo esc_html__('Task Runs Retention', 'status-sentry-wp'); ?></th>
                        <td>
                            <input type="number" name="task_runs_retention_days" value="<?php echo esc_attr($settings['task_runs_retention_days']); ?>" min="1" max="90" step="1"> <?php echo esc_html__('days', 'status-sentry-wp'); ?>
                            <p class="description"><?php echo esc_html__('Number of days to keep task execution history.', 'status-sentry-wp'); ?></p>
                        </td>
                    </tr>
                </table>

                <div class="setup-actions">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=status-sentry-setup&step=3')); ?>" class="button"><?php echo esc_html__('Back', 'status-sentry-wp'); ?></a>
                    <button type="submit" class="button button-primary"><?php echo esc_html__('Continue', 'status-sentry-wp'); ?></button>
                </div>
            </form>
        </div>
        <?php
    }

    /**
     * Render the complete step.
     *
     * @since    1.4.0
     * @param    array    $settings    The current settings.
     */
    private function render_complete_step($settings) {
        // Get the selected preset
        $preset = get_option('status_sentry_preset', 'balanced');
        $preset_names = [
            'basic' => __('Basic', 'status-sentry-wp'),
            'balanced' => __('Balanced', 'status-sentry-wp'),
            'comprehensive' => __('Comprehensive', 'status-sentry-wp'),
            'custom' => __('Custom', 'status-sentry-wp'),
        ];
        $preset_name = isset($preset_names[$preset]) ? $preset_names[$preset] : $preset_names['balanced'];

        ?>
        <div class="setup-section">
            <h2><?php echo esc_html__('Setup Complete!', 'status-sentry-wp'); ?></h2>
            <p><?php echo esc_html__('Congratulations! You have successfully configured Status Sentry for your WordPress site.', 'status-sentry-wp'); ?></p>

            <h3><?php echo esc_html__('Summary of Your Settings', 'status-sentry-wp'); ?></h3>

            <h4><?php echo esc_html__('Selected Preset', 'status-sentry-wp'); ?></h4>
            <p><strong><?php echo esc_html($preset_name); ?></strong></p>

            <?php if ($preset === 'custom') : ?>
                <h4><?php echo esc_html__('Enabled Features', 'status-sentry-wp'); ?></h4>
                <ul>
                    <?php if ($settings['core_monitoring']) : ?>
                        <li><?php echo esc_html__('Core Monitoring', 'status-sentry-wp'); ?></li>
                    <?php endif; ?>

                    <?php if ($settings['db_monitoring']) : ?>
                        <li><?php echo esc_html__('Database Monitoring', 'status-sentry-wp'); ?></li>
                    <?php endif; ?>

                    <?php if ($settings['conflict_detection']) : ?>
                        <li><?php echo esc_html__('Conflict Detection', 'status-sentry-wp'); ?></li>
                    <?php endif; ?>

                    <?php if ($settings['performance_monitoring']) : ?>
                        <li><?php echo esc_html__('Performance Monitoring', 'status-sentry-wp'); ?></li>
                    <?php endif; ?>
                </ul>

                <h4><?php echo esc_html__('Data Retention', 'status-sentry-wp'); ?></h4>
                <ul>
                    <li><?php echo esc_html(sprintf(__('Events: %d days', 'status-sentry-wp'), $settings['events_retention_days'])); ?></li>
                    <li><?php echo esc_html(sprintf(__('Processed Queue: %d days', 'status-sentry-wp'), $settings['processed_queue_retention_days'])); ?></li>
                    <li><?php echo esc_html(sprintf(__('Failed Queue: %d days', 'status-sentry-wp'), $settings['failed_queue_retention_days'])); ?></li>
                    <li><?php echo esc_html(sprintf(__('Task Runs: %d days', 'status-sentry-wp'), $settings['task_runs_retention_days'])); ?></li>
                </ul>
            <?php else : ?>
                <p><?php echo esc_html__('The selected preset has been applied with optimized settings for your site.', 'status-sentry-wp'); ?></p>
            <?php endif; ?>

            <p><?php echo esc_html__('You can change these settings at any time from the Status Sentry Settings page.', 'status-sentry-wp'); ?></p>

            <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=status-sentry-setup')); ?>">
                <?php wp_nonce_field('status_sentry_setup', 'status_sentry_setup_nonce'); ?>
                <input type="hidden" name="status_sentry_setup_step" value="5">

                <div class="setup-actions">
                    <?php if ($preset === 'custom') : ?>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=status-sentry-setup&step=4')); ?>" class="button"><?php echo esc_html__('Back', 'status-sentry-wp'); ?></a>
                    <?php else : ?>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=status-sentry-setup&step=2')); ?>" class="button"><?php echo esc_html__('Back', 'status-sentry-wp'); ?></a>
                    <?php endif; ?>
                    <button type="submit" class="button button-primary"><?php echo esc_html__('Finish Setup', 'status-sentry-wp'); ?></button>
                </div>
            </form>
        </div>
        <?php
    }

    /**
     * Get settings.
     *
     * @since    1.4.0
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
}
