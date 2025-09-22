<?php
/**
 * UUID 库 - 已迁移到 OrrisHelper 类
 * 这个文件保持向后兼容性，但所有 UUID 功能现在都通过 helper.php 中的 OrrisHelper 类处理
 */

// 确保 helper.php 已加载
if (!class_exists('OrrisHelper')) {
    require_once __DIR__ . '/../helper.php';
}

// Legacy compatibility functions - 委托给 OrrisHelper
if (!function_exists('orrism_generate_uuid')) {
    /**
     * Legacy UUID generator - delegates to OrrisHelper
     * @deprecated Use OrrisHelper::generateUuid() instead
     * @return string UUID string
     */
    function orrism_generate_uuid() {
        return OrrisHelper::generateUuid();
    }
}

// Note: orrism_uuid4() is defined in helper.php with function_exists check 