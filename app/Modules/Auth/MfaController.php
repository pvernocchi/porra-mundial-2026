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
 *
 * When the library is present the endpoints implement the full
 * FIDO2/WebAuthn registration and authentication ceremonies.
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

    public function skipEnrollment(Request $req): Response
    {
        if (!$this->app->csrf()->valid((string)$req->input('_token'))) {
            return (new Response())->redirect($this->app->baseUrl() . '/account/mfa/enroll');
        }
        $uid = $this->app->auth()->pendingEnrollmentUserId();
        if ($uid === null) {
            // Already logged in or no pending enrollment – redirect to home.
            return (new Response())->redirect($this->app->baseUrl() . '/home');
        }
        $user = (new \App\Models\User($this->app->db()))->find($uid);
        if ($user === null) {
            $this->app->auth()->clearPendingMfa();
            return (new Response())->redirect($this->app->baseUrl() . '/login');
        }
        $this->app->auth()->finaliseLogin($user, mfaUsed: false);
        $this->app->audit()->log('mfa.enrollment.skipped', $uid);
        $target = $this->app->auth()->canManageUsers() ? '/admin' : '/home';
        return (new Response())->redirect($this->app->baseUrl() . $target);
    }

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

    /* --------- WebAuthn --------- */

    /**
     * Check whether the webauthn-lib package is available.
     */
    private function webauthnAvailable(): bool
    {
        return class_exists(\Webauthn\PublicKeyCredentialRpEntity::class);
    }

    /**
     * Derive the Relying Party ID (domain) from the configured site URL.
     */
    private function rpId(): string
    {
        $url = $this->app->baseUrl();
        $host = (string)parse_url($url, PHP_URL_HOST);
        return $host !== '' ? $host : 'localhost';
    }

    /**
     * Derive the origin from the configured site URL.
     */
    private function rpOrigin(): string
    {
        $url = $this->app->baseUrl();
        $parsed = parse_url($url);
        $scheme = $parsed['scheme'] ?? 'https';
        $host   = $parsed['host']   ?? 'localhost';
        $port   = $parsed['port']   ?? null;
        $origin = $scheme . '://' . $host;
        if ($port !== null) {
            // Include port unless it's the default for the scheme.
            if (!(($scheme === 'https' && $port === 443) || ($scheme === 'http' && $port === 80))) {
                $origin .= ':' . $port;
            }
        }
        return $origin;
    }

    /**
     * Read JSON body from the request (for POST endpoints).
     *
     * @return array<string, mixed>|null
     */
    private function jsonBody(): ?array
    {
        $raw = file_get_contents('php://input');
        if ($raw === false || $raw === '') {
            return null;
        }
        $data = json_decode($raw, true);
        return is_array($data) ? $data : null;
    }

    /**
     * GET /account/mfa/webauthn/register-options
     *
     * Generates a WebAuthn PublicKeyCredentialCreationOptions challenge
     * and stores it in the session so the register endpoint can verify it.
     */
    public function webauthnRegisterOptions(Request $req): Response
    {
        if ($r = $this->requireUser()) { return $r; }
        if (!$this->isHttps()) {
            return (new Response())->json(['error' => 'WebAuthn requiere HTTPS.'], 400);
        }
        if (!$this->webauthnAvailable()) {
            return (new Response())->json([
                'error' => 'WebAuthn no está disponible en esta instalación. Pídele al administrador que despliegue la versión completa (con `web-auth/webauthn-lib`).',
            ], 501);
        }

        $uid = (int)$this->userIdOrPending();
        $user = $this->app->auth()->user()
            ?? (new \App\Models\User($this->app->db()))->find($uid);
        if ($user === null) {
            return (new Response())->json(['error' => 'Usuario no encontrado.'], 400);
        }

        $rpName = (string)$this->app->config()->get('site.name', 'Porra Mundial 2026');
        $rpId   = $this->rpId();

        $rpEntity   = \Webauthn\PublicKeyCredentialRpEntity::create($rpName, $rpId);
        $userHandle = hash('sha256', (string)$uid, true);
        $userEntity = \Webauthn\PublicKeyCredentialUserEntity::create(
            $user->email,
            $userHandle,
            $user->fullName !== '' ? $user->fullName : $user->email,
        );

        // Exclude already-registered credentials so the authenticator
        // doesn't re-register.
        $existing = (new MfaCredential($this->app->db()))->listByType($uid, 'webauthn');
        $excludeCredentials = [];
        foreach ($existing as $cred) {
            $rawId = \ParagonIE\ConstantTime\Base64UrlSafe::decodeNoPadding((string)$cred['webauthn_credential_id']);
            $transports = $cred['transports'] !== null && $cred['transports'] !== ''
                ? explode(',', (string)$cred['transports'])
                : [];
            $excludeCredentials[] = \Webauthn\PublicKeyCredentialDescriptor::create(
                \Webauthn\PublicKeyCredentialDescriptor::CREDENTIAL_TYPE_PUBLIC_KEY,
                $rawId,
                $transports,
            );
        }

        $challenge = random_bytes(32);

        $creationOptions = \Webauthn\PublicKeyCredentialCreationOptions::create(
            rp: $rpEntity,
            user: $userEntity,
            challenge: $challenge,
            pubKeyCredParams: [
                \Webauthn\PublicKeyCredentialParameters::createPk(\Cose\Algorithms::COSE_ALGORITHM_ES256),
                \Webauthn\PublicKeyCredentialParameters::createPk(\Cose\Algorithms::COSE_ALGORITHM_RS256),
            ],
            authenticatorSelection: \Webauthn\AuthenticatorSelectionCriteria::create(
                userVerification: \Webauthn\AuthenticatorSelectionCriteria::USER_VERIFICATION_REQUIREMENT_PREFERRED,
                residentKey: \Webauthn\AuthenticatorSelectionCriteria::RESIDENT_KEY_REQUIREMENT_PREFERRED,
            ),
            attestation: \Webauthn\PublicKeyCredentialCreationOptions::ATTESTATION_CONVEYANCE_PREFERENCE_NONE,
            excludeCredentials: $excludeCredentials,
            timeout: 60000,
        );

        // Store challenge + options in session for verification.
        $this->app->session()->set('webauthn_register_challenge', \ParagonIE\ConstantTime\Base64UrlSafe::encodeUnpadded($challenge));
        $this->app->session()->set('webauthn_register_user_id', $uid);

        // Build JSON-safe output (avoiding deprecated jsonSerialize triggers).
        $json = [
            'rp' => ['name' => $rpName, 'id' => $rpId],
            'user' => [
                'id'          => \ParagonIE\ConstantTime\Base64UrlSafe::encodeUnpadded($userHandle),
                'name'        => $user->email,
                'displayName' => $user->fullName !== '' ? $user->fullName : $user->email,
            ],
            'challenge'     => \ParagonIE\ConstantTime\Base64UrlSafe::encodeUnpadded($challenge),
            'pubKeyCredParams' => [
                ['type' => 'public-key', 'alg' => \Cose\Algorithms::COSE_ALGORITHM_ES256],
                ['type' => 'public-key', 'alg' => \Cose\Algorithms::COSE_ALGORITHM_RS256],
            ],
            'timeout' => 60000,
            'attestation' => 'none',
            'authenticatorSelection' => [
                'userVerification' => 'preferred',
                'residentKey'      => 'preferred',
                'requireResidentKey' => false,
            ],
        ];
        if ($excludeCredentials !== []) {
            $excl = [];
            foreach ($excludeCredentials as $ec) {
                $item = [
                    'type' => $ec->type,
                    'id'   => \ParagonIE\ConstantTime\Base64UrlSafe::encodeUnpadded($ec->id),
                ];
                if ($ec->transports !== []) {
                    $item['transports'] = $ec->transports;
                }
                $excl[] = $item;
            }
            $json['excludeCredentials'] = $excl;
        }

        return (new Response())->json($json);
    }

    /**
     * POST /account/mfa/webauthn/register
     *
     * Validates the attestation response from the browser, extracts the
     * credential public key and stores it.
     */
    public function webauthnRegister(Request $req): Response
    {
        if ($r = $this->requireUser()) { return $r; }
        if (!$this->webauthnAvailable()) {
            return (new Response())->json(['error' => 'NotImplemented'], 501);
        }

        $body = $this->jsonBody();
        if ($body === null) {
            return (new Response())->json(['error' => 'Cuerpo JSON inválido.'], 400);
        }

        $challengeB64 = (string)$this->app->session()->get('webauthn_register_challenge', '');
        $expectedUid  = (int)$this->app->session()->get('webauthn_register_user_id', 0);
        if ($challengeB64 === '' || $expectedUid <= 0) {
            return (new Response())->json(['error' => 'Sesión expirada. Inténtalo de nuevo.'], 400);
        }

        $uid = (int)$this->userIdOrPending();
        if ($uid !== $expectedUid) {
            return (new Response())->json(['error' => 'Error de sesión.'], 400);
        }

        // Consume the challenge immediately to prevent replay.
        $this->app->session()->forget('webauthn_register_challenge');
        $this->app->session()->forget('webauthn_register_user_id');

        try {
            $challenge = \ParagonIE\ConstantTime\Base64UrlSafe::decodeNoPadding($challengeB64);

            // Load the credential from the browser response.
            $attestationStatementSupportManager = new \Webauthn\AttestationStatement\AttestationStatementSupportManager([
                new \Webauthn\AttestationStatement\NoneAttestationStatementSupport(),
            ]);
            $attestationObjectLoader = \Webauthn\AttestationStatement\AttestationObjectLoader::create(
                $attestationStatementSupportManager,
            );
            $pkCredentialLoader = \Webauthn\PublicKeyCredentialLoader::create($attestationObjectLoader);

            $publicKeyCredential = $pkCredentialLoader->loadArray($body);
            $response = $publicKeyCredential->response;
            if (!$response instanceof \Webauthn\AuthenticatorAttestationResponse) {
                return (new Response())->json(['error' => 'Respuesta inesperada del autenticador.'], 400);
            }

            // Reconstruct the creation options for validation.
            $rpName = (string)$this->app->config()->get('site.name', 'Porra Mundial 2026');
            $rpId   = $this->rpId();
            $rpEntity   = \Webauthn\PublicKeyCredentialRpEntity::create($rpName, $rpId);
            $userHandle = hash('sha256', (string)$uid, true);

            $user = $this->app->auth()->user()
                ?? (new \App\Models\User($this->app->db()))->find($uid);
            $userEntity = \Webauthn\PublicKeyCredentialUserEntity::create(
                $user->email ?? 'user',
                $userHandle,
                ($user->fullName ?? '') !== '' ? $user->fullName : ($user->email ?? 'user'),
            );

            $creationOptions = \Webauthn\PublicKeyCredentialCreationOptions::create(
                rp: $rpEntity,
                user: $userEntity,
                challenge: $challenge,
                pubKeyCredParams: [
                    \Webauthn\PublicKeyCredentialParameters::createPk(\Cose\Algorithms::COSE_ALGORITHM_ES256),
                    \Webauthn\PublicKeyCredentialParameters::createPk(\Cose\Algorithms::COSE_ALGORITHM_RS256),
                ],
                attestation: \Webauthn\PublicKeyCredentialCreationOptions::ATTESTATION_CONVEYANCE_PREFERENCE_NONE,
            );

            $ceremonyStepManagerFactory = new \Webauthn\CeremonyStep\CeremonyStepManagerFactory();
            $ceremonyStepManagerFactory->setSecuredRelyingPartyId([$rpId]);

            $validator = \Webauthn\AuthenticatorAttestationResponseValidator::create(
                ceremonyStepManager: $ceremonyStepManagerFactory->creationCeremony(),
            );

            $publicKeyCredentialSource = $validator->check(
                $response,
                $creationOptions,
                $this->rpOrigin(),
            );

            // Store credential in the database.
            $credentialId = \ParagonIE\ConstantTime\Base64UrlSafe::encodeUnpadded($publicKeyCredentialSource->publicKeyCredentialId);
            $publicKeyB64 = \ParagonIE\ConstantTime\Base64UrlSafe::encodeUnpadded($publicKeyCredentialSource->credentialPublicKey);
            $signCount    = $publicKeyCredentialSource->counter;
            $aaguid       = $publicKeyCredentialSource->aaguid->toRfc4122();
            $transports   = $publicKeyCredentialSource->transports !== []
                ? implode(',', $publicKeyCredentialSource->transports)
                : null;

            $label = trim((string)($body['label'] ?? ''));

            $credModel = new MfaCredential($this->app->db());
            $credModel->createWebauthn(
                $uid,
                $label !== '' ? $label : 'Llave de seguridad',
                $credentialId,
                $publicKeyB64,
                $signCount,
                $aaguid,
                $transports,
            );

            // Generate recovery codes if this is the first MFA credential.
            $codes = null;
            if ((new MfaRecoveryCode($this->app->db()))->unusedCount($uid) === 0) {
                $codes = (new MfaRecoveryCode($this->app->db()))->regenerate($uid);
            }

            $this->app->audit()->log('mfa.webauthn.added', $uid, ['label' => $label]);

            // If the user was mid-enrollment (forced policy), finalise login.
            if ($this->app->auth()->user() === null) {
                $userObj = (new \App\Models\User($this->app->db()))->find($uid);
                if ($userObj !== null) {
                    $this->app->auth()->finaliseLogin($userObj, mfaUsed: true);
                }
            }

            $result = ['ok' => true];
            if ($codes !== null) {
                $result['recoveryCodes'] = $codes;
            }
            return (new Response())->json($result);

        } catch (\Throwable $e) {
            return (new Response())->json(['error' => 'Registro fallido: ' . $e->getMessage()], 400);
        }
    }

    /**
     * GET /api/webauthn/login-options
     *
     * Generates a PublicKeyCredentialRequestOptions challenge for login.
     * Requires a pending MFA user in the session.
     */
    public function webauthnLoginOptions(Request $req): Response
    {
        if (!$this->webauthnAvailable()) {
            return (new Response())->json(['error' => 'NotImplemented'], 501);
        }

        $uid = $this->app->auth()->pendingMfaUserId();
        if ($uid === null) {
            return (new Response())->json(['error' => 'No hay sesión MFA pendiente.'], 401);
        }

        $rpId = $this->rpId();

        // Retrieve the user's registered WebAuthn credentials.
        $existing = (new MfaCredential($this->app->db()))->listByType($uid, 'webauthn');
        $allowCredentials = [];
        foreach ($existing as $cred) {
            $rawId = \ParagonIE\ConstantTime\Base64UrlSafe::decodeNoPadding((string)$cred['webauthn_credential_id']);
            $transports = $cred['transports'] !== null && $cred['transports'] !== ''
                ? explode(',', (string)$cred['transports'])
                : [];
            $allowCredentials[] = \Webauthn\PublicKeyCredentialDescriptor::create(
                \Webauthn\PublicKeyCredentialDescriptor::CREDENTIAL_TYPE_PUBLIC_KEY,
                $rawId,
                $transports,
            );
        }

        if ($allowCredentials === []) {
            return (new Response())->json(['error' => 'No hay llaves WebAuthn registradas para este usuario.'], 400);
        }

        $challenge = random_bytes(32);

        $this->app->session()->set('webauthn_login_challenge', \ParagonIE\ConstantTime\Base64UrlSafe::encodeUnpadded($challenge));
        $this->app->session()->set('webauthn_login_user_id', $uid);

        $json = [
            'challenge'      => \ParagonIE\ConstantTime\Base64UrlSafe::encodeUnpadded($challenge),
            'rpId'           => $rpId,
            'timeout'        => 60000,
            'userVerification' => 'preferred',
            'allowCredentials' => array_map(function (\Webauthn\PublicKeyCredentialDescriptor $desc) {
                $item = [
                    'type' => $desc->type,
                    'id'   => \ParagonIE\ConstantTime\Base64UrlSafe::encodeUnpadded($desc->id),
                ];
                if ($desc->transports !== []) {
                    $item['transports'] = $desc->transports;
                }
                return $item;
            }, $allowCredentials),
        ];

        return (new Response())->json($json);
    }

    /**
     * POST /api/webauthn/login
     *
     * Validates the assertion response from the browser and finalises
     * the login if verification succeeds.
     */
    public function webauthnLogin(Request $req): Response
    {
        if (!$this->webauthnAvailable()) {
            return (new Response())->json(['error' => 'NotImplemented'], 501);
        }

        $uid = $this->app->auth()->pendingMfaUserId();
        if ($uid === null) {
            return (new Response())->json(['error' => 'No hay sesión MFA pendiente.'], 401);
        }

        // Rate limit
        $rl = $this->app->rateLimit()->hit('mfa:' . $uid, 10, 600);
        if (!$rl['allowed']) {
            return (new Response())->json(['error' => 'Demasiados intentos. Espera ' . $rl['retryAfter'] . 's.'], 429);
        }

        $body = $this->jsonBody();
        if ($body === null) {
            return (new Response())->json(['error' => 'Cuerpo JSON inválido.'], 400);
        }

        $challengeB64 = (string)$this->app->session()->get('webauthn_login_challenge', '');
        $expectedUid  = (int)$this->app->session()->get('webauthn_login_user_id', 0);
        if ($challengeB64 === '' || $expectedUid <= 0 || $uid !== $expectedUid) {
            return (new Response())->json(['error' => 'Sesión expirada. Inténtalo de nuevo.'], 400);
        }

        // Consume the challenge immediately.
        $this->app->session()->forget('webauthn_login_challenge');
        $this->app->session()->forget('webauthn_login_user_id');

        try {
            $challenge = \ParagonIE\ConstantTime\Base64UrlSafe::decodeNoPadding($challengeB64);
            $rpId = $this->rpId();

            // Load credential from browser.
            $attestationStatementSupportManager = new \Webauthn\AttestationStatement\AttestationStatementSupportManager([
                new \Webauthn\AttestationStatement\NoneAttestationStatementSupport(),
            ]);
            $attestationObjectLoader = \Webauthn\AttestationStatement\AttestationObjectLoader::create(
                $attestationStatementSupportManager,
            );
            $pkCredentialLoader = \Webauthn\PublicKeyCredentialLoader::create($attestationObjectLoader);
            $publicKeyCredential = $pkCredentialLoader->loadArray($body);

            $response = $publicKeyCredential->response;
            if (!$response instanceof \Webauthn\AuthenticatorAssertionResponse) {
                return (new Response())->json(['error' => 'Respuesta inesperada del autenticador.'], 400);
            }

            // Look up the stored credential.
            $credentialIdB64 = \ParagonIE\ConstantTime\Base64UrlSafe::encodeUnpadded($publicKeyCredential->rawId);
            $credModel = new MfaCredential($this->app->db());
            $storedCred = $credModel->findByCredentialId($credentialIdB64);
            if ($storedCred === null || (int)$storedCred['user_id'] !== $uid) {
                $this->app->audit()->log('mfa.failed', $uid, ['method' => 'webauthn']);
                return (new Response())->json(['error' => 'Llave de seguridad no reconocida.'], 400);
            }

            // Reconstruct the PublicKeyCredentialSource from stored data.
            $storedPublicKey = \ParagonIE\ConstantTime\Base64UrlSafe::decodeNoPadding((string)$storedCred['webauthn_public_key']);
            $storedCredIdRaw = \ParagonIE\ConstantTime\Base64UrlSafe::decodeNoPadding((string)$storedCred['webauthn_credential_id']);
            $storedTransports = $storedCred['transports'] !== null && $storedCred['transports'] !== ''
                ? explode(',', (string)$storedCred['transports'])
                : [];
            $aaguidStr = (string)($storedCred['webauthn_aaguid'] ?? '00000000-0000-0000-0000-000000000000');
            $aaguid = \Symfony\Component\Uid\Uuid::fromString($aaguidStr);
            $userHandle = hash('sha256', (string)$uid, true);

            $publicKeyCredentialSource = \Webauthn\PublicKeyCredentialSource::create(
                publicKeyCredentialId: $storedCredIdRaw,
                type: \Webauthn\PublicKeyCredentialDescriptor::CREDENTIAL_TYPE_PUBLIC_KEY,
                transports: $storedTransports,
                attestationType: 'none',
                trustPath: new \Webauthn\TrustPath\EmptyTrustPath(),
                aaguid: $aaguid,
                credentialPublicKey: $storedPublicKey,
                userHandle: $userHandle,
                counter: (int)$storedCred['webauthn_sign_count'],
            );

            // Reconstruct request options.
            $existing = $credModel->listByType($uid, 'webauthn');
            $allowCredentials = [];
            foreach ($existing as $cred) {
                $rawId = \ParagonIE\ConstantTime\Base64UrlSafe::decodeNoPadding((string)$cred['webauthn_credential_id']);
                $transports = $cred['transports'] !== null && $cred['transports'] !== ''
                    ? explode(',', (string)$cred['transports'])
                    : [];
                $allowCredentials[] = \Webauthn\PublicKeyCredentialDescriptor::create(
                    \Webauthn\PublicKeyCredentialDescriptor::CREDENTIAL_TYPE_PUBLIC_KEY,
                    $rawId,
                    $transports,
                );
            }

            $requestOptions = \Webauthn\PublicKeyCredentialRequestOptions::create(
                challenge: $challenge,
                rpId: $rpId,
                allowCredentials: $allowCredentials,
                userVerification: \Webauthn\PublicKeyCredentialRequestOptions::USER_VERIFICATION_REQUIREMENT_PREFERRED,
                timeout: 60000,
            );

            $ceremonyStepManagerFactory = new \Webauthn\CeremonyStep\CeremonyStepManagerFactory();
            $ceremonyStepManagerFactory->setSecuredRelyingPartyId([$rpId]);

            $validator = \Webauthn\AuthenticatorAssertionResponseValidator::create(
                ceremonyStepManager: $ceremonyStepManagerFactory->requestCeremony(),
            );

            $updatedSource = $validator->check(
                $publicKeyCredentialSource,
                $response,
                $requestOptions,
                $this->rpOrigin(),
                $userHandle,
            );

            // Update sign count.
            $credModel->setSignCount((int)$storedCred['id'], $updatedSource->counter);

            // Finalise login.
            $userObj = (new \App\Models\User($this->app->db()))->find($uid);
            if ($userObj === null) {
                return (new Response())->json(['error' => 'Usuario no encontrado.'], 400);
            }

            $this->app->auth()->finaliseLogin($userObj, mfaUsed: true);

            return (new Response())->json(['ok' => true, 'redirect' => $this->app->baseUrl() . '/admin']);

        } catch (\Throwable $e) {
            $this->app->audit()->log('mfa.failed', $uid, ['method' => 'webauthn', 'error' => $e->getMessage()]);
            return (new Response())->json(['error' => 'Verificación fallida: ' . $e->getMessage()], 400);
        }
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
