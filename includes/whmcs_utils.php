<?php
/**
 * ORRISM - ShadowSocks Manager Module for WHMCS
 *
 * @package    WHMCS
 * @author     ORRISM Development Team
 * @copyright  Copyright (c) 2022-2024
 * @version    1.0
 */

/**
 * 调用 WHMCS API 的通用函数
 */
function orrism_callAPI($command, $postData, $adminUser, $logMessage = '') {
    $results = localAPI($command, $postData, $adminUser);
    if ($logMessage) {
        $logStatus = $results['result'] === 'success' ? "成功" : "失败";
        // error_log("{$logMessage} - {$logStatus}", 0);
    }
    return $results;
}

/**
 * 获取指定模块的产品ID列表
 * @param string $adminUser 管理员用户名
 * @param string $moduleName 模块名
 * @return array 产品ID列表
 */
function orrism_get_module_product_ids($adminUser, $moduleName = 'orrism') {
    $moduleProductsResponse = orrism_callAPI('GetProducts', ['module' => $moduleName], $adminUser, "获取模块 {$moduleName} 的产品ID");
    $productIds = [];
    if (isset($moduleProductsResponse['products']['product'])) {
        foreach ($moduleProductsResponse['products']['product'] as $moduleProduct) {
            $productIds[] = $moduleProduct['pid'];
        }
    }
    if (empty($productIds)) {
        error_log("ORRISM_DEBUG: Module {$moduleName} has no product IDs.");
    } else {
        error_log("ORRISM_DEBUG: Module {$moduleName} Product IDs: " . print_r($productIds, true));
    }
    return $productIds;
}

/**
 * 获取指定产品ID列表的活动客户服务
 * @param string $adminUser 管理员用户名
 * @param array $productIds 产品ID数组
 * @param string $status 服务状态, 默认为 'Active'
 * @return array 客户服务列表
 */
function orrism_get_module_client_services($adminUser, array $productIds, $status = 'Active') {
    if (empty($productIds)) {
        return [];
    }

    $allClientProducts = [];
    foreach ($productIds as $productId) {
        $singlePidClientResponse = orrism_callAPI(
            'GetClientsProducts',
            [
                'limitnum' => 10000, 
                'status'   => $status,
                'pid'      => $productId 
            ],
            $adminUser,
            "获取PID {$productId} 的 {$status} 客户服务"
        );
        // error_log("ORRISM_DEBUG: GetClientsProducts response for single PID {$productId}: " . print_r($singlePidClientResponse, true));

        if (isset($singlePidClientResponse['products']['product'])) {
            $allClientProducts = array_merge($allClientProducts, $singlePidClientResponse['products']['product']);
        }
    }
    // error_log("ORRISM_DEBUG: All combined client products for selected PIDs: " . print_r(array_column($allClientProducts, 'id'), true));
    return $allClientProducts;
} 