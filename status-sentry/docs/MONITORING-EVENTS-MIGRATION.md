# Migrating from Legacy Events to Monitoring Events

## Overview

As of Status Sentry version 1.6.0, we have migrated the dashboard widget and REST API to use the Monitoring Events system instead of the Legacy Events system. This document explains the changes and how they affect your Status Sentry installation.

## Background

Status Sentry originally used a simple events system that stored events in the `wp_status_sentry_events` table. This system categorized events by "feature" (core_monitoring, db_monitoring, conflict_detection, performance_monitoring).

In version 1.6.0, we introduced a more robust Monitoring Events system that stores events in the `wp_status_sentry_monitoring_events` table. This system categorizes events by "type" (info, warning, error, critical, conflict, performance, etc.) and includes additional metadata such as priority, source, context, and message.

## Changes in Version 1.6.0

1. **Dashboard Widget**: The dashboard widget now displays events from the Monitoring Events system instead of the Legacy Events system.
2. **REST API**: The REST API endpoints now return data from the Monitoring Events system instead of the Legacy Events system.
3. **Events Page**: The Events page now defaults to the Monitoring Events tab instead of the Legacy Events tab.
4. **Legacy Events Tab**: The Legacy Events tab is now hidden by default but can be re-enabled via a filter.

## Mapping Between Systems

To maintain backward compatibility, we've implemented a mapping between the Monitoring Events system's event types and the Legacy Events system's feature categories:

| Monitoring Event Type | Legacy Feature Category |
|----------------------|------------------------|
| info                 | core_monitoring        |
| warning              | core_monitoring        |
| error                | core_monitoring        |
| critical             | db_monitoring          |
| conflict             | conflict_detection     |
| performance          | performance_monitoring |

This mapping ensures that existing code that relies on the Legacy Events system's feature categories will continue to work with the Monitoring Events system.

## Testing Your Events

If you're using the Status Sentry Test Tool to generate test events, those events will now be visible in the dashboard widget and REST API. The test tool generates events via `Monitoring_Manager::emit(...)`, which stores them in the monitoring events table.

## Backward Compatibility

While we've migrated the dashboard widget and REST API to use the Monitoring Events system, we've maintained backward compatibility in several ways:

1. **Legacy Events Tab**: The Legacy Events tab can be re-enabled via the `status_sentry_show_legacy_events_tab` filter.
2. **Feature Mapping**: Monitoring events are mapped to legacy feature categories for backward compatibility.
3. **Legacy Code**: The Legacy Events system code remains intact for backward compatibility.

## Future Plans

In a future major release (version 2.0.0), we plan to completely remove the Legacy Events system. If you have custom code that interacts with the Legacy Events system, we recommend updating it to use the Monitoring Events system instead.

## How to Re-enable the Legacy Events Tab

If you need to access the Legacy Events tab, you can re-enable it by adding the following code to your theme's `functions.php` file or a custom plugin:

```php
/**
 * Re-enable the Status Sentry legacy events tab
 */
add_filter('status_sentry_show_legacy_events_tab', '__return_true');
```

## How to Use the Monitoring Events System

If you have custom code that interacts with the Legacy Events system, here's how to update it to use the Monitoring Events system:

### Legacy Events System

```php
// Get legacy events repository
$repository = new Status_Sentry_Events_Repository();
$events = $repository->get_events(20);

// Legacy event structure
$event->id;         // Event ID
$event->feature;    // Feature name (e.g., 'core_monitoring')
$event->hook;       // WordPress hook name
$event->data;       // JSON-encoded event data
$event->event_time; // Event timestamp
```

### Monitoring Events System

```php
// Get monitoring events repository
$repository = new Status_Sentry_Monitoring_Events_Repository();
$events = $repository->get_events(20);

// Monitoring event structure
$event->id;         // Event ID
$event->event_id;   // Unique event identifier
$event->event_type; // Event type (e.g., 'info', 'warning', 'error')
$event->priority;   // Event priority
$event->source;     // Event source
$event->context;    // Event context
$event->message;    // Event message
$event->data;       // JSON-encoded event data
$event->timestamp;  // Event timestamp
$event->created_at; // Record creation timestamp
```

## Questions and Support

If you have questions about the migration from Legacy Events to Monitoring Events or need help updating your custom code, please contact our support team.
