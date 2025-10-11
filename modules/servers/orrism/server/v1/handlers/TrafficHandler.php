<?php
/**
 * ORRISM Server API - Traffic Handler
 * Handles traffic reporting and management
 *
 * @package    ORRISM
 * @author     ORRISM Team
 * @version    1.0.0
 */

namespace Orrism\Server\Handlers;

use Orrism\Server\Request;

class TrafficHandler
{
    private $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * Batch report traffic from node
     * POST /server/v1/traffic/report
     *
     * Expected body format:
     * {
     *   "node_id": 1,
     *   "data": [
     *     {"user_id": 1001, "u": 104857600, "d": 524288000},
     *     {"user_id": 1002, "u": 52428800, "d": 262144000}
     *   ]
     * }
     *
     * @return array Report result with statistics
     * @throws \Exception If parameters invalid
     */
    public function report()
    {
        require_once __DIR__ . '/../../../api/traffic.php';

        $nodeId = $this->request->input('node_id');
        $data = $this->request->input('data');

        // Validate parameters
        if (!$nodeId) {
            throw new \Exception('Parameter node_id is required', 400);
        }

        if (!is_array($data) || empty($data)) {
            throw new \Exception('Parameter data must be a non-empty array', 400);
        }

        $processed = 0;
        $failed = 0;
        $errors = [];

        // Process each traffic record
        foreach ($data as $item) {
            try {
                $userId = $item['user_id'] ?? $item['sid'] ?? null;
                $upload = (int)($item['u'] ?? $item['upload'] ?? 0);
                $download = (int)($item['d'] ?? $item['download'] ?? 0);

                // Skip invalid records
                if (!$userId || ($upload === 0 && $download === 0)) {
                    continue;
                }

                // Use existing traffic report function
                $result = orris_report_traffic([
                    'user_id' => $userId,
                    'u' => $upload,
                    'd' => $download,
                    'node_id' => $nodeId
                ]);

                if ($result) {
                    $processed++;
                } else {
                    $failed++;
                    $errors[] = "User {$userId}: Update failed";
                }

            } catch (\Exception $e) {
                $failed++;
                $errors[] = "User {$userId}: " . $e->getMessage();
            }
        }

        // Log report summary
        if (class_exists('OrrisHelper')) {
            \OrrisHelper::log('info', 'Traffic report processed', [
                'node_id' => $nodeId,
                'processed' => $processed,
                'failed' => $failed,
                'total' => count($data)
            ]);
        }

        return [
            'node_id' => (int)$nodeId,
            'processed' => $processed,
            'failed' => $failed,
            'total' => count($data),
            'errors' => $failed > 0 ? array_slice($errors, 0, 10) : [] // Limit to 10 errors
        ];
    }

    /**
     * Reset user traffic
     * POST /server/v1/traffic/reset
     *
     * Expected body format:
     * {
     *   "sid": 123,
     *   "reason": "manual"
     * }
     *
     * @return array Reset result
     * @throws \Exception If user not found
     */
    public function reset()
    {
        require_once __DIR__ . '/../../../api/service.php';

        $sid = $this->request->input('sid');
        $reason = $this->request->input('reason', 'manual');

        if (!$sid) {
            throw new \Exception('Parameter sid is required', 400);
        }

        // Get current usage before reset
        $user = orris_get_user($sid);
        if (empty($user)) {
            throw new \Exception('User not found', 404);
        }

        $previousUsage = [
            'upload' => (int)$user[0]['u'],
            'download' => (int)$user[0]['d'],
            'total' => (int)($user[0]['u'] + $user[0]['d'])
        ];

        // Reset traffic using existing function
        orris_reset_user_traffic($sid);

        // Log reset operation
        if (class_exists('OrrisHelper')) {
            \OrrisHelper::log('info', 'Traffic reset successful', [
                'sid' => $sid,
                'reason' => $reason,
                'previous_usage' => $previousUsage
            ]);
        }

        return [
            'sid' => (int)$sid,
            'reason' => $reason,
            'previous_usage' => $previousUsage,
            'reset_at' => time()
        ];
    }
}
