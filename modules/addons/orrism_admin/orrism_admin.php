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

/**
 * Safe wrapper for logModuleCall to prevent errors
 */
function safeLogModuleCall($module, $action, $request, $response) {
    try {
        if (function_exists('logModuleCall')) {
            logModuleCall($module, $action, $request, $response);
        } else {
            error_log("ORRISM $action: " . (is_string($response) ? $response : json_encode($response)));
        }
    } catch (Exception $e) {
        error_log("ORRISM: Failed to log [$action]: " . $e->getMessage());
    }
}

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
            // Clean all output buffers and suppress errors for clean JSON response
            while (ob_get_level()) {
                ob_end_clean();
            }
            
            // Start fresh output buffering
            ob_start();
            
            try {
                // Set HTTP status code to 200 and headers
                if (!headers_sent()) {
                    http_response_code(200);
                    header('Content-Type: application/json; charset=utf-8');
                    header('X-ORRISM-Debug: Connection-Test');
                    header('Cache-Control: no-cache, must-revalidate');
                }
            
            // Comprehensive request logging with safe header collection
            $headers = [];
            if (function_exists('getallheaders')) {
                $headers = getallheaders();
            } else {
                // Fallback for environments where getallheaders() is not available
                foreach ($_SERVER as $key => $value) {
                    if (strpos($key, 'HTTP_') === 0) {
                        $header = str_replace('_', '-', substr($key, 5));
                        $headers[$header] = $value;
                    }
                }
            }
            
            // Safely read PHP input
            $phpInput = '';
            try {
                $phpInput = file_get_contents('php://input');
            } catch (Exception $e) {
                $phpInput = 'Error reading php://input: ' . $e->getMessage();
            }
            
            $requestData = [
                'POST' => $_POST,
                'headers' => $headers,
                'php_input' => $phpInput,
                'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
            ];
            safeLogModuleCall('orrism', 'test_connection_request_detailed', $requestData, 'Database connection test requested with full context');
            
            try {
                // Check if testDatabaseConnection function exists
                if (!function_exists('testDatabaseConnection')) {
                    throw new Exception('testDatabaseConnection function not found');
                }
                
                $result = testDatabaseConnection();
                
                // Enhanced result logging
                $responseData = [
                    'result' => $result,
                    'execution_time' => microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'],
                    'memory_usage' => memory_get_usage(true),
                    'peak_memory' => memory_get_peak_usage(true)
                ];
                safeLogModuleCall('orrism', 'test_connection_result_detailed', $responseData, 'Database connection test completed with performance metrics');
                
                // Clean output buffer and send JSON response
                ob_clean();
                echo json_encode($result);
                ob_end_flush();
                exit; // Important: prevent any additional output
                
            } catch (Exception $e) {
                // Comprehensive error logging
                $errorData = [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                    'post_data' => $_POST,
                    'server_vars' => [
                        'REQUEST_METHOD' => $_SERVER['REQUEST_METHOD'],
                        'CONTENT_TYPE' => $_SERVER['CONTENT_TYPE'] ?? '',
                        'HTTP_ACCEPT' => $_SERVER['HTTP_ACCEPT'] ?? ''
                    ]
                ];
                safeLogModuleCall('orrism', 'test_connection_error_detailed', $errorData, 'Database connection test failed with full error context');
                
                $errorResponse = [
                    'success' => false,
                    'message' => 'Internal error: ' . $e->getMessage(),
                    'debug' => [
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                        'error_type' => get_class($e),
                        'request_data' => $_POST
                    ]
                ];
                // Clean output buffer and send error response
                ob_clean();
                echo json_encode($errorResponse);
                ob_end_flush();
                exit; // Important: prevent any additional output
            }
            
        } catch (Exception $topLevelException) {
            // Catch any unexpected errors in the AJAX handler
            header('Content-Type: application/json');
            $criticalErrorResponse = [
                'success' => false,
                'message' => 'Critical server error: ' . $topLevelException->getMessage(),
                'error_category' => 'critical_error',
                'debug' => [
                    'error_type' => get_class($topLevelException),
                    'error_location' => $topLevelException->getFile() . ':' . $topLevelException->getLine(),
                    'trace' => $topLevelException->getTraceAsString()
                ]
            ];
            
            // Log the critical error
            safeLogModuleCall('orrism', 'test_connection_critical_error', $criticalErrorResponse, 'Critical error in AJAX test connection handler');
            
            // Ensure clean JSON output
            ob_clean();
            echo json_encode($criticalErrorResponse);
            ob_end_flush();
            exit;
        }
        
        exit;
        }
        
        if ($action === 'test_redis' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            // Clean all output buffers for clean JSON response
            while (ob_get_level()) {
                ob_end_clean();
            }
            ob_start();
            
            try {
                // Set headers safely
                if (!headers_sent()) {
                    http_response_code(200);
                    header('Content-Type: application/json; charset=utf-8');
                    header('X-ORRISM-Debug: Redis-Test');
                }
                
                // Log the request safely
                safeLogModuleCall('orrism', 'test_redis_request', $_POST, 'Redis connection test requested');
                
                // Check if testRedisConnectionHandler function exists
                if (!function_exists('testRedisConnectionHandler')) {
                    throw new Exception('testRedisConnectionHandler function not found');
                }
                
                $result = testRedisConnectionHandler();
                
                // Log the result safely
                safeLogModuleCall('orrism', 'test_redis_result', $result, 'Redis connection test completed');
                
                // Clean output and send JSON
                ob_clean();
                echo json_encode($result);
                ob_end_flush();
                exit;
                
            } catch (Exception $e) {
                // Log the error safely
                safeLogModuleCall('orrism', 'test_redis_error', [], 'Redis connection test failed: ' . $e->getMessage());
                
                $errorResponse = [
                    'success' => false,
                    'message' => 'Internal error: ' . $e->getMessage(),
                    'debug' => [
                        'file' => $e->getFile(),
                        'line' => $e->getLine()
                    ]
                ];
                
                // Clean output and send error
                ob_clean();
                echo json_encode($errorResponse);
                ob_end_flush();
                exit;
            }
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
 * Render navigation tabs with active state
 *
 * @param string $activeAction
 * @return string
 */
function renderNavigationTabs($activeAction)
{
    $tabs = [
        'dashboard' => 'Dashboard',
        'database' => 'Database Setup',
        'nodes' => 'Node Management',
        'users' => 'User Management',
        'traffic' => 'Traffic Management',
        'settings' => 'Settings'
    ];

    $nav = '<div class="orrism-nav-tabs">';
    foreach ($tabs as $action => $label) {
        $isActive = ($action === $activeAction);
        $classes = $isActive ? 'btn btn-primary btn-sm' : 'btn btn-default btn-sm';
        $nav .= '<a href="?module=orrism_admin&action=' . $action . '" class="' . $classes . '">' . $label . '</a>';
    }
    $nav .= '</div>';

    return $nav;
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
    $content .= renderNavigationTabs('dashboard');
    
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
            if ($isConnected) {
                $content .= '<span class="orrism-text-success">Connected</span>';
                if (!empty($settings['database_port'])) {
                    $content .= ' <small class="text-muted">Port: ' . htmlspecialchars($settings['database_port']) . '</small>';
                }
            } else {
                $content .= '<span class="orrism-text-danger">Not Connected</span>';
            }
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
            $redisHost = $settings['redis_host'] ?? ($vars['redis_host'] ?? 'localhost');
            $redisPort = isset($settings['redis_port']) ? (int)$settings['redis_port'] : (int)($vars['redis_port'] ?? 6379);
            $redisDbIndex = isset($settings['redis_db']) ? (int)$settings['redis_db'] : (int)($vars['redis_db'] ?? 0);
            $redisUsername = $settings['redis_username'] ?? ($vars['redis_username'] ?? '');
            $redisPassword = $settings['redis_password'] ?? ($vars['redis_password'] ?? '');

            $redis = new Redis();
            $connected = $redis->connect($redisHost, $redisPort);
            
            if ($connected) {
                try {
                    if (!empty($redisUsername) && !empty($redisPassword)) {
                        if (!$redis->auth([$redisUsername, $redisPassword])) {
                            throw new Exception('Redis authentication failed (username + password)');
                        }
                    } elseif (!empty($redisPassword)) {
                        if (!$redis->auth($redisPassword)) {
                            throw new Exception('Redis authentication failed (password only)');
                        }
                    }

                    if (!$redis->select($redisDbIndex)) {
                        throw new Exception('Redis DB #' . $redisDbIndex . ' select failed');
                    }

                    $pong = $redis->ping();
                    if ($pong) {
                        $content .= '<span class="orrism-text-success">Connected</span>';
                        $content .= ' <small class="text-muted">DB: ' . $redisDbIndex . '</small>';
                    } else {
                        $content .= '<span class="orrism-text-warning">Connected (No Ping)</span>';
                    }
                } catch (Exception $redisException) {
                    $content .= '<span class="orrism-text-danger">' . htmlspecialchars($redisException->getMessage()) . '</span>';
                } finally {
                    $redis->close();
                }
            } else {
                $content .= '<span class="orrism-text-danger">Not Connected</span>';
            }
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

        $storedSettings = getOrrisSettings();
        $currentHost = $storedSettings['database_host'] ?? ($vars['database_host'] ?? 'localhost');
        $currentPort = $storedSettings['database_port'] ?? ($vars['database_port'] ?? '3306');
        $currentName = $storedSettings['database_name'] ?? ($vars['database_name'] ?? 'shadowsocks');
        $currentUser = $storedSettings['database_user'] ?? ($vars['database_user'] ?? 'shadowsocks_user');

        // Navigation with responsive design
        $content .= renderNavigationTabs('database');

        // Database installation form with responsive panel
        $content .= '<div class="orrism-panel">';
        $content .= '<div class="orrism-panel-heading">Install ShadowSocks Database</div>';
        $content .= '<div class="orrism-panel-body">';
        $content .= '<form method="post">';
        $content .= '<input type="hidden" name="action" value="install_database">';
        $content .= '<p>This will create the necessary database tables for ShadowSocks integration.</p>';
        $content .= '<p><strong>Current Configuration:</strong></p>';
        $content .= '<ul>';
        $content .= '<li>Host: ' . htmlspecialchars($currentHost) . '</li>';
        $content .= '<li>Port: ' . htmlspecialchars($currentPort) . '</li>';
        $content .= '<li>Database: ' . htmlspecialchars($currentName) . '</li>';
        $content .= '<li>Username: ' . htmlspecialchars($currentUser) . '</li>';
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
    $content .= renderNavigationTabs('nodes');
    
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
    $content .= renderNavigationTabs('users');
    
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
    $content .= renderNavigationTabs('traffic');
    
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
    $content .= renderNavigationTabs('settings');
    
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
    $content .= '<label for="db_port">Database Port</label>';
    $content .= '<input type="number" class="form-control" id="db_port" name="database_port" value="' . htmlspecialchars($settings['database_port'] ?? '3306') . '" min="1" max="65535" required>';
    $content .= '<small class="form-text text-muted">MySQL 端口（默认 3306）</small>';
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

    $content .= '<div class="form-group">';
    $content .= '<label for="redis_db">Redis Database</label>';
    $content .= '<input type="number" class="form-control" id="redis_db" name="redis_db" value="' . htmlspecialchars($settings['redis_db'] ?? '0') . '" min="0">';
    $content .= '<small class="form-text text-muted">Redis 数据库编号（默认 0）</small>';
    $content .= '</div>';

    $content .= '<div class="form-group">';
    $content .= '<label for="redis_username">Redis Username</label>';
    $content .= '<input type="text" class="form-control" id="redis_username" name="redis_username" value="' . htmlspecialchars($settings['redis_username'] ?? '') . '">';
    $content .= '<small class="form-text text-muted">Redis username (optional, for Redis 6.0+ ACL)</small>';
    $content .= '</div>';
    
    $content .= '<div class="form-group">';
    $content .= '<label for="redis_password">Redis Password</label>';
    $content .= '<input type="password" class="form-control" id="redis_password" name="redis_password" value="' . htmlspecialchars($settings['redis_password'] ?? '') . '">';
    $content .= '<small class="form-text text-muted">Redis password (optional)</small>';
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
        var port = document.getElementById("db_port").value;
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
        resultDiv.innerHTML = \'<div class="alert alert-info"><i class="fa fa-spinner fa-spin"></i> Testing connection to \' + host + ":" + (port || "3306") + \'...</div>\';
        
        // Make AJAX request
        var xhr = new XMLHttpRequest();
        var requestUrl = "addonmodules.php?module=orrism_admin&action=test_connection";
        xhr.open("POST", requestUrl, true);
        xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
        
        // Add timeout
        xhr.timeout = 30000; // 30 seconds
        
        // Enhanced response handling with detailed debugging
        xhr.onreadystatechange = function() {
            // Log every state change for debugging
            console.log("ORRISM DB Test - ReadyState:", xhr.readyState, "Status:", xhr.status);
            
            if (xhr.readyState === 4) {
                btn.disabled = false;
                btn.innerHTML = \'<i class="fa fa-plug"></i> Test Connection\';
                
                // Comprehensive response logging
                console.log("ORRISM DB Test - Final Status:", xhr.status);
                console.log("ORRISM DB Test - Status Text:", xhr.statusText);
                console.log("ORRISM DB Test - Response Headers:", xhr.getAllResponseHeaders());
                console.log("ORRISM DB Test - Response Text Length:", xhr.responseText ? xhr.responseText.length : 0);
                console.log("ORRISM DB Test - Raw Response:", xhr.responseText);
                
                // Try to parse JSON response regardless of HTTP status code
                // This handles cases where server returns valid JSON with non-200 status
                var response = null;
                var parseError = null;
                
                try {
                    // Check if response is empty or only whitespace
                    if (!xhr.responseText || xhr.responseText.trim() === "") {
                        throw new Error("Empty response received from server");
                    }
                    
                    response = JSON.parse(xhr.responseText);
                    console.log("ORRISM DB Test - Parsed response:", response);
                } catch (parseErr) {
                    parseError = parseErr;
                    console.error("ORRISM DB Test - JSON parse error:", parseErr);
                }
                
                // If we successfully parsed JSON, process it regardless of HTTP status
                if (response && !parseError) {
                        
                        if (response.success) {
                            var tableInfo = \'\';
                            if (response.tables_exist && response.existing_tables && response.existing_tables.length > 0) {
                                tableInfo = \'<span class="text-info">✓ ORRISM tables detected (\' + response.existing_tables.length + \' tables)</span>\';
                            } else {
                                tableInfo = \'<span class="text-warning">⚠ No ORRISM tables found (run Database Setup)</span>\';
                            }
                            
                            var hostInfo = 'N/A';
                            if (response.connection_details) {
                                hostInfo = (response.connection_details.host || 'N/A');
                                if (response.connection_details.port) {
                                    hostInfo += ':' + response.connection_details.port;
                                }
                            }

                            var performanceInfo = response.execution_time_ms ? 
                                \'<small class="text-muted">Connection time: \' + response.execution_time_ms + \'ms</small>\' : \'\';
                            
                            resultDiv.innerHTML = \'<div class="alert alert-success">\' +
                                \'<i class="fa fa-check-circle"></i> <strong>Connection Successful!</strong><br>\' +
                                \'Database: \' + (response.database || "N/A") + \'<br>\' +
                                \'Server: \' + (response.server || "N/A") + \'<br>\' +
                                \'Host: \' + hostInfo + \'<br>\' +
                                tableInfo + \'<br>\' +
                                performanceInfo +
                                \'</div>\';
                        } else {
                            var debugInfo = \'\';
                            if (response.debug) {
                                debugInfo = \'<details style="margin-top: 10px;"><summary>Debug Information</summary>\' +
                                    \'<small><strong>Error Category:</strong> \' + (response.error_category || "N/A") + \'<br>\' +
                                    \'<strong>Error Code:</strong> \' + (response.error_code || "N/A") + \'<br>\' +
                                    \'<strong>Execution Time:</strong> \' + (response.execution_time_ms || "N/A") + \'ms<br>\' +
                                    \'<strong>Location:</strong> \' + (response.debug.error_location || "N/A") + \'<br>\';
                                    
                                if (response.debug.raw_error) {
                                    debugInfo += \'<strong>Raw Error:</strong><br><code style="font-size: 10px;">\' + 
                                        response.debug.raw_error.replace(/</g, "&lt;").replace(/>/g, "&gt;") + \'</code><br>\';
                                }
                                
                                if (response.debug.connection_params) {
                                    debugInfo += \'<strong>Connection Params:</strong> \' + JSON.stringify(response.debug.connection_params) + \'<br>\';
                                }
                                
                                debugInfo += \'</small></details>\';
                            }
                            
                            var alertClass = "alert-danger";
                            var iconClass = "fa-times-circle";
                            
                            // Use different alert styles based on error category
                            if (response.error_category === \'timeout\') {
                                alertClass = "alert-warning";
                                iconClass = "fa-clock-o";
                            } else if (response.error_category === \'authentication\') {
                                iconClass = "fa-lock";
                            } else if (response.error_category === \'network_error\') {
                                iconClass = "fa-exclamation-triangle";
                            }
                            
                            resultDiv.innerHTML = \'<div class="alert \' + alertClass + \'">\' +
                                \'<i class="fa \' + iconClass + \'"></i> <strong>Connection Failed</strong><br>\' +
                                (response.message || "Unknown error") + debugInfo +
                                \'</div>\';
                        }
                } else {
                    // Handle cases where JSON parsing failed or response is invalid
                    console.log("ORRISM DB Test - Failed to parse JSON or no valid response");
                    console.log("ORRISM DB Test - First 500 chars:", xhr.responseText ? xhr.responseText.substring(0, 500) : "No response text");
                    
                    // Check for common response issues
                    var errorDetails = "";
                    if (!xhr.responseText || xhr.responseText.trim() === "") {
                        errorDetails = "Empty response received";
                    } else if (xhr.responseText.indexOf("Fatal error") !== -1) {
                        errorDetails = "PHP Fatal Error detected in response";
                    } else if (xhr.responseText.indexOf("Parse error") !== -1) {
                        errorDetails = "PHP Parse Error detected in response";
                    } else if (xhr.responseText.indexOf("Warning") !== -1) {
                        errorDetails = "PHP Warning detected in response";
                    } else if (xhr.responseText.indexOf("<html") !== -1) {
                        errorDetails = "HTML response received instead of JSON";
                    } else if (parseError) {
                        errorDetails = "Invalid JSON format: " + parseError.message;
                    } else {
                        errorDetails = "Unknown response format";
                    }
                    
                    // Handle HTTP error status codes
                    var httpStatusInfo = "";
                    if (xhr.status === 500) {
                        httpStatusInfo = "Server returned HTTP 500 but response may contain useful data. ";
                    } else if (xhr.status !== 200) {
                        var httpErrorMsg = "";
                        switch(xhr.status) {
                            case 0:
                                httpErrorMsg = "No response from server (connection failed)";
                                break;
                            case 404:
                                httpErrorMsg = "Addon module not found (404)";
                                break;
                            case 403:
                                httpErrorMsg = "Access forbidden (403)";
                                break;
                            default:
                                httpErrorMsg = "HTTP " + xhr.status + ": " + (xhr.statusText || "Unknown error");
                        }
                        httpStatusInfo = httpErrorMsg + ". ";
                    }
                    
                    resultDiv.innerHTML = \'<div class="alert alert-danger">\' +
                        \'<i class="fa fa-times-circle"></i> <strong>Response Error</strong><br>\' +
                        httpStatusInfo + errorDetails + \'<br>\' +
                        \'<details><summary>Raw Response (first 500 chars)</summary><pre style="white-space: pre-wrap; font-size: 11px;">\' +
                        (xhr.responseText ? xhr.responseText.substring(0, 500).replace(/</g, "&lt;").replace(/>/g, "&gt;") : "No response content") +
                        (xhr.responseText && xhr.responseText.length > 500 ? "..." : "") + \'</pre></details>\' +
                        \'</div>\';
                }
            }
        };
        
        xhr.ontimeout = function() {
            btn.disabled = false;
            btn.innerHTML = \'<i class="fa fa-plug"></i> Test Connection\';
            console.error("ORRISM DB Test - Request timeout");
            resultDiv.innerHTML = \'<div class="alert alert-danger">\' +
                \'<i class="fa fa-clock-o"></i> <strong>Request Timeout</strong><br>\' +
                \'The request took longer than 30 seconds to complete.<br>\' +
                \'This could indicate server overload or network issues.<br>\' +
                \'URL: \' + requestUrl + \'</div>\';
        };
        
        xhr.onerror = function() {
            btn.disabled = false;
            btn.innerHTML = \'<i class="fa fa-plug"></i> Test Connection\';
            console.error("ORRISM DB Test - Network error");
            resultDiv.innerHTML = \'<div class="alert alert-danger">\' +
                \'<i class="fa fa-exclamation-triangle"></i> <strong>Network Error</strong><br>\' +
                \'Failed to establish connection to server.<br>\' +
                \'Please check your network connection and try again.<br>\' +
                \'URL: \' + requestUrl + \'</div>\';
        };
        
        // Prepare and send the request with detailed logging
        var params = "test_host=" + encodeURIComponent(host) + 
                    "&test_port=" + encodeURIComponent(port || '3306') + 
                    "&test_name=" + encodeURIComponent(name) + 
                    "&test_user=" + encodeURIComponent(user) + 
                    "&test_pass=" + encodeURIComponent(pass);
        
        // Log request details for debugging
        console.log("ORRISM DB Test - Request URL:", requestUrl);
        console.log("ORRISM DB Test - Request Method: POST");
        console.log("ORRISM DB Test - Content Type: application/x-www-form-urlencoded");
        console.log("ORRISM DB Test - Timeout:", xhr.timeout + "ms");
        console.log("ORRISM DB Test - Parameters:", {
            host: host,
            port: port,
            name: name,
            user: user,
            pass: pass ? "[HIDDEN]" : "[EMPTY]"
        });
        console.log("ORRISM DB Test - Raw params length:", params.length);
        
        try {
            xhr.send(params);
            console.log("ORRISM DB Test - Request sent successfully");
        } catch (e) {
            console.error("ORRISM DB Test - Send error:", e);
            btn.disabled = false;
            btn.innerHTML = \'<i class="fa fa-plug"></i> Test Connection\';
            resultDiv.innerHTML = \'<div class="alert alert-danger">\' +
                \'<i class="fa fa-exclamation-triangle"></i> <strong>Request Send Error</strong><br>\' +
                \'Failed to send request: \' + e.message + \'</div>\';
        }
    }
    
    // Test Redis connection
    function testRedisConnection() {
        var resultDiv = document.getElementById("redisTestResult");
        var host = document.getElementById("redis_host").value;
        var port = document.getElementById("redis_port").value;
        var db = document.getElementById("redis_db").value;
        var username = document.getElementById("redis_username").value;
        var password = document.getElementById("redis_password").value;
        
        if (!host || !port) {
            resultDiv.innerHTML = \'<div class="alert alert-warning"><i class="fa fa-exclamation-triangle"></i> Please fill in Redis host and port</div>\';
            return;
        }
        
        resultDiv.innerHTML = \'<div class="alert alert-info"><i class="fa fa-spinner fa-spin"></i> Testing Redis connection...</div>\';
        
        // Make AJAX request with timeout
        var xhr = new XMLHttpRequest();
        var requestUrl = "addonmodules.php?module=orrism_admin&action=test_redis";
        xhr.open("POST", requestUrl, true);
        xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
        
        // Add timeout
        xhr.timeout = 15000; // 15 seconds timeout for Redis test
        
        xhr.onreadystatechange = function() {
            console.log("Redis Test - ReadyState:", xhr.readyState, "Status:", xhr.status);
            
            if (xhr.readyState === 4) {
                console.log("Redis Test - Final Status:", xhr.status);
                console.log("Redis Test - Response:", xhr.responseText);
                
                if (xhr.status === 200) {
                try {
                    var response = JSON.parse(xhr.responseText);
                    if (response.success) {
                        var infoHtml = \'\';
                        if (response.info) {
                            infoHtml = \'<br><small class="text-muted">\' +
                                \'Version: \' + (response.info.version || "Unknown") + \'<br>\' +
                                \'Auth: \' + (response.auth_method || "Unknown") + \'<br>\' +
                                (typeof response.database !== 'undefined' ? \'DB: \' + response.database + \'<br>\' : \'\') +
                                (response.info.connected_clients ? \'Clients: \' + response.info.connected_clients + \'<br>\' : \'\') +
                                (response.info.memory_usage ? \'Memory: \' + response.info.memory_usage : \'\') +
                                \'</small>\';
                        }
                        resultDiv.innerHTML = \'<div class="alert alert-success">\' +
                            \'<i class="fa fa-check-circle"></i> <strong>Redis connected successfully!</strong>\' +
                            infoHtml + \'</div>\';
                    } else {
                        resultDiv.innerHTML = \'<div class="alert alert-danger">\' +
                            \'<i class="fa fa-times-circle"></i> <strong>Redis connection failed</strong><br>\' +
                            (response.message || "Unknown error") + \'</div>\';
                    }
                } catch (e) {
                    console.error("Redis Test - JSON parse error:", e);
                    resultDiv.innerHTML = \'<div class="alert alert-danger">\' +
                        \'<i class="fa fa-times-circle"></i> <strong>Error parsing response</strong><br>\' +
                        \'Parse error: \' + e.message + \'<br>\' +
                        \'Raw response: \' + xhr.responseText.substring(0, 200) + \'</div>\';
                }
                } else {
                    // Handle HTTP errors
                    console.error("Redis Test - HTTP error:", xhr.status, xhr.statusText);
                    var errorMsg = "HTTP " + xhr.status + ": " + (xhr.statusText || "Unknown error");
                    resultDiv.innerHTML = \'<div class="alert alert-danger">\' +
                        \'<i class="fa fa-times-circle"></i> <strong>Request Failed</strong><br>\' +
                        errorMsg + \'<br>URL: \' + requestUrl + \'</div>\';
                }
            }
        };
        
        xhr.ontimeout = function() {
            console.error("Redis Test - Request timeout");
            resultDiv.innerHTML = \'<div class="alert alert-danger">\' +
                \'<i class="fa fa-clock-o"></i> <strong>Request Timeout</strong><br>\' +
                \'Redis connection test timed out after 15 seconds.</div>\';
        };
        
        xhr.onerror = function() {
            console.error("Redis Test - Network error");
            resultDiv.innerHTML = \'<div class="alert alert-danger">\' +
                \'<i class="fa fa-exclamation-triangle"></i> <strong>Network Error</strong><br>\' +
                \'Failed to connect to server.</div>\';
        };
        
        var params = "redis_host=" + encodeURIComponent(host) + 
                    "&redis_port=" + encodeURIComponent(port) + 
                    "&redis_db=" + encodeURIComponent(db || 0) + 
                    "&redis_username=" + encodeURIComponent(username) + 
                    "&redis_password=" + encodeURIComponent(password);
        
        // Log request details for debugging
        console.log("Redis Test - Request URL:", requestUrl);
        console.log("Redis Test - Parameters:", {
            host: host,
            port: port,
            db: db,
            username: username,
            password: password ? "[HIDDEN]" : "[EMPTY]"
        });
        
        try {
            xhr.send(params);
            console.log("Redis Test - Request sent successfully");
        } catch (e) {
            console.error("Redis Test - Send error:", e);
            resultDiv.innerHTML = \'<div class="alert alert-danger">\' +
                \'<i class="fa fa-exclamation-triangle"></i> <strong>Request Send Error</strong><br>\' +
                \'Failed to send request: \' + e.message + \'</div>\';
        }
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
            $dbPort = $_POST['database_port'] ?? '3306';
            $settings['database_port'] = is_numeric($dbPort) ? (string) max(1, min(65535, (int) $dbPort)) : '3306';
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
            $redisDb = $_POST['redis_db'] ?? '0';
            $settings['redis_db'] = is_numeric($redisDb) ? (string) max(0, (int) $redisDb) : '0';
            $settings['redis_username'] = $_POST['redis_username'] ?? '';
            $settings['redis_password'] = $_POST['redis_password'] ?? '';
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
    $startTime = microtime(true);
    
    try {
        // Log function entry
        safeLogModuleCall('orrism', 'testDatabaseConnection_start', $_POST, 'Starting database connection test');
        
        $host = $_POST['test_host'] ?? '';
        $port = isset($_POST['test_port']) && is_numeric($_POST['test_port']) ? (int) $_POST['test_port'] : 3306;
        if ($port <= 0 || $port > 65535) {
            $port = 3306;
        }
        $name = $_POST['test_name'] ?? '';
        $user = $_POST['test_user'] ?? '';
        $pass = $_POST['test_pass'] ?? '';
        
        // Enhanced parameter validation
        $missingParams = [];
        if (empty($host)) $missingParams[] = 'host';
        if (empty($name)) $missingParams[] = 'database name';
        if (empty($user)) $missingParams[] = 'username';
        
        if (!empty($missingParams)) {
            $errorResponse = [
                'success' => false,
                'message' => 'Missing required parameters: ' . implode(', ', $missingParams),
                'debug' => [
                    'missing_params' => $missingParams,
                    'received_params' => array_keys($_POST)
                ]
            ];
            safeLogModuleCall('orrism', 'testDatabaseConnection_validation_error', $errorResponse, 'Parameter validation failed');
            return $errorResponse;
        }
        
        // Enhanced connection attempt with detailed logging
        $dsn = "mysql:host=$host;port=$port;dbname=$name;charset=utf8";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => 10,  // Increased timeout to 10 seconds
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"
        ];
        
        safeLogModuleCall('orrism', 'testDatabaseConnection_attempt', [
            'dsn' => "mysql:host=$host;port=$port;dbname=$name;charset=utf8",
            'user' => $user,
            'options' => array_keys($options)
        ], 'Attempting PDO connection');
        
        $pdo = new PDO($dsn, $user, $pass, $options);
        
        // Test the connection is actually working
        $pdo->query('SELECT 1');
        
        // Get detailed server info
        $serverVersion = $pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
        $serverInfo = $pdo->getAttribute(PDO::ATTR_SERVER_INFO);
        $connectionStatus = $pdo->getAttribute(PDO::ATTR_CONNECTION_STATUS);
        
        safeLogModuleCall('orrism', 'testDatabaseConnection_connected', [
            'server_version' => $serverVersion,
            'server_info' => $serverInfo,
            'connection_status' => $connectionStatus,
            'port' => $port
        ], 'Database connection established');
        
        // Check if ORRISM tables exist (core tables without prefix)
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
        
        $executionTime = round((microtime(true) - $startTime) * 1000, 2); // milliseconds
        
        $successResponse = [
            'success' => true,
            'database' => $name,
            'server' => "MySQL $serverVersion",
            'server_info' => $serverInfo,
            'tables_exist' => $tablesExist,
            'existing_tables' => $existingTables,
            'execution_time_ms' => $executionTime,
            'connection_details' => [
                'host' => $host,
                'port' => $port,
                'charset' => 'utf8',
                'connection_status' => $connectionStatus
            ]
        ];
        
        safeLogModuleCall('orrism', 'testDatabaseConnection_success', $successResponse, 'Database connection test completed successfully');
        return $successResponse;
        
    } catch (PDOException $e) {
        $executionTime = round((microtime(true) - $startTime) * 1000, 2);
        $rawMessage = $e->getMessage();
        $errorCode = $e->getCode();
        
        // Comprehensive error logging
        $errorDetails = [
            'message' => $rawMessage,
            'code' => $errorCode,
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'host' => $host,
            'port' => $port,
            'database' => $name,
            'user' => $user,
            'execution_time_ms' => $executionTime,
            'dsn_used' => "mysql:host=$host;port=$port;dbname=$name;charset=utf8"
        ];
        safeLogModuleCall('orrism', 'testDatabaseConnection_pdo_error', $errorDetails, 'PDO Exception occurred during connection test');
        
        // Clean up error message for user-friendly display
        $userMessage = '';
        $errorCategory = 'unknown';
        
        if (strpos($rawMessage, 'Access denied') !== false) {
            $userMessage = 'Access denied. Please check username and password.';
            $errorCategory = 'authentication';
        } elseif (strpos($rawMessage, 'Unknown database') !== false) {
            $userMessage = "Database '$name' does not exist. Please check the database name.";
            $errorCategory = 'database_not_found';
        } elseif (strpos($rawMessage, 'Connection refused') !== false || strpos($rawMessage, 'No such host') !== false) {
            $userMessage = "Cannot connect to server at '$host:$port'. Please check the hostname/port and ensure MySQL is running.";
            $errorCategory = 'connection_refused';
        } elseif (strpos($rawMessage, 'timed out') !== false || strpos($rawMessage, 'timeout') !== false) {
            $userMessage = "Connection timeout. The server may be overloaded or unreachable.";
            $errorCategory = 'timeout';
        } elseif (strpos($rawMessage, 'No route to host') !== false) {
            $userMessage = "Network error: Cannot reach host '$host:$port'.";
            $errorCategory = 'network_error';
        } else {
            $userMessage = 'Database connection failed: ' . substr($rawMessage, 0, 150) . (strlen($rawMessage) > 150 ? '...' : '');
            $errorCategory = 'generic_error';
        }
        
        $errorResponse = [
            'success' => false,
            'message' => $userMessage,
            'error_category' => $errorCategory,
            'error_code' => $errorCode,
            'execution_time_ms' => $executionTime,
            'debug' => [
                'raw_error' => substr($rawMessage, 0, 200),
                'error_location' => $e->getFile() . ':' . $e->getLine(),
                'connection_params' => [
                    'host' => $host,
                    'port' => $port,
                    'database' => $name,
                    'user' => $user
                ]
            ]
        ];
        
        return $errorResponse;
        
    } catch (Exception $e) {
        $executionTime = round((microtime(true) - $startTime) * 1000, 2);
        
        // Log unexpected errors
        $unexpectedErrorDetails = [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
            'execution_time_ms' => $executionTime
        ];
        safeLogModuleCall('orrism', 'testDatabaseConnection_unexpected_error', $unexpectedErrorDetails, 'Unexpected exception during connection test');
        
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
 * Test Redis connection
 * 
 * @return array JSON response
 */
function testRedisConnectionHandler()
{
    try {
        $host = $_POST['redis_host'] ?? 'localhost';
        $port = $_POST['redis_port'] ?? 6379;
        $username = $_POST['redis_username'] ?? '';
        $password = $_POST['redis_password'] ?? '';
        $dbIndex = isset($_POST['redis_db']) ? max(0, (int) $_POST['redis_db']) : 0;
        
        if (!class_exists('Redis')) {
            return [
                'success' => false,
                'message' => 'Redis PHP extension is not installed'
            ];
        }
        
        $redis = new Redis();
        $connected = @$redis->connect($host, (int)$port, 2); // 2 second timeout
        
        if (!$connected) {
            return [
                'success' => false,
                'message' => "Failed to connect to Redis server at {$host}:{$port}"
            ];
        }
        
        // Handle authentication
        try {
            if (!empty($username) && !empty($password)) {
                // Redis 6.0+ ACL with username and password
                $authResult = $redis->auth([$username, $password]);
                if (!$authResult) {
                    $redis->close();
                    return [
                        'success' => false,
                        'message' => 'Redis authentication failed with username and password'
                    ];
                }
            } elseif (!empty($password)) {
                // Traditional Redis auth with password only
                $authResult = $redis->auth($password);
                if (!$authResult) {
                    $redis->close();
                    return [
                        'success' => false,
                        'message' => 'Redis authentication failed with password'
                    ];
                }
            }
            
            // Select target database if available
            try {
                $selectResult = $redis->select($dbIndex);
            } catch (Exception $selectException) {
                $redis->close();
                return [
                    'success' => false,
                    'message' => 'Redis select database #' . $dbIndex . ' error: ' . $selectException->getMessage()
                ];
            }

            if (!$selectResult) {
                $redis->close();
                return [
                    'success' => false,
                    'message' => 'Redis select database #' . $dbIndex . ' failed'
                ];
            }

            // Try to ping Redis to verify the connection is working
            $pong = $redis->ping();
            
            // Get Redis info for additional details
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
                // If we can't get info, it's not critical
                $info['version'] = 'Unknown';
            }
            
            $redis->close();
            
            if ($pong) {
                return [
                    'success' => true,
                    'message' => 'Redis connected successfully',
                    'info' => $info,
                    'database' => $dbIndex,
                    'auth_method' => !empty($username) ? 'ACL (username + password)' : (!empty($password) ? 'Password only' : 'No authentication')
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
