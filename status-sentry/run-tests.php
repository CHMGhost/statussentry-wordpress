<?php
/**
 * Run Tests Script
 *
 * This script runs the tests for the Status Sentry plugin.
 *
 * @package    Status_Sentry
 */

// Load WordPress
require_once dirname(__DIR__) . '/wp-load.php';

// Run the tests
require_once __DIR__ . '/tests/test-core-framework.php';
