<?php
/**
 * ORRISM Database Manager
 * Handles database installation, upgrades, and management
 * 
 * @package    ORRISM
 * @author     ORRISM Development Team
 * @version    2.0
 */

use WHMCS\Database\Capsule;

// Check if Capsule is available
if (!class_exists('WHMCS\Database\Capsule')) {
    if (!function_exists('logModuleCall')) {
        function logModuleCall($module, $action, $request, $response) {
            error_log("[$module] $action: " . json_encode(['request' => $request, 'response' => $response]));
        }
    }
    logModuleCall('orrism', 'database_manager_init', [], 'Capsule not available - likely CLI mode');
}

// Include OrrisDB helper if exists
$orrisDbPath = __DIR__ . '/orris_db.php';
if (file_exists($orrisDbPath)) {
    require_once $orrisDbPath;
}

class OrrisDatabaseManager
{
    private $currentVersion = '2.0.0';
    private $moduleVersion = '2.0.0';
    private $useOrrisDB = false;
    
    /**
     * Constructor
     */
    public function __construct()
    {
        // Check if we should use OrrisDB
        $this->useOrrisDB = class_exists('OrrisDB') && OrrisDB::isConfigured();
        
        if ($this->useOrrisDB) {
            logModuleCall('orrism', 'database_manager', [], 'Using OrrisDB for database operations');
        } else {
            logModuleCall('orrism', 'database_manager', [], 'Using Capsule for database operations');
        }
    }
    
    /**
     * Check if database tables are installed
     * 
     * @return bool
     */
    public function isInstalled()
    {
        try {
            if ($this->useOrrisDB) {
                $schema = OrrisDB::schema();
                if (!$schema) {
                    return false;
                }
                return $schema->hasTable('services') &&
                       $schema->hasTable('nodes') &&
                       $schema->hasTable('config');
            } else {
                // Use Capsule for WHMCS database
                return Capsule::schema()->hasTable('services') &&
                       Capsule::schema()->hasTable('nodes') &&
                       Capsule::schema()->hasTable('config');
            }
        } catch (Exception $e) {
            logModuleCall('orrism', __METHOD__, [], 'Error: ' . $e->getMessage());
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
            // Check if config table exists
            $schema = $this->useOrrisDB ? OrrisDB::schema() : Capsule::schema();
            if (!$schema->hasTable('config')) {
                return null;
            }
            
            // Get database version from config table
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
            logModuleCall('orrism', __METHOD__, [], 'Error: ' . $e->getMessage());
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
                
                // Version tracking is handled through the config table
                // No need for separate migrations table
                
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
            
            if ($keepData) {
                // Only drop configuration tables, keep user data
                $tables = ['config'];
                
                foreach ($tables as $table) {
                    if ($schema->hasTable($table)) {
                        $schema->drop($table);
                    }
                }
                
                $message = 'Configuration tables removed, user data preserved';
            } else {
                // Drop all tables
                $tables = [
                    'service_sessions',
                    'service_usage',
                    'services', 
                    'nodes',
                    'node_groups',
                    'config'
                ];
                
                foreach ($tables as $table) {
                    if ($schema->hasTable($table)) {
                        $schema->drop($table);
                    }
                }
                
                $message = 'All database tables removed successfully';
            }
            
            // Migrations table no longer used - version tracked in config
            
            logModuleCall('orrism', __METHOD__, ['keepData' => $keepData], $message);
            
            return [
                'success' => true,
                'message' => $message
            ];
            
        } catch (Exception $e) {
            $errorMsg = 'Database uninstallation failed: ' . $e->getMessage();
            logModuleCall('orrism', __METHOD__, [], $errorMsg);
            
            return [
                'success' => false,
                'message' => $errorMsg
            ];
        }
    }
    
    /**
     * Upgrade database to latest version
     * 
     * @return array Upgrade result
     */
    public function upgrade()
    {
        try {
            $currentVersion = $this->getCurrentVersion();
            
            if (!$currentVersion) {
                return [
                    'success' => false,
                    'message' => 'Unable to determine current database version'
                ];
            }
            
            if (version_compare($currentVersion, $this->currentVersion, '>=')) {
                return [
                    'success' => true,
                    'message' => 'Database is already up to date'
                ];
            }
            
            // Future migrations can be handled here if needed
            $migrations = $this->getAvailableMigrations($currentVersion);
            
            if ($connection) {
                $connection->beginTransaction();
            }
            
            // Future migrations can be handled here if needed
            foreach ($migrations as $migration) {
                // Migration logic would go here
            }
            
            // Update database version
            $this->updateConfig('database_version', $this->currentVersion);
            
            if ($connection) {
                $connection->commit();
            }
            
            logModuleCall('orrism', __METHOD__, [], 'Database upgraded from ' . $currentVersion . ' to ' . $this->currentVersion);
            
            return [
                'success' => true,
                'message' => 'Database upgraded successfully to version ' . $this->currentVersion
            ];
            
        } catch (Exception $e) {
            if (isset($connection) && $connection) {
                $connection->rollback();
            }
            
            $errorMsg = 'Database upgrade failed: ' . $e->getMessage();
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
        
        // Set default string length for utf8mb4
        if (method_exists($schema->getConnection()->getSchemaBuilder(), 'defaultStringLength')) {
            $schema->getConnection()->getSchemaBuilder()->defaultStringLength(191);
        }
        
        // Create node_groups table
        if (!$schema->hasTable('node_groups')) {
            $schema->create('node_groups', function ($table) {
                $table->bigIncrements('id');
                $table->string('name', 100)->unique();
                $table->text('description')->nullable();
                $table->decimal('bandwidth_ratio', 5, 2)->default(1.00);
                $table->unsignedInteger('max_devices')->default(3);
                $table->integer('sort_order')->default(0);
                $table->boolean('status')->default(true);
                $table->json('metadata')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent();
                
                // Indexes
                $table->index(['status', 'sort_order'], 'idx_status_sort');
            });
            
            // Set table options for utf8mb4
            if ($this->useOrrisDB) {
                OrrisDB::statement("ALTER TABLE `node_groups` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            } else {
                Capsule::statement("ALTER TABLE `node_groups` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            }
        }
        
        // Create nodes table
        if (!$schema->hasTable('nodes')) {
            $schema->create('nodes', function ($table) {
                $table->bigIncrements('id');
                $table->string('name', 100);
                $table->string('type', 20)->default('shadowsocks');
                $table->string('address', 255);
                $table->unsignedInteger('port');
                $table->string('method', 50)->nullable();
                $table->unsignedBigInteger('group_id')->nullable();
                $table->unsignedInteger('capacity')->default(1000);
                $table->unsignedInteger('current_load')->default(0);
                $table->unsignedBigInteger('bandwidth_limit')->nullable();
                $table->integer('sort_order')->default(0);
                $table->string('status', 20)->default('active');
                $table->integer('health_score')->default(100);
                $table->timestamp('last_check_at')->nullable();
                $table->json('config')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent();

                // Indexes
                $table->index(['group_id', 'status'], 'idx_group_status');
                $table->index(['type', 'status'], 'idx_type_status');
                $table->index(['current_load', 'capacity'], 'idx_load_capacity');
            });

            // Set table options for utf8mb4
            if ($this->useOrrisDB) {
                OrrisDB::statement("ALTER TABLE `nodes` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            } else {
                Capsule::statement("ALTER TABLE `nodes` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            }

            // Add CHECK constraints for data integrity (MySQL 8.0.16+)
            try {
                if ($this->useOrrisDB) {
                    OrrisDB::statement("ALTER TABLE `nodes` ADD CONSTRAINT `chk_node_type` CHECK (`type` IN ('shadowsocks', 'v2ray', 'trojan', 'vless', 'vmess', 'hysteria', 'snell'))");
                    OrrisDB::statement("ALTER TABLE `nodes` ADD CONSTRAINT `chk_node_status` CHECK (`status` IN ('active', 'inactive', 'maintenance'))");
                } else {
                    Capsule::statement("ALTER TABLE `nodes` ADD CONSTRAINT `chk_node_type` CHECK (`type` IN ('shadowsocks', 'v2ray', 'trojan', 'vless', 'vmess', 'hysteria', 'snell'))");
                    Capsule::statement("ALTER TABLE `nodes` ADD CONSTRAINT `chk_node_status` CHECK (`status` IN ('active', 'inactive', 'maintenance'))");
                }
            } catch (Exception $e) {
                // Log but don't fail on CHECK constraint errors (for MySQL < 8.0.16)
                logModuleCall('orrism', 'createTables_nodes_constraints', [], 'CHECK constraint warning: ' . $e->getMessage());
            }
        }
        
        // Create services table
        if (!$schema->hasTable('services')) {
            $schema->create('services', function ($table) {
                // Primary key - only unique constraint
                $table->bigIncrements('id');

                // Service identifiers - no unique constraints
                $table->unsignedBigInteger('service_id');
                $table->string('email', 255);
                $table->string('uuid', 36);
                $table->string('password', 255);
                $table->string('password_algo', 20)->default('bcrypt');

                // Traffic management
                $table->unsignedBigInteger('bandwidth_limit')->default(0);
                $table->unsignedBigInteger('upload_bytes')->default(0);
                $table->unsignedBigInteger('download_bytes')->default(0);
                // Note: total_bytes is a generated column, added via raw SQL below

                // Monthly traffic tracking
                $table->unsignedBigInteger('monthly_upload')->default(0);
                $table->unsignedBigInteger('monthly_download')->default(0);
                $table->unsignedTinyInteger('monthly_reset_day')->default(1);
                $table->timestamp('last_reset_at')->nullable();

                // Service management
                $table->string('status', 20)->default('pending');
                $table->unsignedBigInteger('node_group_id')->nullable();
                $table->unsignedInteger('max_devices')->default(3);
                $table->unsignedInteger('current_devices')->default(0);

                // Timestamps
                $table->timestamp('last_used_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent();

                // Additional data
                $table->json('metadata')->nullable();

                // Indexes for performance - NO unique constraints except primary key
                $table->index('service_id', 'idx_service_id');
                $table->index('email', 'idx_email');
                $table->index('uuid', 'idx_uuid');
                $table->index(['status', 'expires_at'], 'idx_status_expires');
                $table->index('node_group_id', 'idx_node_group');
                $table->index('last_used_at', 'idx_last_used');
                $table->index(['monthly_reset_day', 'last_reset_at'], 'idx_monthly_reset');
            });

            // Add generated column and additional index
            if ($this->useOrrisDB) {
                OrrisDB::statement("ALTER TABLE `services` ADD COLUMN `total_bytes` BIGINT UNSIGNED GENERATED ALWAYS AS (`upload_bytes` + `download_bytes`) STORED");
                OrrisDB::statement("ALTER TABLE `services` ADD INDEX `idx_traffic_check` (`total_bytes`, `bandwidth_limit`, `status`)");
                OrrisDB::statement("ALTER TABLE `services` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            } else {
                Capsule::statement("ALTER TABLE `services` ADD COLUMN `total_bytes` BIGINT UNSIGNED GENERATED ALWAYS AS (`upload_bytes` + `download_bytes`) STORED");
                Capsule::statement("ALTER TABLE `services` ADD INDEX `idx_traffic_check` (`total_bytes`, `bandwidth_limit`, `status`)");
                Capsule::statement("ALTER TABLE `services` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            }

            // Add CHECK constraint for status (MySQL 8.0.16+)
            try {
                if ($this->useOrrisDB) {
                    OrrisDB::statement("ALTER TABLE `services` ADD CONSTRAINT `chk_service_status` CHECK (`status` IN ('active', 'suspended', 'expired', 'banned', 'pending'))");
                } else {
                    Capsule::statement("ALTER TABLE `services` ADD CONSTRAINT `chk_service_status` CHECK (`status` IN ('active', 'suspended', 'expired', 'banned', 'pending'))");
                }
            } catch (Exception $e) {
                // Log but don't fail on CHECK constraint errors (for MySQL < 8.0.16)
                logModuleCall('orrism', 'createTables_services_constraints', [], 'CHECK constraint warning: ' . $e->getMessage());
            }
        }
        
        // Create service_usage table
        if (!$schema->hasTable('service_usage')) {
            $schema->create('service_usage', function ($table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('service_id');
                $table->unsignedBigInteger('node_id');
                $table->unsignedBigInteger('upload_bytes')->default(0);
                $table->unsignedBigInteger('download_bytes')->default(0);
                $table->timestamp('session_start')->useCurrent();
                $table->timestamp('session_end')->nullable();
                $table->unsignedInteger('session_duration')->nullable();
                $table->string('client_ip', 45)->nullable();
                $table->string('device_id', 100)->nullable();
                $table->timestamp('created_at')->useCurrent();
                
                // Indexes
                $table->index(['service_id', 'created_at'], 'idx_service_time');
                $table->index(['node_id', 'created_at'], 'idx_node_time');
                $table->index(['service_id', 'session_start', 'session_end'], 'idx_session');
                $table->index(['device_id', 'service_id'], 'idx_device');
            });
            
            // Set table options for utf8mb4
            if ($this->useOrrisDB) {
                OrrisDB::statement("ALTER TABLE `service_usage` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            } else {
                Capsule::statement("ALTER TABLE `service_usage` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            }
        }
        
        // Create config table
        if (!$schema->hasTable('config')) {
            $schema->create('config', function ($table) {
                $table->increments('id');
                $table->string('config_key', 100)->unique();
                $table->text('config_value')->nullable();
                $table->string('config_type', 20)->default('string');
                $table->string('category', 50)->default('general');
                $table->text('description')->nullable();
                $table->boolean('is_system')->default(false);
                $table->boolean('is_public')->default(false);
                $table->json('validation_rules')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent();

                // Indexes
                $table->unique('config_key', 'uk_config_key');
                $table->index('category', 'idx_category');
                $table->index('is_system', 'idx_system');
                $table->index('is_public', 'idx_public');
            });

            // Set table options for utf8mb4
            if ($this->useOrrisDB) {
                OrrisDB::statement("ALTER TABLE `config` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            } else {
                Capsule::statement("ALTER TABLE `config` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            }

            // Add CHECK constraint for config_type (MySQL 8.0.16+)
            try {
                if ($this->useOrrisDB) {
                    OrrisDB::statement("ALTER TABLE `config` ADD CONSTRAINT `chk_config_type` CHECK (`config_type` IN ('string', 'integer', 'boolean', 'json', 'encrypted'))");
                } else {
                    Capsule::statement("ALTER TABLE `config` ADD CONSTRAINT `chk_config_type` CHECK (`config_type` IN ('string', 'integer', 'boolean', 'json', 'encrypted'))");
                }
            } catch (Exception $e) {
                // Log but don't fail on CHECK constraint errors (for MySQL < 8.0.16)
                logModuleCall('orrism', 'createTables_config_constraints', [], 'CHECK constraint warning: ' . $e->getMessage());
            }
        }
        
        // Create service_sessions table
        if (!$schema->hasTable('service_sessions')) {
            $schema->create('service_sessions', function ($table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('service_id');
                $table->unsignedBigInteger('node_id');
                $table->string('session_token', 64)->unique();
                $table->string('device_id', 100)->nullable();
                $table->string('client_ip', 45)->nullable();
                $table->unsignedInteger('client_port')->nullable();
                $table->unsignedBigInteger('bytes_sent')->default(0);
                $table->unsignedBigInteger('bytes_received')->default(0);
                $table->timestamp('connected_at')->useCurrent();
                $table->timestamp('last_activity')->useCurrent();
                $table->json('metadata')->nullable();

                // Indexes
                $table->unique('session_token', 'uk_session_token');
                $table->index(['service_id', 'connected_at'], 'idx_service_active');
                $table->index(['node_id', 'connected_at'], 'idx_node_active');
                $table->index(['device_id', 'service_id'], 'idx_device');
                $table->index('last_activity', 'idx_last_activity');
            });

            // Set table options for utf8mb4
            if ($this->useOrrisDB) {
                OrrisDB::statement("ALTER TABLE `service_sessions` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            } else {
                Capsule::statement("ALTER TABLE `service_sessions` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            }
        }
        
        // Create additional indexes for better performance
        try {
            if ($this->useOrrisDB) {
                // For OrrisDB, check if indexes exist before creating
                $existingIndexes = OrrisDB::select("SHOW INDEX FROM `services` WHERE Key_name = 'idx_services_traffic'");
                if (empty($existingIndexes)) {
                    OrrisDB::statement("CREATE INDEX `idx_services_traffic` ON `services` (`upload_bytes`, `download_bytes`)");
                }
                
                $existingIndexes = OrrisDB::select("SHOW INDEX FROM `service_usage` WHERE Key_name = 'idx_usage_created'");
                if (empty($existingIndexes)) {
                    OrrisDB::statement("CREATE INDEX `idx_usage_created` ON `service_usage` (`created_at`)");
                }
                
                $existingIndexes = OrrisDB::select("SHOW INDEX FROM `nodes` WHERE Key_name = 'idx_nodes_health'");
                if (empty($existingIndexes)) {
                    OrrisDB::statement("CREATE INDEX `idx_nodes_health` ON `nodes` (`health_score`, `status`)");
                }
            } else {
                // For Capsule, check if indexes exist before creating
                $existingIndexes = Capsule::select("SHOW INDEX FROM `services` WHERE Key_name = 'idx_services_traffic'");
                if (empty($existingIndexes)) {
                    Capsule::statement("CREATE INDEX `idx_services_traffic` ON `services` (`upload_bytes`, `download_bytes`)");
                }
                
                $existingIndexes = Capsule::select("SHOW INDEX FROM `service_usage` WHERE Key_name = 'idx_usage_created'");
                if (empty($existingIndexes)) {
                    Capsule::statement("CREATE INDEX `idx_usage_created` ON `service_usage` (`created_at`)");
                }
                
                $existingIndexes = Capsule::select("SHOW INDEX FROM `nodes` WHERE Key_name = 'idx_nodes_health'");
                if (empty($existingIndexes)) {
                    Capsule::statement("CREATE INDEX `idx_nodes_health` ON `nodes` (`health_score`, `status`)");
                }
            }
        } catch (Exception $e) {
            // Log but don't fail on index creation errors
            logModuleCall('orrism', 'createTables_indexes', [], 'Index creation warning: ' . $e->getMessage());
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
                'description' => 'Default node group',
                'bandwidth_ratio' => 1.00,
                'max_devices' => 3,
                'status' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);
        }
        
        // Insert default configuration
        $defaultConfigs = [
            ['config_key' => 'database_version', 'config_value' => $this->currentVersion, 'config_type' => 'string', 'category' => 'system', 'description' => 'Current database schema version', 'is_system' => true],
            ['config_key' => 'module_version', 'config_value' => $this->moduleVersion, 'config_type' => 'string', 'category' => 'system', 'description' => 'Current module version', 'is_system' => true],
            ['config_key' => 'default_encryption', 'config_value' => 'aes-256-gcm', 'config_type' => 'string', 'category' => 'node', 'description' => 'Default encryption method for new nodes', 'is_system' => false],
            ['config_key' => 'auto_reset_traffic', 'config_value' => '0', 'config_type' => 'boolean', 'category' => 'traffic', 'description' => 'Enable automatic monthly traffic reset', 'is_system' => false],
            ['config_key' => 'reset_day', 'config_value' => '1', 'config_type' => 'integer', 'category' => 'traffic', 'description' => 'Day of month for traffic reset (1-28)', 'is_system' => false],
            ['config_key' => 'max_devices_per_user', 'config_value' => '3', 'config_type' => 'integer', 'category' => 'service', 'description' => 'Default maximum devices per service', 'is_system' => false],
            ['config_key' => 'enable_usage_logging', 'config_value' => '1', 'config_type' => 'boolean', 'category' => 'logging', 'description' => 'Enable detailed usage logging', 'is_system' => false],
            ['config_key' => 'session_timeout', 'config_value' => '86400', 'config_type' => 'integer', 'category' => 'session', 'description' => 'Session timeout in seconds (24 hours)', 'is_system' => false],
            ['config_key' => 'subscription_base_url', 'config_value' => '', 'config_type' => 'string', 'category' => 'api', 'description' => 'Base URL for subscription services', 'is_system' => false]
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
     * Get available migrations for current version
     * 
     * @param string $currentVersion Current database version
     * @return array List of migrations to run
     */
    private function getAvailableMigrations($currentVersion)
    {
        // Define available migrations
        $migrations = [];
        
        // Add future migrations here
        // Example:
        //     $migrations[] = ['version' => '2.1', 'method' => 'migrateTo21'];
        
        return $migrations;
    }
    
    /**
     * Update configuration value
     * 
     * @param string $key Configuration key
     * @param mixed $value Configuration value
     */
    private function updateConfig($key, $value)
    {
        $table = $this->useOrrisDB ? OrrisDB::table('config') : Capsule::table('config');
        $table->where('config_key', $key)
            ->update([
                'config_value' => $value,
                'updated_at' => date('Y-m-d H:i:s')
            ]);
    }
    
    /**
     * Test database connection
     * 
     * @return bool
     */
    public function testConnection()
    {
        try {
            if ($this->useOrrisDB) {
                $pdo = OrrisDB::connection()->getPdo();
            } else {
                $pdo = Capsule::connection()->getPdo();
            }
            
            return $pdo !== null;
        } catch (Exception $e) {
            logModuleCall('orrism', __METHOD__, [], 'Connection test failed: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get service count
     * 
     * @return int Number of services
     */
    public function getServiceCount()
    {
        try {
            $table = $this->useOrrisDB ? OrrisDB::table('services') : Capsule::table('services');
            return $table->count();
        } catch (Exception $e) {
            return 0;
        }
    }
    
    /**
     * Check if specific table exists
     * 
     * @param string $tableName Table name to check
     * @return bool
     */
    public function tableExists($tableName)
    {
        try {
            $schema = $this->useOrrisDB ? OrrisDB::schema() : Capsule::schema();
            return $schema->hasTable($tableName);
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Get all installed tables
     * 
     * @return array List of table names
     */
    public function getInstalledTables()
    {
        $tables = [
            'services',
            'nodes', 
            'service_usage',
            'service_sessions',
            'node_groups',
            'config'
        ];
        
        $installed = [];
        foreach ($tables as $table) {
            try {
                // Use appropriate schema and table based on configuration
                if ($this->useOrrisDB) {
                    $schema = OrrisDB::schema();
                    $exists = $schema ? $schema->hasTable($table) : false;
                } else {
                    $exists = Capsule::schema()->hasTable($table);
                }
                
                if ($exists) {
                    $installed[] = $table;
                }
            } catch (Exception $e) {
                // Continue checking other tables
                continue;
            }
        }
        
        return $installed;
    }
}