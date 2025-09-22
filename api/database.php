<?php
/**
 * ORRISM - ShadowSocks Manager Module for WHMCS
 *
 * @package    WHMCS
 * @author     ORRISM Development Team
 * @copyright  Copyright (c) 2022-2024
 * @version    1.0
 */

require_once __DIR__ . '/../config.php';

/**
 * 获取数据库连接
 * @return PDO
 * @throws Exception 当数据库连接失败时抛出
 */
function orrism_get_db_connection() {
    $config = orrism_get_config();
    
    try {
        $conn = new PDO(
            "mysql:host={$config['mysql_host']};port={$config['mysql_port']};dbname={$config['mysql_db']};charset=utf8mb4",
            $config['mysql_user'],
            $config['mysql_pass'],
            [
                PDO::ATTR_TIMEOUT => 5,
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
        return $conn;
    } catch (PDOException $e) {
        throw new Exception("数据库连接失败: " . $e->getMessage());
    }
}

/**
 * 获取Redis连接
 * @param int $index
 * @return Redis|null
 */
function orrism_get_redis_connection($index = 0) {
    static $redis_connections = [];
    if (!isset($redis_connections[$index])) {
        $config = orrism_get_config();
        $redis = new Redis();
        try {
            $redis->connect($config['redis_host'], $config['redis_port'], 2.0); // 2秒超时
            if (isset($config['redis_pass']) && !empty($config['redis_pass'])) {
                $redis->auth($config['redis_pass']);
            }
            $redis->select($index);
            $redis_connections[$index] = $redis;
        } catch (Exception $e) {
            error_log("Redis连接失败: " . $e->getMessage());
            return null;
        }
    }
    return $redis_connections[$index];
}

/**
 * Redis操作
 * @param string $key 键名
 * @param mixed $value 值
 * @param string $action 操作类型 (set, get, del, incrBy)
 * @param int $index Redis数据库索引
 * @param int $ttl 过期时间(秒)，仅对set操作有效，0表示使用默认值
 * @return mixed 操作结果
 */
function orrism_set_redis($key, $value, $action, $index = 0, $ttl = 0) {
    try {
        $redis = orrism_get_redis_connection($index);
        if (!$redis) {
            return false;
        }
        switch ($action) {
            case 'set':
                // 如果没有提供TTL，则使用默认值
                if ($ttl <= 0) {
                    $ttl = ($index == 1) ? 3600 : 60;
                }
                return $redis->set($key, $value, $ttl);
            case 'del':
                return $redis->del($key);
            case 'get':
                return $redis->get($key);
            case 'incrBy':
                return $redis->incrBy($key, $value);
            case 'exists':
                return $redis->exists($key);
            case 'ttl':
                return $redis->ttl($key);
            case 'expire':
                return $redis->expire($key, $value); // value为TTL
            case 'hSet':
                return $redis->hSet($key, $value[0], $value[1]);
            case 'hGet':
                return $redis->hGet($key, $value);
            case 'hGetAll':
                return $redis->hGetAll($key);
            case 'hDel':
                return $redis->hDel($key, $value);
            default:
                return false;
        }
    } catch (Exception $e) {
        error_log("Redis操作失败: " . $e->getMessage());
        return false;
    }
}

/**
 * 检查并初始化必要的数据库表
 * @return bool
 * @throws Exception 当数据库操作失败时抛出
 */
function orrism_check_and_init_tables() {
    $conn = orrism_get_db_connection();
    try {
        // 检查nodes表是否存在
        $stmt = $conn->query("SHOW TABLES LIKE 'nodes'");
        $nodes_table_exists = $stmt->rowCount() > 0;

        if (!$nodes_table_exists) {
            // 创建nodes表
            $sql = "CREATE TABLE `nodes` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `name` varchar(128) NOT NULL COMMENT '节点名称',
                `server` varchar(128) NOT NULL COMMENT '服务器地址',
                `port` int(11) NOT NULL COMMENT '服务端口',
                `type` varchar(32) NOT NULL DEFAULT 'ss' COMMENT '节点类型',
                `method` varchar(32) NOT NULL DEFAULT 'aes-256-gcm' COMMENT '加密方式',
                `info` varchar(128) NOT NULL DEFAULT '' COMMENT '节点信息',
                `status` tinyint(4) NOT NULL DEFAULT '1' COMMENT '状态 0-维护中 1-正常',
                `sort` int(11) NOT NULL DEFAULT '0' COMMENT '排序',
                `traffic_rate` float NOT NULL DEFAULT '1' COMMENT '流量倍率',
                `node_group` int(11) NOT NULL DEFAULT '0' COMMENT '节点分组',
                `online_user` int(11) NOT NULL DEFAULT '0' COMMENT '在线用户数',
                `max_user` int(11) NOT NULL DEFAULT '0' COMMENT '最大用户数',
                `updated_at` int(11) NOT NULL DEFAULT '0' COMMENT '更新时间',
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COMMENT='节点信息表';";
            
            $conn->exec($sql);
            
            // 添加一个默认节点作为示例
            $sql = "INSERT INTO `nodes` (`name`, `server`, `port`, `type`, `method`, `info`, `status`, `sort`, `traffic_rate`, `node_group`) 
                    VALUES ('默认节点', 'example.com', 443, 'ss', 'aes-256-gcm', '示例节点 - 请修改', 1, 0, 1.0, 0);";
            $conn->exec($sql);
            
            error_log("nodes表已创建并添加默认节点");
        }
        
        return true;
    } catch (PDOException $e) {
        error_log("数据库表检查/初始化失败: " . $e->getMessage());
        throw new Exception("数据库表检查/初始化失败: " . $e->getMessage());
    }
}

// 仅在必要时检查表结构
if (defined('ORRISM_AUTO_CHECK_TABLES') && ORRISM_AUTO_CHECK_TABLES) {
    try {
        orrism_check_and_init_tables();
    } catch (Exception $e) {
        error_log("自动检查表失败: " . $e->getMessage());
        // 不终止执行，让错误在需要时显示
    }
}

/**
 * 获取用户Redis统计数据
 * @param string $sid 用户SID
 * @return array 用户统计数据
 */
function orrism_get_user_redis_stats($sid) {
    $result = [
        'last_ip' => '',
        'last_access' => 0,
        'created_at' => 0,
        'ip_history' => [],
        'app_stats' => [],
        'ttl' => 0
    ];
    
    try {
        $user_key = "user_data:{$sid}";
        
        // 检查键是否存在
        $exists = orrism_set_redis($user_key, null, 'exists', 0);
        if (!$exists) {
            return $result; // 返回默认空数据
        }
        
        // 获取所有用户数据
        $user_data = orrism_set_redis($user_key, null, 'hGetAll', 0);
        if (!$user_data) {
            return $result;
        }
        
        // 填充结果数组
        $result['last_ip'] = $user_data['last_ip'] ?? '';
        $result['last_access'] = isset($user_data['last_access']) ? intval($user_data['last_access']) : 0;
        $result['created_at'] = isset($user_data['created_at']) ? intval($user_data['created_at']) : 0;
        
        // 解析JSON数据
        $result['ip_history'] = isset($user_data['ip_history']) ? json_decode($user_data['ip_history'], true) : [];
        $result['app_stats'] = isset($user_data['app_stats']) ? json_decode($user_data['app_stats'], true) : [];
        
        // 获取过期时间
        $result['ttl'] = orrism_set_redis($user_key, null, 'ttl', 0);
        
        // 计算最后一次访问距今天数
        if ($result['last_access'] > 0) {
            $result['days_since_last_access'] = floor((time() - $result['last_access']) / 86400);
        }
        
        // 计算账户年龄（天）
        if ($result['created_at'] > 0) {
            $result['account_age_days'] = floor((time() - $result['created_at']) / 86400);
        }
        
    } catch (Exception $e) {
        error_log("获取用户Redis统计数据失败: " . $e->getMessage());
    }
    
    return $result;
}

/**
 * 获取IP使用统计
 * @param string $ip 要查询的IP地址
 * @return array IP使用统计
 */
function orrism_get_ip_stats($ip) {
    $result = [
        'used_by' => [],
        'detailed_info' => []
    ];
    
    try {
        // 从全局IP映射获取数据
        $ip_key = "ip_sid_map";
        $ip_map_json = orrism_set_redis($ip_key, null, 'get', 0);
        
        if (!$ip_map_json) {
            return $result;
        }
        
        $ip_map = json_decode($ip_map_json, true);
        $formatted_ip = str_replace('.', '_', $ip);
        
        // 获取使用此IP的SID列表
        $result['used_by'] = $ip_map[$formatted_ip] ?? [];
        
        // 获取每个用户的详细信息
        foreach ($result['used_by'] as $sid) {
            $user_stats = orrism_get_user_redis_stats($sid);
            
            // 查找此IP最后一次被此用户使用的时间
            $last_used = null;
            if (!empty($user_stats['ip_history'])) {
                foreach ($user_stats['ip_history'] as $history) {
                    if ($history['ip'] === $ip) {
                        $last_used = $history['time'];
                        $last_app = $history['app'];
                    }
                }
            }
            
            $result['detailed_info'][$sid] = [
                'last_used' => $last_used,
                'last_app' => $last_app ?? null,
                'account_age_days' => $user_stats['account_age_days'] ?? 0
            ];
        }
        
    } catch (Exception $e) {
        error_log("获取IP统计数据失败: " . $e->getMessage());
    }
    
    return $result;
}

/**
 * 获取每日访问统计
 * @param int $days 要获取的天数，默认为7天
 * @return array 每日访问统计
 */
function orrism_get_daily_stats($days = 7) {
    $result = [];
    
    try {
        // 获取指定天数的访问统计
        for ($i = 0; $i < $days; $i++) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $date_key = "stats:daily:" . $date;
            $visits = orrism_set_redis($date_key, null, 'get', 0);
            $result[$date] = $visits ? intval($visits) : 0;
        }
        
    } catch (Exception $e) {
        error_log("获取每日访问统计失败: " . $e->getMessage());
    }
    
    return $result;
}