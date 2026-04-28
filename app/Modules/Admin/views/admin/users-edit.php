<?php
/** @var \App\Core\View $view */
/** @var \App\Core\Application $app */
/** @var callable $e */
/** @var \App\Models\User $user */
/** @var array<int, array<string, mixed>> $mfa */
/** @var array<int, array<string, mixed>> $audit */
/** @var ?string $msg */
/** @var array<int, string> $errors */
$view->extend('admin.layout', ['title' => 'Editar: ' . $user->fullName, 'flash' => $msg ?? null]);
$view->section('content');
$base = $e($app->baseUrl());
?>
<?php if ($errors): ?>
  <div class="alert alert-danger"><ul>
    <?php foreach ($errors as $err): ?><li><?= $e($err) ?></li><?php endforeach ?>
  </ul></div>
<?php endif ?>

<a href="<?= $base ?>/admin/users">← Volver al listado</a>

<h2>Datos</h2>
<form method="post" action="<?= $base ?>/admin/users/<?= (int)$user->id ?>" class="form-narrow">
  <?= $app->csrf()->field() ?>
  <label>Email <input type="email" value="<?= $e($user->email) ?>" disabled>
    <small class="muted">El email no se puede modificar desde aquí.</small></label>
  <label>Nombre completo <input type="text" name="full_name" value="<?= $e($user->fullName) ?>" required></label>
  <label>Rol
    <select name="role">
      <option value="user"  <?= $user->role === 'user'  ? 'selected' : '' ?>>Usuario</option>
      <?php if ($app->auth()->isAdmin()): ?>
        <option value="account_manager" <?= $user->role === 'account_manager' ? 'selected' : '' ?>>Gestor de cuentas</option>
        <option value="admin" <?= $user->role === 'admin' ? 'selected' : '' ?>>Administrador</option>
      <?php endif ?>
    </select>
  </label>
  <label>Estado
    <select name="status">
      <option value="active"   <?= $user->status === 'active'   ? 'selected' : '' ?>>Activo</option>
      <option value="disabled" <?= $user->status === 'disabled' ? 'selected' : '' ?>>Deshabilitado</option>
    </select>
  </label>
  <button class="btn btn-primary" type="submit">Guardar</button>
</form>

<h2>Cambiar contraseña</h2>
<form method="post" action="<?= $base ?>/admin/users/<?= (int)$user->id ?>/password" class="form-narrow"
      onsubmit="return confirm('¿Cambiar la contraseña de este usuario?');">
  <?= $app->csrf()->field() ?>
  <label>Nueva contraseña <input type="password" name="password" minlength="8" autocomplete="new-password" required></label>
  <p class="muted"><small>Mínimo 8 caracteres y al menos 3 de: minúsculas, mayúsculas, dígitos, símbolos.</small></p>
  <button class="btn btn-secondary" type="submit">Cambiar contraseña</button>
</form>

<h2>MFA</h2>
<?php if ($mfa === []): ?>
  <p class="muted">Sin métodos MFA registrados.</p>
<?php else: ?>
  <table class="table">
    <thead><tr><th>Tipo</th><th>Etiqueta</th><th>Creado</th><th>Último uso</th></tr></thead>
    <tbody>
    <?php foreach ($mfa as $m): ?>
      <tr>
        <td><?= $e((string)$m['type']) ?></td>
        <td><?= $e((string)$m['label']) ?></td>
        <td><?= $e((string)$m['created_at']) ?></td>
        <td><?= $e((string)($m['last_used_at'] ?? '—')) ?></td>
      </tr>
    <?php endforeach ?>
    </tbody>
  </table>
  <form method="post" action="<?= $base ?>/admin/users/<?= (int)$user->id ?>/mfa-reset"
        onsubmit="return confirm('Esto eliminará TODOS los métodos MFA de este usuario. ¿Continuar?');">
    <?= $app->csrf()->field() ?>
    <button class="btn btn-danger" type="submit">Eliminar todos los métodos MFA</button>
  </form>
<?php endif ?>

<h2>Eliminar usuario</h2>
<form method="post" action="<?= $base ?>/admin/users/<?= (int)$user->id ?>/delete"
      onsubmit="return confirm('¿Eliminar este usuario? Es una baja lógica (soft delete).');">
  <?= $app->csrf()->field() ?>
  <button class="btn btn-danger" type="submit">Eliminar usuario</button>
</form>

<h2>Auditoría</h2>
<table class="table">
  <thead><tr><th>Fecha</th><th>Evento</th><th>IP</th><th>Datos</th></tr></thead>
  <tbody>
  <?php foreach ($audit as $a): ?>
    <tr>
      <td><?= $e((string)$a['created_at']) ?></td>
      <td><?= $e((string)$a['event']) ?></td>
      <td><?= $e((string)($a['ip'] ?? '')) ?></td>
      <td><code><?= $e((string)($a['data'] ?? '')) ?></code></td>
    </tr>
  <?php endforeach ?>
  <?php if ($audit === []): ?>
    <tr><td colspan="4" class="muted">Sin eventos registrados.</td></tr>
  <?php endif ?>
  </tbody>
</table>
<?php $view->endSection() ?>
