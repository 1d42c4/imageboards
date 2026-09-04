<?php

declare(strict_types=1);

namespace Chessboard\Http;

final class Router
{
    private array $routes = [];

    public function add(string $method, string $pattern, callable $handler): void
    {
        $this->routes[] = [strtoupper($method), $pattern, $handler];
    }

    public function dispatch(Request $request): Response
    {
        $allowed = [];

        foreach ($this->routes as [$method, $pattern, $handler]) {
            if (!preg_match($pattern, $request->path, $matches)) {
                continue;
            }

            if ($request->method !== $method && !($request->method === 'HEAD' && $method === 'GET')) {
                $allowed[] = $method;
                continue;
            }

            $parameters = array_filter(
                $matches,
                static fn (int|string $key): bool => is_string($key),
                ARRAY_FILTER_USE_KEY,
            );

            return $handler($request, $parameters);
        }

        if ($allowed !== []) {
            throw new HttpException(405, 'Method not allowed.');
        }

        throw new HttpException(404, 'Page not found.');
    }
}

