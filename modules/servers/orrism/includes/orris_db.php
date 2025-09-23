<?php
/**
 * ORRISM Database Connection Manager
 * Manages separate database connection for ORRISM data
 *
 * @package    WHMCS
 * @author     ORRISM Development Team
 * @copyright  Copyright (c) 2024
 * @version    2.0
 */

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

use Illuminate\Database\Capsule\Manager as Capsule;
use WHMCS\Database\Capsule as WhmcsCapsule;

/**
 * ORRISM Database Connection Class
 */
class OrrisDB
{
    private static $instance = null;
    private static $capsule = null;
    private static $config = null;
    
    /**
     * Get database configuration from addon module settings
     * 
     * @return array|false
     */
    public static function getConfig()
    {
        if (self::$config !== null) {
            return self::$config;
        }
        
        try {
            // First check if the settings table exists
            if (!WhmcsCapsule::schema()->hasTable('mod_orrism_admin_settings')) {
                logModuleCall('orrism', __METHOD__, [], 'ORRISM settings table not found');
                return false;
            }
            
            // Get configuration from mod_orrism_admin_settings table
            $settings = WhmcsCapsule::table('mod_orrism_admin_settings')
                ->whereIn('setting_key', ['database_host', 'database_name', 'database_user', 'database_password'])
                ->pluck('setting_value', 'setting_key')
                ->toArray();
            
            if (empty($settings['database_host']) || empty($settings['database_name'])) {
                logModuleCall('orrism', __METHOD__, [], 'ORRISM database not configured in settings');
                return false;
            }
            
            self::$config = [
                'driver' => 'mysql',
                'host' => $settings['database_host'] ?? 'localhost',
                'database' => $settings['database_name'] ?? 'orrism',
                'username' => $settings['database_user'] ?? 'root',
                'password' => $settings['database_password'] ?? '',
                'charset' => 'utf8',
                'collation' => 'utf8_unicode_ci',
                'prefix' => '',
                'strict' => false,
                'engine' => 'InnoDB',
            ];
            
            return self::$config;
            
        } catch (Exception $e) {
            logModuleCall('orrism', __METHOD__, [], 'Failed to get database config: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get or create ORRISM database connection
     * 
     * @return Capsule|false
     */
    public static function getCapsule()
    {
        if (self::$capsule !== null) {
            return self::$capsule;
        }
        
        $config = self::getConfig();
        if (!$config) {
            return false;
        }
        
        try {
            // Create a separate Capsule instance for ORRISM
            // This ensures complete isolation from WHMCS database operations
            self::$capsule = new Capsule();
            
            // Add only the ORRISM database connection
            self::$capsule->addConnection($config, 'orrism');
            
            // Do NOT set as global or boot Eloquent
            // This prevents interfering with WHMCS's database operations
            
            logModuleCall('orrism', __METHOD__, ['host' => $config['host'], 'database' => $config['database']], 'ORRISM database connected');
            
            return self::$capsule;
            
        } catch (Exception $e) {
            logModuleCall('orrism', __METHOD__, [], 'Failed to connect to ORRISM database: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get database connection
     * 
     * @return \Illuminate\Database\Connection|false
     */
    public static function connection()
    {
        $capsule = self::getCapsule();
        if (!$capsule) {
            return false;
        }
        
        try {
            // Get the ORRISM connection from our Capsule instance
            return $capsule->getConnection('orrism');
        } catch (Exception $e) {
            logModuleCall('orrism', __METHOD__, [], 'Failed to get connection: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get schema builder for ORRISM database
     * 
     * @return \Illuminate\Database\Schema\Builder|false
     */
    public static function schema()
    {
        $connection = self::connection();
        if (!$connection) {
            return false;
        }
        
        try {
            return $connection->getSchemaBuilder();
        } catch (Exception $e) {
            logModuleCall('orrism', __METHOD__, [], 'Failed to get schema: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get table query builder
     * 
     * @param string $table
     * @return \Illuminate\Database\Query\Builder|false
     */
    public static function table($table)
    {
        $connection = self::connection();
        if (!$connection) {
            return false;
        }
        
        try {
            return $connection->table($table);
        } catch (Exception $e) {
            logModuleCall('orrism', __METHOD__, ['table' => $table], 'Failed to get table: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Test database connection
     * 
     * @return bool
     */
    public static function testConnection()
    {
        try {
            $connection = self::connection();
            if (!$connection) {
                return false;
            }
            
            $connection->getPdo();
            return true;
            
        } catch (Exception $e) {
            logModuleCall('orrism', __METHOD__, [], 'Connection test failed: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Reset connection (useful after configuration changes)
     */
    public static function reset()
    {
        self::$capsule = null;
        self::$config = null;
    }
}