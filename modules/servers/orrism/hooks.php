<?php
/**
 * ORRISM Server Module Hooks
 * Handles service lifecycle events only
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

// Load module dependencies
require_once __DIR__ . '/includes/database_manager.php';
require_once __DIR__ . '/includes/whmcs_database.php';
require_once __DIR__ . '/helper.php';

/**
 * Hook to handle service creation
 */
add_hook('AfterModuleCreate', 1, function($vars) {
    if ($vars['producttype'] !== 'server' || $vars['servertype'] !== 'orrism') {
        return;
    }
    
    try {
        OrrisHelper::log('info', 'Service created', [
            'service_id' => $vars['serviceid'],
            'domain' => $vars['domain'],
            'username' => $vars['username']
        ]);
        
        // Additional service creation logic can be added here
        
    } catch (Exception $e) {
        OrrisHelper::log('error', 'Service creation hook failed', [
            'service_id' => $vars['serviceid'],
            'error' => $e->getMessage()
        ]);
    }
});

/**
 * Hook to handle service suspension
 */
add_hook('AfterModuleSuspend', 1, function($vars) {
    if ($vars['producttype'] !== 'server' || $vars['servertype'] !== 'orrism') {
        return;
    }
    
    try {
        OrrisHelper::log('info', 'Service suspended', [
            'service_id' => $vars['serviceid'],
            'domain' => $vars['domain']
        ]);
        
    } catch (Exception $e) {
        OrrisHelper::log('error', 'Service suspension hook failed', [
            'service_id' => $vars['serviceid'],
            'error' => $e->getMessage()
        ]);
    }
});

/**
 * Hook to handle service unsuspension
 */
add_hook('AfterModuleUnsuspend', 1, function($vars) {
    if ($vars['producttype'] !== 'server' || $vars['servertype'] !== 'orrism') {
        return;
    }
    
    try {
        OrrisHelper::log('info', 'Service unsuspended', [
            'service_id' => $vars['serviceid'],
            'domain' => $vars['domain']
        ]);
        
    } catch (Exception $e) {
        OrrisHelper::log('error', 'Service unsuspension hook failed', [
            'service_id' => $vars['serviceid'],
            'error' => $e->getMessage()
        ]);
    }
});

/**
 * Hook to handle service termination
 */
add_hook('AfterModuleTerminate', 1, function($vars) {
    if ($vars['producttype'] !== 'server' || $vars['servertype'] !== 'orrism') {
        return;
    }
    
    try {
        OrrisHelper::log('info', 'Service terminated', [
            'service_id' => $vars['serviceid'],
            'domain' => $vars['domain']
        ]);
        
    } catch (Exception $e) {
        OrrisHelper::log('error', 'Service termination hook failed', [
            'service_id' => $vars['serviceid'],
            'error' => $e->getMessage()
        ]);
    }
});

/**
 * Hook to handle service password changes
 */
add_hook('AfterModuleChangePassword', 1, function($vars) {
    if ($vars['producttype'] !== 'server' || $vars['servertype'] !== 'orrism') {
        return;
    }
    
    try {
        OrrisHelper::log('info', 'Service password changed', [
            'service_id' => $vars['serviceid'],
            'domain' => $vars['domain']
        ]);
        
    } catch (Exception $e) {
        OrrisHelper::log('error', 'Service password change hook failed', [
            'service_id' => $vars['serviceid'],
            'error' => $e->getMessage()
        ]);
    }
});


