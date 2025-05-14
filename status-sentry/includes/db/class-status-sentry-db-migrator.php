<?php
/**
 * Database migrator class.
 *
 * @since      1.0.0
 * @package    Status_Sentry
 * @subpackage Status_Sentry/includes/db
 */

/**
 * Database Migrator Class
 *
 * This class manages database migrations for the Status Sentry plugin. It provides
 * a robust system for versioning and applying database schema changes in a controlled,
 * sequential manner.
 *
 * Key features:
 * - Version tracking to ensure migrations run only once
 * - Sequential execution of migrations based on version numbers
 * - Automatic discovery of migration files
 * - Transaction support for safe schema changes
 * - Error handling and logging
 *
 * The migrator follows a convention-based approach where migration files are named
 * with a version number prefix (e.g., 001_create_queue_table.php) and contain a
 * class with up() and down() methods for applying and reverting changes.
 *
 * @since      1.0.0
 * @package    Status_Sentry
 * @subpackage Status_Sentry/includes/db
 * @author     Status Sentry Team
 */
class Status_Sentry_DB_Migrator {

    /**
     * The migrations directory.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $migrations_dir    The migrations directory.
     */
    private $migrations_dir;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     */
    public function __construct() {
        $this->migrations_dir = STATUS_SENTRY_PLUGIN_DIR . 'includes/db/migrations/';
    }

    /**
     * Run database migrations.
     *
     * This method orchestrates the migration process by:
     * 1. Determining the current database schema version
     * 2. Discovering available migration files
     * 3. Running migrations in sequential order
     * 4. Updating the version after each successful migration
     * 5. Handling errors and providing detailed logging
     *
     * Migrations are run in a transaction when possible to ensure database integrity.
     * If a migration fails, the process stops and returns false.
     *
     * @since    1.0.0
     * @param    bool    $use_transactions    Whether to use transactions for migrations (default: true).
     * @return   bool                         Whether all migrations were successfully run.
     */
    public function run_migrations($use_transactions = true) {
        global $wpdb;

        // Log migration start
        error_log('Status Sentry: Starting database migrations');

        // Get the current migration version
        $current_version = $this->get_migration_version();
        error_log(sprintf('Status Sentry: Current database version: %d', $current_version));

        // Get available migrations
        $migrations = $this->get_available_migrations();
        if (empty($migrations)) {
            error_log('Status Sentry: No migrations found');
            return true; // No migrations to run is considered success
        }

        // Sort migrations by version
        ksort($migrations);

        // Check if we have migrations to run
        $pending_migrations = array_filter(array_keys($migrations), function($version) use ($current_version) {
            return $version > $current_version;
        });

        if (empty($pending_migrations)) {
            error_log('Status Sentry: No pending migrations');
            return true;
        }

        error_log(sprintf('Status Sentry: Found %d pending migrations', count($pending_migrations)));

        // Check if we can use transactions
        $can_use_transactions = $use_transactions &&
                               method_exists($wpdb, 'query') &&
                               method_exists($wpdb, 'begin') &&
                               method_exists($wpdb, 'commit') &&
                               method_exists($wpdb, 'rollback');

        // Start transaction if supported
        if ($can_use_transactions) {
            error_log('Status Sentry: Using transactions for migrations');
            $wpdb->query('START TRANSACTION');
        } else {
            error_log('Status Sentry: Not using transactions for migrations');
        }

        // Run migrations
        $success = true;
        foreach ($migrations as $version => $migration) {
            // Skip migrations that have already been run
            if ($version <= $current_version) {
                continue;
            }

            // Log migration execution
            error_log(sprintf('Status Sentry: Running migration %d: %s', $version, basename($migration)));

            // Run the migration
            try {
                $start_time = microtime(true);
                $result = $this->run_migration($migration);
                $execution_time = microtime(true) - $start_time;

                if ($result) {
                    // Update the migration version
                    $this->update_migration_version($version);
                    error_log(sprintf('Status Sentry: Migration %d completed successfully in %.2f seconds', $version, $execution_time));
                } else {
                    error_log(sprintf('Status Sentry: Migration %d failed', $version));
                    $success = false;
                    break;
                }
            } catch (Exception $e) {
                error_log(sprintf('Status Sentry: Migration %d failed with exception: %s', $version, $e->getMessage()));
                $success = false;
                break;
            }
        }

        // Commit or rollback transaction
        if ($can_use_transactions) {
            if ($success) {
                $wpdb->query('COMMIT');
                error_log('Status Sentry: Migrations committed successfully');
            } else {
                $wpdb->query('ROLLBACK');
                error_log('Status Sentry: Migrations rolled back due to failure');
            }
        }

        // Log final status
        if ($success) {
            error_log(sprintf('Status Sentry: Database migrations completed successfully, new version: %d', $this->get_migration_version()));
        } else {
            error_log('Status Sentry: Database migrations failed');
        }

        return $success;
    }

    /**
     * Get the current migration version.
     *
     * @since    1.0.0
     * @access   private
     * @return   int    The current migration version.
     */
    private function get_migration_version() {
        return (int) get_option('status_sentry_db_version', 0);
    }

    /**
     * Update the migration version.
     *
     * @since    1.0.0
     * @access   private
     * @param    int    $version    The new migration version.
     * @return   bool               Whether the migration version was successfully updated.
     */
    private function update_migration_version($version) {
        return update_option('status_sentry_db_version', $version);
    }

    /**
     * Get available migrations.
     *
     * @since    1.0.0
     * @access   private
     * @return   array    The available migrations.
     */
    private function get_available_migrations() {
        $migrations = [];

        // Check if the migrations directory exists
        if (!is_dir($this->migrations_dir)) {
            return $migrations;
        }

        // Get migration files
        $files = glob($this->migrations_dir . '*.php');

        foreach ($files as $file) {
            // Extract the version from the filename
            $filename = basename($file);
            if (preg_match('/^(\d+)_.*\.php$/', $filename, $matches)) {
                $version = (int) $matches[1];
                $migrations[$version] = $file;
            }
        }

        // Add hardcoded migrations for 1.2.0
        // This ensures that the new migrations are included even if the files don't exist yet
        if (!isset($migrations[5])) {
            $file = $this->migrations_dir . '005_create_task_state_table.php';
            if (file_exists($file)) {
                $migrations[5] = $file;
            } else {
                error_log('Status Sentry: Migration file not found: ' . $file);
            }
        }
        if (!isset($migrations[6])) {
            $file = $this->migrations_dir . '006_create_query_cache_table.php';
            if (file_exists($file)) {
                $migrations[6] = $file;
            } else {
                error_log('Status Sentry: Migration file not found: ' . $file);
            }
        }
        if (!isset($migrations[7])) {
            $file = $this->migrations_dir . '007_add_composite_indexes.php';
            if (file_exists($file)) {
                $migrations[7] = $file;
            } else {
                error_log('Status Sentry: Migration file not found: ' . $file);
            }
        }

        // Add hardcoded migrations for 1.4.0
        if (!isset($migrations[8])) {
            $file = $this->migrations_dir . '008_create_monitoring_events_table.php';
            if (file_exists($file)) {
                $migrations[8] = $file;
            } else {
                error_log('Status Sentry: Migration file not found: ' . $file);
            }
        }
        if (!isset($migrations[9])) {
            $file = $this->migrations_dir . '009_create_cron_logs_table.php';
            if (file_exists($file)) {
                $migrations[9] = $file;
            } else {
                error_log('Status Sentry: Migration file not found: ' . $file);
            }
        }

        return $migrations;
    }

    /**
     * Run a single migration.
     *
     * This method executes a single migration file by:
     * 1. Loading the migration file
     * 2. Extracting the migration class name from the filename
     * 3. Instantiating the migration class
     * 4. Calling the up() method to apply the migration
     *
     * The method includes error handling to catch and log any issues that
     * occur during the migration process.
     *
     * @since    1.0.0
     * @access   private
     * @param    string    $migration    The full path to the migration file.
     * @return   bool                    Whether the migration was successfully run.
     * @throws   Exception               If an error occurs during migration.
     */
    private function run_migration($migration) {
        // Validate migration file
        if (!file_exists($migration)) {
            error_log(sprintf('Status Sentry: Migration file not found: %s', $migration));
            return false;
        }

        try {
            // Include the migration file
            require_once $migration;

            // Extract the migration class name from the filename
            $filename = basename($migration);
            if (!preg_match('/^\d+_(.*)\.php$/', $filename, $matches)) {
                error_log(sprintf('Status Sentry: Invalid migration filename format: %s', $filename));
                return false;
            }

            $class_name = 'Status_Sentry_Migration_' . $this->camelize($matches[1]);

            // Check if the migration class exists
            if (!class_exists($class_name)) {
                error_log(sprintf('Status Sentry: Migration class not found: %s', $class_name));
                return false;
            }

            // Create an instance of the migration class
            $migration_instance = new $class_name();

            // Check if the up method exists
            if (!method_exists($migration_instance, 'up')) {
                error_log(sprintf('Status Sentry: Migration class %s does not have an up() method', $class_name));
                return false;
            }

            // Run the migration
            $result = $migration_instance->up();

            // Check the result
            if ($result === false) {
                error_log(sprintf('Status Sentry: Migration %s returned false', $class_name));
                return false;
            }

            return true;
        } catch (Exception $e) {
            error_log(sprintf('Status Sentry: Exception in migration %s: %s', basename($migration), $e->getMessage()));
            throw $e; // Re-throw to be caught by the caller
        } catch (Error $e) {
            error_log(sprintf('Status Sentry: Error in migration %s: %s', basename($migration), $e->getMessage()));
            throw new Exception($e->getMessage(), $e->getCode(), $e); // Convert to Exception and re-throw
        }
    }

    /**
     * Convert a string to camel case.
     *
     * @since    1.0.0
     * @access   private
     * @param    string    $string    The string to convert.
     * @return   string               The camel case string.
     */
    private function camelize($string) {
        $string = str_replace(['_', '-'], ' ', $string);
        $string = ucwords($string);
        $string = str_replace(' ', '', $string);

        return $string;
    }
}
