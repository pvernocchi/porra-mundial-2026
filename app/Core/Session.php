<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Session helper. Uses native PHP sessions configured with safe cookie
 * defaults (HttpOnly, SameSite=Lax, Secure when HTTPS is detected).
 *
 * Implements idle timeout: sessions older than `idle` seconds since the
 * last activity are destroyed.
 */
final class Session
{
    private bool $started = false;

    public function __construct(
        private string $name = 'porra_sess',
        private int $idle = 3600,
    ) {
    }

    public function start(): void
    {
        if ($this->started || PHP_SAPI === 'cli' || session_status() === PHP_SESSION_ACTIVE) {
            $this->started = true;
            return;
        }

        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

        session_name($this->name);
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'domain'   => '',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');

        @session_start();
        $this->started = true;

        $now  = time();
        $last = (int)($_SESSION['_last_activity'] ?? 0);
        if ($last > 0 && ($now - $last) > $this->idle) {
            $this->destroy();
            @session_start();
        }
        $_SESSION['_last_activity'] = $now;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $this->start();
        return $_SESSION[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->start();
        $_SESSION[$key] = $value;
    }

    public function has(string $key): bool
    {
        $this->start();
        return array_key_exists($key, $_SESSION);
    }

    public function forget(string $key): void
    {
        $this->start();
        unset($_SESSION[$key]);
    }

    public function flash(string $key, ?string $message = null): ?string
    {
        $this->start();
        $bag = $_SESSION['_flash'] ?? [];
        if ($message !== null) {
            $bag[$key] = $message;
            $_SESSION['_flash'] = $bag;
            return null;
        }
        $msg = $bag[$key] ?? null;
        unset($bag[$key]);
        $_SESSION['_flash'] = $bag;
        return $msg;
    }

    public function regenerate(): void
    {
        $this->start();
        @session_regenerate_id(true);
    }

    public function destroy(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires'  => time() - 3600,
                'path'     => $params['path'],
                'domain'   => $params['domain'],
                'secure'   => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => $params['samesite'] ?? 'Lax',
            ]);
            session_destroy();
        }
        $this->started = false;
    }
}
