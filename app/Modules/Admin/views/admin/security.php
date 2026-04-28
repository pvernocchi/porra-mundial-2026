<?php
/** @var \App\Core\View $view */
/** @var \App\Core\Application $app */
/** @var callable $e */
/** @var array<string, mixed> $captcha */
/** @var string $mfaPolicy */
/** @var bool $freshAdmin */
/** @var int $rememberDays */
/** @var int $maxAttempts */
/** @var int $window */
/** @var int $sessionIdle */
/** @var ?string $msg */
$view->extend('layout', ['title' => 'Seguridad', 'flash' => $msg ?? null]);
$view->section('content');
$base = $e($app->baseUrl());
?>
<form method="post" action="<?= $base ?>/admin/security">
  <?= $app->csrf()->field() ?>

  <h2>Captcha</h2>
  <label>Proveedor
    <select name="captcha_provider">
      <option value="none"        <?= $captcha['provider'] === 'none'        ? 'selected' : '' ?>>Ninguno</option>
      <option value="recaptcha_v2" <?= $captcha['provider'] === 'recaptcha_v2' ? 'selected' : '' ?>>Google reCAPTCHA v2</option>
      <option value="recaptcha_v3" <?= $captcha['provider'] === 'recaptcha_v3' ? 'selected' : '' ?>>Google reCAPTCHA v3</option>
      <option value="turnstile"   <?= $captcha['provider'] === 'turnstile'   ? 'selected' : '' ?>>Cloudflare Turnstile</option>
    </select>
  </label>
  <label>Site key   <input type="text"     name="captcha_site_key"   value="<?= $e((string)$captcha['site_key']) ?>"></label>
  <label>Secret key <input type="password" name="captcha_secret_key" autocomplete="off" placeholder="<?= !empty($captcha['secret_key_set']) ? '(sin cambios — déjalo vacío para conservar)' : '' ?>"></label>
  <label>Umbral (sólo v3) <input type="number" step="0.05" min="0" max="1" name="captcha_threshold" value="<?= $e((string)$captcha['threshold']) ?>"></label>
  <p class="muted"><small>Se aplica en login, aceptación de invitación y restablecimiento de contraseña.</small></p>

  <h2>MFA</h2>
  <label>Política
    <select name="mfa_policy">
      <option value="optional" <?= $mfaPolicy === 'optional' ? 'selected' : '' ?>>Opcional</option>
      <option value="admins"   <?= $mfaPolicy === 'admins'   ? 'selected' : '' ?>>Obligatorio para administradores</option>
      <option value="all"      <?= $mfaPolicy === 'all'      ? 'selected' : '' ?>>Obligatorio para todos</option>
    </select>
  </label>
  <label class="checkbox">
    <input type="checkbox" name="mfa_fresh_required" value="1" <?= $freshAdmin ? 'checked' : '' ?>>
    Re-verificar MFA para acciones administrativas sensibles
  </label>
  <label>Recordar dispositivo (días)
    <select name="mfa_remember_days">
      <option value="0"  <?= $rememberDays === 0  ? 'selected' : '' ?>>No recordar</option>
      <option value="7"  <?= $rememberDays === 7  ? 'selected' : '' ?>>7 días</option>
      <option value="30" <?= $rememberDays === 30 ? 'selected' : '' ?>>30 días</option>
    </select>
  </label>

  <h2>Bloqueo de cuenta</h2>
  <label>Intentos máximos por ventana
    <input type="number" name="login_max_attempts" value="<?= $e((string)$maxAttempts) ?>" min="3" max="100">
  </label>
  <label>Ventana (segundos)
    <input type="number" name="login_window_seconds" value="<?= $e((string)$window) ?>" min="60" max="86400">
  </label>

  <h2>Sesión</h2>
  <p class="muted">Inactividad antes de cerrar sesión: <strong><?= $e((string)$sessionIdle) ?>s</strong>
  <small>(configurable en <code>config/config.php</code>)</small></p>

  <button class="btn btn-primary" type="submit">Guardar</button>
</form>
<?php $view->endSection() ?>
