<?php
/**
 * ORRISM Server API - User Handler
 * Handles user-related API requests
 *
 * @package    ORRISM
 * @author     ORRISM Team
 * @version    1.0.0
 */

namespace Orrism\Server\Handlers;

use Orrism\Server\Request;

class UserHandler
{
    private $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * Get users by node group
     * GET /server/v1/groups/{id}/users
     *
     * @param string $id Node group ID
     * @return array User list with metadata
     */
    public function byGroup($id)
    {
        require_once __DIR__ . '/../../../api/database.php';

        $timestamp = (int)$this->request->query('timestamp', 0);

        $conn = orris_get_db_connection();

        $sql = "
            SELECT id, sid, uuid, email, enable, bandwidth,
                   u, d, node_group_id, updated_at, created_at
            FROM user
            WHERE node_group_id = :node_group AND enable = 1
        ";

        $params = [':node_group' => (int)$id];

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
            'node_group' => (int)$id,
            'count' => count($users),
            'timestamp' => time(),
            'incremental' => $timestamp > 0,
            'users' => $users
        ];
    }

    /**
     * Get single user information
     * GET /server/v1/users/{sid}
     *
     * @param string $sid Service ID
     * @return array User information
     * @throws \Exception If user not found
     */
    public function show($sid)
    {
        require_once __DIR__ . '/../../../api/service.php';

        $user = orris_get_user($sid);

        if (empty($user)) {
            throw new \Exception('User not found', 404);
        }

        return $user[0];
    }

    /**
     * Get user traffic statistics
     * GET /server/v1/users/{sid}/traffic
     *
     * @param string $sid Service ID
     * @return array Traffic statistics
     * @throws \Exception If user not found
     */
    public function traffic($sid)
    {
        require_once __DIR__ . '/../../../api/service.php';

        $user = orris_get_user($sid);

        if (empty($user)) {
            throw new \Exception('User not found', 404);
        }

        $userData = $user[0];
        $totalUsed = $userData['u'] + $userData['d'];
        $remaining = $userData['bandwidth'] - $totalUsed;

        return [
            'sid' => (int)$sid,
            'uuid' => $userData['uuid'],
            'bandwidth_limit' => (int)$userData['bandwidth'],
            'upload' => (int)$userData['u'],
            'download' => (int)$userData['d'],
            'total_used' => $totalUsed,
            'remaining' => max(0, $remaining),
            'usage_percent' => $userData['bandwidth'] > 0
                ? round(($totalUsed / $userData['bandwidth']) * 100, 2)
                : 0,
            'enable' => (int)$userData['enable'],
            'updated_at' => (int)$userData['updated_at']
        ];
    }
}
