<?php
declare(strict_types=1);

namespace App\Modules\Admin;

use App\Core\Application;
use App\Core\Request;
use App\Core\Response;

/**
 * Admin · Communications. SMTP configuration + test email.
 */
final class CommunicationController
{
    public function __construct(private Application $app)
    {
    }

    public function smtp(Request $req): Response
    {
        $cfg = $this->app->mail()->smtpConfig();
        // Don't expose the actual password back into the form; use a marker.
        $cfg['password'] = $cfg['password'] !== '' ? '__keep__' : '';
        $msg = $this->app->session()->flash('comm_msg');
        return (new Response())->html($this->app->view()->render('admin.communications-smtp', [
            'cfg' => $cfg, 'msg' => $msg, 'errors' => [],
        ]));
    }

    public function smtpSubmit(Request $req): Response
    {
        if (!$this->app->csrf()->valid((string)$req->input('_token'))) {
            $this->app->session()->flash('comm_msg', 'Token CSRF inválido.');
            return (new Response())->redirect($this->app->baseUrl() . '/admin/communications/smtp');
        }
        $errors = [];
        $current = (array)$this->app->settings()->get('mail.smtp', []);

        $host = trim((string)$req->input('host', ''));
        $port = (int)$req->input('port', 587);
        $enc  = (string)$req->input('encryption', 'tls');
        $auth = (bool)$req->input('auth', false);
        $user = trim((string)$req->input('username', ''));
        $passInput = (string)$req->input('password', '');
        $from  = trim((string)$req->input('from_email', ''));
        $fromName = trim((string)$req->input('from_name', ''));
        $reply = trim((string)$req->input('reply_to', ''));

        if ($host === '') { $errors[] = 'El host SMTP es obligatorio.'; }
        if ($port < 1 || $port > 65535) { $errors[] = 'Puerto inválido.'; }
        if (!in_array($enc, ['none', 'ssl', 'tls'], true)) { $errors[] = 'Cifrado inválido.'; }
        if ($from !== '' && !filter_var($from, FILTER_VALIDATE_EMAIL)) { $errors[] = 'Email remitente inválido.'; }
        if ($reply !== '' && !filter_var($reply, FILTER_VALIDATE_EMAIL)) { $errors[] = 'Email reply-to inválido.'; }

        if ($errors !== []) {
            $cfg = compact('host', 'port', 'enc', 'auth', 'user', 'from', 'fromName', 'reply');
            return (new Response())->html($this->app->view()->render('admin.communications-smtp', [
                'cfg' => [
                    'host' => $host, 'port' => $port, 'encryption' => $enc, 'auth' => $auth,
                    'username' => $user, 'password' => $passInput === '' ? '__keep__' : '',
                    'from_email' => $from, 'from_name' => $fromName, 'reply_to' => $reply,
                ],
                'msg' => null,
                'errors' => $errors,
            ]), 400);
        }

        // Decide whether to keep the existing password.
        if ($passInput === '__keep__' || ($passInput === '' && !empty($current['password_enc']))) {
            $passwordEnc = (string)($current['password_enc'] ?? '');
        } else {
            $passwordEnc = $this->app->mail()->encryptPassword($passInput);
        }

        $this->app->settings()->set('mail.smtp', [
            'host' => $host, 'port' => $port, 'encryption' => $enc, 'auth' => $auth,
            'username' => $user, 'password_enc' => $passwordEnc,
            'from_email' => $from, 'from_name' => $fromName, 'reply_to' => $reply,
        ]);
        $me = $this->app->auth()->user();
        $this->app->audit()->log('settings.smtp_updated', $me?->id);
        $this->app->session()->flash('comm_msg', 'Configuración SMTP guardada.');
        return (new Response())->redirect($this->app->baseUrl() . '/admin/communications/smtp');
    }

    public function smtpTest(Request $req): Response
    {
        if (!$this->app->csrf()->valid((string)$req->input('_token'))) {
            $this->app->session()->flash('comm_msg', 'Token CSRF inválido.');
            return (new Response())->redirect($this->app->baseUrl() . '/admin/communications/smtp');
        }
        $to = trim((string)$req->input('test_to', ''));
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $this->app->session()->flash('comm_msg', 'Email destino inválido.');
            return (new Response())->redirect($this->app->baseUrl() . '/admin/communications/smtp');
        }
        $ok = $this->app->mail()->send(
            $to,
            'Test de SMTP — ' . (string)$this->app->config()->get('site.name'),
            '<p>Este es un correo de prueba enviado desde el panel de administración.</p>'
            . '<p>Si lo recibes, el SMTP está bien configurado.</p>'
        );
        $msg = $ok
            ? 'Email de prueba enviado a ' . $to . '. Si no llega, revisa la carpeta de spam.'
            : 'Falló el envío: ' . $this->app->mail()->lastError();
        $this->app->session()->flash('comm_msg', $msg);
        return (new Response())->redirect($this->app->baseUrl() . '/admin/communications/smtp');
    }
}
