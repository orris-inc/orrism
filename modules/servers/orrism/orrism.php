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
 * Defines basic module configuration and capabilities.
 * Called by WHMCS to determine module features and requirements.
 *
 * @return array Module metadata including:
 *   - DisplayName: string Module display name in WHMCS
 *   - APIVersion: string WHMCS API version compatibility
 *   - RequiresServer: bool Whether module requires server configuration
 *   - DefaultNonSSLPort: string Default HTTP port
 *   - DefaultSSLPort: string Default HTTPS port
 *   - ServiceSingleSignOnLabel: string Client SSO button label
 *   - AdminSingleSignOnLabel: string Admin SSO button label
 */
function orrism_MetaData()
{
    return [
        'DisplayName' => 'ORRISM Manager',
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
 * Defines product configuration options available when setting up ORRISM products.
 * Called by WHMCS to display configuration fields in product setup.
 *
 * Configuration options are accessed in code as:
 *   - $params['configoption1'] = Node Group ID
 *   - $params['configoption2'] = Monthly Bandwidth (GB)
 *   - $params['configoption3'] = Traffic Reset Strategy
 *   - $params['configoption4'] = Reset Day of Month
 *   - $params['configoption5'] = Enable Traffic Reset
 *   - $params['configoption6'] = Max Concurrent Devices
 *   - $params['configoption7'] = Allow Manual Reset
 *   - $params['configoption8'] = Manual Reset Cost (%)
 *   - $params['configoption9'] = Service Speed Limit (Mbps)
 *   - $params['configoption10'] = Enable Usage Logging
 *
 * @return array Array of configuration options, each with:
 *   - Type: string Field type (text, dropdown, textarea, yesno, etc.)
 *   - Options: array Available options (for dropdown type)
 *   - Default: mixed Default value
 *   - Description: string Help text displayed to admin
 */
function orrism_ConfigOptions()
{
    return [
        // Config Option 1: Node Group
        'Node Group ID' => [
            'Type' => 'text',
            'Size' => '10',
            'Default' => '1',
            'Description' => 'Node group ID (from node_groups table)',
            'SimpleMode' => true
        ],

        // Config Option 2: Bandwidth Limit
        'Monthly Bandwidth (GB)' => [
            'Type' => 'text',
            'Size' => '10',
            'Default' => '100',
            'Description' => 'Monthly bandwidth limit in GB (0 = unlimited)',
            'SimpleMode' => true
        ],

        // Config Option 3: Traffic Reset Strategy
        'Traffic Reset Strategy' => [
            'Type' => 'dropdown',
            'Options' => [
                '0' => 'No Automatic Reset',
                '1' => 'Reset on Order Date (Anniversary)',
                '2' => 'Reset on First Day of Month',
                '3' => 'Reset on Last Day of Month',
                '4' => 'Reset on Custom Day of Month'
            ],
            'Default' => '1',
            'Description' => 'How traffic usage should be reset',
            'SimpleMode' => true
        ],

        // Config Option 4: Reset Day (for Custom strategy)
        'Reset Day of Month' => [
            'Type' => 'text',
            'Size' => '5',
            'Default' => '1',
            'Description' => 'Day of month for reset (1-28) - Only used if strategy is "Custom Day"',
            'SimpleMode' => false
        ],

        // Config Option 5: Enable Traffic Reset
        'Enable Traffic Reset' => [
            'Type' => 'yesno',
            'Default' => 'yes',
            'Description' => 'Enable automatic traffic reset (disable for pay-as-you-go plans)',
            'SimpleMode' => true
        ],

        // Config Option 6: Max Devices
        'Max Concurrent Devices' => [
            'Type' => 'text',
            'Size' => '5',
            'Default' => '3',
            'Description' => 'Maximum number of devices that can connect simultaneously',
            'SimpleMode' => true
        ],

        // Config Option 7: Allow Manual Reset
        'Allow Manual Reset' => [
            'Type' => 'yesno',
            'Default' => '',
            'Description' => 'Allow users to manually reset traffic (with optional fee)',
            'SimpleMode' => false
        ],

        // Config Option 8: Manual Reset Cost
        'Manual Reset Cost (%)' => [
            'Type' => 'text',
            'Size' => '5',
            'Default' => '0',
            'Description' => 'Cost as percentage of service price (0-100, 0 = free)',
            'SimpleMode' => false
        ],

        // Config Option 9: Speed Limit
        'Speed Limit (Mbps)' => [
            'Type' => 'text',
            'Size' => '10',
            'Default' => '0',
            'Description' => 'Maximum speed per connection in Mbps (0 = unlimited)',
            'SimpleMode' => false
        ],

        // Config Option 10: Usage Logging
        'Enable Usage Logging' => [
            'Type' => 'yesno',
            'Default' => 'yes',
            'Description' => 'Log detailed usage statistics (disable to improve performance)',
            'SimpleMode' => false
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
        $dbManager = db_manager();
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
        logModuleCall(
            'orrism',
            __FUNCTION__,
            $params,
            $e->getMessage(),
            $e->getTraceAsString(),
            ['password', 'serverpassword', 'apikey']
        );
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Create account
 *
 * Automatically called by WHMCS when a new service is activated.
 * Creates a new user account in the ORRISM database.
 *
 * @param array $params Module parameters automatically injected by WHMCS including:
 *   - serviceid: int Service ID from WHMCS
 *   - pid: int Product ID
 *   - userid: int Client user ID
 *   - domain: string Service domain
 *   - username: string Service username
 *   - password: string Service password
 *   - serverhostname: string Server hostname from server configuration
 *   - serverip: string Server IP address
 *   - serverusername: string Server username
 *   - serverpassword: string Server password
 *   - serverport: int Server port
 *   - serversecure: bool Whether to use SSL
 *   - configoption[N]: mixed Product configuration options
 * @return string "success" or error message
 */
function orrism_CreateAccount(array $params)
{
    try {
        $db = db();
        $result = $db->createService($params);

        if (!$result['success']) {
            logModuleCall(
                'orrism',
                __FUNCTION__,
                $params,
                $result,
                json_encode($result),
                ['password', 'serverpassword', 'apikey']
            );
            return 'Error: ' . $result['message'];
        }

        // Update WHMCS service with generated credentials
        $username = $result['username'];
        $password = $result['password'];
        $domain = $params['domain'] ?: $params['customfields']['domain'] ?: '';

        Capsule::table('tblhosting')
            ->where('id', $params['serviceid'])
            ->update([
                'username' => $username,
                'password' => encrypt($password),
                'domain' => $domain
            ]);

        logModuleCall(
            'orrism',
            __FUNCTION__,
            $params,
            'Account created successfully',
            json_encode(['username' => $username, 'domain' => $domain]),
            ['password', 'serverpassword', 'apikey']
        );

        return 'success';

    } catch (Exception $e) {
        logModuleCall(
            'orrism',
            __FUNCTION__,
            $params,
            $e->getMessage(),
            $e->getTraceAsString(),
            ['password', 'serverpassword', 'apikey']
        );
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
        $db = db();
        $success = $db->updateServiceStatus($params['serviceid'], 'suspended');

        if (!$success) {
            throw new Exception('User not found in database');
        }

        logModuleCall(
            'orrism',
            __FUNCTION__,
            $params,
            'Account suspended successfully',
            json_encode(['service_id' => $params['serviceid']]),
            ['password', 'serverpassword', 'apikey']
        );

        return 'success';

    } catch (Exception $e) {
        logModuleCall(
            'orrism',
            __FUNCTION__,
            $params,
            $e->getMessage(),
            $e->getTraceAsString(),
            ['password', 'serverpassword', 'apikey']
        );
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
        $db = db();
        $success = $db->updateServiceStatus($params['serviceid'], 'active');

        if (!$success) {
            throw new Exception('User not found in database');
        }

        logModuleCall(
            'orrism',
            __FUNCTION__,
            $params,
            'Account unsuspended successfully',
            json_encode(['service_id' => $params['serviceid']]),
            ['password', 'serverpassword', 'apikey']
        );

        return 'success';

    } catch (Exception $e) {
        logModuleCall(
            'orrism',
            __FUNCTION__,
            $params,
            $e->getMessage(),
            $e->getTraceAsString(),
            ['password', 'serverpassword', 'apikey']
        );
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
        $db = db();
        $success = $db->deleteService($params['serviceid']);

        if (!$success) {
            // User might already be deleted, consider it success
            logModuleCall(
                'orrism',
                __FUNCTION__,
                $params,
                'User not found, might be already deleted',
                json_encode(['service_id' => $params['serviceid']]),
                ['password', 'serverpassword', 'apikey']
            );
        } else {
            logModuleCall(
                'orrism',
                __FUNCTION__,
                $params,
                'Account terminated successfully',
                json_encode(['service_id' => $params['serviceid']]),
                ['password', 'serverpassword', 'apikey']
            );
        }

        return 'success';

    } catch (Exception $e) {
        logModuleCall(
            'orrism',
            __FUNCTION__,
            $params,
            $e->getMessage(),
            $e->getTraceAsString(),
            ['password', 'serverpassword', 'apikey']
        );
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
        $serviceid = $params['serviceid'];
        $password = $params['password'];

        // Load OrrisDB or use Capsule
        $orrisDbPath = __DIR__ . '/includes/orris_db.php';
        if (file_exists($orrisDbPath)) {
            require_once $orrisDbPath;
        }

        $useOrrisDB = class_exists('OrrisDB') && OrrisDB::isConfigured();

        // Update password in ORRISM database (matching actual table structure)
        if ($useOrrisDB) {
            $updated = OrrisDB::table('services')
                ->where('service_id', $serviceid)
                ->update([
                    'password' => password_hash($password, PASSWORD_DEFAULT),
                    'password_algo' => 'bcrypt',
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
        } else {
            $updated = Capsule::table('services')
                ->where('service_id', $serviceid)
                ->update([
                    'password' => password_hash($password, PASSWORD_DEFAULT),
                    'password_algo' => 'bcrypt',
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
        }

        if (!$updated) {
            throw new Exception('User not found in database');
        }

        logModuleCall(
            'orrism',
            __FUNCTION__,
            $params,
            'Password changed successfully',
            json_encode(['service_id' => $serviceid]),
            ['password', 'serverpassword', 'apikey', 'newpassword']
        );

        return 'success';

    } catch (Exception $e) {
        logModuleCall(
            'orrism',
            __FUNCTION__,
            $params,
            $e->getMessage(),
            $e->getTraceAsString(),
            ['password', 'serverpassword', 'apikey', 'newpassword']
        );
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
        $db = db();
        $success = $db->updateUserPackage($params['serviceid'], $params);

        if (!$success) {
            throw new Exception('User not found in database');
        }

        logModuleCall(
            'orrism',
            __FUNCTION__,
            $params,
            'Package changed successfully',
            json_encode(['service_id' => $params['serviceid']]),
            ['password', 'serverpassword', 'apikey']
        );

        return 'success';

    } catch (Exception $e) {
        logModuleCall(
            'orrism',
            __FUNCTION__,
            $params,
            $e->getMessage(),
            $e->getTraceAsString(),
            ['password', 'serverpassword', 'apikey']
        );
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
        $serviceid = $params['serviceid'];
        $resetStrategy = $params['configoption3'] ?: 0; // Traffic Reset Strategy
        $enableReset = $params['configoption5'] == 'on'; // Enable Traffic Reset

        // Handle traffic reset based on strategy
        if ($enableReset && $resetStrategy > 0) {
            $db = db();
            $success = $db->resetServiceTraffic($serviceid);

            if (!$success) {
                logModuleCall(
                    'orrism',
                    __FUNCTION__,
                    $params,
                    'Failed to reset traffic for renewal',
                    json_encode(['service_id' => $serviceid, 'reset_strategy' => $resetStrategy]),
                    ['password', 'serverpassword', 'apikey']
                );
            } else {
                logModuleCall(
                    'orrism',
                    __FUNCTION__,
                    $params,
                    'Service renewed and traffic reset successfully',
                    json_encode(['service_id' => $serviceid, 'reset_strategy' => $resetStrategy]),
                    ['password', 'serverpassword', 'apikey']
                );
            }
        }

        return 'success';

    } catch (Exception $e) {
        logModuleCall(
            'orrism',
            __FUNCTION__,
            $params,
            $e->getMessage(),
            $e->getTraceAsString(),
            ['password', 'serverpassword', 'apikey']
        );
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
        'Reset Traffic' => 'ResetTraffic',  // WHMCS automatically adds orrism_ prefix
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
        $db = db();
        $success = $db->resetServiceTraffic($params['serviceid']);

        if (!$success) {
            throw new Exception('User not found in database');
        }

        logModuleCall(
            'orrism',
            __FUNCTION__,
            $params,
            'Traffic reset successfully',
            json_encode(['service_id' => $params['serviceid']]),
            ['password', 'serverpassword', 'apikey']
        );

        return 'success';

    } catch (Exception $e) {
        logModuleCall(
            'orrism',
            __FUNCTION__,
            $params,
            $e->getMessage(),
            $e->getTraceAsString(),
            ['password', 'serverpassword', 'apikey']
        );
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
        $db = db();
        $result = $db->regenerateUUID($params['serviceid']);

        if (!$result['success']) {
            throw new Exception($result['message']);
        }

        logModuleCall(
            'orrism',
            __FUNCTION__,
            $params,
            'UUID reset successfully',
            json_encode(['service_id' => $params['serviceid'], 'new_uuid' => $result['uuid'] ?? 'N/A']),
            ['password', 'serverpassword', 'apikey']
        );

        return 'success';

    } catch (Exception $e) {
        logModuleCall(
            'orrism',
            __FUNCTION__,
            $params,
            $e->getMessage(),
            $e->getTraceAsString(),
            ['password', 'serverpassword', 'apikey']
        );
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
        $db = db();
        $usage = $db->getUserUsage($params['serviceid']);

        if (empty($usage)) {
            logModuleCall(
                'orrism',
                __FUNCTION__,
                $params,
                'User not found',
                json_encode(['service_id' => $params['serviceid']]),
                ['password', 'serverpassword', 'apikey']
            );
            return ['error' => 'User not found'];
        }

        logModuleCall(
            'orrism',
            __FUNCTION__,
            $params,
            'Usage retrieved successfully',
            json_encode($usage),
            ['password', 'serverpassword', 'apikey']
        );

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
        logModuleCall(
            'orrism',
            __FUNCTION__,
            $params,
            $e->getMessage(),
            $e->getTraceAsString(),
            ['password', 'serverpassword', 'apikey']
        );
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

        // Debug: Log entry
        logModuleCall('orrism', __FUNCTION__ . '_START', ['serviceid' => $serviceid], 'Starting client area');

        $db = db();

        // Get user data
        $service = $db->getService($serviceid);

        // Debug: Log service data
        logModuleCall('orrism', __FUNCTION__ . '_SERVICE', ['serviceid' => $serviceid], 'Service data: ' . json_encode($service));

        if (!$service) {
            logModuleCall(
                'orrism',
                __FUNCTION__,
                $params,
                'Service not found',
                json_encode(['service_id' => $serviceid]),
                ['password', 'serverpassword', 'apikey']
            );
            return [
                'templatefile' => 'error',
                'vars' => ['errormessage' => 'Account not found. Please contact support.']
            ];
        }

        // Get usage statistics
        $usage = $db->getServiceUsage($serviceid);

        // Debug: Log usage data
        logModuleCall('orrism', __FUNCTION__ . '_USAGE', ['serviceid' => $serviceid], 'Usage: ' . json_encode($usage));

        // Get nodes for service's group
        $nodes = $db->getNodesForGroup($service->node_group_id);

        // Debug: Log nodes
        logModuleCall('orrism', __FUNCTION__ . '_NODES', ['serviceid' => $serviceid], 'Nodes: ' . count($nodes));

        // Generate subscription URL
        $subscriptionUrl = generate_subscription_url($params, $service->uuid);

        // Debug: Log final return
        logModuleCall('orrism', __FUNCTION__ . '_RETURN', ['serviceid' => $serviceid], 'Returning clientarea template with ' . count($nodes) . ' nodes');

        return [
            'tabOverviewReplacementTemplate' => 'clientarea',
            'templatefile' => 'clientarea',
            'vars' => [
                'serviceid' => $serviceid,
                'uuid' => $service->uuid,
                'email' => $service->email,
                'nodes' => $nodes,
                'totalBandwidth' => $usage['total_bandwidth'],
                'usedBandwidth' => $usage['used_bandwidth'],
                'remainingBandwidth' => $usage['remaining_bandwidth'],
                'usagePercent' => $usage['usage_percent'],
                'uploadGB' => $usage['upload_gb'],
                'downloadGB' => $usage['download_gb'],
                'subscriptionUrl' => $subscriptionUrl,
                'allowReset' => $params['configoption7'] == 'on', // Allow Manual Reset
                'resetCost' => $params['configoption8'] ?: 0, // Manual Reset Cost (%)
                'maxDevices' => $params['configoption6'] ?: 3, // Max Concurrent Devices
                'status' => $service->status,
                'lastReset' => $service->last_reset_at
            ]
        ];

    } catch (Exception $e) {
        logModuleCall(
            'orrism',
            __FUNCTION__ . '_ERROR',
            $params,
            'Exception: ' . $e->getMessage(),
            $e->getTraceAsString(),
            ['password', 'serverpassword', 'apikey']
        );
        return [
            'templatefile' => 'error',
            'vars' => ['errormessage' => 'Error loading service: ' . $e->getMessage()]
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
        'Reset Traffic' => 'ClientResetTraffic'  // WHMCS automatically adds orrism_ prefix
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
        if ($params['configoption7'] != 'on') { // Allow Manual Reset
            logModuleCall(
                'orrism',
                __FUNCTION__,
                $params,
                'Manual traffic reset not allowed',
                json_encode(['service_id' => $params['serviceid']]),
                ['password', 'serverpassword', 'apikey']
            );
            return 'Manual traffic reset is not allowed';
        }

        $db = db();
        $success = $db->resetServiceTraffic($params['serviceid']);

        if (!$success) {
            throw new Exception('User not found in database');
        }

        // Check if there's a reset cost
        $resetCost = $params['configoption8'] ?: 0; // Manual Reset Cost (%)
        if ($resetCost > 0) {
            // Create invoice for reset cost
            $amount = $params['amount'] * ($resetCost / 100);
            // TODO: Implement invoice creation logic
        }

        logModuleCall(
            'orrism',
            __FUNCTION__,
            $params,
            'Client traffic reset successfully',
            json_encode(['service_id' => $params['serviceid'], 'reset_cost' => $resetCost]),
            ['password', 'serverpassword', 'apikey']
        );

        return 'success';

    } catch (Exception $e) {
        logModuleCall(
            'orrism',
            __FUNCTION__,
            $params,
            $e->getMessage(),
            $e->getTraceAsString(),
            ['password', 'serverpassword', 'apikey']
        );
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
        $db = db();
        $service = $db->getService($params['serviceid']);

        if (!$service) {
            logModuleCall(
                'orrism',
                __FUNCTION__,
                $params,
                'Service not found',
                json_encode(['service_id' => $params['serviceid']]),
                ['password', 'serverpassword', 'apikey']
            );
            return [];
        }

        $usage = $db->getServiceUsage($params['serviceid']);

        return [
            'UUID' => $service->uuid,
            'Service Username' => $service->email,  // Email is used as username
            'WHMCS Account' => $service->email,
            'Total Bandwidth' => $usage['total_bandwidth'] . ' GB',
            'Used Bandwidth' => $usage['used_bandwidth'] . ' GB',
            'Upload' => $usage['upload_gb'] . ' GB',
            'Download' => $usage['download_gb'] . ' GB',
            'Usage Percentage' => $usage['usage_percent'] . '%',
            'Status' => ucfirst($service->status),
            'Node Group' => $service->node_group_id,
            'Created' => $service->created_at,
            'Updated' => $service->updated_at,
            'Last Reset' => $service->last_reset_at ?: 'Never'
        ];

    } catch (Exception $e) {
        logModuleCall(
            'orrism',
            __FUNCTION__,
            $params,
            $e->getMessage(),
            $e->getTraceAsString(),
            ['password', 'serverpassword', 'apikey']
        );
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
    $serverPort = $params['serverport'] ?: ($params['serversecure'] ? 443 : 80);
    $protocol = $params['serversecure'] ? 'https' : 'http';

    return "{$protocol}://{$serverHost}:{$serverPort}/admin";
}

/**
 * Service single sign-on
 *
 * Called when a client clicks the single sign-on link in the client area.
 * Generates a secure token and redirects the client to the service panel.
 *
 * @param array $params Module parameters automatically injected by WHMCS including:
 *   - serviceid: int Service ID
 *   - serverhostname: string Server hostname (auto-injected from server config)
 *   - serverip: string Server IP (auto-injected from server config)
 *   - serversecure: bool Whether to use HTTPS (auto-injected)
 * @return array Array with keys:
 *   - success: bool Whether SSO generation succeeded
 *   - redirectTo: string URL to redirect user to (if success=true)
 *   - errorMsg: string Error message (if success=false)
 */
function orrism_ServiceSingleSignOn(array $params)
{
    try {
        $serviceid = $params['serviceid'];
        $db = get_database($params);

        // Get user data
        $user = Capsule::table($db . '.user')
            ->where('pid', $serviceid)
            ->first();

        if (!$service) {
            logModuleCall(
                'orrism',
                __FUNCTION__,
                $params,
                'User not found',
                json_encode(['service_id' => $serviceid]),
                ['password', 'serverpassword', 'apikey', 'token']
            );
            return ['success' => false, 'errorMsg' => 'User not found'];
        }

        // Generate SSO token
        $token = generate_sso_token($params, $user);

        $serverHost = $params['serverhostname'] ?: $params['serverip'];
        $protocol = $params['serversecure'] ? 'https' : 'http';

        logModuleCall(
            'orrism',
            __FUNCTION__,
            $params,
            'SSO token generated successfully',
            json_encode(['service_id' => $serviceid, 'redirect_url' => "{$protocol}://{$serverHost}/sso"]),
            ['password', 'serverpassword', 'apikey', 'token']
        );

        return [
            'success' => true,
            'redirectTo' => "{$protocol}://{$serverHost}/sso?token={$token}"
        ];

    } catch (Exception $e) {
        logModuleCall(
            'orrism',
            __FUNCTION__,
            $params,
            $e->getMessage(),
            $e->getTraceAsString(),
            ['password', 'serverpassword', 'apikey', 'token']
        );
        return ['success' => false, 'errorMsg' => $e->getMessage()];
    }
}

/**
 * Admin single sign-on
 *
 * Called when an admin clicks the SSO link in the admin panel for a server.
 * Generates a secure admin token and redirects to the server admin panel.
 *
 * @param array $params Module parameters automatically injected by WHMCS including:
 *   - serverhostname: string Server hostname (auto-injected from server config)
 *   - serverip: string Server IP (auto-injected from server config)
 *   - serversecure: bool Whether to use HTTPS (auto-injected)
 *   - serverusername: string Server admin username (auto-injected)
 *   - serverpassword: string Server admin password (auto-injected)
 * @return array Array with keys:
 *   - success: bool Whether SSO generation succeeded
 *   - redirectTo: string URL to redirect admin to (if success=true)
 *   - errorMsg: string Error message (if success=false)
 */
function orrism_AdminSingleSignOn(array $params)
{
    try {
        // Generate admin SSO token
        $token = generate_admin_sso_token($params);

        $serverHost = $params['serverhostname'] ?: $params['serverip'];
        $protocol = $params['serversecure'] ? 'https' : 'http';

        logModuleCall(
            'orrism',
            __FUNCTION__,
            $params,
            'Admin SSO token generated successfully',
            json_encode(['redirect_url' => "{$protocol}://{$serverHost}/admin/sso"]),
            ['password', 'serverpassword', 'apikey', 'token']
        );

        return [
            'success' => true,
            'redirectTo' => "{$protocol}://{$serverHost}/admin/sso?token={$token}"
        ];

    } catch (Exception $e) {
        logModuleCall(
            'orrism',
            __FUNCTION__,
            $params,
            $e->getMessage(),
            $e->getTraceAsString(),
            ['password', 'serverpassword', 'apikey', 'token']
        );
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
        $db = db();
        $usage = $db->getUserUsage($params['serviceid']);

        if (empty($usage)) {
            logModuleCall(
                'orrism',
                __FUNCTION__,
                $params,
                'Usage data not found',
                json_encode(['service_id' => $params['serviceid']]),
                ['password', 'serverpassword', 'apikey']
            );
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
        logModuleCall(
            'orrism',
            __FUNCTION__,
            $params,
            $e->getMessage(),
            $e->getTraceAsString(),
            ['password', 'serverpassword', 'apikey']
        );
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
function generate_password($length = 12)
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
function save_custom_field($serviceid, $fieldname, $value)
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
        logModuleCall(
            'orrism',
            __FUNCTION__,
            ['serviceid' => $serviceid, 'field' => $fieldname],
            $e->getMessage(),
            $e->getTraceAsString(),
            ['password', 'serverpassword', 'apikey']
        );
    }
}

/**
 * Generate subscription URL
 *
 * @param array $params Module parameters
 * @param string $uuid User UUID
 * @return string
 */
function generate_subscription_url(array $params, $uuid)
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
function generate_sso_token(array $params, $user)
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
function generate_admin_sso_token(array $params)
{
    $data = [
        'admin' => true,
        'timestamp' => time(),
        'expires' => time() + 300 // 5 minutes
    ];
    return base64_encode(json_encode($data));
}
