<?php
/**
 * ORRISM Configuration Reader
 * Reads configuration from WHMCS addon module settings
 *
 * @package    WHMCS
 * @author     ORRISM Development Team
 * @copyright  Copyright (c) 2024
 * @version    2.0
 */

// Include WHMCS configuration if not already loaded
if (!defined('WHMCS')) {
    $whmcsPath = dirname(__DIR__, 4);
    if (file_exists($whmcsPath . '/init.php')) {
        require_once $whmcsPath . '/init.php';
    }
}

use WHMCS\Database\Capsule;
use WHMCS\Config\Setting;

/**
 * Get addon module configuration value
 * Standard WHMCS way to get addon module settings
 * 
 * @param string $module Module name
 * @param string $setting Setting name
 * @return string|null Setting value
 */
function getAddonModuleSetting($module, $setting) {
    static $cache = [];
    
    $cacheKey = $module . '_' . $setting;
    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }
    
    try {
        // Standard WHMCS way to get addon module configuration
        $value = Capsule::table('tbladdonmodules')
            ->where('module', $module)
            ->where('setting', $setting)
            ->value('value');
        
        $cache[$cacheKey] = $value;
        return $value;
        
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Get all addon module settings
 * 
 * @param string $module Module name
 * @return array Settings array
 */
function getAddonModuleSettings($module) {
    static $cache = [];
    
    if (isset($cache[$module])) {
        return $cache[$module];
    }
    
    try {
        $settings = [];
        $result = Capsule::table('tbladdonmodules')
            ->where('module', $module)
            ->get();
        
        foreach ($result as $row) {
            $settings[$row->setting] = $row->value;
        }
        
        $cache[$module] = $settings;
        return $settings;
        
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Get ORRISM configuration from addon settings
 * 
 * @return array Configuration array
 */
function orris_get_config() {
    static $config = null;
    
    if ($config !== null) {
        return $config;
    }
    
    // Get all settings for orrism_admin module
    $settings = getAddonModuleSettings('orrism_admin');
    
    // Map to expected config format for backward compatibility
    $config = [
        // MySQL settings
        'mysql_host' => $settings['database_host'] ?? 'localhost',
        'mysql_port' => $settings['database_port'] ?? '3306',
        'mysql_db' => $settings['database_name'] ?? 'orrism',
        'mysql_user' => $settings['database_user'] ?? 'root',
        'mysql_pass' => $settings['database_password'] ?? '',
        
        // Redis settings
        'redis_host' => $settings['redis_host'] ?? 'localhost',
        'redis_port' => $settings['redis_port'] ?? '6379',
        'redis_db' => $settings['redis_db'] ?? '0',
        'redis_user' => $settings['redis_username'] ?? '',
        'redis_pass' => $settings['redis_password'] ?? '',
        
        // Other settings
        'enable_traffic_log' => $settings['enable_traffic_log'] ?? '1',
        'traffic_reset_day' => $settings['traffic_reset_day'] ?? '1',
    ];
    
    return $config;
}

/**
 * Get specific ORRISM configuration value
 * 
 * @param string $key Configuration key
 * @param mixed $default Default value if not found
 * @return mixed Configuration value
 */
function orris_get_config_value($key, $default = null) {
    $config = orris_get_config();
    return $config[$key] ?? $default;
}

/**
 * Get database DSN for PDO connection
 * 
 * @return string DSN string
 */
function orris_get_dsn() {
    $config = orris_get_config();
    return "mysql:host={$config['mysql_host']};port={$config['mysql_port']};dbname={$config['mysql_db']};charset=utf8mb4";
}

/**
 * Get Redis connection parameters
 * 
 * @return array Redis connection parameters
 */
function orris_get_redis_config() {
    $config = orris_get_config();
    return [
        'host' => $config['redis_host'],
        'port' => (int)$config['redis_port'],
        'database' => (int)$config['redis_db'],
        'username' => $config['redis_user'],
        'password' => $config['redis_pass'],
    ];
}