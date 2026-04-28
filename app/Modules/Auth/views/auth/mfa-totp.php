<?php
/** @var \App\Core\Application $app */
/** @var callable $e */
/** @var string $secret */
/** @var string $uri */
/** @var string $qr_url */
/** @var ?string $error */
$base = $e($app->baseUrl());
?>
<!doctype html><html lang="es"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Configurar TOTP</title>
<link rel="stylesheet" href="<?= $base ?>/assets/css/app.css"></head>
<body class="install">
<main class="container narrow">
<h1>Configurar aplicación de autenticación</h1>

<?php if ($error): ?><div class="alert alert-danger"><?= $e($error) ?></div><?php endif ?>

<ol>
  <li>Abre tu app de autenticación (Google Authenticator, 1Password, Authy, etc).</li>
  <li>Escanea este código QR <em>o</em> introduce manualmente el secreto.</li>
  <li>Introduce el código de 6 dígitos que muestra la app para confirmar.</li>
</ol>

<p style="text-align:center"><img src="<?= $e($qr_url) ?>" alt="QR" width="200" height="200"></p>
<p style="text-align:center"><small>Si no funciona el QR: <code><?= $e($secret) ?></code></small></p>

<form method="post" action="<?= $base ?>/account/mfa/totp/new" class="form-narrow">
  <?= $app->csrf()->field() ?>
  <label>Etiqueta (opcional) <input type="text" name="label" placeholder="p.ej. iPhone, 1Password"></label>
  <label>Código de 6 dígitos
    <input type="text" name="code" inputmode="numeric" pattern="\d{6}" maxlength="6" required autofocus autocomplete="one-time-code">
  </label>
  <button class="btn btn-primary" type="submit">Confirmar y guardar</button>
  <a class="btn btn-secondary" href="<?= $base ?>/account/mfa">Cancelar</a>
  <a class="btn btn-link" href="<?= $base ?>/account/mfa/totp/new?regen=1">Generar otro secreto</a>
</form>
</main></body></html>
