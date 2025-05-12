# Status Sentry Core Framework

The Status Sentry Core Framework provides a robust foundation for monitoring WordPress sites. It includes a hook management system, data capture pipeline, event processing, database schema management, and a scheduler for background tasks.

## Components

### Hook Management

The hook management system allows you to define and register WordPress hooks for monitoring purposes. It includes:

- **Hook Config**: Defines which hooks to monitor and how to process them.
- **Hook Manager**: Registers hooks and applies sampling logic to reduce performance impact.

```php
// Example: Define a hook in Hook_Config
private function define_hooks() {
    $this->add_hook('core_monitoring', 'init', [
        'callback' => 'capture_init',
        'priority' => 999,
        'args' => 0,
        'sampling_rate' => 0.1, // 10% sampling
        'group' => 'core',
    ]);
}

// Example: Register hooks
$hook_manager = new Status_Sentry_Hook_Manager($hook_config, $sampling_manager, $data_capture);
$hook_manager->register_hooks();
```

### Data Pipeline

The data pipeline captures, filters, samples, and queues events for processing. It includes:

- **Data Capture**: Captures data from hooks and adds metadata.
- **Data Filter**: Filters and sanitizes captured data.
- **Sampling Manager**: Implements sampling logic to reduce performance impact.
- **Event Queue**: Stores events in a queue for later processing.

```php
// Example: Capture data
$data_capture = new Status_Sentry_Data_Capture();
$data_capture->capture('feature', 'hook', $data);
```

### Event Processing

The event processor retrieves events from the queue, processes them, and stores them in the events table. It includes:

- **Event Processor**: Processes events from the queue and stores them in the events table.

```php
// Example: Process events
$event_processor = new Status_Sentry_Event_Processor();
$event_processor->process_events(100);
```

### Database Schema

The database schema is managed through a migration system that ensures safe, versioned updates. It includes:

- **DB Migrator**: Manages database migrations.
- **Migrations**: Define database schema changes.

```php
// Example: Run migrations
$migrator = new Status_Sentry_DB_Migrator();
$migrator->run_migrations();
```

### Scheduler

The scheduler manages background tasks using WordPress cron. It includes:

- **Scheduler**: Registers custom intervals and schedules tasks.

```php
// Example: Schedule tasks
Status_Sentry_Scheduler::schedule_tasks();
```

## Usage

To use the Status Sentry Core Framework in your plugin:

1. Include the necessary files:

```php
require_once 'includes/hooks/class-status-sentry-hook-config.php';
require_once 'includes/hooks/class-status-sentry-hook-manager.php';
require_once 'includes/data/class-status-sentry-data-capture.php';
require_once 'includes/data/class-status-sentry-data-filter.php';
require_once 'includes/data/class-status-sentry-sampling-manager.php';
require_once 'includes/data/class-status-sentry-event-queue.php';
require_once 'includes/data/class-status-sentry-event-processor.php';
require_once 'includes/db/class-status-sentry-db-migrator.php';
require_once 'includes/class-status-sentry-scheduler.php';
```

2. Initialize the components:

```php
// Create instances of the required classes
$hook_config = new Status_Sentry_Hook_Config();
$sampling_manager = new Status_Sentry_Sampling_Manager();
$data_capture = new Status_Sentry_Data_Capture();
$hook_manager = new Status_Sentry_Hook_Manager($hook_config, $sampling_manager, $data_capture);

// Register hooks
$hook_manager->register_hooks();

// Initialize the scheduler
Status_Sentry_Scheduler::init();
Status_Sentry_Scheduler::schedule_tasks();
```

3. Run database migrations during plugin activation:

```php
// In your plugin's activation function
$migrator = new Status_Sentry_DB_Migrator();
$migrator->run_migrations();
```

4. Clean up during plugin deactivation:

```php
// In your plugin's deactivation function
Status_Sentry_Scheduler::unschedule_tasks();
```

## Testing

To test the core framework, run the test script:

```bash
php run-tests.php
```

This will verify that all components are working correctly.

## Extending

You can extend the core framework by:

1. Adding new hooks to the Hook Config
2. Creating custom data filters
3. Implementing custom event processors
4. Adding new database migrations
5. Registering custom scheduled tasks

See the individual component documentation for more details on how to extend each component.
