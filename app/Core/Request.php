<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Wraps a single HTTP request. Path normalisation strips the application
 * base path so handlers always see paths starting with "/".
 */
final class Request
{
    /** @var array<string, string> */
    private array $get;
    /** @var array<string, mixed> */
    private array $post;
    /** @var array<string, string> */
    private array $server;
    /** @var array<string, string> */
    private array $cookies;
    /** @var array<string, mixed> */
    private array $files;

    private string $method;
    private string $path;
    private string $rawPath;

    /**
     * @param array<string, mixed> $get
     * @param array<string, mixed> $post
     * @param array<string, mixed> $server
     * @param array<string, mixed> $cookies
     * @param array<string, mixed> $files
     */
    public function __construct(
        array $get = [],
        array $post = [],
        array $server = [],
        array $cookies = [],
        array $files = []
    ) {
        $this->get     = array_map(static fn($v) => is_array($v) ? $v : (string)$v, $get);
        $this->post    = $post;
        $this->server  = array_map(static fn($v) => (string)$v, $server);
        $this->cookies = array_map(static fn($v) => (string)$v, $cookies);
        $this->files   = $files;

        $this->method  = strtoupper($server['REQUEST_METHOD'] ?? 'GET');
        $uri           = (string)($server['REQUEST_URI'] ?? '/');
        $path          = parse_url($uri, PHP_URL_PATH) ?: '/';
        $this->rawPath = $path;
        $this->path    = self::normalisePath($path, (string)($server['SCRIPT_NAME'] ?? ''));
    }

    public static function fromGlobals(): self
    {
        return new self($_GET, $_POST, $_SERVER, $_COOKIE, $_FILES);
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function rawPath(): string
    {
        return $this->rawPath;
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->get[$key] ?? $default;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->post[$key] ?? $default;
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return array_replace($this->get, $this->post);
    }

    public function ip(): string
    {
        return (string)($this->server['REMOTE_ADDR'] ?? '0.0.0.0');
    }

    public function userAgent(): string
    {
        return (string)($this->server['HTTP_USER_AGENT'] ?? '');
    }

    public function isHttps(): bool
    {
        $h = strtolower((string)($this->server['HTTPS'] ?? ''));
        if ($h !== '' && $h !== 'off') {
            return true;
        }
        $proto = strtolower((string)($this->server['HTTP_X_FORWARDED_PROTO'] ?? ''));
        return $proto === 'https';
    }

    public function header(string $name): string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        return (string)($this->server[$key] ?? '');
    }

    private static function normalisePath(string $path, string $script): string
    {
        // If the front controller is reached directly (no rewrite), the URI
        // typically looks like "/public/index.php/foo". Strip the prefix up
        // to and including the script name.
        $scriptDir = rtrim(str_replace('\\', '/', dirname($script)), '/');
        if ($scriptDir !== '' && $scriptDir !== '/' && str_starts_with($path, $scriptDir . '/')) {
            $path = substr($path, strlen($scriptDir));
        }
        if (str_starts_with($path, '/index.php')) {
            $path = substr($path, strlen('/index.php'));
        }
        if ($path === '' || $path === false) {
            $path = '/';
        }
        return $path;
    }
}
