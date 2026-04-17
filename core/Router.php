<?php

declare(strict_types=1);

class Router
{
    private array $routes = [];

    public function get(string $path, callable $handler, array $middleware = []): void
    {
        $this->addRoute('GET', $path, $handler, $middleware);
    }

    public function post(string $path, callable $handler, array $middleware = []): void
    {
        $this->addRoute('POST', $path, $handler, $middleware);
    }

    public function patch(string $path, callable $handler, array $middleware = []): void
    {
        $this->addRoute('PATCH', $path, $handler, $middleware);
    }

    public function delete(string $path, callable $handler, array $middleware = []): void
    {
        $this->addRoute('DELETE', $path, $handler, $middleware);
    }

    private function addRoute(string $method, string $path, callable $handler, array $middleware): void
    {
        $this->routes[] = compact('method', 'path', 'handler', 'middleware');
    }

    public function dispatch(Request $req, Response $res): void
    {
        $method = $req->getMethod();
        $uri    = $req->getUri();

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            $pattern = $this->pathToPattern($route['path']);
            if (preg_match($pattern, $uri, $matches)) {
                $params = array_filter($matches, function ($k) { return !is_int($k); }, ARRAY_FILTER_USE_KEY);
                $req->setParams($params);

                $this->runMiddleware($route['middleware'], $req, $res, function () use ($route, $req, $res) {
                    call_user_func($route['handler'], $req, $res);
                });
                return;
            }
        }

        $res->status(404)->json(['error' => 'Route not found']);
    }

    private function pathToPattern(string $path): string
    {
        $pattern = preg_replace('/\/:([a-zA-Z_]+)/', '/(?P<$1>[^/]+)', $path);
        return '#^' . $pattern . '$#';
    }

    private function runMiddleware(array $middleware, Request $req, Response $res, callable $final): void
    {
        if (empty($middleware)) {
            call_user_func($final);
            return;
        }

        $mw   = array_shift($middleware);
        $self = $this;
        $next = function () use ($self, $middleware, $req, $res, $final) {
            $self->runMiddleware($middleware, $req, $res, $final);
        };
        call_user_func($mw, $req, $res, $next);
    }
}
