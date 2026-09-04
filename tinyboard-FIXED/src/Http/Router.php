<?php

declare(strict_types=1);

namespace Newboard\Http;

final class Router
{
    /** @var list<array{methods: list<string>, pattern: string, handler: callable}> */
    private array $routes = [];

    /** @param callable(Request, array<string, string>): Response $handler */
    public function add(string $methods, string $pattern, callable $handler): void
    {
        $methodsList = array_map('strtoupper', array_map('trim', explode('|', $methods)));
        $this->routes[] = [
            'methods' => $methodsList,
            'pattern' => $pattern,
            'handler' => $handler,
        ];
    }

    public function dispatch(Request $request): Response
    {
        foreach ($this->routes as $route) {
            if (!in_array($request->method, $route['methods'], true) && !in_array('ANY', $route['methods'], true)) {
                continue;
            }
            $params = $this->match($route['pattern'], $request->path);
            if ($params === null) {
                continue;
            }

            return ($route['handler'])($request, $params);
        }

        return Response::html('<h1>404</h1><p>Not found.</p>', 404);
    }

    /** @return array<string, string>|null */
    private function match(string $pattern, string $path): ?array
    {
        $regex = preg_replace('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', '(?P<$1>[^/]+)', $pattern);
        $regex = '#^' . $regex . '$#u';
        if (!preg_match($regex, $path, $m)) {
            return null;
        }
        $params = [];
        foreach ($m as $k => $v) {
            if (is_string($k)) {
                $params[$k] = $v;
            }
        }

        return $params;
    }
}
