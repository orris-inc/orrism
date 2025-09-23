<?php
/**
 * ORRISM Database Manager
 * Handles database installation, migration, and management for WHMCS
 *
 * @package    WHMCS
 * @author     ORRISM Development Team
 * @copyright  Copyright (c) 2024
 * @version    2.0
 */

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

use WHMCS\Database\Capsule;

// Include ORRISM database connection manager
require_once __DIR__ . '/orris_db.php';

/**
 * ORRISM Database Manager Class
 */
class OrrisDatabaseManager
{
    private static $instance = null;
    private $currentVersion = '1.0';
    private $moduleVersion = '2.0';
    private $useOrrisDB = true;  // Use separate ORRISM database
    
    /**
     * Get singleton instance
     * 
     * @return OrrisDatabaseManager
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Check if database tables are installed
     * 
     * @return bool
     */
    public function isInstalled()
    {
        try {
            // Use ORRISM database if configured
            if ($this->useOrrisDB) {
                $schema = OrrisDB::schema();
                if (!$schema) {
                    return false;
                }
                return $schema->hasTable('services') &&
                       $schema->hasTable('nodes') &&
                       $schema->hasTable('config');
            } else {
                // Fallback to WHMCS database
                return Capsule::schema()->hasTable('services') &&
                       Capsule::schema()->hasTable('nodes') &&
                       Capsule::schema()->hasTable('config');
            }
        } catch (Exception $e) {
            logModuleCall('orrism', __METHOD__, [], 'Database check failed: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get current database version
     * 
     * @return string|null
     */
    public function getCurrentVersion()
    {
        try {
            if (!$this->isInstalled()) {
                return null;
            }
            
            // Use ORRISM database if configured
            if ($this->useOrrisDB) {
                $version = OrrisDB::table('config')
                    ->where('config_key', 'database_version')
                    ->first();
            } else {
                $version = Capsule::table('config')
                    ->where('config_key', 'database_version')
                    ->first();
            }
                
            return $version ? $version->config_value : null;
        } catch (Exception $e) {
            logModuleCall('orrism', __METHOD__, [], 'Version check failed: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Install database tables
     * 
     * @return array Installation result
     */
    public function install()
    {
        try {
            // Check if already installed first
            if ($this->isInstalled()) {
                return [
                    'success' => false,
                    'message' => 'Database tables already exist'
                ];
            }
            
            // Get appropriate database connection
            if ($this->useOrrisDB) {
                $connection = OrrisDB::connection();
                if (!$connection) {
                    return [
                        'success' => false,
                        'message' => 'Failed to connect to ORRISM database. Please check addon module configuration.'
                    ];
                }
            } else {
                $connection = Capsule::connection();
            }
            
            $inTransaction = false;
            
            try {
                // Check if we're already in a transaction
                if (!$connection->transactionLevel()) {
                    $connection->beginTransaction();
                    $inTransaction = true;
                }
                
                // Create tables
                $this->createTables();
                
                // Insert default data
                $this->insertDefaultData();
                
                // Record installation
                $this->recordMigration('1.0', 'Initial database installation');
                
                // Commit only if we started the transaction
                if ($inTransaction) {
                    $connection->commit();
                }
                
                logModuleCall('orrism', __METHOD__, [], 'Database installation completed successfully');
                
                return [
                    'success' => true,
                    'message' => 'Database tables installed successfully'
                ];
                
            } catch (Exception $e) {
                // Rollback only if we started the transaction
                if ($inTransaction && $connection->transactionLevel()) {
                    try {
                        $connection->rollback();
                    } catch (Exception $rollbackError) {
                        // Log rollback error but continue with original error
                        logModuleCall('orrism', __METHOD__, [], 'Rollback error: ' . $rollbackError->getMessage());
                    }
                }
                throw $e;
            }
            
        } catch (Exception $e) {
            $errorMsg = 'Database installation failed: ' . $e->getMessage();
            logModuleCall('orrism', __METHOD__, [], $errorMsg);
            
            return [
                'success' => false,
                'message' => $errorMsg
            ];
        }
    }
    
    /**
     * Uninstall database tables
     * 
     * @param bool $keepData Whether to keep user data
     * @return array Uninstallation result
     */
    public function uninstall($keepData = false)
    {
        try {
            // Get schema builder based on configuration
            $schema = $this->useOrrisDB ? OrrisDB::schema() : Capsule::schema();
            
            if (!$schema) {
                throw new Exception('Failed to get database schema builder');
            }
            
            if (!$keepData) {
                // Drop all tables
                $tables = [
                    'service_usage',
                    'services', 
                    'nodes',
                    'node_groups',
                    'config',
                    'migrations'
                ];
                
                foreach ($tables as $table) {
                    if ($schema->hasTable($table)) {
                        $schema->drop($table);
                    }
                }
                
                $message = 'All database tables removed successfully';
            } else {
                // Only drop configuration tables, keep user data
                if ($schema->hasTable('config')) {
                    $schema->drop('config');
                }
                if ($schema->hasTable('migrations')) {
                    $schema->drop('migrations');
                }
                
                $message = 'Configuration tables removed, user data preserved';
            }
            
            logModuleCall('orrism', __METHOD__, ['keepData' => $keepData], $message);
            
            return [
                'success' => true,
                'message' => $message
            ];
            
        } catch (Exception $e) {
            $errorMsg = 'Database uninstallation failed: ' . $e->getMessage();
            logModuleCall('orrism', __METHOD__, ['keepData' => $keepData], $errorMsg);
            
            return [
                'success' => false,
                'message' => $errorMsg
            ];
        }
    }
    
    /**
     * Run database migration to latest version
     * 
     * @return array Migration result
     */
    public function migrate()
    {
        // Get appropriate connection for transactions
        $connection = $this->useOrrisDB ? OrrisDB::connection() : Capsule::connection();
        
        try {
            $currentVersion = $this->getCurrentVersion();
            
            if (!$currentVersion) {
                return $this->install();
            }
            
            if (version_compare($currentVersion, $this->currentVersion, '>=')) {
                return [
                    'success' => true,
                    'message' => 'Database is already up to date'
                ];
            }
            
            // Run specific migrations based on version
            $migrations = $this->getAvailableMigrations($currentVersion);
            
            if ($connection) {
                $connection->beginTransaction();
            }
            
            foreach ($migrations as $migration) {
                $this->runMigration($migration);
            }
            
            // Update database version
            $this->updateConfig('database_version', $this->currentVersion);
            
            if ($connection) {
                $connection->commit();
            }
            
            $message = 'Database migrated successfully to version ' . $this->currentVersion;
            logModuleCall('orrism', __METHOD__, [], $message);
            
            return [
                'success' => true,
                'message' => $message
            ];
            
        } catch (Exception $e) {
            if ($connection) {
                $connection->rollback();
            }
            
            $errorMsg = 'Database migration failed: ' . $e->getMessage();
            logModuleCall('orrism', __METHOD__, [], $errorMsg);
            
            return [
                'success' => false,
                'message' => $errorMsg
            ];
        }
    }
    
    /**
     * Create all database tables
     */
    private function createTables()
    {
        // Get schema builder based on configuration
        $schema = $this->useOrrisDB ? OrrisDB::schema() : Capsule::schema();
        
        if (!$schema) {
            throw new Exception('Failed to get database schema builder');
        }
        
        // Create node groups table
        if (!$schema->hasTable('node_groups')) {
            $schema->create('node_groups', function ($table) {
                $table->increments('id');
                $table->string('name', 100)->unique();
                $table->text('description')->nullable();
                $table->decimal('bandwidth_ratio', 3, 2)->default(1.00);
                $table->integer('max_devices')->default(3);
                $table->boolean('status')->default(true);
                $table->timestamps();
            });
        }
        
        // Create nodes table
        if (!$schema->hasTable('nodes')) {
            $schema->create('nodes', function ($table) {
            $table->increments('id');
            $table->string('node_type', 40)->default('shadowsocks');
            $table->integer('group_id')->default(1);
            $table->string('node_name');
            $table->string('address');
            $table->integer('port');
            $table->string('node_method', 50)->default('aes-256-gcm');
            $table->decimal('rate', 3, 2)->default(1.00);
            $table->string('network_type', 10)->default('tcp');
            $table->text('tag');
            $table->boolean('status')->default(true);
            $table->integer('sort_order')->default(0);
            $table->integer('max_users')->default(0);
            $table->integer('current_users')->default(0);
            $table->timestamps();
            
            $table->index(['group_id', 'status']);
            $table->index(['node_type', 'status']);
            });
        }
        
        // Create services table
        if (!$schema->hasTable('services')) {
            $schema->create('services', function ($table) {
            $table->increments('id');
            
            // WHMCS Service Relationship
            $table->integer('service_id')->unique()->comment('WHMCS tblhosting.id');
            $table->integer('client_id')->comment('WHMCS tblclients.userid');
            $table->string('domain', 255)->nullable()->comment('Service identifier/domain');
            $table->integer('product_id')->nullable()->comment('WHMCS tblproducts.id');
            
            // WHMCS Account Info (who owns this service)
            $table->string('whmcs_username', 100)->nullable()->comment('WHMCS account username');
            $table->string('whmcs_email')->comment('WHMCS account email');
            
            // ORRISM Service Credentials
            $table->string('service_username', 100)->unique()->comment('ORRISM service login username');
            $table->string('service_password', 255)->nullable()->comment('ORRISM service password (encrypted)');
            $table->string('uuid', 36)->unique()->comment('ORRISM service UUID');
            
            // Traffic and Limits
            $table->bigInteger('upload_bytes')->default(0);
            $table->bigInteger('download_bytes')->default(0);
            $table->bigInteger('bandwidth_limit')->default(0)->comment('Monthly bandwidth limit in bytes');
            $table->integer('node_group_id')->default(1)->comment('Allowed node group');
            
            // Service Status
            $table->enum('status', ['active', 'suspended', 'terminated'])->default('active');
            $table->boolean('need_reset')->default(true);
            
            // Dates
            $table->timestamp('last_reset_at')->nullable()->comment('Last traffic reset');
            $table->timestamp('expired_at')->nullable()->comment('Service expiry date');
            $table->timestamps();
            
            // Indexes
            $table->index('service_id');
            $table->index('client_id');
            $table->index('whmcs_email');
            $table->index('service_username');
            $table->index('status');
            $table->index(['node_group_id', 'status']);
            });
        }
        
        // Create service usage table
        if (!$schema->hasTable('service_usage')) {
            $schema->create('service_usage', function ($table) {
            $table->increments('id');
            $table->integer('service_id');
            $table->integer('node_id');
            $table->bigInteger('upload_bytes')->default(0);
            $table->bigInteger('download_bytes')->default(0);
            $table->timestamp('session_start');
            $table->timestamp('session_end')->nullable();
            $table->string('client_ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('recorded_at')->useCurrent();
            
            $table->index('service_id');
            $table->index('node_id');
            $table->index('recorded_at');
            
            // Foreign key constraints removed for MySQL compatibility
            // Data integrity will be maintained through application logic
            });
        }
        
        // Create config table
        if (!$schema->hasTable('config')) {
            $schema->create('config', function ($table) {
            $table->increments('id');
            $table->string('config_key', 100)->unique();
            $table->text('config_value')->nullable();
            $table->enum('config_type', ['string', 'integer', 'boolean', 'json', 'encrypted'])->default('string');
            $table->string('description')->nullable();
            $table->boolean('is_system')->default(false);
            $table->timestamps();
            });
        }
        
        // Create migrations table
        if (!$schema->hasTable('migrations')) {
            $schema->create('migrations', function ($table) {
            $table->increments('id');
            $table->string('version', 20)->unique();
            $table->string('description');
            $table->timestamp('executed_at')->useCurrent();
            });
        }
    }
    
    /**
     * Insert default data
     */
    private function insertDefaultData()
    {
        // Insert default node group if not exists
        $table = $this->useOrrisDB ? OrrisDB::table('node_groups') : Capsule::table('node_groups');
        
        if (!$table->where('id', 1)->exists()) {
            $table->insert([
                'id' => 1,
                'name' => 'Default',
                'description' => 'Default node group for new users',
                'bandwidth_ratio' => 1.00,
                'max_devices' => 3,
                'status' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);
        }
        
        // Insert default configuration
        $defaultConfigs = [
            ['config_key' => 'module_version', 'config_value' => $this->moduleVersion, 'description' => 'Current module version', 'is_system' => true],
            ['config_key' => 'database_version', 'config_value' => $this->currentVersion, 'description' => 'Current database schema version', 'is_system' => true],
            ['config_key' => 'subscription_base_url', 'config_value' => '', 'description' => 'Base URL for subscription services'],
            ['config_key' => 'default_encryption', 'config_value' => 'aes-256-gcm', 'description' => 'Default encryption method for new nodes'],
            ['config_key' => 'auto_reset_traffic', 'config_value' => '0', 'config_type' => 'boolean', 'description' => 'Enable automatic traffic reset'],
            ['config_key' => 'reset_day', 'config_value' => '1', 'config_type' => 'integer', 'description' => 'Day of month for traffic reset (1-28)'],
            ['config_key' => 'max_devices_per_user', 'config_value' => '3', 'config_type' => 'integer', 'description' => 'Maximum concurrent devices per user'],
            ['config_key' => 'enable_usage_logging', 'config_value' => '1', 'config_type' => 'boolean', 'description' => 'Enable detailed usage logging']
        ];
        
        foreach ($defaultConfigs as $config) {
            // Check if config key exists before inserting
            $configTable = $this->useOrrisDB ? OrrisDB::table('config') : Capsule::table('config');
            
            if (!$configTable->where('config_key', $config['config_key'])->exists()) {
                $configTable->insert(array_merge($config, [
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ]));
            }
        }
    }
    
    /**
     * Record migration execution
     * 
     * @param string $version Migration version
     * @param string $description Migration description
     */
    private function recordMigration($version, $description)
    {
        Capsule::table('migrations')->insert([
            'version' => $version,
            'description' => $description,
            'executed_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    /**
     * Get available migrations for current version
     * 
     * @param string $fromVersion Current version
     * @return array List of migrations to run
     */
    private function getAvailableMigrations($fromVersion)
    {
        // Define available migrations
        $migrations = [];
        
        // Add future migrations here
        // Example: if (version_compare($fromVersion, '1.1', '<')) {
        //     $migrations[] = ['version' => '1.1', 'method' => 'migrateTo11'];
        // }
        
        return $migrations;
    }
    
    /**
     * Run specific migration
     * 
     * @param array $migration Migration details
     */
    private function runMigration($migration)
    {
        if (method_exists($this, $migration['method'])) {
            $this->{$migration['method']}();
            $this->recordMigration($migration['version'], $migration['description'] ?? 'Migration to version ' . $migration['version']);
        }
    }
    
    /**
     * Update configuration value
     * 
     * @param string $key Configuration key
     * @param mixed $value Configuration value
     */
    private function updateConfig($key, $value)
    {
        Capsule::table('config')
            ->where('config_key', $key)
            ->update([
                'config_value' => $value,
                'updated_at' => date('Y-m-d H:i:s')
            ]);
    }
    
    /**
     * Check database connection
     * 
     * @return array Connection test result
     */
    public function testConnection()
    {
        try {
            // Use appropriate connection based on configuration
            if ($this->useOrrisDB) {
                $connection = OrrisDB::connection();
                if (!$connection) {
                    return [
                        'success' => false,
                        'message' => 'Failed to establish ORRISM database connection'
                    ];
                }
                $connection->getPdo();
            } else {
                Capsule::connection()->getPdo();
            }
            
            return [
                'success' => true,
                'message' => 'Database connection successful'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Database connection failed: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Get database status information
     * 
     * @return array Database status
     */
    public function getStatus()
    {
        $status = [
            'installed' => $this->isInstalled(),
            'current_version' => $this->getCurrentVersion(),
            'latest_version' => $this->currentVersion,
            'needs_migration' => false,
            'tables' => []
        ];
        
        if ($status['installed'] && $status['current_version']) {
            $status['needs_migration'] = version_compare($status['current_version'], $this->currentVersion, '<');
        }
        
        // Check table status
        $tables = [
            'services',
            'nodes', 
            'service_usage',
            'node_groups',
            'config',
            'migrations'
        ];
        
        foreach ($tables as $table) {
            try {
                // Use appropriate schema and table based on configuration
                if ($this->useOrrisDB) {
                    $schema = OrrisDB::schema();
                    $exists = $schema ? $schema->hasTable($table) : false;
                    $count = $exists ? OrrisDB::table($table)->count() : 0;
                } else {
                    $exists = Capsule::schema()->hasTable($table);
                    $count = $exists ? Capsule::table($table)->count() : 0;
                }
                
                $status['tables'][$table] = [
                    'exists' => $exists,
                    'records' => $count
                ];
            } catch (Exception $e) {
                $status['tables'][$table] = [
                    'exists' => false,
                    'error' => $e->getMessage()
                ];
            }
        }
        
        return $status;
    }
    
    /**
     * Get count of services in the ORRISM database
     * 
     * @return int Number of services
     */
    public function getServiceCount()
    {
        try {
            if (!$this->isInstalled()) {
                return 0;
            }
            
            $table = $this->useOrrisDB ? OrrisDB::table('services') : Capsule::table('services');
            return $table->count();
            
        } catch (Exception $e) {
            logModuleCall('orrism', __METHOD__, [], 'Error: ' . $e->getMessage());
            return 0;
        }
    }
    
}

/**
 * Helper function to get database manager instance
 * 
 * @return OrrisDatabaseManager
 */
function db_manager()
{
    return OrrisDatabaseManager::getInstance();
}
