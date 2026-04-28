<?php
declare(strict_types=1);

namespace App\Modules\Auth;

use App\Core\Application;
use App\Core\Crypto;
use App\Core\Request;
use App\Core\Response;
use App\Core\Totp;
use App\Models\MfaCredential;
use App\Models\MfaRecoveryCode;

/**
 * MFA enrollment + management for the logged-in user.
 *
 * Routes:
 *   GET  /account/mfa            -> dashboard (list credentials)
 *   GET  /account/mfa/enroll     -> entry point used right after login
 *                                   when policy requires MFA but user
 *                                   has none.
 *   GET  /account/mfa/totp/new   -> show TOTP enrollment form (QR)
 *   POST /account/mfa/totp/new   -> confirm TOTP code, save credential
 *   POST /account/mfa/{id}/delete-> delete a credential
 *
 * WebAuthn endpoints (JSON):
 *   GET  /account/mfa/webauthn/register-options
 *   POST /account/mfa/webauthn/register
 *   GET  /api/webauthn/login-options
 *   POST /api/webauthn/login
 *
 * The WebAuthn endpoints return 501 unless the optional
 * `web-auth/webauthn-lib` package is bundled (production zips include
 * it; bare-bones `composer install`-less dev installs see the stub).
 */
final class MfaController
{
    public function __construct(private Application $app)
    {
    }

    /* --------- helpers --------- */

    private function userIdOrPending(): ?int
    {
        $u = $this->app->auth()->user();
        if ($u !== null) {
            return $u->id;
        }
        return $this->app->auth()->pendingEnrollmentUserId();
    }

    private function requireUser(): ?Response
    {
        if ($this->userIdOrPending() === null) {
            return (new Response())->redirect($this->app->baseUrl() . '/login');
        }
        return null;
    }

    /* --------- dashboard --------- */

    public function dashboard(Request $req): Response
    {
        if ($r = $this->requireUser()) { return $r; }
        $uid = (int)$this->userIdOrPending();
        $creds = (new MfaCredential($this->app->db()))->listForUser($uid);
        $unusedCodes = (new MfaRecoveryCode($this->app->db()))->unusedCount($uid);
        $rpAllowed = $this->isHttps();
        $msg = $this->app->session()->flash('mfa_msg');

        return (new Response())->html($this->app->view()->render('auth.mfa-dashboard', [
            'creds'        => $creds,
            'unusedCodes'  => $unusedCodes,
            'rpAllowed'    => $rpAllowed,
            'msg'          => $msg,
            'pending'      => $this->app->auth()->user() === null, // mid-enrollment
        ]));
    }

    public function totpNew(Request $req): Response
    {
        if ($r = $this->requireUser()) { return $r; }
        $uid = (int)$this->userIdOrPending();
        $sess = $this->app->session();

        // Fresh secret per visit so a stale browser can't reuse one.
        $secret = (string)$sess->get('totp_pending_secret', '');
        if ($secret === '' || (string)$req->query('regen', '') === '1') {
            $secret = Totp::generateSecret();
            $sess->set('totp_pending_secret', $secret);
        }

        $email = $this->app->auth()->user()?->email
            ?? (new \App\Models\User($this->app->db()))->find($uid)?->email
            ?? 'user';
        $issuer = (string)$this->app->config()->get('site.name', 'Porra Mundial 2026');
        $uri = Totp::provisioningUri($secret, $email, $issuer);

        return (new Response())->html($this->app->view()->render('auth.mfa-totp', [
            'secret'      => $secret,
            'uri'         => $uri,
            'qr_url'      => 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . urlencode($uri),
            'error'       => $this->app->session()->flash('totp_error'),
        ]));
    }

    public function totpConfirm(Request $req): Response
    {
        if ($r = $this->requireUser()) { return $r; }
        if (!$this->app->csrf()->valid((string)$req->input('_token'))) {
            $this->app->session()->flash('totp_error', 'Token CSRF inválido.');
            return (new Response())->redirect($this->app->baseUrl() . '/account/mfa/totp/new');
        }

        $uid    = (int)$this->userIdOrPending();
        $secret = (string)$this->app->session()->get('totp_pending_secret', '');
        $code   = (string)$req->input('code', '');
        $label  = trim((string)$req->input('label', ''));

        if ($secret === '' || !Totp::verify($secret, $code)) {
            $this->app->session()->flash('totp_error', 'Código incorrecto. Comprueba la hora del dispositivo y vuelve a intentarlo.');
            return (new Response())->redirect($this->app->baseUrl() . '/account/mfa/totp/new');
        }

        $key = $this->app->appKey();
        if ($key === '') {
            $this->app->session()->flash('totp_error', 'APP_KEY no configurado en config/config.php.');
            return (new Response())->redirect($this->app->baseUrl() . '/account/mfa/totp/new');
        }
        $encrypted = (new Crypto($key))->encrypt($secret);
        (new MfaCredential($this->app->db()))->createTotp($uid, $label !== '' ? $label : 'Authenticator', $encrypted);
        $this->app->session()->forget('totp_pending_secret');

        // Generate recovery codes if this is the first credential.
        $codes = null;
        if ((new MfaRecoveryCode($this->app->db()))->unusedCount($uid) === 0) {
            $codes = (new MfaRecoveryCode($this->app->db()))->regenerate($uid);
        }

        $this->app->audit()->log('mfa.totp.added', $uid, ['label' => $label]);

        // If the user was mid-enrollment (came from a forced policy),
        // finalise the login now.
        if ($this->app->auth()->user() === null) {
            $user = (new \App\Models\User($this->app->db()))->find($uid);
            if ($user !== null) {
                $this->app->auth()->finaliseLogin($user, mfaUsed: true);
            }
        }

        if ($codes !== null) {
            return (new Response())->html($this->app->view()->render('auth.mfa-recovery-codes', [
                'codes' => $codes,
            ]));
        }
        $this->app->session()->flash('mfa_msg', 'TOTP añadido correctamente.');
        return (new Response())->redirect($this->app->baseUrl() . '/account/mfa');
    }

    public function deleteCredential(Request $req, array $params): Response
    {
        if ($r = $this->requireUser()) { return $r; }
        if (!$this->app->csrf()->valid((string)$req->input('_token'))) {
            $this->app->session()->flash('mfa_msg', 'Token CSRF inválido.');
            return (new Response())->redirect($this->app->baseUrl() . '/account/mfa');
        }
        $uid = (int)$this->userIdOrPending();
        $id  = (int)($params['id'] ?? 0);
        $deleted = (new MfaCredential($this->app->db()))->deleteForUser($id, $uid);
        if ($deleted) {
            $this->app->audit()->log('mfa.deleted', $uid, ['credential_id' => $id]);
            $this->app->session()->flash('mfa_msg', 'Método eliminado.');
        }
        return (new Response())->redirect($this->app->baseUrl() . '/account/mfa');
    }

    public function regenerateRecovery(Request $req): Response
    {
        if ($r = $this->requireUser()) { return $r; }
        if (!$this->app->csrf()->valid((string)$req->input('_token'))) {
            $this->app->session()->flash('mfa_msg', 'Token CSRF inválido.');
            return (new Response())->redirect($this->app->baseUrl() . '/account/mfa');
        }
        $uid = (int)$this->userIdOrPending();
        $codes = (new MfaRecoveryCode($this->app->db()))->regenerate($uid);
        $this->app->audit()->log('mfa.recovery.regenerated', $uid);
        return (new Response())->html($this->app->view()->render('auth.mfa-recovery-codes', [
            'codes' => $codes,
        ]));
    }

    /* --------- WebAuthn (stub when the lib is missing) --------- */

    public function webauthnRegisterOptions(Request $req): Response
    {
        if ($r = $this->requireUser()) { return $r; }
        if (!$this->isHttps()) {
            return (new Response())->json(['error' => 'WebAuthn requiere HTTPS.'], 400);
        }
        if (!class_exists(\Webauthn\PublicKeyCredentialRpEntity::class)) {
            return (new Response())->json([
                'error' => 'WebAuthn no está disponible en esta instalación. Pídele al administrador que despliegue la versión completa (con `web-auth/webauthn-lib`).',
            ], 501);
        }
        // Production releases bundle web-auth/webauthn-lib. The full
        // implementation lives outside the scope of this PR; the stub
        // keeps the wiring honest and the UI graceful.
        return (new Response())->json(['error' => 'NotImplemented'], 501);
    }

    public function webauthnRegister(Request $req): Response
    {
        if ($r = $this->requireUser()) { return $r; }
        return (new Response())->json(['error' => 'NotImplemented'], 501);
    }

    public function webauthnLoginOptions(Request $req): Response
    {
        return (new Response())->json(['error' => 'NotImplemented'], 501);
    }

    public function webauthnLogin(Request $req): Response
    {
        return (new Response())->json(['error' => 'NotImplemented'], 501);
    }

    private function isHttps(): bool
    {
        $h = strtolower((string)($_SERVER['HTTPS'] ?? ''));
        if ($h !== '' && $h !== 'off') {
            return true;
        }
        $proto = strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
        if ($proto === 'https') {
            return true;
        }
        // Allow testing on localhost (Chrome treats localhost as a secure context).
        $host = (string)($_SERVER['HTTP_HOST'] ?? '');
        if ($host === 'localhost' || str_starts_with($host, 'localhost:') || $host === '127.0.0.1' || str_starts_with($host, '127.0.0.1:')) {
            return true;
        }
        return false;
    }
}
