<?php
/**
 * ORRISM Manager Module for WHMCS
 *
 * @package    WHMCS
 * @author     ORRIS Development Team
 * @copyright  Copyright (c) 2022-2024
 * @version    1.0
 */

// Initialize WHMCS environment for direct API access
if (!defined('WHMCS')) {
    define('WHMCS', true);
    $init_path = __DIR__ . '/../../../../init.php';
    if (file_exists($init_path)) {
        require_once $init_path;
    }
}

// Node management business module
require_once __DIR__ . '/../helper.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/service.php';

function orris_node_get_nodes($sid) {
    // 迁移 get_nodes 逻辑
    return orris_get_nodes($sid);
}

/**
 * 获取节点信息
 * @param int $node_id
 * @return array|null
 */
function orris_get_node($node_id) {
    try {
        $redis_key = "node_data_{$node_id}";
        $cached_node = orris_set_redis($redis_key, null, 'get', 1);
        if ($cached_node !== null && $cached_node !== false) {
            return json_decode($cached_node, true);
        }
        $conn = orris_get_db_connection();
        $action = $conn->prepare('SELECT * FROM nodes WHERE `id` = :id');
        $action->bindValue(':id', $node_id, PDO::PARAM_INT);
        $action->execute();
        $result = $action->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            $redis = orris_get_redis_connection(1);
            $redis->set($redis_key, json_encode($result), 3600);
        }
        return $result;
    } catch (Exception $e) {
        error_log("Error fetching node: " . $e->getMessage());
        return null;
    }
}

/**
 * 获取分组下所有服务
 * @param int $group_id
 * @return array
 */
function orris_get_group_id_services($group_id) {
    try {
        $conn = orris_get_db_connection();
        $action = $conn->prepare('SELECT * FROM services WHERE `node_group_id` = :node_group_id AND `status` = "active"');
        $action->bindValue(':node_group_id', $group_id);
        $action->execute();
        return $action->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error getting services by group: " . $e->getMessage());
        return [];
    }
}

/**
 * 获取服务节点列表
 * @param int $sid
 * @return array
 */
function orris_get_nodes($sid) {
    $redis_key = 'node_key_' . $sid;
    $cached_node = orris_set_redis($redis_key, null, 'get', 1);
    if ($cached_node) {
        return json_decode($cached_node, true);
    }
    $conn = orris_get_db_connection();
    $node_group_id = orris_set_redis("node_group_id_sid_{$sid}", null, 'get');
    if ($node_group_id !== null) {
        $node_group_ids = explode(",", $node_group_id);
        $node_data = [];
        foreach ($node_group_ids as $id) {
            $jsonData = orris_set_redis("node_group_id_{$sid}_{$id}", null, 'get', 1);
            if ($jsonData) {
                $node_data = array_merge($node_data, json_decode($jsonData, true));
            }
        }
    } else {
        if (!empty(orris_get_user($sid))) {
            $conn = orris_get_db_connection();
            $action = $conn->prepare('SELECT node_group_id FROM user WHERE `sid` = :sid');
            $action->bindValue(':sid', $sid);
            $action->execute();
            $id = $action->fetch(PDO::FETCH_ASSOC)['node_group_id'] ?? '';
            orris_set_redis("node_group_id_sid_{$sid}", $id, 'set');
            
            // Handle empty node group id case
            if (empty($id)) {
                return [];
            }
            
            $node_group_ids = explode(",", $id);
            $placeholders = implode(',', array_fill(0, count($node_group_ids), '?'));
            $sql = "SELECT * FROM nodes WHERE `group_id` IN ($placeholders) AND enable = 1 ORDER BY rate";
            
            $action = $conn->prepare($sql);
            
            // Bind all parameters using bindValue with specific positions
            foreach ($node_group_ids as $index => $group_id) {
                $action->bindValue($index + 1, (int)$group_id, PDO::PARAM_INT);
            }
            
            $action->execute();
            $node_data = $action->fetchAll(PDO::FETCH_ASSOC);
            
            // Initialize the array before use
            $allNodes = [];
            foreach ($node_data as $node) {
                $allNodes[$node['group_id']][] = $node;
            }
            
            // Cache node data by group
            foreach ($allNodes as $group_id => $nodes) {
                // Set longer expiration for node data
                $redis = orris_get_redis_connection(1);
                $redis->set("node_group_id_{$sid}_{$group_id}", json_encode($nodes), 3600); // Cache for 1 hour
            }
        } else {
            return ORRIS_L::error_account_not_found;
        }
    }
    return $node_data ?? [];
}

function orris_get_node_group_id($sid) {
    $node_group_id = orris_set_redis("node_group_id_sid_{$sid}", null, 'get');
    if ($node_group_id !== null) {
        return $node_group_id;
    }
    return null;
}

function orris_get_node_by_group_id($sid, $id) {
    $jsonData = orris_set_redis("node_group_id_{$sid}_{$id}", null, 'get', 1);
    if ($jsonData) {
        $conn = orris_get_db_connection();
        $action = $conn->prepare('SELECT * FROM nodes WHERE `group_id` = :group_id AND `id` = :id');
        $action->bindValue(':group_id', $id);
        $action->bindValue(':id', $id);
        $action->execute();
        $result = $action->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            orris_set_redis("node_group_id_sid_{$sid}", $id, 'set');
            return $result;
        }
    }
    return null;
}

// 预留节点相关函数接口

// ============================================
// API Endpoint Handler
// ============================================

/**
 * Handle direct API requests
 */
if (php_sapi_name() !== 'cli' && !defined('ORRISM_API_INCLUDED')) {
    // Set JSON response header
    header('Content-Type: application/json; charset=utf-8');

    // Enable CORS
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-API-Key');

    // Handle OPTIONS request
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }

    try {
        use WHMCS\Database\Capsule;

        // Get action from request
        $action = $_GET['action'] ?? $_POST['action'] ?? 'list';

        // Route to appropriate function
        switch ($action) {
            case 'list':
            case 'get_list':
                // Get node list
                $nodes = Capsule::table('nodes')
                    ->select('id', 'name', 'type', 'address', 'port', 'method', 'status',
                             'group_id', 'sort_order', 'capacity', 'current_load')
                    ->orderBy('sort_order')
                    ->get();

                echo json_encode([
                    'success' => true,
                    'count' => count($nodes),
                    'data' => $nodes
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                break;

            case 'get':
            case 'info':
                // Get single node
                $id = $_GET['id'] ?? $_POST['id'] ?? null;
                if (!$id) {
                    throw new Exception('Node ID is required');
                }

                $node = Capsule::table('nodes')
                    ->where('id', $id)
                    ->first();

                if (!$node) {
                    throw new Exception('Node not found');
                }

                echo json_encode([
                    'success' => true,
                    'data' => $node
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                break;

            case 'stats':
                // Get node statistics
                $totalNodes = Capsule::table('nodes')->count();
                $activeNodes = Capsule::table('nodes')->where('status', 'active')->count();

                echo json_encode([
                    'success' => true,
                    'data' => [
                        'total_nodes' => $totalNodes,
                        'active_nodes' => $activeNodes,
                        'inactive_nodes' => $totalNodes - $activeNodes
                    ]
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                break;

            default:
                throw new Exception('Unknown action: ' . $action);
        }

    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    exit;
} 
