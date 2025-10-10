<?php
/**
 * ORRISM Manager Module for WHMCS
 *
 * @package    WHMCS
 * @author     ORRIS Development Team
 * @copyright  Copyright (c) 2022-2024
 * @version    1.0
 */

// Initialize WHMCS environment for direct API access
if (!defined('WHMCS')) {
    define('WHMCS', true);
    $init_path = __DIR__ . '/../../../../init.php';
    if (file_exists($init_path)) {
        require_once $init_path;
    }
}

// Service management business module
require_once __DIR__ . '/../helper.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/../lib/uuid.php'; // 确保加载我们的UUID库
// 不再直接使用Ramsey\Uuid，而是使用我们的兼容函数
// use Ramsey\Uuid\Uuid;
use WHMCS\Database\Capsule;

/**
 * 清除Smarty模板缓存
 * @param string $template 模板名称
 * @return bool 是否成功清除
 */
function orrism_smarty_clear_compiled_tpl($template = 'details.tpl') {
    try {
        // 尝试获取Smarty对象
        global $smarty;
        
        // 如果没有全局Smarty对象，尝试创建一个
        if (!isset($smarty) || !is_object($smarty)) {
            // 清除文件系统中的缓存
            $templates_c_dir = ROOTDIR . '/templates_c/';
            if (is_dir($templates_c_dir)) {
                $files = glob($templates_c_dir . "*{$template}*");
                foreach ($files as $file) {
                    if (is_file($file)) {
                        @unlink($file);
                    }
                }
            }
            
            return true;
        }
        
        // 如果是Smarty对象，使用其方法清除
        if (method_exists($smarty, 'clearCompiledTemplate')) {
            $smarty->clearCompiledTemplate($template);
        }
        
        return true;
    } catch (Exception $e) {
        //error_log('清除模板缓存失败: ' . $e->getMessage());
        return false;
    }
}

function orris_user_create_account($params) {
    $pid = $params['serviceid'] ?? 0;
    $productidResult = Capsule::table('tblhostingconfigoptions')
        ->where('relid', $pid)
        ->get();
    $bandwidthInBytes = 0;
    foreach ($productidResult as $configOption) {
        $optionId = $configOption->optionid;
        $configId = $configOption->configid;
        $optionNameResult = Capsule::table('tblproductconfigoptionssub')
            ->where('configid', $configId)
            ->where('id', $optionId)
            ->first();
        if ($optionNameResult) {
            $optionName = intval($optionNameResult->optionname);
            $bandwidthInBytes = $optionName * 1024 * 1024 * 1024;
        }
    }
    if ($bandwidthInBytes === 0) {
        $bandwidthInBytes = isset($params['configoption4']) ? $params['configoption4'] * 1024 * 1024 * 1024 : 0;
    }
    $customFeature = '';
    $data = [
        'email'         => $params['clientsdetails']['email'] ?? '',
        'uuid'          => orris_uuid4(), // 使用我们的函数替代Uuid::uuid4()
        'token'         => orris_generate_md5_token(),
        'sid'           => $params['serviceid'] ?? 0,
        'package_id'    => $params['pid'] ?? 0,
        'telegram_id'   => 0,
        'enable'        => 1,
        'need_reset'    => $params['configoption5'] ?? 0,
        'node_group_id' => $params['configoption7'] ?? 0,
        'bandwidth'     => $bandwidthInBytes,
        'custom_feature'=> $customFeature
    ];
    return orris_new_account($data);
}

function orris_user_suspend_account($params) {
    $sid = $params['serviceid'] ?? 0;
    $data = [
        'sid'    => $sid,
        'action' => 0,
    ];
    return orris_set_status($data);
}

function orris_user_unsuspend_account($params) {
    $sid = $params['serviceid'] ?? 0;
    $data = [
        'sid'    => $sid,
        'action' => 1,
    ];
    return orris_set_status($data);
}

function orris_user_terminate_account($params) {
    $sid = $params['serviceid'] ?? 0;
    $data = [ 
        'sid' => $sid
    ];
    return orris_delete_account($data);
}

function orris_user_reset_uuid($params) {
    $sid = $params['serviceid'] ?? 0;
    $new_uuid = orris_uuid4(); // Generate the new UUID
    $data = [
        'sid'  => $sid,
        'uuid' => $new_uuid
    ];
    
    $success = orris_reset_uuid_internal($data); // This now returns true or false
    
    if ($success) {
        // Log success with the new UUID
        //error_log("ORRIS_INFO: UUID and Token reset successful for service ID {$sid}. New UUID: {$new_uuid}");
        return [
            'status'   => 'success',
            'msg'      => ORRIS_L::product_reset_uuid_success,
            'new_uuid' => $new_uuid // Optionally include the new UUID in the response
        ];
    } else {
        // Error details are already logged by orris_reset_uuid_internal
        //error_log("ORRIS_ERROR: UUID and Token reset failed for service ID {$sid}. Check previous logs for details.");
        return [
            'status' => 'error',
            'msg'    => "UUID/Token重置失败，请检查系统日志。" // Generic error message for the client
        ];
    }
}

function orris_user_admin_services_tab_fields($params) {
    try {
        $user = orris_get_user($params['serviceid'] ?? 0)[0];
        $result = [
            'uuid'                => $user['uuid'],
            ORRIS_L::admin_bandwidth    => orris_convert_byte($user['bandwidth']),
            ORRIS_L::common_upload      => orris_convert_byte($user['u']),
            ORRIS_L::common_download    => orris_convert_byte($user['d']),
            ORRIS_L::common_left        => orris_convert_byte($user['bandwidth'] - ($user['u'] + $user['d'])),
            ORRIS_L::common_used        => orris_convert_byte($user['u'] + $user['d']),
            ORRIS_L::common_created_at  => $user['created_at'],
        ];
        return $result;
    } catch (Exception $e) {
        return $e;
    }
}

function orris_user_admin_custom_button_array() {
    return [
        ORRIS_L::product_reset_bandwidth => 'reset_bandwidth_admin',
        ORRIS_L::product_reset_uuid      => 'module_reset_uuid',
    ];
}

/**
 * 客户区展示
 * @param array $params
 * @return array
 */
function orris_user_client_area($params) {
    // 确保清除所有可能的输出缓冲，防止与Laminas冲突
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    // 清除模板缓存，确保使用最新的常量
    smarty_clear_compiled_tpl('details.tpl');
    
    if (!class_exists('ORRIS_L')) {
        return [
            'tabOverviewReplacementTemplate' => 'error.tpl',
            'templateVariables' => [
                'error' => 'Class ORRIS_L not found',
            ],
        ];
    }
    if (!defined('ORRIS_L::admin_bandwidth')) {
        return [
            'tabOverviewReplacementTemplate' => 'error.tpl',
            'templateVariables' => [
                'error' => 'ORRIS_L::admin_bandwidth not defined',
            ],
        ];
    }
    
    // 处理客户区AJAX请求
    if (isset($_GET['ssmAction'])) {
        try {
            switch ($_GET['ssmAction']) {
                case 'ResetUUID':
                    if (isset($_GET['sid']) && $_GET['sid'] == ($params['serviceid'] ?? 0)) {
                        $reset_result = orris_user_reset_uuid($params); // Get the structured result
                        
                        header('Content-Type: application/json');
                        echo json_encode($reset_result); // Output the actual result to the client
                        
                        // Log based on the actual outcome
                        if ($reset_result['status'] === 'success') {
                            //error_log("ORRIS_INFO: Client Area - UUID reset successful for service ID {$params['serviceid']}. Response: " . json_encode($reset_result));
                        } else {
                            //error_log("ORRIS_ERROR: Client Area - UUID reset failed for service ID {$params['serviceid']}. Response: " . json_encode($reset_result));
                        }
                        exit;
                    } else {
                        header('Content-Type: application/json');
                        echo json_encode([
                            'status' => 'error',
                            'msg'    => ORRIS_L::common_prohibit
                        ]);
                        exit;
                    }
                    break;
                case 'ResetBandwidth':
                    if (isset($_GET['sid']) && $_GET['sid'] == ($params['serviceid'] ?? 0)) {
                        // 直接输出JSON响应
                        orris_traffic_reset_bandwidth_user($params);
                        header('Content-Type: application/json');
                        echo json_encode([
                            'status' => 'success',
                            'msg'    => ORRIS_L::traffic_reset_success
                        ]);
                        exit;
                    } else {
                        header('Content-Type: application/json');
                        echo json_encode([
                            'status' => 'error',
                            'msg'    => ORRIS_L::common_prohibit
                        ]);
                        exit;
                    }
                    break;
                default:
                    break;
            }
        } catch (Exception $e) {
            // 发生异常时返回错误信息
            return [
                'tabOverviewReplacementTemplate' => 'error.tpl',
                'templateVariables' => [
                    'error' => $e->getMessage(),
                ],
            ];
        }
    }
    
    try {
        $service_id = $params['serviceid'] ?? 0;
        
        // 获取用户信息
        $user = orris_get_user($service_id);
        if ($user) {
            $user_traffic_total = $user[0]['u'] + $user[0]['d'];
            $user_traffic_upload = $user[0]['u'];
            $user_traffic_download = $user[0]['d'];
            $bandwidth = $user[0]['bandwidth'];
            $left = $bandwidth - $user_traffic_total;
            $uuid = $user[0]['uuid'];
            $telegram_id = $user[0]['telegram_id'];
            $sid = $user[0]['sid'];
            $created_at = $user[0]['created_at'];
            $token = $user[0]['token'];
            $info = [
                'uuid'        => $uuid,
                'upload'      => orris_convert_byte($user_traffic_upload),
                'download'    => orris_convert_byte($user_traffic_download),
                'total_used'  => orris_convert_byte($user_traffic_total),
                'left'        => orris_convert_byte($left),
                'created_at'  => $created_at,
                'telegram_id' => $telegram_id,
                'bandwidth'   => orris_convert_byte($bandwidth),
                'sid'         => $sid,
                'token'       => $token
            ];
            
            // 获取节点信息，添加错误处理
            try {
                $nodes = orris_get_nodes($service_id);
                //error_log("成功获取服务 #{$service_id} 的节点列表：" . count($nodes) . " 个节点");
            } catch (Exception $e) {
                //error_log("获取服务 #{$service_id} 的节点列表时出错: " . $e->getMessage());
                $nodes = [];
            }
            
            // 确保获取到有效的到期日期
            $nextduedate = '';
            if (!empty($params['nextduedate'])) {
                // 尝试将日期转换为更友好的格式
                try {
                    $date = new DateTime($params['nextduedate']);
                    $nextduedate = $date->format('Y-m-d');
                } catch (Exception $e) {
                    $nextduedate = $params['nextduedate'];
                }
            } else {
                // 如果模块中没有，尝试从WHMCS数据库获取
                try {
                    $hosting = Capsule::table('tblhosting')
                        ->where('id', $service_id)
                        ->first();
                    if ($hosting && !empty($hosting->nextduedate)) {
                        $date = new DateTime($hosting->nextduedate);
                        $nextduedate = $date->format('Y-m-d');
                    }
                } catch (Exception $e) {
                    //error_log("获取到期日期时出错: " . $e->getMessage());
                }
            }
            
            // 获取产品名称
            $productName = $params['product'] ?? '';
            if (empty($productName)) {
                try {
                    // 尝试从数据库获取产品名称
                    $hosting = isset($hosting) ? $hosting : Capsule::table('tblhosting')
                        ->where('id', $service_id)
                        ->first();
                        
                    if ($hosting && !empty($hosting->packageid)) {
                        $product = Capsule::table('tblproducts')
                            ->where('id', $hosting->packageid)
                            ->first();
                            
                        if ($product && !empty($product->name)) {
                            $productName = $product->name;
                        }
                    }
                } catch (Exception $e) {
                    //error_log("获取产品名称时出错: " . $e->getMessage());
                }
            }
            
            // 获取订阅拉取记录
            try {
                // 引入checkmate/utils.php
                require_once dirname(__FILE__) . '/checkmate/utils.php';
                $subscription_records = Utils::getSubscriptionRecords($service_id);
            } catch (Exception $e) {
                //error_log("获取订阅记录时出错: " . $e->getMessage());
                $subscription_records = [];
            }
            
            return [
                'tabOverviewReplacementTemplate' => 'details.tpl',
                'templateVariables' => [
                    'user'          => $info,
                    'node'          => $nodes,
                    'product'       => $productName,
                    'serviceid'     => $service_id,
                    'nextduedate'   => $nextduedate,
                    'subscribe_url' => orris_get_config()['subscribe_url'] ?? '',
                    'subscription_records' => $subscription_records,
                ],
            ];
        } else {
            return [
                'tabOverviewReplacementTemplate' => 'error.tpl',
                'templateVariables' => [
                    'error' => ORRIS_L::error_account_not_found,
                ],
            ];
        }
    } catch (Exception $e) {
        //error_log("客户区加载错误: " . $e->getMessage());
        return [
            'tabOverviewReplacementTemplate' => 'error.tpl',
            'templateVariables' => [
                'error' => "加载失败: " . $e->getMessage(),
            ],
        ];
    }
}

/**
 * Get service information
 * @param int $sid Service ID
 * @return array
 */
function orris_get_user($sid) {
    $redis_key = 'uuid'.$sid;
    $conn = orris_get_db_connection();
    $sql = 'SELECT * FROM user WHERE `sid` = :sid';
    $db = $conn->prepare($sql);
    $db->bindValue(':sid', $sid);
    $db->execute();
    return $db->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Create new service account
 * @param array $data
 * @return bool|string|Exception
 */
function orris_new_account($data){
    try {
        if (empty(orris_get_user($data['sid']))){
            $conn = orris_get_db_connection();
            $insert = 'INSERT INTO `user`(`email`,`uuid`,`u`,`d`,`bandwidth`,`created_at`,`updated_at`,`need_reset`,`sid`,`package_id`,`enable`,`telegram_id`,`token`,`node_group_id`) VALUES (:email,:uuid,0,0,:bandwidth,UNIX_TIMESTAMP(),0,:need_reset,:sid,:package_id,:enable,:telegram_id,:token,:node_group_id)';
            $action = $conn->prepare($insert);
            $params = [
                ':email' => $data['email'],
                ':uuid' => $data['uuid'],
                ':need_reset' => $data['need_reset'],
                ':sid' => $data['sid'],
                ':package_id' => $data['package_id'],
                ':enable' => $data['enable'],
                ':telegram_id' => $data['telegram_id'],
                ':token' => $data['token'],
                ':bandwidth' => $data['bandwidth'],
                ':node_group_id' => $data['node_group_id']
            ];
            foreach ($params as $key => $value) {
                $action->bindValue($key, $value);
            }
            orris_set_redis($data['sid'], $data['token'], 'set');
            orris_set_redis("node_group_id_sid_{$data['sid']}", $data['node_group_id'], 'set');
            orris_set_redis("uuid{$data['sid']}", $data['uuid'], 'set');
            return $action->execute();
        } else{
            return ORRIS_L::error_account_already_exists;
        }
    } catch (Exception $e){
        return $e;
    }
}

/**
 * Set service status
 * @param array $data
 * @return bool|string|Exception
 */
function orris_set_status($data){
    //error_log("ORRIS_DEBUG: orris_set_status called with data: " . print_r($data, true)); // Log input data
    try {
        $user_exists = count(orris_get_user($data['sid'])) > 0;
        //error_log("ORRIS_DEBUG: User exists for sid " . ($data['sid'] ?? 'N/A') . "? " . ($user_exists ? 'Yes' : 'No')); // Log user existence

        if ($user_exists){
            $conn = orris_get_db_connection();
            $db = $conn->prepare('UPDATE `user` SET `enable` = :enable WHERE `sid` = :sid');
            $db->bindValue(':enable',$data['action']);
            $db->bindValue(':sid',$data['sid']);
            $execute_result = $db->execute();
            //error_log("ORRIS_DEBUG: Database execute result for sid " . ($data['sid'] ?? 'N/A') . ": " . ($execute_result ? 'Success' : 'Failure')); // Log execution result
            return $execute_result;
        } else{
            //error_log("ORRIS_DEBUG: User not found for sid " . ($data['sid'] ?? 'N/A') . ". Returning error_account_not_found."); // Log user not found
            return ORRIS_L::error_account_not_found;
        }
    } catch (Exception $e){
        //error_log("ORRIS_DEBUG: Exception in orris_set_status for sid " . ($data['sid'] ?? 'N/A') . ": " . $e->getMessage()); // Log exception
        return $e;
    }
}

/**
 * Delete service account
 * @param array $data
 * @return bool|string|Exception
 */
function orris_delete_account($data){
    try {
        if (count(orris_get_user($data['sid'])) > 0){
            $conn = orris_get_db_connection();
            $db = $conn->prepare('DELETE FROM `user` WHERE `sid` = :sid');
            $db->bindValue(':sid',$data['sid']);
            //orris_set_redis($data['sid'],null,'del');
            return $db->execute();
        } else{
            return ORRIS_L::error_account_not_found;
        }
    } catch (Exception $e){
        return $e;
    }
}

/**
 * Reset service UUID
 * @param array $data
 * @return bool|string|Exception
 */
function orris_reset_uuid_internal($data){
    try {
        // Call orris_get_user once to check existence
        $user_info = orris_get_user($data['sid']);
        if (count($user_info) > 0) {
            $conn = orris_get_db_connection();
            $new_token = orris_generate_md5_token(); // Generate a new token
            // $data['uuid'] is the new UUID passed from orris_user_reset_uuid
            $db = $conn->prepare('UPDATE `user` SET `uuid` = :uuid, `token` = :token WHERE `sid` = :sid');
            $db->bindValue(':sid', $data['sid']);
            $db->bindValue(':uuid', $data['uuid']);
            $db->bindValue(':token', $new_token);
            
            if ($db->execute()) {
                // Clear and set Redis cache for UUID
                orris_set_redis('uuid'.$data['sid'], null, 'del', 0);
                orris_set_redis('uuid'.$data['sid'], $data['uuid'], 'set', 0);
                // Clear and set Redis cache for token (using sid as key for token)
                orris_set_redis($data['sid'], null, 'del', 0); 
                orris_set_redis($data['sid'], $new_token, 'set', 0);
                return true; // Successfully updated DB and Redis
            } else {
                //error_log("ORRIS_ERROR: Database execute failed in orris_reset_uuid_internal for SID: " . ($data['sid'] ?? 'N/A'));
                return false; // Database execution failed
            }
        } else {
            //error_log("ORRIS_ERROR: User not found in orris_reset_uuid_internal for SID: " . ($data['sid'] ?? 'N/A'));
            return false; // User not found
        }
    } catch (Exception $e) {
        //error_log("ORRIS_ERROR: Exception in orris_reset_uuid_internal for SID: " . ($data['sid'] ?? 'N/A') . " - " . $e->getMessage());
        return false; // An exception occurred
    }
}

/**
 * Set service bandwidth
 * @param array $data
 * @return bool|string|Exception
 */
function orris_set_bandwidth($data){
    try {
        if (count(orris_get_user($data['sid'])) > 0) {
            $conn = orris_get_db_connection();
            $db = $conn->prepare('UPDATE `user` SET `u` = :u , `d` = :d  WHERE `sid` = :sid');
            $db->bindValue(':u', $data['u']);
            $db->bindValue(':d',$data['d']);
            $db->bindValue(':sid', $data['sid']);
            return $db->execute();
        }else{
            return ORRIS_L::error_account_not_found;
        }
    } catch (Exception $e){
        return $e;
    }
}

/**
 * Reset service traffic
 * @param int $sid Service ID
 */
function orris_reset_user_traffic($sid) {
    if (orris_get_user($sid) != null){
        $reset_traffic = array(
            'u' => 0,
            'd' => 0,
            'sid' => $sid
        );
        orris_set_bandwidth($reset_traffic);
    }
}

/**
 * Get service UUID
 * @param int $sid Service ID
 * @return string|null
 */
function orris_get_uuid($sid){
    $redis_key = 'uuid'.$sid;
    $redis = orris_get_redis_connection(0);
    $redis = orris_set_redis($redis_key, null, 'get', 0);
    if ($redis) {
        return $redis;
    }
    $conn = orris_get_db_connection();
    $sql = 'SELECT uuid FROM user WHERE `sid` = :sid';
    $db = $conn->prepare($sql);
    $db->bindValue(':sid', $sid, PDO::PARAM_INT);
    $db->execute();
    
    $row = $db->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return null; // User not found
    }
    
    $result = $row['uuid'];
    // Store in Redis with longer expiration (24 hours)
    $redis = orris_get_redis_connection(0);
    $redis->set($redis_key, $result, 86400);
    
    return $result;
}

/**
 * Get service TOKEN
 * @param int $sid Service ID
 * @return string|null
 */
function orris_get_token($sid){
    $conn = orris_get_db_connection();
    $sql = 'SELECT token FROM user WHERE `sid` = :sid';
    $db = $conn->prepare($sql);
    $db->bindValue(':sid', $sid, PDO::PARAM_INT);
    $db->execute();
    
    $row = $db->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return null; // User not found
    }
    
    $result = $row['token'];
    return $result;
}

/**
 * Get available nodes for service
 * @param int $sid Service ID
 * @return array Node list
 */
function orris_get_nodes($sid) {
    try {
        // 获取用户信息，主要是获取node_group_id
        $user = orris_get_user($sid);
        if (empty($user)) {
            return [];
        }
        
        $node_group_id = $user[0]['node_group_id'] ?? 0;
        // error_log("node_group_id: " . $node_group_id);
        // 连接数据库
        $conn = orris_get_db_connection();
        
        // 如果有node_group_id，获取该组的节点
        if ($node_group_id > 0) {
            $sql = 'SELECT * FROM nodes WHERE group_id = :node_group_id AND enable = 1';
            $stmt = $conn->prepare($sql);
            $stmt->bindValue(':node_group_id', $node_group_id, PDO::PARAM_STR); // group_id为text类型
        } else {
            // 否则获取全部状态为1的节点
            $sql = 'SELECT * FROM nodes WHERE enable = 1';
            $stmt = $conn->prepare($sql);
        }
        
        $stmt->execute();
        $nodes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        // error_log("nodes: " . json_encode($nodes));
        
        // 处理返回结果，添加一些额外信息
        foreach ($nodes as &$node) {
            // 计算节点负载情况
            $node['load'] = isset($node['online_user']) && isset($node['max_user']) && $node['max_user'] > 0
                ? round(($node['online_user'] / $node['max_user']) * 100, 1)
                : 0;
                
            // 添加节点状态标签
            $node['status_label'] = $node['enable'] == 1 ? '正常' : '维护中';
        }
        
        return $nodes;
    } catch (Exception $e) {
        //error_log("获取节点列表失败: " . $e->getMessage());
        return [];
    }
}

// ============================================
// Service API Endpoint Handler
// ============================================

/**
 * Handle direct service API requests
 */
if (php_sapi_name() !== 'cli' && !defined('ORRISM_API_INCLUDED')) {
    // Set JSON response header
    header('Content-Type: application/json; charset=utf-8');

    // Enable CORS
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-API-Key');

    // Handle OPTIONS request
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }

    try {
        // Get action from request
        $action = $_GET['action'] ?? $_POST['action'] ?? 'list';

        // Route to appropriate function
        switch ($action) {
            case 'list':
            case 'get_list':
                // Get service list
                $limit = (int)($_GET['limit'] ?? 100);
                $offset = (int)($_GET['offset'] ?? 0);

                // Use OrrisDB or Capsule to query services table
                if (class_exists('OrrisDB') && OrrisDB::isConfigured()) {
                    $services = OrrisDB::table('services')
                        ->select('id', 'service_id', 'email', 'uuid', 'status', 'bandwidth_limit',
                                 'upload_bytes', 'download_bytes', 'created_at', 'updated_at')
                        ->limit($limit)
                        ->offset($offset)
                        ->get();
                } else {
                    // Fallback: try to get from addon database using PDO
                    $conn = orris_get_db_connection();
                    $sql = "SELECT id, service_id, email, uuid, status, bandwidth_limit,
                            upload_bytes, download_bytes, created_at, updated_at
                            FROM services LIMIT :limit OFFSET :offset";
                    $stmt = $conn->prepare($sql);
                    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
                    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
                    $stmt->execute();
                    $services = $stmt->fetchAll(PDO::FETCH_ASSOC);
                }

                echo json_encode([
                    'success' => true,
                    'count' => count($services),
                    'limit' => $limit,
                    'offset' => $offset,
                    'data' => $services
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                break;

            case 'get':
            case 'info':
                // Get single service
                $id = $_GET['id'] ?? $_POST['id'] ?? null;
                if (!$id) {
                    throw new Exception('Service ID is required');
                }

                if (class_exists('OrrisDB') && OrrisDB::isConfigured()) {
                    $service = OrrisDB::table('services')
                        ->where('id', $id)
                        ->orWhere('service_id', $id)
                        ->first();
                } else {
                    $conn = orris_get_db_connection();
                    $sql = "SELECT id, service_id, email, uuid, status, bandwidth_limit,
                            upload_bytes, download_bytes, created_at, updated_at
                            FROM services WHERE id = :id OR service_id = :sid";
                    $stmt = $conn->prepare($sql);
                    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
                    $stmt->bindValue(':sid', $id, PDO::PARAM_INT);
                    $stmt->execute();
                    $service = $stmt->fetch(PDO::FETCH_ASSOC);
                }

                if (!$service) {
                    throw new Exception('Service not found');
                }

                echo json_encode([
                    'success' => true,
                    'data' => $service
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                break;

            case 'traffic':
            case 'stats':
                // Get service traffic stats
                $id = $_GET['id'] ?? $_POST['id'] ?? null;
                if (!$id) {
                    throw new Exception('Service ID is required');
                }

                if (class_exists('OrrisDB') && OrrisDB::isConfigured()) {
                    $service = OrrisDB::table('services')
                        ->where('id', $id)
                        ->orWhere('service_id', $id)
                        ->first();
                } else {
                    $conn = orris_get_db_connection();
                    $sql = "SELECT id, service_id, email, uuid, status, bandwidth_limit,
                            upload_bytes, download_bytes
                            FROM services WHERE id = :id OR service_id = :sid";
                    $stmt = $conn->prepare($sql);
                    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
                    $stmt->bindValue(':sid', $id, PDO::PARAM_INT);
                    $stmt->execute();
                    $service = $stmt->fetch(PDO::FETCH_ASSOC);
                }

                if (!$service) {
                    throw new Exception('Service not found');
                }

                // Handle both object and array formats
                $uploadBytes = is_object($service) ? $service->upload_bytes : $service['upload_bytes'];
                $downloadBytes = is_object($service) ? $service->download_bytes : $service['download_bytes'];
                $bandwidthLimit = is_object($service) ? $service->bandwidth_limit : $service['bandwidth_limit'];

                $totalUsage = $uploadBytes + $downloadBytes;
                $usagePercent = $bandwidthLimit > 0
                    ? round($totalUsage / $bandwidthLimit * 100, 2)
                    : 0;

                echo json_encode([
                    'success' => true,
                    'data' => [
                        'service_id' => is_object($service) ? $service->service_id : $service['service_id'],
                        'email' => is_object($service) ? $service->email : $service['email'],
                        'bandwidth_limit' => $bandwidthLimit,
                        'upload_bytes' => $uploadBytes,
                        'download_bytes' => $downloadBytes,
                        'total_usage' => $totalUsage,
                        'usage_percent' => $usagePercent,
                        'status' => is_object($service) ? $service->status : $service['status']
                    ]
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                break;

            default:
                throw new Exception('Unknown action: ' . $action);
        }

    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    exit;
}
