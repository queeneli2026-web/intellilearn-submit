<?php
declare(strict_types=1);

namespace App;

/**
 * Regex-based front controller router.
 *
 * Maps HTTP method + URI patterns to controller class methods.
 * Routes are registered via addRoute() and dispatched via dispatch().
 *
 * Route pattern syntax:
 *   - Static segments: /admin/login
 *   - Capture groups:  /admin/topics/edit/([^/]+)
 *
 * Handler format: "ControllerClass@methodName"
 * Example: "AuthController@loginForm"
 *
 * Dispatched as: App\Controllers\{ControllerClass}::{methodName}Action($params...)
 */
class Router
{
    /** @var array<int, array{method: string, pattern: string, handler: string}> */
    private array $routes = [];

    /**
     * Register a route.
     *
     * @param string $method  HTTP method (GET, POST, etc.)
     * @param string $pattern URL pattern with optional ([^/]+) capture groups
     * @param string $handler ControllerClass@methodName format
     */
    public function addRoute(string $method, string $pattern, string $handler): void
    {
        $this->routes[] = [
            'method'  => strtoupper($method),
            'pattern' => $pattern,
            'handler' => $handler,
        ];
    }

    /**
     * Dispatch the current request to the matching controller action.
     *
     * @param string $method HTTP method
     * @param string $uri    Request URI
     */
    public function dispatch(string $method, string $uri): void
    {
        // Normalise URI: strip query string, remove trailing slash (except root), ensure leading slash
        $uri = parse_url($uri, PHP_URL_PATH);
        $uri = rtrim($uri, '/');
        if ($uri === '') {
            $uri = '/';
        }

        $method = strtoupper($method);

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            $regex = '#^' . $route['pattern'] . '$#';
            if (preg_match($regex, $uri, $matches)) {
                // Extract controller class and method
                $parts = explode('@', $route['handler']);
                $controllerClass = 'App\\Controllers\\' . $parts[0];
                $methodName = $parts[1] . 'Action';

                // Remove full match, keep capture groups as params
                array_shift($matches);

                // Instantiate controller and call method
                if (!class_exists($controllerClass)) {
                    http_response_code(500);
                    echo '500 Internal Server Error: Controller not found';
                    return;
                }

                $controller = new $controllerClass();
                if (!method_exists($controller, $methodName)) {
                    http_response_code(500);
                    echo '500 Internal Server Error: Action not found';
                    return;
                }

                call_user_func_array([$controller, $methodName], $matches);
                return;
            }
        }

        // No route matched
        http_response_code(404);
        echo '404 Not Found';
    }
}
