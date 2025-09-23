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
                safeLogModuleCall('orrism_admin', 'dependency_load', $name, $e->getMessage());
            }
        } else {
            $loadErrors[] = "File not found: $name at $path";
            safeLogModuleCall('orrism_admin', 'dependency_missing', $name, $path);
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
            public function getUserCount() { return 0; }
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
            safeLogModuleCall('orrism_admin', 'controller_error', 'Controller class not found', $controllerClass);
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
        safeLogModuleCall('orrism_admin', 'output_error', $e->getMessage(), $e->getTraceAsString());
    }
}