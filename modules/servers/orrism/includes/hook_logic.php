<?php
/**
 * ORRISM - ShadowSocks Manager Module for WHMCS
 *
 * @package    WHMCS
 * @author     ORRISM Development Team
 * @copyright  Copyright (c) 2022-2024
 * @version    1.0
 */

// Include required files with proper path resolution
require_once __DIR__ . '/../api/database.php';

use Carbon\Carbon; // Ensure Carbon is available if used directly

/**
 * 暂停逾期的产品并暂停相关订单的模块函数
 */
function orrism_suspendOverdueProducts($adminUser, $allowableOverdueDays = 1) {
    $now = Carbon::now()->startOfDay();

    // Step 1: Get Product IDs for the 'orrism' module
    $orrisProductIds = orrism_get_module_product_ids($adminUser, 'orrism');

    if (empty($orrisProductIds)) {
        error_log("ORRISM_DEBUG: No product IDs found for orris module in orrism_suspendOverdueProducts. Skipping.");
        return;
    }

    // Step 2: Get active client services for these Product IDs
    $activeClientServices = orrism_get_module_client_services($adminUser, $orrisProductIds, 'Active');

    if (empty($activeClientServices)) {
        error_log("ORRISM_DEBUG: No active client services found for orris module in orrism_suspendOverdueProducts. Skipping.");
        return;
    }

    error_log("ORRISM_DEBUG: Suspending overdue products. Processing " . count($activeClientServices) . " orris client services.");

    foreach ($activeClientServices as $service) {
        if (isset($service['status']) && $service['status'] === 'Cancelled') {
            error_log("产品 #" . $service['id'] . " 已被取消，跳过暂停检查。");
            continue;
        }

        $nextDueDate = Carbon::parse($service['nextduedate'])->startOfDay();
        
        if ($nextDueDate->isBefore($now)) {
            $daysDifference = $now->diffInDays($nextDueDate);

            if ($daysDifference > $allowableOverdueDays) {
                error_log("产品 #" . $service['id'] . " 已逾期超过允许的天数 ({$daysDifference} > {$allowableOverdueDays})，尝试暂停服务。");
                orrism_suspendAssociatedOrders($service['clientid'], $service['id'], $adminUser);
            }
        }
    }
}

/**
 * 检查逾期产品
 */
function orrism_checkOverdueProducts($adminUser, $allowableOverdueDays = 1) {
    $now = Carbon::now()->startOfDay();

    $orrisProductIds = orrism_get_module_product_ids($adminUser, 'orrism');

    if (empty($orrisProductIds)) {
        error_log("ORRISM_DEBUG: No product IDs found for orris module in orrism_checkOverdueProducts. Returning empty list.");
        return [];
    }

    $activeClientServices = orrism_get_module_client_services($adminUser, $orrisProductIds, 'Active');

    if (empty($activeClientServices)) {
        error_log("ORRISM_DEBUG: No active client services found for orris module in orrism_checkOverdueProducts. Returning empty list.");
        return [];
    }
    
    error_log("ORRISM_DEBUG: Checking overdue products. Processing " . count($activeClientServices) . " orris client services.");

    $overdueServiceIds = [];

    foreach ($activeClientServices as $service) {
        $nextDueDate = Carbon::parse($service['nextduedate'])->startOfDay();
        
        if ($nextDueDate->isBefore($now)) {
            $daysDifference = $now->diffInDays($nextDueDate);
            
            if ($daysDifference > $allowableOverdueDays) {
                $overdueServiceIds[] = $service['id'];
            }
        }
    }
    
    if (!empty($overdueServiceIds)) {
        error_log("ORRISM_DEBUG: Found overdue orris service IDs: " . print_r($overdueServiceIds, true));
    }

    return $overdueServiceIds;
}

/**
 * 暂停与特定产品关联的订单服务
 */
function orrism_suspendAssociatedOrders($clientId, $productId, $adminUser) {
    $suspendOrderResults = orrism_callAPI('ModuleSuspend', ['serviceid' => $productId, 'suspendreason' => '逾期未付款'], $adminUser, "暂停服务 #" . $productId);
    
    if ($suspendOrderResults['result'] === 'success') {
        error_log("已暂停产品 #" . $productId . " 关联的服务，客户 #" . $clientId . "。", 0);
    } else {
        error_log("暂停产品 #" . $productId . " 关联的服务失败，客户 #" . $clientId . "。", 0);
    }
}

/**
 * Processes pending orders that have been paid or have a zero amount.
 * @param string $adminUser WHMCS admin username
 */
function orrism_process_paid_pending_orders($adminUser) {
    error_log("ORRISM_DEBUG: Starting orrism_process_paid_pending_orders.");
    $orders = orrism_callAPI('GetOrders', ['limitnum' => 10000, 'status' => 'Pending'], $adminUser, 'GetPendingOrdersForProcessing');
    
    if (isset($orders['orders']['order'])) {
        $processedCount = 0;
        foreach ($orders['orders']['order'] as $order) {
            if (($order['paymentstatus'] ?? null) === 'Paid' || (isset($order['amount']) && $order['amount'] === '0.00')) {
                orrism_callAPI('AcceptOrder', ['orderid' => $order['id']], $adminUser, "Accept order #" . $order['id']);
                $processedCount++;
            }
        }
        error_log("ORRISM_DEBUG: orrism_process_paid_pending_orders processed {$processedCount} orders.");
    } else {
        error_log("ORRISM_DEBUG: No pending orders found to process in orrism_process_paid_pending_orders.");
    }
}

/**
 * Cancels pending orders that are older than the specified number of days.
 * @param string $adminUser WHMCS admin username
 * @param int $orderOverdueDays Number of days after which a pending order is considered overdue
 */
function orrism_cancel_overdue_pending_orders($adminUser, $orderOverdueDays) {
    error_log("ORRISM_DEBUG: Starting orrism_cancel_overdue_pending_orders. Overdue days: {$orderOverdueDays}");
    $now = Carbon::now()->startOfDay();
    $orders = orrism_callAPI('GetOrders', ['limitnum' => 10000, 'status' => 'Pending'], $adminUser, 'GetPendingOrdersForCancellation');

    if (isset($orders['orders']['order'])) {
        $cancelledCount = 0;
        foreach ($orders['orders']['order'] as $order) {
            $createDate = Carbon::parse($order['date'])->startOfDay();
            if ($now->diffInDays($createDate) >= $orderOverdueDays) {
                orrism_callAPI('CancelOrder', ['orderid' => $order['id']], $adminUser, "Cancel overdue order #" . $order['id']);
                $cancelledCount++;
            }
        }
        error_log("ORRISM_DEBUG: orrism_cancel_overdue_pending_orders cancelled {$cancelledCount} orders.");
    } else {
        error_log("ORRISM_DEBUG: No pending orders found for cancellation in orrism_cancel_overdue_pending_orders.");
    }
}

/**
 * Cancels overdue invoices that are older than the specified number of days from their due date.
 * @param string $adminUser WHMCS admin username
 * @param int $invoiceOverdueDays Number of days after due date an invoice is considered overdue for cancellation
 */
function orrism_cancel_overdue_invoices($adminUser, $invoiceOverdueDays) {
    error_log("ORRISM_DEBUG: Starting orrism_cancel_overdue_invoices. Overdue days: {$invoiceOverdueDays}");
    $now = Carbon::now()->startOfDay();
    // Note: WHMCS GetInvoices status 'Overdue' might be sufficient. 
    // If not, one might need to fetch 'Unpaid' and then check duedate.
    $invoices = orrism_callAPI('GetInvoices', ['limitnum' => 10000, 'status' => 'Overdue'], $adminUser, 'GetOverdueInvoicesForCancellation');

    if (isset($invoices['invoices']['invoice'])) {
        $cancelledCount = 0;
        foreach ($invoices['invoices']['invoice'] as $invoice) {
            $dueDate = Carbon::parse($invoice['duedate'])->startOfDay();
            // The GetInvoices call with status=Overdue should ideally only return overdue invoices.
            // This diffInDays check is an additional safeguard or for a more precise definition of "overdue for cancellation".
            if ($now->diffInDays($dueDate) >= $invoiceOverdueDays) {
                orrism_callAPI('UpdateInvoice', ['invoiceid' => $invoice['id'], 'status' => 'Cancelled'], $adminUser, "Cancel overdue invoice #" . $invoice['id']);
                $cancelledCount++;
            }
        }
        error_log("ORRISM_DEBUG: orrism_cancel_overdue_invoices cancelled {$cancelledCount} invoices.");
    } else {
        error_log("ORRISM_DEBUG: No overdue invoices found for cancellation in orrism_cancel_overdue_invoices.");
    }
}

/**
 * Performs traffic checks for active, non-overdue ORRISM services.
 * This is typically called by the AfterCronJob hook.
 * @param string $adminUser WHMCS admin username
 */
function orrism_perform_after_cron_traffic_checks($adminUser) {
    error_log("ORRISM_DEBUG: Starting orrism_perform_after_cron_traffic_checks.");
    try {
        $orrisProductIds = orrism_get_module_product_ids($adminUser, 'orrism');
        if (empty($orrisProductIds)) {
            error_log("ORRISM: AfterCronJob (via orrism_perform_after_cron_traffic_checks) - No ORRISM product IDs found, skipping traffic check logic.");
            return;
        }

        // It's important that orrism_checkOverdueProducts itself is efficient and correctly scoped.
        // Allowable overdue days for traffic check is 0, meaning if it's overdue at all, skip traffic reset.
        $overdueServiceIds = orrism_checkOverdueProducts($adminUser, 0);
        $activeClientServices = orrism_get_module_client_services($adminUser, $orrisProductIds, 'Active');
        
        if (empty($activeClientServices)) {
            error_log("ORRISM: AfterCronJob (via orrism_perform_after_cron_traffic_checks) - No active ORRISM client services found.");
            return;
        }

        $checkedCount = 0;
        $skippedCount = 0;
        foreach ($activeClientServices as $service) {
            if (!in_array($service['id'], $overdueServiceIds)) {
                orrism_reset_traffic($service['id']); // This function is from api/traffic.php
                // error_log("ORRISM: AfterCronJob (via orrism_perform_after_cron_traffic_checks) - Product #" . $service['id'] . " traffic check executed.");
                $checkedCount++;
            } else {
                // error_log("ORRISM: AfterCronJob (via orrism_perform_after_cron_traffic_checks) - Product #" . $service['id'] . " is overdue, skipping traffic check.");
                $skippedCount++;
            }
        }
        error_log("ORRISM_DEBUG: orrism_perform_after_cron_traffic_checks completed. Services checked: {$checkedCount}, services skipped (overdue): {$skippedCount}.");

    } catch (Exception $e) {
        error_log("ORRISM: Error in orrism_perform_after_cron_traffic_checks: " . $e->getMessage() . " Trace: " . $e->getTraceAsString());
    }
}


/**
 * Performs traffic reset for services whose next due date is today (billing day).
 * @param string $adminUser WHMCS admin username
 */
function orrism_perform_billing_day_traffic_reset($adminUser) {
    error_log("ORRISM_DEBUG: Starting orrism_perform_billing_day_traffic_reset.");
    try {
        $now = Carbon::now()->startOfDay();

        // Step 1: Get Product IDs for the 'orrism' module
        $orrisProductIds = orrism_get_module_product_ids($adminUser, 'orrism');

        if (empty($orrisProductIds)) {
            error_log("ORRISM: BillingDayTrafficReset - No ORRISM product IDs found, skipping billing day traffic reset.");
            return;
        }

        // Step 2: Get active client services for these Product IDs
        $activeClientServices = orrism_get_module_client_services($adminUser, $orrisProductIds, 'Active');

        if (empty($activeClientServices)) {
            error_log("ORRISM: BillingDayTrafficReset - No active ORRISM client services found for billing day traffic reset.");
            return;
        }

        $resetCount = 0;
        foreach ($activeClientServices as $service) {
            $nextDueDate = Carbon::parse($service['nextduedate'])->startOfDay();

            // Check if the next due date is today
            if ($nextDueDate->isSameDay($now)) {
                orrism_reset_traffic($service['id']); // This function is from api/traffic.php
                // error_log("ORRISM: BillingDayTrafficReset - Product #" . $service['id'] . " billing day traffic reset executed.");
                $resetCount++;
            }
        }
        error_log("ORRISM_DEBUG: orrism_perform_billing_day_traffic_reset completed. Services reset: {$resetCount}.");

    } catch (Exception $e) {
        error_log("ORRISM: Error in orrism_perform_billing_day_traffic_reset: " . $e->getMessage() . " Trace: " . $e->getTraceAsString());
    }
} 
