<?php

namespace App\Core;

class Router
{
    private array $routes = [];

    public function get(string $path, callable|array $handler): void
    {
        $this->routes['GET'][$path] = $handler;
    }

    public function post(string $path, callable|array $handler): void
    {
        $this->routes['POST'][$path] = $handler;
    }

    public function put(string $path, callable|array $handler): void
    {
        $this->routes['PUT'][$path] = $handler;
    }

    public function delete(string $path, callable|array $handler): void
    {
        $this->routes['DELETE'][$path] = $handler;
    }

    public function dispatch(string $method, string $uri): void
    {
        $uri = strtok($uri, '?');
        $uri = rtrim($uri, '/') ?: '/';
        $method = $this->normalizeMethod($method);

        foreach ($this->routes[$method] ?? [] as $pattern => $handler) {
            $regex  = $this->toRegex($pattern);
            if (preg_match($regex, $uri, $matches)) {
                array_shift($matches);
                $this->call($handler, $matches);
                return;
            }
        }

        http_response_code(404);
        if ($this->isApi($uri)) {
            Response::json(['error' => 'Not found'], 404);
        } else {
            require __DIR__ . '/../../views/errors/404.php';
        }
    }

    private function toRegex(string $pattern): string
    {
        $regex = preg_replace('/\{(\w+)\}/', '([^/]+)', $pattern);
        return '#^' . $regex . '$#';
    }

    private function isApi(string $uri): bool
    {
        return str_starts_with($uri, '/api/');
    }

    private function normalizeMethod(string $method): string
    {
        $method = strtoupper($method);
        if ($method === 'POST') {
            $override = strtoupper((string)($_POST['_method'] ?? $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] ?? ''));
            if (in_array($override, ['PUT', 'DELETE'], true)) {
                return $override;
            }
        }
        return $method;
    }

    private function call(callable|array $handler, array $params): void
    {
        if (is_callable($handler)) {
            call_user_func_array($handler, $params);
            return;
        }
        [$class, $method] = $handler;
        $instance = new $class();
        call_user_func_array([$instance, $method], $params);
    }
}
