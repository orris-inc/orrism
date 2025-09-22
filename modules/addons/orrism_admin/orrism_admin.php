<?php
/**
 * ORRISM Administration Module - Ultra Minimal Debug Version
 */

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

/**
 * Module configuration
 */
function orrism_admin_config()
{
    error_log('ORRISM DEBUG: config() called');
    
    return array(
        'name' => 'ORRISM Administration',
        'description' => 'Debug version - Ultra minimal',
        'version' => '2.0-debug-ultra',
        'author' => 'ORRISM Team',
        'language' => 'english',
        'fields' => array(
            'test_field' => array(
                'FriendlyName' => 'Test Field',
                'Type' => 'text',
                'Size' => '25',
                'Default' => 'test',
                'Description' => 'Test field for debugging'
            )
        )
    );
}

/**
 * Module activation
 */
function orrism_admin_activate()
{
    error_log('ORRISM DEBUG: activate() called');
    
    return array(
        'status' => 'success',
        'description' => 'Debug module activated successfully'
    );
}

/**
 * Module deactivation
 */
function orrism_admin_deactivate()
{
    error_log('ORRISM DEBUG: deactivate() called');
    
    return array(
        'status' => 'success',
        'description' => 'Debug module deactivated successfully'
    );
}

/**
 * Main module output function
 */
function orrism_admin_output($vars)
{
    // 记录调用
    error_log('=== ORRISM DEBUG: OUTPUT FUNCTION CALLED ===');
    error_log('ORRISM DEBUG: vars = ' . print_r($vars, true));
    
    // 清理任何之前的输出缓冲
    if (ob_get_level()) {
        ob_clean();
    }
    
    // 强制开始输出缓冲
    ob_start();
    
    // 设置正确的内容类型
    if (!headers_sent()) {
        header('Content-Type: text/html; charset=UTF-8');
    }
    
    // 直接输出内容而不是返回
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>ORRISM Debug</title>
        <style>
            .orrism-debug { font-family: Arial, sans-serif; margin: 20px; }
            .alert-success { background: #d4edda; color: #155724; padding: 15px; border: 1px solid #c3e6cb; border-radius: 4px; margin: 10px 0; }
            .alert-info { background: #d1ecf1; color: #0c5460; padding: 15px; border: 1px solid #bee5eb; border-radius: 4px; margin: 10px 0; }
            .debug-vars { background: #f8f9fa; padding: 15px; border-radius: 4px; overflow: auto; }
        </style>
    </head>
    <body>
        <div class="orrism-debug">
            <h1 style="color: red; font-size: 32px;">🔥 ORRISM DEBUG MODE ACTIVE</h1>
            
            <div class="alert-success">
                <h3>✅ SUCCESS - Function Called!</h3>
                <p><strong>Time:</strong> <?php echo date('Y-m-d H:i:s'); ?></p>
                <p><strong>Function:</strong> orrism_admin_output()</p>
                <p><strong>Status:</strong> Output function is working correctly!</p>
            </div>
            
            <div class="alert-info">
                <h3>📋 Module Variables</h3>
                <pre class="debug-vars"><?php echo htmlspecialchars(print_r($vars, true)); ?></pre>
            </div>
            
            <div class="alert-info">
                <h3>🔧 Environment Info</h3>
                <ul>
                    <li><strong>PHP Version:</strong> <?php echo PHP_VERSION; ?></li>
                    <li><strong>WHMCS Defined:</strong> <?php echo defined('WHMCS') ? 'Yes' : 'No'; ?></li>
                    <li><strong>Output Buffering Level:</strong> <?php echo ob_get_level(); ?></li>
                    <li><strong>Headers Sent:</strong> <?php echo headers_sent() ? 'Yes' : 'No'; ?></li>
                    <li><strong>Memory Usage:</strong> <?php echo memory_get_usage(true); ?></li>
                </ul>
            </div>
            
            <div class="alert-success">
                <h3>🎯 Next Steps</h3>
                <p>If you can see this page, the ORRISM Administration module is working!</p>
                <p>Ready to restore full functionality.</p>
            </div>
        </div>
    </body>
    </html>
    <?php
    
    // 获取缓冲内容
    $content = ob_get_contents();
    ob_end_clean();
    
    // 记录输出长度
    error_log('ORRISM DEBUG: Generated output length = ' . strlen($content));
    
    // 直接输出到浏览器
    echo $content;
    
    // 刷新输出缓冲区
    if (ob_get_level()) {
        ob_flush();
    }
    flush();
    
    // 也返回内容作为后备
    return $content;
}

// 在文件末尾记录加载
error_log('ORRISM DEBUG: Module file loaded completely at ' . date('Y-m-d H:i:s'));
file_put_contents('/tmp/orrism_debug.log', 
    '[' . date('Y-m-d H:i:s') . '] Module file loaded' . "\n", 
    FILE_APPEND
);