<?php

declare(strict_types=1);

namespace Hut;

class Router
{
    private array $routes = [];

    public function add(string $method, string $pattern, callable $handler): void
    {
        $this->routes[] = [$method, $pattern, $handler];
    }

    public function get(string $pattern, callable $handler): void
    {
        $this->add('GET', $pattern, $handler);
    }

    public function post(string $pattern, callable $handler): void
    {
        $this->add('POST', $pattern, $handler);
    }

    /**
     * Dispatch the current request.
     * Returns false if no route matched (caller should send 404).
     */
    public function dispatch(string $method, string $uri): bool
    {
        // Strip query string
        $path = parse_url($uri, PHP_URL_PATH);

        foreach ($this->routes as [$routeMethod, $pattern, $handler]) {
            if (strtoupper($method) !== strtoupper($routeMethod)) {
                continue;
            }

            $regex = preg_replace('/\{(\w+)\}/', '(?P<$1>[^/]+)', $pattern);
            $regex = '#^' . $regex . '$#';

            if (preg_match($regex, $path, $matches)) {
                // Extract named captures only
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                if (is_array($handler) && count($handler) === 2) {
                    [$class, $method] = $handler;
                    $class::$method($params);
                } else {
                    $handler($params);
                }
                return true;
            }
        }

        return false;
    }
}
