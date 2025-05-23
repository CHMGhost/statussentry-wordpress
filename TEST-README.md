# Status Sentry Monitoring Test Tools

This directory contains tools for testing the Status Sentry monitoring system by generating various types of error events.

## Quick Start

To quickly test the Status Sentry monitoring system, run:

```bash
./test-status-sentry.sh
```

This will generate all types of test events (info, warning, error, critical, conflict, and cron error).

## Test Script Options

### Command Line Usage

You can run the PHP script directly:

```bash
php test-status-sentry-monitoring.php
```

### Browser Usage

You can also access the test script through a web browser:

1. Copy the `test-status-sentry-monitoring.php` file to your WordPress installation
2. Access it via your browser: `http://your-site.com/test-status-sentry-monitoring.php`
3. Click on the buttons to generate different types of test events

## Generated Events

The test script generates the following types of events:

### Info Event
- Type: `info`
- Source: `test_script`
- Context: `manual_test`
- Message: `Test info event from monitoring test script`
- Priority: `PRIORITY_NORMAL`

### Warning Event
- Type: `warning`
- Source: `test_script`
- Context: `manual_test`
- Message: `Test warning event from monitoring test script`
- Priority: `PRIORITY_NORMAL`

### Error Event
- Type: `error`
- Source: `test_script`
- Context: `manual_test`
- Message: `Test error event from monitoring test script`
- Priority: `PRIORITY_HIGH`

### Critical Event
- Type: `critical`
- Source: `test_script`
- Context: `manual_test`
- Message: `Test critical event from monitoring test script`
- Priority: `PRIORITY_CRITICAL`

### Conflict Event
- Type: `conflict`
- Source: `test_script`
- Context: `manual_test`
- Message: `Test conflict event from monitoring test script`
- Priority: `PRIORITY_HIGH`

### Cron Error Event
- Type: `cron_error`
- Source: `test_script`
- Context: `cron_test`
- Message: `Test cron error event from monitoring test script`
- Priority: `PRIORITY_HIGH`

## Verifying Results

After running the test script, you can verify that the events were generated by:

1. Checking the Status Sentry monitoring dashboard in WordPress admin
2. Looking at the WordPress database in the `wp_status_sentry_monitoring_events` table
3. Checking if any notifications were triggered based on your Status Sentry configuration

## Troubleshooting

If the test script fails to generate events, check the following:

1. Make sure the Status Sentry plugin is active
2. Verify that the monitoring system is enabled in Status Sentry settings
3. Check that the WordPress database tables for Status Sentry exist
4. Look for any PHP errors in your server logs

## WordPress Plugin Test

If you prefer to use a WordPress plugin for testing, you can use the included `test-status-sentry-errors` plugin:

1. Copy the `test-status-sentry-errors` directory to your WordPress plugins directory
2. Activate the plugin through the WordPress admin
3. Go to the "SS Test" menu in your admin dashboard
4. Click the "Trigger Test Errors" button to generate test events
