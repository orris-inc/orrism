<?php
/**
 * ORRISM - ShadowSocks Manager Module for WHMCS
 *
 * @package    WHMCS
 * @author     ORRISM Development Team
 * @copyright  Copyright (c) 2022-2024
 * @version    1.0
 */
// 节点相关业务模块
require_once __DIR__ . '/../helper.php';
require_once __DIR__ . '/../lib/database.php';

function orrism_node_get_nodes($sid) {
    $db = OrrisDatabase::getInstance();
    return $db->getNodesForUser($sid);
}

/**
 * 获取节点信息
 * @param int $node_id
 * @return array|null
 */
function orrism_get_node($node_id) {
    try {
        $redis_key = "node_data_{$node_id}";
        $cached_node = orrism_set_redis($redis_key, null, 'get', 1);
        if ($cached_node !== null && $cached_node !== false) {
            return json_decode($cached_node, true);
        }
        $conn = orrism_get_db_connection();
        $action = $conn->prepare('SELECT * FROM nodes WHERE `id` = :id');
        $action->bindValue(':id', $node_id, PDO::PARAM_INT);
        $action->execute();
        $result = $action->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            $redis = orrism_get_redis_connection(1);
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
function orrism_get_group_id_user($group_id) {
    try {
        $conn = orrism_get_db_connection();
        $action = $conn->prepare('SELECT * FROM user WHERE `node_group_id` = :node_group_id AND `enable` = 1');
        $action->bindValue(':node_group_id', $group_id);
        $action->execute();
        return $action->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error getting users by group: " . $e->getMessage());
        return [];
    }
}

// This function is now handled by OrrisDatabase::getNodesForUser()
// Kept for backward compatibility - redirects to unified database layer

function orrism_get_node_group_id($sid) {
    $node_group_id = orrism_set_redis("node_group_id_sid_{$sid}", null, 'get');
    if ($node_group_id !== null) {
        return $node_group_id;
    }
    return null;
}

function orrism_get_node_by_group_id($sid, $id) {
    $jsonData = orrism_set_redis("node_group_id_{$sid}_{$id}", null, 'get', 1);
    if ($jsonData) {
        $conn = orrism_get_db_connection();
        $action = $conn->prepare('SELECT * FROM nodes WHERE `group_id` = :group_id AND `id` = :id');
        $action->bindValue(':group_id', $id);
        $action->bindValue(':id', $id);
        $action->execute();
        $result = $action->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            orrism_set_redis("node_group_id_sid_{$sid}", $id, 'set');
            return $result;
        }
    }
    return null;
}

// 预留节点相关函数接口 