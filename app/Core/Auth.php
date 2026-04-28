<?php
declare(strict_types=1);

namespace App\Core;

use App\Models\User;
use App\Models\MfaCredential;

/**
 * Authentication service. Encapsulates login state, MFA gating and the
 * "fresh MFA" requirement for sensitive admin actions.
 *
 * Login flow:
 *   1. attemptCredentials($email, $password) — verifies password.
 *      On success and if the user has MFA credentials, sets the
 *      `_pending_mfa_user_id` session key and returns 'mfa_required'.
 *      Otherwise, completes the login and returns 'ok'.
 *   2. completeMfa($userId, $type, $payload) — verifies the second
 *      factor and finalises login.
 */
final class Auth
{
    public function __construct(private Application $app)
    {
    }

    public function user(): ?User
    {
        $id = (int)$this->app->session()->get('_user_id', 0);
        if ($id <= 0) {
            return null;
        }
        return (new User($this->app->db()))->find($id);
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function isAdmin(): bool
    {
        $u = $this->user();
        return $u !== null && $u->role === 'admin';
    }

    public function loggedInAt(): int
    {
        return (int)$this->app->session()->get('_user_login_at', 0);
    }

    public function lastMfaAt(): int
    {
        return (int)$this->app->session()->get('_user_mfa_at', 0);
    }

    /**
     * Step 1: verify password. Applies rate limit by IP+email.
     *
     * @return array{status:string, user?:User, message?:string, retryAfter?:int}
     */
    public function attemptCredentials(string $email, string $password, string $ip): array
    {
        $email = strtolower(trim($email));
        $key   = 'login:' . sha1($ip . '|' . $email);
        $maxAttempts = (int)($this->app->settings()->get('security.login.max_attempts', 10));
        $window      = (int)($this->app->settings()->get('security.login.window_seconds', 900));
        $hit = $this->app->rateLimit()->hit($key, $maxAttempts, $window);
        if (!$hit['allowed']) {
            $this->app->audit()->log('login.rate_limited', null, ['email' => $email]);
            return ['status' => 'rate_limited', 'retryAfter' => $hit['retryAfter']];
        }

        $userModel = new User($this->app->db());
        $user = $userModel->findByEmail($email);

        if ($user === null || $user->status !== 'active' || !Password::verify($password, $user->passwordHash)) {
            $this->app->audit()->log('login.failed', $user?->id, ['email' => $email]);
            return ['status' => 'invalid', 'message' => 'Email o contraseña incorrectos.'];
        }

        // Optional rehash if algorithm changed.
        if (Password::needsRehash($user->passwordHash)) {
            $userModel->setPassword($user->id, $password);
        }

        $hasMfa = (new MfaCredential($this->app->db()))->countForUser($user->id) > 0;
        if ($hasMfa) {
            $this->app->session()->set('_pending_mfa_user_id', $user->id);
            $this->app->session()->set('_pending_mfa_at', time());
            $this->app->audit()->log('login.password_ok_mfa_required', $user->id, ['email' => $email]);
            return ['status' => 'mfa_required', 'user' => $user];
        }

        // No MFA registered — but global policy may still require it.
        $policy = $this->mfaPolicy();
        if ($policy === 'all' || ($policy === 'admins' && $user->role === 'admin')) {
            // Force the user to register MFA before fully logging in.
            $this->app->session()->set('_pending_enroll_user_id', $user->id);
            $this->app->audit()->log('login.password_ok_enrollment_required', $user->id);
            return ['status' => 'enrollment_required', 'user' => $user];
        }

        $this->finaliseLogin($user, mfaUsed: false);
        return ['status' => 'ok', 'user' => $user];
    }

    public function pendingMfaUserId(): ?int
    {
        $id = (int)$this->app->session()->get('_pending_mfa_user_id', 0);
        return $id > 0 ? $id : null;
    }

    public function pendingEnrollmentUserId(): ?int
    {
        $id = (int)$this->app->session()->get('_pending_enroll_user_id', 0);
        return $id > 0 ? $id : null;
    }

    public function clearPendingMfa(): void
    {
        $this->app->session()->forget('_pending_mfa_user_id');
        $this->app->session()->forget('_pending_mfa_at');
        $this->app->session()->forget('_pending_enroll_user_id');
    }

    public function finaliseLogin(User $user, bool $mfaUsed): void
    {
        $sess = $this->app->session();
        $sess->regenerate();
        $sess->set('_user_id', $user->id);
        $sess->set('_user_login_at', time());
        if ($mfaUsed) {
            $sess->set('_user_mfa_at', time());
        }
        $this->clearPendingMfa();
        (new User($this->app->db()))->touchLogin($user->id);
        $this->app->audit()->log('login.success', $user->id, ['mfa' => $mfaUsed]);
    }

    public function logout(): void
    {
        $u = $this->user();
        $this->app->audit()->log('logout', $u?->id);
        $this->app->session()->destroy();
    }

    public function requireFreshMfa(int $maxAgeSeconds = 600): bool
    {
        if (!$this->isAdmin()) {
            return true;
        }
        if (!(bool)$this->app->settings()->get('security.mfa.fresh_required_for_admin', true)) {
            return true;
        }
        if ((new MfaCredential($this->app->db()))->countForUser($this->user()->id) === 0) {
            return true; // user has no MFA, nothing to re-verify
        }
        $age = time() - $this->lastMfaAt();
        return $this->lastMfaAt() > 0 && $age <= $maxAgeSeconds;
    }

    public function mfaPolicy(): string
    {
        $p = (string)$this->app->settings()->get('security.mfa.policy', 'optional');
        return in_array($p, ['optional', 'admins', 'all'], true) ? $p : 'optional';
    }
}
