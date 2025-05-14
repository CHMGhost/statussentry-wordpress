# Legacy Events System

## Deprecation Notice

As of Status Sentry version 1.6.0, the legacy events system has been deprecated in favor of the new Monitoring Events system. The legacy events tab in the admin interface is now hidden by default.

## Background

The legacy events system was based on a "queue → processor → wp_status_sentry_events" pipeline that stored events in the `wp_status_sentry_events` table. This system has been replaced by the more robust Monitoring Events system introduced in version 1.6.0, which uses the `wp_status_sentry_monitoring_events` table.

Since most sites never insert rows into the legacy events table by default, the legacy tab typically shows "No legacy events found." To reduce UI clutter, we've hidden this tab by default while maintaining backward compatibility for sites that might still be using the legacy system.

## Timeline for Removal

- **Version 1.6.0+**: Legacy events tab hidden by default but can be re-enabled via filter
- **Version 2.0.0** (future): Legacy events system code will be completely removed

## Re-enabling the Legacy Events Tab

If you need to access the legacy events tab, you can re-enable it by adding the following code to your theme's `functions.php` file or a custom plugin:

```php
/**
 * Re-enable the Status Sentry legacy events tab
 */
add_filter('status_sentry_show_legacy_events_tab', '__return_true');
```

## Migrating from Legacy to Monitoring Events

If you have custom code that interacts with the legacy events system, we recommend updating it to use the Monitoring Events system instead. Here's a comparison of the two systems:

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

## Benefits of the Monitoring Events System

The Monitoring Events system offers several advantages over the legacy system:

1. **More structured data**: Events include type, priority, source, context, and message fields
2. **Better categorization**: Events are categorized by type (info, warning, error, critical, etc.)
3. **Priority levels**: Events have priority levels (low, normal, high, critical)
4. **Improved filtering**: More fields to filter and search events
5. **Better performance**: Optimized database schema and queries

## Questions and Support

If you have questions about the deprecation of the legacy events system or need help migrating to the Monitoring Events system, please contact our support team.
