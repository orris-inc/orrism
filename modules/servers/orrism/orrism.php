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
function MetaData()
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
 * @return array Array of configuration options, each with:
 *   - Type: string Field type (text, dropdown, textarea, yesno, etc.)
 *   - Options: array Available options (for dropdown type)
 *   - Default: mixed Default value
 *   - Description: string Help text displayed to admin
 */
function ConfigOptions()
{
    return [
        'database' => [
            'Type' => 'text',
            'Size' => '25',
            'Default' => 'shadowsocks',
            'Description' => 'Database name for ORRISM service data'
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
function TestConnection(array $params)
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
function CreateAccount(array $params)
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
function SuspendAccount(array $params)
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
function UnsuspendAccount(array $params)
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
function TerminateAccount(array $params)
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
function ChangePassword(array $params)
{
    try {
        $serviceid = $params['serviceid'];
        $password = $params['password'];

        // Update password hash in ORRISM database
        $updated = Capsule::table('users')
            ->where('service_id', $serviceid)
            ->update([
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'updated_at' => date('Y-m-d H:i:s')
            ]);

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
function ChangePackage(array $params)
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
function Renew(array $params)
{
    try {
        $serviceid = $params['serviceid'];
        $resetStrategy = $params['configoption2'] ?: 0;

        // Handle traffic reset based on strategy
        if ($resetStrategy > 0) {
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
function AdminCustomButtonArray()
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
function ResetTraffic(array $params)
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
function ResetUUID(array $params)
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
function ViewUsage(array $params)
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
function ClientArea(array $params)
{
    try {
        $serviceid = $params['serviceid'];
        $db = db();

        // Get user data
        $service = $db->getService($serviceid);
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
                'vars' => ['errormessage' => 'Account not found']
            ];
        }

        // Get usage statistics
        $usage = $db->getServiceUsage($serviceid);

        // Get nodes for service's group
        $nodes = $db->getNodesForGroup($service->node_group_id);

        // Generate subscription URL
        $subscriptionUrl = generate_subscription_url($params, $service->uuid);

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
        logModuleCall(
            'orrism',
            __FUNCTION__,
            $params,
            $e->getMessage(),
            $e->getTraceAsString(),
            ['password', 'serverpassword', 'apikey']
        );
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
function ClientAreaCustomButtonArray()
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
function ClientResetTraffic(array $params)
{
    try {
        // Check if manual reset is allowed
        if ($params['configoption5'] != 'on') {
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
        $resetCost = $params['configoption6'] ?: 0;
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
function AdminServicesTabFields(array $params)
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
            'Service Username' => $service->service_username,
            'WHMCS Account' => $service->whmcs_email,
            'Total Bandwidth' => $usage['total_bandwidth'] . ' GB',
            'Used Bandwidth' => $usage['used_bandwidth'] . ' GB',
            'Upload' => $usage['upload_gb'] . ' GB',
            'Download' => $usage['download_gb'] . ' GB',
            'Usage Percentage' => $usage['usage_percent'] . '%',
            'Status' => ucfirst($service->status),
            'Node Group' => $service->node_group_id,
            'Created' => $user->created_at,
            'Updated' => $user->updated_at,
            'Last Reset' => $user->last_reset_at ?: 'Never'
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
function LoginLink(array $params)
{
    $serverHost = $params['serverhostname'] ?: $params['serverip'];
    $serverPort = $params['serversecure'] ? $params['configoption3'] : $params['configoption2'];
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
function ServiceSingleSignOn(array $params)
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
function AdminSingleSignOn(array $params)
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
function UsageUpdate(array $params)
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
