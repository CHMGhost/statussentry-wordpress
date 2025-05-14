<?php
/**
 * Run Tests Script
 *
 * This script runs the tests for the Status Sentry plugin.
 *
 * @package    Status_Sentry
 */

// Load WordPress
require_once '/var/www/html/wp-load.php';

// Define the plugin constants if not already defined
if (!defined('STATUS_SENTRY_VERSION')) {
    define('STATUS_SENTRY_VERSION', '1.3.0');
}
if (!defined('STATUS_SENTRY_PLUGIN_DIR')) {
    define('STATUS_SENTRY_PLUGIN_DIR', __DIR__ . '/');
}

// Run the tests
require_once __DIR__ . '/tests/test-core-framework.php';
require_once __DIR__ . '/tests/test-performance-benchmark.php';
require_once __DIR__ . '/tests/test-plugin-compatibility.php';
