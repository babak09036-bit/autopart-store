<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Small router: "/api/products/{id:\d+}" style patterns, per-route middleware and controller callables.
 * Middleware names: csrf, auth, admin, perm:<slug>, throttle:<name>,<max>,<seconds>
 */
final class Router
{
    private array $routes = [];
    private array $groupStack = [];

    public function get(string $path, array|callable $handler, array $middleware = []): void
    {
        $this->add('GET', $path, $handler, $middleware);
    }

    public function post(string $path, array|callable $handler, array $middleware = []): void
    {
        $this->add('POST', $path, $handler, $middleware);
    }

    public function put(string $path, array|callable $handler, array $middleware = []): void
    {
        $this->add('PUT', $path, $handler, $middleware);
    }

    public function patch(string $path, array|callable $handler, array $middleware = []): void
    {
        $this->add('PATCH', $path, $handler, $middleware);
    }

    public function delete(string $path, array|callable $handler, array $middleware = []): void
    {
        $this->add('DELETE', $path, $handler, $middleware);
    }

    /** Groups routes under a prefix with shared middleware. */
    public function group(string $prefix, array $middleware, callable $routes): void
    {
        $this->groupStack[] = [$prefix, $middleware];
        $routes($this);
        array_pop($this->groupStack);
    }

    private function add(string $method, string $path, array|callable $handler, array $middleware): void
    {
        $prefix = '';
        $shared = [];
        foreach ($this->groupStack as [$p, $m]) {
            $prefix .= $p;
            $shared = array_merge($shared, $m);
        }
        $full = rtrim($prefix . $path, '/') ?: '/';
        $regex = preg_replace_callback('#\{(\w+)(?::([^}]+))?\}#', static function ($m) {
            return '(?P<' . $m[1] . '>' . ($m[2] ?? '[^/]+') . ')';
        }, $full);
        $this->routes[] = [
            'method' => $method,
            'regex' => '#^' . $regex . '$#u',
            'handler' => $handler,
            'middleware' => array_merge($shared, $middleware),
        ];
    }

    public function dispatch(Request $request): Response
    {
        $allowed = [];
        foreach ($this->routes as $route) {
            if (!preg_match($route['regex'], $request->path, $m)) {
                continue;
            }
            if ($route['method'] !== $request->method && !($route['method'] === 'GET' && $request->method === 'HEAD')) {
                $allowed[] = $route['method'];
                continue;
            }
            $request->params = array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);
            foreach ($route['middleware'] as $name) {
                Middleware::run($name, $request);
            }
            $handler = $route['handler'];
            if (is_array($handler)) {
                [$class, $method] = $handler;
                $handler = [new $class(), $method];
            }
            $response = $handler($request);
            return $response instanceof Response ? $response : Response::ok($response);
        }
        if ($allowed) {
            throw new HttpException(405, 'این متد برای این مسیر مجاز نیست.');
        }
        throw new HttpException(404, 'مسیر درخواستی یافت نشد.');
    }
}
