<?php
/**
 * Database Manager for ORRISM Administration Module
 * Handles database operations including installation, connection testing, and management
 *
 * @package    WHMCS\Module\Addon\OrrisAdmin\Admin
 * @author     ORRISM Development Team
 * @copyright  Copyright (c) 2024
 * @version    2.0
 */

namespace WHMCS\Module\Addon\OrrisAdmin\Admin;

use WHMCS\Database\Capsule;
use Exception;
use PDO;
use PDOException;
use Redis;

/**
 * DatabaseManager Class
 * Provides database management functionality for ORRISM system
 */
class DatabaseManager
{
    /** @var \OrrisDatabaseManager */
    private $orrisDatabaseManager;
    
    /** @var array */
    private $settings = [];
    
    /** @var bool */
    private $debugMode = false;
    
    /**
     * Constructor
     * Initialize database manager with optional settings
     * 
     * @param array $settings Optional database settings
     */
    public function __construct(array $settings = [])
    {
        $this->settings = $settings;
        $this->debugMode = isset($_GET['debug']) || !empty($settings['debug']);
        
        // Try to load OrrisDatabaseManager if available
        $this->loadOrrisDatabaseManager();
    }
    
    /**
     * Load OrrisDatabaseManager from server module
     * 
     * @return bool Success status
     */
    private function loadOrrisDatabaseManager(): bool
    {
        try {
            $serverModulePath = dirname(__DIR__, 4) . '/servers/orrism';
            $databaseManagerPath = $serverModulePath . '/includes/database_manager.php';
            
            if (file_exists($databaseManagerPath)) {
                require_once $databaseManagerPath;
                
                if (class_exists('OrrisDatabaseManager')) {
                    $this->orrisDatabaseManager = new \OrrisDatabaseManager();
                    $this->log('OrrisDatabaseManager loaded successfully');
                    return true;
                }
            }
            
            $this->log('OrrisDatabaseManager not found or could not be loaded', 'warning');
            return false;
            
        } catch (Exception $e) {
            $this->log('Failed to load OrrisDatabaseManager: ' . $e->getMessage(), 'error');
            return false;
        }
    }
    
    /**
     * Install ORRISM database tables
     * Migrated from handleDatabaseInstall
     * 
     * @return array Installation result with success status and message
     */
    public function install(): array
    {
        try {
            // Check if OrrisDatabaseManager is available
            if ($this->orrisDatabaseManager) {
                // Check if already installed
                if ($this->orrisDatabaseManager->isInstalled()) {
                    return [
                        'success' => false,
                        'message' => 'Database tables already exist. If you need to reinstall, please uninstall first.',
                        'status' => 'already_installed'
                    ];
                }
                
                // Run installation using OrrisDatabaseManager
                $result = $this->orrisDatabaseManager->install();
                
                if ($result['success']) {
                    // Update addon settings to mark as installed
                    $this->updateInstallationStatus(true);
                    
                    $this->log('Database installed successfully using OrrisDatabaseManager');
                    
                    return [
                        'success' => true,
                        'message' => 'Database installed successfully! ' . ($result['message'] ?? ''),
                        'status' => 'installed'
                    ];
                } else {
                    return [
                        'success' => false,
                        'message' => 'Database installation failed: ' . ($result['message'] ?? 'Unknown error'),
                        'status' => 'installation_failed'
                    ];
                }
            }
            
            // Fallback to simple table creation if OrrisDatabaseManager is not available
            return $this->installSimpleTables();
            
        } catch (Exception $e) {
            $this->log('Database installation error: ' . $e->getMessage(), 'error');
            
            // Try fallback method
            return $this->installSimpleTables();
        }
    }
    
    /**
     * Install simple database tables without OrrisDatabaseManager
     * Migrated from handleSimpleTableCreation
     * 
     * @return array Installation result
     */
    public function installSimpleTables(): array
    {
        try {
            $pdo = Capsule::connection()->getPdo();
            
            // Define tables to create
            $tables = [
                'mod_orrism_node_groups' => "CREATE TABLE IF NOT EXISTS mod_orrism_node_groups (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(100) UNIQUE,
                    description TEXT,
                    sort_order INT DEFAULT 0,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                )",
                
                'mod_orrism_nodes' => "CREATE TABLE IF NOT EXISTS mod_orrism_nodes (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(100),
                    type ENUM('shadowsocks', 'v2ray', 'trojan') DEFAULT 'shadowsocks',
                    address VARCHAR(255),
                    port INT,
                    method VARCHAR(50),
                    group_id INT,
                    sort_order INT DEFAULT 0,
                    status ENUM('active', 'inactive') DEFAULT 'active',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                )",
                
                'mod_orrism_services' => "CREATE TABLE IF NOT EXISTS mod_orrism_services (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    service_id INT UNIQUE,
                    email VARCHAR(255),
                    uuid VARCHAR(36) UNIQUE,
                    password VARCHAR(255),
                    transfer_enable BIGINT DEFAULT 0,
                    upload BIGINT DEFAULT 0,
                    download BIGINT DEFAULT 0,
                    total BIGINT DEFAULT 0,
                    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
                    node_group_id INT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                )",
                
                'mod_orrism_config' => "CREATE TABLE IF NOT EXISTS mod_orrism_config (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    config_key VARCHAR(100) UNIQUE,
                    config_value TEXT,
                    config_type ENUM('string', 'boolean', 'json') DEFAULT 'string',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                )"
            ];
            
            $createdTables = [];
            $failedTables = [];
            
            foreach ($tables as $tableName => $sql) {
                try {
                    $pdo->exec($sql);
                    $createdTables[] = $tableName;
                    $this->log("Created table: $tableName");
                } catch (Exception $tableError) {
                    $failedTables[] = $tableName;
                    $this->log("Failed to create table $tableName: " . $tableError->getMessage(), 'error');
                }
            }
            
            // Insert default data if any tables were created
            if (count($createdTables) > 0) {
                try {
                    // Insert default node group
                    $pdo->exec("INSERT IGNORE INTO mod_orrism_node_groups (name, description) VALUES ('Default Group', 'Default node group')");
                    
                    // Update installation status
                    $this->updateInstallationStatus(true);
                    
                } catch (Exception $dataError) {
                    $this->log('Failed to insert default data: ' . $dataError->getMessage(), 'warning');
                }
                
                $message = 'Database tables created successfully! Created: ' . implode(', ', $createdTables);
                if (count($failedTables) > 0) {
                    $message .= ' Failed: ' . implode(', ', $failedTables);
                }
                
                return [
                    'success' => true,
                    'message' => $message,
                    'created_tables' => $createdTables,
                    'failed_tables' => $failedTables
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Failed to create any database tables. Please check error logs.',
                    'failed_tables' => $failedTables
                ];
            }
            
        } catch (Exception $e) {
            $this->log('Simple installation failed: ' . $e->getMessage(), 'error');
            
            return [
                'success' => false,
                'message' => 'Simple installation also failed: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Test database connection with custom parameters
     * Migrated from testDatabaseConnection
     * 
     * @param array|null $params Optional connection parameters (host, port, name, user, pass)
     * @return array Test result with success status and details
     */
    public function testConnection(array $params = null): array
    {
        $startTime = microtime(true);
        
        try {
            // Use provided parameters or get from settings/POST
            if ($params === null) {
                $params = [
                    'host' => $_POST['test_host'] ?? $this->settings['database_host'] ?? '',
                    'port' => $_POST['test_port'] ?? $this->settings['database_port'] ?? 3306,
                    'name' => $_POST['test_name'] ?? $this->settings['database_name'] ?? '',
                    'user' => $_POST['test_user'] ?? $this->settings['database_user'] ?? '',
                    'pass' => $_POST['test_pass'] ?? $this->settings['database_password'] ?? ''
                ];
            }
            
            // Validate parameters
            $missingParams = [];
            if (empty($params['host'])) $missingParams[] = 'host';
            if (empty($params['name'])) $missingParams[] = 'database name';
            if (empty($params['user'])) $missingParams[] = 'username';
            
            if (!empty($missingParams)) {
                return [
                    'success' => false,
                    'message' => 'Missing required parameters: ' . implode(', ', $missingParams),
                    'debug' => [
                        'missing_params' => $missingParams,
                        'received_params' => array_keys($params)
                    ]
                ];
            }
            
            // Ensure port is valid
            $port = is_numeric($params['port']) ? (int)$params['port'] : 3306;
            if ($port <= 0 || $port > 65535) {
                $port = 3306;
            }
            
            // Build DSN and attempt connection
            $dsn = "mysql:host={$params['host']};port={$port};dbname={$params['name']};charset=utf8";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_TIMEOUT => 10,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"
            ];
            
            $this->log("Attempting connection to: {$params['host']}:{$port}/{$params['name']}");
            
            $pdo = new PDO($dsn, $params['user'], $params['pass'], $options);
            
            // Test the connection
            $pdo->query('SELECT 1');
            
            // Get server information
            $serverVersion = $pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
            $serverInfo = $pdo->getAttribute(PDO::ATTR_SERVER_INFO);
            $connectionStatus = $pdo->getAttribute(PDO::ATTR_CONNECTION_STATUS);
            
            // Check for ORRISM tables (without prefix for external database)
            $coreTableNames = ['users', 'nodes', 'user_usage', 'node_groups', 'config'];
            $existingTables = [];
            $tablesExist = false;
            
            foreach ($coreTableNames as $tableName) {
                $stmt = $pdo->query("SHOW TABLES LIKE '$tableName'");
                if ($stmt->rowCount() > 0) {
                    $existingTables[] = $tableName;
                    $tablesExist = true;
                }
            }
            
            $executionTime = round((microtime(true) - $startTime) * 1000, 2);
            
            $this->log("Connection successful to {$params['host']}:{$port}");
            
            return [
                'success' => true,
                'database' => $params['name'],
                'server' => "MySQL $serverVersion",
                'server_info' => $serverInfo,
                'tables_exist' => $tablesExist,
                'existing_tables' => $existingTables,
                'execution_time_ms' => $executionTime,
                'connection_details' => [
                    'host' => $params['host'],
                    'port' => $port,
                    'charset' => 'utf8',
                    'connection_status' => $connectionStatus
                ]
            ];
            
        } catch (PDOException $e) {
            $executionTime = round((microtime(true) - $startTime) * 1000, 2);
            $rawMessage = $e->getMessage();
            $errorCode = $e->getCode();
            
            $this->log("Database connection failed: $rawMessage", 'error');
            
            // Parse error message for user-friendly display
            $userMessage = $this->parseConnectionError($rawMessage, $params);
            $errorCategory = $this->categorizeConnectionError($rawMessage);
            
            return [
                'success' => false,
                'message' => $userMessage,
                'error_category' => $errorCategory,
                'error_code' => $errorCode,
                'execution_time_ms' => $executionTime,
                'debug' => [
                    'raw_error' => substr($rawMessage, 0, 200),
                    'error_location' => $e->getFile() . ':' . $e->getLine(),
                    'connection_params' => [
                        'host' => $params['host'] ?? '',
                        'port' => $port ?? 3306,
                        'database' => $params['name'] ?? '',
                        'user' => $params['user'] ?? ''
                    ]
                ]
            ];
            
        } catch (Exception $e) {
            $executionTime = round((microtime(true) - $startTime) * 1000, 2);
            
            $this->log("Unexpected error during connection test: " . $e->getMessage(), 'error');
            
            return [
                'success' => false,
                'message' => 'Unexpected error occurred during connection test: ' . $e->getMessage(),
                'error_category' => 'unexpected',
                'execution_time_ms' => $executionTime,
                'debug' => [
                    'error_type' => get_class($e),
                    'error_location' => $e->getFile() . ':' . $e->getLine()
                ]
            ];
        }
    }
    
    /**
     * Test Redis connection with custom parameters
     * Migrated from testRedisConnectionHandler
     * 
     * @param array|null $params Optional Redis connection parameters
     * @return array Test result with success status and details
     */
    public function testRedis(array $params = null): array
    {
        try {
            // Use provided parameters or get from POST/settings
            if ($params === null) {
                $params = [
                    'host' => $_POST['redis_host'] ?? $this->settings['redis_host'] ?? 'localhost',
                    'port' => $_POST['redis_port'] ?? $this->settings['redis_port'] ?? 6379,
                    'username' => $_POST['redis_username'] ?? $this->settings['redis_username'] ?? '',
                    'password' => $_POST['redis_password'] ?? $this->settings['redis_password'] ?? '',
                    'database' => $_POST['redis_db'] ?? $this->settings['redis_db'] ?? 0
                ];
            }
            
            // Check if Redis extension is available
            if (!class_exists('Redis')) {
                return [
                    'success' => false,
                    'message' => 'Redis PHP extension is not installed'
                ];
            }
            
            $redis = new Redis();
            
            // Attempt connection
            $port = is_numeric($params['port']) ? (int)$params['port'] : 6379;
            $connected = @$redis->connect($params['host'], $port, 2); // 2 second timeout
            
            if (!$connected) {
                return [
                    'success' => false,
                    'message' => "Failed to connect to Redis server at {$params['host']}:{$port}"
                ];
            }
            
            // Handle authentication
            try {
                if (!empty($params['username']) && !empty($params['password'])) {
                    // Redis 6.0+ ACL with username and password
                    $authResult = $redis->auth([$params['username'], $params['password']]);
                    if (!$authResult) {
                        $redis->close();
                        return [
                            'success' => false,
                            'message' => 'Redis authentication failed with username and password'
                        ];
                    }
                } elseif (!empty($params['password'])) {
                    // Traditional Redis auth with password only
                    $authResult = $redis->auth($params['password']);
                    if (!$authResult) {
                        $redis->close();
                        return [
                            'success' => false,
                            'message' => 'Redis authentication failed with password'
                        ];
                    }
                }
                
                // Select database
                $dbIndex = isset($params['db']) ? max(0, (int)$params['db']) : 0;
                $selectResult = $redis->select($dbIndex);
                
                if (!$selectResult) {
                    $redis->close();
                    return [
                        'success' => false,
                        'message' => "Redis select database #{$dbIndex} failed"
                    ];
                }
                
                // Test connection with ping
                $pong = $redis->ping();
                
                // Get Redis server info
                $info = [];
                try {
                    $redisInfo = $redis->info();
                    if (isset($redisInfo['redis_version'])) {
                        $info['version'] = $redisInfo['redis_version'];
                    }
                    if (isset($redisInfo['connected_clients'])) {
                        $info['connected_clients'] = $redisInfo['connected_clients'];
                    }
                    if (isset($redisInfo['used_memory_human'])) {
                        $info['memory_usage'] = $redisInfo['used_memory_human'];
                    }
                } catch (Exception $infoException) {
                    $info['version'] = 'Unknown';
                }
                
                $redis->close();
                
                if ($pong) {
                    $this->log("Redis connection successful to {$params['host']}:{$port}");
                    
                    return [
                        'success' => true,
                        'message' => 'Redis connected successfully',
                        'info' => $info,
                        'database' => $dbIndex,
                        'auth_method' => !empty($params['username']) ? 
                            'ACL (username + password)' : 
                            (!empty($params['password']) ? 'Password only' : 'No authentication')
                    ];
                } else {
                    return [
                        'success' => false,
                        'message' => 'Connected to Redis but ping failed'
                    ];
                }
                
            } catch (Exception $authException) {
                $redis->close();
                return [
                    'success' => false,
                    'message' => 'Redis authentication error: ' . $authException->getMessage()
                ];
            }
            
        } catch (Exception $e) {
            $this->log('Redis connection error: ' . $e->getMessage(), 'error');
            
            return [
                'success' => false,
                'message' => 'Redis connection error: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Check if database is installed
     * 
     * @return bool Installation status
     */
    public function isInstalled(): bool
    {
        try {
            // First try with OrrisDatabaseManager
            if ($this->orrisDatabaseManager) {
                return $this->orrisDatabaseManager->isInstalled();
            }
            
            // Fallback to checking addon settings
            $pdo = Capsule::connection()->getPdo();
            $stmt = $pdo->prepare("SELECT setting_value FROM mod_orrism_admin_settings WHERE setting_key = 'db_initialized'");
            $stmt->execute();
            $result = $stmt->fetch();
            
            return $result && $result['setting_value'] === '1';
            
        } catch (Exception $e) {
            $this->log('Failed to check installation status: ' . $e->getMessage(), 'warning');
            return false;
        }
    }
    
    /**
     * Get user count from ORRISM database
     * 
     * @return int Number of users
     */
    public function getUserCount(): int
    {
        try {
            // Use OrrisDatabaseManager if available
            if ($this->orrisDatabaseManager) {
                return $this->orrisDatabaseManager->getUserCount();
            }
            
            // Fallback to direct query
            $pdo = Capsule::connection()->getPdo();
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM mod_orrism_users");
            $result = $stmt->fetch();
            
            return (int)($result['count'] ?? 0);
            
        } catch (Exception $e) {
            $this->log('Failed to get user count: ' . $e->getMessage(), 'warning');
            return 0;
        }
    }
    
    /**
     * Update installation status in addon settings
     * 
     * @param bool $installed Installation status
     * @return bool Success status
     */
    private function updateInstallationStatus(bool $installed): bool
    {
        try {
            $pdo = Capsule::connection()->getPdo();
            $stmt = $pdo->prepare("UPDATE mod_orrism_admin_settings SET setting_value = ? WHERE setting_key = 'db_initialized'");
            $stmt->execute([$installed ? '1' : '0']);
            
            return true;
            
        } catch (Exception $e) {
            $this->log('Failed to update installation status: ' . $e->getMessage(), 'warning');
            return false;
        }
    }
    
    /**
     * Parse connection error for user-friendly message
     * 
     * @param string $rawMessage Raw error message
     * @param array $params Connection parameters
     * @return string User-friendly error message
     */
    private function parseConnectionError(string $rawMessage, array $params): string
    {
        if (strpos($rawMessage, 'Access denied') !== false) {
            return 'Access denied. Please check username and password.';
        } elseif (strpos($rawMessage, 'Unknown database') !== false) {
            return "Database '{$params['name']}' does not exist. Please check the database name.";
        } elseif (strpos($rawMessage, 'Connection refused') !== false || strpos($rawMessage, 'No such host') !== false) {
            return "Cannot connect to server at '{$params['host']}:{$params['port']}'. Please check the hostname/port and ensure MySQL is running.";
        } elseif (strpos($rawMessage, 'timed out') !== false || strpos($rawMessage, 'timeout') !== false) {
            return "Connection timeout. The server may be overloaded or unreachable.";
        } elseif (strpos($rawMessage, 'No route to host') !== false) {
            return "Network error: Cannot reach host '{$params['host']}:{$params['port']}'.";
        } else {
            return 'Database connection failed: ' . substr($rawMessage, 0, 150) . (strlen($rawMessage) > 150 ? '...' : '');
        }
    }
    
    /**
     * Categorize connection error
     * 
     * @param string $rawMessage Raw error message
     * @return string Error category
     */
    private function categorizeConnectionError(string $rawMessage): string
    {
        if (strpos($rawMessage, 'Access denied') !== false) {
            return 'authentication';
        } elseif (strpos($rawMessage, 'Unknown database') !== false) {
            return 'database_not_found';
        } elseif (strpos($rawMessage, 'Connection refused') !== false || strpos($rawMessage, 'No such host') !== false) {
            return 'connection_refused';
        } elseif (strpos($rawMessage, 'timed out') !== false || strpos($rawMessage, 'timeout') !== false) {
            return 'timeout';
        } elseif (strpos($rawMessage, 'No route to host') !== false) {
            return 'network_error';
        } else {
            return 'generic_error';
        }
    }
    
    /**
     * Log message with optional level
     * 
     * @param string $message Log message
     * @param string $level Log level (info, warning, error)
     */
    private function log(string $message, string $level = 'info'): void
    {
        if ($this->debugMode || $level === 'error') {
            error_log("ORRISM DatabaseManager [$level]: $message");
        }
        
        // Also use WHMCS logging if available
        if (function_exists('logModuleCall')) {
            logModuleCall('orrism_admin', 'DatabaseManager', ['level' => $level], $message);
        }
    }
    
    /**
     * Get database configuration from settings
     * 
     * @return array Database configuration
     */
    public function getDatabaseConfig(): array
    {
        return [
            'host' => $this->settings['database_host'] ?? 'localhost',
            'port' => $this->settings['database_port'] ?? 3306,
            'name' => $this->settings['database_name'] ?? 'orrism',
            'user' => $this->settings['database_user'] ?? '',
            'password' => $this->settings['database_password'] ?? ''
        ];
    }
    
    /**
     * Get Redis configuration from settings
     * 
     * @return array Redis configuration
     */
    public function getRedisConfig(): array
    {
        return [
            'host' => $this->settings['redis_host'] ?? 'localhost',
            'port' => $this->settings['redis_port'] ?? 6379,
            'database' => $this->settings['redis_db'] ?? 0,
            'username' => $this->settings['redis_username'] ?? '',
            'password' => $this->settings['redis_password'] ?? ''
        ];
    }
}