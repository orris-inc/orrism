<?php
/**
 * ORRISM - ShadowSocks Manager Module for WHMCS
 *
 * @package    WHMCS
 * @author     ORRISM Development Team
 * @copyright  Copyright (c) 2022-2024
 * @version    1.0
 */


// 用户相关业务模块
require_once __DIR__ . '/../helper.php';
require_once __DIR__ . '/../lib/database.php';
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

function orrism_user_create_account($params) {
    try {
        $serviceId = $params['serviceid'] ?? 0;
        
        // Get bandwidth from config options
        $bandwidthInBytes = OrrisHelper::gbToBytes($params['configoption4'] ?? 100);
        
        // Check for product-specific config options
        $productOptions = Capsule::table('tblhostingconfigoptions')
            ->where('relid', $serviceId)
            ->get();
            
        foreach ($productOptions as $option) {
            $optionDetail = Capsule::table('tblproductconfigoptionssub')
                ->where('configid', $option->configid)
                ->where('id', $option->optionid)
                ->first();
            if ($optionDetail && is_numeric($optionDetail->optionname)) {
                $bandwidthInBytes = OrrisHelper::gbToBytes(intval($optionDetail->optionname));
                break;
            }
        }
        
        $userData = [
            'email' => OrrisHelper::sanitizeEmail($params['clientsdetails']['email'] ?? ''),
            'uuid' => OrrisHelper::generateUuid(),
            'token' => OrrisHelper::generateMd5Token(),
            'sid' => $serviceId,
            'package_id' => $params['pid'] ?? 0,
            'telegram_id' => 0,
            'enable' => 1,
            'need_reset' => $params['configoption5'] ?? 0,
            'node_group_id' => $params['configoption7'] ?? 1,
            'bandwidth' => $bandwidthInBytes,
            'custom_feature' => ''
        ];
        
        $db = OrrisDatabase::getInstance();
        $result = $db->createUser($userData);
        
        return $result ? 'success' : 'Account creation failed';
        
    } catch (Exception $e) {
        OrrisHelper::log('error', 'Account creation failed', [
            'service_id' => $params['serviceid'] ?? 'unknown',
            'error' => $e->getMessage()
        ]);
        return 'Account creation failed: ' . $e->getMessage();
    }
}

function orrism_user_suspend_account($params) {
    $serviceId = $params['serviceid'] ?? 0;
    $db = OrrisDatabase::getInstance();
    $result = $db->updateUserStatus($serviceId, 0);
    return $result ? 'success' : 'Suspend failed';
}

function orrism_user_unsuspend_account($params) {
    $serviceId = $params['serviceid'] ?? 0;
    $db = OrrisDatabase::getInstance();
    $result = $db->updateUserStatus($serviceId, 1);
    return $result ? 'success' : 'Unsuspend failed';
}

function orrism_user_terminate_account($params) {
    $serviceId = $params['serviceid'] ?? 0;
    $db = OrrisDatabase::getInstance();
    $result = $db->deleteUser($serviceId);
    return $result ? 'success' : 'Termination failed';
}

function orrism_user_reset_uuid($params) {
    $serviceId = $params['serviceid'] ?? 0;
    $newUuid = OrrisHelper::generateUuid();
    
    $db = OrrisDatabase::getInstance();
    $success = $db->resetUserUuid($serviceId, $newUuid);
    
    if ($success) {
        return [
            'status' => 'success',
            'msg' => ORRISM_L::product_reset_uuid_success,
            'new_uuid' => $newUuid
        ];
    } else {
        return [
            'status' => 'error',
            'msg' => 'UUID reset failed, please check system logs'
        ];
    }
}

function orrism_user_admin_services_tab_fields($params) {
    try {
        $db = OrrisDatabase::getInstance();
        $user = $db->getUser($params['serviceid'] ?? 0);
        
        if (!$user) {
            return ['error' => 'User not found'];
        }
        
        $totalUsed = $user['u'] + $user['d'];
        $remaining = max(0, $user['bandwidth'] - $totalUsed);
        
        return [
            'uuid' => $user['uuid'],
            ORRISM_L::admin_bandwidth => OrrisHelper::formatBytes($user['bandwidth']),
            ORRISM_L::common_upload => OrrisHelper::formatBytes($user['u']),
            ORRISM_L::common_download => OrrisHelper::formatBytes($user['d']),
            ORRISM_L::common_left => OrrisHelper::formatBytes($remaining),
            ORRISM_L::common_used => OrrisHelper::formatBytes($totalUsed),
            ORRISM_L::common_created_at => date('Y-m-d H:i:s', $user['created_at']),
            'Usage Percentage' => OrrisHelper::calculateUsagePercentage($totalUsed, $user['bandwidth']) . '%'
        ];
    } catch (Exception $e) {
        OrrisHelper::log('error', 'Failed to get admin tab fields', [
            'service_id' => $params['serviceid'] ?? 'unknown',
            'error' => $e->getMessage()
        ]);
        return ['error' => $e->getMessage()];
    }
}

function orrism_user_admin_custom_button_array() {
    return [
        ORRISM_L::product_reset_bandwidth => 'reset_bandwidth_admin',
        ORRISM_L::product_reset_uuid      => 'module_reset_uuid',
    ];
}

/**
 * 客户区展示
 * @param array $params
 * @return array
 */
function orrism_user_client_area($params) {
    // 确保清除所有可能的输出缓冲，防止与Laminas冲突
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    // 清除模板缓存，确保使用最新的常量
    orrism_smarty_clear_compiled_tpl('details.tpl');
    
    if (!class_exists('ORRISM_L')) {
        return [
            'tabOverviewReplacementTemplate' => 'error.tpl',
            'templateVariables' => [
                'error' => 'Class ORRISM_L not found',
            ],
        ];
    }
    if (!defined('ORRISM_L::admin_bandwidth')) {
        return [
            'tabOverviewReplacementTemplate' => 'error.tpl',
            'templateVariables' => [
                'error' => 'ORRISM_L::admin_bandwidth not defined',
            ],
        ];
    }
    
    // 处理客户区AJAX请求
    if (isset($_GET['ssmAction'])) {
        try {
            switch ($_GET['ssmAction']) {
                case 'ResetUUID':
                    if (isset($_GET['sid']) && $_GET['sid'] == ($params['serviceid'] ?? 0)) {
                        $reset_result = orrism_user_reset_uuid($params); // Get the structured result
                        
                        header('Content-Type: application/json');
                        echo json_encode($reset_result); // Output the actual result to the client
                        
                        // Log based on the actual outcome
                        if ($reset_result['status'] === 'success') {
                            //error_log("ORRISM_INFO: Client Area - UUID reset successful for service ID {$params['serviceid']}. Response: " . json_encode($reset_result));
                        } else {
                            //error_log("ORRISM_ERROR: Client Area - UUID reset failed for service ID {$params['serviceid']}. Response: " . json_encode($reset_result));
                        }
                        exit;
                    } else {
                        header('Content-Type: application/json');
                        echo json_encode([
                            'status' => 'error',
                            'msg'    => ORRISM_L::common_prohibit
                        ]);
                        exit;
                    }
                    break;
                case 'ResetBandwidth':
                    if (isset($_GET['sid']) && $_GET['sid'] == ($params['serviceid'] ?? 0)) {
                        // 重置用户流量
                        $db = OrrisDatabase::getInstance();
                        $resetResult = $db->resetUserTraffic($serviceId);
                        
                        header('Content-Type: application/json');
                        echo json_encode([
                            'status' => $resetResult ? 'success' : 'error',
                            'msg' => $resetResult ? ORRISM_L::traffic_reset_success : 'Reset failed'
                        ]);
                        exit;
                    } else {
                        header('Content-Type: application/json');
                        echo json_encode([
                            'status' => 'error',
                            'msg'    => ORRISM_L::common_prohibit
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
        $db = OrrisDatabase::getInstance();
        $user = $db->getUser($service_id);
        if ($user) {
            $user_traffic_total = $user['u'] + $user['d'];
            $user_traffic_upload = $user['u'];
            $user_traffic_download = $user['d'];
            $bandwidth = $user['bandwidth'];
            $left = max(0, $bandwidth - $user_traffic_total);
            
            $info = [
                'uuid' => $user['uuid'],
                'upload' => OrrisHelper::formatBytes($user_traffic_upload),
                'download' => OrrisHelper::formatBytes($user_traffic_download),
                'total_used' => OrrisHelper::formatBytes($user_traffic_total),
                'left' => OrrisHelper::formatBytes($left),
                'created_at' => date('Y-m-d H:i:s', $user['created_at']),
                'telegram_id' => $user['telegram_id'],
                'bandwidth' => OrrisHelper::formatBytes($bandwidth),
                'sid' => $user['sid'],
                'token' => $user['token'],
                'usage_percent' => OrrisHelper::calculateUsagePercentage($user_traffic_total, $bandwidth)
            ];
            
            // 获取节点信息
            try {
                $nodes = $db->getNodesForUser($service_id);
                OrrisHelper::log('info', 'Retrieved nodes for user', [
                    'service_id' => $service_id,
                    'node_count' => count($nodes)
                ]);
            } catch (Exception $e) {
                OrrisHelper::log('error', 'Failed to get nodes for user', [
                    'service_id' => $service_id,
                    'error' => $e->getMessage()
                ]);
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
                    'subscribe_url' => orrism_get_config()['subscribe_url'] ?? '',
                    'subscription_records' => $subscription_records,
                ],
            ];
        } else {
            return [
                'tabOverviewReplacementTemplate' => 'error.tpl',
                'templateVariables' => [
                    'error' => ORRISM_L::error_account_not_found,
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
 * 获取用户信息
 * @param int $sid
 * @return array
 */
function orrism_get_user($sid) {
    $redis_key = 'uuid'.$sid;
    $conn = orrism_get_db_connection();
    $sql = 'SELECT * FROM user WHERE `sid` = :sid';
    $db = $conn->prepare($sql);
    $db->bindValue(':sid', $sid);
    $db->execute();
    return $db->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * 新建账号
 * @param array $data
 * @return bool|string|Exception
 */
function orrism_new_account($data){
    try {
        if (empty(orrism_get_user($data['sid']))){
            $conn = orrism_get_db_connection();
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
            orrism_set_redis($data['sid'], $data['token'], 'set');
            orrism_set_redis("node_group_id_sid_{$data['sid']}", $data['node_group_id'], 'set');
            orrism_set_redis("uuid{$data['sid']}", $data['uuid'], 'set');
            return $action->execute();
        } else{
            return ORRISM_L::error_account_already_exists;
        }
    } catch (Exception $e){
        return $e;
    }
}

/**
 * 设置用户状态
 * @param array $data
 * @return bool|string|Exception
 */
function orrism_set_status($data){
    //error_log("ORRISM_DEBUG: orrism_set_status called with data: " . print_r($data, true)); // Log input data
    try {
        $user_exists = count(orrism_get_user($data['sid'])) > 0;
        //error_log("ORRISM_DEBUG: User exists for sid " . ($data['sid'] ?? 'N/A') . "? " . ($user_exists ? 'Yes' : 'No')); // Log user existence

        if ($user_exists){
            $conn = orrism_get_db_connection();
            $db = $conn->prepare('UPDATE `user` SET `enable` = :enable WHERE `sid` = :sid');
            $db->bindValue(':enable',$data['action']);
            $db->bindValue(':sid',$data['sid']);
            $execute_result = $db->execute();
            //error_log("ORRISM_DEBUG: Database execute result for sid " . ($data['sid'] ?? 'N/A') . ": " . ($execute_result ? 'Success' : 'Failure')); // Log execution result
            return $execute_result;
        } else{
            //error_log("ORRISM_DEBUG: User not found for sid " . ($data['sid'] ?? 'N/A') . ". Returning error_account_not_found."); // Log user not found
            return ORRISM_L::error_account_not_found;
        }
    } catch (Exception $e){
        //error_log("ORRISM_DEBUG: Exception in orrism_set_status for sid " . ($data['sid'] ?? 'N/A') . ": " . $e->getMessage()); // Log exception
        return $e;
    }
}

/**
 * 删除账号
 * @param array $data
 * @return bool|string|Exception
 */
function orrism_delete_account($data){
    try {
        if (count(orrism_get_user($data['sid'])) > 0){
            $conn = orrism_get_db_connection();
            $db = $conn->prepare('DELETE FROM `user` WHERE `sid` = :sid');
            $db->bindValue(':sid',$data['sid']);
            //orrism_set_redis($data['sid'],null,'del');
            return $db->execute();
        } else{
            return ORRISM_L::error_account_not_found;
        }
    } catch (Exception $e){
        return $e;
    }
}

/**
 * UUID重置
 * @param array $data
 * @return bool|string|Exception
 */
function orrism_reset_uuid_internal($data){
    try {
        // Call orrism_get_user once to check existence
        $user_info = orrism_get_user($data['sid']);
        if (count($user_info) > 0) {
            $conn = orrism_get_db_connection();
            $new_token = orrism_generate_md5_token(); // Generate a new token
            // $data['uuid'] is the new UUID passed from orrism_user_reset_uuid
            $db = $conn->prepare('UPDATE `user` SET `uuid` = :uuid, `token` = :token WHERE `sid` = :sid');
            $db->bindValue(':sid', $data['sid']);
            $db->bindValue(':uuid', $data['uuid']);
            $db->bindValue(':token', $new_token);
            
            if ($db->execute()) {
                // Clear and set Redis cache for UUID
                orrism_set_redis('uuid'.$data['sid'], null, 'del', 0);
                orrism_set_redis('uuid'.$data['sid'], $data['uuid'], 'set', 0);
                // Clear and set Redis cache for token (using sid as key for token)
                orrism_set_redis($data['sid'], null, 'del', 0); 
                orrism_set_redis($data['sid'], $new_token, 'set', 0);
                return true; // Successfully updated DB and Redis
            } else {
                //error_log("ORRISM_ERROR: Database execute failed in orrism_reset_uuid_internal for SID: " . ($data['sid'] ?? 'N/A'));
                return false; // Database execution failed
            }
        } else {
            //error_log("ORRISM_ERROR: User not found in orrism_reset_uuid_internal for SID: " . ($data['sid'] ?? 'N/A'));
            return false; // User not found
        }
    } catch (Exception $e) {
        //error_log("ORRISM_ERROR: Exception in orrism_reset_uuid_internal for SID: " . ($data['sid'] ?? 'N/A') . " - " . $e->getMessage());
        return false; // An exception occurred
    }
}

/**
 * 设置用户带宽
 * @param array $data
 * @return bool|string|Exception
 */
function orrism_set_bandwidth($data){
    try {
        if (count(orrism_get_user($data['sid'])) > 0) {
            $conn = orrism_get_db_connection();
            $db = $conn->prepare('UPDATE `user` SET `u` = :u , `d` = :d  WHERE `sid` = :sid');
            $db->bindValue(':u', $data['u']);
            $db->bindValue(':d',$data['d']);
            $db->bindValue(':sid', $data['sid']);
            return $db->execute();
        }else{
            return ORRISM_L::error_account_not_found;
        }
    } catch (Exception $e){
        return $e;
    }
}

/**
 * 重置用户流量
 * @param int $sid
 */
function orrism_reset_user_traffic($sid) {
    if (orrism_get_user($sid) != null){
        $reset_traffic = array(
            'u' => 0,
            'd' => 0,
            'sid' => $sid
        );
        orrism_set_bandwidth($reset_traffic);
    }
}

/**
 * 获取用户 UUID
 * @param int $sid
 * @return string|null
 */
function orrism_get_uuid($sid){
    $redis_key = 'uuid'.$sid;
    $redis = orrism_get_redis_connection(0);
    $redis = orrism_set_redis($redis_key, null, 'get', 0);
    if ($redis) {
        return $redis;
    }
    $conn = orrism_get_db_connection();
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
    $redis = orrism_get_redis_connection(0);
    $redis->set($redis_key, $result, 86400);
    
    return $result;
}

/**
 * 获取用户 TOKEN
 * @param int $sid
 * @return string|null
 */
function orrism_get_token($sid){
    $conn = orrism_get_db_connection();
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
 * 获取用户可用节点列表
 * @param int $sid 服务ID
 * @return array 节点列表
 */
function orrism_get_nodes($sid) {
    try {
        // 获取用户信息，主要是获取node_group_id
        $user = orrism_get_user($sid);
        if (empty($user)) {
            return [];
        }
        
        $node_group_id = $user[0]['node_group_id'] ?? 0;
        // error_log("node_group_id: " . $node_group_id);
        // 连接数据库
        $conn = orrism_get_db_connection();
        
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
