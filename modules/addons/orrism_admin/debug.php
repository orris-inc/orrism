<?php
/**
 * ORRISM Debug Helper
 * Helps diagnose module loading issues
 */

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

/**
 * Debug ORRISM module dependencies
 * 
 * @return array Debug information
 */
function orrism_debug_dependencies() {
    $debug = [
        'paths' => [],
        'classes' => [],
        'files' => [],
        'environment' => []
    ];
    
    // Check paths
    $serverModulePath = __DIR__ . '/../../servers/orrism';
    $debug['paths']['server_module_relative'] = $serverModulePath;
    $debug['paths']['server_module_exists'] = is_dir($serverModulePath);
    
    $whmcsRoot = dirname(__DIR__, 3);
    $serverModulePathAlt = $whmcsRoot . '/modules/servers/orrism';
    $debug['paths']['server_module_absolute'] = $serverModulePathAlt;
    $debug['paths']['server_module_absolute_exists'] = is_dir($serverModulePathAlt);
    
    // Check required files
    $dependencies = [
        'database_manager.php' => $serverModulePath . '/includes/database_manager.php',
        'whmcs_database.php' => $serverModulePath . '/includes/whmcs_database.php',
        'helper.php' => $serverModulePath . '/helper.php'
    ];
    
    foreach ($dependencies as $name => $path) {
        $debug['files'][$name] = [
            'path' => $path,
            'exists' => file_exists($path),
            'readable' => file_exists($path) ? is_readable($path) : false
        ];
    }
    
    // Check classes
    $expectedClasses = ['OrrisDatabaseManager', 'OrrisDatabase', 'OrrisHelper'];
    foreach ($expectedClasses as $class) {
        $debug['classes'][$class] = class_exists($class);
    }
    
    // Environment info
    $debug['environment'] = [
        'php_version' => PHP_VERSION,
        'whmcs_dir' => $whmcsRoot,
        'current_dir' => __DIR__,
        'include_path' => get_include_path()
    ];
    
    return $debug;
}

/**
 * Format debug output as HTML
 * 
 * @return string HTML formatted debug info
 */
function orrism_debug_output_html() {
    $debug = orrism_debug_dependencies();
    
    $html = '<div class="orrism-panel">';
    $html .= '<div class="orrism-panel-heading">';
    $html .= '<strong>ORRISM Debug Information</strong> ';
    $html .= '<small style="color: #666;">(This section helps diagnose module loading issues)</small>';
    $html .= '</div>';
    $html .= '<div class="orrism-panel-body">';
    
    // Show load errors if any
    global $orrism_load_errors;
    if (!empty($orrism_load_errors)) {
        $html .= '<div class="orrism-alert orrism-alert-warning">';
        $html .= '<h4>⚠️ Dependency Loading Issues</h4>';
        $html .= '<ul>';
        foreach ($orrism_load_errors as $error) {
            $html .= '<li>' . htmlspecialchars($error) . '</li>';
        }
        $html .= '</ul>';
        $html .= '</div>';
    }
    
    // Paths
    $html .= '<h4>Paths</h4>';
    $html .= '<ul>';
    foreach ($debug['paths'] as $key => $value) {
        $status = is_bool($value) ? ($value ? 'YES' : 'NO') : $value;
        $html .= "<li><strong>$key:</strong> $status</li>";
    }
    $html .= '</ul>';
    
    // Files
    $html .= '<h4>Required Files</h4>';
    $html .= '<ul>';
    foreach ($debug['files'] as $name => $info) {
        $status = $info['exists'] ? '✅' : '❌';
        $html .= "<li>$status <strong>$name:</strong> {$info['path']}</li>";
    }
    $html .= '</ul>';
    
    // Classes
    $html .= '<h4>Required Classes</h4>';
    $html .= '<ul>';
    foreach ($debug['classes'] as $class => $exists) {
        $status = $exists ? '✅' : '❌';
        $html .= "<li>$status <strong>$class</strong></li>";
    }
    $html .= '</ul>';
    
    // Environment
    $html .= '<h4>Environment</h4>';
    $html .= '<ul>';
    foreach ($debug['environment'] as $key => $value) {
        $html .= "<li><strong>$key:</strong> $value</li>";
    }
    $html .= '</ul>';
    
    // Add quick actions
    $html .= '<div style="margin-top: 20px; padding-top: 15px; border-top: 1px solid #eee;">';
    $html .= '<h4>Quick Actions</h4>';
    $html .= '<div class="orrism-btn-group">';
    $html .= '<a href="?module=orrism_admin&action=dashboard" class="btn btn-default btn-sm">Reload Dashboard</a>';
    $html .= '<a href="?module=orrism_admin&action=dashboard&debug=1" class="btn btn-warning btn-sm">Enable Debug Mode</a>';
    $html .= '<a href="?module=orrism_admin&action=database" class="btn btn-info btn-sm">Database Setup</a>';
    $html .= '</div>';
    $html .= '</div>';
    
    $html .= '</div></div>';
    
    return $html;
}