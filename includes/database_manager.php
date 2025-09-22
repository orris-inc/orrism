<?php
/**
 * ORRIS Database Manager
 * Handles database installation, migration, and management for WHMCS
 *
 * @package    WHMCS
 * @author     ORRIS Development Team
 * @copyright  Copyright (c) 2024
 * @version    2.0
 */

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

use WHMCS\Database\Capsule;

/**
 * ORRIS Database Manager Class
 */
class OrrisDatabaseManager
{
    private static $instance = null;
    private $currentVersion = '1.0';
    private $moduleVersion = '2.0';
    
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
            return Capsule::schema()->hasTable('mod_orris_users') &&
                   Capsule::schema()->hasTable('mod_orris_nodes') &&
                   Capsule::schema()->hasTable('mod_orris_config');
        } catch (Exception $e) {
            logModuleCall('orris', __METHOD__, [], 'Database check failed: ' . $e->getMessage());
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
            
            $version = Capsule::table('mod_orris_config')
                ->where('config_key', 'database_version')
                ->first();
                
            return $version ? $version->config_value : null;
        } catch (Exception $e) {
            logModuleCall('orris', __METHOD__, [], 'Version check failed: ' . $e->getMessage());
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
            // Start transaction
            Capsule::beginTransaction();
            
            // Create tables
            $this->createTables();
            
            // Insert default data
            $this->insertDefaultData();
            
            // Record installation
            $this->recordMigration('1.0', 'Initial database installation');
            
            Capsule::commit();
            
            logModuleCall('orris', __METHOD__, [], 'Database installation completed successfully');
            
            return [
                'success' => true,
                'message' => 'Database tables installed successfully'
            ];
            
        } catch (Exception $e) {
            Capsule::rollback();
            
            $errorMsg = 'Database installation failed: ' . $e->getMessage();
            logModuleCall('orris', __METHOD__, [], $errorMsg);
            
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
            if (!$keepData) {
                // Drop all tables
                $tables = [
                    'mod_orris_user_usage',
                    'mod_orris_users', 
                    'mod_orris_nodes',
                    'mod_orris_node_groups',
                    'mod_orris_config',
                    'mod_orris_migrations'
                ];
                
                foreach ($tables as $table) {
                    if (Capsule::schema()->hasTable($table)) {
                        Capsule::schema()->drop($table);
                    }
                }
                
                $message = 'All database tables removed successfully';
            } else {
                // Only drop configuration tables, keep user data
                if (Capsule::schema()->hasTable('mod_orris_config')) {
                    Capsule::schema()->drop('mod_orris_config');
                }
                if (Capsule::schema()->hasTable('mod_orris_migrations')) {
                    Capsule::schema()->drop('mod_orris_migrations');
                }
                
                $message = 'Configuration tables removed, user data preserved';
            }
            
            logModuleCall('orris', __METHOD__, ['keepData' => $keepData], $message);
            
            return [
                'success' => true,
                'message' => $message
            ];
            
        } catch (Exception $e) {
            $errorMsg = 'Database uninstallation failed: ' . $e->getMessage();
            logModuleCall('orris', __METHOD__, ['keepData' => $keepData], $errorMsg);
            
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
            
            Capsule::beginTransaction();
            
            foreach ($migrations as $migration) {
                $this->runMigration($migration);
            }
            
            // Update database version
            $this->updateConfig('database_version', $this->currentVersion);
            
            Capsule::commit();
            
            $message = 'Database migrated successfully to version ' . $this->currentVersion;
            logModuleCall('orris', __METHOD__, [], $message);
            
            return [
                'success' => true,
                'message' => $message
            ];
            
        } catch (Exception $e) {
            Capsule::rollback();
            
            $errorMsg = 'Database migration failed: ' . $e->getMessage();
            logModuleCall('orris', __METHOD__, [], $errorMsg);
            
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
        // Create node groups table
        Capsule::schema()->create('mod_orris_node_groups', function ($table) {
            $table->increments('id');
            $table->string('name', 100)->unique();
            $table->text('description')->nullable();
            $table->decimal('bandwidth_ratio', 3, 2)->default(1.00);
            $table->integer('max_devices')->default(3);
            $table->boolean('status')->default(true);
            $table->timestamps();
        });
        
        // Create nodes table
        Capsule::schema()->create('mod_orris_nodes', function ($table) {
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
        
        // Create users table
        Capsule::schema()->create('mod_orris_users', function ($table) {
            $table->increments('id');
            $table->integer('service_id')->unique();
            $table->integer('client_id');
            $table->string('email');
            $table->string('uuid', 36)->unique();
            $table->string('password_hash')->nullable();
            $table->bigInteger('upload_bytes')->default(0);
            $table->bigInteger('download_bytes')->default(0);
            $table->bigInteger('bandwidth_limit')->default(0);
            $table->integer('node_group_id')->default(1);
            $table->enum('status', ['active', 'suspended', 'terminated'])->default('active');
            $table->boolean('need_reset')->default(true);
            $table->timestamp('last_reset_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->timestamps();
            
            $table->index('client_id');
            $table->index('email');
            $table->index('status');
            $table->index(['node_group_id', 'status']);
        });
        
        // Create user usage table
        Capsule::schema()->create('mod_orris_user_usage', function ($table) {
            $table->increments('id');
            $table->integer('user_id');
            $table->integer('service_id');
            $table->integer('node_id');
            $table->bigInteger('upload_bytes')->default(0);
            $table->bigInteger('download_bytes')->default(0);
            $table->timestamp('session_start');
            $table->timestamp('session_end')->nullable();
            $table->string('client_ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('recorded_at')->useCurrent();
            
            $table->index('user_id');
            $table->index('service_id');
            $table->index('node_id');
            $table->index('recorded_at');
            
            $table->foreign('user_id')->references('id')->on('mod_orris_users')->onDelete('cascade');
            $table->foreign('node_id')->references('id')->on('mod_orris_nodes')->onDelete('cascade');
        });
        
        // Create config table
        Capsule::schema()->create('mod_orris_config', function ($table) {
            $table->increments('id');
            $table->string('config_key', 100)->unique();
            $table->text('config_value')->nullable();
            $table->enum('config_type', ['string', 'integer', 'boolean', 'json', 'encrypted'])->default('string');
            $table->string('description')->nullable();
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });
        
        // Create migrations table
        Capsule::schema()->create('mod_orris_migrations', function ($table) {
            $table->increments('id');
            $table->string('version', 20)->unique();
            $table->string('description');
            $table->timestamp('executed_at')->useCurrent();
        });
    }
    
    /**
     * Insert default data
     */
    private function insertDefaultData()
    {
        // Insert default node group
        Capsule::table('mod_orris_node_groups')->insert([
            'id' => 1,
            'name' => 'Default',
            'description' => 'Default node group for new users',
            'bandwidth_ratio' => 1.00,
            'max_devices' => 3,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now()
        ]);
        
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
            Capsule::table('mod_orris_config')->insert(array_merge($config, [
                'created_at' => now(),
                'updated_at' => now()
            ]));
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
        Capsule::table('mod_orris_migrations')->insert([
            'version' => $version,
            'description' => $description,
            'executed_at' => now()
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
        Capsule::table('mod_orris_config')
            ->where('config_key', $key)
            ->update([
                'config_value' => $value,
                'updated_at' => now()
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
            Capsule::connection()->getPdo();
            
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
            'mod_orris_users',
            'mod_orris_nodes', 
            'mod_orris_user_usage',
            'mod_orris_node_groups',
            'mod_orris_config',
            'mod_orris_migrations'
        ];
        
        foreach ($tables as $table) {
            try {
                $exists = Capsule::schema()->hasTable($table);
                $count = $exists ? Capsule::table($table)->count() : 0;
                
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
}

/**
 * Helper function to get database manager instance
 * 
 * @return OrrisDatabaseManager
 */
function orris_db_manager()
{
    return OrrisDatabaseManager::getInstance();
}