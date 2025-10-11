<?php
/**
 * ORRISM Server API - Node Handler
 * Handles node-related API requests
 *
 * @package    ORRISM
 * @author     ORRISM Team
 * @version    1.0.0
 */

namespace Orrism\Server\Handlers;

use Orrism\Server\Request;

class NodeHandler
{
    private $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * Get node list
     * GET /server/v1/nodes
     *
     * @return array Node list with count
     */
    public function index()
    {
        require_once __DIR__ . '/../../../api/database.php';

        $conn = orris_get_db_connection();
        $stmt = $conn->query("
            SELECT id, name, server, port, type, method,
                   node_group, traffic_rate, status, enable,
                   sort, info, online_user, max_user
            FROM nodes
            WHERE enable = 1
            ORDER BY sort ASC, id ASC
        ");

        $nodes = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return [
            'count' => count($nodes),
            'nodes' => $nodes
        ];
    }

    /**
     * Get single node information
     * GET /server/v1/nodes/{id}
     *
     * @param string $id Node ID
     * @return array Node information
     * @throws \Exception If node not found
     */
    public function show($id)
    {
        require_once __DIR__ . '/../../../api/node.php';
        require_once __DIR__ . '/../../../api/database.php';

        // Try cache first
        $cacheKey = "server_api_node_{$id}";
        $redis = orris_get_redis_connection(1);
        $cached = $redis->get($cacheKey);

        if ($cached !== false) {
            return json_decode($cached, true);
        }

        // Query database
        $conn = orris_get_db_connection();
        $stmt = $conn->prepare("
            SELECT id, name, server, port, type, method,
                   node_group, traffic_rate, status, enable,
                   sort, info, online_user, max_user, updated_at
            FROM nodes
            WHERE id = :id AND enable = 1
        ");
        $stmt->bindValue(':id', $id, \PDO::PARAM_INT);
        $stmt->execute();

        $node = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$node) {
            throw new \Exception('Node not found or disabled', 404);
        }

        // Cache for 5 minutes
        $redis->setex($cacheKey, 300, json_encode($node));

        return $node;
    }

    /**
     * Node heartbeat - Update status
     * POST /server/v1/nodes/{id}/heartbeat
     *
     * @param string $id Node ID
     * @return array Update result
     * @throws \Exception If update fails
     */
    public function heartbeat($id)
    {
        require_once __DIR__ . '/../../../api/database.php';

        $onlineUser = $this->request->input('online_user');
        $load = $this->request->input('load');

        if ($onlineUser === null) {
            throw new \Exception('Parameter online_user is required', 400);
        }

        $conn = orris_get_db_connection();

        // Build update query
        $updates = ['updated_at = UNIX_TIMESTAMP()'];
        $params = [];

        if ($onlineUser !== null) {
            $updates[] = 'online_user = :online_user';
            $params[':online_user'] = (int)$onlineUser;
        }

        if ($load !== null) {
            $updates[] = 'load = :load';
            $params[':load'] = (float)$load;
        }

        $sql = "UPDATE nodes SET " . implode(', ', $updates) . " WHERE id = :id AND enable = 1";
        $params[':id'] = (int)$id;

        $stmt = $conn->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        if (!$stmt->execute()) {
            throw new \Exception('Failed to update node status', 500);
        }

        // Clear cache
        $redis = orris_get_redis_connection(1);
        $redis->del("server_api_node_{$id}");

        // Log heartbeat
        if (class_exists('OrrisHelper')) {
            \OrrisHelper::log('info', 'Node heartbeat received', [
                'node_id' => $id,
                'online_user' => $onlineUser,
                'load' => $load
            ]);
        }

        return [
            'node_id' => (int)$id,
            'updated_at' => time()
        ];
    }

    /**
     * Get users for a specific node
     * GET /server/v1/nodes/{id}/users
     *
     * @param string $id Node ID
     * @return array User list with metadata
     * @throws \Exception If node not found
     */
    public function users($id)
    {
        require_once __DIR__ . '/../../../api/service.php';
        require_once __DIR__ . '/../../../api/database.php';

        $timestamp = (int)$this->request->query('timestamp', 0);

        // Get node information
        $conn = orris_get_db_connection();
        $stmt = $conn->prepare("SELECT node_group, enable FROM nodes WHERE id = :id");
        $stmt->bindValue(':id', $id, \PDO::PARAM_INT);
        $stmt->execute();
        $node = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$node) {
            throw new \Exception('Node not found', 404);
        }

        if ($node['enable'] != 1) {
            throw new \Exception('Node is disabled', 403);
        }

        // Get users by node group
        $sql = "
            SELECT id, sid, uuid, email, enable, bandwidth,
                   u, d, node_group_id, updated_at, created_at
            FROM user
            WHERE node_group_id = :node_group AND enable = 1
        ";

        $params = [':node_group' => $node['node_group']];

        // Support incremental sync
        if ($timestamp > 0) {
            $sql .= " AND updated_at > :timestamp";
            $params[':timestamp'] = $timestamp;
        }

        $sql .= " ORDER BY id ASC";

        $stmt = $conn->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();

        $users = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return [
            'node_id' => (int)$id,
            'node_group' => (int)$node['node_group'],
            'count' => count($users),
            'timestamp' => time(),
            'incremental' => $timestamp > 0,
            'users' => $users
        ];
    }
}
