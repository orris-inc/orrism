<?php
/**
 * ORRISM Provisioning Module for WHMCS
 *
 * @package    WHMCS
 * @author     ORRISM Development Team
 * @copyright  Copyright (c) 2024
 * @version    2.0
 * @link       https://github.com/your-org/orrism-whmcs-module
 */

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

// Use WHMCS namespaces
use WHMCS\Database\Capsule;
use WHMCS\Module\Server;

// Load required dependencies with error handling
$dependencies = [
    'database_manager.php' => __DIR__ . '/includes/database_manager.php',
    'whmcs_database.php' => __DIR__ . '/includes/whmcs_database.php',
    'uuid.php' => __DIR__ . '/lib/uuid.php',
    'helper.php' => __DIR__ . '/helper.php'
];

foreach ($dependencies as $name => $path) {
    if (file_exists($path)) {
        require_once $path;
    } else {
        logModuleCall('orrism', 'dependency_load_failed', [], "Failed to load: $name at $path");
    }
}

/**
 * Module metadata
 * 
 * @return array
 */
function orrism_MetaData()
{
    return [
        'DisplayName' => 'ORRISM ShadowSocks Manager',
        'APIVersion' => '1.1',
        'RequiresServer' => true,
        'DefaultNonSSLPort' => '80',
        'DefaultSSLPort' => '443',
        'ServiceSingleSignOnLabel' => 'Login to Control Panel',
        'AdminSingleSignOnLabel' => 'Login to Admin Panel'
    ];
}

/**
 * Module configuration options
 * 
 * @return array
 */
function orrism_ConfigOptions()
{
    return [
        'database' => [
            'Type' => 'text',
            'Size' => '25',
            'Default' => 'shadowsocks',
            'Description' => 'Database name for ShadowSocks data'
        ],
        'reset_strategy' => [
            'Type' => 'dropdown',
            'Options' => [
                '0' => 'No Reset',
                '1' => 'Reset on Order Date',
                '2' => 'Reset on Month Start',
                '3' => 'Reset on Month End'
            ],
            'Default' => '1',
            'Description' => 'Traffic reset strategy'
        ],
        'node_list' => [
            'Type' => 'textarea',
            'Rows' => '3',
            'Cols' => '50',
            'Description' => 'Available nodes (comma separated node IDs)'
        ],
        'bandwidth' => [
            'Type' => 'text',
            'Size' => '25',
            'Default' => '100',
            'Description' => 'Monthly bandwidth limit (GB)'
        ],
        'manual_reset' => [
            'Type' => 'yesno',
            'Description' => 'Allow manual bandwidth reset by user'
        ],
        'reset_cost' => [
            'Type' => 'text',
            'Size' => '25',
            'Default' => '0',
            'Description' => 'Cost percentage for manual reset (0-100)'
        ],
        'node_group' => [
            'Type' => 'text',
            'Size' => '25',
            'Default' => '1',
            'Description' => 'Node group ID'
        ]
    ];
}

/**
 * Test connection to WHMCS database
 *
 * @param array $params Module parameters
 * @return array
 */
function orrism_TestConnection(array $params)
{
    try {
        $dbManager = orrism_db_manager();
        $result = $dbManager->testConnection();
        
        if (!$result['success']) {
            return ['success' => false, 'error' => $result['message']];
        }
        
        // Check if ORRISM tables are installed
        if (!$dbManager->isInstalled()) {
            return [
                'success' => false, 
                'error' => 'ORRISM database tables not installed. Please run the setup wizard.'
            ];
        }
        
        return ['success' => true, 'error' => ''];
        
    } catch (Exception $e) {
        logModuleCall('orrism', __FUNCTION__, $params, $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Create account
 *
 * @param array $params Module parameters
 * @return string "success" or error message
 */
function orrism_CreateAccount(array $params)
{
    try {
        logModuleCall('orrism', __FUNCTION__, $params, '', '');
        
        $db = orrism_db();
        $result = $db->createUser($params);
        
        if (!$result['success']) {
            return 'Error: ' . $result['message'];
        }
        
        // Update WHMCS service with credentials
        $username = $params['username'] ?: 'user' . $params['serviceid'];
        $password = $params['password'] ?: orrism_generate_password(12);
        $domain = $params['domain'] ?: $params['customfields']['domain'] ?: '';
        
        Capsule::table('tblhosting')
            ->where('id', $params['serviceid'])
            ->update([
                'username' => $username,
                'password' => encrypt($password),
                'domain' => $domain
            ]);
        
        return 'success';
        
    } catch (Exception $e) {
        logModuleCall('orrism', __FUNCTION__, $params, $e->getMessage());
        return 'Error: ' . $e->getMessage();
    }
}

/**
 * Suspend account
 *
 * @param array $params Module parameters
 * @return string "success" or error message
 */
function orrism_SuspendAccount(array $params)
{
    try {
        logModuleCall('orrism', __FUNCTION__, $params, '', '');
        
        $db = orrism_db();
        $success = $db->updateUserStatus($params['serviceid'], 'suspended');
        
        if (!$success) {
            throw new Exception('User not found in database');
        }
        
        return 'success';
        
    } catch (Exception $e) {
        logModuleCall('orrism', __FUNCTION__, $params, $e->getMessage());
        return 'Error: ' . $e->getMessage();
    }
}

/**
 * Unsuspend account
 *
 * @param array $params Module parameters
 * @return string "success" or error message
 */
function orrism_UnsuspendAccount(array $params)
{
    try {
        logModuleCall('orrism', __FUNCTION__, $params, '', '');
        
        $db = orrism_db();
        $success = $db->updateUserStatus($params['serviceid'], 'active');
        
        if (!$success) {
            throw new Exception('User not found in database');
        }
        
        return 'success';
        
    } catch (Exception $e) {
        logModuleCall('orrism', __FUNCTION__, $params, $e->getMessage());
        return 'Error: ' . $e->getMessage();
    }
}

/**
 * Terminate account
 *
 * @param array $params Module parameters
 * @return string "success" or error message
 */
function orrism_TerminateAccount(array $params)
{
    try {
        logModuleCall('orrism', __FUNCTION__, $params, '', '');
        
        $db = orrism_db();
        $success = $db->deleteUser($params['serviceid']);
        
        if (!$success) {
            // User might already be deleted, consider it success
            logModuleCall('orrism', __FUNCTION__, $params, 'User not found, might be already deleted');
        }
        
        return 'success';
        
    } catch (Exception $e) {
        logModuleCall('orrism', __FUNCTION__, $params, $e->getMessage());
        return 'Error: ' . $e->getMessage();
    }
}

/**
 * Change password
 *
 * @param array $params Module parameters
 * @return string "success" or error message
 */
function orrism_ChangePassword(array $params)
{
    try {
        logModuleCall('orrism', __FUNCTION__, $params, '', '');
        
        $serviceid = $params['serviceid'];
        $password = $params['password'];
        
        // Update password hash in ORRISM database
        $updated = Capsule::table('mod_orrism_users')
            ->where('service_id', $serviceid)
            ->update([
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'updated_at' => now()
            ]);
            
        if (!$updated) {
            throw new Exception('User not found in database');
        }
        
        return 'success';
        
    } catch (Exception $e) {
        logModuleCall('orrism', __FUNCTION__, $params, $e->getMessage());
        return 'Error: ' . $e->getMessage();
    }
}

/**
 * Change package
 *
 * @param array $params Module parameters
 * @return string "success" or error message
 */
function orrism_ChangePackage(array $params)
{
    try {
        logModuleCall('orrism', __FUNCTION__, $params, '', '');
        
        $db = orrism_db();
        $success = $db->updateUserPackage($params['serviceid'], $params);
        
        if (!$success) {
            throw new Exception('User not found in database');
        }
        
        return 'success';
        
    } catch (Exception $e) {
        logModuleCall('orrism', __FUNCTION__, $params, $e->getMessage());
        return 'Error: ' . $e->getMessage();
    }
}

/**
 * Renew account
 *
 * @param array $params Module parameters
 * @return string "success" or error message
 */
function orrism_Renew(array $params)
{
    try {
        logModuleCall('orrism', __FUNCTION__, $params, '', '');
        
        $serviceid = $params['serviceid'];
        $resetStrategy = $params['configoption2'] ?: 0;
        
        // Handle traffic reset based on strategy
        if ($resetStrategy > 0) {
            $db = orrism_db();
            $success = $db->resetUserTraffic($serviceid);
            
            if (!$success) {
                logModuleCall('orrism', __FUNCTION__, $params, 'Failed to reset traffic for renewal');
            }
        }
        
        return 'success';
        
    } catch (Exception $e) {
        logModuleCall('orrism', __FUNCTION__, $params, $e->getMessage());
        return 'Error: ' . $e->getMessage();
    }
}

/**
 * Admin custom buttons
 *
 * @return array
 */
function orrism_AdminCustomButtonArray()
{
    return [
        'Reset Traffic' => 'ResetTraffic',
        'Reset UUID' => 'ResetUUID',
        'View Usage' => 'ViewUsage'
    ];
}

/**
 * Reset traffic admin function
 *
 * @param array $params Module parameters
 * @return string "success" or error message
 */
function orrism_ResetTraffic(array $params)
{
    try {
        logModuleCall('orrism', __FUNCTION__, $params, '', '');
        
        $db = orrism_db();
        $success = $db->resetUserTraffic($params['serviceid']);
        
        if (!$success) {
            throw new Exception('User not found in database');
        }
        
        return 'success';
        
    } catch (Exception $e) {
        logModuleCall('orrism', __FUNCTION__, $params, $e->getMessage());
        return 'Error: ' . $e->getMessage();
    }
}

/**
 * Reset UUID admin function
 *
 * @param array $params Module parameters
 * @return string "success" or error message
 */
function orrism_ResetUUID(array $params)
{
    try {
        logModuleCall('orrism', __FUNCTION__, $params, '', '');
        
        $db = orrism_db();
        $result = $db->regenerateUUID($params['serviceid']);
        
        if (!$result['success']) {
            throw new Exception($result['message']);
        }
        
        return 'success';
        
    } catch (Exception $e) {
        logModuleCall('orrism', __FUNCTION__, $params, $e->getMessage());
        return 'Error: ' . $e->getMessage();
    }
}

/**
 * View usage admin function
 *
 * @param array $params Module parameters
 * @return array
 */
function orrism_ViewUsage(array $params)
{
    try {
        $db = orrism_db();
        $usage = $db->getUserUsage($params['serviceid']);
        
        if (empty($usage)) {
            return ['error' => 'User not found'];
        }
        
        return [
            'Total Bandwidth' => $usage['total_bandwidth'] . ' GB',
            'Used Bandwidth' => $usage['used_bandwidth'] . ' GB',
            'Remaining Bandwidth' => $usage['remaining_bandwidth'] . ' GB',
            'Upload' => $usage['upload_gb'] . ' GB',
            'Download' => $usage['download_gb'] . ' GB',
            'Usage Percentage' => $usage['usage_percent'] . '%',
            'Status' => ucfirst($usage['status']),
            'Last Reset' => $usage['last_reset'] ?: 'Never'
        ];
        
    } catch (Exception $e) {
        logModuleCall('orrism', __FUNCTION__, $params, $e->getMessage());
        return ['error' => $e->getMessage()];
    }
}


/**
 * Client area output
 *
 * @param array $params Module parameters
 * @return array
 */
function orrism_ClientArea(array $params)
{
    try {
        $serviceid = $params['serviceid'];
        $db = orrism_db();
        
        // Get user data
        $user = $db->getUser($serviceid);
        if (!$user) {
            return [
                'templatefile' => 'error',
                'vars' => ['errormessage' => 'Account not found']
            ];
        }
        
        // Get usage statistics
        $usage = $db->getUserUsage($serviceid);
        
        // Get nodes for user's group
        $nodes = $db->getNodesForGroup($user->node_group_id);
        
        // Generate subscription URL
        $subscriptionUrl = orrism_generate_subscription_url($params, $user->uuid);
        
        return [
            'templatefile' => 'clientarea',
            'vars' => [
                'serviceid' => $serviceid,
                'uuid' => $user->uuid,
                'email' => $user->email,
                'nodes' => $nodes,
                'totalBandwidth' => $usage['total_bandwidth'],
                'usedBandwidth' => $usage['used_bandwidth'],
                'remainingBandwidth' => $usage['remaining_bandwidth'],
                'usagePercent' => $usage['usage_percent'],
                'uploadGB' => $usage['upload_gb'],
                'downloadGB' => $usage['download_gb'],
                'subscriptionUrl' => $subscriptionUrl,
                'allowReset' => $params['configoption5'] == 'on',
                'resetCost' => $params['configoption6'] ?: 0,
                'status' => $user->status,
                'lastReset' => $user->last_reset_at
            ]
        ];
        
    } catch (Exception $e) {
        logModuleCall('orrism', __FUNCTION__, $params, $e->getMessage());
        return [
            'templatefile' => 'error',
            'vars' => ['errormessage' => $e->getMessage()]
        ];
    }
}

/**
 * Client area custom button array
 *
 * @return array
 */
function orrism_ClientAreaCustomButtonArray()
{
    return [
        'Reset Traffic' => 'ClientResetTraffic'
    ];
}

/**
 * Client reset traffic function
 *
 * @param array $params Module parameters
 * @return string
 */
function orrism_ClientResetTraffic(array $params)
{
    try {
        // Check if manual reset is allowed
        if ($params['configoption5'] != 'on') {
            return 'Manual traffic reset is not allowed';
        }
        
        $db = orrism_db();
        $success = $db->resetUserTraffic($params['serviceid']);
        
        if (!$success) {
            throw new Exception('User not found in database');
        }
        
        // Check if there's a reset cost
        $resetCost = $params['configoption6'] ?: 0;
        if ($resetCost > 0) {
            // Create invoice for reset cost
            $amount = $params['amount'] * ($resetCost / 100);
            // TODO: Implement invoice creation logic
        }
        
        return 'success';
        
    } catch (Exception $e) {
        logModuleCall('orrism', __FUNCTION__, $params, $e->getMessage());
        return 'Error: ' . $e->getMessage();
    }
}

/**
 * Admin services tab fields
 *
 * @param array $params Module parameters
 * @return array
 */
function orrism_AdminServicesTabFields(array $params)
{
    try {
        $db = orrism_db();
        $user = $db->getUser($params['serviceid']);
        
        if (!$user) {
            return [];
        }
        
        $usage = $db->getUserUsage($params['serviceid']);
        
        return [
            'UUID' => $user->uuid,
            'Email' => $user->email,
            'Total Bandwidth' => $usage['total_bandwidth'] . ' GB',
            'Used Bandwidth' => $usage['used_bandwidth'] . ' GB',
            'Upload' => $usage['upload_gb'] . ' GB',
            'Download' => $usage['download_gb'] . ' GB',
            'Usage Percentage' => $usage['usage_percent'] . '%',
            'Status' => ucfirst($user->status),
            'Node Group' => $user->node_group_id,
            'Created' => $user->created_at,
            'Updated' => $user->updated_at,
            'Last Reset' => $user->last_reset_at ?: 'Never'
        ];
        
    } catch (Exception $e) {
        logModuleCall('orrism', __FUNCTION__, $params, $e->getMessage());
        return ['Error' => $e->getMessage()];
    }
}

/**
 * Login link
 *
 * @param array $params Module parameters
 * @return string
 */
function orrism_LoginLink(array $params)
{
    $serverHost = $params['serverhostname'] ?: $params['serverip'];
    $serverPort = $params['serversecure'] ? $params['configoption3'] : $params['configoption2'];
    $protocol = $params['serversecure'] ? 'https' : 'http';
    
    return "{$protocol}://{$serverHost}:{$serverPort}/admin";
}

/**
 * Service single sign-on
 *
 * @param array $params Module parameters
 * @return array
 */
function orrism_ServiceSingleSignOn(array $params)
{
    try {
        $serviceid = $params['serviceid'];
        $db = orrism_get_database($params);
        
        // Get user data
        $user = Capsule::table($db . '.user')
            ->where('pid', $serviceid)
            ->first();
            
        if (!$user) {
            return ['success' => false, 'errorMsg' => 'User not found'];
        }
        
        // Generate SSO token
        $token = orrism_generate_sso_token($params, $user);
        
        $serverHost = $params['serverhostname'] ?: $params['serverip'];
        $protocol = $params['serversecure'] ? 'https' : 'http';
        
        return [
            'success' => true,
            'redirectTo' => "{$protocol}://{$serverHost}/sso?token={$token}"
        ];
        
    } catch (Exception $e) {
        logModuleCall('orrism', __FUNCTION__, $params, $e->getMessage());
        return ['success' => false, 'errorMsg' => $e->getMessage()];
    }
}

/**
 * Admin single sign-on
 *
 * @param array $params Module parameters
 * @return array
 */
function orrism_AdminSingleSignOn(array $params)
{
    try {
        // Generate admin SSO token
        $token = orrism_generate_admin_sso_token($params);
        
        $serverHost = $params['serverhostname'] ?: $params['serverip'];
        $protocol = $params['serversecure'] ? 'https' : 'http';
        
        return [
            'success' => true,
            'redirectTo' => "{$protocol}://{$serverHost}/admin/sso?token={$token}"
        ];
        
    } catch (Exception $e) {
        logModuleCall('orrism', __FUNCTION__, $params, $e->getMessage());
        return ['success' => false, 'errorMsg' => $e->getMessage()];
    }
}

/**
 * Usage update
 *
 * @param array $params Module parameters
 * @return array
 */
function orrism_UsageUpdate(array $params)
{
    try {
        $db = orrism_db();
        $usage = $db->getUserUsage($params['serviceid']);
        
        if (empty($usage)) {
            return [];
        }
        
        $totalMB = round($usage['total_bandwidth'] * 1024, 2);
        $usedMB = round($usage['used_bandwidth'] * 1024, 2);
        
        return [
            'diskusage' => $usedMB,
            'disklimit' => $totalMB,
            'diskpercent' => $usage['usage_percent'],
            'lastupdate' => date('Y-m-d H:i:s')
        ];
        
    } catch (Exception $e) {
        logModuleCall('orrism', __FUNCTION__, $params, $e->getMessage());
        return [];
    }
}

// Legacy database helper function removed - now using WHMCS database directly

/**
 * Helper function to generate password
 *
 * @param int $length Password length
 * @return string
 */
function orrism_generate_password($length = 12)
{
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $password;
}

/**
 * Helper function to save custom field
 *
 * @param int $serviceid Service ID
 * @param string $fieldname Field name
 * @param string $value Field value
 * @return void
 */
function orrism_save_custom_field($serviceid, $fieldname, $value)
{
    try {
        // Get custom field ID
        $field = Capsule::table('tblcustomfields')
            ->where('type', 'product')
            ->where('fieldname', $fieldname)
            ->first();
            
        if (!$field) {
            return;
        }
        
        // Update or insert custom field value
        Capsule::table('tblcustomfieldsvalues')
            ->updateOrInsert(
                ['fieldid' => $field->id, 'relid' => $serviceid],
                ['value' => $value]
            );
            
    } catch (Exception $e) {
        logModuleCall('orrism', __FUNCTION__, ['serviceid' => $serviceid, 'field' => $fieldname], $e->getMessage());
    }
}

/**
 * Generate subscription URL
 *
 * @param array $params Module parameters
 * @param string $uuid User UUID
 * @return string
 */
function orrism_generate_subscription_url(array $params, $uuid)
{
    $serverHost = $params['serverhostname'] ?: $params['serverip'];
    $protocol = $params['serversecure'] ? 'https' : 'http';
    return "{$protocol}://{$serverHost}/subscribe/{$uuid}";
}

/**
 * Generate SSO token for user
 *
 * @param array $params Module parameters
 * @param object $user User data
 * @return string
 */
function orrism_generate_sso_token(array $params, $user)
{
    $data = [
        'user_id' => $user->id,
        'service_id' => $params['serviceid'],
        'timestamp' => time(),
        'expires' => time() + 300 // 5 minutes
    ];
    return base64_encode(json_encode($data));
}

/**
 * Generate SSO token for admin
 *
 * @param array $params Module parameters
 * @return string
 */
function orrism_generate_admin_sso_token(array $params)
{
    $data = [
        'admin' => true,
        'timestamp' => time(),
        'expires' => time() + 300 // 5 minutes
    ];
    return base64_encode(json_encode($data));
}