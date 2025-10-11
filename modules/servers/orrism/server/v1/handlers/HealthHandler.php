<?php
/**
 * ORRISM Server API - Health Check Handler
 * Provides API health status information
 *
 * @package    ORRISM
 * @author     ORRISM Team
 * @version    1.0.0
 */

namespace Orrism\Server\Handlers;

use Orrism\Server\Request;

class HealthHandler
{
    private $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * API health check
     * GET /server/v1/health
     *
     * @return array Health status information
     */
    public function check()
    {
        $health = [
            'status' => 'healthy',
            'version' => '1.0.0',
            'timestamp' => time()
        ];

        // Check database connection
        try {
            require_once __DIR__ . '/../../../api/database.php';
            $conn = orris_get_db_connection();
            $stmt = $conn->query("SELECT 1");
            $health['database'] = 'connected';
        } catch (\Exception $e) {
            $health['database'] = 'error';
            $health['status'] = 'degraded';
        }

        // Check Redis connection
        try {
            $redis = orris_get_redis_connection(0);
            $redis->ping();
            $health['redis'] = 'connected';
        } catch (\Exception $e) {
            $health['redis'] = 'error';
            $health['status'] = 'degraded';
        }

        return $health;
    }
}
