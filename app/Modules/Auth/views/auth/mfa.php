<?php
/** @var \App\Core\Application $app */
/** @var callable $e */
/** @var ?string $error */
/** @var string $next */
/** @var bool $hasTotp */
/** @var bool $hasWebauthn */
$base = $e($app->baseUrl());
?>
<!doctype html>
<html lang="es"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Verificación · <?= $e((string)$app->config()->get('site.name', 'Porra')) ?></title>
<link rel="stylesheet" href="<?= $base ?>/assets/css/app.css">
</head><body class="install">
<main class="container narrow">
<h1>Verificación en dos pasos</h1>
<?php if ($error): ?><div class="alert alert-danger"><?= $e($error) ?></div><?php endif ?>

<?php if ($hasTotp): ?>
<form method="post" action="<?= $base ?>/login/mfa">
  <?= $app->csrf()->field() ?>
  <input type="hidden" name="next" value="<?= $e($next) ?>">
  <input type="hidden" name="method" value="totp">
  <label>Código de la aplicación de autenticación
    <input type="text" name="code" inputmode="numeric" pattern="\d{6}" maxlength="6" required autofocus autocomplete="one-time-code">
  </label>
  <button class="btn btn-primary" type="submit">Verificar</button>
</form>
<?php endif ?>

<?php if ($hasWebauthn): ?>
<hr>
<p>O usa una llave de seguridad / Windows Hello:</p>
<button class="btn btn-secondary" id="webauthn-btn" type="button">Usar llave de seguridad</button>
<p class="muted"><small>Requiere que el sitio se sirva sobre HTTPS.</small></p>
<script>
document.getElementById('webauthn-btn')?.addEventListener('click', async () => {
  alert('WebAuthn estará disponible cuando se despliegue la versión completa con web-auth/webauthn-lib.');
});
</script>
<?php endif ?>

<hr>
<details><summary>¿No tienes acceso al segundo factor? Usa un código de recuperación</summary>
  <form method="post" action="<?= $base ?>/login/mfa" style="margin-top:1em">
    <?= $app->csrf()->field() ?>
    <input type="hidden" name="next" value="<?= $e($next) ?>">
    <input type="hidden" name="method" value="recovery">
    <label>Código de recuperación <input type="text" name="code" required></label>
    <button class="btn btn-secondary" type="submit">Verificar</button>
  </form>
</details>

</main></body></html>
