<?php
/**
 * MSSM - ShadowSocks Manager Module for WHMCS
 *
 * @package    WHMCS
 * @author     MSSM Development Team
 * @copyright  Copyright (c) 2022-2024
 * @version    1.0
 */

// 检查是否直接访问此文件
if (!defined('WHMCS')) {
    header('Location: ../../../index.php');
    exit;
}

// 使用标准WHMCS命名空间
use WHMCS\Database\Capsule;
use WHMCS\Session;

/**
 * 自定义的MSSM语言处理类，完全独立于i18n
 */
class MSSM_Lang {
    private static $translations = [];
    private static $loaded = false;
    private static $defaultLang = 'en';
    
    /**
     * 加载语言文件
     * @param string $langFile 语言文件路径
     * @return bool 是否成功加载
     */
    public static function load($langFile) {
        if (!file_exists($langFile)) {
            error_log("MSSM语言文件不存在: {$langFile}");
            return false;
        }
        
        try {
            // 解析INI文件
            $translations = parse_ini_file($langFile, true);
            if ($translations === false) {
                error_log("MSSM语言文件解析失败: {$langFile}");
                return false;
            }
            
            // 展平数组结构，使用点号连接键名
            foreach ($translations as $section => $items) {
                if (is_array($items)) {
                    foreach ($items as $key => $value) {
                        self::$translations["{$section}.{$key}"] = $value;
                    }
                }
            }
            
            self::$loaded = true;
            return true;
        } catch (Exception $e) {
            error_log("MSSM语言文件加载异常: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 获取翻译文本
     * @param string $key 语言键名
     * @param array $params 替换参数
     * @return string 翻译文本
     */
    public static function get($key, $params = []) {
        // 如果未加载语言文件，返回键名
        if (!self::$loaded) {
            return $key;
        }
        
        // 查找翻译文本
        $text = isset(self::$translations[$key]) ? self::$translations[$key] : $key;
        
        // 替换参数
        if (!empty($params)) {
            foreach ($params as $param => $value) {
                $text = str_replace("{{$param}}", $value, $text);
            }
        }
        
        return $text;
    }
}

// 定义MSSM_L类作为语言常量容器，而不是使用L类，避免命名冲突
if (!class_exists('MSSM_L')) {
    global $CONFIG;

    class MSSM_L {
        // 所有语言常量定义（以中文为主）
        const product_reset_uuid_success = '重置节点 UUID 成功';
        const product_reset_bandwidth = '重置流量';
        const product_reset_uuid = '重置UUID';
        const product_reset_bandwidth_success = '流量重置成功';
        const product_reset_bandwidth_error = '流量重置失败';
        const admin_bandwidth = '总流量';
        const common_upload = '上传流量';
        const common_download = '下载流量';
        const common_left = '剩余流量';
        const common_used = '已用流量';
        const common_created_at = '创建时间';
        const common_prohibit = '禁止操作';
        const error_account_already_exists = '账号已存在';
        const error_account_not_found = '账号未找到';
        const traffic_reset_success = '流量重置成功';
        const client_uuid = 'UUID';
        const common_total = '总计';
        const common_error = '错误';
        const common_allow = '允许';
        const common_loading = '加载中';
        
        // 仅英文常量（无重复）
        const admin_database = 'Database';
        const admin_reset_strategy = 'Reset strategy';
        const admin_end_of_month = 'End of month';
        const admin_start_of_month = 'Start of month';
        const admin_order_date = 'Order date';
        const admin_no_reset = 'No reset';
        const admin_node_list = 'Node list';
        const admin_manual_reset_bandwidth_option = 'Manual reset bandwidth option';
        const admin_reset_bandwidth_cost_percentage = 'Reset bandwidth cost percentage';
        const admin_node_group_id = 'Node group ID';
        
        // 客户端常量
        const client_reset = '重置流量';
        const client_upgrade = '升级套餐';
        const client_renewal = '续费服务';
        const client_node_name = '节点名称';
        const client_node_status = '节点状态';
        const client_bandwidth = '带宽';
        const client_node_rate = '倍率';
        const client_node_tag = '标签';
        const client_question_reset_bandwidth = '确定要重置流量';
        const client_warn_reset_bandwidth = '重置流量可能需要额外付费，请确认后操作';
        const client_copy = '复制';
        const client_import = '导入';
        const client_import_to_choc = '导入到 Choc';
        const client_copy_subscribe_url = '复制订阅地址';
        const client_import_to_clash = '导入到 Clash';
        const client_copy_success = '复制成功';
        
        // 订阅记录相关
        const client_subscription_records = '订阅记录';
        const client_access_time = '访问时间';
        const client_ip_address = 'IP地址';
        const client_app_type = '客户端类型';
        const client_no_records = '暂无记录';
        
        /**
         * 获取语言文本（兼容方法）
         * @param string $key 
         * @return string
         */
        public static function __callStatic($key, $args) {
            return MSSM_Lang::get($key);
        }
    }
    
    // 为了兼容性，如果不存在L类，则定义一个别名
    // 注释掉这段代码，避免与php-i18n库的L类冲突
    // if (!class_exists('L')) {
    //     class_alias('MSSM_L', 'L');
    // }
}

// 初始化语言
$language = Session::get('Language', 'english');
$langMap = [
    'english' => 'en_GB',
    'chinese' => 'zh_CN',
];
$langCode = $langMap[$language] ?? 'en_GB';
$langFile = __DIR__ . "/lang/lang_{$langCode}.ini";
if (file_exists($langFile)) {
    MSSM_Lang::load($langFile);
} else {
    // 尝试加载默认英文语言
    $defaultLangFile = __DIR__ . "/lang/lang_en_GB.ini";
    if (file_exists($defaultLangFile)) {
        MSSM_Lang::load($defaultLangFile);
    }
}

// 定义常量，控制是否自动检查和创建表
define('MSSM_AUTO_CHECK_TABLES', false);

// 依赖加载 - 标准WHMCS方式
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/api/database.php';

// 加载自定义库
require_once __DIR__ . '/lib/uuid.php';
require_once __DIR__ . '/lib/yaml.php';

// 加载模块核心文件
$coreFiles = [
    __DIR__ . '/helper.php',
    __DIR__ . '/api/user.php',
    __DIR__ . '/api/traffic.php',
    __DIR__ . '/api/product.php'
];

foreach ($coreFiles as $file) {
    if (file_exists($file)) {
        require_once $file;
    } else {
        error_log("MSSM模块错误: 核心文件不存在 - {$file}");
    }
}

/**
 * 生成UUID（兼容函数）
 * 如果存在Ramsey UUID库则使用，否则使用我们的简单实现
 */
function mssm_uuid_generate() {
    if (class_exists('Ramsey\Uuid\Uuid', false)) {
        return Ramsey\Uuid\Uuid::uuid4()->toString();
    } else {
        return mssm_generate_uuid();
    }
}

/**
 * 获取模块元数据
 * @return array
 */
function mssm_MetaData() {
    return [
        'DisplayName'    => 'ShadowSocks Manager',
        'APIVersion'     => '1.1',
        'RequiresServer' => true,
        'DefaultNonSSLPort' => '80',
        'DefaultSSLPort' => '443',
        'ServiceSingleSignOnLabel' => 'Login to Panel',
        'AdminSingleSignOnLabel' => 'Login to Admin Panel',
    ];
}

/**
 * 配置项定义
 * @return array
 */
function mssm_ConfigOptions() {
    return [
        // 管理数据库
        MSSM_L::admin_database => ['Type' => 'text', 'Size' => '25'],
        // 重置策略
        MSSM_L::admin_reset_strategy => [
            'Type'    => 'dropdown',
            'Options' => [
                '3' => MSSM_L::admin_end_of_month,
                '2' => MSSM_L::admin_start_of_month,
                '1' => MSSM_L::admin_order_date,
                '0' => MSSM_L::admin_no_reset
            ]
        ],
        // 节点列表
        MSSM_L::admin_node_list => [
            'Type'        => 'textarea',
            'Rows'        => '3',
            'Cols'        => '50',
            'Description' => MSSM_L::admin_node_list
        ],
        // 带宽
        MSSM_L::admin_bandwidth => [
            'Type'        => 'text',
            'Size'        => '25',
            'Description' => 'GB'
        ],
        // 手动重置带宽选项
        MSSM_L::admin_manual_reset_bandwidth_option => [
            'Type'    => 'dropdown',
            'Options' => ['1' => MSSM_L::common_allow, '0' => MSSM_L::common_prohibit]
        ],
        // 重置带宽费用百分比
        MSSM_L::admin_reset_bandwidth_cost_percentage => [
            'Type' => 'text',
            'Size' => '25'
        ],
        // 节点分组ID
        MSSM_L::admin_node_group_id => [
            'Type' => 'text',
            'Size' => '25'
        ]
    ];
}

/**
 * 测试数据库和Redis连接
 * @param array $params
 * @return array
 */
function mssm_TestConnection(array $params) {
    try {
        $config = mssm_get_config();
        $mysql_host = $params['serverhostname'] ?? $params['serverip'] ?? ($config['mysql_host'] ?? null);
        $redis_host = $config['redis_host'] ?? null;
        $redis_port = $config['redis_port'] ?? null;
        $redis_pass = $config['redis_pass'] ?? null;

        if (!$mysql_host) {
            throw new Exception('无法获取数据库服务器地址');
        }

        if ($redis_host && $redis_port) {
            if (class_exists('Redis')) {
                $redis = new Redis();
                $redis->connect($redis_host, $redis_port);
                if ($redis_pass) {
                    $redis->auth($redis_pass);
                }
                $redis->close();
            } else {
                // Redis类不可用，但不阻止连接测试
                error_log('MSSM模块警告: Redis扩展未安装');
            }
        } else {
            error_log('MSSM模块警告: Redis配置不完整');
        }

        $mysql_user = $params['serverusername'] ?? null;
        $mysql_pass = $params['serverpassword'] ?? null;
        
        // 使用PDO测试MySQL连接
        if (class_exists('PDO')) {
            $mysql = new PDO('mysql:host=' . $mysql_host, $mysql_user, $mysql_pass);
            $mysql = null;
        } else {
            // 使用mysqli作为备选
            $mysqli = mysqli_connect($mysql_host, $mysql_user, $mysql_pass);
            mysqli_close($mysqli);
        }
        
        $success = true;
        $errorMsg = '';
    } catch (Exception $e) {
        logModuleCall('mssm', 'mssm_TestConnection', $params, $e->getMessage(), $e->getTraceAsString());
        $success = false;
        $errorMsg = $e->getMessage();
    }
    return ['success' => $success, 'error' => $errorMsg];
}

/**
 * 创建账号
 * @param array $params
 * @return array
 */
function mssm_CreateAccount($params) {
    return mssm_user_create_account($params);
}

/**
 * 暂停账号
 * @param array $params
 * @return array
 */
function mssm_SuspendAccount($params) {
    return mssm_user_suspend_account($params);
}

/**
 * 解除暂停账号
 * @param array $params
 * @return array
 */
function mssm_UnsuspendAccount($params) {
    return mssm_user_unsuspend_account($params);
}

/**
 * 删除账号
 * @param array $params
 * @return array
 */
function mssm_TerminateAccount($params) {
    return mssm_user_terminate_account($params);
}

/**
 * 变更套餐
 * @param array $params
 * @return array
 */
function mssm_ChangePackage($params) {
    return mssm_product_change_package($params);
}

/**
 * 管理员自定义按钮
 * @return array
 */
function mssm_AdminCustomButtonArray() {
    return mssm_user_admin_custom_button_array();
}

/**
 * 客户区展示
 * @param array $params
 * @return array
 */
function mssm_ClientArea($params) {
    return mssm_user_client_area($params);
}

/**
 * 用户带宽重置
 * @param array $params
 */
function mssm_reset_bandwidth_user($params) {
    return mssm_traffic_reset_bandwidth_user($params);
}

/**
 * 管理员带宽重置
 * @param array $params
 * @return string|Exception
 */
function mssm_reset_bandwidth_admin($params) {
    return mssm_traffic_reset_bandwidth_admin($params);
}

/**
 * UUID重置
 * @param array $params
 * @return string The result message for the WHMCS admin interface.
 */
function mssm_module_reset_uuid($params) {
    $result = mssm_user_reset_uuid($params);

    // Ensure $result is an array for safe access, default to an empty array if not.
    if (!is_array($result)) {
        // If $result was a non-empty string, treat it as a direct message.
        if (is_string($result) && !empty($result)) {
            return $result;
        }
        // Otherwise, $result is not a usable array or string, so indicate an error.
        return ['error' => '模块内部函数返回了无效的响应类型。'];
    }

    // Priority 1: Handle explicit 'error' status from mssm_user_reset_uuid
    if (isset($result['status']) && $result['status'] === 'error') {
        $errorMessage = (isset($result['msg']) && is_string($result['msg']) && !empty($result['msg']))
                        ? $result['msg']
                        : '操作过程中发生错误，但未提供详细信息。';
        return ['error' => $errorMessage];
    }

    // Priority 2: Handle explicit 'success' status from mssm_user_reset_uuid
    if (isset($result['status']) && $result['status'] === 'success') {
        // If a specific success message is provided, use it.
        if (isset($result['msg']) && is_string($result['msg']) && !empty($result['msg'])) {
            return $result['msg'];
        }
        // Otherwise, use the default success message.
        return MSSM_L::product_reset_uuid_success;
    }

    // Priority 3: Handle cases where 'msg' is present, but status is not explicitly 'error' or 'success'
    if (isset($result['msg']) && is_string($result['msg']) && !empty($result['msg'])) {
        return $result['msg'];
    }
    
    // Fallback: If the $result array structure doesn't match any of the above known patterns
    return ['error' => '模块操作已执行，但返回了未知或不明确的状态。'];
}

/**
 * 管理服务Tab字段
 * @param array $params
 * @return array|Exception
 */
function mssm_AdminServicesTabFields($params) {
    return mssm_user_admin_services_tab_fields($params);
}
