<?php
/**
 * ORRISM Administration Module for WHMCS
 * Centralized configuration and management for the ORRISM system
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

/**
 * Safe wrapper for logModuleCall with sensitive data protection
 *
 * @param string $module Module name
 * @param string $action Action being performed
 * @param mixed $request Request data
 * @param mixed $response Response data
 * @param mixed $processedData Processed response data
 * @param array $replaceVars Additional sensitive fields to hide
 * @return void
 */
function safeLogModuleCall($module, $action, $request, $response, $processedData = '', $replaceVars = []) {
    // Default sensitive fields for addon module
    $defaultSensitiveFields = [
        'password',
        'database_password',
        'redis_password',
        'apikey',
        'api_key',
        'token',
        'secret',
        'auth_token',
        'access_token'
    ];

    // Merge custom sensitive fields with defaults
    $sensitiveFields = array_merge($defaultSensitiveFields, $replaceVars);

    try {
        if (function_exists('logModuleCall')) {
            logModuleCall(
                $module,
                $action,
                $request,
                $response,
                $processedData,
                $sensitiveFields
            );
        } else {
            error_log("ORRISM $action: " . (is_string($response) ? $response : json_encode($response)));
        }
    } catch (Exception $e) {
        error_log("ORRISM: Failed to log [$action]: " . $e->getMessage());
    }
}

/**
 * Addon module configuration
 * 
 * @return array
 */
function orrism_admin_config()
{
    return [
        'name' => 'ORRISM Administration',
        'description' => 'Centralized management for the ORRISM system including settings configuration, node management, and user administration.',
        'version' => '2.0',
        'author' => 'ORRISM Development Team',
        'language' => 'english',
        'fields' => [
            // Database Configuration
            'database_host' => [
                'FriendlyName' => 'ORRISM Database Host',
                'Type' => 'text',
                'Size' => '25',
                'Default' => 'localhost',
                'Description' => 'ORRISM database server hostname'
            ],
            'database_port' => [
                'FriendlyName' => 'Database Port',
                'Type' => 'text',
                'Size' => '10',
                'Default' => '3306',
                'Description' => 'Database server port'
            ],
            'database_name' => [
                'FriendlyName' => 'Database Name',
                'Type' => 'text',
                'Size' => '25',
                'Default' => 'orrism',
                'Description' => 'ORRISM database name'
            ],
            'database_user' => [
                'FriendlyName' => 'Database User',
                'Type' => 'text',
                'Size' => '25',
                'Description' => 'Database username'
            ],
            'database_password' => [
                'FriendlyName' => 'Database Password',
                'Type' => 'password',
                'Size' => '25',
                'Description' => 'Database password'
            ],
            
            // Redis Configuration
            'redis_host' => [
                'FriendlyName' => 'Redis Host',
                'Type' => 'text',
                'Size' => '25',
                'Default' => 'localhost',
                'Description' => 'Redis server hostname'
            ],
            'redis_port' => [
                'FriendlyName' => 'Redis Port',
                'Type' => 'text',
                'Size' => '10',
                'Default' => '6379',
                'Description' => 'Redis server port'
            ],
            'redis_db' => [
                'FriendlyName' => 'Redis Database',
                'Type' => 'text',
                'Size' => '5',
                'Default' => '0',
                'Description' => 'Redis database number (0-15)'
            ],
            'redis_username' => [
                'FriendlyName' => 'Redis Username',
                'Type' => 'text',
                'Size' => '25',
                'Description' => 'Redis username (Redis 6.0+ ACL)'
            ],
            'redis_password' => [
                'FriendlyName' => 'Redis Password',
                'Type' => 'password',
                'Size' => '25',
                'Description' => 'Redis authentication password'
            ],
            
            // General Settings
            'enable_traffic_log' => [
                'FriendlyName' => 'Enable Traffic Logging',
                'Type' => 'yesno',
                'Description' => 'Enable detailed traffic usage logging'
            ],
            'traffic_reset_day' => [
                'FriendlyName' => 'Traffic Reset Day',
                'Type' => 'text',
                'Size' => '5',
                'Default' => '1',
                'Description' => 'Day of month to reset traffic (1-28)'
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
        // Module activation successful
        // Database tables will be installed manually after configuration
        return [
            'status' => 'success',
            'description' => 'ORRISM Administration module activated successfully. Please configure database settings and install tables from the Settings page.'
        ];
        
    } catch (Exception $e) {
        return [
            'status' => 'error',
            'description' => 'Module activation failed: ' . $e->getMessage()
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
        // By default, keep tables and configuration for safety
        return [
            'status' => 'success',
            'description' => 'ORRISM Administration module deactivated. Database tables and configuration retained for safety.'
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
 * @return void
 */
function orrism_admin_output($vars)
{
    // Register autoloader for module classes
    spl_autoload_register(function ($class) {
        // Check if class belongs to our namespace
        $prefix = 'WHMCS\\Module\\Addon\\OrrisAdmin\\';
        $len = strlen($prefix);
        
        if (strncmp($prefix, $class, $len) !== 0) {
            return;
        }
        
        // Get the relative class name
        $relative_class = substr($class, $len);
        
        // Replace namespace separator with directory separator
        $file = __DIR__ . '/lib/' . str_replace('\\', '/', $relative_class) . '.php';
        
        // If the file exists, require it
        if (file_exists($file)) {
            require_once $file;
        }
    });
    
    // Load server module dependencies
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
                safeLogModuleCall(
                    'orrism_admin',
                    'dependency_load',
                    ['file' => $name],
                    $e->getMessage(),
                    $e->getTraceAsString()
                );
            }
        } else {
            $loadErrors[] = "File not found: $name at $path";
            safeLogModuleCall(
                'orrism_admin',
                'dependency_missing',
                ['file' => $name],
                "File not found at: $path"
            );
        }
    }
    
    // Include debug helper if available
    $debugPath = __DIR__ . '/debug.php';
    if (file_exists($debugPath)) {
        require_once $debugPath;
    }
    
    // Create fallback classes if dependencies fail to load
    if (!class_exists('OrrisDatabaseManager')) {
        class OrrisDatabaseManager {
            public function testConnection() { return false; }
            public function isInstalled() { return false; }
            public function getServiceCount() { return 0; }
            public function install() { return ['success' => false, 'message' => 'Database manager not available']; }
        }
    }
    
    if (!class_exists('OrrisDatabase')) {
        class OrrisDatabase {
            public function getActiveServiceCount($module) { return 0; }
        }
    }
    
    try {
        // Initialize and dispatch controller
        $controllerClass = 'WHMCS\\Module\\Addon\\OrrisAdmin\\Admin\\Controller';
        
        if (class_exists($controllerClass)) {
            // Create controller instance
            $controller = new $controllerClass($vars);
            
            // Get action from request
            $action = $_GET['action'] ?? 'index';
            
            // Dispatch the request and output result
            echo $controller->dispatch($action, $vars);
        } else {
            // Fallback error display if controller not found
            echo '<div class="alert alert-danger">
                <h4>Controller Not Found</h4>
                <p>The ORRISM Admin Controller could not be loaded.</p>
                <p>Please ensure the following file exists:</p>
                <code>' . __DIR__ . '/lib/Admin/Controller.php</code>';
            
            if (!empty($loadErrors)) {
                echo '<hr><h5>Dependency Load Errors:</h5><ul>';
                foreach ($loadErrors as $error) {
                    echo '<li>' . htmlspecialchars($error) . '</li>';
                }
                echo '</ul>';
            }
            
            echo '</div>';
            
            // Log the error
            safeLogModuleCall(
                'orrism_admin',
                'controller_error',
                ['expected_class' => $controllerClass],
                'Controller class not found',
                json_encode($loadErrors)
            );
        }
        
    } catch (Exception $e) {
        // Display error message
        echo '<div class="alert alert-danger">
            <h4>Error</h4>
            <p>An error occurred while loading the ORRISM Administration module:</p>
            <p><strong>' . htmlspecialchars($e->getMessage()) . '</strong></p>';
        
        // Show stack trace in debug mode
        if (isset($_GET['debug'])) {
            echo '<hr><h5>Stack Trace:</h5>
                <pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
        }
        
        if (!empty($loadErrors)) {
            echo '<hr><h5>Dependency Load Errors:</h5><ul>';
            foreach ($loadErrors as $error) {
                echo '<li>' . htmlspecialchars($error) . '</li>';
            }
            echo '</ul>';
        }
        
        echo '</div>';

        // Log the error
        safeLogModuleCall(
            'orrism_admin',
            'output_error',
            ['action' => $_GET['action'] ?? 'index'],
            $e->getMessage(),
            $e->getTraceAsString()
        );
    }
}