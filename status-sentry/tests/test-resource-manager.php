<?php
/**
 * Class ResourceManagerTest
 *
 * @package Status_Sentry
 */

/**
 * Resource Manager test case.
 */
class ResourceManagerTest extends WP_UnitTestCase {

    /**
     * Resource Manager instance.
     *
     * @var Status_Sentry_Resource_Manager
     */
    private $resource_manager;

    /**
     * Set up.
     */
    public function setUp() {
        parent::setUp();
        
        // Include necessary files
        require_once STATUS_SENTRY_PLUGIN_DIR . 'includes/monitoring/interface-status-sentry-monitoring.php';
        require_once STATUS_SENTRY_PLUGIN_DIR . 'includes/monitoring/interface-status-sentry-monitoring-handler.php';
        require_once STATUS_SENTRY_PLUGIN_DIR . 'includes/monitoring/class-status-sentry-monitoring-event.php';
        require_once STATUS_SENTRY_PLUGIN_DIR . 'includes/monitoring/class-status-sentry-baseline.php';
        require_once STATUS_SENTRY_PLUGIN_DIR . 'includes/monitoring/class-status-sentry-resource-manager.php';
        
        // Create instance
        $this->resource_manager = new Status_Sentry_Resource_Manager();
    }

    /**
     * Test get_budget method.
     */
    public function test_get_budget() {
        // Test getting budget for each tier
        $critical_budget = $this->resource_manager->get_budget('critical');
        $standard_budget = $this->resource_manager->get_budget('standard');
        $intensive_budget = $this->resource_manager->get_budget('intensive');
        $report_budget = $this->resource_manager->get_budget('report');
        
        // Verify budgets
        $this->assertEquals(10 * 1024 * 1024, $critical_budget['memory']);
        $this->assertEquals(20 * 1024 * 1024, $standard_budget['memory']);
        $this->assertEquals(50 * 1024 * 1024, $intensive_budget['memory']);
        $this->assertEquals(100 * 1024 * 1024, $report_budget['memory']);
        
        // Test getting budget for non-existent tier (should return standard)
        $nonexistent_budget = $this->resource_manager->get_budget('nonexistent');
        $this->assertEquals($standard_budget, $nonexistent_budget);
    }

    /**
     * Test should_trigger_gc_after_task method.
     */
    public function test_should_trigger_gc_after_task() {
        // Test tasks that should force GC
        $this->assertTrue($this->resource_manager->should_trigger_gc_after_task('cleanup'));
        $this->assertTrue($this->resource_manager->should_trigger_gc_after_task('process_queue'));
        
        // Test task that shouldn't force GC
        $this->assertFalse($this->resource_manager->should_trigger_gc_after_task('some_other_task'));
        
        // Test with custom filter
        add_filter('status_sentry_gc_settings', function($settings) {
            $settings['force_after_tasks'][] = 'custom_task';
            return $settings;
        });
        
        // Create new instance to pick up the filter
        $resource_manager = new Status_Sentry_Resource_Manager();
        
        // Test custom task that should now force GC
        $this->assertTrue($resource_manager->should_trigger_gc_after_task('custom_task'));
    }

    /**
     * Test get_cpu_threshold method.
     */
    public function test_get_cpu_threshold() {
        // Test default threshold
        $this->assertEquals(0.7, $this->resource_manager->get_cpu_threshold());
        
        // Test with custom filter
        add_filter('status_sentry_cpu_threshold', function($threshold) {
            return 0.6;
        });
        
        // Create new instance to pick up the filter
        $resource_manager = new Status_Sentry_Resource_Manager();
        
        // Test custom threshold
        $this->assertEquals(0.6, $resource_manager->get_cpu_threshold());
    }

    /**
     * Test should_continue method.
     */
    public function test_should_continue() {
        $start_time = microtime(true);
        $memory_start = memory_get_usage();
        
        // Test with resources within budget
        $this->assertTrue($this->resource_manager->should_continue('standard', $start_time, $memory_start, 50));
        
        // Test with excessive database queries
        $this->assertFalse($this->resource_manager->should_continue('standard', $start_time, $memory_start, 200));
        
        // Test with excessive time (need to mock this)
        $mock_start_time = microtime(true) - 30; // 30 seconds ago
        $this->assertFalse($this->resource_manager->should_continue('standard', $mock_start_time, $memory_start, 50));
    }

    /**
     * Test trigger_gc method.
     */
    public function test_trigger_gc() {
        // Test triggering GC with force=true
        $result = $this->resource_manager->trigger_gc(true);
        $this->assertTrue($result);
        
        // Test triggering GC with force=false (depends on memory usage)
        // This is harder to test reliably, so we'll just make sure it doesn't crash
        $result = $this->resource_manager->trigger_gc(false);
        $this->assertInternalType('boolean', $result);
    }

    /**
     * Test get_system_load method.
     */
    public function test_get_system_load() {
        // Test that get_system_load returns a value between 0 and 1
        $load = $this->resource_manager->get_system_load();
        $this->assertGreaterThanOrEqual(0, $load);
        $this->assertLessThanOrEqual(1, $load);
    }
}
