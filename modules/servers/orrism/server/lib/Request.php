<?php
/**
 * ORRISM Server API Request
 * HTTP Request wrapper and parser
 *
 * @package    ORRISM
 * @author     ORRISM Team
 * @version    1.0.0
 */

namespace Orrism\Server;

class Request
{
    private $method;
    private $path;
    private $query;
    private $body;
    private $headers;

    public function __construct()
    {
        $this->method = $_SERVER['REQUEST_METHOD'];
        $this->path = $this->parsePath();
        $this->query = $_GET;
        $this->body = $this->parseBody();
        $this->headers = $this->parseHeaders();
    }

    /**
     * Get HTTP method
     *
     * @return string HTTP method (GET, POST, PUT, DELETE)
     */
    public function getMethod()
    {
        return $this->method;
    }

    /**
     * Get request path (without query string)
     *
     * @return string Request path
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * Get query parameter
     *
     * @param string $key Parameter key
     * @param mixed $default Default value if not found
     * @return mixed Parameter value or default
     */
    public function query($key, $default = null)
    {
        return $this->query[$key] ?? $default;
    }

    /**
     * Get body parameter
     *
     * @param string $key Parameter key
     * @param mixed $default Default value if not found
     * @return mixed Parameter value or default
     */
    public function input($key, $default = null)
    {
        return $this->body[$key] ?? $default;
    }

    /**
     * Get all body data
     *
     * @return array All body parameters
     */
    public function all()
    {
        return $this->body;
    }

    /**
     * Get header value
     *
     * @param string $key Header key
     * @param mixed $default Default value if not found
     * @return mixed Header value or default
     */
    public function header($key, $default = null)
    {
        $key = strtolower(str_replace('_', '-', $key));
        return $this->headers[$key] ?? $default;
    }

    /**
     * Get API key from request
     *
     * @return string|null API key or null
     */
    public function getApiKey()
    {
        // Try X-API-Key header
        if ($apiKey = $this->header('x-api-key')) {
            return $apiKey;
        }

        // Try Authorization header
        if ($auth = $this->header('authorization')) {
            if (preg_match('/Bearer\s+(.*)$/i', $auth, $matches)) {
                return $matches[1];
            }
        }

        // Try query parameter (fallback)
        return $this->query('api_key');
    }

    /**
     * Get client IP address
     *
     * @return string Client IP address
     */
    public function getClientIp()
    {
        $headers = [
            'HTTP_CF_CONNECTING_IP',  // Cloudflare
            'HTTP_X_FORWARDED_FOR',   // Proxy
            'HTTP_X_REAL_IP',         // Nginx
            'REMOTE_ADDR'             // Direct connection
        ];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                // Handle comma-separated IPs (proxy chain)
                if (strpos($ip, ',') !== false) {
                    $ip = explode(',', $ip)[0];
                }
                return trim($ip);
            }
        }

        return '0.0.0.0';
    }

    /**
     * Parse request path from URL
     *
     * @return string Normalized path
     */
    private function parsePath()
    {
        $uri = $_SERVER['REQUEST_URI'];

        // Remove query string
        if (($pos = strpos($uri, '?')) !== false) {
            $uri = substr($uri, 0, $pos);
        }

        // Remove base path
        $basePath = '/modules/servers/orrism/server/v1';
        if (strpos($uri, $basePath) === 0) {
            $uri = substr($uri, strlen($basePath));
        }

        // Normalize path
        return '/' . trim($uri, '/');
    }

    /**
     * Parse request body
     *
     * @return array Parsed body data
     */
    private function parseBody()
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

        // Handle JSON content
        if (strpos($contentType, 'application/json') !== false) {
            $json = file_get_contents('php://input');
            $data = json_decode($json, true);
            return is_array($data) ? $data : [];
        }

        // Handle form data
        return $_POST;
    }

    /**
     * Parse request headers
     *
     * @return array Normalized headers
     */
    private function parseHeaders()
    {
        $headers = [];

        foreach ($_SERVER as $key => $value) {
            if (strpos($key, 'HTTP_') === 0) {
                $header = strtolower(str_replace('_', '-', substr($key, 5)));
                $headers[$header] = $value;
            }
        }

        return $headers;
    }
}
