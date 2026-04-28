<?php
/** @var \App\Core\View $view */
/** @var \App\Core\Application $app */
/** @var callable $e */
/** @var array<int, string> $errors */
/** @var string $fullName */
/** @var string $email */
/** @var string $role */
$view->extend('admin.layout', ['title' => 'Invitar usuario']);
$view->section('content');
$base = $e($app->baseUrl());
?>
<?php if ($errors): ?>
  <div class="alert alert-danger"><ul>
    <?php foreach ($errors as $err): ?><li><?= $e($err) ?></li><?php endforeach ?>
  </ul></div>
<?php endif ?>
<form method="post" action="<?= $base ?>/admin/users/invite" class="form-narrow">
  <?= $app->csrf()->field() ?>
  <label>Nombre completo <input type="text" name="full_name" value="<?= $e($fullName) ?>" required></label>
  <label>Email <input type="email" name="email" value="<?= $e($email) ?>" required></label>
  <label>Rol
    <select name="role">
      <option value="user"  <?= $role !== 'admin' ? 'selected' : '' ?>>Usuario</option>
      <option value="admin" <?= $role === 'admin' ? 'selected' : '' ?>>Administrador</option>
    </select>
  </label>
  <p class="muted"><small>Se enviará un email con un enlace válido durante 48 horas.</small></p>
  <button class="btn btn-primary" type="submit">Enviar invitación</button>
  <a class="btn btn-secondary" href="<?= $base ?>/admin/users">Cancelar</a>
</form>
<?php if (!$app->mail()->isConfigured()): ?>
  <div class="alert alert-warning">
    SMTP no está configurado. Los correos de invitación se guardarán en
    <code>storage/mail/</code> hasta que configures el SMTP en
    <a href="<?= $base ?>/admin/communications/smtp">Comunicaciones</a>.
  </div>
<?php endif ?>
<?php $view->endSection() ?>
