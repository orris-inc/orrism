<?php
/**
 * ORRISM Server API Response
 * Standardized JSON response handler
 *
 * @package    ORRISM
 * @author     ORRISM Team
 * @version    1.0.0
 */

namespace Orrism\Server;

class Response
{
    /**
     * Send success response
     *
     * @param mixed $data Response data
     * @param string $message Success message
     * @param int $code HTTP status code
     */
    public static function success($data = [], $message = 'Success', $code = 200)
    {
        self::json([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'timestamp' => time()
        ], $code);
    }

    /**
     * Send error response
     *
     * @param string $message Error message
     * @param int $code HTTP status code
     * @param array $details Additional error details
     */
    public static function error($message, $code = 400, $details = [])
    {
        self::json([
            'success' => false,
            'error' => $message,
            'details' => $details,
            'timestamp' => time()
        ], $code);
    }

    /**
     * Send unauthorized response (401)
     *
     * @param string $message Error message
     */
    public static function unauthorized($message = 'Unauthorized')
    {
        self::error($message, 401);
    }

    /**
     * Send forbidden response (403)
     *
     * @param string $message Error message
     */
    public static function forbidden($message = 'Forbidden')
    {
        self::error($message, 403);
    }

    /**
     * Send not found response (404)
     *
     * @param string $message Error message
     */
    public static function notFound($message = 'Resource not found')
    {
        self::error($message, 404);
    }

    /**
     * Send too many requests response (429)
     *
     * @param string $message Error message
     */
    public static function tooManyRequests($message = 'Too many requests')
    {
        self::error($message, 429);
    }

    /**
     * Send JSON response and exit
     *
     * @param array $data Response data
     * @param int $code HTTP status code
     */
    private static function json($data, $code = 200)
    {
        // Log API call using WHMCS standard
        self::logApiCall($data, $code);

        // Set HTTP status code
        http_response_code($code);

        // Set response headers
        header('Content-Type: application/json; charset=utf-8');
        header('X-ORRISM-API-Version: 1.0');

        // Send JSON response
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        exit;
    }

    /**
     * Log API call using WHMCS standard logging
     *
     * @param array $response Response data
     * @param int $statusCode HTTP status code
     */
    private static function logApiCall($response, $statusCode)
    {
        $request = [
            'method' => $_SERVER['REQUEST_METHOD'],
            'uri' => $_SERVER['REQUEST_URI'] ?? '/',
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'body' => file_get_contents('php://input')
        ];

        // Use WHMCS logModuleCall if available
        if (function_exists('logModuleCall')) {
            logModuleCall(
                'orrism_server_api',
                $_SERVER['REQUEST_METHOD'] . ' ' . ($_SERVER['REQUEST_URI'] ?? '/'),
                $request,
                $response,
                json_encode($response),
                ['api_key', 'password', 'token', 'secret'] // Sensitive fields to hide
            );
        }

        // Additional custom logging using OrrisHelper
        if (class_exists('OrrisHelper')) {
            \OrrisHelper::log(
                $statusCode >= 400 ? 'error' : 'info',
                'Server API Request',
                [
                    'method' => $_SERVER['REQUEST_METHOD'],
                    'path' => $_SERVER['REQUEST_URI'] ?? '/',
                    'status' => $statusCode,
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                ]
            );
        }
    }
}
