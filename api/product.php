<?php
/**
 * MSSM - ShadowSocks Manager Module for WHMCS
 *
 * @package    WHMCS
 * @author     MSSM Development Team
 * @copyright  Copyright (c) 2022-2024
 * @version    1.0
 */


require_once __DIR__ . '/../../../../init.php';
// 产品/套餐相关业务模块
require_once __DIR__ . '/../helper.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/user.php';

function mssm_product_change_package($params) {
    $sid = $params['serviceid'] ?? 0;
    $data = [
        'sid'           => $sid,
        'pid'           => $params['pid'] ?? 0,
        'bandwidth'     => $params['configoption4'] ?? 0,
        'node_group_id' => $params['configoption7'] ?? 0
    ];
    return mssm_change_package($data);
}

/**
 * 获取产品信息
 * @param int $sid
 * @return array
 */
function mssm_get_client_products($sid) {
    $adminUsername = mssm_get_config()['admin_username'];
    $data = array(
        'serviceid' => $sid
    );
    $result = localAPI('GetClientsProducts', $data,$adminUsername);
    return $result;
}

/**
 * 获取发票信息
 * @param int $sid
 * @return array
 */
function mssm_get_invoice($sid) {
    try {
        $command = 'GetInvoice';
        $postData = [
            'invoiceid' => $sid,
        ];
        $adminUsername = mssm_get_config()['admin_username'];
        $results = localAPI($command, $postData, $adminUsername);
        return $results;
    } catch (Exception $e) {
        error_log("Error fetching invoice: " . $e->getMessage());
        return ['result' => 'error', 'message' => 'Internal error fetching invoice'];
    }
}


/**
 * 更改用户套餐
 * @param array $data
 * @return bool|string|Exception
 */
function mssm_change_package($data){
    try {
        if (count(mssm_get_user($data['sid'])) > 0) {
            $conn = mssm_get_db_connection();
            $db = $conn->prepare('UPDATE `user` SET `bandwidth` = :bandwidth,`package_id` = :package_id, `u` = 0, `d` = 0, `node_group_id` = :node_group_id WHERE `sid` = :sid');
            $db->bindValue(':bandwidth', $data['bandwidth']);
            $db->bindValue(':sid', $data['sid']);
            $db->bindValue(':package_id', $data['pid']);
            $db->bindValue(':node_group_id', $data['node_group_id']);
            return $db->execute();
        }else{
            return MSSM_L::error_account_not_found;
        }
    } catch (Exception $e){
        return $e;
    }
}

function mssm_get_product($pid) {
    try {
        $conn = mssm_get_db_connection();
        $action = $conn->prepare('SELECT * FROM products WHERE id = :pid');
        $action->bindValue(':pid', $pid);
        $action->execute();
        return $action->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error fetching product: " . $e->getMessage());
        return null;
    }
}

function mssm_get_client_product_config($params) {
    try {
        $conn = mssm_get_db_connection();
        $action = $conn->prepare('SELECT * FROM product_configs WHERE serviceid = :sid');
        $action->bindValue(':sid', $params['serviceid'] ?? 0);
        $action->execute();
        return $action->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error fetching product config: " . $e->getMessage());
        return [];
    }
} 