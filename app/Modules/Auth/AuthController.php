<?php
declare(strict_types=1);

namespace App\Modules\Auth;

use App\Core\Application;
use App\Core\Request;
use App\Core\Response;
use App\Models\MfaCredential;
use App\Models\MfaRecoveryCode;
use App\Models\User;
use App\Core\Crypto;
use App\Core\Totp;

/**
 * Login + logout + MFA challenge.
 */
final class AuthController
{
    public function __construct(private Application $app)
    {
    }

    public function showLogin(Request $req): Response
    {
        if ($this->app->auth()->check()) {
            $target = $this->app->auth()->canManageUsers() ? '/admin' : '/game/picks';
            return (new Response())->redirect($this->app->baseUrl() . $target);
        }
        $error = $this->app->session()->flash('login_error');
        $next  = (string)$req->query('next', '');
        $captcha = $this->app->captcha();
        return (new Response())->html($this->app->view()->render('auth.login', [
            'error'        => $error,
            'next'         => $next,
            'captcha_html' => $captcha->isEnabled() ? $captcha->widgetHtml('login') : '',
            'captcha_provider' => $captcha->provider(),
        ]));
    }

    public function doLogin(Request $req): Response
    {
        if (!$this->app->csrf()->valid((string)$req->input('_token'))) {
            $this->app->session()->flash('login_error', 'Token CSRF inválido.');
            return (new Response())->redirect($this->app->baseUrl() . '/login');
        }

        // Captcha
        $captcha = $this->app->captcha();
        if ($captcha->isEnabled()) {
            $token = (string)($req->input('g-recaptcha-response')
                ?? $req->input('cf-turnstile-response')
                ?? $req->input('captcha_token', ''));
            if (!$captcha->verify($token, $req->ip())) {
                $this->app->session()->flash('login_error', 'Captcha inválido.');
                return (new Response())->redirect($this->app->baseUrl() . '/login');
            }
        }

        $email = (string)$req->input('email', '');
        $pass  = (string)$req->input('password', '');
        $next  = (string)$req->input('next', '');

        $r = $this->app->auth()->attemptCredentials($email, $pass, $req->ip());
        if ($r['status'] === 'rate_limited') {
            $this->app->session()->flash('login_error', 'Demasiados intentos. Vuelve a intentarlo en ' . (int)($r['retryAfter'] ?? 60) . ' segundos.');
            return (new Response())->redirect($this->app->baseUrl() . '/login');
        }
        if ($r['status'] === 'invalid') {
            $this->app->session()->flash('login_error', $r['message'] ?? 'Credenciales incorrectas.');
            return (new Response())->redirect($this->app->baseUrl() . '/login');
        }
        if ($r['status'] === 'mfa_required') {
            $url = $this->app->baseUrl() . '/login/mfa';
            if ($next !== '') {
                $url .= '?next=' . urlencode($next);
            }
            return (new Response())->redirect($url);
        }
        if ($r['status'] === 'enrollment_required') {
            $url = $this->app->baseUrl() . '/account/mfa/enroll';
            if ($next !== '') {
                $url .= '?next=' . urlencode($next);
            }
            return (new Response())->redirect($url);
        }

        // ok
        return (new Response())->redirect($this->safeRedirect($next));
    }

    public function showMfa(Request $req): Response
    {
        $uid = $this->app->auth()->pendingMfaUserId();
        if ($uid === null) {
            return (new Response())->redirect($this->app->baseUrl() . '/login');
        }
        $error = $this->app->session()->flash('mfa_error');
        $next  = (string)$req->query('next', '');
        $creds = (new MfaCredential($this->app->db()))->listForUser($uid);
        $hasTotp     = false;
        $hasWebauthn = false;
        foreach ($creds as $c) {
            if ($c['type'] === 'totp') { $hasTotp = true; }
            if ($c['type'] === 'webauthn') { $hasWebauthn = true; }
        }
        return (new Response())->html($this->app->view()->render('auth.mfa', [
            'error'        => $error,
            'next'         => $next,
            'hasTotp'      => $hasTotp,
            'hasWebauthn'  => $hasWebauthn,
        ]));
    }

    public function doMfa(Request $req): Response
    {
        if (!$this->app->csrf()->valid((string)$req->input('_token'))) {
            $this->app->session()->flash('mfa_error', 'Token CSRF inválido.');
            return (new Response())->redirect($this->app->baseUrl() . '/login/mfa');
        }
        $uid = $this->app->auth()->pendingMfaUserId();
        if ($uid === null) {
            return (new Response())->redirect($this->app->baseUrl() . '/login');
        }
        $next = (string)$req->input('next', '');
        $method = (string)$req->input('method', 'totp');

        // Rate limit MFA challenges per user.
        $rl = $this->app->rateLimit()->hit('mfa:' . $uid, 10, 600);
        if (!$rl['allowed']) {
            $this->app->session()->flash('mfa_error', 'Demasiados intentos. Espera ' . $rl['retryAfter'] . 's.');
            return (new Response())->redirect($this->app->baseUrl() . '/login/mfa');
        }

        $user = (new User($this->app->db()))->find($uid);
        if ($user === null) {
            $this->app->auth()->clearPendingMfa();
            return (new Response())->redirect($this->app->baseUrl() . '/login');
        }

        $ok = false;
        if ($method === 'totp') {
            $code = (string)$req->input('code', '');
            $key  = $this->app->appKey();
            if ($key === '') {
                $this->app->session()->flash('mfa_error', 'APP_KEY no configurado.');
                return (new Response())->redirect($this->app->baseUrl() . '/login/mfa');
            }
            $crypto = new Crypto($key);
            foreach ((new MfaCredential($this->app->db()))->listByType($uid, 'totp') as $cred) {
                $secret = $crypto->decrypt((string)$cred['secret']);
                if ($secret !== null && Totp::verify($secret, $code)) {
                    $ok = true;
                    (new MfaCredential($this->app->db()))->touchLastUsed((int)$cred['id']);
                    break;
                }
            }
        } elseif ($method === 'recovery') {
            $code = (string)$req->input('code', '');
            $ok = (new MfaRecoveryCode($this->app->db()))->consume($uid, $code);
        }
        // Note: WebAuthn login is handled by a separate JSON endpoint
        // that calls finaliseLogin directly when assertion verifies.

        if (!$ok) {
            $this->app->audit()->log('mfa.failed', $uid, ['method' => $method]);
            $this->app->session()->flash('mfa_error', 'Código incorrecto.');
            return (new Response())->redirect($this->app->baseUrl() . '/login/mfa');
        }

        $this->app->auth()->finaliseLogin($user, mfaUsed: true);
        return (new Response())->redirect($this->safeRedirect($next));
    }

    public function logout(Request $req): Response
    {
        $this->app->auth()->logout();
        return (new Response())->redirect($this->app->baseUrl() . '/login');
    }

    /**
     * Validate a redirect target to prevent open-redirect attacks.
     * Only relative paths (starting with /) are accepted; anything else
     * (including protocol-relative URLs like //evil.com) falls back to
     * the role-appropriate default.
     */
    private function safeRedirect(string $url): string
    {
        $default = $this->app->baseUrl() . ($this->app->auth()->canManageUsers() ? '/admin' : '/game/picks');
        if ($url === '') {
            return $default;
        }
        // Must start with a single slash and not be a protocol-relative URL.
        if (!str_starts_with($url, '/') || str_starts_with($url, '//')) {
            return $default;
        }
        return $url;
    }
}
