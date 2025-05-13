<?php
declare(strict_types=1);

/**
 * Output Buffer Class
 *
 * @since      1.5.0
 * @package    Status_Sentry
 * @subpackage Status_Sentry/includes
 */

/**
 * Output Buffer Class
 *
 * This class manages the output buffer to prevent warnings and notices from
 * being output before headers are sent. It captures all output during plugin
 * initialization and only releases it after headers have been sent.
 *
 * Key responsibilities:
 * - Start output buffering during plugin initialization
 * - Capture warnings and notices
 * - Release the buffer after headers have been sent
 * - Log captured warnings and notices
 *
 * @since      1.5.0
 * @package    Status_Sentry
 * @subpackage Status_Sentry/includes
 */
class Status_Sentry_Output_Buffer {

    /**
     * The singleton instance of this class.
     *
     * @since    1.5.0
     * @access   private
     * @var      Status_Sentry_Output_Buffer    $instance    The singleton instance.
     */
    private static $instance = null;

    /**
     * Whether the buffer is active.
     *
     * @since    1.5.0
     * @access   private
     * @var      bool    $is_active    Whether the buffer is active.
     */
    private $is_active = false;

    /**
     * The buffer level when started.
     *
     * @since    1.5.0
     * @access   private
     * @var      int    $buffer_level    The buffer level when started.
     */
    private $buffer_level = 0;

    /**
     * Get the singleton instance of this class.
     *
     * @since    1.5.0
     * @return   Status_Sentry_Output_Buffer    The singleton instance.
     */
    public static function get_instance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Initialize the class.
     *
     * @since    1.5.0
     */
    private function __construct() {
        // Private constructor to prevent direct instantiation
    }

    /**
     * Start output buffering.
     *
     * @since    1.5.0
     * @return   void
     */
    public function start(): void {
        if (!$this->is_active) {
            $this->buffer_level = ob_get_level();
            ob_start([$this, 'process_output']);
            $this->is_active = true;
        }
    }

    /**
     * End output buffering.
     *
     * @since    1.5.0
     * @return   void
     */
    public function end(): void {
        if ($this->is_active) {
            while (ob_get_level() > $this->buffer_level) {
                ob_end_flush();
            }
            $this->is_active = false;
        }
    }

    /**
     * Process captured output.
     *
     * @since    1.5.0
     * @param    string    $output    The captured output.
     * @return   string               The processed output.
     */
    public function process_output(string $output): string {
        if (!empty($output)) {
            // Log the captured output
            error_log('Status Sentry: Captured output: ' . $output);

            // Check if headers have been sent
            if (headers_sent()) {
                // If headers are already sent, we can't redirect, so return the output
                return $output;
            }

            // Return empty string to prevent output
            return '';
        }

        return $output;
    }
}
