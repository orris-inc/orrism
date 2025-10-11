<?php
/**
 * ORRISM Server API Router
 * Routes incoming requests to appropriate handlers
 *
 * @package    ORRISM
 * @author     ORRISM Team
 * @version    1.0.0
 */

namespace Orrism\Server;

class Router
{
    private $routes = [];

    /**
     * Register GET route
     *
     * @param string $path Route path pattern
     * @param string $handler Handler class and method (e.g., 'NodeHandler@index')
     */
    public function get($path, $handler)
    {
        $this->addRoute('GET', $path, $handler);
    }

    /**
     * Register POST route
     *
     * @param string $path Route path pattern
     * @param string $handler Handler class and method
     */
    public function post($path, $handler)
    {
        $this->addRoute('POST', $path, $handler);
    }

    /**
     * Register PUT route
     *
     * @param string $path Route path pattern
     * @param string $handler Handler class and method
     */
    public function put($path, $handler)
    {
        $this->addRoute('PUT', $path, $handler);
    }

    /**
     * Register DELETE route
     *
     * @param string $path Route path pattern
     * @param string $handler Handler class and method
     */
    public function delete($path, $handler)
    {
        $this->addRoute('DELETE', $path, $handler);
    }

    /**
     * Add route to routing table
     *
     * @param string $method HTTP method
     * @param string $path Route path
     * @param string $handler Handler
     */
    private function addRoute($method, $path, $handler)
    {
        $this->routes[$method][$path] = $handler;
    }

    /**
     * Dispatch request to appropriate handler
     *
     * @param Request $request Request object
     * @throws \Exception If route not found
     */
    public function dispatch(Request $request)
    {
        $method = $request->getMethod();
        $path = $request->getPath();

        // Find matching route
        foreach ($this->routes[$method] ?? [] as $pattern => $handler) {
            $params = [];
            if ($this->matchRoute($pattern, $path, $params)) {
                $result = $this->callHandler($handler, $params, $request);
                Response::success($result);
                return;
            }
        }

        throw new \Exception('Route not found', 404);
    }

    /**
     * Match route pattern against request path
     *
     * @param string $pattern Route pattern
     * @param string $path Request path
     * @param array $params Output parameter for matched params
     * @return bool True if matched
     */
    private function matchRoute($pattern, $path, &$params)
    {
        // Convert {param} to named regex capture groups
        $regex = preg_replace('/\{([^}]+)\}/', '(?P<$1>[^/]+)', $pattern);
        $regex = '#^' . $regex . '$#';

        if (preg_match($regex, $path, $matches)) {
            // Extract named parameters
            foreach ($matches as $key => $value) {
                if (is_string($key)) {
                    $params[$key] = $value;
                }
            }
            return true;
        }

        return false;
    }

    /**
     * Call handler method with parameters
     *
     * @param string $handler Handler string (Class@method)
     * @param array $params Route parameters
     * @param Request $request Request object
     * @return mixed Handler result
     * @throws \Exception If handler not found
     */
    private function callHandler($handler, $params, $request)
    {
        list($class, $method) = explode('@', $handler);

        $handlerClass = "\\Orrism\\Server\\Handlers\\{$class}";
        $handlerFile = __DIR__ . "/../v1/handlers/{$class}.php";

        if (!file_exists($handlerFile)) {
            throw new \Exception("Handler file not found: {$class}", 500);
        }

        require_once $handlerFile;

        if (!class_exists($handlerClass)) {
            throw new \Exception("Handler class not found: {$handlerClass}", 500);
        }

        $instance = new $handlerClass($request);

        if (!method_exists($instance, $method)) {
            throw new \Exception("Handler method not found: {$method}", 500);
        }

        return call_user_func_array([$instance, $method], $params);
    }
}
