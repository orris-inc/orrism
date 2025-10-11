<?php
/**
 * ORRISM Server API v1 - Unified Entry Point
 * For XrayR and other node management tools
 *
 * @package    ORRISM
 * @author     ORRISM Team
 * @version    1.0.0
 * @link       https://github.com/your-org/orrism-whmcs-module
 */

// Initialize WHMCS environment
if (!defined('WHMCS')) {
    define('WHMCS', true);
    $initPath = __DIR__ . '/../../../../init.php';
    if (file_exists($initPath)) {
        require_once $initPath;
    } else {
        http_response_code(500);
        die(json_encode(['success' => false, 'error' => 'WHMCS environment not found']));
    }
}

// Load core dependencies
$coreDependencies = [
    __DIR__ . '/../../api/database.php',
    __DIR__ . '/../../helper.php',
    __DIR__ . '/../lib/Router.php',
    __DIR__ . '/../lib/Request.php',
    __DIR__ . '/../lib/Response.php',
    __DIR__ . '/../lib/Auth.php',
    __DIR__ . '/../lib/RateLimit.php'
];

foreach ($coreDependencies as $file) {
    if (file_exists($file)) {
        require_once $file;
    } else {
        http_response_code(500);
        die(json_encode(['success' => false, 'error' => 'Required file not found: ' . basename($file)]));
    }
}

use Orrism\Server\Router;
use Orrism\Server\Request;
use Orrism\Server\Response;
use Orrism\Server\Auth;
use Orrism\Server\RateLimit;

// Handle CORS preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    Response::success([], 'OK');
}

try {
    // Create request object
    $request = new Request();

    // Authentication middleware
    $auth = new Auth();
    if (!$auth->verify($request)) {
        Response::unauthorized('Authentication failed');
    }

    // Rate limiting middleware
    $rateLimit = new RateLimit();
    if (!$rateLimit->check($request)) {
        Response::tooManyRequests('Rate limit exceeded');
    }

    // Initialize router
    $router = new Router();

    // Load route definitions
    require __DIR__ . '/routes.php';

    // Dispatch request
    $router->dispatch($request);

} catch (Exception $e) {
    // Log error using WHMCS standard
    if (function_exists('logModuleCall')) {
        logModuleCall(
            'orrism_server_api',
            'error',
            [
                'method' => $_SERVER['REQUEST_METHOD'],
                'uri' => $_SERVER['REQUEST_URI'] ?? '/',
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ],
            $e->getMessage(),
            $e->getTraceAsString()
        );
    }

    // Send error response
    $code = $e->getCode() ?: 500;
    Response::error($e->getMessage(), $code);
}
