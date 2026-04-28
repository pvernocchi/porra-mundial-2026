<?php
declare(strict_types=1);

namespace App\Modules\Admin;

use App\Core\Application;
use App\Core\Request;
use App\Core\Response;

/**
 * Admin · Security. Captcha + MFA policy + lockout / session policy.
 */
final class SecurityController
{
    public function __construct(private Application $app)
    {
    }

    public function index(Request $req): Response
    {
        $captcha = (array)$this->app->settings()->get('security.captcha', []);
        $captcha['secret_key_set'] = !empty($captcha['secret_key']);
        // Don't echo the captcha secret.
        $captcha['secret_key'] = !empty($captcha['secret_key']) ? '__keep__' : '';

        $mfaPolicy = (string)$this->app->settings()->get('security.mfa.policy', 'optional');
        $freshAdmin = (bool)$this->app->settings()->get('security.mfa.fresh_required_for_admin', true);
        $rememberDays = (int)$this->app->settings()->get('security.mfa.remember_days', 0);

        $maxAttempts = (int)$this->app->settings()->get('security.login.max_attempts', 10);
        $window      = (int)$this->app->settings()->get('security.login.window_seconds', 900);
        $sessionIdle = (int)$this->app->config()->get('session.idle', 3600);

        $msg = $this->app->session()->flash('sec_msg');

        return (new Response())->html($this->app->view()->render('admin.security', [
            'captcha'      => $captcha,
            'mfaPolicy'    => $mfaPolicy,
            'freshAdmin'   => $freshAdmin,
            'rememberDays' => $rememberDays,
            'maxAttempts'  => $maxAttempts,
            'window'       => $window,
            'sessionIdle'  => $sessionIdle,
            'msg'          => $msg,
            'errors'       => [],
        ]));
    }

    public function save(Request $req): Response
    {
        if (!$this->app->csrf()->valid((string)$req->input('_token'))) {
            $this->app->session()->flash('sec_msg', 'Token CSRF inválido.');
            return (new Response())->redirect($this->app->baseUrl() . '/admin/security');
        }

        $errors = [];

        // --- Captcha ---
        $current = (array)$this->app->settings()->get('security.captcha', []);
        $provider  = (string)$req->input('captcha_provider', 'none');
        if (!in_array($provider, ['none', 'recaptcha_v2', 'recaptcha_v3', 'turnstile'], true)) {
            $provider = 'none';
        }
        $siteKey   = trim((string)$req->input('captcha_site_key', ''));
        $secretRaw = (string)$req->input('captcha_secret_key', '');
        $threshold = (float)$req->input('captcha_threshold', 0.5);
        if ($threshold < 0) { $threshold = 0.0; }
        if ($threshold > 1) { $threshold = 1.0; }

        $secret = ($secretRaw === '__keep__' || $secretRaw === '')
            ? (string)($current['secret_key'] ?? '')
            : $secretRaw;

        if ($provider !== 'none' && ($siteKey === '' || $secret === '')) {
            $errors[] = 'Para activar el captcha debes proporcionar site key y secret key.';
        }

        // --- MFA ---
        $policy = (string)$req->input('mfa_policy', 'optional');
        if (!in_array($policy, ['optional', 'admins', 'all'], true)) {
            $policy = 'optional';
        }
        $fresh = (bool)$req->input('mfa_fresh_required', false);
        $remember = (int)$req->input('mfa_remember_days', 0);
        if (!in_array($remember, [0, 7, 30], true)) {
            $remember = 0;
        }

        // --- Login lockout ---
        $maxAttempts = (int)$req->input('login_max_attempts', 10);
        if ($maxAttempts < 3) { $maxAttempts = 3; }
        if ($maxAttempts > 100) { $maxAttempts = 100; }
        $window = (int)$req->input('login_window_seconds', 900);
        if ($window < 60) { $window = 60; }
        if ($window > 86400) { $window = 86400; }

        if ($errors !== []) {
            $this->app->session()->flash('sec_msg', implode(' ', $errors));
            return (new Response())->redirect($this->app->baseUrl() . '/admin/security');
        }

        $this->app->settings()->set('security.captcha', [
            'provider'   => $provider,
            'site_key'   => $siteKey,
            'secret_key' => $secret,
            'threshold'  => $threshold,
        ]);
        $this->app->settings()->set('security.mfa.policy', $policy);
        $this->app->settings()->set('security.mfa.fresh_required_for_admin', $fresh);
        $this->app->settings()->set('security.mfa.remember_days', $remember);
        $this->app->settings()->set('security.login.max_attempts', $maxAttempts);
        $this->app->settings()->set('security.login.window_seconds', $window);

        $me = $this->app->auth()->user();
        $this->app->audit()->log('settings.security_updated', $me?->id, [
            'captcha_provider' => $provider,
            'mfa_policy'       => $policy,
            'login_max_attempts' => $maxAttempts,
        ]);

        $this->app->session()->flash('sec_msg', 'Configuración guardada.');
        return (new Response())->redirect($this->app->baseUrl() . '/admin/security');
    }
}
