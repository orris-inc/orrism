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

use WHMCS\Database\Capsule;

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

// Note: orris_get_nodes() is defined in service.php which is already included above

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
        // Get action from request
        $action = $_GET['action'] ?? $_POST['action'] ?? 'list';

        // Route to appropriate function
        switch ($action) {
            case 'list':
            case 'get_list':
                // Get node list - always use PDO connection to addon database
                $conn = orris_get_db_connection();

                // Get database name for debugging
                $dbName = $conn->query("SELECT DATABASE()")->fetchColumn();

                $sql = "SELECT id, name, type, address, port, method, status,
                        group_id, sort_order
                        FROM nodes ORDER BY sort_order";
                $stmt = $conn->prepare($sql);
                $stmt->execute();
                $nodes = $stmt->fetchAll(PDO::FETCH_ASSOC);

                echo json_encode([
                    'success' => true,
                    'count' => count($nodes),
                    'database' => $dbName,  // Debug info
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

                if (class_exists('OrrisDB') && OrrisDB::isConfigured()) {
                    $node = OrrisDB::table('nodes')
                        ->where('id', $id)
                        ->first();
                } else {
                    $conn = orris_get_db_connection();
                    $sql = "SELECT * FROM nodes WHERE id = :id";
                    $stmt = $conn->prepare($sql);
                    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
                    $stmt->execute();
                    $node = $stmt->fetch(PDO::FETCH_ASSOC);
                }

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
                if (class_exists('OrrisDB') && OrrisDB::isConfigured()) {
                    $totalNodes = OrrisDB::table('nodes')->count();
                    $activeNodes = OrrisDB::table('nodes')->where('status', 'active')->count();
                } else {
                    $conn = orris_get_db_connection();

                    $stmt = $conn->query("SELECT COUNT(*) as total FROM nodes");
                    $totalNodes = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

                    $stmt = $conn->query("SELECT COUNT(*) as active FROM nodes WHERE status = 'active'");
                    $activeNodes = $stmt->fetch(PDO::FETCH_ASSOC)['active'];
                }

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
