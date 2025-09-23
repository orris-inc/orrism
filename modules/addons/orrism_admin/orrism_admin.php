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
        'fields' => []  // No configuration fields needed during activation
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
        
        // Handle AJAX test connection requests
        if ($action === 'test_connection' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            header('Content-Type: application/json');
            echo json_encode(testDatabaseConnection());
            exit;
        }
        
        if ($action === 'test_redis' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            header('Content-Type: application/json');
            echo json_encode(testRedisConnectionHandler());
            exit;
        }
        
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
        case 'save_settings':
            return handleSettingsSave($vars);
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
        // First check if database is configured
        $settings = getOrrisSettings();
        if (empty($settings['database_host']) || empty($settings['database_name'])) {
            $content .= '<p><i class="fa fa-database"></i> ORRISM Database: ';
            $content .= '<span class="orrism-text-warning">Not Configured</span> ';
            $content .= '<a href="?module=orrism_admin&action=settings" class="btn btn-xs btn-info">Configure Now</a>';
            $content .= '</p>';
        } elseif (class_exists('OrrisDatabaseManager')) {
            $dbManager = new OrrisDatabaseManager();
            $isConnected = $dbManager->testConnection();
            $content .= '<p><i class="fa fa-database"></i> ORRISM Database: ';
            $content .= $isConnected ? '<span class="orrism-text-success">Connected</span>' : '<span class="orrism-text-danger">Not Connected</span>';
            $content .= '</p>';
        } else {
            $content .= '<p><i class="fa fa-database"></i> ORRISM Database: <span class="orrism-text-warning">Manager Not Loaded</span></p>';
        }
    } catch (Exception $e) {
        $content .= '<p><i class="fa fa-database"></i> ORRISM Database: <span class="orrism-text-danger">Error: ' . $e->getMessage() . '</span></p>';
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
    
    // Get current settings from database
    $settings = getOrrisSettings();
    
    // Settings form
    $content .= '<div class="row">';
    
    // Database Configuration
    $content .= '<div class="col-xs-12 col-sm-12 col-md-6 col-lg-6">';
    $content .= '<div class="orrism-panel">';
    $content .= '<div class="orrism-panel-heading">Database Configuration</div>';
    $content .= '<div class="orrism-panel-body">';
    $content .= '<form method="post" action="?module=orrism_admin&action=settings">';
    $content .= '<input type="hidden" name="action" value="save_settings">';
    $content .= '<input type="hidden" name="settings_type" value="database">';
    
    $content .= '<div class="form-group">';
    $content .= '<label for="db_host">Database Host</label>';
    $content .= '<input type="text" class="form-control" id="db_host" name="database_host" value="' . htmlspecialchars($settings['database_host'] ?? 'localhost') . '" required>';
    $content .= '<small class="form-text text-muted">Database server hostname or IP address</small>';
    $content .= '</div>';
    
    $content .= '<div class="form-group">';
    $content .= '<label for="db_name">Database Name</label>';
    $content .= '<input type="text" class="form-control" id="db_name" name="database_name" value="' . htmlspecialchars($settings['database_name'] ?? 'orrism') . '" required>';
    $content .= '<small class="form-text text-muted">Database name for ORRISM data</small>';
    $content .= '</div>';
    
    $content .= '<div class="form-group">';
    $content .= '<label for="db_user">Database Username</label>';
    $content .= '<input type="text" class="form-control" id="db_user" name="database_user" value="' . htmlspecialchars($settings['database_user'] ?? '') . '" required>';
    $content .= '<small class="form-text text-muted">Database username</small>';
    $content .= '</div>';
    
    $content .= '<div class="form-group">';
    $content .= '<label for="db_pass">Database Password</label>';
    $content .= '<input type="password" class="form-control" id="db_pass" name="database_password" value="' . htmlspecialchars($settings['database_password'] ?? '') . '">';
    $content .= '<small class="form-text text-muted">Database password (leave blank to keep current)</small>';
    $content .= '</div>';
    
    $content .= '<button type="submit" class="btn btn-primary btn-sm">Save Database Settings</button>';
    $content .= ' <button type="button" class="btn btn-info btn-sm" onclick="testDatabaseConnection()" id="testDbBtn">';
    $content .= '<i class="fa fa-plug"></i> Test Connection</button>';
    $content .= '</form>';
    $content .= '<div id="testResult" class="mt-3"></div>';
    $content .= '</div></div></div>';
    
    // Redis Configuration
    $content .= '<div class="col-xs-12 col-sm-12 col-md-6 col-lg-6">';
    $content .= '<div class="orrism-panel">';
    $content .= '<div class="orrism-panel-heading">Cache Configuration (Optional)</div>';
    $content .= '<div class="orrism-panel-body">';
    $content .= '<form method="post" action="?module=orrism_admin&action=settings">';
    $content .= '<input type="hidden" name="action" value="save_settings">';
    $content .= '<input type="hidden" name="settings_type" value="redis">';
    
    $content .= '<div class="form-group">';
    $content .= '<label for="redis_host">Redis Host</label>';
    $content .= '<input type="text" class="form-control" id="redis_host" name="redis_host" value="' . htmlspecialchars($settings['redis_host'] ?? 'localhost') . '">';
    $content .= '<small class="form-text text-muted">Redis server hostname (optional)</small>';
    $content .= '</div>';
    
    $content .= '<div class="form-group">';
    $content .= '<label for="redis_port">Redis Port</label>';
    $content .= '<input type="text" class="form-control" id="redis_port" name="redis_port" value="' . htmlspecialchars($settings['redis_port'] ?? '6379') . '">';
    $content .= '<small class="form-text text-muted">Redis server port</small>';
    $content .= '</div>';
    
    $content .= '<button type="submit" class="btn btn-primary btn-sm">Save Redis Settings</button>';
    $content .= ' <button type="button" class="btn btn-info btn-sm" onclick="testRedisConnection()">';
    $content .= '<i class="fa fa-server"></i> Test Redis</button>';
    $content .= '</form>';
    $content .= '<div id="redisTestResult" class="mt-3"></div>';
    $content .= '</div></div></div>';
    
    $content .= '</div>';  // End row
    
    // General Settings
    $content .= '<div class="orrism-panel">';
    $content .= '<div class="orrism-panel-heading">General Settings</div>';
    $content .= '<div class="orrism-panel-body">';
    $content .= '<form method="post" action="?module=orrism_admin&action=settings">';
    $content .= '<input type="hidden" name="action" value="save_settings">';
    $content .= '<input type="hidden" name="settings_type" value="general">';
    
    $content .= '<div class="form-check">';
    $content .= '<input type="checkbox" class="form-check-input" id="auto_sync" name="auto_sync" value="1" ' . ($settings['auto_sync'] ? 'checked' : '') . '>';
    $content .= '<label class="form-check-label" for="auto_sync">Enable Automatic User Synchronization</label>';
    $content .= '</div>';
    
    $content .= '<div class="form-check">';
    $content .= '<input type="checkbox" class="form-check-input" id="auto_reset" name="auto_reset_traffic" value="1" ' . ($settings['auto_reset_traffic'] ? 'checked' : '') . '>';
    $content .= '<label class="form-check-label" for="auto_reset">Enable Automatic Traffic Reset</label>';
    $content .= '</div>';
    
    $content .= '<div class="form-group mt-3">';
    $content .= '<label for="reset_day">Traffic Reset Day (1-28)</label>';
    $content .= '<input type="number" class="form-control" id="reset_day" name="reset_day" min="1" max="28" value="' . htmlspecialchars($settings['reset_day'] ?? '1') . '">';
    $content .= '<small class="form-text text-muted">Day of month for traffic reset</small>';
    $content .= '</div>';
    
    $content .= '<button type="submit" class="btn btn-primary btn-sm">Save General Settings</button>';
    $content .= '</form>';
    $content .= '</div></div>';
    
    // Add JavaScript for testing connection
    $content .= '<script>
    function testDatabaseConnection() {
        var btn = document.getElementById("testDbBtn");
        var resultDiv = document.getElementById("testResult");
        
        // Get form values
        var host = document.getElementById("db_host").value;
        var name = document.getElementById("db_name").value;
        var user = document.getElementById("db_user").value;
        var pass = document.getElementById("db_pass").value;
        
        if (!host || !name || !user) {
            resultDiv.innerHTML = \'<div class="alert alert-warning"><i class="fa fa-exclamation-triangle"></i> Please fill in all required database fields</div>\';
            return;
        }
        
        // Disable button and show loading
        btn.disabled = true;
        btn.innerHTML = \'<i class="fa fa-spinner fa-spin"></i> Testing...\';
        resultDiv.innerHTML = \'<div class="alert alert-info"><i class="fa fa-spinner fa-spin"></i> Testing connection to \' + host + \'...</div>\';
        
        // Make AJAX request
        var xhr = new XMLHttpRequest();
        xhr.open("POST", "addonmodules.php?module=orrism_admin&action=test_connection", true);
        xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
        
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4) {
                btn.disabled = false;
                btn.innerHTML = \'<i class="fa fa-plug"></i> Test Connection\';
                
                if (xhr.status === 200) {
                    try {
                        var response = JSON.parse(xhr.responseText);
                        if (response.success) {
                            resultDiv.innerHTML = \'<div class="alert alert-success">\' +
                                \'<i class="fa fa-check-circle"></i> <strong>Connection Successful!</strong><br>\' +
                                \'Database: \' + response.database + \'<br>\' +
                                \'Server: \' + response.server + \'<br>\' +
                                (response.tables_exist ? \'<span class="text-info">ORRISM tables detected</span>\' : \'<span class="text-warning">No ORRISM tables found (run Database Setup)</span>\') +
                                \'</div>\';
                        } else {
                            resultDiv.innerHTML = \'<div class="alert alert-danger">\' +
                                \'<i class="fa fa-times-circle"></i> <strong>Connection Failed</strong><br>\' +
                                response.message +
                                \'</div>\';
                        }
                    } catch (e) {
                        resultDiv.innerHTML = \'<div class="alert alert-danger"><i class="fa fa-times-circle"></i> Error parsing response</div>\';
                    }
                } else {
                    resultDiv.innerHTML = \'<div class="alert alert-danger"><i class="fa fa-times-circle"></i> Request failed. Please try again.</div>\';
                }
            }
        };
        
        // Send the request
        var params = "test_host=" + encodeURIComponent(host) + 
                    "&test_name=" + encodeURIComponent(name) + 
                    "&test_user=" + encodeURIComponent(user) + 
                    "&test_pass=" + encodeURIComponent(pass);
        xhr.send(params);
    }
    
    // Test Redis connection
    function testRedisConnection() {
        var resultDiv = document.getElementById("redisTestResult");
        var host = document.getElementById("redis_host").value;
        var port = document.getElementById("redis_port").value;
        
        if (!host || !port) {
            resultDiv.innerHTML = \'<div class="alert alert-warning"><i class="fa fa-exclamation-triangle"></i> Please fill in Redis host and port</div>\';
            return;
        }
        
        resultDiv.innerHTML = \'<div class="alert alert-info"><i class="fa fa-spinner fa-spin"></i> Testing Redis connection...</div>\';
        
        // Make AJAX request
        var xhr = new XMLHttpRequest();
        xhr.open("POST", "addonmodules.php?module=orrism_admin&action=test_redis", true);
        xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
        
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4 && xhr.status === 200) {
                try {
                    var response = JSON.parse(xhr.responseText);
                    if (response.success) {
                        resultDiv.innerHTML = \'<div class="alert alert-success"><i class="fa fa-check-circle"></i> Redis connected successfully!</div>\';
                    } else {
                        resultDiv.innerHTML = \'<div class="alert alert-danger"><i class="fa fa-times-circle"></i> \' + response.message + \'</div>\';
                    }
                } catch (e) {
                    resultDiv.innerHTML = \'<div class="alert alert-danger"><i class="fa fa-times-circle"></i> Error parsing response</div>\';
                }
            }
        };
        
        xhr.send("redis_host=" + encodeURIComponent(host) + "&redis_port=" + encodeURIComponent(port));
    }
    </script>';
    
    $content .= '</div>';
    
    return $content;
    
    } catch (Exception $e) {
        return '<div class="orrism-alert orrism-alert-danger">Settings Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}

/**
 * Get ORRISM settings from database
 * 
 * @return array
 */
function getOrrisSettings()
{
    try {
        $pdo = Capsule::connection()->getPdo();
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM mod_orrism_admin_settings");
        $settings = [];
        
        while ($row = $stmt->fetch()) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        
        return $settings;
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Save ORRISM settings to database
 * 
 * @param array $settings Settings to save
 * @return bool
 */
function saveOrrisSettings($settings)
{
    try {
        $pdo = Capsule::connection()->getPdo();
        
        foreach ($settings as $key => $value) {
            $stmt = $pdo->prepare("
                INSERT INTO mod_orrism_admin_settings (setting_key, setting_value, updated_at) 
                VALUES (?, ?, NOW())
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()
            ");
            $stmt->execute([$key, $value]);
        }
        
        // Clear cached configuration in OrrisDB
        if (class_exists('OrrisDB')) {
            OrrisDB::reset();
        }
        
        return true;
    } catch (Exception $e) {
        logModuleCall('orrism', __FUNCTION__, $settings, 'Error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Handle settings save request
 * 
 * @param array $vars Module variables
 * @return string
 */
function handleSettingsSave($vars)
{
    $settingsType = $_POST['settings_type'] ?? '';
    $settings = [];
    
    switch ($settingsType) {
        case 'database':
            $settings['database_host'] = $_POST['database_host'] ?? 'localhost';
            $settings['database_name'] = $_POST['database_name'] ?? 'orrism';
            $settings['database_user'] = $_POST['database_user'] ?? '';
            
            // Only update password if provided
            if (!empty($_POST['database_password'])) {
                $settings['database_password'] = $_POST['database_password'];
            }
            break;
            
        case 'redis':
            $settings['redis_host'] = $_POST['redis_host'] ?? 'localhost';
            $settings['redis_port'] = $_POST['redis_port'] ?? '6379';
            break;
            
        case 'general':
            $settings['auto_sync'] = isset($_POST['auto_sync']) ? '1' : '0';
            $settings['auto_reset_traffic'] = isset($_POST['auto_reset_traffic']) ? '1' : '0';
            $settings['reset_day'] = $_POST['reset_day'] ?? '1';
            break;
    }
    
    if (saveOrrisSettings($settings)) {
        $message = '<div class="orrism-alert orrism-alert-success">Settings saved successfully!</div>';
    } else {
        $message = '<div class="orrism-alert orrism-alert-danger">Failed to save settings.</div>';
    }
    
    return $message . renderSettings($vars);
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

/**
 * Test database connection
 * 
 * @return array JSON response
 */
function testDatabaseConnection()
{
    try {
        $host = $_POST['test_host'] ?? '';
        $name = $_POST['test_name'] ?? '';
        $user = $_POST['test_user'] ?? '';
        $pass = $_POST['test_pass'] ?? '';
        
        if (empty($host) || empty($name) || empty($user)) {
            return [
                'success' => false,
                'message' => 'Missing required parameters'
            ];
        }
        
        // Try to connect using PDO
        $dsn = "mysql:host=$host;dbname=$name;charset=utf8";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => 5  // 5 second timeout
        ];
        
        $pdo = new PDO($dsn, $user, $pass, $options);
        
        // Get server info
        $serverInfo = $pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
        
        // Check if ORRISM tables exist
        $stmt = $pdo->query("SHOW TABLES LIKE 'mod_orrism_%'");
        $tablesExist = $stmt->rowCount() > 0;
        
        return [
            'success' => true,
            'database' => $name,
            'server' => "MySQL $serverInfo",
            'tables_exist' => $tablesExist
        ];
        
    } catch (PDOException $e) {
        $message = $e->getMessage();
        
        // Clean up error message for user
        if (strpos($message, 'Access denied') !== false) {
            $message = 'Access denied. Please check username and password.';
        } elseif (strpos($message, 'Unknown database') !== false) {
            $message = "Database '$name' does not exist.";
        } elseif (strpos($message, 'Connection refused') !== false || strpos($message, 'No such host') !== false) {
            $message = "Cannot connect to server at '$host'.";
        } else {
            // Generic database error, log the full error
            logModuleCall('orrism', __FUNCTION__, ['host' => $host, 'database' => $name], 'Error: ' . $message);
            $message = 'Database connection failed: ' . substr($message, 0, 100);
        }
        
        return [
            'success' => false,
            'message' => $message
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Unexpected error: ' . $e->getMessage()
        ];
    }
}

/**
 * Test Redis connection
 * 
 * @return array JSON response
 */
function testRedisConnectionHandler()
{
    try {
        $host = $_POST['redis_host'] ?? 'localhost';
        $port = $_POST['redis_port'] ?? 6379;
        
        if (!class_exists('Redis')) {
            return [
                'success' => false,
                'message' => 'Redis PHP extension is not installed'
            ];
        }
        
        $redis = new Redis();
        $connected = @$redis->connect($host, (int)$port, 2); // 2 second timeout
        
        if ($connected) {
            // Try to ping Redis
            $pong = $redis->ping();
            $redis->close();
            
            if ($pong) {
                return [
                    'success' => true,
                    'message' => 'Redis connected successfully'
                ];
            }
        }
        
        return [
            'success' => false,
            'message' => "Cannot connect to Redis at $host:$port"
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Redis connection error: ' . $e->getMessage()
        ];
    }
}