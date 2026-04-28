<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Application kernel. Owns the lifecycle and the service container.
 *
 * Designed to be tiny and predictable: explicit getters, no reflection,
 * no magic.
 */
final class Application
{
    private string $root;
    private ?Config $config = null;
    private ?Database $db = null;
    private ?Session $session = null;
    private ?Csrf $csrf = null;
    private ?View $view = null;
    private ?Mail $mail = null;
    private ?Settings $settings = null;
    private ?Audit $audit = null;
    private ?Auth $auth = null;
    private ?RateLimit $rateLimit = null;
    private ?Captcha $captcha = null;
    private ?Router $router = null;

    public function __construct(string $root)
    {
        $this->root = rtrim($root, '/\\');
    }

    public function root(): string
    {
        return $this->root;
    }

    public function path(string $sub = ''): string
    {
        return $this->root . ($sub === '' ? '' : '/' . ltrim($sub, '/'));
    }

    public function isInstalled(): bool
    {
        return is_file($this->path('config/config.php'))
            && is_file($this->path('storage/installed.lock'));
    }

    public function version(): string
    {
        $file = $this->path('VERSION');
        return is_file($file) ? trim((string)file_get_contents($file)) : '0.0.0';
    }

    public function installedVersion(): ?string
    {
        $file = $this->path('storage/installed.lock');
        if (!is_file($file)) {
            return null;
        }
        $data = json_decode((string)file_get_contents($file), true);
        return is_array($data) && isset($data['version']) ? (string)$data['version'] : null;
    }

    public function config(): Config
    {
        if ($this->config === null) {
            $this->config = new Config($this->path('config/config.php'));
        }
        return $this->config;
    }

    public function db(): Database
    {
        if ($this->db === null) {
            $this->db = Database::fromConfig($this->config()->all()['db'] ?? []);
        }
        return $this->db;
    }

    public function session(): Session
    {
        if ($this->session === null) {
            $cfg = $this->config()->get('session', []);
            $this->session = new Session(
                name:    $cfg['name']     ?? 'porra_sess',
                idle:    (int)($cfg['idle'] ?? 3600),
            );
        }
        return $this->session;
    }

    public function csrf(): Csrf
    {
        return $this->csrf ??= new Csrf($this->session());
    }

    public function view(): View
    {
        if ($this->view === null) {
            $paths = [
                $this->path('app/Modules/Admin/views'),
                $this->path('app/Modules/Auth/views'),
                $this->path('app/Modules/Install/views'),
                $this->path('app/Modules/Game/views'),
            ];
            $this->view = new View($paths, $this);
        }
        return $this->view;
    }

    public function settings(): Settings
    {
        return $this->settings ??= new Settings($this->db());
    }

    public function audit(): Audit
    {
        return $this->audit ??= new Audit($this->db());
    }

    public function mail(): Mail
    {
        return $this->mail ??= new Mail($this);
    }

    public function auth(): Auth
    {
        return $this->auth ??= new Auth($this);
    }

    public function rateLimit(): RateLimit
    {
        return $this->rateLimit ??= new RateLimit($this->path('storage/cache/ratelimit'));
    }

    public function captcha(): Captcha
    {
        return $this->captcha ??= new Captcha($this->settings());
    }

    public function router(): Router
    {
        return $this->router ??= new Router();
    }

    public function appKey(): string
    {
        $key = (string)$this->config()->get('app_key', '');
        if ($key === '') {
            return '';
        }
        $raw = base64_decode($key, true);
        return $raw === false ? '' : $raw;
    }

    public function baseUrl(): string
    {
        $cfg = (string)$this->config()->get('site.base_url', '');
        if ($cfg !== '') {
            return rtrim($cfg, '/');
        }
        // Auto-detect when not configured (used during installation).
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
        // Sanitise the Host header to prevent header-injection attacks.
        // Allow alphanumerics, dots, hyphens, colons (for port), and brackets (for IPv6 like [::1]).
        $host = preg_replace('/[^a-zA-Z0-9.\-:]/', '', (string)$host);
        return $scheme . '://' . $host;
    }
}
