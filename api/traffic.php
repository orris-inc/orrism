<?php
/**
 * ORRISM - ShadowSocks Manager Module for WHMCS
 *
 * @package    WHMCS
 * @author     ORRISM Development Team
 * @copyright  Copyright (c) 2022-2024
 * @version    1.0
 */


// 流量相关业务模块
require_once __DIR__ . '/../helper.php';
require_once __DIR__ . '/../lib/database.php';
require_once __DIR__ . '/product.php';


function orrism_traffic_reset_bandwidth_user($params) {
    try {
        $serviceId = $params['serviceid'] ?? 0;
        
        // Check if paid reset is enabled
        if (($params['configoption5'] ?? 0) != 0) {
            $client_product = orrism_get_client_products($serviceId);
            if (($client_product['result'] ?? '') === 'success') {
                $product = $client_product['products']['product'][0] ?? [];
                if (($product['status'] ?? '') === 'Active') {
                    $amount = $product['recurringamount'] ?? 0;
                    $cost = ($params['configoption6'] ?? 0) * $amount;
                    
                    if ($cost > 0) {
                        $creditData = [
                            'clientid' => $params['userid'] ?? 0,
                            'description' => 'Traffic reset fee for service #' . $serviceId,
                            'amount' => (float)$cost,
                            'type' => 'remove',
                        ];
                        $result = orrism_set_credit($creditData);
                        
                        if (($result['result'] ?? '') !== 'success') {
                            return ['status' => 'fail', 'msg' => ORRISM_L::product_reset_bandwidth_error];
                        }
                    }
                }
            }
        }
        
        // Reset traffic using unified database layer
        $db = OrrisDatabase::getInstance();
        $resetResult = $db->resetUserTraffic($serviceId);
        
        return [
            'status' => $resetResult ? 'success' : 'fail',
            'msg' => $resetResult ? ORRISM_L::product_reset_bandwidth_success : ORRISM_L::product_reset_bandwidth_error
        ];
        
    } catch (Exception $e) {
        OrrisHelper::log('error', 'Traffic reset failed', [
            'service_id' => $params['serviceid'] ?? 'unknown',
            'error' => $e->getMessage()
        ]);
        return ['status' => 'fail', 'msg' => ORRISM_L::common_prohibit];
    }
}

function orrism_traffic_reset_bandwidth_admin($params) {
    try {
        $serviceId = $params['serviceid'] ?? 0;
        $db = OrrisDatabase::getInstance();
        $result = $db->resetUserTraffic($serviceId);
        
        return $result ? ORRISM_L::product_reset_bandwidth_success : ORRISM_L::product_reset_bandwidth_error;
    } catch (Exception $e) {
        OrrisHelper::log('error', 'Admin traffic reset failed', [
            'service_id' => $serviceId ?? 'unknown',
            'error' => $e->getMessage()
        ]);
        return $e->getMessage();
    }
}

/**
 * 上报流量
 * @param array $data
 * @return bool
 */
function orrism_report_traffic($data) {
    try {
        $u = $data['u'];
        $d = $data['d'];
        $sid = $data['user_id'];
        $node_id = $data['node_id'];
        $conn = orrism_get_db_connection();
        $conn->beginTransaction();
        $update = $conn->prepare('UPDATE `user` SET `u` = u + :u, `d` = d + :d, `updated_at` = UNIX_TIMESTAMP() WHERE `sid` = :sid');
        $update->bindValue(':u', $u, PDO::PARAM_INT);
        $update->bindValue(':d', $d, PDO::PARAM_INT);
        $update->bindValue(':sid', $sid, PDO::PARAM_INT);
        $update_result = $update->execute();
        if ($update_result) {
            $conn->commit();
            orrism_reset_traffic($sid);
            return true;
        } else {
            $conn->rollBack();
            return false;
        }
    } catch (Exception $e) {
        if (isset($conn) && $conn->inTransaction()) {
            $conn->rollBack();
        }
        error_log("Error reporting traffic: " . $e->getMessage());
        return false;
    }
}

/**
 * 检查并自动禁用/启用超流量用户
 * @return bool
 */
function orrism_check_traffic() {
    try {
        $conn = orrism_get_db_connection();
        
        // Disable users who have exceeded their bandwidth and are currently enabled
        $stmt_disable = $conn->prepare('UPDATE user SET enable = 0 WHERE (u + d) > bandwidth AND enable = 1');
        $stmt_disable->execute();
        
        // Enable users who are below their bandwidth limit and are currently disabled
        $stmt_enable = $conn->prepare('UPDATE user SET enable = 1 WHERE (u + d) < bandwidth AND enable = 0');
        $stmt_enable->execute();
        
        return true;
    } catch (Exception $e) {
        error_log("流量检查失败: " . $e->getMessage());
        return false;
    }
}

/**
 * 检查用户流量并更新状态
 * @param int $sid
 */
function orrism_reset_traffic($sid) {
    try {
        $data = array(
            'sid' => $sid,
            'action' => null, // 先初始化 action
        );

        // 获取用户流量信息
        $user = orrism_get_user($sid);
        if (!$user) {
            return; // 如果用户不存在，则直接返回
        }

        $total_used = $user[0]['u'] + $user[0]['d'];

        if ($total_used > $user[0]['bandwidth'] && $user[0]['enable'] == 1) {
            $data['action'] = 0;
        } elseif ($user[0]['enable'] == 0 && $total_used < $user[0]['bandwidth']) {
            $data['action'] = 1;
        }

        if (!is_null($data['action'])) {
            orrism_set_status($data);
        }
    } catch (Exception $e) {
        error_log("Traffic check failed: " . $e->getMessage());
    }
}

/**
 * 月度流量重置
 * @return void
 */
function orrism_reset_traffic_month(){
    $adminUsername = orrism_get_config()['admin_username'];
    $get_orders_data = array(
        'status' => 'Active',
        'limitstart' => 0,
        'limitnum' => 10000
    );
    $result_orders = localAPI('GetOrders', $get_orders_data, $adminUsername);
    $get_products_data = array(
        'module' => 'orrism'
    );
    $result_products = localAPI('GetProducts', $get_products_data, $adminUsername);

    if ($result_products['result'] == 'success'){
        $pid_array = array_column($result_products['products']['product'], 'pid');
    }
    $today = date("d");

    if ($result_orders['result'] == 'success'){
        // Get all orders and sort by sid
        $orders = array();
        foreach ($result_orders['orders']['order'] as $data) {
            $sid = $data['lineitems']['lineitem'][0]['relid'];
            $orders[$sid] = $data;
        }
        ksort($orders); // Sort by sid

        // Process orders in sorted order
        foreach ($orders as $sid => $data) {
            try {
                $product = orrism_get_client_products($sid)['products']['product'][0];
                
                // 检查用户need_reset设置
                $user_data = orrism_get_user($sid);
                if (empty($user_data)) {
                    error_log("用户 {$sid} 不存在");
                    continue;
                }
                
                if ($user_data[0]['need_reset'] == 0) {
                    error_log("用户 {$sid} 不需要重置流量");
                    continue;
                }
                
                if ($user_data[0]['enable'] == 0) {
                    error_log("用户 {$sid} 已禁用");
                    continue;
                }

                if ($product['status'] != 'Active' || !in_array($product['pid'], $pid_array)) {
                    error_log("用户 {$sid} 产品未激活或不在重置列表中");
                    continue;
                }

                $due_date = date("d", strtotime($product['nextduedate']));
                $buy_date = date("d", strtotime($product['regdate']));
                
                $should_reset = false;
                
                if ($product['billingcycle'] == 'Free Account') {
                    $should_reset = ($buy_date != '31' && $buy_date == $today) || 
                          ($buy_date == '31' && $today == '30');
                } else {
                    $should_reset = ($due_date != '31' && $due_date == $today) || 
                          ($due_date == '31' && $today == '30');
                }

                if ($should_reset) {
                    orrism_reset_user_traffic($sid);
                    error_log("用户 {$sid} 的流量已重置");
                } else {
                    error_log("用户 {$sid} 未到重置时间");
                }

            } catch (Exception $e) {
                error_log("处理用户 {$sid} 时发生错误: " . $e->getMessage());
                continue;
            }
        }
    }
} 
