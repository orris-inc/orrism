<?php
/**
 * ORRISM Administration Module for WHMCS
 * Centralized configuration and management for ORRISM ShadowSocks system
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

// Load required dependencies from parent module
$parentModulePath = dirname(__DIR__, 2) . '/servers/orrism';
require_once $parentModulePath . '/includes/database_manager.php';
require_once $parentModulePath . '/includes/whmcs_database.php';
require_once $parentModulePath . '/helper.php';

/**
 * Addon module configuration
 * 
 * @return array
 */
function orrism_admin_config()
{
    return [
        'name' => 'ORRISM Administration',
        'description' => 'Centralized management for ORRISM ShadowSocks system including database setup, node management, and user administration.',
        'version' => '2.0',
        'author' => 'ORRISM Development Team',
        'language' => 'english',
        'fields' => [
            'database_host' => [
                'FriendlyName' => 'ShadowSocks Database Host',
                'Type' => 'text',
                'Size' => '25',
                'Default' => 'localhost',
                'Description' => 'Database server hostname or IP'
            ],
            'database_name' => [
                'FriendlyName' => 'ShadowSocks Database Name',
                'Type' => 'text',
                'Size' => '25',
                'Default' => 'shadowsocks',
                'Description' => 'Database name for ShadowSocks data'
            ],
            'database_user' => [
                'FriendlyName' => 'Database Username',
                'Type' => 'text',
                'Size' => '25',
                'Default' => 'shadowsocks_user',
                'Description' => 'Database username'
            ],
            'database_password' => [
                'FriendlyName' => 'Database Password',
                'Type' => 'password',
                'Size' => '25',
                'Description' => 'Database password'
            ],
            'redis_host' => [
                'FriendlyName' => 'Redis Host',
                'Type' => 'text',
                'Size' => '25',
                'Default' => 'localhost',
                'Description' => 'Redis server for caching'
            ],
            'redis_port' => [
                'FriendlyName' => 'Redis Port',
                'Type' => 'text',
                'Size' => '10',
                'Default' => '6379',
                'Description' => 'Redis server port'
            ],
            'auto_sync' => [
                'FriendlyName' => 'Auto Sync Enabled',
                'Type' => 'yesno',
                'Description' => 'Automatically sync user data with ShadowSocks database'
            ]
        ]
    ];
}

/**
 * Addon module activation
 * 
 * @return array
 */
function orrism_admin_activate()
{
    try {
        // Create addon configuration tables if needed
        $pdo = Capsule::connection()->getPdo();
        
        // Create addon settings table
        $sql = "CREATE TABLE IF NOT EXISTS mod_orrism_admin_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(100) UNIQUE NOT NULL,
            setting_value TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )";
        $pdo->exec($sql);
        
        // Insert default settings
        $defaultSettings = [
            'db_initialized' => '0',
            'last_sync' => '',
            'sync_enabled' => '1'
        ];
        
        foreach ($defaultSettings as $key => $value) {
            $stmt = $pdo->prepare("INSERT IGNORE INTO mod_orrism_admin_settings (setting_key, setting_value) VALUES (?, ?)");
            $stmt->execute([$key, $value]);
        }
        
        return [
            'status' => 'success',
            'description' => 'ORRISM Administration module activated successfully.'
        ];
        
    } catch (Exception $e) {
        return [
            'status' => 'error',
            'description' => 'Failed to activate module: ' . $e->getMessage()
        ];
    }
}

/**
 * Addon module deactivation
 * 
 * @return array
 */
function orrism_admin_deactivate()
{
    try {
        // Note: We don't drop tables on deactivation for safety
        return [
            'status' => 'success',
            'description' => 'ORRISM Administration module deactivated successfully.'
        ];
        
    } catch (Exception $e) {
        return [
            'status' => 'error', 
            'description' => 'Failed to deactivate module: ' . $e->getMessage()
        ];
    }
}

/**
 * Main addon output function
 * 
 * @param array $vars Module variables
 * @return string
 */
function orrism_admin_output($vars)
{
    $action = isset($_GET['action']) ? $_GET['action'] : 'dashboard';
    
    // Handle POST requests
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        return handlePostRequest($vars);
    }
    
    // Generate output based on action
    switch ($action) {
        case 'database':
            return renderDatabaseSetup($vars);
        case 'nodes':
            return renderNodeManagement($vars);
        case 'users':
            return renderUserManagement($vars);
        case 'traffic':
            return renderTrafficManagement($vars);
        case 'settings':
            return renderSettings($vars);
        default:
            return renderDashboard($vars);
    }
}

/**
 * Handle POST requests
 * 
 * @param array $vars Module variables
 * @return string
 */
function handlePostRequest($vars)
{
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'install_database':
            return handleDatabaseInstall($vars);
        case 'sync_users':
            return handleUserSync($vars);
        case 'reset_traffic':
            return handleTrafficReset($vars);
        default:
            return renderDashboard($vars);
    }
}

/**
 * Render main dashboard
 * 
 * @param array $vars Module variables
 * @return string
 */
function renderDashboard($vars)
{
    $content = '<div class="orrism-admin-dashboard">';
    $content .= '<h2>ORRISM System Dashboard</h2>';
    
    // Navigation menu
    $content .= '<div class="nav-tabs" style="margin-bottom: 20px;">';
    $content .= '<a href="?module=orrism_admin&action=dashboard" class="btn btn-default">Dashboard</a> ';
    $content .= '<a href="?module=orrism_admin&action=database" class="btn btn-default">Database Setup</a> ';
    $content .= '<a href="?module=orrism_admin&action=nodes" class="btn btn-default">Node Management</a> ';
    $content .= '<a href="?module=orrism_admin&action=users" class="btn btn-default">User Management</a> ';
    $content .= '<a href="?module=orrism_admin&action=traffic" class="btn btn-default">Traffic Management</a> ';
    $content .= '<a href="?module=orrism_admin&action=settings" class="btn btn-default">Settings</a>';
    $content .= '</div>';
    
    // System status
    $content .= '<div class="row">';
    $content .= '<div class="col-md-6">';
    $content .= '<div class="panel panel-default">';
    $content .= '<div class="panel-heading">System Status</div>';
    $content .= '<div class="panel-body">';
    
    // Check database connection
    try {
        $dbManager = new DatabaseManager();
        $isConnected = $dbManager->testConnection();
        $content .= '<p><i class="fa fa-database"></i> ShadowSocks Database: ';
        $content .= $isConnected ? '<span class="text-success">Connected</span>' : '<span class="text-danger">Not Connected</span>';
        $content .= '</p>';
    } catch (Exception $e) {
        $content .= '<p><i class="fa fa-database"></i> ShadowSocks Database: <span class="text-danger">Error</span></p>';
    }
    
    // Check Redis connection
    $content .= '<p><i class="fa fa-server"></i> Redis Cache: ';
    if (class_exists('Redis')) {
        try {
            $redis = new Redis();
            $connected = $redis->connect($vars['redis_host'] ?? 'localhost', $vars['redis_port'] ?? 6379);
            $content .= $connected ? '<span class="text-success">Connected</span>' : '<span class="text-danger">Not Connected</span>';
            if ($connected) $redis->close();
        } catch (Exception $e) {
            $content .= '<span class="text-danger">Error</span>';
        }
    } else {
        $content .= '<span class="text-warning">Redis Extension Not Installed</span>';
    }
    $content .= '</p>';
    
    $content .= '</div></div></div>';
    
    // Quick stats
    $content .= '<div class="col-md-6">';
    $content .= '<div class="panel panel-default">';
    $content .= '<div class="panel-heading">Quick Statistics</div>';
    $content .= '<div class="panel-body">';
    
    try {
        $whmcsDb = new WhmcsDatabase();
        $totalServices = $whmcsDb->getActiveServiceCount('orrism');
        $content .= '<p>Active Services: <strong>' . $totalServices . '</strong></p>';
        
        // Try to get ShadowSocks stats if database is connected
        if (isset($isConnected) && $isConnected) {
            $userCount = $dbManager->getUserCount();
            $content .= '<p>ShadowSocks Users: <strong>' . $userCount . '</strong></p>';
        }
    } catch (Exception $e) {
        $content .= '<p class="text-muted">Statistics unavailable</p>';
    }
    
    $content .= '</div></div></div></div>';
    $content .= '</div>';
    
    return $content;
}

/**
 * Render database setup page
 * 
 * @param array $vars Module variables
 * @return string
 */
function renderDatabaseSetup($vars)
{
    $content = '<h2>Database Setup & Installation</h2>';
    
    // Navigation
    $content .= '<div class="nav-tabs" style="margin-bottom: 20px;">';
    $content .= '<a href="?module=orrism_admin&action=dashboard" class="btn btn-default">Dashboard</a> ';
    $content .= '<a href="?module=orrism_admin&action=database" class="btn btn-primary">Database Setup</a>';
    $content .= '</div>';
    
    // Database installation form
    $content .= '<div class="panel panel-default">';
    $content .= '<div class="panel-heading">Install ShadowSocks Database</div>';
    $content .= '<div class="panel-body">';
    $content .= '<form method="post">';
    $content .= '<input type="hidden" name="action" value="install_database">';
    $content .= '<p>This will create the necessary database tables for ShadowSocks integration.</p>';
    $content .= '<p><strong>Current Configuration:</strong></p>';
    $content .= '<ul>';
    $content .= '<li>Host: ' . ($vars['database_host'] ?? 'localhost') . '</li>';
    $content .= '<li>Database: ' . ($vars['database_name'] ?? 'shadowsocks') . '</li>';
    $content .= '<li>Username: ' . ($vars['database_user'] ?? 'shadowsocks_user') . '</li>';
    $content .= '</ul>';
    $content .= '<button type="submit" class="btn btn-success">Install Database Tables</button>';
    $content .= '</form>';
    $content .= '</div></div>';
    
    return $content;
}

/**
 * Handle database installation
 * 
 * @param array $vars Module variables
 * @return string
 */
function handleDatabaseInstall($vars)
{
    try {
        $migrationPath = dirname(__DIR__, 2) . '/servers/orrism/migration/legacy_data_migration.php';
        
        if (file_exists($migrationPath)) {
            // Include and run migration
            include_once $migrationPath;
            
            if (function_exists('install_shadowsocks_database')) {
                $result = install_shadowsocks_database($vars);
                
                if ($result['success']) {
                    // Update settings
                    $pdo = Capsule::connection()->getPdo();
                    $stmt = $pdo->prepare("UPDATE mod_orrism_admin_settings SET setting_value = '1' WHERE setting_key = 'db_initialized'");
                    $stmt->execute();
                    
                    return '<div class="alert alert-success">Database installed successfully!</div>' . renderDatabaseSetup($vars);
                } else {
                    return '<div class="alert alert-danger">Database installation failed: ' . $result['message'] . '</div>' . renderDatabaseSetup($vars);
                }
            }
        }
        
        return '<div class="alert alert-danger">Migration script not found.</div>' . renderDatabaseSetup($vars);
        
    } catch (Exception $e) {
        return '<div class="alert alert-danger">Installation error: ' . $e->getMessage() . '</div>' . renderDatabaseSetup($vars);
    }
}

/**
 * Render node management page
 * 
 * @param array $vars Module variables
 * @return string
 */
function renderNodeManagement($vars)
{
    $content = '<h2>Node Management</h2>';
    
    // Navigation
    $content .= '<div class="nav-tabs" style="margin-bottom: 20px;">';
    $content .= '<a href="?module=orrism_admin&action=dashboard" class="btn btn-default">Dashboard</a> ';
    $content .= '<a href="?module=orrism_admin&action=nodes" class="btn btn-primary">Node Management</a>';
    $content .= '</div>';
    
    $content .= '<div class="alert alert-info">Node management functionality will be implemented here.</div>';
    
    return $content;
}

/**
 * Render user management page
 * 
 * @param array $vars Module variables
 * @return string
 */
function renderUserManagement($vars)
{
    $content = '<h2>User Management</h2>';
    
    // Navigation
    $content .= '<div class="nav-tabs" style="margin-bottom: 20px;">';
    $content .= '<a href="?module=orrism_admin&action=dashboard" class="btn btn-default">Dashboard</a> ';
    $content .= '<a href="?module=orrism_admin&action=users" class="btn btn-primary">User Management</a>';
    $content .= '</div>';
    
    $content .= '<div class="alert alert-info">User management functionality will be implemented here.</div>';
    
    return $content;
}

/**
 * Render traffic management page
 * 
 * @param array $vars Module variables
 * @return string
 */
function renderTrafficManagement($vars)
{
    $content = '<h2>Traffic Management</h2>';
    
    // Navigation
    $content .= '<div class="nav-tabs" style="margin-bottom: 20px;">';
    $content .= '<a href="?module=orrism_admin&action=dashboard" class="btn btn-default">Dashboard</a> ';
    $content .= '<a href="?module=orrism_admin&action=traffic" class="btn btn-primary">Traffic Management</a>';
    $content .= '</div>';
    
    $content .= '<div class="alert alert-info">Traffic management functionality will be implemented here.</div>';
    
    return $content;
}

/**
 * Render settings page
 * 
 * @param array $vars Module variables
 * @return string
 */
function renderSettings($vars)
{
    $content = '<h2>ORRISM Settings</h2>';
    
    // Navigation
    $content .= '<div class="nav-tabs" style="margin-bottom: 20px;">';
    $content .= '<a href="?module=orrism_admin&action=dashboard" class="btn btn-default">Dashboard</a> ';
    $content .= '<a href="?module=orrism_admin&action=settings" class="btn btn-primary">Settings</a>';
    $content .= '</div>';
    
    $content .= '<div class="alert alert-info">Advanced settings configuration will be implemented here.</div>';
    
    return $content;
}