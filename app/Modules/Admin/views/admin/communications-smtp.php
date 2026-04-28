<?php
/** @var \App\Core\View $view */
/** @var \App\Core\Application $app */
/** @var callable $e */
/** @var array<string, mixed> $cfg */
/** @var ?string $msg */
/** @var array<int, string> $errors */
$view->extend('admin.layout', ['title' => 'Comunicaciones · SMTP', 'flash' => $msg ?? null]);
$view->section('content');
$base = $e($app->baseUrl());
?>
<?php if ($errors): ?>
  <div class="alert alert-danger"><ul>
    <?php foreach ($errors as $err): ?><li><?= $e($err) ?></li><?php endforeach ?>
  </ul></div>
<?php endif ?>

<form method="post" action="<?= $base ?>/admin/communications/smtp" class="form-narrow">
  <?= $app->csrf()->field() ?>
  <label>Host <input type="text" name="host" value="<?= $e((string)$cfg['host']) ?>" required></label>
  <label>Puerto <input type="number" name="port" value="<?= $e((string)$cfg['port']) ?>" required></label>
  <label>Cifrado
    <select name="encryption">
      <option value="none" <?= $cfg['encryption'] === 'none' ? 'selected' : '' ?>>Ninguno</option>
      <option value="tls"  <?= $cfg['encryption'] === 'tls'  ? 'selected' : '' ?>>STARTTLS (587)</option>
      <option value="ssl"  <?= $cfg['encryption'] === 'ssl'  ? 'selected' : '' ?>>SSL/TLS (465)</option>
    </select>
  </label>
  <label class="checkbox">
    <input type="checkbox" name="auth" value="1" <?= !empty($cfg['auth']) ? 'checked' : '' ?>>
    Requiere autenticación
  </label>
  <label>Usuario <input type="text" name="username" value="<?= $e((string)$cfg['username']) ?>"></label>
  <label>Contraseña
    <input type="password" name="password" autocomplete="off" placeholder="<?= $cfg['password'] === '__keep__' ? '(sin cambios — déjalo vacío para conservar)' : '' ?>">
  </label>
  <label>Email remitente <input type="email" name="from_email" value="<?= $e((string)$cfg['from_email']) ?>"></label>
  <label>Nombre remitente <input type="text" name="from_name" value="<?= $e((string)$cfg['from_name']) ?>"></label>
  <label>Reply-To (opcional) <input type="email" name="reply_to" value="<?= $e((string)$cfg['reply_to']) ?>"></label>
  <button class="btn btn-primary" type="submit">Guardar</button>
</form>

<h2>Enviar correo de prueba</h2>
<form method="post" action="<?= $base ?>/admin/communications/smtp/test" class="form-narrow">
  <?= $app->csrf()->field() ?>
  <label>Destino <input type="email" name="test_to" required></label>
  <button class="btn btn-secondary" type="submit">Enviar</button>
</form>

<p class="muted"><small>
  Si SMTP no está configurado, los emails se guardan en
  <code>storage/mail/</code> como archivos <code>.eml</code> para que puedas
  comprobar el contenido durante las pruebas.
</small></p>
<?php $view->endSection() ?>
