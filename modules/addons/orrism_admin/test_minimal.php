<?php
/**
 * Minimal ORRISM Admin Test
 * 极简版本用于诊断空白页面问题
 */

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

/**
 * 极简配置函数
 */
function orrism_admin_config()
{
    return [
        'name' => 'ORRISM Administration',
        'description' => 'Test version for debugging',
        'version' => '2.0-debug',
        'author' => 'ORRISM Team',
        'language' => 'english',
        'fields' => []
    ];
}

/**
 * 激活函数
 */
function orrism_admin_activate()
{
    return [
        'status' => 'success',
        'description' => 'Test module activated'
    ];
}

/**
 * 停用函数
 */
function orrism_admin_deactivate()
{
    return [
        'status' => 'success',
        'description' => 'Test module deactivated'
    ];
}

/**
 * 主输出函数 - 极简版本
 */
function orrism_admin_output($vars)
{
    // 记录到错误日志以确认函数被调用
    error_log('ORRISM TEST: orrism_admin_output() called with vars: ' . print_r($vars, true));
    
    // 极简HTML输出
    $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>ORRISM Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .alert { padding: 15px; margin: 10px 0; border-radius: 4px; }
        .alert-success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-info { background-color: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
    </style>
</head>
<body>
    <h1>🎉 ORRISM Administration - Test Mode</h1>
    
    <div class="alert alert-success">
        <strong>Success!</strong> The module is working! Function called at: ' . date('Y-m-d H:i:s') . '
    </div>
    
    <div class="alert alert-info">
        <strong>Debug Info:</strong><br>
        • Function: orrism_admin_output()<br>
        • WHMCS Version: ' . (defined('WHMCS_VERSION') ? WHMCS_VERSION : 'Unknown') . '<br>
        • PHP Version: ' . PHP_VERSION . '<br>
        • Module Path: ' . __FILE__ . '<br>
        • Current User: ' . (isset($_SESSION['adminid']) ? $_SESSION['adminid'] : 'Not logged in') . '
    </div>
    
    <h2>Variables Passed to Module:</h2>
    <pre style="background: #f8f9fa; padding: 10px; border-radius: 4px; overflow: auto;">
' . htmlspecialchars(print_r($vars, true)) . '
    </pre>
    
    <h2>Current Environment:</h2>
    <pre style="background: #f8f9fa; padding: 10px; border-radius: 4px; overflow: auto;">
' . htmlspecialchars(print_r([
        'GET' => $_GET,
        'POST' => $_POST,
        'REQUEST_URI' => $_SERVER['REQUEST_URI'] ?? 'Unknown',
        'SCRIPT_NAME' => $_SERVER['SCRIPT_NAME'] ?? 'Unknown',
        'HTTP_HOST' => $_SERVER['HTTP_HOST'] ?? 'Unknown'
    ], true)) . '
    </pre>
</body>
</html>';
    
    return $html;
}