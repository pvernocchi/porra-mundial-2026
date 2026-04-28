<?php
declare(strict_types=1);

namespace App\Modules\Auth;

use App\Core\Application;
use App\Core\Password;
use App\Core\Request;
use App\Core\Response;
use App\Models\Invitation;
use App\Models\User;

/**
 * Public-facing endpoints for accepting invitations.
 *
 * GET  /invite/{token}   -> show form
 * POST /invite/{token}   -> create user + log them in
 */
final class InviteController
{
    public function __construct(private Application $app)
    {
    }

    public function show(Request $req, array $params): Response
    {
        $token = (string)($params['token'] ?? '');
        $inv = (new Invitation($this->app->db()))->findValidByToken($token);
        if ($inv === null) {
            return (new Response())->html($this->layout('Invitación', $this->invalidView()), 410);
        }
        return (new Response())->html($this->layout('Invitación', $this->formView($inv, [], $token, '')));
    }

    public function submit(Request $req, array $params): Response
    {
        if (!$this->app->csrf()->valid((string)$req->input('_token'))) {
            return (new Response())->html($this->layout('Invitación', '<div class="alert alert-danger">Token CSRF inválido</div>'), 400);
        }
        $token = (string)($params['token'] ?? '');
        $invModel = new Invitation($this->app->db());
        $inv = $invModel->findValidByToken($token);
        if ($inv === null) {
            return (new Response())->html($this->layout('Invitación', $this->invalidView()), 410);
        }

        // Captcha (if enabled)
        $captcha = $this->app->captcha();
        if ($captcha->isEnabled()) {
            $captchaToken = (string)($req->input('g-recaptcha-response')
                ?? $req->input('cf-turnstile-response')
                ?? $req->input('captcha_token', ''));
            if (!$captcha->verify($captchaToken, $req->ip())) {
                return (new Response())->html(
                    $this->layout('Invitación', $this->formView($inv, ['Captcha inválido.'], $token, (string)$req->input('full_name', ''))),
                    400
                );
            }
        }

        $fullName = trim((string)$req->input('full_name', $inv['full_name']));
        $pass     = (string)$req->input('password', '');
        $pass2    = (string)$req->input('password_confirm', '');

        $errors = [];
        if ($fullName === '') {
            $errors[] = 'El nombre es obligatorio.';
        }
        if ($pass !== $pass2) {
            $errors[] = 'Las contraseñas no coinciden.';
        }
        $errors = array_merge($errors, Password::validate($pass, $this->app->path('data/common-passwords.txt')));

        $userModel = new User($this->app->db());
        if ($userModel->emailExists((string)$inv['email'])) {
            $errors[] = 'Ya existe una cuenta con ese email. Pide al administrador que reenvíe la invitación.';
        }

        if ($errors !== []) {
            return (new Response())->html(
                $this->layout('Invitación', $this->formView($inv, $errors, $token, $fullName)),
                400
            );
        }

        $userId = $userModel->create($fullName, (string)$inv['email'], $pass, (string)$inv['role']);
        $invModel->markUsed((int)$inv['id']);
        $this->app->audit()->log('invite.accepted', $userId, ['email' => $inv['email']]);

        // Auto-login.
        $user = $userModel->find($userId);
        if ($user !== null) {
            $policy = $this->app->auth()->mfaPolicy();
            if ($policy === 'all' || ($policy === 'admins' && in_array($user->role, ['admin', 'account_manager'], true))) {
                // Stash login state to require MFA enrollment immediately.
                $this->app->session()->set('_pending_enroll_user_id', $user->id);
                return (new Response())->redirect($this->app->baseUrl() . '/account/mfa/enroll');
            }
            $this->app->auth()->finaliseLogin($user, mfaUsed: false);
        }
        return (new Response())->redirect($this->app->baseUrl() . '/account');
    }

    private function layout(string $title, string $body): string
    {
        $base = htmlspecialchars($this->app->baseUrl(), ENT_QUOTES);
        return <<<HTML
<!doctype html><html lang="es"><head><meta charset="utf-8">
<title>{$title}</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="{$base}/assets/css/app.css"></head>
<body class="install"><main class="container narrow"><h1>{$title}</h1>{$body}</main></body></html>
HTML;
    }

    /**
     * @param array<string, mixed> $inv
     * @param array<int, string>   $errors
     */
    private function formView(array $inv, array $errors, string $token, string $fullName = ''): string
    {
        $tok = $this->app->csrf()->field();
        $email = htmlspecialchars((string)$inv['email'], ENT_QUOTES);
        $name  = htmlspecialchars($fullName !== '' ? $fullName : (string)$inv['full_name'], ENT_QUOTES);
        $action = htmlspecialchars($this->app->baseUrl() . '/invite/' . $token, ENT_QUOTES);
        $expiresAt = htmlspecialchars((string)$inv['expires_at'], ENT_QUOTES);

        $errBlock = '';
        if ($errors) {
            $errBlock = '<div class="alert alert-danger"><ul>';
            foreach ($errors as $e) { $errBlock .= '<li>' . htmlspecialchars($e, ENT_QUOTES) . '</li>'; }
            $errBlock .= '</ul></div>';
        }

        $captcha = $this->app->captcha();
        $cap = $captcha->isEnabled() ? $captcha->widgetHtml('invite') : '';

        return <<<HTML
<p>Has sido invitado a unirte. Esta invitación expira el <code>{$expiresAt}</code> (UTC).</p>
{$errBlock}
<form method="post" action="{$action}">{$tok}
  <label>Email <input type="email" value="{$email}" disabled></label>
  <label>Nombre completo <input type="text" name="full_name" value="{$name}" required></label>
  <label>Contraseña <input type="password" name="password" autocomplete="new-password" required minlength="8"></label>
  <label>Confirmar contraseña <input type="password" name="password_confirm" autocomplete="new-password" required minlength="8"></label>
  <p class="muted"><small>Debe tener al menos 8 caracteres y combinar al menos 3 de: minúsculas, mayúsculas, dígitos, símbolos.</small></p>
  {$cap}
  <button class="btn btn-primary" type="submit">Crear mi cuenta</button>
</form>
HTML;
    }

    private function invalidView(): string
    {
        return '<div class="alert alert-danger">El enlace de invitación no es válido o ha caducado.</div>'
            . '<p>Pide al administrador que te reenvíe una nueva invitación.</p>';
    }
}
