<?php
/**
 * ORRISM Settings Manager
 * Handles all settings-related operations for the ORRISM admin module
 *
 * @package    WHMCS\Module\Addon\OrrisAdmin\Admin
 * @author     ORRISM Development Team
 * @copyright  Copyright (c) 2024
 * @version    1.0
 */

namespace WHMCS\Module\Addon\OrrisAdmin\Admin;

use WHMCS\Database\Capsule;
use PDO;
use PDOException;
use Exception;
use Redis;

class SettingsManager
{
    /**
     * Addon module name
     * @var string
     */
    private $moduleName = 'orrism_admin';
    
    /**
     * WHMCS addon settings table
     * @var string
     */
    private $tableName = 'tbladdonmodules';
    
    /**
     * Valid setting types
     * @var array
     */
    private $validSettingTypes = ['database', 'redis', 'general'];
    
    /**
     * Database connection settings keys
     * @var array
     */
    private $databaseSettingKeys = [
        'database_host',
        'database_port', 
        'database_name',
        'database_user',
        'database_password'
    ];
    
    /**
     * Redis connection settings keys
     * @var array
     */
    private $redisSettingKeys = [
        'redis_host',
        'redis_port',
        'redis_db',
        'redis_username',
        'redis_password'
    ];
    
    /**
     * General settings keys
     * @var array
     */
    private $generalSettingKeys = [
        'enable_traffic_log',
        'enable_online_status',
        'enable_audit_log',
        'auto_sync',
        'auto_reset_traffic',
        'reset_day'
    ];
    
    /**
     * Get all settings from database
     * Uses standard WHMCS method to retrieve addon module configuration
     *
     * @return array Settings array
     */
    public function getSettings()
    {
        try {
            $settings = [];
            
            // Standard WHMCS way: Get settings from addon modules table
            $result = Capsule::table($this->tableName)
                ->where('module', $this->moduleName)
                ->pluck('value', 'setting');
            
            // Convert to array
            $settings = $result->toArray();
            
            
            // Ensure default values for important settings
            $this->ensureDefaultSettings($settings);
            
            return $settings;
        } catch (Exception $e) {
            $this->logError('getSettings', $e->getMessage());
            return $this->getDefaultSettings();
        }
    }
    
    /**
     * Save settings to database
     *
     * @param array $settings Settings to save
     * @return bool Success status
     */
    public function saveSettings($settings)
    {
        try {
            foreach ($settings as $key => $value) {
                // Skip invalid settings keys
                if (!$this->isValidSettingKey($key)) {
                    continue;
                }
                
                // Check if this is a core addon setting or custom setting
                if ($this->isCoreAddonSetting($key)) {
                    // Save to WHMCS addon modules table
                    Capsule::table($this->tableName)
                        ->updateOrInsert(
                            ['module' => $this->moduleName, 'setting' => $key],
                            ['value' => $value]
                        );
                } else {
                    // Non-core settings are not saved (they should be added to core settings if needed)
                    continue;
                }
            }
            
            // Clear cached configuration if OrrisDB exists
            if (class_exists('OrrisDB')) {
                \OrrisDB::reset();
            }
            
            $this->logActivity('saveSettings', 'Settings updated successfully', $settings);
            return true;
        } catch (Exception $e) {
            $this->logError('saveSettings', 'Failed to save settings: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Validate and save settings by type
     *
     * @param string $settingsType Type of settings (database, redis, general)
     * @param array $postData POST data containing settings
     * @return array Result array with success status and message
     */
    public function validateAndSave($settingsType, $postData)
    {
        try {
            // Validate settings type
            if (!in_array($settingsType, $this->validSettingTypes)) {
                return [
                    'success' => false,
                    'message' => 'Invalid settings type: ' . $settingsType
                ];
            }
            
            $settings = [];
            
            switch ($settingsType) {
                case 'database':
                    $settings = $this->processDatabaseSettings($postData);
                    break;
                    
                case 'redis':
                    $settings = $this->processRedisSettings($postData);
                    break;
                    
                case 'general':
                    $settings = $this->processGeneralSettings($postData);
                    break;
            }
            
            // Validate processed settings
            $validation = $this->validateSettings($settingsType, $settings);
            if (!$validation['valid']) {
                return [
                    'success' => false,
                    'message' => 'Validation failed: ' . $validation['message']
                ];
            }
            
            // Save settings
            if ($this->saveSettings($settings)) {
                return [
                    'success' => true,
                    'message' => ucfirst($settingsType) . ' settings saved successfully!'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Failed to save ' . $settingsType . ' settings'
                ];
            }
            
        } catch (Exception $e) {
            $this->logError('validateAndSave', 'Error processing settings: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Process database settings from POST data
     *
     * @param array $postData POST data
     * @return array Processed settings
     */
    private function processDatabaseSettings($postData)
    {
        $settings = [];
        
        $settings['database_host'] = $postData['database_host'] ?? 'localhost';
        
        // Validate and process port
        $dbPort = $postData['database_port'] ?? '3306';
        $settings['database_port'] = is_numeric($dbPort) ? 
            (string) max(1, min(65535, (int) $dbPort)) : '3306';
        
        $settings['database_name'] = $postData['database_name'] ?? 'orrism';
        $settings['database_user'] = $postData['database_user'] ?? '';
        
        // Only update password if provided
        if (!empty($postData['database_password'])) {
            $settings['database_password'] = $postData['database_password'];
        } else {
            // Keep existing password if not provided
            $currentSettings = $this->getSettings();
            if (!empty($currentSettings['database_password'])) {
                $settings['database_password'] = $currentSettings['database_password'];
            }
        }
        
        return $settings;
    }
    
    /**
     * Process Redis settings from POST data
     *
     * @param array $postData POST data
     * @return array Processed settings
     */
    private function processRedisSettings($postData)
    {
        $settings = [];
        
        $settings['redis_host'] = $postData['redis_host'] ?? 'localhost';
        
        // Validate and process port
        $redisPort = $postData['redis_port'] ?? '6379';
        $settings['redis_port'] = is_numeric($redisPort) ? 
            (string) max(1, min(65535, (int) $redisPort)) : '6379';
        
        // Validate and process database index
        $redisDb = $postData['redis_db'] ?? '0';
        $settings['redis_db'] = is_numeric($redisDb) ? 
            (string) max(0, (int) $redisDb) : '0';
        
        $settings['redis_username'] = $postData['redis_username'] ?? '';
        
        // Only update password if provided
        if (!empty($postData['redis_password'])) {
            $settings['redis_password'] = $postData['redis_password'];
        } else {
            // Keep existing password if not provided
            $currentSettings = $this->getSettings();
            if (!empty($currentSettings['redis_password'])) {
                $settings['redis_password'] = $currentSettings['redis_password'];
            }
        }
        
        return $settings;
    }
    
    /**
     * Process general settings from POST data
     *
     * @param array $postData POST data
     * @return array Processed settings
     */
    private function processGeneralSettings($postData)
    {
        $settings = [];
        
        // Boolean settings
        $settings['enable_traffic_log'] = isset($postData['enable_traffic_log']) ? '1' : '0';
        $settings['enable_online_status'] = isset($postData['enable_online_status']) ? '1' : '0';
        $settings['enable_audit_log'] = isset($postData['enable_audit_log']) ? '1' : '0';
        $settings['auto_sync'] = isset($postData['auto_sync']) ? '1' : '0';
        $settings['auto_reset_traffic'] = isset($postData['auto_reset_traffic']) ? '1' : '0';
        
        // Validate reset day (1-28)
        $resetDay = $postData['reset_day'] ?? '1';
        $settings['reset_day'] = is_numeric($resetDay) ? 
            (string) max(1, min(28, (int) $resetDay)) : '1';
        
        return $settings;
    }
    
    /**
     * Validate settings based on type
     *
     * @param string $type Settings type
     * @param array $settings Settings to validate
     * @return array Validation result
     */
    private function validateSettings($type, $settings)
    {
        switch ($type) {
            case 'database':
                return $this->validateDatabaseSettings($settings);
                
            case 'redis':
                return $this->validateRedisSettings($settings);
                
            case 'general':
                return $this->validateGeneralSettings($settings);
                
            default:
                return ['valid' => false, 'message' => 'Unknown settings type'];
        }
    }
    
    /**
     * Validate database settings
     *
     * @param array $settings Database settings
     * @return array Validation result
     */
    private function validateDatabaseSettings($settings)
    {
        // Required fields
        $requiredFields = ['database_host', 'database_name', 'database_user'];
        foreach ($requiredFields as $field) {
            if (empty($settings[$field])) {
                return [
                    'valid' => false,
                    'message' => 'Database ' . str_replace('database_', '', $field) . ' is required'
                ];
            }
        }
        
        // Validate port range
        $port = (int) ($settings['database_port'] ?? 3306);
        if ($port < 1 || $port > 65535) {
            return [
                'valid' => false,
                'message' => 'Database port must be between 1 and 65535'
            ];
        }
        
        return ['valid' => true, 'message' => 'Database settings are valid'];
    }
    
    /**
     * Validate Redis settings
     *
     * @param array $settings Redis settings
     * @return array Validation result
     */
    private function validateRedisSettings($settings)
    {
        // Redis settings are optional, so we only validate if provided
        if (!empty($settings['redis_host'])) {
            // Validate port range
            $port = (int) ($settings['redis_port'] ?? 6379);
            if ($port < 1 || $port > 65535) {
                return [
                    'valid' => false,
                    'message' => 'Redis port must be between 1 and 65535'
                ];
            }
            
            // Validate database index
            $db = (int) ($settings['redis_db'] ?? 0);
            if ($db < 0) {
                return [
                    'valid' => false,
                    'message' => 'Redis database index must be 0 or greater'
                ];
            }
        }
        
        return ['valid' => true, 'message' => 'Redis settings are valid'];
    }
    
    /**
     * Validate general settings
     *
     * @param array $settings General settings
     * @return array Validation result
     */
    private function validateGeneralSettings($settings)
    {
        // Validate reset day
        if (isset($settings['reset_day'])) {
            $resetDay = (int) $settings['reset_day'];
            if ($resetDay < 1 || $resetDay > 28) {
                return [
                    'valid' => false,
                    'message' => 'Traffic reset day must be between 1 and 28'
                ];
            }
        }
        
        return ['valid' => true, 'message' => 'General settings are valid'];
    }
    
    /**
     * Test database connection with provided settings
     *
     * @param array $settings Database settings to test
     * @return array Test result
     */
    public function testDatabaseConnection($settings)
    {
        $startTime = microtime(true);
        
        try {
            $host = $settings['host'] ?? '';
            $port = isset($settings['port']) && is_numeric($settings['port']) ? 
                (int) $settings['port'] : 3306;
            $name = $settings['name'] ?? '';
            $user = $settings['user'] ?? '';
            $pass = $settings['password'] ?? '';
            
            // Validate required parameters
            $missingParams = [];
            if (empty($host)) $missingParams[] = 'host';
            if (empty($name)) $missingParams[] = 'database name';
            if (empty($user)) $missingParams[] = 'username';
            
            if (!empty($missingParams)) {
                return [
                    'success' => false,
                    'message' => 'Missing required parameters: ' . implode(', ', $missingParams)
                ];
            }
            
            // Attempt connection
            $dsn = "mysql:host=$host;port=$port;dbname=$name;charset=utf8";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_TIMEOUT => 10,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"
            ];
            
            $pdo = new PDO($dsn, $user, $pass, $options);
            
            // Test the connection
            $pdo->query('SELECT 1');
            
            // Get server information
            $serverVersion = $pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
            $serverInfo = $pdo->getAttribute(PDO::ATTR_SERVER_INFO);
            $connectionStatus = $pdo->getAttribute(PDO::ATTR_CONNECTION_STATUS);
            
            // Check for ORRISM tables
            $coreTableNames = ['users', 'nodes', 'user_usage', 'node_groups', 'config'];
            $existingTables = [];
            
            foreach ($coreTableNames as $tableName) {
                $stmt = $pdo->query("SHOW TABLES LIKE '$tableName'");
                if ($stmt->rowCount() > 0) {
                    $existingTables[] = $tableName;
                }
            }
            
            $executionTime = round((microtime(true) - $startTime) * 1000, 2);
            
            return [
                'success' => true,
                'database' => $name,
                'server' => "MySQL $serverVersion",
                'server_info' => $serverInfo,
                'tables_exist' => !empty($existingTables),
                'existing_tables' => $existingTables,
                'execution_time_ms' => $executionTime,
                'connection_details' => [
                    'host' => $host,
                    'port' => $port,
                    'charset' => 'utf8',
                    'connection_status' => $connectionStatus
                ]
            ];
            
        } catch (PDOException $e) {
            $executionTime = round((microtime(true) - $startTime) * 1000, 2);
            return $this->processDatabaseError($e, $executionTime, $settings);
        } catch (Exception $e) {
            $executionTime = round((microtime(true) - $startTime) * 1000, 2);
            return [
                'success' => false,
                'message' => 'Unexpected error: ' . $e->getMessage(),
                'error_category' => 'unexpected',
                'execution_time_ms' => $executionTime
            ];
        }
    }
    
    /**
     * Test Redis connection with provided settings
     *
     * @param array $settings Redis settings to test
     * @return array Test result
     */
    public function testRedisConnection($settings)
    {
        try {
            $host = $settings['host'] ?? 'localhost';
            $port = $settings['port'] ?? 6379;
            $username = $settings['username'] ?? '';
            $password = $settings['password'] ?? '';
            $dbIndex = isset($settings['db']) ? max(0, (int) $settings['db']) : 0;
            
            if (!class_exists('Redis')) {
                return [
                    'success' => false,
                    'message' => 'Redis PHP extension is not installed'
                ];
            }
            
            $redis = new Redis();
            $connected = @$redis->connect($host, (int)$port, 2);
            
            if (!$connected) {
                return [
                    'success' => false,
                    'message' => "Failed to connect to Redis server at {$host}:{$port}"
                ];
            }
            
            // Handle authentication
            if (!empty($username) && !empty($password)) {
                // Redis 6.0+ ACL
                $authResult = $redis->auth([$username, $password]);
                if (!$authResult) {
                    $redis->close();
                    return [
                        'success' => false,
                        'message' => 'Redis authentication failed with username and password'
                    ];
                }
            } elseif (!empty($password)) {
                // Traditional Redis auth
                $authResult = $redis->auth($password);
                if (!$authResult) {
                    $redis->close();
                    return [
                        'success' => false,
                        'message' => 'Redis authentication failed with password'
                    ];
                }
            }
            
            // Select database
            if (!$redis->select($dbIndex)) {
                $redis->close();
                return [
                    'success' => false,
                    'message' => 'Failed to select Redis database #' . $dbIndex
                ];
            }
            
            // Test connection with ping
            $pong = $redis->ping();
            
            // Get Redis info
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
            } catch (Exception $e) {
                $info['version'] = 'Unknown';
            }
            
            $redis->close();
            
            if ($pong) {
                return [
                    'success' => true,
                    'message' => 'Redis connected successfully',
                    'info' => $info,
                    'database' => $dbIndex,
                    'auth_method' => !empty($username) ? 
                        'ACL (username + password)' : 
                        (!empty($password) ? 'Password only' : 'No authentication')
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Connected to Redis but ping failed'
                ];
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Redis connection error: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Process database connection error
     *
     * @param PDOException $e The exception
     * @param float $executionTime Execution time in milliseconds
     * @param array $settings Connection settings
     * @return array Error response
     */
    private function processDatabaseError($e, $executionTime, $settings)
    {
        $rawMessage = $e->getMessage();
        $errorCode = $e->getCode();
        
        // Determine user-friendly message and error category
        $userMessage = '';
        $errorCategory = 'unknown';
        
        if (strpos($rawMessage, 'Access denied') !== false) {
            $userMessage = 'Access denied. Please check username and password.';
            $errorCategory = 'authentication';
        } elseif (strpos($rawMessage, 'Unknown database') !== false) {
            $userMessage = "Database '{$settings['name']}' does not exist.";
            $errorCategory = 'database_not_found';
        } elseif (strpos($rawMessage, 'Connection refused') !== false) {
            $userMessage = "Cannot connect to server at '{$settings['host']}:{$settings['port']}'.";
            $errorCategory = 'connection_refused';
        } elseif (strpos($rawMessage, 'timed out') !== false) {
            $userMessage = "Connection timeout. The server may be unreachable.";
            $errorCategory = 'timeout';
        } else {
            $userMessage = 'Database connection failed: ' . substr($rawMessage, 0, 150);
            $errorCategory = 'generic_error';
        }
        
        return [
            'success' => false,
            'message' => $userMessage,
            'error_category' => $errorCategory,
            'error_code' => $errorCode,
            'execution_time_ms' => $executionTime
        ];
    }
    
    /**
     * Check if a setting key is valid
     *
     * @param string $key Setting key to check
     * @return bool
     */
    private function isValidSettingKey($key)
    {
        $allValidKeys = array_merge(
            $this->databaseSettingKeys,
            $this->redisSettingKeys,
            $this->generalSettingKeys,
            ['db_initialized', 'last_sync', 'sync_enabled'] // Additional system keys
        );
        
        return in_array($key, $allValidKeys);
    }
    
    /**
     * Ensure default settings are present
     *
     * @param array &$settings Settings array to update
     */
    private function ensureDefaultSettings(&$settings)
    {
        $defaults = $this->getDefaultSettings();
        
        foreach ($defaults as $key => $value) {
            if (!isset($settings[$key])) {
                $settings[$key] = $value;
            }
        }
    }
    
    /**
     * Get default settings
     *
     * @return array Default settings
     */
    private function getDefaultSettings()
    {
        return [
            // Database defaults
            'database_host' => 'localhost',
            'database_port' => '3306',
            'database_name' => 'orrism',
            'database_user' => '',
            'database_password' => '',
            
            // Redis defaults
            'redis_host' => 'localhost',
            'redis_port' => '6379',
            'redis_db' => '0',
            'redis_username' => '',
            'redis_password' => '',
            
            // General defaults
            'enable_traffic_log' => '1',
            'enable_online_status' => '1',
            'enable_audit_log' => '0',
            'auto_sync' => '1',
            'auto_reset_traffic' => '0',
            'reset_day' => '1',
            
            // System defaults
            'db_initialized' => '0',
            'last_sync' => '',
            'sync_enabled' => '1'
        ];
    }
    
    /**
     * Log activity
     *
     * @param string $action Action performed
     * @param string $message Message to log
     * @param array $data Additional data
     */
    private function logActivity($action, $message, $data = [])
    {
        if (function_exists('logModuleCall')) {
            logModuleCall(
                'orrism_admin',
                $action,
                $data,
                $message,
                json_encode($data),
                ['password', 'database_password', 'redis_password', 'apikey', 'token']
            );
        } else {
            error_log("ORRISM Admin [$action]: $message");
        }
    }

    /**
     * Log error
     *
     * @param string $action Action that failed
     * @param string $error Error message
     */
    private function logError($action, $error)
    {
        if (function_exists('logModuleCall')) {
            logModuleCall(
                'orrism_admin',
                $action . '_error',
                [],
                $error,
                '',
                ['password', 'database_password', 'redis_password', 'apikey', 'token']
            );
        } else {
            error_log("ORRISM Admin Error [$action]: $error");
        }
    }
    
    /**
     * Check if a setting is a core addon setting
     *
     * @param string $key Setting key
     * @return bool
     */
    private function isCoreAddonSetting($key)
    {
        $coreSettings = array_merge(
            $this->databaseSettingKeys,
            $this->redisSettingKeys,
            ['enable_traffic_log', 'traffic_reset_day']
        );
        
        return in_array($key, $coreSettings);
    }
}