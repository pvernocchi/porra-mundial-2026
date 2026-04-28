<?php
/** @var \App\Core\Application $app */
/** @var callable $e */
/** @var ?string $error */
/** @var string $next */
/** @var string $captcha_html */
$base = $e($app->baseUrl());
$siteName = $e((string)$app->config()->get('site.name', 'Porra'));
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Iniciar sesión · <?= $siteName ?></title>
<link rel="stylesheet" href="<?= $base ?>/assets/css/app.css">
</head>
<body class="install">
<main class="container narrow">
  <h1>Iniciar sesión</h1>
  <?php if ($error): ?>
    <div class="alert alert-danger"><?= $e($error) ?></div>
  <?php endif ?>
  <form method="post" action="<?= $base ?>/login" class="form-narrow">
    <?= $app->csrf()->field() ?>
    <input type="hidden" name="next" value="<?= $e($next) ?>">
    <label>Email <input type="email" name="email" autocomplete="username" required autofocus></label>
    <label>Contraseña <input type="password" name="password" autocomplete="current-password" required></label>
    <?= $captcha_html ?>
    <button class="btn btn-primary" type="submit">Entrar</button>
  </form>
</main>
</body>
</html>
