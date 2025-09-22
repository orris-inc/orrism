<?php
/**
 * MSSM - ShadowSocks Manager Module for WHMCS
 *
 * @package    WHMCS
 * @author     MSSM Development Team
 * @copyright  Copyright (c) 2022-2024
 * @version    1.0
 */

/**
 * 获取系统配置
 * 
 * @param string $key 可选，指定获取特定配置项
 * @return array|mixed 返回配置数组或指定的配置项
 */
function mssm_get_config($key = null) {
    // 定义配置文件路径
    $config_file = __DIR__ . '/config.local.php';
    
    // 基础配置
    $config = [
        // API相关配置
        'api_key' => getenv('SSM_API_KEY') ?: 'YJH2HikkEn1d',
        
        // 数据库配置
        'mysql_host' => getenv('SSM_MYSQL_HOST') ?: 'mysql',
        'mysql_db' => getenv('SSM_MYSQL_DB') ?: 'ssmanage',        
        'mysql_user' => getenv('SSM_MYSQL_USER') ?: 'root',
        'mysql_pass' => getenv('SSM_MYSQL_PASS') ?: 'root',
        'mysql_port' => getenv('SSM_MYSQL_PORT') ?: '3306',
        
        // Redis配置
        'redis_host' => getenv('SSM_REDIS_HOST') ?: 'redis',
        'redis_port' => getenv('SSM_REDIS_PORT') ?: '6379',
        'redis_pass' => getenv('SSM_REDIS_PASS') ?: '',
        
        // 管理设置
        'admin_username' => getenv('SSM_ADMIN_USERNAME') ?: 'root',
        
        // 订阅相关设置
        'subscribe_urls' => [
            'localhost:8080/modules/servers/ssm/api'
        ],
    ];
    
    // 如果存在本地配置文件，加载并覆盖默认配置
    if (file_exists($config_file)) {
        $local_config = include($config_file);
        if (is_array($local_config)) {
            $config = array_merge($config, $local_config);
        }
    }
    
    // 自动选择订阅URL
    if (!isset($config['subscribe_url']) && isset($config['subscribe_urls']) && !empty($config['subscribe_urls'])) {
        $config['subscribe_url'] = $config['subscribe_urls'][array_rand($config['subscribe_urls'], 1)];
    }
    
    // 如果指定了获取特定配置项
    if ($key !== null) {
        return $config[$key] ?? null;
    }
    
    return $config;
}

/**
 * 设置配置项（运行时）
 * 
 * @param string $key 配置键名
 * @param mixed $value 配置值
 * @return bool 设置是否成功
 */
function mssm_set_config($key, $value) {
    static $runtime_config = [];
    $runtime_config[$key] = $value;
    return true;
}
