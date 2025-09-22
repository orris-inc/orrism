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
 * 安全相关工具
 */
class Security {
    /**
     * 设置安全响应头
     */
    public static function setSecureHeaders() {
        header("X-Content-Type-Options: nosniff");
        header("X-Frame-Options: DENY");
        header("X-XSS-Protection: 1; mode=block");
        header("Referrer-Policy: strict-origin-when-cross-origin");
        header("Content-Security-Policy: default-src 'self'");
    }
    
    /**
     * 获取客户端真实IP地址
     * 考虑了各种代理情况
     * @return string 客户端IP地址
     */
    public static function getClientIp() {
        // 可能的包含客户端IP的HTTP头
        $ip_headers = [
            'HTTP_X_FORWARDED_FOR',
            'HTTP_CLIENT_IP',
            'HTTP_X_REAL_IP',
            'HTTP_X_FORWARDED',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];
        
        foreach ($ip_headers as $header) {
            if (!empty($_SERVER[$header])) {
                // 如果是多个IP的列表，取第一个（最可能是客户端真实IP）
                if (strpos($_SERVER[$header], ',') !== false) {
                    $ips = explode(',', $_SERVER[$header]);
                    $ip = trim($ips[0]);
                } else {
                    $ip = $_SERVER[$header];
                }
                
                // 验证IP格式
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        
        // 默认返回REMOTE_ADDR或未知
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    /**
     * 记录客户端IP到Redis
     * @param string $sid 用户SID
     * @param string $ip 客户端IP
     * @param string $app 请求类型
     * @return bool 是否成功记录
     */
    public static function recordIpToRedis($sid, $ip, $app) {
        try {
            $current_time = time();
            $oneMonth = 2592000;  // 30天过期时间
            
            // 使用单个哈希表存储所有SID相关信息 - 键: user_data:{sid}
            $user_key = "user_data:{$sid}";
            
            // 检查哈希表是否存在，不存在则初始化
            $exists = mssm_set_redis($user_key, null, 'exists', 0);
            if (!$exists) {
                // 初始化新用户数据
                mssm_set_redis($user_key, ['created_at', $current_time], 'hSet', 0);
                mssm_set_redis($user_key, ['ip_history', json_encode([])], 'hSet', 0);
                mssm_set_redis($user_key, ['app_stats', json_encode([])], 'hSet', 0);
                
                // 设置过期时间
                mssm_set_redis($user_key, $oneMonth, 'expire', 0);
            } else {
                // 更新过期时间
                mssm_set_redis($user_key, $oneMonth, 'expire', 0);
            }
            
            // 更新最近IP和访问时间
            mssm_set_redis($user_key, ['last_ip', $ip], 'hSet', 0);
            mssm_set_redis($user_key, ['last_access', $current_time], 'hSet', 0);
            
            // 更新IP历史
            $ip_history_json = mssm_set_redis($user_key, 'ip_history', 'hGet', 0);
            $ip_history = $ip_history_json ? json_decode($ip_history_json, true) : [];
            
            // 限制历史记录数量，只保留最近10条
            if (count($ip_history) >= 10) {
                array_shift($ip_history); // 移除最旧的记录
            }
            
            // 添加新记录
            $ip_history[] = [
                'ip' => $ip,
                'time' => $current_time,
                'app' => $app
            ];
            
            // 更新IP历史记录
            mssm_set_redis($user_key, ['ip_history', json_encode($ip_history)], 'hSet', 0);
            
            // 更新APP使用统计
            $app_stats_json = mssm_set_redis($user_key, 'app_stats', 'hGet', 0);
            $app_stats = $app_stats_json ? json_decode($app_stats_json, true) : [];
            
            if (!isset($app_stats[$app])) {
                $app_stats[$app] = 0;
            }
            $app_stats[$app]++;
            
            // 更新APP统计数据
            mssm_set_redis($user_key, ['app_stats', json_encode($app_stats)], 'hSet', 0);
            
            // 记录全局IP映射，用于反查
            $ip_key = "ip_sid_map";
            $ip_map_json = mssm_set_redis($ip_key, null, 'get', 0);
            $ip_map = $ip_map_json ? json_decode($ip_map_json, true) : [];
            
            // 格式化IP作为键名
            $formatted_ip = str_replace('.', '_', $ip);
            if (!isset($ip_map[$formatted_ip])) {
                $ip_map[$formatted_ip] = [];
            }
            
            // 如果SID不在列表中，添加它
            if (!in_array($sid, $ip_map[$formatted_ip])) {
                $ip_map[$formatted_ip][] = $sid;
                // 最多保留10个SID，防止列表过长
                if (count($ip_map[$formatted_ip]) > 10) {
                    array_shift($ip_map[$formatted_ip]);
                }
            }
            
            // 更新IP映射
            mssm_set_redis($ip_key, json_encode($ip_map), 'set', 0, $oneMonth);
            
            // 更新每日统计
            $date_key = "stats:daily:" . date('Y-m-d');
            mssm_set_redis($date_key, 1, 'incrBy', 0, 86400); // 1天有效期
            
            return true;
        } catch (Exception $e) {
            error_log("MSSM API Services Error: 记录IP到Redis失败: " . $e->getMessage());
            return false;
        }
    }
}

/**
 * 请求处理工具
 */
class RequestHandler {
    /**
     * 检查是否超过请求频率限制
     * @param string $sid 用户SID
     * @param string $ip 客户端IP
     * @return bool 如果未超过限制返回true，否则返回false
     */
    public static function checkRateLimit($sid, $ip) {
        // 基于用户ID和IP的限制文件
        $rate_limit_file = sys_get_temp_dir() . "/mssm_rate_limit_" . md5($sid . '_' . $ip);
        $max_requests = 10; // 最大请求次数
        $time_window = 60; // 时间窗口（秒）
        
        if (file_exists($rate_limit_file)) {
            $requests = json_decode(file_get_contents($rate_limit_file), true);
            
            // 清理过期的请求记录
            $current_time = time();
            $requests = array_filter($requests, function($timestamp) use ($current_time, $time_window) {
                return ($current_time - $timestamp) < $time_window;
            });
            
            // 检查是否超过频率限制
            if (count($requests) >= $max_requests) {
                return false;
            }
        } else {
            $requests = [];
        }
        
        // 添加新的请求记录
        $requests[] = time();
        file_put_contents($rate_limit_file, json_encode($requests));
        
        return true;
    }
    
    /**
     * 验证请求参数是否有效
     * @param string $app 请求类型
     * @param string $token 用户令牌
     * @param string $sid 用户SID
     * @param array $flag 允许的类型列表
     * @return bool
     */
    public static function isValidRequest($app, $token, $sid, $flag) {
        // 检查必要参数是否存在
        if (empty($app) || empty($token) || empty($sid)) {
            return false;
        }
        
        // 检查类型是否在允许列表中
        if (!in_array($app, $flag)) {
            return false;
        }
        
        // 验证SID是否为纯数字
        if (!preg_match("/^\d+$/", $sid)) {
            return false;
        }
        
        // 验证token的格式，至少应为16位字符
        if (strlen($token) < 16) {
            return false;
        }
        
        return true;
    }
    
    /**
     * 安全处理请求错误
     * @param string $status HTTP状态码
     * @param string $message 日志消息
     * @param string $ip 客户端IP
     * @param string $user_message 返回给用户的消息
     */
    public static function handleError($status, $message, $ip, $user_message) {
        header("HTTP/1.1 $status");
        error_log("MSSM API Services Error [IP: $ip]: $message");
        exit($user_message);
    }
}

/**
 * 通用工具
 */
class Utils {
    /**
     * 转换字节大小为可读格式
     * @param int $size 字节大小
     * @param int $digits 小数位数
     * @return string 可读大小
     */
    public static function convertByte($size, $digits = 2) {
        if ($size == 0) {
            return '0 B';
        }
        $unit = ['','K','M','G','T','P'];
        $base = 1024;
        $i = floor(log($size, $base));
        $i = min($i, count($unit) - 1);
        // Ensure $i is a valid index before accessing $unit[$i]
        if ($i < 0) $i = 0; 
        return round($size / pow($base, $i), $digits) . ' ' . $unit[$i] . 'B';
    }
    
    /**
     * 获取用户订阅拉取记录
     * @param string $sid 用户SID
     * @param int $limit 最大记录数量，默认为10
     * @return array 订阅拉取记录
     */
    public static function getSubscriptionRecords($sid, $limit = 10) {
        try {
            $user_key = "user_data:{$sid}";
            
            // 检查用户数据是否存在
            $exists = mssm_set_redis($user_key, null, 'exists', 0);
            if (!$exists) {
                return [];
            }
            
            // 获取IP历史记录
            $ip_history_json = mssm_set_redis($user_key, 'ip_history', 'hGet', 0);
            if (empty($ip_history_json)) {
                return [];
            }
            
            $ip_history = json_decode($ip_history_json, true) ?: [];
            
            // 按时间倒序排序
            usort($ip_history, function($a, $b) {
                return $b['time'] - $a['time'];
            });
            
            // 限制返回记录数
            return array_slice($ip_history, 0, $limit);
        } catch (Exception $e) {
            error_log("MSSM API Services Error: 获取订阅记录失败: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * 获取用户应用使用统计
     * @param string $sid 用户SID
     * @return array 应用使用统计
     */
    public static function getAppUsageStats($sid) {
        try {
            $user_key = "user_data:{$sid}";
            
            // 检查用户数据是否存在
            $exists = mssm_set_redis($user_key, null, 'exists', 0);
            if (!$exists) {
                return [];
            }
            
            // 获取应用统计数据
            $app_stats_json = mssm_set_redis($user_key, 'app_stats', 'hGet', 0);
            if (empty($app_stats_json)) {
                return [];
            }
            
            return json_decode($app_stats_json, true) ?: [];
        } catch (Exception $e) {
            error_log("MSSM API Services Error: 获取应用统计失败: " . $e->getMessage());
            return [];
        }
    }
} 