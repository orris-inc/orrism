<?php
/**
 * ORRISM Administration Module Hooks
 * Handles system-wide operations like cron jobs, traffic monitoring, etc.
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
    'hook_logic.php' => $serverModulePath . '/includes/hook_logic.php',
    'helper.php' => $serverModulePath . '/helper.php'
];

foreach ($dependencies as $name => $path) {
    if (file_exists($path)) {
        require_once $path;
    } else {
        error_log("ORRISM Hooks: Failed to load dependency: $name at $path");
    }
}

/**
 * AfterCronJob hook - handles routine system maintenance
 */
add_hook('AfterCronJob', 10, function ($vars) {
    // Check if ORRISM admin module is active
    $moduleStatus = Capsule::table('tbladdonmodules')
        ->where('module', 'orrism_admin')
        ->where('setting', 'auto_sync')
        ->where('value', 'on')
        ->exists();
        
    if (!$moduleStatus) {
        return; // Module not active or auto sync disabled
    }
    
    $adminUser = 'system'; // System user for automated tasks

    try {
        // Traffic check logic
        if (function_exists('orrism_perform_after_cron_traffic_checks')) {
            orrism_perform_after_cron_traffic_checks($adminUser);
        }
        
        OrrisHelper::log('info', 'AfterCronJob completed successfully');
        
    } catch (Exception $e) {
        OrrisHelper::log('error', 'AfterCronJob failed', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
    }
});

/**
 * DailyCronJob hook - handles daily maintenance tasks
 */
add_hook('DailyCronJob', 1, function ($vars) {
    // Check if ORRISM admin module is active
    $moduleStatus = Capsule::table('tbladdonmodules')
        ->where('module', 'orrism_admin')
        ->where('setting', 'auto_sync')
        ->where('value', 'on')
        ->exists();
        
    if (!$moduleStatus) {
        return; // Module not active
    }
    
    $adminUser = 'system';
    $orderOverdueDays = 3; // Configurable
    $invoiceOverdueDays = 3; // Configurable
    $allowableServiceOverdueDays = 1; // Configurable

    try {
        // Cancel overdue pending orders
        if (function_exists('orrism_cancel_overdue_pending_orders')) {
            orrism_cancel_overdue_pending_orders($adminUser, $orderOverdueDays);
        }

        // Cancel overdue invoices
        if (function_exists('orrism_cancel_overdue_invoices')) {
            orrism_cancel_overdue_invoices($adminUser, $invoiceOverdueDays);
        }

        // Perform billing day traffic reset
        if (function_exists('orrism_perform_billing_day_traffic_reset')) {
            orrism_perform_billing_day_traffic_reset($adminUser);
        }

        // Suspend overdue ORRISM services
        if (function_exists('orrism_suspendOverdueProducts')) {
            orrism_suspendOverdueProducts($adminUser, $allowableServiceOverdueDays);
        }
        
        OrrisHelper::log('info', 'DailyCronJob completed successfully');
        
    } catch (Exception $e) {
        OrrisHelper::log('error', 'DailyCronJob failed', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
    }
});

/**
 * Admin menu hook to add ORRISM management links
 */
add_hook('AdminAreaHeaderOutput', 1, function($vars) {
    // Check if current admin user has permission and module is active
    $moduleActive = Capsule::table('tbladdonmodules')
        ->where('module', 'orrism_admin')
        ->exists();
        
    if (!$moduleActive) {
        return '';
    }
    
    // Add custom CSS for ORRISM admin
    return '
    <style>
    .orrism-admin-dashboard .nav-tabs a {
        margin-right: 5px;
        margin-bottom: 10px;
    }
    .orrism-admin-dashboard .panel {
        margin-bottom: 20px;
    }
    .orrism-status-success { color: #5cb85c; }
    .orrism-status-error { color: #d9534f; }
    .orrism-status-warning { color: #f0ad4e; }
    </style>';
});

/**
 * Hook to track addon module configuration changes
 */
add_hook('AddonConfigSave', 1, function($vars) {
    if ($vars['module'] !== 'orrism_admin') {
        return;
    }
    
    try {
        OrrisHelper::log('info', 'ORRISM admin module configuration updated', [
            'admin_id' => $_SESSION['adminid'] ?? 'unknown',
            'settings' => array_keys($vars)
        ]);
        
    } catch (Exception $e) {
        OrrisHelper::log('error', 'Failed to log configuration change', [
            'error' => $e->getMessage()
        ]);
    }
});