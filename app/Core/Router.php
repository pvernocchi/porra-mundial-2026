<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Tiny route matcher. Routes are defined as ['METHOD', '/path/{id}', handler]
 * where handler is a callable receiving (Request, array $params, Application $app).
 *
 * The router intentionally avoids reflection so it works on any host with
 * minimal PHP extensions.
 */
final class Router
{
    /** @var array<int, array{0:string,1:string,2:callable,3:array<int,callable>}> */
    private array $routes = [];

    /** @var array<int, callable> */
    private array $globalMiddleware = [];

    public function use(callable $middleware): void
    {
        $this->globalMiddleware[] = $middleware;
    }

    /**
     * @param array<int, callable> $middleware
     */
    public function add(string $method, string $path, callable $handler, array $middleware = []): void
    {
        $this->routes[] = [strtoupper($method), $path, $handler, $middleware];
    }

    public function get(string $path, callable $h, array $mw = []): void    { $this->add('GET', $path, $h, $mw); }
    public function post(string $path, callable $h, array $mw = []): void   { $this->add('POST', $path, $h, $mw); }
    public function any(string $path, callable $h, array $mw = []): void
    {
        foreach (['GET', 'POST'] as $m) {
            $this->add($m, $path, $h, $mw);
        }
    }

    /**
     * @return array{handler:callable, params:array<string,string>, middleware:array<int,callable>}|null
     */
    public function match(string $method, string $path): ?array
    {
        $method = strtoupper($method);
        foreach ($this->routes as [$m, $p, $handler, $mw]) {
            if ($m !== $method) {
                continue;
            }
            $regex = '#^' . preg_replace('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', '(?P<$1>[^/]+)', $p) . '$#';
            if (preg_match($regex, $path, $m2)) {
                $params = [];
                foreach ($m2 as $k => $v) {
                    if (!is_int($k)) {
                        $params[$k] = $v;
                    }
                }
                return ['handler' => $handler, 'params' => $params, 'middleware' => $mw];
            }
        }
        return null;
    }

    public function dispatch(Request $req, Application $app): Response
    {
        $found = $this->match($req->method(), $req->path());
        if ($found === null) {
            return (new Response())->status(404)->html(
                '<!doctype html><meta charset="utf-8"><title>404</title>' .
                '<h1>404 — No encontrado</h1><p>La ruta <code>' .
                htmlspecialchars($req->path(), ENT_QUOTES) .
                '</code> no existe.</p>',
                404
            );
        }

        // Compose middleware chain (global + per-route) wrapping the handler.
        $stack = array_merge($this->globalMiddleware, $found['middleware']);
        $core  = function (Request $r) use ($found, $app): Response {
            $res = ($found['handler'])($r, $found['params'], $app);
            return $res instanceof Response ? $res : (new Response())->html((string)$res);
        };
        $next = $core;
        foreach (array_reverse($stack) as $mw) {
            $current = $next;
            $next = static fn(Request $r): Response => $mw($r, $current, $app);
        }
        return $next($req);
    }
}
