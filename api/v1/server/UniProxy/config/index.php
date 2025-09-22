<?php
// 路径处理：使用基于当前文件的相对路径
$base_dir = dirname(__FILE__, 6); // 向上6级目录到达模块根目录
require_once $base_dir . '/config.php';
require_once $base_dir . '/api/database.php';
require_once $base_dir . '/helper.php';
require_once $base_dir . '/api/traffic.php';

header('Content-Type: application/json');

// 获取API密钥
$api_token = orrism_get_config('api_key');
if (!$api_token) {
    http_response_code(500);
    echo json_encode(['error' => 'API密钥未配置或过短']);
    exit;
}

/**
 * 验证请求令牌
 * @param string $token 请求中的令牌
 * @return bool 验证是否通过
 */
function orrism_verify_token($token) {
    $api_token = orrism_get_config('api_key') ?: '123456789';
    return $token === $api_token;
}

// 获取请求参数
$act = isset($_GET['act']) ? $_GET['act'] : 'config';
$token = isset($_GET['token']) ? $_GET['token'] : '';
$node_id = isset($_GET['node_id']) ? intval($_GET['node_id']) : 0;
$node_type = isset($_GET['node_type']) ? $_GET['node_type'] : '';

// 验证令牌
if (!orrism_verify_token($token)) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

// 请求处理逻辑
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // 处理GET请求
    orrism_handleGetRequest($act, $node_id, $node_type);
} else if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 处理POST请求
    orrism_handlePostRequest($act, $node_id);
} else {
    // 不支持的请求方法
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}

/**
 * 处理GET请求
 * @param string $act 操作类型
 * @param int $node_id 节点ID
 * @param string $node_type 节点类型
 */
function orrism_handleGetRequest($act, $node_id, $node_type) {
    $raw_data = file_get_contents('php://input');
    
    switch ($act) {
        case 'user':
            // 获取指定节点的用户列表
            $users = orrism_get_node_users($node_id, $node_type);
            echo json_encode(['data' => $users]);
            break;
            
        case 'node_info':
            // 获取指定节点信息
            $node = orrism_get_node_info($node_id);
            echo json_encode($node);
            break;
            
        case 'config':
            // 获取节点配置
            if ($node_id > 0) {
                $nodes = orrism_get_node_by_id($node_id);
            } else {
                $nodes = orrism_get_all_nodes();
            }
            $config = orrism_format_config($nodes);
            echo json_encode($config);
            break;            
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
}

/**
 * 处理POST请求
 * @param string $act 操作类型
 * @param int $node_id 节点ID
 */
function orrism_handlePostRequest($act, $node_id) {
    $raw_data = file_get_contents('php://input');
    $data = json_decode($raw_data, true);
    
    switch ($act) {
        case 'submit':
            // 处理流量上报
            $result = orrism_handle_traffic_report($data, $node_id);
            foreach ($data as $user_data) {
                orrism_report_traffic($user_data);
            }
            echo json_encode($result);
            break;
            
        case 'nodestatus':
            // 处理节点状态上报
            orrism_handleNodeStatusReport($data, $node_id);
            break;
            
        case 'sync_state_to_mysql':
            // 同步Redis中的状态到MySQL
            $result = orrism_sync_state_to_mysql();
            echo json_encode($result);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
}

/**
 * 处理节点状态上报
 * @param array $data 节点状态数据
 * @param int $node_id 节点ID
 */
function orrism_handleNodeStatusReport($data, $node_id) {
    // 检查数据格式
    if (!is_array($data) || !isset($data['cpu'], $data['mem'], $data['net'], $data['disk'], $data['uptime'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid data format']);
        return;
    }
    
    $redis = orrism_get_redis_connection();
    if (!$redis) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Redis connection failed']);
        return;
    }
    
    $key = "node_status:" . $node_id;
    $redis->set($key, json_encode($data));
    echo json_encode(['success' => true]);
}

/**
 * 获取所有节点信息
 * @return array 节点列表
 */
function orrism_get_all_nodes() {
    try {
        // 使用PDO获取所有节点
        $conn = orrism_get_db_connection();
        $query = "SELECT * FROM `nodes`";
        $stmt = $conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("获取全部节点失败: " . $e->getMessage());
        return [];
    }
}

/**
 * 获取指定ID的节点
 * @param int $node_id 节点ID
 * @return array 节点数组（为了与orrism_get_all_nodes格式一致，返回数组）
 */
function orrism_get_node_by_id($node_id) {
    try {
        $conn = orrism_get_db_connection();
        $query = "SELECT * FROM `nodes` WHERE `id` = :node_id";
        $stmt = $conn->prepare($query);
        $stmt->bindValue(':node_id', $node_id, PDO::PARAM_INT);
        $stmt->execute();
        $node = $stmt->fetch(PDO::FETCH_ASSOC);       
        // 返回数组格式，即使只有一个节点
        return $node ? [$node] : [];
    } catch (Exception $e) {
        error_log("获取节点ID={$node_id}失败: " . $e->getMessage());
        return [];
    }
}

/**
 * 获取指定节点的信息
 * @param int $node_id 节点ID
 * @return array 节点信息
 */
function orrism_get_node_info($node_id) {
    try {
        // 获取节点基本信息
        $conn = orrism_get_db_connection();
        $query = "SELECT * FROM `nodes` WHERE `id` = :node_id";
        $stmt = $conn->prepare($query);
        $stmt->bindValue(':node_id', $node_id, PDO::PARAM_INT);
        $stmt->execute();
        $node = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$node) {
            return ['error' => 'Node not found'];
        }
        
        // 获取节点的用户总数
        $node_group = $node['node_group'];
        $query = "SELECT COUNT(*) as user_count 
                 FROM `user` 
                 WHERE `enable` = 1";
                 
        // 如果节点有分组，则只统计该分组的用户
        if ($node_group > 0) {
            $query .= " AND (`group_id` = :node_group OR `group_id` = 0)";
        }
        
        $stmt = $conn->prepare($query);
        
        if ($node_group > 0) {
            $stmt->bindValue(':node_group', $node_group, PDO::PARAM_INT);
        }
        
        $stmt->execute();
        $count_result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // 添加更多节点信息
        $result = $node;
        $result['total_users'] = (int)$count_result['user_count'];
        $result['online_users'] = (int)$node['online_user'];
        
        // 如果节点状态为0，标记为维护中
        $result['status_description'] = ($node['status'] == 1) ? '正常' : '维护中';
        
        // 计算负载百分比
        if (isset($node['max_user']) && $node['max_user'] > 0) {
            $result['load_percent'] = round(($node['online_user'] / $node['max_user']) * 100, 1);
        } else {
            $result['load_percent'] = 0;
        }
        
        return $result;
    } catch (Exception $e) {
        error_log("获取节点信息失败: " . $e->getMessage());
        return ['error' => 'Database error: ' . $e->getMessage()];
    }
}

/**
 * 获取指定节点的用户列表
 * @param int $node_id 节点ID
 * @param string $node_type 节点类型
 * @return array 用户列表
 */
function orrism_get_node_users($node_id, $node_type) {
    try {
        // 获取节点信息，检查group_id和node_method
        $conn = orrism_get_db_connection();
        $query = "SELECT `group_id`, `node_method` FROM `nodes` WHERE `id` = :node_id";
        $stmt = $conn->prepare($query);
        $stmt->bindValue(':node_id', $node_id, PDO::PARAM_INT);
        $stmt->execute();
        $node = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$node) {
            return [];
        }
        
        $node_group = $node['group_id'];
        $cipher = $node['node_method'];
        
        // 查询启用的用户，根据节点组过滤
        $query = "SELECT `sid`, `uuid` FROM `user` WHERE `enable` = 1";
        if ($node_group > 0) {
            $query .= " AND (`node_group_id` = :node_group OR `node_group_id` = 0)";
        }
        $stmt = $conn->prepare($query);
        if ($node_group > 0) {
            $stmt->bindValue(':node_group', $node_group, PDO::PARAM_INT);
        }
        $stmt->execute();
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 组装数据
        $result = [];
        foreach ($users as $user) {
            // 动态生成password
            $password = null;
            switch ($cipher) {
                case '2022-blake3-aes-128-gcm':
                    $password = orrism_uuidToBase64($user['uuid'], 16);
                    break;
                case '2022-blake3-aes-256-gcm':
                    $password = orrism_uuidToBase64($user['uuid'], 32);
                    break;
                default:
                    $password = null;
            }
            $result[] = [
                'id' => $user['sid'],
                'shadowsocks_user' => [
                    'secret' => $user['uuid'],
                    'cipher' => $cipher,
                    'password' => $password,
                ]
            ];
        }
        return $result;
    } catch (Exception $e) {
        error_log("获取节点用户列表失败: " . $e->getMessage());
        return [];
    }
}

/**
 * 处理流量上报
 * @param array $data 上报的数据（数组，每个元素包含user_id, u, d）
 * @param int $node_id 节点ID
 * @return array 处理结果
 */
function orrism_handle_traffic_report($data, $node_id) {
    if (!is_array($data)) {
        return ['success' => false, 'message' => 'Invalid data format'];
    }
    
    try {
        $redis = orrism_get_redis_connection();
        if (!$redis) {
            return ['success' => false, 'message' => 'Redis connection failed'];
        }
        
        $updated = 0;
        $node_total_u = 0;
        $node_total_d = 0;
        $date = date('Ymd');
        $user_state_key = "traffic_report:users:{$date}";
        $node_state_key = "traffic_report:nodes:{$date}";
        
        foreach ($data as $user_data) {
            if (!isset($user_data['user_id'], $user_data['u'], $user_data['d'])) {
                continue;
            }
            
            $user_id = intval($user_data['user_id']);
            $upload = intval($user_data['u']);
            $download = intval($user_data['d']);
            
            // 用户流量累加到同一个ZSET
            $redis->zIncrBy($user_state_key, $upload, $user_id . ':u');
            $redis->zIncrBy($user_state_key, $download, $user_id . ':d');
            
            // 节点流量累加到同一个ZSET
            $redis->zIncrBy($node_state_key, $upload, $node_id . ':u');
            $redis->zIncrBy($node_state_key, $download, $node_id . ':d');
            
            $updated++;
            $node_total_u += $upload;
            $node_total_d += $download;
        }
        
        // 记录节点在线用户数
        $redis->set("node_online_user:{$node_id}", count($data));
        
        return [
            'success' => true,
            'updated_users' => $updated,
            'node_total_upload' => $node_total_u,
            'node_total_download' => $node_total_d,
            'online_count' => count($data)
        ];
    } catch (Exception $e) {
        error_log("处理流量上报失败: " . $e->getMessage());
        return ['success' => false, 'message' => 'Redis error'];
    }
}

/**
 * 格式化配置数据为指定结构
 * @param array $nodes 节点数据
 * @return array 格式化后的配置
 */
function orrism_format_config($nodes) {
    $config = [
        'inbounds' => []
    ];
    
    foreach ($nodes as $node) {
        $inbound = [
            'port' => (int)$node['server_port'],
            'settings' => []
        ];
        
        if (isset($node['node_method']) && !empty($node['node_method'])) {
            $inbound['settings']['method'] = $node['node_method'];
            // 动态生成 password
            switch ($node['node_method']) {
                case '2022-blake3-aes-128-gcm':
                    $ctime_ts = is_numeric($node['ctime']) ? intval($node['ctime']) : strtotime($node['ctime']);
                    $inbound['settings']['password'] = orrism_get_server_key($ctime_ts, 16);
                    break;
                case '2022-blake3-aes-256-gcm':
                    $ctime_ts = is_numeric($node['ctime']) ? intval($node['ctime']) : strtotime($node['ctime']);
                    $inbound['settings']['password'] = orrism_get_server_key('1743405847', 32);
                    break;
            }
        } else if (isset($node['method']) && !empty($node['method'])) {
            $inbound['settings']['method'] = $node['method'];
        }
        
        if (isset($node['password']) && !empty($node['password'])) {
            $inbound['settings']['password'] = $node['password'];
        }
        
        $config['inbounds'][] = $inbound;
    }
    
    return $config;
}

/**
 * 同步Redis中的user_state和server_state到MySQL
 * @return array 同步结果
 */
function orrism_sync_state_to_mysql() {
    $date = date('Ymd');
    $rtime = date('Y-m-d 00:00:00');
    $now = date('Y-m-d H:i:s'); // 东八区当前时间
    
    $redis = orrism_get_redis_connection();
    $conn = orrism_get_db_connection();
    
    $user_state_key = "traffic_report:users:{$date}";
    $node_state_key = "traffic_report:nodes:{$date}";
    
    // 兼容低版本phpredis
    $user_states = $redis->zRange($user_state_key, 0, -1, true);
    $node_states = $redis->zRange($node_state_key, 0, -1, true);
    
    $user_inserted = 0;
    $server_inserted = 0;
    
    // 处理用户流量
    $user_usage = [];
    foreach ($user_states as $member => $score) {
        $parts = explode(':', $member);
        if (count($parts) != 2) continue;
        
        $sid = intval($parts[0]);
        $type = $parts[1];
        
        if (!isset($user_usage[$sid])) {
            $user_usage[$sid] = ['upload' => 0, 'download' => 0];
        }
        
        if ($type === 'u') {
            $user_usage[$sid]['upload'] = intval($score);
        } elseif ($type === 'd') {
            $user_usage[$sid]['download'] = intval($score);
        }
    }
    
    foreach ($user_usage as $sid => $usage) {
        $stmt = $conn->prepare("INSERT INTO traffic_user_usage (sid, upload, download, rtime, ctime, mtime) 
                               VALUES (:sid, :upload, :download, :rtime, :ctime, :mtime)");
        $stmt->bindValue(':sid', $sid, PDO::PARAM_INT);
        $stmt->bindValue(':upload', $usage['upload'], PDO::PARAM_INT);
        $stmt->bindValue(':download', $usage['download'], PDO::PARAM_INT);
        $stmt->bindValue(':rtime', $rtime, PDO::PARAM_STR);
        $stmt->bindValue(':ctime', $now, PDO::PARAM_STR);
        $stmt->bindValue(':mtime', $now, PDO::PARAM_STR);
        $stmt->execute();
        $user_inserted++;
    }
    
    // 处理节点流量
    $server_usage = [];
    foreach ($node_states as $member => $score) {
        $parts = explode(':', $member);
        if (count($parts) != 2) continue;
        
        $server_id = intval($parts[0]);
        $type = $parts[1];
        
        if (!isset($server_usage[$server_id])) {
            $server_usage[$server_id] = ['upload' => 0, 'download' => 0];
        }
        
        if ($type === 'u') {
            $server_usage[$server_id]['upload'] = intval($score);
        } elseif ($type === 'd') {
            $server_usage[$server_id]['download'] = intval($score);
        }
    }
    
    foreach ($server_usage as $server_id => $usage) {
        $stmt = $conn->prepare("INSERT INTO traffic_server_usage (server_id, upload, download, rtime, ctime, mtime) 
                               VALUES (:server_id, :upload, :download, :rtime, :ctime, :mtime)");
        $stmt->bindValue(':server_id', $server_id, PDO::PARAM_INT);
        $stmt->bindValue(':upload', $usage['upload'], PDO::PARAM_INT);
        $stmt->bindValue(':download', $usage['download'], PDO::PARAM_INT);
        $stmt->bindValue(':rtime', $rtime, PDO::PARAM_STR);
        $stmt->bindValue(':ctime', $now, PDO::PARAM_STR);
        $stmt->bindValue(':mtime', $now, PDO::PARAM_STR);
        $stmt->execute();
        $server_inserted++;
    }
    
    return [
        'success' => true, 
        'user_inserted' => $user_inserted, 
        'server_inserted' => $server_inserted
    ];
}?>

