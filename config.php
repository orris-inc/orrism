<?php
/**
 * ORRISM Configuration for WHMCS Module
 *
 * @package    WHMCS
 * @author     ORRISM Development Team
 * @copyright  Copyright (c) 2024
 * @version    2.0
 * @link       https://github.com/your-org/orrism-whmcs-module
 */

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

// Module configuration constants
define('ORRISM_MODULE_VERSION', '2.0');
define('ORRISM_API_VERSION', '1.1');
define('ORRISM_MIN_PHP_VERSION', '7.4');

/**
 * Get module configuration
 * 
 * @param string|null $key Optional specific configuration key to retrieve
 * @return array|mixed Configuration array or specific value
 */
function orrism_get_config($key = null)
{
    static $config = null;
    
    // Initialize configuration once
    if ($config === null) {
        $config = orrism_load_configuration();
    }
    
    // Return specific key if requested
    if ($key !== null) {
        return $config[$key] ?? null;
    }
    
    return $config;
}

/**
 * Load and merge configuration from multiple sources
 * 
 * @return array Complete configuration array
 */
function orrism_load_configuration()
{
    // Default configuration
    $defaultConfig = [
        // API Settings
        'api_key' => getenv('ORRISM_API_KEY') ?: '',
        'api_timeout' => 30,
        'api_retries' => 3,
        
        // Database Settings (fallback for legacy systems)
        'mysql_host' => getenv('ORRISM_MYSQL_HOST') ?: 'localhost',
        'mysql_db' => getenv('ORRISM_MYSQL_DB') ?: 'shadowsocks',        
        'mysql_user' => getenv('ORRISM_MYSQL_USER') ?: '',
        'mysql_pass' => getenv('ORRISM_MYSQL_PASS') ?: '',
        'mysql_port' => getenv('ORRISM_MYSQL_PORT') ?: '3306',
        'mysql_charset' => 'utf8mb4',
        
        // Redis Settings (optional cache layer)
        'redis_enabled' => false,
        'redis_host' => getenv('ORRISM_REDIS_HOST') ?: '127.0.0.1',
        'redis_port' => getenv('ORRISM_REDIS_PORT') ?: '6379',
        'redis_pass' => getenv('ORRISM_REDIS_PASS') ?: '',
        'redis_database' => 0,
        'redis_prefix' => 'orrism:',
        
        // Module Settings
        'debug_mode' => getenv('ORRISM_DEBUG') === 'true',
        'log_level' => getenv('ORRISM_LOG_LEVEL') ?: 'info',
        'cache_ttl' => 300, // 5 minutes
        
        // Security Settings
        'encrypt_passwords' => true,
        'token_lifetime' => 300, // 5 minutes for SSO tokens
        'max_login_attempts' => 5,
        
        // Subscription Settings
        'subscription_base_url' => '',
        'subscription_token_key' => '',
        'subscription_encryption' => true,
        
        // Traffic Management
        'auto_reset_traffic' => false,
        'reset_day' => 1, // First day of month
        'traffic_multiplier' => 1.0,
        
        // Node Management
        'auto_sync_nodes' => true,
        'node_check_interval' => 300, // 5 minutes
        'node_timeout' => 10,
        
        // Feature Flags
        'enable_usage_tracking' => true,
        'enable_sso' => true,
        'enable_client_reset' => true,
        'enable_notifications' => false,
    ];
    
    // Load local configuration if exists
    $localConfig = orrism_load_local_config();
    
    // Merge configurations (local overrides default)
    $config = array_merge($defaultConfig, $localConfig);
    
    // Post-process configuration
    $config = orrism_process_configuration($config);
    
    return $config;
}

/**
 * Load local configuration file
 * 
 * @return array Local configuration array
 */
function orrism_load_local_config()
{
    $configFile = __DIR__ . '/config.local.php';
    
    if (!file_exists($configFile)) {
        return [];
    }
    
    try {
        $localConfig = include $configFile;
        return is_array($localConfig) ? $localConfig : [];
    } catch (Exception $e) {
        error_log("ORRISM: Failed to load local config - " . $e->getMessage());
        return [];
    }
}

/**
 * Process and validate configuration
 * 
 * @param array $config Raw configuration
 * @return array Processed configuration
 */
function orrism_process_configuration(array $config)
{
    // Validate PHP version
    if (version_compare(PHP_VERSION, ORRISM_MIN_PHP_VERSION, '<')) {
        error_log("ORRISM: PHP version " . ORRISM_MIN_PHP_VERSION . " or higher required");
    }
    
    // Process subscription URL
    if (empty($config['subscription_base_url'])) {
        global $_SERVER;
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $config['subscription_base_url'] = "{$protocol}://{$host}/modules/servers/orrism/api/subscribe";
    }
    
    // Ensure required directories exist
    orrism_ensure_directories();
    
    return $config;
}

/**
 * Ensure required directories exist
 */
function orrism_ensure_directories()
{
    $directories = [
        __DIR__ . '/logs',
        __DIR__ . '/cache',
        __DIR__ . '/tmp'
    ];
    
    foreach ($directories as $dir) {
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
    }
}

/**
 * Set runtime configuration value
 * 
 * @param string $key Configuration key
 * @param mixed $value Configuration value
 * @return bool Success status
 */
function orrism_set_config($key, $value)
{
    static $runtimeConfig = [];
    
    if (!is_string($key) || empty($key)) {
        return false;
    }
    
    $runtimeConfig[$key] = $value;
    
    // Log configuration changes in debug mode
    if (orrism_get_config('debug_mode')) {
        error_log("ORRISM: Runtime config set - {$key}");
    }
    
    return true;
}

/**
 * Get runtime configuration value
 * 
 * @param string $key Configuration key
 * @param mixed $default Default value if key not found
 * @return mixed Configuration value
 */
function orrism_get_runtime_config($key, $default = null)
{
    static $runtimeConfig = [];
    return $runtimeConfig[$key] ?? $default;
}

/**
 * Check if module is properly configured
 * 
 * @return array Configuration status
 */
function orrism_check_configuration()
{
    $config = orrism_get_config();
    $issues = [];
    
    // Check required settings
    if (empty($config['mysql_host'])) {
        $issues[] = 'MySQL host not configured';
    }
    
    if (empty($config['mysql_user'])) {
        $issues[] = 'MySQL user not configured';
    }
    
    if (empty($config['api_key'])) {
        $issues[] = 'API key not configured';
    }
    
    // Check optional but recommended settings
    $warnings = [];
    
    if (!$config['encrypt_passwords']) {
        $warnings[] = 'Password encryption is disabled';
    }
    
    if ($config['debug_mode']) {
        $warnings[] = 'Debug mode is enabled in production';
    }
    
    return [
        'status' => empty($issues) ? 'ok' : 'error',
        'issues' => $issues,
        'warnings' => $warnings
    ];
}

/**
 * Get database connection parameters from WHMCS or module config
 * 
 * @param array $params WHMCS module parameters
 * @return array Database connection parameters
 */
function orrism_get_database_config(array $params = [])
{
    // Try to get from WHMCS parameters first
    if (!empty($params['serverhostname']) || !empty($params['serverip'])) {
        return [
            'host' => $params['serverhostname'] ?: $params['serverip'],
            'username' => $params['serverusername'] ?? '',
            'password' => $params['serverpassword'] ?? '',
            'database' => $params['configoption1'] ?: orrism_get_config('mysql_db'),
            'port' => orrism_get_config('mysql_port'),
            'charset' => orrism_get_config('mysql_charset')
        ];
    }
    
    // Fallback to module configuration
    $config = orrism_get_config();
    return [
        'host' => $config['mysql_host'],
        'username' => $config['mysql_user'],
        'password' => $config['mysql_pass'],
        'database' => $config['mysql_db'],
        'port' => $config['mysql_port'],
        'charset' => $config['mysql_charset']
    ];
}
