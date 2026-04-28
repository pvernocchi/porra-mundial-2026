<?php
declare(strict_types=1);

namespace App\Modules\Install;

use App\Core\Application;
use App\Core\Crypto;
use App\Core\Installer;
use App\Core\Password;
use App\Core\Request;
use App\Core\Response;
use App\Models\User;

/**
 * Install wizard. Steps:
 *   /install              -> preflight (step 0)
 *   /install/db           -> DB credentials (step 1)
 *   /install/site         -> site name, URL, timezone (step 2)
 *   /install/admin        -> first administrator (step 3)
 *   /install/run          -> writes config, runs migrations, seeds admin
 *   /install/done         -> finish page
 *   /install/upgrade      -> upgrade mode (post-update FTP overwrite)
 *
 * The wizard stores partial data in the session so the user can move
 * back and forth.
 */
final class InstallController
{
    private Application $app;
    private Installer $inst;

    public function __construct(Application $app)
    {
        $this->app  = $app;
        $this->inst = new Installer($app);
    }

    private function ensureNotInstalled(): ?Response
    {
        if (!$this->app->isInstalled()) {
            return null;
        }
        $codeVer = $this->app->version();
        $haveVer = $this->app->installedVersion() ?? '0.0.0';
        if (version_compare($codeVer, $haveVer, '>')) {
            // Upgrade mode is allowed.
            return null;
        }
        $html = $this->layout(
            'Ya instalado',
            '<div class="alert alert-warning">La aplicación ya está instalada (versión '
            . htmlspecialchars($haveVer, ENT_QUOTES) . '). '
            . 'Si necesitas reinstalar, borra <code>config/config.php</code> y '
            . '<code>storage/installed.lock</code> por FTP.</div>'
            . '<a href="' . htmlspecialchars($this->app->baseUrl(), ENT_QUOTES) . '/login" class="btn btn-primary">Ir al login</a>'
        );
        return (new Response())->html($html, 403);
    }

    public function step0(Request $req): Response
    {
        if ($r = $this->ensureNotInstalled()) { return $r; }
        $checks = $this->inst->preflight();
        $allOk = $this->inst->preflightOk();
        return (new Response())->html(
            $this->layout('Comprobaciones del sistema', $this->preflightView($checks, $allOk))
        );
    }

    public function step1Form(Request $req): Response
    {
        if ($r = $this->ensureNotInstalled()) { return $r; }
        $sess = $this->app->session();
        $db = (array)$sess->get('install.db', [
            'host' => 'localhost', 'port' => 3306, 'database' => '',
            'username' => '', 'password' => '', 'prefix' => 'pm_',
        ]);
        return (new Response())->html($this->layout('Base de datos', $this->dbView($db, '')));
    }

    public function step1Submit(Request $req): Response
    {
        if ($r = $this->ensureNotInstalled()) { return $r; }
        if (!$this->app->csrf()->valid((string)$req->input('_token'))) {
            return (new Response())->html($this->layout('Base de datos', '<div class="alert alert-danger">Token CSRF inválido</div>'), 400);
        }
        $db = [
            'driver'   => 'mysql',
            'host'     => trim((string)$req->input('host', 'localhost')),
            'port'     => (int)$req->input('port', 3306),
            'database' => trim((string)$req->input('database', '')),
            'username' => trim((string)$req->input('username', '')),
            'password' => (string)$req->input('password', ''),
            'charset'  => 'utf8mb4',
            'prefix'   => trim((string)$req->input('prefix', 'pm_')),
        ];
        $test = $this->inst->testDbConnection($db);
        if (!$test['ok']) {
            return (new Response())->html(
                $this->layout('Base de datos', $this->dbView($db, $test['error'] ?? 'Error desconocido')),
                400
            );
        }
        $this->app->session()->set('install.db', $db);
        return (new Response())->redirect($this->app->baseUrl() . '/install/site');
    }

    public function step2Form(Request $req): Response
    {
        if ($r = $this->ensureNotInstalled()) { return $r; }
        $site = (array)$this->app->session()->get('install.site', [
            'name'     => 'Porra Mundial 2026',
            'base_url' => $this->app->baseUrl(),
            'timezone' => 'Europe/Madrid',
            'locale'   => 'es',
        ]);
        return (new Response())->html($this->layout('Configuración del sitio', $this->siteView($site)));
    }

    public function step2Submit(Request $req): Response
    {
        if ($r = $this->ensureNotInstalled()) { return $r; }
        if (!$this->app->csrf()->valid((string)$req->input('_token'))) {
            return (new Response())->html($this->layout('Sitio', '<div class="alert alert-danger">Token CSRF inválido</div>'), 400);
        }
        $site = [
            'name'     => trim((string)$req->input('name', 'Porra Mundial 2026')) ?: 'Porra Mundial 2026',
            'base_url' => rtrim(trim((string)$req->input('base_url', $this->app->baseUrl())), '/'),
            'timezone' => trim((string)$req->input('timezone', 'Europe/Madrid')),
            'locale'   => trim((string)$req->input('locale', 'es')),
        ];
        if (@timezone_open($site['timezone']) === false) {
            return (new Response())->html(
                $this->layout('Configuración del sitio', $this->siteView($site, 'Zona horaria inválida.')),
                400
            );
        }
        $this->app->session()->set('install.site', $site);
        return (new Response())->redirect($this->app->baseUrl() . '/install/admin');
    }

    public function step3Form(Request $req): Response
    {
        if ($r = $this->ensureNotInstalled()) { return $r; }
        $admin = (array)$this->app->session()->get('install.admin', ['full_name' => '', 'email' => '']);
        return (new Response())->html($this->layout('Administrador inicial', $this->adminView($admin, [])));
    }

    public function step3Submit(Request $req): Response
    {
        if ($r = $this->ensureNotInstalled()) { return $r; }
        if (!$this->app->csrf()->valid((string)$req->input('_token'))) {
            return (new Response())->html($this->layout('Administrador inicial', '<div class="alert alert-danger">Token CSRF inválido</div>'), 400);
        }
        $full = trim((string)$req->input('full_name', ''));
        $email = strtolower(trim((string)$req->input('email', '')));
        $pass = (string)$req->input('password', '');
        $pass2 = (string)$req->input('password_confirm', '');

        $errors = [];
        if ($full === '') { $errors[] = 'El nombre es obligatorio.'; }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $errors[] = 'Email inválido.'; }
        if ($pass !== $pass2) { $errors[] = 'Las contraseñas no coinciden.'; }
        $errors = array_merge($errors, Password::validate($pass, $this->app->path('data/common-passwords.txt')));

        if ($errors !== []) {
            return (new Response())->html(
                $this->layout('Administrador inicial', $this->adminView(['full_name' => $full, 'email' => $email], $errors)),
                400
            );
        }
        $this->app->session()->set('install.admin', [
            'full_name' => $full,
            'email'     => $email,
            'password'  => $pass,
        ]);
        return (new Response())->redirect($this->app->baseUrl() . '/install/run');
    }

    public function run(Request $req): Response
    {
        if ($r = $this->ensureNotInstalled()) { return $r; }

        $db    = (array)$this->app->session()->get('install.db');
        $site  = (array)$this->app->session()->get('install.site');
        $admin = (array)$this->app->session()->get('install.admin');
        if (!$db || !$site || !$admin) {
            return (new Response())->redirect($this->app->baseUrl() . '/install');
        }
        if ($req->method() === 'GET') {
            return (new Response())->html($this->layout('Instalando', $this->runView()));
        }
        if (!$this->app->csrf()->valid((string)$req->input('_token'))) {
            return (new Response())->html($this->layout('Instalando', '<div class="alert alert-danger">Token CSRF inválido</div>'), 400);
        }

        try {
            // 1. Run migrations against the DB.
            $this->inst->runMigrations($db);

            // 2. Generate APP_KEY.
            $appKey = Crypto::generateKey();

            // 3. Write config/config.php.
            $this->inst->writeConfig([
                'db'      => $db,
                'site'    => $site,
                'app_key' => $appKey,
                'env'     => 'production',
                'session' => ['name' => 'porra_sess', 'lifetime' => 0, 'idle' => 3600],
            ]);

            // 4. Reload config & DB through the application.
            // The simplest way: build a fresh Application for this step.
            $app = new Application($this->app->root());
            // Set timezone now.
            date_default_timezone_set($site['timezone']);

            // 5. Seed admin user.
            (new User($app->db()))->create(
                $admin['full_name'],
                $admin['email'],
                $admin['password'],
                'admin'
            );

            // 6. Seed default settings (login lockout, etc.)
            $app->settings()->set('security.login.max_attempts', 10);
            $app->settings()->set('security.login.window_seconds', 900);
            $app->settings()->set('security.mfa.policy', 'optional');
            $app->settings()->set('security.mfa.fresh_required_for_admin', true);
            $app->settings()->set('security.mfa.remember_days', 0);
            $app->settings()->set('security.captcha', ['provider' => 'none', 'site_key' => '', 'secret_key' => '', 'threshold' => 0.5]);

            // 7. installed.lock
            $this->inst->writeInstalledLock($app->version());

            $app->audit()->log('install.completed', null, [
                'admin_email' => $admin['email'],
                'version'     => $app->version(),
            ]);

            // Clean up wizard state.
            $this->app->session()->forget('install.db');
            $this->app->session()->forget('install.site');
            $this->app->session()->forget('install.admin');

            return (new Response())->redirect($this->app->baseUrl() . '/install/done');
        } catch (\Throwable $e) {
            return (new Response())->html(
                $this->layout('Error de instalación',
                    '<div class="alert alert-danger"><strong>Falló la instalación:</strong> '
                    . htmlspecialchars($e->getMessage(), ENT_QUOTES) . '</div>'
                    . '<a class="btn btn-secondary" href="' . htmlspecialchars($this->app->baseUrl(), ENT_QUOTES) . '/install/db">Volver atrás</a>'
                ),
                500
            );
        }
    }

    public function done(Request $req): Response
    {
        $base = $this->app->baseUrl();
        $html = '<div class="alert alert-success">¡Instalación completada con éxito!</div>'
            . '<p>Por seguridad, te recomendamos:</p>'
            . '<ul>'
            . '<li>Configurar HTTPS en tu hosting (necesario para WebAuthn / Yubikey / Windows Hello).</li>'
            . '<li>Configurar SMTP en <em>Comunicaciones</em> para que los emails de invitación se envíen.</li>'
            . '<li>Activar MFA para tu cuenta de administrador.</li>'
            . '</ul>'
            . '<a class="btn btn-primary" href="' . htmlspecialchars($base, ENT_QUOTES) . '/login">Ir al login</a>';
        return (new Response())->html($this->layout('Instalación completada', $html));
    }

    public function upgrade(Request $req): Response
    {
        $code = $this->app->version();
        $have = $this->app->installedVersion();
        if ($have === null) {
            return (new Response())->redirect($this->app->baseUrl() . '/install');
        }
        if (!version_compare($code, $have, '>')) {
            return (new Response())->html(
                $this->layout('Sin actualizaciones',
                    '<div class="alert alert-info">No hay migraciones pendientes (versión actual: '
                    . htmlspecialchars($have, ENT_QUOTES) . ').</div>'
                    . '<a class="btn btn-primary" href="' . htmlspecialchars($this->app->baseUrl(), ENT_QUOTES) . '/login">Volver</a>'
                )
            );
        }

        // Require an admin to be logged in to apply migrations.
        $auth = $this->app->auth();
        if (!$auth->isAdmin()) {
            return (new Response())->redirect($this->app->baseUrl() . '/login?next=/install/upgrade');
        }

        if ($req->method() === 'GET') {
            $body = '<div class="alert alert-info">Hay una nueva versión: <strong>'
                . htmlspecialchars($code, ENT_QUOTES) . '</strong> (instalada: '
                . htmlspecialchars($have, ENT_QUOTES) . ').</div>'
                . '<form method="post">' . $this->app->csrf()->field()
                . '<button class="btn btn-primary" type="submit">Aplicar actualización</button></form>';
            return (new Response())->html($this->layout('Actualización', $body));
        }

        if (!$this->app->csrf()->valid((string)$req->input('_token'))) {
            return (new Response())->html($this->layout('Actualización', '<div class="alert alert-danger">Token CSRF inválido</div>'), 400);
        }

        try {
            $cfg = (array)$this->app->config()->get('db', []);
            $this->inst->runMigrations($cfg);
            $this->inst->writeInstalledLock($code);
            $this->app->audit()->log('upgrade.applied', $auth->user()?->id, [
                'from' => $have, 'to' => $code,
            ]);
            return (new Response())->html(
                $this->layout('Actualización completada',
                    '<div class="alert alert-success">Aplicación actualizada a la versión '
                    . htmlspecialchars($code, ENT_QUOTES) . '.</div>'
                    . '<a class="btn btn-primary" href="' . htmlspecialchars($this->app->baseUrl(), ENT_QUOTES) . '/admin">Ir al panel</a>'
                )
            );
        } catch (\Throwable $e) {
            return (new Response())->html(
                $this->layout('Error',
                    '<div class="alert alert-danger">No se pudo actualizar: '
                    . htmlspecialchars($e->getMessage(), ENT_QUOTES) . '</div>'
                ),
                500
            );
        }
    }

    /* ---------- inline views (kept as plain strings to avoid shipping
                  a full template engine for the wizard) ---------- */

    private function layout(string $title, string $body): string
    {
        $base = htmlspecialchars($this->app->baseUrl(), ENT_QUOTES);
        $css  = $base . '/assets/css/app.css';
        return <<<HTML
<!doctype html>
<html lang="es"><head><meta charset="utf-8">
<title>Instalación · {$title}</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="{$css}">
</head><body class="install">
<header class="container narrow"><h1>Porra Mundial 2026 · Instalación</h1><p class="muted">{$title}</p></header>
<main class="container narrow">{$body}</main>
<footer class="container narrow muted"><hr><small>v{$this->app->version()} · PHP {$this->phpVer()}</small></footer>
</body></html>
HTML;
    }

    private function phpVer(): string
    {
        return htmlspecialchars(PHP_VERSION, ENT_QUOTES);
    }

    /**
     * @param array<int, array<string, mixed>> $checks
     */
    private function preflightView(array $checks, bool $ok): string
    {
        $rows = '';
        foreach ($checks as $c) {
            $badge = $c['ok'] ? '<span class="ok">OK</span>' : '<span class="fail">FALTA</span>';
            $hint  = !empty($c['hint']) ? ' <small class="muted">' . htmlspecialchars((string)$c['hint'], ENT_QUOTES) . '</small>' : '';
            $rows .= '<tr><td>' . htmlspecialchars((string)$c['label'], ENT_QUOTES) . $hint . '</td><td>' . $badge . '</td></tr>';
        }
        $next = $ok
            ? '<a class="btn btn-primary" href="' . htmlspecialchars($this->app->baseUrl(), ENT_QUOTES) . '/install/db">Continuar</a>'
            : '<button class="btn btn-primary" disabled>Resuelve los puntos marcados como FALTA y recarga la página</button>';
        return '<table class="table">' . $rows . '</table>' . $next;
    }

    /**
     * @param array<string, mixed> $db
     */
    private function dbView(array $db, string $error): string
    {
        $err = $error !== ''
            ? '<div class="alert alert-danger"><strong>No se pudo conectar:</strong> ' . htmlspecialchars($error, ENT_QUOTES) . '</div>'
            : '';
        $tok = $this->app->csrf()->field();
        $f = static fn(string $k, string $default = '') => htmlspecialchars((string)($db[$k] ?? $default), ENT_QUOTES);
        return $err . <<<HTML
<form method="post">{$tok}
  <div class="row">
    <label>Host <input type="text" name="host" value="{$f('host', 'localhost')}" required></label>
    <label>Puerto <input type="number" name="port" value="{$f('port', '3306')}" required></label>
  </div>
  <label>Base de datos <input type="text" name="database" value="{$f('database')}" required></label>
  <label>Usuario <input type="text" name="username" value="{$f('username')}" required></label>
  <label>Contraseña <input type="password" name="password" autocomplete="off"></label>
  <label>Prefijo de tablas <input type="text" name="prefix" value="{$f('prefix', 'pm_')}"></label>
  <button class="btn btn-primary" type="submit">Probar conexión y continuar</button>
</form>
HTML;
    }

    /**
     * @param array<string, mixed> $site
     */
    private function siteView(array $site, string $error = ''): string
    {
        $tok = $this->app->csrf()->field();
        $err = $error !== '' ? '<div class="alert alert-danger">' . htmlspecialchars($error, ENT_QUOTES) . '</div>' : '';
        $f = static fn(string $k) => htmlspecialchars((string)($site[$k] ?? ''), ENT_QUOTES);
        return $err . <<<HTML
<form method="post">{$tok}
  <label>Nombre del sitio <input type="text" name="name" value="{$f('name')}" required></label>
  <label>URL pública <input type="url" name="base_url" value="{$f('base_url')}" required></label>
  <label>Zona horaria <input type="text" name="timezone" value="{$f('timezone')}" required>
    <small class="muted">Ej.: Europe/Madrid, America/Argentina/Buenos_Aires</small></label>
  <label>Idioma
    <select name="locale">
      <option value="es" selected>Español</option>
      <option value="en">English</option>
    </select>
  </label>
  <button class="btn btn-primary" type="submit">Continuar</button>
</form>
HTML;
    }

    /**
     * @param array<string, mixed> $admin
     * @param array<int, string> $errors
     */
    private function adminView(array $admin, array $errors): string
    {
        $tok = $this->app->csrf()->field();
        $errBlock = '';
        if ($errors) {
            $errBlock = '<div class="alert alert-danger"><ul>';
            foreach ($errors as $err) {
                $errBlock .= '<li>' . htmlspecialchars($err, ENT_QUOTES) . '</li>';
            }
            $errBlock .= '</ul></div>';
        }
        $f = static fn(string $k) => htmlspecialchars((string)($admin[$k] ?? ''), ENT_QUOTES);
        return $errBlock . <<<HTML
<form method="post">{$tok}
  <label>Nombre completo <input type="text" name="full_name" value="{$f('full_name')}" required></label>
  <label>Email (será tu usuario para iniciar sesión) <input type="email" name="email" value="{$f('email')}" required></label>
  <label>Contraseña <input type="password" name="password" autocomplete="new-password" required minlength="8"></label>
  <label>Confirmar contraseña <input type="password" name="password_confirm" autocomplete="new-password" required minlength="8"></label>
  <p class="muted"><small>La contraseña debe tener al menos 8 caracteres y combinar al menos 3 de: minúsculas, mayúsculas, dígitos, símbolos.</small></p>
  <button class="btn btn-primary" type="submit">Continuar</button>
</form>
HTML;
    }

    private function runView(): string
    {
        $tok = $this->app->csrf()->field();
        return <<<HTML
<p>Vamos a aplicar las migraciones, escribir <code>config/config.php</code> y crear tu usuario administrador.</p>
<form method="post">{$tok}
  <button class="btn btn-primary" type="submit">Ejecutar instalación</button>
</form>
HTML;
    }
}
