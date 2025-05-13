# Getting Started: Extending Status Sentry

This guide provides a concise introduction to extending the Status Sentry WordPress plugin. It covers the essential steps to set up your development environment, understand the plugin architecture, and implement custom extensions.

## Development Environment Setup

1. **Clone the Repository**

   ```bash
   git clone https://github.com/status-sentry/status-sentry-wp.git
   cd status-sentry-wp
   ```

2. **Install Dependencies**

   ```bash
   composer install
   ```

3. **Set Up WordPress Development Environment**

   Use the provided Docker setup:

   ```bash
   cp .env.example .env
   docker-compose up -d
   ```

4. **Activate the Plugin**

   Navigate to WordPress admin (http://localhost:8000/wp-admin) and activate the Status Sentry plugin.

## Plugin Architecture Overview

Status Sentry follows a modular architecture with these key components:

- **Hook Management**: Registers and manages WordPress hooks
- **Data Pipeline**: Captures, filters, and processes data
- **Event Processing**: Processes events from the queue
- **Database Management**: Handles database schema and migrations
- **Scheduler**: Manages background tasks
- **Monitoring System**: Centralized monitoring with standardized interfaces

## Extending the Plugin

### 1. Adding Custom Hooks

Custom hooks allow you to monitor specific actions in WordPress or other plugins.

```php
// Add this to your theme's functions.php or a custom plugin
add_filter('status_sentry_hook_config', function($hooks) {
    // Add a custom hook for WooCommerce product creation
    $hooks['woocommerce']['woocommerce_new_product'] = [
        'callback' => 'my_custom_product_callback',
        'priority' => 10,
        'sampling_rate' => 1.0, // Capture all events
    ];
    return $hooks;
});

// Define the callback function
function my_custom_product_callback($product_id) {
    // Get the product data
    $product = wc_get_product($product_id);
    
    // Capture the data
    $data = [
        'product_id' => $product_id,
        'product_name' => $product->get_name(),
        'product_price' => $product->get_price(),
        'timestamp' => current_time('mysql'),
    ];
    
    // Get the Status Sentry data capture instance
    $data_capture = new Status_Sentry_Data_Capture();
    
    // Capture the data
    $data_capture->capture('woocommerce', 'woocommerce_new_product', $data);
    
    // Return the product ID to maintain the hook chain
    return $product_id;
}
```

### 2. Adding Custom Data Filters

Data filters allow you to modify or enrich captured data before it's processed.

```php
// Add a custom data filter
add_filter('status_sentry_process_event', function($data, $feature, $hook) {
    // Only process WooCommerce events
    if ($feature === 'woocommerce') {
        // Add additional context
        $data['site_name'] = get_bloginfo('name');
        $data['currency'] = get_woocommerce_currency();
        
        // Anonymize sensitive data
        if (isset($data['customer_email'])) {
            $data['customer_email'] = 'anonymized@example.com';
        }
    }
    
    return $data;
}, 10, 3);
```

### 3. Creating a Custom Event Processor

Custom event processors allow you to handle events in specialized ways.

```php
/**
 * Custom WooCommerce Event Processor
 */
class My_Custom_WooCommerce_Processor extends Status_Sentry_Event_Processor {
    /**
     * Process a WooCommerce event.
     *
     * @param array $event The event data.
     * @return bool Whether the event was processed successfully.
     */
    public function process_woocommerce_event($event) {
        // Only process WooCommerce events
        if ($event['feature'] !== 'woocommerce') {
            return parent::process_event($event);
        }
        
        // Decode the JSON data
        $data = json_decode($event['data'], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('Status Sentry: Error decoding JSON data - ' . json_last_error_msg());
            return false;
        }
        
        // Process the event based on the hook
        switch ($event['hook']) {
            case 'woocommerce_new_product':
                return $this->process_new_product_event($data, $event);
            
            case 'woocommerce_order_status_changed':
                return $this->process_order_status_event($data, $event);
            
            default:
                return parent::process_event($event);
        }
    }
    
    /**
     * Process a new product event.
     *
     * @param array $data The event data.
     * @param array $event The original event.
     * @return bool Whether the event was processed successfully.
     */
    private function process_new_product_event($data, $event) {
        // Custom processing logic for new product events
        // ...
        
        return true;
    }
    
    /**
     * Process an order status change event.
     *
     * @param array $data The event data.
     * @param array $event The original event.
     * @return bool Whether the event was processed successfully.
     */
    private function process_order_status_event($data, $event) {
        // Custom processing logic for order status events
        // ...
        
        return true;
    }
}

// Register the custom processor
add_action('init', function() {
    // Replace the default event processor with our custom one
    add_filter('status_sentry_event_processor', function() {
        return new My_Custom_WooCommerce_Processor();
    });
});
```

### 4. Scheduling Custom Tasks

Schedule custom tasks to run at specific intervals.

```php
// Register a custom scheduled task
add_action('init', function() {
    // Register the task
    add_action('my_custom_scheduled_task', 'my_custom_scheduled_function');
    
    // Schedule the task if not already scheduled
    if (!wp_next_scheduled('my_custom_scheduled_task')) {
        wp_schedule_event(time(), 'hourly', 'my_custom_scheduled_task');
    }
});

// Define the scheduled function
function my_custom_scheduled_function() {
    // Get the Status Sentry event processor
    $event_processor = new Status_Sentry_Event_Processor();
    
    // Process WooCommerce events with a higher priority
    $event_processor->process_events(100, [
        'feature' => 'woocommerce',
        'priority' => 'high',
    ]);
}
```

### 5. Adding Custom Monitoring

Create custom monitoring components to track specific aspects of your site.

```php
/**
 * Custom WooCommerce Monitoring
 */
class My_WooCommerce_Monitoring implements Status_Sentry_Monitoring_Interface {
    /**
     * Initialize the monitoring component.
     */
    public function init() {
        // Register hooks
        add_action('woocommerce_new_order', [$this, 'monitor_new_order']);
        add_action('woocommerce_order_status_changed', [$this, 'monitor_order_status'], 10, 3);
    }
    
    /**
     * Monitor new orders.
     *
     * @param int $order_id The order ID.
     */
    public function monitor_new_order($order_id) {
        // Create a monitoring event
        $event = new Status_Sentry_Monitoring_Event(
            'woocommerce',
            'new_order',
            [
                'order_id' => $order_id,
                'timestamp' => current_time('mysql'),
            ]
        );
        
        // Dispatch the event
        Status_Sentry_Monitoring_Manager::dispatch_event($event);
    }
    
    /**
     * Monitor order status changes.
     *
     * @param int $order_id The order ID.
     * @param string $old_status The old status.
     * @param string $new_status The new status.
     */
    public function monitor_order_status($order_id, $old_status, $new_status) {
        // Create a monitoring event
        $event = new Status_Sentry_Monitoring_Event(
            'woocommerce',
            'order_status_changed',
            [
                'order_id' => $order_id,
                'old_status' => $old_status,
                'new_status' => $new_status,
                'timestamp' => current_time('mysql'),
            ]
        );
        
        // Dispatch the event
        Status_Sentry_Monitoring_Manager::dispatch_event($event);
    }
    
    /**
     * Get the monitoring component's status.
     *
     * @return array The component status.
     */
    public function get_status() {
        return [
            'component' => 'woocommerce_monitoring',
            'status' => 'active',
            'orders_monitored' => get_option('my_woocommerce_orders_monitored', 0),
            'last_order_time' => get_option('my_woocommerce_last_order_time', ''),
        ];
    }
}

// Register the custom monitoring component
add_action('init', function() {
    // Get the monitoring manager
    $monitoring_manager = Status_Sentry_Monitoring_Manager::get_instance();
    
    // Register the custom monitoring component
    $monitoring_manager->register_component('woocommerce', new My_WooCommerce_Monitoring());
});
```

## Testing Your Extensions

1. **Run the Tests**

   ```bash
   php status-sentry/run-tests.php
   ```

2. **Add Custom Tests**

   Create a custom test file in the `tests` directory:

   ```php
   // tests/test-my-extension.php
   
   // Include necessary files
   require_once STATUS_SENTRY_PLUGIN_DIR . 'path/to/my/extension.php';
   
   // Define test functions
   function test_my_extension() {
       // Test code here
       // ...
       
       return true; // Return true if the test passes
   }
   
   // Run the test
   test_my_extension();
   ```

## Best Practices

1. **Follow WordPress Coding Standards**: Adhere to the [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/).
2. **Use Proper Namespacing**: Prefix your functions and classes to avoid conflicts.
3. **Implement Error Handling**: Add robust error handling to prevent failures.
4. **Document Your Code**: Add PHPDoc comments to all functions and classes.
5. **Consider Performance**: Be mindful of performance implications, especially for hooks that fire frequently.
6. **Test Thoroughly**: Test your extensions in various environments and with different WordPress configurations.

## Further Resources

- [Status Sentry Core Framework Documentation](CORE-FRAMEWORK.md)
- [Status Sentry Resource Management Documentation](RESOURCE-MANAGEMENT.md)
- [Status Sentry Performance Benchmarking](PERFORMANCE-BENCHMARKING.md)
- [Status Sentry Production Hardening](PRODUCTION-HARDENING.md)
- [WordPress Plugin Handbook](https://developer.wordpress.org/plugins/)
