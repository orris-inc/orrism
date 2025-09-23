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

// Load required dependencies from server module
$serverModulePath = __DIR__ . '/../../servers/orrism';

// Check if server module exists
if (!is_dir($serverModulePath)) {
    // Fallback to WHMCS root detection
    $whmcsRoot = dirname(__DIR__, 3);
    $serverModulePath = $whmcsRoot . '/modules/servers/orrism';
}

// Include dependencies with error handling
$dependencies = [
    'database_manager.php' => $serverModulePath . '/includes/database_manager.php',
    'whmcs_database.php' => $serverModulePath . '/includes/whmcs_database.php', 
    'helper.php' => $serverModulePath . '/helper.php'
];

$loadErrors = [];
foreach ($dependencies as $name => $path) {
    if (file_exists($path)) {
        try {
            require_once $path;
        } catch (Exception $e) {
            $loadErrors[] = "Failed to include $name: " . $e->getMessage();
            error_log("ORRISM Admin: Failed to include dependency: $name - " . $e->getMessage());
        }
    } else {
        $loadErrors[] = "File not found: $name at $path";
        error_log("ORRISM Admin: Failed to load dependency: $name at $path");
    }
}

// Store load errors for later display
global $orrism_load_errors;
$orrism_load_errors = $loadErrors;

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

// Include debug helper
require_once __DIR__ . '/debug.php';

/**
 * Create fallback classes if dependencies fail to load
 */
if (!class_exists('OrrisDatabaseManager')) {
    class OrrisDatabaseManager {
        public function testConnection() { return false; }
        public function isInstalled() { return false; }
        public function getUserCount() { return 0; }
        public function install() { return ['success' => false, 'message' => 'Database manager not available']; }
    }
}

if (!class_exists('OrrisDatabase')) {
    class OrrisDatabase {
        public function getActiveServiceCount($module) { return 0; }
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
    // Enable error reporting for debugging
    if (isset($_GET['debug']) || !class_exists('OrrisDatabaseManager')) {
        ini_set('display_errors', 1);
        error_reporting(E_ALL);
    }
    
    try {
        // Add responsive CSS styles with better WHMCS theme compatibility
        $output = '<style>
        /* Main container with responsive padding */
        .orrism-admin-dashboard { 
            padding: 15px; 
            max-width: 100%;
            overflow-x: hidden;
        }
        
        /* Responsive navigation tabs */
        .orrism-nav-tabs { 
            margin-bottom: 20px;
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
        }
        .orrism-nav-tabs .btn { 
            margin: 2px;
            flex: 0 1 auto;
            white-space: nowrap;
        }
        
        /* Mobile responsive navigation */
        @media (max-width: 768px) {
            .orrism-nav-tabs {
                flex-direction: column;
            }
            .orrism-nav-tabs .btn {
                width: 100%;
                margin: 2px 0;
                text-align: left;
            }
        }
        
        /* Alert styles with proper spacing */
        .orrism-alert {
            padding: 12px 15px;
            margin-bottom: 20px;
            border: 1px solid transparent;
            border-radius: 4px;
            word-wrap: break-word;
        }
        .orrism-alert-success { 
            color: #3c763d; 
            background-color: #dff0d8; 
            border-color: #d6e9c6; 
        }
        .orrism-alert-warning { 
            color: #8a6d3b; 
            background-color: #fcf8e3; 
            border-color: #faebcc; 
        }
        .orrism-alert-danger { 
            color: #a94442; 
            background-color: #f2dede; 
            border-color: #ebccd1; 
        }
        .orrism-alert-info { 
            color: #31708f; 
            background-color: #d9edf7; 
            border-color: #bce8f1; 
        }
        
        /* Text color utilities */
        .orrism-text-success { color: #3c763d; }
        .orrism-text-danger { color: #a94442; }
        .orrism-text-warning { color: #8a6d3b; }
        .orrism-text-muted { color: #777; }
        
        /* Panel styles with responsive design */
        .orrism-panel { 
            margin-bottom: 20px; 
            background-color: #fff; 
            border: 1px solid #ddd; 
            border-radius: 4px;
            box-shadow: 0 1px 1px rgba(0,0,0,.05);
        }
        .orrism-panel-heading { 
            padding: 10px 15px; 
            background-color: #f5f5f5; 
            border-bottom: 1px solid #ddd;
            border-radius: 3px 3px 0 0;
            font-weight: 600;
        }
        .orrism-panel-body { 
            padding: 15px;
            overflow-x: auto;
        }
        
        /* Responsive grid adjustments */
        @media (max-width: 992px) {
            .orrism-admin-dashboard .col-md-6 {
                width: 100%;
                margin-bottom: 10px;
            }
        }
        
        /* Table responsive wrapper */
        .orrism-table-responsive {
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        
        /* Button group responsive */
        .orrism-btn-group {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
        }
        
        /* Form controls responsive */
        .orrism-form-control {
            width: 100%;
            max-width: 100%;
        }
        
        /* Ensure compatibility with WHMCS admin theme */
        .orrism-admin-dashboard h2 {
            font-size: 24px;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e0e0e0;
        }
        
        /* Small screen adjustments */
        @media (max-width: 576px) {
            .orrism-admin-dashboard {
                padding: 10px;
            }
            .orrism-admin-dashboard h2 {
                font-size: 20px;
            }
            .orrism-panel-body {
                padding: 10px;
            }
        }
        
        /* Dark mode compatibility */
        @media (prefers-color-scheme: dark) {
            .orrism-panel {
                background-color: #2d2d2d;
                border-color: #444;
            }
            .orrism-panel-heading {
                background-color: #333;
                border-color: #444;
                color: #e0e0e0;
            }
        }
        </style>';
        
        $action = isset($_GET['action']) ? $_GET['action'] : 'dashboard';
        
        // Handle POST requests
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $output .= handlePostRequest($vars);
        } else {
            // Generate output based on action
            switch ($action) {
                case 'database':
                    $output .= renderDatabaseSetup($vars);
                    break;
                case 'nodes':
                    $output .= renderNodeManagement($vars);
                    break;
                case 'users':
                    $output .= renderUserManagement($vars);
                    break;
                case 'traffic':
                    $output .= renderTrafficManagement($vars);
                    break;
                case 'settings':
                    $output .= renderSettings($vars);
                    break;
                default:
                    $output .= renderDashboard($vars);
                    break;
            }
        }
        
        // WHMCS expects the function to echo the output, not return it
        echo $output;
        
    } catch (Exception $e) {
        // Display error information instead of blank page
        $errorOutput = '<div class="orrism-alert orrism-alert-danger">';
        $errorOutput .= '<h4>ORRISM Administration Error</h4>';
        $errorOutput .= '<p><strong>Error:</strong> ' . htmlspecialchars($e->getMessage()) . '</p>';
        $errorOutput .= '<p><strong>File:</strong> ' . htmlspecialchars($e->getFile()) . '</p>';
        $errorOutput .= '<p><strong>Line:</strong> ' . $e->getLine() . '</p>';
        if (isset($_GET['debug'])) {
            $errorOutput .= '<p><strong>Stack Trace:</strong></p>';
            $errorOutput .= '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
        }
        $errorOutput .= '<p><a href="?module=orrism_admin&debug=1" class="btn btn-warning btn-sm">Enable Debug Mode</a></p>';
        $errorOutput .= '</div>';
        
        // Add debug information
        $errorOutput .= orrism_debug_output_html();
        
        echo $errorOutput;
    } catch (Error $e) {
        // Ultimate fallback for fatal errors
        echo '<div style="padding: 20px; border: 2px solid #d9534f; background: #f2dede; color: #a94442;">' .
               '<h3>ORRISM Administration - Critical Error</h3>' .
               '<p><strong>A critical error occurred:</strong> ' . htmlspecialchars($e->getMessage()) . '</p>' .
               '<p><strong>Location:</strong> ' . htmlspecialchars($e->getFile()) . ':' . $e->getLine() . '</p>' .
               '<p>Please check server error logs and contact system administrator.</p>' .
               '<p><a href="?module=orrism_admin&debug=1">Enable Debug Mode</a> | ' .
               '<a href="?module=orrism_admin">Reload</a></p>' .
               '</div>';
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
    try {
        $content = '<div class="orrism-admin-dashboard">';
        $content .= '<h2>ORRISM System Dashboard</h2>';
    
    // Navigation menu with responsive design
    $content .= '<div class="orrism-nav-tabs">';
    $content .= '<a href="?module=orrism_admin&action=dashboard" class="btn btn-primary btn-sm">Dashboard</a>';
    $content .= '<a href="?module=orrism_admin&action=database" class="btn btn-default btn-sm">Database Setup</a>';
    $content .= '<a href="?module=orrism_admin&action=nodes" class="btn btn-default btn-sm">Node Management</a>';
    $content .= '<a href="?module=orrism_admin&action=users" class="btn btn-default btn-sm">User Management</a>';
    $content .= '<a href="?module=orrism_admin&action=traffic" class="btn btn-default btn-sm">Traffic Management</a>';
    $content .= '<a href="?module=orrism_admin&action=settings" class="btn btn-default btn-sm">Settings</a>';
    $content .= '</div>';
    
    // System status with responsive columns
    $content .= '<div class="row">';
    $content .= '<div class="col-xs-12 col-sm-12 col-md-6 col-lg-6">';
    $content .= '<div class="orrism-panel">';
    $content .= '<div class="orrism-panel-heading">System Status</div>';
    $content .= '<div class="orrism-panel-body">';
    
    // Check database connection
    try {
        if (class_exists('OrrisDatabaseManager')) {
            $dbManager = new OrrisDatabaseManager();
            $isConnected = $dbManager->testConnection();
            $content .= '<p><i class="fa fa-database"></i> ShadowSocks Database: ';
            $content .= $isConnected ? '<span class="orrism-text-success">Connected</span>' : '<span class="orrism-text-danger">Not Connected</span>';
            $content .= '</p>';
        } else {
            $content .= '<p><i class="fa fa-database"></i> ShadowSocks Database: <span class="orrism-text-warning">Manager Not Loaded</span></p>';
        }
    } catch (Exception $e) {
        $content .= '<p><i class="fa fa-database"></i> ShadowSocks Database: <span class="orrism-text-danger">Error: ' . $e->getMessage() . '</span></p>';
    }
    
    // Check Redis connection
    $content .= '<p><i class="fa fa-server"></i> Redis Cache: ';
    if (class_exists('Redis')) {
        try {
            $redis = new Redis();
            $connected = $redis->connect($vars['redis_host'] ?? 'localhost', $vars['redis_port'] ?? 6379);
            $content .= $connected ? '<span class="orrism-text-success">Connected</span>' : '<span class="orrism-text-danger">Not Connected</span>';
            if ($connected) $redis->close();
        } catch (Exception $e) {
            $content .= '<span class="orrism-text-danger">Error</span>';
        }
    } else {
        $content .= '<span class="orrism-text-warning">Redis Extension Not Installed</span>';
    }
    $content .= '</p>';
    
    $content .= '</div></div></div>';
    
    // Quick stats with responsive columns
    $content .= '<div class="col-xs-12 col-sm-12 col-md-6 col-lg-6">';
    $content .= '<div class="orrism-panel">';
    $content .= '<div class="orrism-panel-heading">Quick Statistics</div>';
    $content .= '<div class="orrism-panel-body">';
    
    try {
        if (class_exists('OrrisDatabase')) {
            $whmcsDb = new OrrisDatabase();
            $totalServices = $whmcsDb->getActiveServiceCount('orrism');
            $content .= '<p>Active Services: <strong>' . $totalServices . '</strong></p>';
        }
        
        // Try to get ShadowSocks stats if database is connected
        if (isset($isConnected) && $isConnected && isset($dbManager)) {
            $userCount = $dbManager->getUserCount();
            $content .= '<p>ShadowSocks Users: <strong>' . $userCount . '</strong></p>';
        }
    } catch (Exception $e) {
        $content .= '<p class="orrism-text-muted">Statistics unavailable: ' . $e->getMessage() . '</p>';
    }
    
    $content .= '</div></div></div></div>';
    $content .= '</div>';
    
    // Add debug information if in development mode or if there are loading issues
    if (isset($_GET['debug']) || !class_exists('OrrisDatabaseManager')) {
        $content .= orrism_debug_output_html();
    }
    
    $content .= '</div>';
    return $content;
    
    } catch (Exception $e) {
        return '<div class="orrism-alert orrism-alert-danger">Dashboard Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}

/**
 * Render database setup page
 * 
 * @param array $vars Module variables
 * @return string
 */
function renderDatabaseSetup($vars)
{
    try {
        $content = '<div class="orrism-admin-dashboard">';
        $content .= '<h2>Database Setup & Installation</h2>';
    
    // Navigation with responsive design
    $content .= '<div class="orrism-nav-tabs">';
    $content .= '<a href="?module=orrism_admin&action=dashboard" class="btn btn-default btn-sm">Dashboard</a>';
    $content .= '<a href="?module=orrism_admin&action=database" class="btn btn-primary btn-sm">Database Setup</a>';
    $content .= '</div>';
    
    // Database installation form with responsive panel
    $content .= '<div class="orrism-panel">';
    $content .= '<div class="orrism-panel-heading">Install ShadowSocks Database</div>';
    $content .= '<div class="orrism-panel-body">';
    $content .= '<form method="post">';
    $content .= '<input type="hidden" name="action" value="install_database">';
    $content .= '<p>This will create the necessary database tables for ShadowSocks integration.</p>';
    $content .= '<p><strong>Current Configuration:</strong></p>';
    $content .= '<ul>';
    $content .= '<li>Host: ' . ($vars['database_host'] ?? 'localhost') . '</li>';
    $content .= '<li>Database: ' . ($vars['database_name'] ?? 'shadowsocks') . '</li>';
    $content .= '<li>Username: ' . ($vars['database_user'] ?? 'shadowsocks_user') . '</li>';
    $content .= '</ul>';
    $content .= '<button type="submit" class="btn btn-success btn-sm">Install Database Tables</button>';
    $content .= '</form>';
    $content .= '</div></div>';
    $content .= '</div>';
    
    return $content;
    
    } catch (Exception $e) {
        return '<div class="orrism-alert orrism-alert-danger">Database Setup Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
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
        // Check if database manager class is available
        if (!class_exists('OrrisDatabaseManager')) {
            // Fallback: Try simple table creation
            return handleSimpleTableCreation($vars);
        }
        
        // Create database manager instance
        $dbManager = new OrrisDatabaseManager();
        
        // Check if already installed
        if ($dbManager->isInstalled()) {
            return '<div class="orrism-alert orrism-alert-warning">Database tables already exist. If you need to reinstall, please uninstall first.</div>' . renderDatabaseSetup($vars);
        }
        
        // Run installation
        $result = $dbManager->install();
        
        if ($result['success']) {
            // Update addon settings
            try {
                $pdo = Capsule::connection()->getPdo();
                $stmt = $pdo->prepare("UPDATE mod_orrism_admin_settings SET setting_value = '1' WHERE setting_key = 'db_initialized'");
                $stmt->execute();
            } catch (Exception $updateError) {
                // Log but don't fail the installation
                error_log('ORRISM: Failed to update addon settings: ' . $updateError->getMessage());
            }
            
            return '<div class="orrism-alert orrism-alert-success">Database installed successfully! ' . $result['message'] . '</div>' . renderDatabaseSetup($vars);
        } else {
            return '<div class="orrism-alert orrism-alert-danger">Database installation failed: ' . $result['message'] . '</div>' . renderDatabaseSetup($vars);
        }
        
    } catch (Exception $e) {
        error_log('ORRISM: Database installation error: ' . $e->getMessage());
        
        // Try fallback method
        return handleSimpleTableCreation($vars);
    }
}

/**
 * Fallback simple table creation method
 */
function handleSimpleTableCreation($vars)
{
    try {
        $pdo = Capsule::connection()->getPdo();
        
        // Simple table creation without transactions
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
            
            'mod_orrism_users' => "CREATE TABLE IF NOT EXISTS mod_orrism_users (
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
        foreach ($tables as $tableName => $sql) {
            try {
                $pdo->exec($sql);
                $createdTables[] = $tableName;
            } catch (Exception $tableError) {
                error_log("ORRISM: Failed to create table $tableName: " . $tableError->getMessage());
            }
        }
        
        if (count($createdTables) > 0) {
            // Insert default data
            try {
                $pdo->exec("INSERT IGNORE INTO mod_orrism_node_groups (name, description) VALUES ('Default Group', 'Default node group')");
            } catch (Exception $dataError) {
                error_log('ORRISM: Failed to insert default data: ' . $dataError->getMessage());
            }
            
            return '<div class="orrism-alert orrism-alert-success">Database tables created successfully! Created: ' . implode(', ', $createdTables) . '</div>' . renderDatabaseSetup($vars);
        } else {
            return '<div class="orrism-alert orrism-alert-danger">Failed to create any database tables. Please check error logs.</div>' . renderDatabaseSetup($vars);
        }
        
    } catch (Exception $e) {
        return '<div class="orrism-alert orrism-alert-danger">Simple installation also failed: ' . $e->getMessage() . '</div>' . renderDatabaseSetup($vars);
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
    try {
        $content = '<div class="orrism-admin-dashboard">';
        $content .= '<h2>Node Management</h2>';
    
    // Navigation with responsive design
    $content .= '<div class="orrism-nav-tabs">';
    $content .= '<a href="?module=orrism_admin&action=dashboard" class="btn btn-default btn-sm">Dashboard</a>';
    $content .= '<a href="?module=orrism_admin&action=nodes" class="btn btn-primary btn-sm">Node Management</a>';
    $content .= '</div>';
    
    $content .= '<div class="orrism-alert orrism-alert-info">Node management functionality will be implemented here.</div>';
    $content .= '</div>';
    
    return $content;
    
    } catch (Exception $e) {
        return '<div class="orrism-alert orrism-alert-danger">Node Management Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}

/**
 * Render user management page
 * 
 * @param array $vars Module variables
 * @return string
 */
function renderUserManagement($vars)
{
    try {
        $content = '<div class="orrism-admin-dashboard">';
        $content .= '<h2>User Management</h2>';
    
    // Navigation with responsive design
    $content .= '<div class="orrism-nav-tabs">';
    $content .= '<a href="?module=orrism_admin&action=dashboard" class="btn btn-default btn-sm">Dashboard</a>';
    $content .= '<a href="?module=orrism_admin&action=users" class="btn btn-primary btn-sm">User Management</a>';
    $content .= '</div>';
    
    $content .= '<div class="orrism-alert orrism-alert-info">User management functionality will be implemented here.</div>';
    $content .= '</div>';
    
    return $content;
    
    } catch (Exception $e) {
        return '<div class="orrism-alert orrism-alert-danger">User Management Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}

/**
 * Render traffic management page
 * 
 * @param array $vars Module variables
 * @return string
 */
function renderTrafficManagement($vars)
{
    try {
        $content = '<div class="orrism-admin-dashboard">';
        $content .= '<h2>Traffic Management</h2>';
    
    // Navigation with responsive design
    $content .= '<div class="orrism-nav-tabs">';
    $content .= '<a href="?module=orrism_admin&action=dashboard" class="btn btn-default btn-sm">Dashboard</a>';
    $content .= '<a href="?module=orrism_admin&action=traffic" class="btn btn-primary btn-sm">Traffic Management</a>';
    $content .= '</div>';
    
    $content .= '<div class="orrism-alert orrism-alert-info">Traffic management functionality will be implemented here.</div>';
    $content .= '</div>';
    
    return $content;
    
    } catch (Exception $e) {
        return '<div class="orrism-alert orrism-alert-danger">Traffic Management Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}

/**
 * Render settings page
 * 
 * @param array $vars Module variables
 * @return string
 */
function renderSettings($vars)
{
    try {
        $content = '<div class="orrism-admin-dashboard">';
        $content .= '<h2>ORRISM Settings</h2>';
    
    // Navigation with responsive design
    $content .= '<div class="orrism-nav-tabs">';
    $content .= '<a href="?module=orrism_admin&action=dashboard" class="btn btn-default btn-sm">Dashboard</a>';
    $content .= '<a href="?module=orrism_admin&action=settings" class="btn btn-primary btn-sm">Settings</a>';
    $content .= '</div>';
    
    $content .= '<div class="orrism-alert orrism-alert-info">Advanced settings configuration will be implemented here.</div>';
    $content .= '</div>';
    
    return $content;
    
    } catch (Exception $e) {
        return '<div class="orrism-alert orrism-alert-danger">Settings Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}

/**
 * Handle user sync request
 * 
 * @param array $vars Module variables
 * @return string
 */
function handleUserSync($vars)
{
    try {
        $message = '<div class="orrism-alert orrism-alert-info">User synchronization functionality is not yet implemented.</div>';
        return $message . renderUserManagement($vars);
    } catch (Exception $e) {
        return '<div class="orrism-alert orrism-alert-danger">User Sync Error: ' . htmlspecialchars($e->getMessage()) . '</div>' . renderUserManagement($vars);
    }
}

/**
 * Handle traffic reset request
 * 
 * @param array $vars Module variables
 * @return string
 */
function handleTrafficReset($vars)
{
    try {
        $message = '<div class="orrism-alert orrism-alert-info">Traffic reset functionality is not yet implemented.</div>';
        return $message . renderTrafficManagement($vars);
    } catch (Exception $e) {
        return '<div class="orrism-alert orrism-alert-danger">Traffic Reset Error: ' . htmlspecialchars($e->getMessage()) . '</div>' . renderTrafficManagement($vars);
    }
}