<?php
/**
 * ORRISM Administration Module Controller
 * Handles all admin interface requests and routing
 *
 * @package    WHMCS\Module\Addon\OrrisAdmin\Admin
 * @author     ORRISM Development Team
 * @copyright  Copyright (c) 2024
 * @version    2.0
 */

namespace WHMCS\Module\Addon\OrrisAdmin\Admin;

use WHMCS\Database\Capsule;
use Exception;

class Controller
{
    /**
     * Module variables from WHMCS
     * @var array
     */
    protected $vars = [];
    
    /**
     * Current action being executed
     * @var string
     */
    protected $action = '';
    
    /**
     * Constructor
     * 
     * @param array $vars Module variables from WHMCS
     */
    public function __construct($vars = [])
    {
        $this->vars = $vars;
        $this->action = $_GET['action'] ?? 'index';
        
        // Load dependencies
        $this->loadDependencies();
    }
    
    /**
     * Load required dependencies
     */
    protected function loadDependencies()
    {
        $serverModulePath = dirname(__DIR__, 3) . '/servers/orrism';
        
        // Check if server module exists
        if (!is_dir($serverModulePath)) {
            $whmcsRoot = dirname(__DIR__, 5);
            $serverModulePath = $whmcsRoot . '/modules/servers/orrism';
        }
        
        // Include dependencies with error handling
        $dependencies = [
            'database_manager.php' => $serverModulePath . '/includes/database_manager.php',
            'whmcs_database.php' => $serverModulePath . '/includes/whmcs_database.php',
            'helper.php' => $serverModulePath . '/helper.php'
        ];
        
        foreach ($dependencies as $name => $path) {
            if (file_exists($path)) {
                try {
                    require_once $path;
                } catch (Exception $e) {
                    error_log("ORRISM Admin Controller: Failed to include dependency: $name - " . $e->getMessage());
                }
            }
        }
        
        // Include node UI functions
        $nodeUiPath = dirname(__DIR__, 2) . '/includes/node_ui.php';
        if (file_exists($nodeUiPath)) {
            require_once $nodeUiPath;
        }
    }
    
    /**
     * Main dispatcher method
     * 
     * @param string $action Action to dispatch
     * @param array $vars Module variables
     * @return string HTML output
     */
    public function dispatch($action = null, $vars = null)
    {
        if ($action !== null) {
            $this->action = $action;
        }
        if ($vars !== null) {
            $this->vars = $vars;
        }
        
        try {
            // Handle AJAX requests first
            if ($this->isAjaxRequest()) {
                return $this->ajax($this->vars);
            }
            
            // Handle POST requests
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                return $this->handlePostRequest();
            }
            
            // Route to appropriate method based on action
            switch ($this->action) {
                case 'nodes':
                    return $this->nodes($this->vars);
                case 'services':
                case 'servers': // Backward compatibility  
                case 'users': // Backward compatibility
                    return $this->services($this->vars);
                case 'settings':
                    return $this->settings($this->vars);
                case 'database':
                    return $this->database($this->vars);
                case 'index':
                case 'dashboard':
                default:
                    return $this->index($this->vars);
            }
        } catch (Exception $e) {
            return $this->renderError($e);
        }
    }
    
    /**
     * Dashboard/Index page
     * 
     * @param array $vars Module variables
     * @return string
     */
    public function index($vars)
    {
        try {
            $content = $this->getStyles();
            $content .= '<div class="orrism-admin-dashboard">';
            $content .= '<h2>ORRISM System Dashboard</h2>';
            
            // Navigation menu
            $content .= $this->renderNavigationTabs('dashboard');
            
            // System status
            $content .= '<div class="row">';
            $content .= '<div class="col-xs-12 col-sm-12 col-md-6 col-lg-6">';
            $content .= '<div class="orrism-panel">';
            $content .= '<div class="orrism-panel-heading">System Status</div>';
            $content .= '<div class="orrism-panel-body">';
            
            // Check database connection
            $settings = $this->getOrrisSettings();
            
            if (empty($settings['database_host']) || empty($settings['database_name'])) {
                $content .= '<p><i class="fa fa-database"></i> ORRISM Database: ';
                $content .= '<span class="orrism-text-warning">Not Configured</span> ';
                $content .= '<a href="?module=orrism_admin&action=settings" class="btn btn-xs btn-info">Configure Now</a>';
                $content .= '</p>';
            } elseif (class_exists('OrrisDatabaseManager')) {
                $dbManager = new \OrrisDatabaseManager();
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
            
            // Check Redis connection
            $content .= $this->checkRedisStatus($settings);
            
            $content .= '</div></div></div>';
            
            // Quick stats
            $content .= '<div class="col-xs-12 col-sm-12 col-md-6 col-lg-6">';
            $content .= '<div class="orrism-panel">';
            $content .= '<div class="orrism-panel-heading">Quick Statistics</div>';
            $content .= '<div class="orrism-panel-body">';
            
            // Get statistics
            $stats = $this->getSystemStatistics();
            
            $content .= '<p><i class="fa fa-users"></i> Active Services: <strong>' . $stats['active_services'] . '</strong></p>';
            $content .= '<p><i class="fa fa-server"></i> Total Nodes: <strong>' . $stats['total_nodes'] . '</strong></p>';
            $content .= '<p><i class="fa fa-database"></i> ORRISM Users: <strong>' . $stats['orrism_users'] . '</strong></p>';
            $content .= '<p><i class="fa fa-clock-o"></i> Last Sync: <strong>' . ($stats['last_sync'] ?: 'Never') . '</strong></p>';
            
            $content .= '</div></div></div>';
            $content .= '</div>'; // End row
            
            // Recent activity or alerts
            $content .= $this->renderRecentActivity();
            
            $content .= '</div>'; // End dashboard div
            
            return $content;
            
        } catch (Exception $e) {
            return $this->renderError($e);
        }
    }
    
    /**
     * Settings management page
     * 
     * @param array $vars Module variables
     * @return string
     */
    public function settings($vars)
    {
        try {
            $content = $this->getStyles();
            $content .= '<div class="orrism-admin-dashboard">';
            $content .= '<h2>ORRISM Settings & Database Setup</h2>';
            
            // Navigation
            $content .= $this->renderNavigationTabs('settings');
            
            // Handle settings save if POST request
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'save_settings') {
                $saveResult = $this->handleSettingsSave();
                if ($saveResult['success']) {
                    $content .= '<div class="orrism-alert orrism-alert-success">' . $saveResult['message'] . '</div>';
                } else {
                    $content .= '<div class="orrism-alert orrism-alert-danger">' . $saveResult['message'] . '</div>';
                }
            }
            
            // Get current settings
            $settings = $this->getOrrisSettings();
            
            // Database Installation Section
            $content .= $this->renderDatabaseInstallationSection($settings);
            
            // Settings forms
            $content .= '<div class="row">';
            
            // Database Configuration
            $content .= $this->renderDatabaseConfigurationForm($settings);
            
            // Redis Configuration
            $content .= $this->renderRedisConfigurationForm($settings);
            
            $content .= '</div>'; // End row
            
            // General Settings
            $content .= $this->renderGeneralSettingsForm($settings);
            
            // Add JavaScript for testing connections
            $content .= $this->getSettingsJavaScript();
            
            $content .= '</div>'; // End dashboard div
            
            return $content;
            
        } catch (Exception $e) {
            return $this->renderError($e);
        }
    }
    
    /**
     * User management page
     * 
     * @param array $vars Module variables
     * @return string
     */
    public function services($vars)
    {
        try {
            $content = $this->getStyles();
            $content .= '<div class="orrism-admin-dashboard">';
            $content .= '<h2>Server Management</h2>';
            
            // Navigation
            $content .= $this->renderNavigationTabs('users');
            
            // Handle POST requests
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                if ($_POST['action'] === 'reset_traffic') {
                    $resetResult = $this->handleTrafficReset();
                    $content .= '<div class="orrism-alert orrism-alert-info">' . $resetResult['message'] . '</div>';
                }
            }
            
            // Server management interface
            $content .= '<div class="orrism-panel">';
            $content .= '<div class="orrism-panel-heading">Service Management</div>';
            $content .= '<div class="orrism-panel-body">';
            
            $content .= '<p>ORRISM service accounts are automatically created when WHMCS services are provisioned. Each WHMCS service corresponds to one ORRISM service account.</p>';
            
            $content .= '<div class="btn-group" style="margin-bottom: 20px;">';
            $content .= '<form method="post" style="display: inline-block;">';
            $content .= '<input type="hidden" name="action" value="reset_traffic">';
            $content .= '<button type="submit" class="btn btn-warning"><i class="fa fa-undo"></i> Reset All Traffic</button>';
            $content .= '</form>';
            $content .= '</div>';
            
            // Display server list
            $content .= $this->renderServerList();
            
            $content .= '<div class="orrism-alert orrism-alert-info" style="margin-top: 20px;">';
            $content .= '<i class="fa fa-info-circle"></i> Services are managed through WHMCS service provisioning. To create a new service, create a new service order in WHMCS.';
            $content .= '</div>';
            
            $content .= '</div></div>';
            
            $content .= '</div>'; // End dashboard div
            
            return $content;
            
        } catch (Exception $e) {
            return $this->renderError($e);
        }
    }
    
    /**
     * Node management page
     * 
     * @param array $vars Module variables
     * @return string
     */
    public function nodes($vars)
    {
        try {
            // Check if node_ui.php function exists
            if (function_exists('renderNodeManagement')) {
                return $this->getStyles() . renderNodeManagement($vars);
            }
            
            // Fallback if node UI not available
            $content = $this->getStyles();
            $content .= '<div class="orrism-admin-dashboard">';
            $content .= '<h2>Node Management</h2>';
            
            $content .= $this->renderNavigationTabs('nodes');
            
            $content .= '<div class="orrism-alert orrism-alert-warning">';
            $content .= 'Node management interface is not available. Please ensure node_ui.php is properly installed.';
            $content .= '</div>';
            
            $content .= '</div>';
            
            return $content;
            
        } catch (Exception $e) {
            return $this->renderError($e);
        }
    }
    
    /**
     * Database status interface
     * 
     * @param array $vars Module variables
     * @return string
     */
    public function database($vars)
    {
        try {
            $content = $this->getStyles();
            $content .= '<div class="orrism-admin-dashboard">';
            $content .= '<h2>Database Status</h2>';
            
            $content .= $this->renderNavigationTabs('settings');
            
            // Check database status
            $dbStatus = $this->checkDatabaseStatus();
            
            $content .= '<div class="orrism-panel">';
            $content .= '<div class="orrism-panel-heading">Database Information</div>';
            $content .= '<div class="orrism-panel-body">';
            
            $content .= '<p><strong>Current Status:</strong> <span class="' . $dbStatus['class'] . '">' . $dbStatus['status'] . '</span></p>';
            
            if ($dbStatus['status'] === 'Not Installed') {
                $content .= '<div class="orrism-alert orrism-alert-info">';
                $content .= '<strong>Note:</strong> Database tables are automatically installed when the module is activated. ';
                $content .= 'If you\'re seeing this message, please deactivate and reactivate the module to install the database tables.';
                $content .= '</div>';
            } elseif ($dbStatus['status'] === 'Not Configured') {
                $content .= '<p>' . $dbStatus['message'] . '</p>';
                $content .= '<a href="?module=orrism_admin&action=settings" class="btn btn-info">';
                $content .= '<i class="fa fa-cog"></i> Configure Database Settings';
                $content .= '</a>';
            } else {
                $content .= '<p>' . $dbStatus['message'] . '</p>';
            }
            
            $content .= '</div></div>';
            
            $content .= '</div>';
            
            return $content;
            
        } catch (Exception $e) {
            return $this->renderError($e);
        }
    }
    
    /**
     * Handle AJAX requests
     * 
     * @param array $vars Module variables
     * @return void Outputs JSON directly and exits
     */
    public function ajax($vars)
    {
        // Clean output buffers for clean JSON response
        while (ob_get_level()) {
            ob_end_clean();
        }
        ob_start();
        
        try {
            header('Content-Type: application/json; charset=utf-8');
            
            $action = $_REQUEST['ajax_action'] ?? $_REQUEST['action'] ?? '';
            
            switch ($action) {
                case 'test_database':
                case 'test_connection':
                    $result = $this->testDatabaseConnection();
                    break;

                case 'test_redis':
                    $result = $this->testRedisConnection();
                    break;

                case 'get_node_stats':
                    $result = $this->getNodeStatistics();
                    break;

                case 'get_service_stats':
                    $result = $this->getServiceStatistics();
                    break;

                // Node management AJAX actions
                case 'node_create':
                    $result = $this->handleNodeCreate();
                    break;

                case 'node_update':
                    $result = $this->handleNodeUpdate();
                    break;

                case 'node_get':
                    $result = $this->handleNodeGet();
                    break;

                case 'node_delete':
                    $result = $this->handleNodeDelete();
                    break;

                case 'node_toggle':
                    $result = $this->handleNodeToggle();
                    break;

                case 'node_batch':
                    $result = $this->handleNodeBatch();
                    break;

                case 'node_get_methods':
                    $result = $this->handleNodeGetMethods();
                    break;

                default:
                    $result = [
                        'success' => false,
                        'message' => 'Unknown AJAX action: ' . $action
                    ];
            }
            
            ob_clean();
            echo json_encode($result);
            ob_end_flush();
            exit;
            
        } catch (Exception $e) {
            $errorResponse = [
                'success' => false,
                'message' => 'AJAX Error: ' . $e->getMessage(),
                'debug' => [
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]
            ];
            
            ob_clean();
            echo json_encode($errorResponse);
            ob_end_flush();
            exit;
        }
    }
    
    /**
     * Handle POST requests
     * 
     * @return string
     */
    protected function handlePostRequest()
    {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'install_database':
                $result = $this->handleDatabaseInstall();
                return $this->renderPostResult($result, 'database');
                
            case 'save_settings':
                $result = $this->handleSettingsSave();
                return $this->renderPostResult($result, 'settings');
                
                
            case 'reset_traffic':
                $result = $this->handleTrafficReset();
                return $this->renderPostResult($result, 'servers');
                
            default:
                return $this->index($this->vars);
        }
    }
    
    /**
     * Render POST operation result
     * 
     * @param array $result Operation result
     * @param string $redirectAction Action to redirect to
     * @return string
     */
    protected function renderPostResult($result, $redirectAction)
    {
        $message = '';
        
        if ($result['success']) {
            $message = '<div class="orrism-alert orrism-alert-success">' . htmlspecialchars($result['message']) . '</div>';
        } else {
            $message = '<div class="orrism-alert orrism-alert-danger">' . htmlspecialchars($result['message']) . '</div>';
        }
        
        // Redirect to appropriate page with message
        switch ($redirectAction) {
            case 'database':
                return $message . $this->database($this->vars);
            case 'settings':
                return $message . $this->settings($this->vars);
            case 'users':
                return $message . $this->users($this->vars);
            default:
                return $message . $this->index($this->vars);
        }
    }
    
    /**
     * Handle database installation
     * 
     * @return array
     */
    protected function handleDatabaseInstall()
    {
        try {
            if (!class_exists('OrrisDatabaseManager')) {
                // Fallback: Try simple table creation
                return $this->handleSimpleTableCreation();
            }
            
            $dbManager = new \OrrisDatabaseManager();
            
            if ($dbManager->isInstalled()) {
                return [
                    'success' => false,
                    'message' => 'Database tables already exist. If you need to reinstall, please uninstall first.'
                ];
            }
            
            $result = $dbManager->install();
            
            if ($result['success']) {
                // No need to track installation status - checked directly from database
            }
            
            return $result;
            
        } catch (Exception $e) {
            error_log('ORRISM Controller: Database installation error: ' . $e->getMessage());
            return $this->handleSimpleTableCreation();
        }
    }
    
    /**
     * Fallback simple table creation
     * 
     * @return array
     */
    protected function handleSimpleTableCreation()
    {
        // Fallback method removed - use OrrisDatabaseManager directly
        return [
            'success' => false,
            'message' => 'Database installation failed. Please check the error logs.'
        ];
    }
    
    /**
     * Handle settings save
     * 
     * @return array
     */
    protected function handleSettingsSave()
    {
        $settingsType = $_POST['settings_type'] ?? '';
        $settings = [];
        
        switch ($settingsType) {
            case 'database':
                $settings['database_host'] = $_POST['database_host'] ?? 'localhost';
                $dbPort = $_POST['database_port'] ?? '3306';
                $settings['database_port'] = is_numeric($dbPort) ? (string)max(1, min(65535, (int)$dbPort)) : '3306';
                $settings['database_name'] = $_POST['database_name'] ?? 'orrism';
                $settings['database_user'] = $_POST['database_user'] ?? '';
                
                if (!empty($_POST['database_password'])) {
                    $settings['database_password'] = $_POST['database_password'];
                }
                break;
                
            case 'redis':
                $settings['redis_host'] = $_POST['redis_host'] ?? 'localhost';
                $settings['redis_port'] = $_POST['redis_port'] ?? '6379';
                $redisDb = $_POST['redis_db'] ?? '0';
                $settings['redis_db'] = is_numeric($redisDb) ? (string)max(0, (int)$redisDb) : '0';
                $settings['redis_username'] = $_POST['redis_username'] ?? '';
                $settings['redis_password'] = $_POST['redis_password'] ?? '';
                break;
                
            case 'general':
                $settings['auto_sync'] = isset($_POST['auto_sync']) ? '1' : '0';
                $settings['auto_reset_traffic'] = isset($_POST['auto_reset_traffic']) ? '1' : '0';
                $settings['reset_day'] = $_POST['reset_day'] ?? '1';
                break;
        }
        
        if ($this->saveOrrisSettings($settings)) {
            return [
                'success' => true,
                'message' => 'Settings saved successfully!'
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Failed to save settings.'
            ];
        }
    }
    
    /**
     * Handle traffic reset
     * 
     * @return array
     */
    protected function handleTrafficReset()
    {
        try {
            // Placeholder for traffic reset implementation
            return [
                'success' => true,
                'message' => 'Traffic reset functionality is not yet implemented.'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Traffic Reset Error: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Test database connection
     * 
     * @return array
     */
    protected function testDatabaseConnection()
    {
        try {
            $host = $_POST['test_host'] ?? '';
            $port = isset($_POST['test_port']) && is_numeric($_POST['test_port']) ? (int)$_POST['test_port'] : 3306;
            $name = $_POST['test_name'] ?? '';
            $user = $_POST['test_user'] ?? '';
            $pass = $_POST['test_pass'] ?? '';
            
            if (empty($host) || empty($name) || empty($user)) {
                return [
                    'success' => false,
                    'message' => 'Missing required parameters'
                ];
            }
            
            $dsn = "mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4";
            $pdo = new \PDO($dsn, $user, $pass, [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_TIMEOUT => 5
            ]);
            
            $stmt = $pdo->query("SELECT VERSION() as version");
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            return [
                'success' => true,
                'message' => 'Connection successful! MySQL version: ' . $result['version']
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Connection failed: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Test Redis connection
     * 
     * @return array
     */
    protected function testRedisConnection()
    {
        try {
            if (!class_exists('Redis')) {
                return [
                    'success' => false,
                    'message' => 'Redis extension is not installed'
                ];
            }
            
            $host = $_POST['test_host'] ?? 'localhost';
            $port = isset($_POST['test_port']) ? (int)$_POST['test_port'] : 6379;
            $db = isset($_POST['test_db']) ? (int)$_POST['test_db'] : 0;
            $username = $_POST['test_username'] ?? '';
            $password = $_POST['test_password'] ?? '';
            
            $redis = new \Redis();
            
            if (!$redis->connect($host, $port, 2.0)) {
                return [
                    'success' => false,
                    'message' => 'Failed to connect to Redis server'
                ];
            }
            
            // Authenticate if needed
            if (!empty($username) && !empty($password)) {
                if (!$redis->auth([$username, $password])) {
                    return [
                        'success' => false,
                        'message' => 'Redis authentication failed'
                    ];
                }
            } elseif (!empty($password)) {
                if (!$redis->auth($password)) {
                    return [
                        'success' => false,
                        'message' => 'Redis authentication failed'
                    ];
                }
            }
            
            // Select database
            if (!$redis->select($db)) {
                return [
                    'success' => false,
                    'message' => 'Failed to select Redis database'
                ];
            }
            
            // Test ping
            $pong = $redis->ping();
            $redis->close();
            
            if ($pong) {
                return [
                    'success' => true,
                    'message' => 'Redis connection successful!'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Redis server did not respond to ping'
                ];
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Redis connection error: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Check if this is an AJAX request
     *
     * @return bool
     */
    protected function isAjaxRequest()
    {
        $ajaxActions = [
            'test_connection', 'test_database', 'test_redis',
            'node_create', 'node_update', 'node_get', 'node_delete',
            'node_toggle', 'node_batch', 'node_get_methods',
            'get_node_stats', 'get_service_stats'
        ];
        $currentAction = $_GET['action'] ?? $_POST['action'] ?? '';

        return in_array($currentAction, $ajaxActions) ||
               (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
                strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
    }
    
    /**
     * Get ORRISM settings from database
     * Uses standard WHMCS method for addon modules
     * 
     * @return array
     */
    protected function getOrrisSettings()
    {
        // Use the vars passed to the module which contains all configuration
        $settings = [];
        
        // The module configuration is already loaded in $this->vars
        if (!empty($this->vars)) {
            // Extract configuration fields from vars
            $configFields = [
                'database_host', 'database_port', 'database_name', 'database_user', 'database_password',
                'redis_host', 'redis_port', 'redis_db', 'redis_username', 'redis_password',
                'enable_traffic_log', 'traffic_reset_day'
            ];
            
            foreach ($configFields as $field) {
                if (isset($this->vars[$field])) {
                    $settings[$field] = $this->vars[$field];
                }
            }
        }
        
        // If vars not available, fallback to direct query
        if (empty($settings)) {
            try {
                $result = Capsule::table('tbladdonmodules')
                    ->where('module', 'orrism_admin')
                    ->get();
                
                foreach ($result as $row) {
                    $settings[$row->setting] = $row->value;
                }
            } catch (Exception $e) {
                // Log error but continue
            }
        }
        
        
        return $settings;
    }
    
    /**
     * Save ORRISM settings to database
     * 
     * @param array $settings
     * @return bool
     */
    protected function saveOrrisSettings($settings)
    {
        try {
            $coreSettings = [
                'database_host', 'database_port', 'database_name', 'database_user', 'database_password',
                'redis_host', 'redis_port', 'redis_db', 'redis_username', 'redis_password',
                'enable_traffic_log', 'traffic_reset_day'
            ];
            
            foreach ($settings as $key => $value) {
                if (in_array($key, $coreSettings)) {
                    // Save to WHMCS addon modules table
                    Capsule::table('tbladdonmodules')
                        ->updateOrInsert(
                            ['module' => 'orrism_admin', 'setting' => $key],
                            ['value' => $value]
                        );
                } else {
                    // Non-core settings are ignored
                    continue;
                }
            }
            
            // Clear cached configuration if OrrisDB exists
            if (class_exists('OrrisDB')) {
                \OrrisDB::reset();
            }
            
            return true;
        } catch (Exception $e) {
            error_log('ORRISM Controller: Failed to save settings: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check database status
     * 
     * @return array
     */
    protected function checkDatabaseStatus()
    {
        $settings = $this->getOrrisSettings();
        
        if (empty($settings['database_host']) || empty($settings['database_name'])) {
            return [
                'status' => 'Not Configured',
                'class' => 'orrism-text-warning',
                'can_install' => false,
                'message' => 'Please configure database settings first.'
            ];
        }
        
        if (!class_exists('OrrisDatabaseManager')) {
            return [
                'status' => 'Manager Not Available',
                'class' => 'orrism-text-danger',
                'can_install' => false,
                'message' => 'Database manager class is not available.'
            ];
        }
        
        try {
            $dbManager = new \OrrisDatabaseManager();
            
            if (!$dbManager->testConnection()) {
                return [
                    'status' => 'Connection Failed',
                    'class' => 'orrism-text-danger',
                    'can_install' => false,
                    'message' => 'Cannot connect to database. Please check your settings.'
                ];
            }
            
            if ($dbManager->isInstalled()) {
                return [
                    'status' => 'Installed',
                    'class' => 'orrism-text-success',
                    'can_install' => false,
                    'message' => 'Database tables are already installed and working correctly.'
                ];
            }
            
            return [
                'status' => 'Ready to Install',
                'class' => 'orrism-text-warning',
                'can_install' => true,
                'message' => 'Database is connected but tables are not installed.'
            ];
            
        } catch (Exception $e) {
            return [
                'status' => 'Error',
                'class' => 'orrism-text-danger',
                'can_install' => false,
                'message' => 'Error checking database status: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Get system statistics
     * 
     * @return array
     */
    protected function getSystemStatistics()
    {
        $stats = [
            'active_services' => 0,
            'total_nodes' => 0,
            'orrism_users' => 0,
            'last_sync' => null
        ];
        
        try {
            // Get active services count
            if (class_exists('OrrisDatabase')) {
                $orrisDb = new \OrrisDatabase();
                $stats['active_services'] = $orrisDb->getActiveServiceCount('orrism');
            }
            
            // Get ORRISM users count
            if (class_exists('OrrisDatabaseManager')) {
                $dbManager = new \OrrisDatabaseManager();
                if ($dbManager->testConnection()) {
                    $stats['orrism_services'] = $dbManager->getServiceCount();
                }
            }
            
            // Get last sync time
            $settings = $this->getOrrisSettings();
            $stats['last_sync'] = $settings['last_sync'] ?? null;
            
        } catch (Exception $e) {
            error_log('ORRISM Controller: Error getting statistics: ' . $e->getMessage());
        }
        
        return $stats;
    }
    
    /**
     * Check Redis status
     * 
     * @param array $settings
     * @return string
     */
    protected function checkRedisStatus($settings)
    {
        $content = '<p><i class="fa fa-server"></i> Redis Cache: ';
        
        if (!class_exists('Redis')) {
            $content .= '<span class="orrism-text-warning">Redis Extension Not Installed</span>';
        } else {
            try {
                $redisHost = $settings['redis_host'] ?? 'localhost';
                $redisPort = isset($settings['redis_port']) ? (int)$settings['redis_port'] : 6379;
                $redisDb = isset($settings['redis_db']) ? (int)$settings['redis_db'] : 0;
                $redisUsername = $settings['redis_username'] ?? '';
                $redisPassword = $settings['redis_password'] ?? '';
                
                $redis = new \Redis();
                $connected = $redis->connect($redisHost, $redisPort, 2.0);
                
                if ($connected) {
                    if (!empty($redisUsername) && !empty($redisPassword)) {
                        $redis->auth([$redisUsername, $redisPassword]);
                    } elseif (!empty($redisPassword)) {
                        $redis->auth($redisPassword);
                    }
                    
                    $redis->select($redisDb);
                    $pong = $redis->ping();
                    
                    if ($pong) {
                        $content .= '<span class="orrism-text-success">Connected</span>';
                        $content .= ' <small class="text-muted">DB: ' . $redisDb . '</small>';
                    } else {
                        $content .= '<span class="orrism-text-warning">Connected (No Ping)</span>';
                    }
                    
                    $redis->close();
                } else {
                    $content .= '<span class="orrism-text-danger">Not Connected</span>';
                }
            } catch (Exception $e) {
                $content .= '<span class="orrism-text-danger">Error</span>';
            }
        }
        
        $content .= '</p>';
        return $content;
    }
    
    /**
     * Render navigation tabs
     * 
     * @param string $activeAction
     * @return string
     */
    protected function renderNavigationTabs($activeAction)
    {
        $tabs = [
            'dashboard' => 'Dashboard',
            'nodes' => 'Node Management',
            'servers' => 'Server Management',
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
     * Render recent activity section
     * 
     * @return string
     */
    protected function renderRecentActivity()
    {
        $content = '<div class="orrism-panel" style="margin-top: 20px;">';
        $content .= '<div class="orrism-panel-heading">Recent Activity</div>';
        $content .= '<div class="orrism-panel-body">';
        
        $content .= '<div class="orrism-alert orrism-alert-info">';
        $content .= '<i class="fa fa-info-circle"></i> Activity tracking and system logs will be displayed here.';
        $content .= '</div>';
        
        $content .= '</div></div>';
        
        return $content;
    }
    
    /**
     * Render database installation section
     * 
     * @param array $settings
     * @return string
     */
    protected function renderDatabaseInstallationSection($settings)
    {
        $content = '<div class="orrism-panel" style="margin-bottom: 20px;">';
        $content .= '<div class="orrism-panel-heading">Database Installation</div>';
        $content .= '<div class="orrism-panel-body">';
        
        $dbStatus = $this->checkDatabaseStatus();
        
        $content .= '<p><strong>Status:</strong> <span class="' . $dbStatus['class'] . '">' . $dbStatus['status'] . '</span></p>';
        
        // Check if database is configured
        if (empty($settings['database_host']) || empty($settings['database_name']) || empty($settings['database_user'])) {
            $content .= '<div class="orrism-alert orrism-alert-warning">';
            $content .= '<i class="fa fa-exclamation-triangle"></i> <strong>Important:</strong> Please configure database settings below first, then come back here to install the tables.';
            $content .= '<ol style="margin-top: 10px;">';
            $content .= '<li>Fill in the Database Configuration form below</li>';
            $content .= '<li>Click "Save Database Settings"</li>';
            $content .= '<li>Test the connection to ensure it works</li>';
            $content .= '<li>Return here and click "Install Database Tables"</li>';
            $content .= '</ol>';
            $content .= '</div>';
        } elseif ($dbStatus['can_install']) {
            $content .= '<p>The database is configured and connected. Click the button below to create the necessary tables for ORRISM integration.</p>';
            $content .= '<form method="post" style="display: inline;">';
            $content .= '<input type="hidden" name="action" value="install_database">';
            $content .= '<button type="submit" class="btn btn-success btn-sm">';
            $content .= '<i class="fa fa-database"></i> Install Database Tables</button>';
            $content .= '</form>';
            $content .= '<p style="margin-top: 10px;"><small class="text-muted">This will create tables in the <strong>' . htmlspecialchars($settings['database_name']) . '</strong> database.</small></p>';
        } else {
            $content .= '<div class="orrism-alert orrism-alert-' . 
                        ($dbStatus['status'] === 'Installed' ? 'success' : 'info') . '">';
            $content .= '<i class="fa fa-' . 
                        ($dbStatus['status'] === 'Installed' ? 'check-circle' : 'info-circle') . 
                        '"></i> ' . $dbStatus['message'];
            $content .= '</div>';
        }
        
        $content .= '</div></div>';
        
        return $content;
    }
    
    /**
     * Render database configuration form
     * 
     * @param array $settings
     * @return string
     */
    protected function renderDatabaseConfigurationForm($settings)
    {
        $content = '<div class="col-xs-12 col-sm-12 col-md-6 col-lg-6">';
        $content .= '<div class="orrism-panel">';
        $content .= '<div class="orrism-panel-heading">Database Configuration</div>';
        $content .= '<div class="orrism-panel-body">';
        
        $content .= '<form method="post" action="?module=orrism_admin&action=settings">';
        $content .= '<input type="hidden" name="action" value="save_settings">';
        $content .= '<input type="hidden" name="settings_type" value="database">';
        
        $content .= '<div class="form-group">';
        $content .= '<label for="db_host">Database Host</label>';
        $content .= '<input type="text" class="form-control" id="db_host" name="database_host" ';
        $content .= 'value="' . htmlspecialchars($settings['database_host'] ?? 'localhost') . '" required>';
        $content .= '<small class="form-text text-muted">Database server hostname or IP address</small>';
        $content .= '</div>';
        
        $content .= '<div class="form-group">';
        $content .= '<label for="db_port">Database Port</label>';
        $content .= '<input type="number" class="form-control" id="db_port" name="database_port" ';
        $content .= 'value="' . htmlspecialchars($settings['database_port'] ?? '3306') . '" ';
        $content .= 'min="1" max="65535" required>';
        $content .= '<small class="form-text text-muted">MySQL port (default 3306)</small>';
        $content .= '</div>';
        
        $content .= '<div class="form-group">';
        $content .= '<label for="db_name">Database Name</label>';
        $content .= '<input type="text" class="form-control" id="db_name" name="database_name" ';
        $content .= 'value="' . htmlspecialchars($settings['database_name'] ?? 'orrism') . '" required>';
        $content .= '<small class="form-text text-muted">Database name for ORRISM data</small>';
        $content .= '</div>';
        
        $content .= '<div class="form-group">';
        $content .= '<label for="db_user">Database Username</label>';
        $content .= '<input type="text" class="form-control" id="db_user" name="database_user" ';
        $content .= 'value="' . htmlspecialchars($settings['database_user'] ?? '') . '" required>';
        $content .= '<small class="form-text text-muted">Database username</small>';
        $content .= '</div>';
        
        $content .= '<div class="form-group">';
        $content .= '<label for="db_pass">Database Password</label>';
        $content .= '<input type="password" class="form-control" id="db_pass" name="database_password" ';
        $content .= 'value="' . htmlspecialchars($settings['database_password'] ?? '') . '">';
        $content .= '<small class="form-text text-muted">Database password (leave blank to keep current)</small>';
        $content .= '</div>';
        
        $content .= '<button type="submit" class="btn btn-primary btn-sm">Save Database Settings</button>';
        $content .= ' <button type="button" class="btn btn-info btn-sm" onclick="testDatabaseConnection()" ';
        $content .= 'id="testDbBtn"><i class="fa fa-plug"></i> Test Connection</button>';
        
        $content .= '</form>';
        $content .= '<div id="testResult" class="mt-3"></div>';
        
        $content .= '</div></div></div>';
        
        return $content;
    }
    
    /**
     * Render Redis configuration form
     * 
     * @param array $settings
     * @return string
     */
    protected function renderRedisConfigurationForm($settings)
    {
        $content = '<div class="col-xs-12 col-sm-12 col-md-6 col-lg-6">';
        $content .= '<div class="orrism-panel">';
        $content .= '<div class="orrism-panel-heading">Cache Configuration (Optional)</div>';
        $content .= '<div class="orrism-panel-body">';
        
        $content .= '<form method="post" action="?module=orrism_admin&action=settings">';
        $content .= '<input type="hidden" name="action" value="save_settings">';
        $content .= '<input type="hidden" name="settings_type" value="redis">';
        
        $content .= '<div class="form-group">';
        $content .= '<label for="redis_host">Redis Host</label>';
        $content .= '<input type="text" class="form-control" id="redis_host" name="redis_host" ';
        $content .= 'value="' . htmlspecialchars($settings['redis_host'] ?? 'localhost') . '">';
        $content .= '<small class="form-text text-muted">Redis server hostname (optional)</small>';
        $content .= '</div>';
        
        $content .= '<div class="form-group">';
        $content .= '<label for="redis_port">Redis Port</label>';
        $content .= '<input type="text" class="form-control" id="redis_port" name="redis_port" ';
        $content .= 'value="' . htmlspecialchars($settings['redis_port'] ?? '6379') . '">';
        $content .= '<small class="form-text text-muted">Redis server port</small>';
        $content .= '</div>';
        
        $content .= '<div class="form-group">';
        $content .= '<label for="redis_db">Redis Database</label>';
        $content .= '<input type="number" class="form-control" id="redis_db" name="redis_db" ';
        $content .= 'value="' . htmlspecialchars($settings['redis_db'] ?? '0') . '" min="0">';
        $content .= '<small class="form-text text-muted">Redis database number (default 0)</small>';
        $content .= '</div>';
        
        $content .= '<div class="form-group">';
        $content .= '<label for="redis_username">Redis Username</label>';
        $content .= '<input type="text" class="form-control" id="redis_username" name="redis_username" ';
        $content .= 'value="' . htmlspecialchars($settings['redis_username'] ?? '') . '">';
        $content .= '<small class="form-text text-muted">Redis username (optional, for Redis 6.0+ ACL)</small>';
        $content .= '</div>';
        
        $content .= '<div class="form-group">';
        $content .= '<label for="redis_password">Redis Password</label>';
        $content .= '<input type="password" class="form-control" id="redis_password" name="redis_password" ';
        $content .= 'value="' . htmlspecialchars($settings['redis_password'] ?? '') . '">';
        $content .= '<small class="form-text text-muted">Redis password (optional)</small>';
        $content .= '</div>';
        
        $content .= '<button type="submit" class="btn btn-primary btn-sm">Save Redis Settings</button>';
        $content .= ' <button type="button" class="btn btn-info btn-sm" onclick="testRedisConnection()" ';
        $content .= 'id="testRedisBtn"><i class="fa fa-plug"></i> Test Connection</button>';
        
        $content .= '</form>';
        $content .= '<div id="testRedisResult" class="mt-3"></div>';
        
        $content .= '</div></div></div>';
        
        return $content;
    }
    
    /**
     * Render general settings form
     * 
     * @param array $settings
     * @return string
     */
    protected function renderGeneralSettingsForm($settings)
    {
        $content = '<div class="orrism-panel" style="margin-top: 20px;">';
        $content .= '<div class="orrism-panel-heading">General Settings</div>';
        $content .= '<div class="orrism-panel-body">';
        
        $content .= '<form method="post" action="?module=orrism_admin&action=settings">';
        $content .= '<input type="hidden" name="action" value="save_settings">';
        $content .= '<input type="hidden" name="settings_type" value="general">';
        
        $content .= '<div class="form-check">';
        $content .= '<input type="checkbox" class="form-check-input" id="auto_sync" name="auto_sync" ';
        $content .= (($settings['auto_sync'] ?? '0') === '1' ? 'checked' : '') . '>';
        $content .= '<label class="form-check-label" for="auto_sync">';
        $content .= 'Enable automatic user synchronization';
        $content .= '</label>';
        $content .= '</div>';
        
        $content .= '<div class="form-check">';
        $content .= '<input type="checkbox" class="form-check-input" id="auto_reset_traffic" name="auto_reset_traffic" ';
        $content .= (($settings['auto_reset_traffic'] ?? '0') === '1' ? 'checked' : '') . '>';
        $content .= '<label class="form-check-label" for="auto_reset_traffic">';
        $content .= 'Enable automatic monthly traffic reset';
        $content .= '</label>';
        $content .= '</div>';
        
        $content .= '<div class="form-group" style="margin-top: 15px;">';
        $content .= '<label for="reset_day">Traffic Reset Day</label>';
        $content .= '<select class="form-control" id="reset_day" name="reset_day">';
        $currentDay = $settings['reset_day'] ?? '1';
        for ($i = 1; $i <= 28; $i++) {
            $selected = ($i == $currentDay) ? 'selected' : '';
            $content .= '<option value="' . $i . '" ' . $selected . '>Day ' . $i . ' of each month</option>';
        }
        $content .= '</select>';
        $content .= '<small class="form-text text-muted">Day of the month to reset traffic counters</small>';
        $content .= '</div>';
        
        $content .= '<button type="submit" class="btn btn-primary btn-sm">Save General Settings</button>';
        
        $content .= '</form>';
        $content .= '</div></div>';
        
        return $content;
    }
    
    /**
     * Get CSS styles
     * 
     * @return string
     */
    protected function getStyles()
    {
        return '<style>
        .orrism-admin-dashboard { 
            padding: 15px; 
            max-width: 100%;
            overflow-x: hidden;
        }
        
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
        
        .orrism-text-success { color: #3c763d; }
        .orrism-text-danger { color: #a94442; }
        .orrism-text-warning { color: #8a6d3b; }
        .orrism-text-muted { color: #777; }
        
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
        
        @media (max-width: 992px) {
            .orrism-admin-dashboard .col-md-6 {
                width: 100%;
                margin-bottom: 10px;
            }
        }
        
        .orrism-table-responsive {
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        
        .orrism-btn-group {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
        }
        </style>';
    }
    
    /**
     * Get JavaScript for settings page
     * 
     * @return string
     */
    protected function getSettingsJavaScript()
    {
        return '<script>
        function testDatabaseConnection() {
            var btn = document.getElementById("testDbBtn");
            var resultDiv = document.getElementById("testResult");
            
            btn.disabled = true;
            btn.innerHTML = \'<i class="fa fa-spinner fa-spin"></i> Testing...\';
            
            var formData = new FormData();
            formData.append("action", "test_database");
            formData.append("test_host", document.getElementById("db_host").value);
            formData.append("test_port", document.getElementById("db_port").value);
            formData.append("test_name", document.getElementById("db_name").value);
            formData.append("test_user", document.getElementById("db_user").value);
            formData.append("test_pass", document.getElementById("db_pass").value);
            
            fetch("?module=orrism_admin", {
                method: "POST",
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    resultDiv.innerHTML = \'<div class="orrism-alert orrism-alert-success">\' + data.message + \'</div>\';
                } else {
                    resultDiv.innerHTML = \'<div class="orrism-alert orrism-alert-danger">\' + data.message + \'</div>\';
                }
            })
            .catch(error => {
                resultDiv.innerHTML = \'<div class="orrism-alert orrism-alert-danger">Connection test failed: \' + error + \'</div>\';
            })
            .finally(() => {
                btn.disabled = false;
                btn.innerHTML = \'<i class="fa fa-plug"></i> Test Connection\';
            });
        }
        
        function testRedisConnection() {
            var btn = document.getElementById("testRedisBtn");
            var resultDiv = document.getElementById("testRedisResult");
            
            btn.disabled = true;
            btn.innerHTML = \'<i class="fa fa-spinner fa-spin"></i> Testing...\';
            
            var formData = new FormData();
            formData.append("action", "test_redis");
            formData.append("test_host", document.getElementById("redis_host").value);
            formData.append("test_port", document.getElementById("redis_port").value);
            formData.append("test_db", document.getElementById("redis_db").value);
            formData.append("test_username", document.getElementById("redis_username").value);
            formData.append("test_password", document.getElementById("redis_password").value);
            
            fetch("?module=orrism_admin", {
                method: "POST",
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    resultDiv.innerHTML = \'<div class="orrism-alert orrism-alert-success">\' + data.message + \'</div>\';
                } else {
                    resultDiv.innerHTML = \'<div class="orrism-alert orrism-alert-danger">\' + data.message + \'</div>\';
                }
            })
            .catch(error => {
                resultDiv.innerHTML = \'<div class="orrism-alert orrism-alert-danger">Connection test failed: \' + error + \'</div>\';
            })
            .finally(() => {
                btn.disabled = false;
                btn.innerHTML = \'<i class="fa fa-plug"></i> Test Connection\';
            });
        }
        </script>';
    }
    
    /**
     * Render error message
     * 
     * @param Exception $e
     * @return string
     */
    protected function renderError($e)
    {
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
        
        return $errorOutput;
    }
    
    /**
     * Get node statistics for AJAX
     * 
     * @return array
     */
    protected function getNodeStatistics()
    {
        try {
            // Placeholder implementation
            return [
                'success' => true,
                'total_nodes' => 0,
                'active_nodes' => 0,
                'inactive_nodes' => 0
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error getting node statistics: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Get user statistics for AJAX
     * 
     * @return array
     */
    protected function getServiceStatistics()
    {
        try {
            $serviceManager = new ServiceManager();
            $stats = $serviceManager->getServiceStatistics();
            return array_merge(['success' => true], $stats);
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error getting service statistics: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Render service list table
     * 
     * @return string
     */
    protected function renderServerList()
    {
        try {
            // Get ORRISM services from database
            $serviceManager = new ServiceManager();
            $services = $serviceManager->getServiceList();
            
            $html = '<div class="table-responsive">';
            $html .= '<table class="table table-striped table-hover">';
            $html .= '<thead>';
            $html .= '<tr>';
            $html .= '<th>Service ID</th>';
            $html .= '<th>WHMCS Account</th>';
            $html .= '<th>Service Username</th>';
            $html .= '<th>Domain</th>';
            $html .= '<th>Status</th>';
            $html .= '<th>Traffic Used</th>';
            $html .= '<th>Traffic Limit</th>';
            $html .= '<th>Created</th>';
            $html .= '<th>Actions</th>';
            $html .= '</tr>';
            $html .= '</thead>';
            $html .= '<tbody>';
            
            if (empty($services['services'])) {
                $html .= '<tr><td colspan="9" class="text-center">No services found</td></tr>';
            } else {
                foreach ($services['services'] as $service) {
                    $statusClass = $service['status'] === 'active' ? 'success' : 'warning';
                    $html .= '<tr>';
                    $html .= '<td>' . htmlspecialchars($service['service_id'] ?? '-') . '</td>';
                    $html .= '<td>' . htmlspecialchars($service['whmcs_email'] ?? '-') . '</td>';
                    $html .= '<td>' . htmlspecialchars($service['service_username'] ?? '-') . '</td>';
                    $html .= '<td>' . htmlspecialchars($service['domain'] ?? '-') . '</td>';
                    $html .= '<td><span class="label label-' . $statusClass . '">' . ucfirst($service['status']) . '</span></td>';
                    $html .= '<td>' . $this->formatTraffic($service['upload_bytes'] + $service['download_bytes']) . '</td>';
                    $html .= '<td>' . $this->formatTraffic($service['bandwidth_limit'] ?? 0) . '</td>';
                    $html .= '<td>' . htmlspecialchars($service['created_at'] ?? '-') . '</td>';
                    $html .= '<td>';
                    $html .= '<button class="btn btn-xs btn-info" onclick="viewServiceDetails(' . $service['id'] . ')">View</button> ';
                    $html .= '<button class="btn btn-xs btn-warning" onclick="resetServiceTraffic(' . $service['id'] . ')">Reset</button>';
                    $html .= '</td>';
                    $html .= '</tr>';
                }
            }
            
            $html .= '</tbody>';
            $html .= '</table>';
            $html .= '</div>';
            
            // Add JavaScript for actions
            $html .= '<script>
            function viewServiceDetails(serviceId) {
                alert("View details for service #" + serviceId + " - Feature coming soon");
            }
            function resetServiceTraffic(serviceId) {
                if (confirm("Reset traffic for this service?")) {
                    alert("Reset traffic for service #" + serviceId + " - Feature coming soon");
                }
            }
            </script>';
            
            return $html;
            
        } catch (Exception $e) {
            return '<div class="alert alert-danger">Failed to load service list: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }
    
    /**
     * Format traffic bytes to readable format
     *
     * @param int $bytes
     * @return string
     */
    protected function formatTraffic($bytes)
    {
        if ($bytes == 0) return '0 B';

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $factor = floor((strlen($bytes) - 1) / 3);

        return sprintf("%.2f", $bytes / pow(1024, $factor)) . ' ' . $units[$factor];
    }

    /**
     * Handle node creation
     *
     * @return array
     */
    protected function handleNodeCreate()
    {
        try {
            // Load NodeManager
            require_once dirname(__DIR__, 2) . '/includes/node_manager.php';

            $nodeManager = new \NodeManager();

            // Collect node data from POST
            $nodeData = [
                'node_type' => $_POST['node_type'] ?? '',
                'node_name' => $_POST['node_name'] ?? '',
                'address' => $_POST['address'] ?? '',
                'port' => $_POST['port'] ?? '',
                'group_id' => $_POST['group_id'] ?? 1,
                'rate' => $_POST['rate'] ?? 1.0,
                'node_method' => $_POST['node_method'] ?? 'aes-256-gcm',
                'network_type' => $_POST['network_type'] ?? 'tcp',
                'status' => isset($_POST['status']) ? (int)$_POST['status'] : 1,
                'sort_order' => $_POST['sort_order'] ?? 0,
                'tag' => $_POST['tag'] ?? ''
            ];

            // Create node
            $result = $nodeManager->createNode($nodeData);

            // Log the operation
            error_log('Node create operation: ' . json_encode($result));

            return $result;

        } catch (Exception $e) {
            error_log('Node create error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to create node: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Handle node update
     *
     * @return array
     */
    protected function handleNodeUpdate()
    {
        try {
            // Load NodeManager
            require_once dirname(__DIR__, 2) . '/includes/node_manager.php';

            $nodeManager = new \NodeManager();

            $nodeId = $_POST['node_id'] ?? null;

            if (!$nodeId) {
                return [
                    'success' => false,
                    'message' => 'Node ID is required'
                ];
            }

            // Collect node data from POST
            $nodeData = [];

            $allowedFields = [
                'node_type', 'node_name', 'address', 'port',
                'group_id', 'rate', 'node_method', 'network_type',
                'status', 'sort_order', 'tag'
            ];

            foreach ($allowedFields as $field) {
                if (isset($_POST[$field])) {
                    $nodeData[$field] = $_POST[$field];
                }
            }

            // Update node
            $result = $nodeManager->updateNode($nodeId, $nodeData);

            // Log the operation
            error_log('Node update operation for ID ' . $nodeId . ': ' . json_encode($result));

            return $result;

        } catch (Exception $e) {
            error_log('Node update error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to update node: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Handle get node details
     *
     * @return array
     */
    protected function handleNodeGet()
    {
        try {
            // Load NodeManager
            require_once dirname(__DIR__, 2) . '/includes/node_manager.php';

            $nodeManager = new \NodeManager();

            $nodeId = $_POST['node_id'] ?? $_GET['node_id'] ?? null;

            if (!$nodeId) {
                return [
                    'success' => false,
                    'message' => 'Node ID is required'
                ];
            }

            return $nodeManager->getNode($nodeId);

        } catch (Exception $e) {
            error_log('Node get error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to get node: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Handle node deletion
     *
     * @return array
     */
    protected function handleNodeDelete()
    {
        try {
            // Load NodeManager
            require_once dirname(__DIR__, 2) . '/includes/node_manager.php';

            $nodeManager = new \NodeManager();

            $nodeId = $_POST['node_id'] ?? null;

            if (!$nodeId) {
                return [
                    'success' => false,
                    'message' => 'Node ID is required'
                ];
            }

            $result = $nodeManager->deleteNode($nodeId);

            // Log the operation
            error_log('Node delete operation for ID ' . $nodeId . ': ' . json_encode($result));

            return $result;

        } catch (Exception $e) {
            error_log('Node delete error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to delete node: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Handle node status toggle
     *
     * @return array
     */
    protected function handleNodeToggle()
    {
        try {
            // Load NodeManager
            require_once dirname(__DIR__, 2) . '/includes/node_manager.php';

            $nodeManager = new \NodeManager();

            $nodeId = $_POST['node_id'] ?? null;

            if (!$nodeId) {
                return [
                    'success' => false,
                    'message' => 'Node ID is required'
                ];
            }

            $result = $nodeManager->toggleNodeStatus($nodeId);

            // Log the operation
            error_log('Node toggle operation for ID ' . $nodeId . ': ' . json_encode($result));

            return $result;

        } catch (Exception $e) {
            error_log('Node toggle error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to toggle node: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Handle batch node operations
     *
     * @return array
     */
    protected function handleNodeBatch()
    {
        try {
            // Load NodeManager
            require_once dirname(__DIR__, 2) . '/includes/node_manager.php';

            $nodeManager = new \NodeManager();

            $nodeIds = $_POST['node_ids'] ?? [];
            $action = $_POST['batch_action'] ?? '';

            if (empty($nodeIds)) {
                return [
                    'success' => false,
                    'message' => 'No nodes selected'
                ];
            }

            if (empty($action)) {
                return [
                    'success' => false,
                    'message' => 'No action specified'
                ];
            }

            $data = $_POST;

            $result = $nodeManager->batchUpdateNodes($nodeIds, $action, $data);

            // Log the operation
            error_log('Node batch operation (' . $action . ') for IDs ' . implode(',', $nodeIds) . ': ' . json_encode($result));

            return $result;

        } catch (Exception $e) {
            error_log('Node batch error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Batch operation failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Handle get encryption methods for node type
     *
     * @return array
     */
    protected function handleNodeGetMethods()
    {
        try {
            // Load NodeManager
            require_once dirname(__DIR__, 2) . '/includes/node_manager.php';

            $nodeManager = new \NodeManager();

            $nodeType = $_POST['node_type'] ?? $_GET['node_type'] ?? '';

            if (empty($nodeType)) {
                return [
                    'success' => false,
                    'message' => 'Node type is required'
                ];
            }

            $methods = $nodeManager->getEncryptionMethods($nodeType);

            return [
                'success' => true,
                'methods' => $methods
            ];

        } catch (Exception $e) {
            error_log('Node get methods error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to get encryption methods: ' . $e->getMessage()
            ];
        }
    }
}