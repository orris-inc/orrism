<?php
/**
 * ORRISM Server API Authentication Middleware
 * Handles API key validation and IP whitelist checking
 *
 * @package    ORRISM
 * @author     ORRISM Team
 * @version    1.0.0
 */

namespace Orrism\Server;

class Auth
{
    private $apiKey;
    private $clientIp;
    private $nodeId;

    /**
     * Verify request authentication
     *
     * @param Request $request Request object
     * @return bool True if authenticated
     */
    public function verify(Request $request)
    {
        $this->apiKey = $request->getApiKey();
        $this->clientIp = $request->getClientIp();

        // Check if API is enabled
        if (!$this->isEnabled()) {
            $this->logFailure('API disabled');
            return false;
        }

        // Check if API key is provided
        if (empty($this->apiKey)) {
            $this->logFailure('No API key provided');
            return false;
        }

        // Validate API key
        if (!$this->validateKey()) {
            $this->logFailure('Invalid API key');
            return false;
        }

        // Check IP whitelist
        if (!$this->checkIp()) {
            $this->logFailure('IP not whitelisted');
            return false;
        }

        $this->logSuccess();
        return true;
    }

    /**
     * Check if Server API is enabled
     *
     * @return bool True if enabled
     */
    private function isEnabled()
    {
        $enabled = getAddonModuleSetting('orrism_admin', 'server_api_enabled');
        return $enabled === 'on' || $enabled === '1';
    }

    /**
     * Validate API key against configured keys
     *
     * @return bool True if valid
     */
    private function validateKey()
    {
        // Check global API key
        $globalKey = getAddonModuleSetting('orrism_admin', 'server_api_key');
        if (!empty($globalKey) && $this->apiKey === $globalKey) {
            $this->nodeId = 0; // Global key
            return true;
        }

        // Check node-specific API key
        try {
            require_once __DIR__ . '/../../api/database.php';
            $conn = orris_get_db_connection();

            $stmt = $conn->prepare("SELECT id FROM nodes WHERE api_key = :api_key AND enable = 1");
            $stmt->bindValue(':api_key', $this->apiKey);
            $stmt->execute();

            $node = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($node) {
                $this->nodeId = $node['id'];
                return true;
            }

        } catch (\Exception $e) {
            error_log("ORRISM Auth Error: " . $e->getMessage());
        }

        return false;
    }

    /**
     * Check if client IP is whitelisted
     *
     * @return bool True if whitelisted or whitelist is empty
     */
    private function checkIp()
    {
        $whitelist = getAddonModuleSetting('orrism_admin', 'server_api_ip_whitelist');

        // If whitelist is empty, allow all IPs
        if (empty($whitelist)) {
            return true;
        }

        $allowedIps = array_map('trim', explode("\n", $whitelist));

        // Check exact match or CIDR range
        foreach ($allowedIps as $allowedIp) {
            if (empty($allowedIp)) {
                continue;
            }

            if ($this->ipMatch($this->clientIp, $allowedIp)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Match IP address against pattern (supports CIDR notation)
     *
     * @param string $ip IP address to check
     * @param string $pattern IP pattern (e.g., 192.168.1.1 or 192.168.1.0/24)
     * @return bool True if matched
     */
    private function ipMatch($ip, $pattern)
    {
        // Exact match
        if ($ip === $pattern) {
            return true;
        }

        // CIDR match
        if (strpos($pattern, '/') !== false) {
            list($subnet, $bits) = explode('/', $pattern);
            $ipLong = ip2long($ip);
            $subnetLong = ip2long($subnet);
            $mask = -1 << (32 - $bits);
            $subnetLong &= $mask;
            return ($ipLong & $mask) === $subnetLong;
        }

        return false;
    }

    /**
     * Get authenticated node ID
     *
     * @return int|null Node ID or null for global key
     */
    public function getNodeId()
    {
        return $this->nodeId;
    }

    /**
     * Log successful authentication
     */
    private function logSuccess()
    {
        if (class_exists('OrrisHelper')) {
            \OrrisHelper::log('info', 'Server API authentication successful', [
                'ip' => $this->clientIp,
                'node_id' => $this->nodeId,
                'key' => substr($this->apiKey, 0, 8) . '...'
            ]);
        }
    }

    /**
     * Log authentication failure
     *
     * @param string $reason Failure reason
     */
    private function logFailure($reason)
    {
        if (class_exists('OrrisHelper')) {
            \OrrisHelper::log('warning', 'Server API authentication failed', [
                'reason' => $reason,
                'ip' => $this->clientIp,
                'key' => !empty($this->apiKey) ? substr($this->apiKey, 0, 8) . '...' : 'none'
            ]);
        }
    }
}
