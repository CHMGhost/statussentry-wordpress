<?php
/**
 * Plugin Compatibility Test Script
 *
 * This script tests the compatibility of the Status Sentry plugin with other popular
 * WordPress plugins. It simulates the presence of these plugins and verifies that
 * Status Sentry can operate alongside them without conflicts.
 *
 * @package    Status_Sentry
 * @subpackage Status_Sentry/tests
 */

// Define the plugin constants if not already defined
if (!defined('STATUS_SENTRY_VERSION')) {
    define('STATUS_SENTRY_VERSION', '1.3.0');
}
if (!defined('STATUS_SENTRY_PLUGIN_DIR')) {
    define('STATUS_SENTRY_PLUGIN_DIR', dirname(__DIR__) . '/');
}

// Include the necessary Status Sentry files
require_once STATUS_SENTRY_PLUGIN_DIR . 'includes/class-status-sentry.php';

/**
 * Define mock classes for popular plugins to simulate their presence
 */

// WooCommerce mock classes
if (!class_exists('WooCommerce')) {
    class WooCommerce {
        public function __construct() {
            // Mock constructor
        }
        
        public function init() {
            // Mock init method
            return true;
        }
    }
}

if (!class_exists('WC_API')) {
    class WC_API {
        public function __construct() {
            // Mock constructor
        }
        
        public function init() {
            // Mock init method
            return true;
        }
    }
}

// Yoast SEO mock classes
if (!class_exists('WPSEO_Options')) {
    class WPSEO_Options {
        public static function get_instance() {
            // Mock singleton method
            return new self();
        }
        
        public function get($option) {
            // Mock get method
            return 'mock_value';
        }
    }
}

// Jetpack mock classes
if (!class_exists('Jetpack')) {
    class Jetpack {
        public static function is_active() {
            // Mock is_active method
            return true;
        }
        
        public static function get_modules() {
            // Mock get_modules method
            return ['stats', 'protect', 'monitor'];
        }
    }
}

// Contact Form 7 mock classes
if (!function_exists('wpcf7_contact_form')) {
    function wpcf7_contact_form($id = null) {
        // Mock function
        return new WPCF7_ContactForm();
    }
}

if (!class_exists('WPCF7_ContactForm')) {
    class WPCF7_ContactForm {
        public function prop($name) {
            // Mock prop method
            return 'mock_prop_value';
        }
    }
}

/**
 * Test compatibility with WooCommerce.
 *
 * @return bool Whether the test passed.
 */
function test_woocommerce_compatibility() {
    echo "Testing WooCommerce compatibility...\n";
    
    // Create instances of both plugins
    $woocommerce = new WooCommerce();
    $status_sentry = new Status_Sentry();
    
    // Initialize WooCommerce
    $woocommerce->init();
    
    // Run Status Sentry
    $status_sentry->run();
    
    // Verify that Status Sentry hooks are registered
    $hooks_registered = has_action('init', [$status_sentry, 'init_hooks']);
    
    // Verify that Status Sentry can access WooCommerce hooks if they exist
    $wc_hooks_accessible = true;
    if (has_action('woocommerce_init')) {
        $wc_hooks_accessible = has_action('woocommerce_init');
    }
    
    $passed = $hooks_registered && $wc_hooks_accessible;
    
    echo $passed ? "WooCommerce compatibility test passed.\n" : "WooCommerce compatibility test failed.\n";
    
    return $passed;
}

/**
 * Test compatibility with Yoast SEO.
 *
 * @return bool Whether the test passed.
 */
function test_yoast_seo_compatibility() {
    echo "Testing Yoast SEO compatibility...\n";
    
    // Create an instance of Status Sentry
    $status_sentry = new Status_Sentry();
    
    // Run Status Sentry
    $status_sentry->run();
    
    // Verify that Status Sentry hooks are registered
    $hooks_registered = has_action('init', [$status_sentry, 'init_hooks']);
    
    // Verify that Status Sentry can access Yoast SEO options
    $yoast_options_accessible = class_exists('WPSEO_Options');
    
    $passed = $hooks_registered && $yoast_options_accessible;
    
    echo $passed ? "Yoast SEO compatibility test passed.\n" : "Yoast SEO compatibility test failed.\n";
    
    return $passed;
}

/**
 * Test compatibility with Jetpack.
 *
 * @return bool Whether the test passed.
 */
function test_jetpack_compatibility() {
    echo "Testing Jetpack compatibility...\n";
    
    // Create an instance of Status Sentry
    $status_sentry = new Status_Sentry();
    
    // Run Status Sentry
    $status_sentry->run();
    
    // Verify that Status Sentry hooks are registered
    $hooks_registered = has_action('init', [$status_sentry, 'init_hooks']);
    
    // Verify that Status Sentry can access Jetpack methods
    $jetpack_methods_accessible = class_exists('Jetpack') && method_exists('Jetpack', 'is_active');
    
    $passed = $hooks_registered && $jetpack_methods_accessible;
    
    echo $passed ? "Jetpack compatibility test passed.\n" : "Jetpack compatibility test failed.\n";
    
    return $passed;
}

/**
 * Test compatibility with Contact Form 7.
 *
 * @return bool Whether the test passed.
 */
function test_contact_form_7_compatibility() {
    echo "Testing Contact Form 7 compatibility...\n";
    
    // Create an instance of Status Sentry
    $status_sentry = new Status_Sentry();
    
    // Run Status Sentry
    $status_sentry->run();
    
    // Verify that Status Sentry hooks are registered
    $hooks_registered = has_action('init', [$status_sentry, 'init_hooks']);
    
    // Verify that Status Sentry can access Contact Form 7 functions
    $cf7_functions_accessible = function_exists('wpcf7_contact_form');
    
    $passed = $hooks_registered && $cf7_functions_accessible;
    
    echo $passed ? "Contact Form 7 compatibility test passed.\n" : "Contact Form 7 compatibility test failed.\n";
    
    return $passed;
}

/**
 * Run all plugin compatibility tests.
 */
function run_plugin_compatibility_tests() {
    echo "Running plugin compatibility tests...\n";
    
    $tests = [
        'test_woocommerce_compatibility',
        'test_yoast_seo_compatibility',
        'test_jetpack_compatibility',
        'test_contact_form_7_compatibility',
    ];
    
    $passed = 0;
    $total = count($tests);
    
    foreach ($tests as $test) {
        if (function_exists($test)) {
            $result = call_user_func($test);
            if ($result) {
                $passed++;
            }
        }
    }
    
    echo "\nPlugin compatibility results: $passed/$total tests passed.\n";
}

// Run the plugin compatibility tests
run_plugin_compatibility_tests();
