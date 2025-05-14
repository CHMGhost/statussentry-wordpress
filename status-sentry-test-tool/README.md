# Status Sentry Test Tool

A simple WordPress plugin to test Status Sentry's monitoring system by generating various types of test events.

## Description

This plugin provides an easy-to-use admin interface for generating test events to verify that Status Sentry's monitoring system is working correctly. It allows you to generate different types of events (info, warning, error, critical, conflict, and cron error) with a single click.

## Installation

1. Upload the `status-sentry-test-tool` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to the 'Tools > SS Test Tool' menu in your admin dashboard

## Requirements

- WordPress 5.0 or higher
- Status Sentry plugin must be active

## Usage

1. Go to 'Tools > SS Test Tool' in your WordPress admin
2. Click on one of the buttons to generate a specific type of test event:
   - Generate Info Event
   - Generate Warning Event
   - Generate Error Event
   - Generate Critical Event
   - Generate Conflict Event
   - Generate Cron Error
   - Generate All Events

## Generated Events

The plugin generates the following types of events:

### Info Event
- Type: `info`
- Source: `test_tool`
- Context: `manual_test`
- Message: `Test info event from Status Sentry Test Tool`
- Priority: `PRIORITY_NORMAL`

### Warning Event
- Type: `warning`
- Source: `test_tool`
- Context: `manual_test`
- Message: `Test warning event from Status Sentry Test Tool`
- Priority: `PRIORITY_NORMAL`

### Error Event
- Type: `error`
- Source: `test_tool`
- Context: `manual_test`
- Message: `Test error event from Status Sentry Test Tool`
- Priority: `PRIORITY_HIGH`

### Critical Event
- Type: `critical`
- Source: `test_tool`
- Context: `manual_test`
- Message: `Test critical event from Status Sentry Test Tool`
- Priority: `PRIORITY_CRITICAL`

### Conflict Event
- Type: `conflict`
- Source: `test_tool`
- Context: `manual_test`
- Message: `Test conflict event from Status Sentry Test Tool`
- Priority: `PRIORITY_HIGH`

### Cron Error Event
- Type: `cron_error`
- Source: `test_tool`
- Context: `cron_test`
- Message: `Test cron error event from Status Sentry Test Tool`
- Priority: `PRIORITY_HIGH`

## Verifying Results

After generating events, you can verify them in the following ways:

1. Check the Status Sentry monitoring dashboard in WordPress admin
2. Look at the WordPress database in the `wp_status_sentry_monitoring_events` table
3. Check if any notifications were triggered based on your Status Sentry configuration

## Troubleshooting

If the plugin fails to generate events, check the following:

1. Make sure the Status Sentry plugin is active
2. Verify that the monitoring system is enabled in Status Sentry settings
3. Check that the WordPress database tables for Status Sentry exist
4. Look for any PHP errors in your server logs

## License

This plugin is licensed under the GPL v2 or later.
