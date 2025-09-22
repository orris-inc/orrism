<?php
/**
 * ORRISM - ShadowSocks Manager Module for WHMCS
 *
 * @package    WHMCS
 * @author     ORRISM Development Team
 * @copyright  Copyright (c) 2022-2024
 * @version    1.0
 */

require_once __DIR__ . '/api/database.php';
require_once __DIR__ . '/api/user.php';
require_once __DIR__ . '/api/traffic.php';
require_once __DIR__ . '/includes/whmcs_utils.php'; // Include the new utility file
require_once __DIR__ . '/includes/hook_logic.php'; // Include the new hook logic file

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use WHMCS\View\Menu\Item as MenuItem;
use WHMCS\Database\Capsule;

// Definitions for orrism_suspendOverdueProducts, orrism_checkOverdueProducts, 
// and orrism_suspendAssociatedOrders are now in includes/hook_logic.php

// AfterCronJob hook
add_hook('AfterCronJob', 10, function ($vars) {
    $adminUser = 'easay'; // Consider making this configurable

    // 检查流量 (Traffic Check Logic)
    try {
        orrism_perform_after_cron_traffic_checks($adminUser);
    } catch (Exception $e) {
        // Fallback error logging if the function itself doesn't catch its own top-level errors
        error_log("ORRISM: AfterCronJob - Uncaught exception during orrism_perform_after_cron_traffic_checks: " . $e->getMessage() . " Trace: " . $e->getTraceAsString());
    }

    // 接受支付订单 (Accept Paid/Free Pending Orders)
    try {
        orrism_process_paid_pending_orders($adminUser);
    } catch (Exception $e) {
        error_log("ORRISM: AfterCronJob - Processing paid pending orders failed: " . $e->getMessage() . " Trace: " . $e->getTraceAsString());
    }
});

// DailyCronJob hook for order/invoice cancellation and traffic reset
add_hook('DailyCronJob', 1, function ($vars) {
    $adminUser = 'easay'; // Consider making this configurable
    $orderOverdueDays = 3; // Configurable
    $invoiceOverdueDays = 3; // Configurable
    $allowableServiceOverdueDays = 1; // Configurable, for orrism_suspendOverdueProducts

    // 取消过期订单 (Cancel Overdue Pending Orders)
    try {
        orrism_cancel_overdue_pending_orders($adminUser, $orderOverdueDays);
    } catch (Exception $e) {
        error_log("ORRISM: DailyCronJob - Cancelling overdue pending orders failed: " . $e->getMessage() . " Trace: " . $e->getTraceAsString());
    }

    // 取消过期发票 (Cancel Overdue Invoices)
    try {
        orrism_cancel_overdue_invoices($adminUser, $invoiceOverdueDays);
    } catch (Exception $e) {
        error_log("ORRISM: DailyCronJob - Cancelling overdue invoices failed: " . $e->getMessage() . " Trace: " . $e->getTraceAsString());
    }

    // 账单日流量重置 (Perform Billing Day Traffic Reset)
    orrism_perform_billing_day_traffic_reset($adminUser);

    // // 同步Redis流量到MySQL
    // try {
    //     require_once __DIR__ . '/api/v1/server/UniProxy/config/index.php';
    //     $sync_result = orrism_sync_state_to_mysql();
    //     error_log("ORRISM: DailyCronJob - Sync state to MySQL: " . json_encode($sync_result));
    // } catch (Exception $e) {
    //     error_log("ORRISM: DailyCronJob - Sync state to MySQL failed: " . $e->getMessage() . " Trace: " . $e->getTraceAsString());
    // }

    // 暂停模块服务 (Suspend Overdue ORRISM Services)
    try {
        orrism_suspendOverdueProducts($adminUser, $allowableServiceOverdueDays);
    } catch (Exception $e) {
        error_log("ORRISM: DailyCronJob - Suspending overdue products failed: " . $e->getMessage() . " Trace: " . $e->getTraceAsString());
    }
});


