<?php
/**
 * ORRISM Manager Module for WHMCS
 *
 * @package    WHMCS
 * @author     ORRIS Development Team
 * @copyright  Copyright (c) 2022-2024
 * @version    1.0
 */
// 节点相关业务模块
require_once __DIR__ . '/../helper.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/user.php';

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
 * 获取分组下所有用户
 * @param int $group_id
 * @return array
 */
function orris_get_group_id_user($group_id) {
    try {
        $conn = orris_get_db_connection();
        $action = $conn->prepare('SELECT * FROM user WHERE `node_group_id` = :node_group_id AND `enable` = 1');
        $action->bindValue(':node_group_id', $group_id);
        $action->execute();
        return $action->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error getting users by group: " . $e->getMessage());
        return [];
    }
}

/**
 * 获取用户节点列表
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
