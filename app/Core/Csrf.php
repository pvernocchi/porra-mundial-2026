<?php
declare(strict_types=1);

namespace App\Core;

/**
 * CSRF token helper. Uses a single per-session token validated with
 * a constant-time comparison.
 */
final class Csrf
{
    public function __construct(private Session $session)
    {
    }

    public function token(): string
    {
        $tok = (string)$this->session->get('_csrf', '');
        if ($tok === '') {
            $tok = bin2hex(random_bytes(32));
            $this->session->set('_csrf', $tok);
        }
        return $tok;
    }

    public function valid(?string $candidate): bool
    {
        $real = (string)$this->session->get('_csrf', '');
        if ($real === '' || $candidate === null || $candidate === '') {
            return false;
        }
        return hash_equals($real, $candidate);
    }

    public function field(): string
    {
        return '<input type="hidden" name="_token" value="' . htmlspecialchars($this->token(), ENT_QUOTES) . '">';
    }
}
