<?php
declare(strict_types=1);

namespace App\Modules\Admin;

use App\Core\Application;
use App\Core\Password;
use App\Core\Request;
use App\Core\Response;
use App\Models\Invitation;
use App\Models\MfaCredential;
use App\Models\User;

/**
 * Admin · users management.
 *
 *  GET  /admin/users                     -> list with search & filters
 *  GET  /admin/users/invite              -> invite form
 *  POST /admin/users/invite              -> create invitation, send email
 *  POST /admin/users/invitations/{id}/resend
 *  POST /admin/users/invitations/{id}/revoke
 *  GET  /admin/users/{id}                -> edit
 *  POST /admin/users/{id}                -> save edit
 *  POST /admin/users/{id}/password       -> change password
 *  POST /admin/users/{id}/mfa-reset      -> remove all MFA credentials
 *  POST /admin/users/{id}/delete         -> soft-delete
 */
final class UserController
{
    public function __construct(private Application $app)
    {
    }

    public function index(Request $req): Response
    {
        $search = trim((string)$req->query('q', ''));
        $role   = (string)$req->query('role', '');
        $status = (string)$req->query('status', '');
        $page   = max(1, (int)$req->query('page', 1));
        $perPage = 25;

        [$users, $total] = (new User($this->app->db()))->paginate(
            $search,
            $role !== '' ? $role : null,
            $status !== '' ? $status : null,
            $page,
            $perPage
        );

        $invitations = (new Invitation($this->app->db()))->listPending();
        $msg = $this->app->session()->flash('admin_msg');

        // Annotate each user with MFA info.
        $mfa = new MfaCredential($this->app->db());
        $userMfa = [];
        foreach ($users as $u) {
            $userMfa[$u->id] = $mfa->listForUser($u->id);
        }

        return (new Response())->html($this->app->view()->render('admin.users-index', [
            'users'       => $users,
            'userMfa'     => $userMfa,
            'total'       => $total,
            'page'        => $page,
            'perPage'     => $perPage,
            'search'      => $search,
            'role'        => $role,
            'status'      => $status,
            'invitations' => $invitations,
            'msg'         => $msg,
        ]));
    }

    /* ------- invitations ------- */

    public function inviteForm(Request $req): Response
    {
        return (new Response())->html($this->app->view()->render('admin.users-invite', [
            'errors'   => [],
            'fullName' => '',
            'email'    => '',
            'role'     => 'user',
        ]));
    }

    public function inviteSubmit(Request $req): Response
    {
        if (!$this->app->csrf()->valid((string)$req->input('_token'))) {
            $this->app->session()->flash('admin_msg', 'Token CSRF inválido.');
            return (new Response())->redirect($this->app->baseUrl() . '/admin/users');
        }
        $fullName = trim((string)$req->input('full_name', ''));
        $email    = strtolower(trim((string)$req->input('email', '')));
        $role     = (string)$req->input('role', 'user');

        $errors = [];
        if ($fullName === '') { $errors[] = 'El nombre es obligatorio.'; }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $errors[] = 'Email inválido.'; }
        if (!in_array($role, ['user', 'admin', 'account_manager'], true)) { $role = 'user'; }
        // Account managers cannot assign admin or account_manager roles.
        if ($this->app->auth()->isAccountManager() && $role !== 'user') { $role = 'user'; }
        if ((new User($this->app->db()))->emailExistsIncludingDeleted($email)) {
            $errors[] = 'Ya existe un usuario con ese email. Revisa esa cuenta antes de reenviar la invitación.';
        }
        if ($errors !== []) {
            return (new Response())->html($this->app->view()->render('admin.users-invite', [
                'errors'   => $errors,
                'fullName' => $fullName,
                'email'    => $email,
                'role'     => $role,
            ]), 400);
        }

        $admin = $this->app->auth()->user();
        $inv = (new Invitation($this->app->db()))->create($fullName, $email, $role, $admin?->id ?? 0);
        $this->sendInvitation($email, $fullName, $inv['token']);
        $this->app->audit()->log('invite.created', $admin?->id, ['email' => $email, 'role' => $role]);

        $this->app->session()->flash('admin_msg', 'Invitación enviada a ' . $email . '.');
        return (new Response())->redirect($this->app->baseUrl() . '/admin/users');
    }

    public function inviteResend(Request $req, array $params): Response
    {
        if (!$this->app->csrf()->valid((string)$req->input('_token'))) {
            $this->app->session()->flash('admin_msg', 'Token CSRF inválido.');
            return (new Response())->redirect($this->app->baseUrl() . '/admin/users');
        }
        $id = (int)($params['id'] ?? 0);
        $invModel = new Invitation($this->app->db());
        $row = $this->app->db()->fetch('SELECT * FROM {prefix:invitations} WHERE id = :i', ['i' => $id]);
        if ($row === null) {
            $this->app->session()->flash('admin_msg', 'Invitación no encontrada.');
            return (new Response())->redirect($this->app->baseUrl() . '/admin/users');
        }
        $admin = $this->app->auth()->user();
        $new = $invModel->create((string)$row['full_name'], (string)$row['email'], (string)$row['role'], $admin?->id ?? 0);
        $this->sendInvitation((string)$row['email'], (string)$row['full_name'], $new['token']);
        $this->app->audit()->log('invite.resent', $admin?->id, ['email' => $row['email']]);
        $this->app->session()->flash('admin_msg', 'Invitación reenviada (nueva validez 48h).');
        return (new Response())->redirect($this->app->baseUrl() . '/admin/users');
    }

    public function inviteRevoke(Request $req, array $params): Response
    {
        if (!$this->app->csrf()->valid((string)$req->input('_token'))) {
            $this->app->session()->flash('admin_msg', 'Token CSRF inválido.');
            return (new Response())->redirect($this->app->baseUrl() . '/admin/users');
        }
        $id = (int)($params['id'] ?? 0);
        $this->app->db()->run(
            'UPDATE {prefix:invitations} SET revoked_at = :now WHERE id = :i AND used_at IS NULL AND revoked_at IS NULL',
            ['now' => gmdate('Y-m-d H:i:s'), 'i' => $id]
        );
        $admin = $this->app->auth()->user();
        $this->app->audit()->log('invite.revoked', $admin?->id, ['invitation_id' => $id]);
        $this->app->session()->flash('admin_msg', 'Invitación revocada.');
        return (new Response())->redirect($this->app->baseUrl() . '/admin/users');
    }

    /* ------- edit / delete ------- */

    public function edit(Request $req, array $params): Response
    {
        $id = (int)($params['id'] ?? 0);
        $user = (new User($this->app->db()))->find($id);
        if ($user === null) {
            return (new Response())->html('<h1>404</h1>', 404);
        }
        $mfa = (new MfaCredential($this->app->db()))->listForUser($user->id);
        $audit = $this->app->audit()->recentForUser($user->id, 30);
        $msg = $this->app->session()->flash('admin_msg');
        return (new Response())->html($this->app->view()->render('admin.users-edit', [
            'user' => $user, 'mfa' => $mfa, 'audit' => $audit, 'msg' => $msg, 'errors' => [],
        ]));
    }

    public function update(Request $req, array $params): Response
    {
        if (!$this->app->csrf()->valid((string)$req->input('_token'))) {
            $this->app->session()->flash('admin_msg', 'Token CSRF inválido.');
            return (new Response())->redirect($this->app->baseUrl() . '/admin/users');
        }
        $id = (int)($params['id'] ?? 0);
        $userModel = new User($this->app->db());
        $user = $userModel->find($id);
        if ($user === null) {
            return (new Response())->html('<h1>404</h1>', 404);
        }
        $fullName = trim((string)$req->input('full_name', ''));
        $teamName = trim((string)$req->input('team_name', ''));
        $role     = (string)$req->input('role', 'user');
        $status   = (string)$req->input('status', 'active');

        $errors = [];
        if ($fullName === '') { $errors[] = 'El nombre es obligatorio.'; }

        // Account managers cannot edit admin or other account_manager users.
        $isAccountManager = $this->app->auth()->isAccountManager();
        if ($isAccountManager && in_array($user->role, ['admin', 'account_manager'], true)) {
            $errors[] = 'No tienes permiso para modificar este usuario.';
        }
        // Account managers cannot assign admin or account_manager roles.
        if ($isAccountManager && in_array($role, ['admin', 'account_manager'], true)) {
            $role = 'user';
        }

        // Don't allow demoting the last active admin.
        if ($user->role === 'admin' && $role !== 'admin') {
            if ($userModel->adminCount() <= 1) {
                $errors[] = 'No puedes quitar el rol al último administrador activo.';
            }
        }
        if ($user->role === 'admin' && $status === 'disabled' && $userModel->adminCount() <= 1) {
            $errors[] = 'No puedes desactivar al último administrador activo.';
        }
        // Also forbid self-demotion / self-disable to avoid lockouts.
        $me = $this->app->auth()->user();
        if ($me !== null && $me->id === $user->id) {
            if ($me->role === 'admin' && $role !== 'admin') {
                $errors[] = 'No puedes quitarte el rol de administrador a ti mismo.';
            }
            if ($me->role === 'account_manager' && $role !== 'account_manager') {
                $errors[] = 'No puedes quitarte el rol de gestor de cuentas a ti mismo.';
            }
            if ($status === 'disabled') {
                $errors[] = 'No puedes desactivar tu propia cuenta.';
            }
        }

        if ($errors !== []) {
            $mfa   = (new MfaCredential($this->app->db()))->listForUser($user->id);
            $audit = $this->app->audit()->recentForUser($user->id, 30);
            return (new Response())->html($this->app->view()->render('admin.users-edit', [
                'user' => $user, 'mfa' => $mfa, 'audit' => $audit, 'msg' => null, 'errors' => $errors,
            ]), 400);
        }

        $userModel->updateProfile($id, $fullName, $role, $status, $teamName);
        $this->app->audit()->log('user.updated', $me?->id, [
            'target' => $id, 'role' => $role, 'status' => $status,
        ]);
        $this->app->session()->flash('admin_msg', 'Usuario actualizado.');
        return (new Response())->redirect($this->app->baseUrl() . '/admin/users/' . $id);
    }

    public function changePassword(Request $req, array $params): Response
    {
        if (!$this->app->csrf()->valid((string)$req->input('_token'))) {
            $this->app->session()->flash('admin_msg', 'Token CSRF inválido.');
            return (new Response())->redirect($this->app->baseUrl() . '/admin/users');
        }
        $id = (int)($params['id'] ?? 0);
        $userModel = new User($this->app->db());
        $user = $userModel->find($id);
        if ($user === null) {
            return (new Response())->html('<h1>404</h1>', 404);
        }
        // Account managers cannot change passwords of admin or account_manager users.
        if ($this->app->auth()->isAccountManager() && in_array($user->role, ['admin', 'account_manager'], true)) {
            $this->app->session()->flash('admin_msg', 'No tienes permiso para modificar este usuario.');
            return (new Response())->redirect($this->app->baseUrl() . '/admin/users/' . $id);
        }
        $pw = (string)$req->input('password', '');
        $errors = Password::validate($pw, $this->app->path('data/common-passwords.txt'));
        if ($errors !== []) {
            $this->app->session()->flash('admin_msg', implode(' ', $errors));
            return (new Response())->redirect($this->app->baseUrl() . '/admin/users/' . $id);
        }
        $userModel->setPassword($id, $pw);
        $me = $this->app->auth()->user();
        $this->app->audit()->log('user.password_changed_by_admin', $me?->id, ['target' => $id]);
        $this->app->session()->flash('admin_msg', 'Contraseña actualizada.');
        return (new Response())->redirect($this->app->baseUrl() . '/admin/users/' . $id);
    }

    public function resetMfa(Request $req, array $params): Response
    {
        if (!$this->app->csrf()->valid((string)$req->input('_token'))) {
            $this->app->session()->flash('admin_msg', 'Token CSRF inválido.');
            return (new Response())->redirect($this->app->baseUrl() . '/admin/users');
        }
        $id = (int)($params['id'] ?? 0);
        // Account managers cannot reset MFA for admin or account_manager users.
        if ($this->app->auth()->isAccountManager()) {
            $target = (new User($this->app->db()))->find($id);
            if ($target !== null && in_array($target->role, ['admin', 'account_manager'], true)) {
                $this->app->session()->flash('admin_msg', 'No tienes permiso para modificar este usuario.');
                return (new Response())->redirect($this->app->baseUrl() . '/admin/users/' . $id);
            }
        }
        $count = (new MfaCredential($this->app->db()))->deleteAllForUser($id);
        $me = $this->app->auth()->user();
        $this->app->audit()->log('user.mfa_reset', $me?->id, ['target' => $id, 'count' => $count]);
        $this->app->session()->flash('admin_msg', $count . ' método(s) MFA eliminados.');
        return (new Response())->redirect($this->app->baseUrl() . '/admin/users/' . $id);
    }

    public function delete(Request $req, array $params): Response
    {
        if (!$this->app->csrf()->valid((string)$req->input('_token'))) {
            $this->app->session()->flash('admin_msg', 'Token CSRF inválido.');
            return (new Response())->redirect($this->app->baseUrl() . '/admin/users');
        }
        $id = (int)($params['id'] ?? 0);
        $userModel = new User($this->app->db());
        $user = $userModel->find($id);
        if ($user === null) {
            return (new Response())->html('<h1>404</h1>', 404);
        }
        $me = $this->app->auth()->user();
        // Account managers cannot delete admin or account_manager users.
        if ($this->app->auth()->isAccountManager() && in_array($user->role, ['admin', 'account_manager'], true)) {
            $this->app->session()->flash('admin_msg', 'No tienes permiso para eliminar este usuario.');
            return (new Response())->redirect($this->app->baseUrl() . '/admin/users/' . $id);
        }
        if ($me !== null && $me->id === $user->id) {
            $this->app->session()->flash('admin_msg', 'No puedes eliminar tu propia cuenta.');
            return (new Response())->redirect($this->app->baseUrl() . '/admin/users/' . $id);
        }
        if ($user->role === 'admin' && $userModel->adminCount() <= 1) {
            $this->app->session()->flash('admin_msg', 'No puedes eliminar al último administrador.');
            return (new Response())->redirect($this->app->baseUrl() . '/admin/users/' . $id);
        }
        $userModel->softDelete($id);
        $this->app->audit()->log('user.deleted', $me?->id, ['target' => $id]);
        $this->app->session()->flash('admin_msg', 'Usuario eliminado.');
        return (new Response())->redirect($this->app->baseUrl() . '/admin/users');
    }

    /* ------- helpers ------- */

    private function sendInvitation(string $email, string $fullName, string $token): void
    {
        $url = $this->app->baseUrl() . '/invite/' . $token;
        $expires = gmdate('Y-m-d H:i', time() + Invitation::VALIDITY_HOURS * 3600);
        $siteName = (string)$this->app->config()->get('site.name', 'Porra Mundial 2026');

        $html = $this->app->view()->render('admin.emails.invitation', [
            'fullName' => $fullName,
            'url'      => $url,
            'expires'  => $expires,
            'siteName' => $siteName,
        ]);
        $subject = '[' . $siteName . '] Invitación para crear tu cuenta';
        $this->app->mail()->send($email, $subject, $html);
    }
}
