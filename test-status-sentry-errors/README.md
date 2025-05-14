# Status Sentry Test Errors

A WordPress plugin for testing the Status Sentry monitoring system by generating various error events.

## Description

This plugin is designed to help test and debug the Status Sentry monitoring system by generating different types of error events. It provides both manual triggers and automated cron-based error generation.

## Features

- **Manual Error Triggers**: Generate error, critical, and conflict events on demand
- **Automated Cron Errors**: Scheduled cron job that generates cron error events every minute
- **Simple Admin Interface**: Easy-to-use dashboard for triggering test events

## Requirements

- WordPress 5.0 or higher
- Status Sentry plugin must be active

## Installation

1. Upload the `test-status-sentry-errors` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to the 'SS Test' menu in your admin dashboard

## Usage

### Manual Error Generation

1. Go to the 'SS Test' page in your WordPress admin
2. Click the "Trigger Test Errors" button
3. Three different error events will be generated:
   - A general error (TYPE_ERROR)
   - A critical error (TYPE_CRITICAL)
   - A conflict error (TYPE_CONFLICT)

### Automated Cron Errors

The plugin automatically schedules a WordPress cron job that runs every minute and generates a cron error event. No manual action is required.

## Event Details

### General Error
- Type: `error`
- Source: `test_plugin`
- Context: `manual_test`
- Message: `Simulated general error`
- Priority: `PRIORITY_HIGH`

### Critical Error
- Type: `critical`
- Source: `test_plugin`
- Context: `manual_test`
- Message: `Simulated critical error`
- Priority: `PRIORITY_CRITICAL`

### Conflict Error
- Type: `conflict`
- Source: `test_plugin`
- Context: `manual_test`
- Message: `Simulated plugin conflict`
- Priority: `PRIORITY_HIGH`

### Cron Error
- Type: `cron_error`
- Source: `test_plugin`
- Context: `cron_test`
- Message: `Simulated cron job failure`
- Priority: `PRIORITY_HIGH`

## Deactivation

When the plugin is deactivated, it will automatically clean up by removing the scheduled cron job.

## Support

This is a testing tool and is not intended for production use. For support, please contact the Status Sentry development team.
