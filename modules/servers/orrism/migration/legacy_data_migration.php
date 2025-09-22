<?php
/**
 * ORRISM Legacy Data Migration Script
 * Migrates data from old ShadowSocks database to new WHMCS-integrated structure
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

require_once dirname(__DIR__) . '/includes/database_manager.php';
require_once dirname(__DIR__) . '/includes/whmcs_database.php';

/**
 * ORRISM Legacy Migration Class
 */
class OrrisLegacyMigration
{
    private $legacyConfig;
    private $dbManager;
    private $orrismDb;
    private $migrationLog = [];
    
    public function __construct($legacyConfig)
    {
        $this->legacyConfig = $legacyConfig;
        $this->dbManager = orrism_db_manager();
        $this->orrismDb = orrism_db();
    }
    
    /**
     * Run complete migration process
     * 
     * @return array Migration result
     */
    public function migrate()
    {
        try {
            $this->log('Starting ORRISM legacy data migration...');
            
            // Step 1: Validate prerequisites
            $this->validatePrerequisites();
            
            // Step 2: Connect to legacy database
            $legacyDb = $this->connectToLegacyDatabase();
            
            // Step 3: Migrate nodes
            $this->migrateNodes($legacyDb);
            
            // Step 4: Migrate users
            $this->migrateUsers($legacyDb);
            
            // Step 5: Migrate usage data
            $this->migrateUsageData($legacyDb);
            
            // Step 6: Verify migration
            $this->verifyMigration($legacyDb);
            
            $this->log('Migration completed successfully!');
            
            return [
                'success' => true,
                'message' => 'Data migration completed successfully',
                'log' => $this->migrationLog
            ];
            
        } catch (Exception $e) {
            $this->log('Migration failed: ' . $e->getMessage(), 'error');
            
            return [
                'success' => false,
                'message' => 'Migration failed: ' . $e->getMessage(),
                'log' => $this->migrationLog
            ];
        }
    }
    
    /**
     * Validate migration prerequisites
     */
    private function validatePrerequisites()
    {
        $this->log('Validating prerequisites...');
        
        // Check if ORRISM tables are installed
        if (!$this->dbManager->isInstalled()) {
            throw new Exception('ORRISM database tables not installed. Please install first.');
        }
        
        // Check legacy database configuration
        $required = ['mysql_host', 'mysql_db', 'mysql_user', 'mysql_pass'];
        foreach ($required as $key) {
            if (empty($this->legacyConfig[$key])) {
                throw new Exception("Legacy database configuration missing: {$key}");
            }
        }
        
        $this->log('Prerequisites validated');
    }
    
    /**
     * Connect to legacy database
     * 
     * @return PDO Legacy database connection
     */
    private function connectToLegacyDatabase()
    {
        $this->log('Connecting to legacy database...');
        
        try {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                $this->legacyConfig['mysql_host'],
                $this->legacyConfig['mysql_port'] ?: 3306,
                $this->legacyConfig['mysql_db']
            );
            
            $pdo = new PDO(
                $dsn,
                $this->legacyConfig['mysql_user'],
                $this->legacyConfig['mysql_pass'],
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]
            );
            
            $this->log('Connected to legacy database');
            return $pdo;
            
        } catch (PDOException $e) {
            throw new Exception('Failed to connect to legacy database: ' . $e->getMessage());
        }
    }
    
    /**
     * Migrate nodes from legacy database
     * 
     * @param PDO $legacyDb Legacy database connection
     */
    private function migrateNodes($legacyDb)
    {
        $this->log('Migrating nodes...');
        
        try {
            // Check if nodes table exists
            $stmt = $legacyDb->query(\"SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '{$this->legacyConfig['mysql_db']}' AND table_name = 'nodes'\");
            if ($stmt->fetchColumn() == 0) {\n                $this->log('Legacy nodes table not found, skipping node migration');
                return;
            }
            
            // Get legacy nodes
            $stmt = $legacyDb->query('SELECT * FROM nodes ORDER BY id');
            $legacyNodes = $stmt->fetchAll();
            
            $migratedCount = 0;
            
            foreach ($legacyNodes as $node) {
                // Check if node already exists
                $existing = Capsule::table('mod_orrism_nodes')
                    ->where('address', $node['address'])
                    ->where('port', $node['port'])
                    ->first();
                
                if ($existing) {
                    $this->log("Node {$node['address']}:{$node['port']} already exists, skipping");
                    continue;
                }
                
                // Insert new node
                Capsule::table('mod_orrism_nodes')->insert([
                    'node_type' => $node['node_type'] ?: 'shadowsocks',
                    'group_id' => $node['group_id'] ?: 1,
                    'node_name' => $node['node_name'] ?: $node['address'],
                    'address' => $node['address'],
                    'port' => $node['port'],
                    'node_method' => $node['node_method'] ?: 'aes-256-gcm',
                    'rate' => $node['rate'] ?: 1.0,
                    'network_type' => $node['network_type'] ?: 'tcp',
                    'tag' => $node['tag'] ?: '{}',
                    'status' => 1, // Assume active
                    'sort_order' => $migratedCount,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
                
                $migratedCount++;
            }
            
            $this->log("Migrated {$migratedCount} nodes");
            
        } catch (Exception $e) {
            $this->log('Node migration failed: ' . $e->getMessage(), 'error');
            throw $e;
        }
    }
    
    /**
     * Migrate users from legacy database
     * 
     * @param PDO $legacyDb Legacy database connection
     */
    private function migrateUsers($legacyDb)
    {
        $this->log('Migrating users...');
        
        try {
            // Check if user table exists
            $stmt = $legacyDb->query(\"SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '{$this->legacyConfig['mysql_db']}' AND table_name = 'user'\");
            if ($stmt->fetchColumn() == 0) {
                $this->log('Legacy user table not found, skipping user migration');
                return;
            }
            
            // Get legacy users
            $stmt = $legacyDb->query('SELECT * FROM user ORDER BY id');
            $legacyUsers = $stmt->fetchAll();
            
            $migratedCount = 0;
            $skippedCount = 0;
            
            foreach ($legacyUsers as $user) {
                // Try to find corresponding WHMCS service
                $serviceId = $this->findWHMCSService($user);
                
                if (!$serviceId) {
                    $this->log("No WHMCS service found for user {$user['email']}, skipping");
                    $skippedCount++;
                    continue;
                }
                
                // Check if user already migrated
                $existing = Capsule::table('mod_orrism_users')
                    ->where('service_id', $serviceId)
                    ->first();
                
                if ($existing) {
                    $this->log("User for service {$serviceId} already exists, skipping");
                    $skippedCount++;
                    continue;
                }
                
                // Get client ID from WHMCS service
                $service = Capsule::table('tblhosting')
                    ->where('id', $serviceId)
                    ->first();
                
                if (!$service) {
                    $this->log("WHMCS service {$serviceId} not found, skipping");
                    $skippedCount++;
                    continue;
                }
                
                // Migrate user
                Capsule::table('mod_orrism_users')->insert([
                    'service_id' => $serviceId,
                    'client_id' => $service->userid,
                    'email' => $user['email'],
                    'uuid' => $user['uuid'],
                    'upload_bytes' => $user['u'] ?: 0,
                    'download_bytes' => $user['d'] ?: 0,
                    'bandwidth_limit' => $user['bandwidth'] ?: $user['transfer_enable'] ?: 0,
                    'node_group_id' => $user['node_group_id'] ?: $user['group_id'] ?: 1,
                    'status' => $user['enable'] ? 'active' : 'suspended',
                    'need_reset' => $user['need_reset'] ?: true,
                    'last_reset_at' => null,
                    'created_at' => isset($user['created_at']) ? date('Y-m-d H:i:s', $user['created_at']) : now(),
                    'updated_at' => isset($user['updated_at']) ? date('Y-m-d H:i:s', $user['updated_at']) : now()
                ]);
                
                $migratedCount++;
            }
            
            $this->log("Migrated {$migratedCount} users, skipped {$skippedCount} users");
            
        } catch (Exception $e) {
            $this->log('User migration failed: ' . $e->getMessage(), 'error');
            throw $e;
        }
    }
    
    /**
     * Migrate usage data from legacy database
     * 
     * @param PDO $legacyDb Legacy database connection
     */
    private function migrateUsageData($legacyDb)
    {
        $this->log('Migrating usage data...');
        
        try {
            // Check if user_usage table exists
            $stmt = $legacyDb->query(\"SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '{$this->legacyConfig['mysql_db']}' AND table_name = 'user_usage'\");
            if ($stmt->fetchColumn() == 0) {
                $this->log('Legacy user_usage table not found, skipping usage migration');
                return;
            }
            
            // Only migrate recent usage data (last 30 days) to avoid overwhelming the database
            $stmt = $legacyDb->query('SELECT * FROM user_usage WHERE log_at > UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 30 DAY)) ORDER BY log_at DESC LIMIT 10000');
            $legacyUsage = $stmt->fetchAll();
            
            $migratedCount = 0;
            
            foreach ($legacyUsage as $usage) {
                // Find corresponding user in new system
                $user = Capsule::table('mod_orrism_users')
                    ->where('service_id', $usage['sid'])
                    ->first();
                
                if (!$user) {
                    continue; // Skip if user not found
                }
                
                // Find corresponding node
                $node = Capsule::table('mod_orrism_nodes')
                    ->where('id', $usage['node_id'])
                    ->first();
                
                if (!$node) {
                    continue; // Skip if node not found
                }
                
                // Insert usage record
                Capsule::table('mod_orrism_user_usage')->insert([
                    'user_id' => $user->id,
                    'service_id' => $usage['sid'],
                    'node_id' => $usage['node_id'],
                    'upload_bytes' => $usage['upload'] ?: 0,
                    'download_bytes' => $usage['download'] ?: 0,
                    'session_start' => date('Y-m-d H:i:s', $usage['log_at']),
                    'recorded_at' => date('Y-m-d H:i:s', $usage['log_at'])
                ]);
                
                $migratedCount++;
            }
            
            $this->log("Migrated {$migratedCount} usage records (last 30 days only)");
            
        } catch (Exception $e) {
            $this->log('Usage data migration failed: ' . $e->getMessage(), 'error');
            throw $e;
        }
    }
    
    /**
     * Find WHMCS service for legacy user
     * 
     * @param array $user Legacy user data
     * @return int|null WHMCS service ID
     */
    private function findWHMCSService($user)
    {
        // Method 1: Try to find by service ID if available
        if (!empty($user['sid']) || !empty($user['package_id'])) {
            $serviceId = $user['sid'] ?: $user['package_id'];
            $service = Capsule::table('tblhosting')->where('id', $serviceId)->first();
            if ($service) {
                return $serviceId;
            }
        }
        
        // Method 2: Try to find by email
        if (!empty($user['email'])) {
            $client = Capsule::table('tblclients')->where('email', $user['email'])->first();
            if ($client) {
                // Find ORRISM service for this client
                $service = Capsule::table('tblhosting')
                    ->where('userid', $client->id)
                    ->where('producttype', 'server')
                    ->where('server', 'LIKE', '%orrism%')
                    ->first();
                
                if ($service) {
                    return $service->id;
                }
            }
        }
        
        return null;
    }
    
    /**
     * Verify migration results
     * 
     * @param PDO $legacyDb Legacy database connection
     */
    private function verifyMigration($legacyDb)
    {
        $this->log('Verifying migration...');
        
        // Count records in both databases
        $legacyNodeCount = 0;
        $legacyUserCount = 0;
        
        try {
            $stmt = $legacyDb->query('SELECT COUNT(*) FROM nodes');
            $legacyNodeCount = $stmt->fetchColumn();
        } catch (Exception $e) {
            // Table might not exist
        }
        
        try {
            $stmt = $legacyDb->query('SELECT COUNT(*) FROM user');
            $legacyUserCount = $stmt->fetchColumn();
        } catch (Exception $e) {
            // Table might not exist
        }
        
        $newNodeCount = Capsule::table('mod_orrism_nodes')->count();
        $newUserCount = Capsule::table('mod_orrism_users')->count();
        
        $this->log("Migration verification:");
        $this->log("- Legacy nodes: {$legacyNodeCount}, New nodes: {$newNodeCount}");
        $this->log("- Legacy users: {$legacyUserCount}, New users: {$newUserCount}");
        
        if ($newUserCount == 0) {
            $this->log('Warning: No users were migrated. Please check your data and configuration.', 'warning');
        }
    }
    
    /**
     * Log migration messages
     * 
     * @param string $message Log message
     * @param string $level Log level (info, warning, error)
     */
    private function log($message, $level = 'info')
    {
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[{$timestamp}] [{$level}] {$message}";
        
        $this->migrationLog[] = $logEntry;
        
        // Also log to WHMCS activity log for important messages
        if ($level === 'error') {
            logModuleCall('orrism_migration', 'error', [], $message);
        }
    }
    
    /**
     * Get migration log
     * 
     * @return array Migration log entries
     */
    public function getLog()
    {
        return $this->migrationLog;
    }
}

/**
 * Helper function to run migration
 * 
 * @param array $legacyConfig Legacy database configuration
 * @return array Migration result
 */
function orrism_run_legacy_migration($legacyConfig)
{
    $migration = new OrrisLegacyMigration($legacyConfig);
    return $migration->migrate();
}