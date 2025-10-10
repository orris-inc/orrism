<?php
/**
 * ORRISM Manager Module for WHMCS
 *
 * @package    WHMCS
 * @author     ORRIS Development Team
 * @copyright  Copyright (c) 2022-2024
 * @version    1.0
 */


require_once __DIR__ . '/../../../../init.php';
// Product/package management business module
require_once __DIR__ . '/../helper.php';
require_once __DIR__ . '/../includes/whmcs_utils.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/service.php';

function orris_product_change_package($params) {
    $sid = $params['serviceid'] ?? 0;
    $data = [
        'sid'           => $sid,
        'pid'           => $params['pid'] ?? 0,
        'bandwidth'     => $params['configoption4'] ?? 0,
        'node_group_id' => $params['configoption7'] ?? 0
    ];
    return orris_change_package($data);
}

/**
 * Get client product information
 *
 * @param int $sid Service ID
 * @return array API response
 */
function orris_get_client_products($sid) {
    $adminUsername = orris_get_config()['admin_username'];
    $data = [
        'serviceid' => $sid
    ];

    // Use unified WHMCS API wrapper
    $result = OrrisWhmcsHelper::callAPI(
        'GetClientsProducts',
        $data,
        $adminUsername,
        "Get client product info for service {$sid}"
    );

    return $result;
}

/**
 * Get invoice information
 *
 * @param int $sid Invoice ID
 * @return array API response
 */
function orris_get_invoice($sid) {
    try {
        $adminUsername = orris_get_config()['admin_username'];
        $postData = [
            'invoiceid' => $sid,
        ];

        // Use unified WHMCS API wrapper
        $results = OrrisWhmcsHelper::callAPI(
            'GetInvoice',
            $postData,
            $adminUsername,
            "Get invoice info for invoice {$sid}"
        );

        return $results;
    } catch (Exception $e) {
        OrrisHelper::log('error', 'Error fetching invoice', [
            'invoice_id' => $sid,
            'error' => $e->getMessage()
        ]);
        return ['result' => 'error', 'message' => 'Internal error fetching invoice'];
    }
}


/**
 * 更改用户套餐
 * @param array $data
 * @return bool|string|Exception
 */
function orris_change_package($data){
    try {
        if (count(orris_get_user($data['sid'])) > 0) {
            $conn = orris_get_db_connection();
            $db = $conn->prepare('UPDATE `user` SET `bandwidth` = :bandwidth,`package_id` = :package_id, `u` = 0, `d` = 0, `node_group_id` = :node_group_id WHERE `sid` = :sid');
            $db->bindValue(':bandwidth', $data['bandwidth']);
            $db->bindValue(':sid', $data['sid']);
            $db->bindValue(':package_id', $data['pid']);
            $db->bindValue(':node_group_id', $data['node_group_id']);
            return $db->execute();
        }else{
            return ORRIS_L::error_account_not_found;
        }
    } catch (Exception $e){
        return $e;
    }
}

function orris_get_product($pid) {
    try {
        $conn = orris_get_db_connection();
        $action = $conn->prepare('SELECT * FROM products WHERE id = :pid');
        $action->bindValue(':pid', $pid);
        $action->execute();
        return $action->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error fetching product: " . $e->getMessage());
        return null;
    }
}

function orris_get_client_product_config($params) {
    try {
        $conn = orris_get_db_connection();
        $action = $conn->prepare('SELECT * FROM product_configs WHERE serviceid = :sid');
        $action->bindValue(':sid', $params['serviceid'] ?? 0);
        $action->execute();
        return $action->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error fetching product config: " . $e->getMessage());
        return [];
    }
} 
