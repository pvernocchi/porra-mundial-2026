<?php
/** @var \App\Core\Application $app */
/** @var callable $e */
/** @var array<int, string> $codes */
$base = $e($app->baseUrl());
?>
<!doctype html><html lang="es"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Códigos de recuperación</title>
<link rel="stylesheet" href="<?= $base ?>/assets/css/app.css"></head>
<body class="install"><main class="container narrow">
<h1>Códigos de recuperación</h1>
<div class="alert alert-warning">
  <strong>Guarda estos códigos en un lugar seguro AHORA.</strong>
  No volverás a verlos. Cada código se puede usar una sola vez si pierdes
  acceso a tu segundo factor.
</div>
<pre style="font-size:1.1em;background:#f4f4f4;padding:1em;border-radius:6px;line-height:1.6">
<?php foreach ($codes as $c) {
    echo $e($c) . "\n";
} ?>
</pre>
<a class="btn btn-primary" href="<?= $base ?>/account/mfa">He guardado los códigos, continuar</a>
</main></body></html>
