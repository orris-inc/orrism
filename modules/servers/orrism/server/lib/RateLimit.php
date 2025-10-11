<?php
/**
 * ORRISM Server API Rate Limiting Middleware
 * Prevents API abuse by limiting request frequency
 *
 * @package    ORRISM
 * @author     ORRISM Team
 * @version    1.0.0
 */

namespace Orrism\Server;

class RateLimit
{
    /**
     * Check if request passes rate limit
     *
     * @param Request $request Request object
     * @return bool True if within limit
     */
    public function check(Request $request)
    {
        $limit = $this->getLimit();
        $ip = $request->getClientIp();

        // If limit is 0, disable rate limiting
        if ($limit <= 0) {
            return true;
        }

        try {
            require_once __DIR__ . '/../../api/database.php';
            $redis = orris_get_redis_connection(0);

            $key = "server_api_rate_limit:{$ip}";
            $requests = $redis->incr($key);

            // Set expiration on first request
            if ($requests === 1) {
                $redis->expire($key, 60); // 1 minute window
            }

            // Check if limit exceeded
            if ($requests > $limit) {
                $this->logExceeded($ip, $requests, $limit);
                return false;
            }

            return true;

        } catch (\Exception $e) {
            // If Redis fails, allow the request (fail open)
            error_log("ORRISM Rate Limit Error: " . $e->getMessage());
            return true;
        }
    }

    /**
     * Get rate limit from configuration
     *
     * @return int Requests per minute
     */
    private function getLimit()
    {
        $limit = (int)getAddonModuleSetting('orrism_admin', 'server_api_rate_limit');
        return $limit > 0 ? $limit : 60; // Default: 60 requests per minute
    }

    /**
     * Log rate limit exceeded event
     *
     * @param string $ip Client IP
     * @param int $requests Current request count
     * @param int $limit Configured limit
     */
    private function logExceeded($ip, $requests, $limit)
    {
        if (class_exists('OrrisHelper')) {
            \OrrisHelper::log('warning', 'Server API rate limit exceeded', [
                'ip' => $ip,
                'requests' => $requests,
                'limit' => $limit
            ]);
        }
    }
}
