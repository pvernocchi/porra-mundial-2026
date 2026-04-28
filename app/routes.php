<?php
declare(strict_types=1);

use App\Core\Application;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Modules\Admin\AdminController;
use App\Modules\Admin\CommunicationController;
use App\Modules\Admin\SecurityController;
use App\Modules\Admin\UserController;
use App\Modules\Auth\AuthController;
use App\Modules\Auth\InviteController;
use App\Modules\Auth\MfaController;
use App\Modules\Install\InstallController;
use App\Modules\Game\GameController;
use App\Modules\Game\AdminGameController;

/**
 * @param Application $app
 * @return Router
 */
return static function (Application $app): Router {
    $router = $app->router();

    // Middleware: ensure session is started for every request that may need it.
    $router->use(static function (Request $req, callable $next, Application $app): Response {
        $app->session()->start();
        return $next($req);
    });

    /* ---------- Public install routes ---------- */
    $router->get('/install',         fn(Request $r) => (new InstallController($app))->step0($r));
    $router->get('/install/db',      fn(Request $r) => (new InstallController($app))->step1Form($r));
    $router->post('/install/db',     fn(Request $r) => (new InstallController($app))->step1Submit($r));
    $router->get('/install/site',    fn(Request $r) => (new InstallController($app))->step2Form($r));
    $router->post('/install/site',   fn(Request $r) => (new InstallController($app))->step2Submit($r));
    $router->get('/install/admin',   fn(Request $r) => (new InstallController($app))->step3Form($r));
    $router->post('/install/admin',  fn(Request $r) => (new InstallController($app))->step3Submit($r));
    $router->get('/install/run',     fn(Request $r) => (new InstallController($app))->run($r));
    $router->post('/install/run',    fn(Request $r) => (new InstallController($app))->run($r));
    $router->get('/install/done',    fn(Request $r) => (new InstallController($app))->done($r));
    $router->any('/install/upgrade', fn(Request $r) => (new InstallController($app))->upgrade($r));

    /* ---------- Anonymous home / login ---------- */
    $router->get('/', static function (Request $r) use ($app): Response {
        if (!$app->isInstalled()) {
            return (new Response())->redirect($app->baseUrl() . '/install');
        }
        $codeVer = $app->version();
        $haveVer = $app->installedVersion() ?? '0.0.0';
        if (version_compare($codeVer, $haveVer, '>')) {
            return (new Response())->redirect($app->baseUrl() . '/install/upgrade');
        }
        return (new Response())->redirect($app->baseUrl() . '/login');
    });

    $router->get('/login',  fn(Request $r) => (new AuthController($app))->showLogin($r));
    $router->post('/login', fn(Request $r) => (new AuthController($app))->doLogin($r));
    $router->get('/login/mfa',  fn(Request $r) => (new AuthController($app))->showMfa($r));
    $router->post('/login/mfa', fn(Request $r) => (new AuthController($app))->doMfa($r));
    $router->any('/logout',     fn(Request $r) => (new AuthController($app))->logout($r));

    $router->get('/invite/{token}',  fn(Request $r, array $p) => (new InviteController($app))->show($r, $p));
    $router->post('/invite/{token}', fn(Request $r, array $p) => (new InviteController($app))->submit($r, $p));

    /* ---------- Authenticated middleware ---------- */
    $auth = static function (Request $req, callable $next, Application $app): Response {
        if (!$app->auth()->check()) {
            return (new Response())->redirect($app->baseUrl() . '/login?next=' . urlencode($req->path()));
        }
        return $next($req);
    };
    $admin = static function (Request $req, callable $next, Application $app): Response {
        if (!$app->auth()->isAdmin()) {
            return (new Response())->html('<h1>403</h1><p>Acceso denegado.</p>', 403);
        }
        return $next($req);
    };
    $userManager = static function (Request $req, callable $next, Application $app): Response {
        if (!$app->auth()->canManageUsers()) {
            return (new Response())->html('<h1>403</h1><p>Acceso denegado.</p>', 403);
        }
        return $next($req);
    };

    /* ---------- Account (any logged-in user) ---------- */
    $router->get('/account', static function (Request $r) use ($app): Response {
        return (new Response())->redirect($app->baseUrl() . '/account/mfa');
    }, [$auth]);

    $router->get('/account/mfa',                 fn(Request $r) => (new MfaController($app))->dashboard($r),       [$auth]);
    $router->get('/account/mfa/enroll',          fn(Request $r) => (new MfaController($app))->dashboard($r));        // pending state
    $router->get('/account/mfa/totp/new',        fn(Request $r) => (new MfaController($app))->totpNew($r));
    $router->post('/account/mfa/totp/new',       fn(Request $r) => (new MfaController($app))->totpConfirm($r));
    $router->post('/account/mfa/{id}/delete',    fn(Request $r, array $p) => (new MfaController($app))->deleteCredential($r, $p), [$auth]);
    $router->post('/account/mfa/recovery',       fn(Request $r) => (new MfaController($app))->regenerateRecovery($r), [$auth]);

    // WebAuthn (stubs return 501 unless lib is present)
    $router->get('/account/mfa/webauthn/register-options',  fn(Request $r) => (new MfaController($app))->webauthnRegisterOptions($r));
    $router->post('/account/mfa/webauthn/register',         fn(Request $r) => (new MfaController($app))->webauthnRegister($r));
    $router->get('/api/webauthn/login-options',             fn(Request $r) => (new MfaController($app))->webauthnLoginOptions($r));
    $router->post('/api/webauthn/login',                    fn(Request $r) => (new MfaController($app))->webauthnLogin($r));

    /* ---------- Admin ---------- */
    $router->get('/admin',                                fn(Request $r) => (new AdminController($app))->dashboard($r), [$auth, $userManager]);

    $router->get('/admin/users',                          fn(Request $r) => (new UserController($app))->index($r),                  [$auth, $userManager]);
    $router->get('/admin/users/invite',                   fn(Request $r) => (new UserController($app))->inviteForm($r),             [$auth, $userManager]);
    $router->post('/admin/users/invite',                  fn(Request $r) => (new UserController($app))->inviteSubmit($r),           [$auth, $userManager]);
    $router->post('/admin/users/invitations/{id}/resend', fn(Request $r, array $p) => (new UserController($app))->inviteResend($r, $p), [$auth, $userManager]);
    $router->post('/admin/users/invitations/{id}/revoke', fn(Request $r, array $p) => (new UserController($app))->inviteRevoke($r, $p), [$auth, $userManager]);
    $router->get('/admin/users/{id}',                     fn(Request $r, array $p) => (new UserController($app))->edit($r, $p),     [$auth, $userManager]);
    $router->post('/admin/users/{id}',                    fn(Request $r, array $p) => (new UserController($app))->update($r, $p),   [$auth, $userManager]);
    $router->post('/admin/users/{id}/password',           fn(Request $r, array $p) => (new UserController($app))->changePassword($r, $p), [$auth, $userManager]);
    $router->post('/admin/users/{id}/mfa-reset',          fn(Request $r, array $p) => (new UserController($app))->resetMfa($r, $p), [$auth, $userManager]);
    $router->post('/admin/users/{id}/delete',             fn(Request $r, array $p) => (new UserController($app))->delete($r, $p),   [$auth, $userManager]);

    $router->get('/admin/communications/smtp',  fn(Request $r) => (new CommunicationController($app))->smtp($r),       [$auth, $admin]);
    $router->post('/admin/communications/smtp', fn(Request $r) => (new CommunicationController($app))->smtpSubmit($r), [$auth, $admin]);
    $router->post('/admin/communications/smtp/test', fn(Request $r) => (new CommunicationController($app))->smtpTest($r), [$auth, $admin]);

    $router->get('/admin/security',  fn(Request $r) => (new SecurityController($app))->index($r), [$auth, $admin]);
    $router->post('/admin/security', fn(Request $r) => (new SecurityController($app))->save($r),  [$auth, $admin]);

    /* ---------- Admin: Game management (admin + account_manager) ---------- */
    $router->get('/admin/game/matches',                                      fn(Request $r) => (new AdminGameController($app))->matches($r),                  [$auth, $userManager]);
    $router->post('/admin/game/matches',                                     fn(Request $r) => (new AdminGameController($app))->createMatch($r),              [$auth, $userManager]);
    $router->get('/admin/game/matches/{id}',                                 fn(Request $r, array $p) => (new AdminGameController($app))->editMatch($r, $p),  [$auth, $userManager]);
    $router->post('/admin/game/matches/{id}',                                fn(Request $r, array $p) => (new AdminGameController($app))->updateMatch($r, $p),[$auth, $userManager]);
    $router->post('/admin/game/matches/{id}/delete',                         fn(Request $r, array $p) => (new AdminGameController($app))->deleteMatch($r, $p),[$auth, $userManager]);
    $router->post('/admin/game/picks-lock',                                  fn(Request $r) => (new AdminGameController($app))->togglePicksLock($r),          [$auth, $userManager]);
    $router->get('/admin/game/progress',                                     fn(Request $r) => (new AdminGameController($app))->progress($r),                 [$auth, $userManager]);
    $router->post('/admin/game/progress',                                    fn(Request $r) => (new AdminGameController($app))->addProgress($r),              [$auth, $userManager]);
    $router->post('/admin/game/progress/{team_id}/{achievement}/delete',     fn(Request $r, array $p) => (new AdminGameController($app))->removeProgress($r, $p), [$auth, $userManager]);
    $router->post('/admin/game/awards',                                      fn(Request $r) => (new AdminGameController($app))->setAward($r),                 [$auth, $userManager]);
    $router->post('/admin/game/awards/{award}/delete',                       fn(Request $r, array $p) => (new AdminGameController($app))->removeAward($r, $p),[$auth, $userManager]);

    /* ---------- Game: Player-facing (any logged-in user) ---------- */
    $router->get('/game/picks',       fn(Request $r) => (new GameController($app))->picks($r),       [$auth]);
    $router->post('/game/picks',      fn(Request $r) => (new GameController($app))->savePicks($r),   [$auth]);
    $router->get('/game/leaderboard', fn(Request $r) => (new GameController($app))->leaderboard($r), [$auth]);
    $router->get('/game/results',     fn(Request $r) => (new GameController($app))->results($r),     [$auth]);

    return $router;
};
