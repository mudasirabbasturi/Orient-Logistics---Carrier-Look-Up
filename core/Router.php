<?php
namespace Core;

class Router {
    protected static array $routes = [];

    public static function get(string $path, $handler): void {
        self::$routes['GET'][$path] = $handler;
    }

    public static function post(string $path, $handler): void {
        self::$routes['POST'][$path] = $handler;
    }

    public static function dispatch(string $method, string $uri) {
        $routes = self::$routes[$method] ?? [];

        if (isset($routes[$uri])) {
            return self::executeHandler($routes[$uri]);
        }

        // Try matching dynamic routes or fallback
        foreach ($routes as $route => $handler) {
            $pattern = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<$1>[^/]+)', $route);
            $pattern = "#^{$pattern}$#";
            if (preg_match($pattern, $uri, $matches)) {
                $params = array_filter($matches, '\is_string', ARRAY_FILTER_USE_KEY);
                return self::executeHandler($handler, $params);
            }
        }

        // 404 Not Found
        http_response_code(404);
        if (Request::isAjax()) {
            Response::json(['error' => 'Endpoint not found', 'path' => $uri], 404);
        } else {
            echo "<h1>404 Not Found</h1><p>Route {$uri} does not exist.</p>";
            exit;
        }
    }

    protected static function executeHandler($handler, array $params = []) {
        if (is_callable($handler)) {
            return call_user_func_array($handler, $params);
        }

        if (is_string($handler) && strpos($handler, '@') !== false) {
            [$class, $method] = explode('@', $handler);
            $class = "\\App\\Controllers\\{$class}";
            if (class_exists($class)) {
                $instance = new $class();
                if (method_exists($instance, $method)) {
                    return call_user_func_array([$instance, $method], $params);
                }
            }
        }

        throw new \Exception("Handler not found for route");
    }
}

