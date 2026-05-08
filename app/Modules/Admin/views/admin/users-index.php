<?php
/** @var \App\Core\View $view */
/** @var \App\Core\Application $app */
/** @var callable $e */
/** @var array<int, \App\Models\User> $users */
/** @var array<int, array<int, array<string,mixed>>> $userMfa */
/** @var int $total */
/** @var int $page */
/** @var int $perPage */
/** @var string $search */
/** @var string $role */
/** @var string $status */
/** @var array<int, array<string, mixed>> $invitations */
/** @var ?string $msg */
$view->extend('admin.layout', ['title' => 'Usuarios', 'flash' => $msg ?? null]);
$view->section('content');
$base = $e($app->baseUrl());
$totalPages = max(1, (int)ceil($total / $perPage));
?>
<form method="get" class="filters" id="users-filter-form">
  <input type="text" name="q" value="<?= $e($search) ?>" placeholder="Buscar por nombre o email…" id="users-search-input">
  <select name="role" id="users-role-filter">
    <option value="">— Rol —</option>
    <option value="user"  <?= $role === 'user'  ? 'selected' : '' ?>>user</option>
    <option value="account_manager" <?= $role === 'account_manager' ? 'selected' : '' ?>>account_manager</option>
    <option value="admin" <?= $role === 'admin' ? 'selected' : '' ?>>admin</option>
  </select>
  <select name="status" id="users-status-filter">
    <option value="">— Estado —</option>
    <option value="active"   <?= $status === 'active'   ? 'selected' : '' ?>>activo</option>
    <option value="disabled" <?= $status === 'disabled' ? 'selected' : '' ?>>deshabilitado</option>
  </select>
  <a class="btn btn-primary" href="<?= $base ?>/admin/users/invite">+ Invitar usuario</a>
</form>
<script>
(function() {
  const form = document.getElementById('users-filter-form');
  const searchInput = document.getElementById('users-search-input');
  const roleSelect = document.getElementById('users-role-filter');
  const statusSelect = document.getElementById('users-status-filter');
  let debounceTimer = null;

  function submitForm() {
    form.submit();
  }

  roleSelect.addEventListener('change', submitForm);
  statusSelect.addEventListener('change', submitForm);

  // Debounce 400ms to avoid excessive requests while still feeling responsive.
  searchInput.addEventListener('input', function() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(submitForm, 400);
  });
})();
</script>

<table class="table">
  <thead>
    <tr>
      <th>Nombre</th><th>Email</th><th>Rol</th><th>Estado</th><th>MFA</th><th>Último login</th><th></th>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($users as $u): ?>
    <?php $methods = $userMfa[$u->id] ?? []; ?>
    <tr>
      <td><?= $e($u->fullName) ?></td>
      <td><?= $e($u->email) ?></td>
      <td><span class="badge"><?= $e($u->role) ?></span></td>
      <td><?= $e($u->status) ?></td>
      <td>
        <?php if ($methods === []): ?>
          <span class="muted">no</span>
        <?php else: ?>
          <?php
            $types = [];
            foreach ($methods as $m) { $types[$m['type']] = ($types[$m['type']] ?? 0) + 1; }
            $parts = [];
            foreach ($types as $t => $c) { $parts[] = $e($t) . ' ×' . (int)$c; }
            echo implode(', ', $parts);
          ?>
        <?php endif ?>
      </td>
      <td><?= $e((string)($u->lastLoginAt ?? '—')) ?></td>
      <td><a href="<?= $base ?>/admin/users/<?= (int)$u->id ?>">Editar</a></td>
    </tr>
  <?php endforeach ?>
  <?php if ($users === []): ?>
    <tr><td colspan="7" class="muted">No hay usuarios que coincidan con los filtros.</td></tr>
  <?php endif ?>
  </tbody>
</table>

<?php if ($totalPages > 1): ?>
<nav class="pagination">
  <?php for ($p = 1; $p <= $totalPages; $p++): ?>
    <a href="?q=<?= urlencode($search) ?>&role=<?= urlencode($role) ?>&status=<?= urlencode($status) ?>&page=<?= $p ?>"
       class="<?= $p === $page ? 'active' : '' ?>"><?= $p ?></a>
  <?php endfor ?>
</nav>
<?php endif ?>

<h2 id="invitations">Invitaciones pendientes</h2>
<table class="table">
  <thead><tr><th>Email</th><th>Nombre</th><th>Rol</th><th>Expira</th><th>Creada</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($invitations as $inv): ?>
    <tr>
      <td><?= $e((string)$inv['email']) ?></td>
      <td><?= $e((string)$inv['full_name']) ?></td>
      <td><?= $e((string)$inv['role']) ?></td>
      <td><?= $e((string)$inv['expires_at']) ?> UTC</td>
      <td><?= $e((string)$inv['created_at']) ?> UTC</td>
      <td>
        <form method="post" action="<?= $base ?>/admin/users/invitations/<?= (int)$inv['id'] ?>/resend" style="display:inline">
          <?= $app->csrf()->field() ?>
          <button class="btn btn-small" type="submit">Reenviar</button>
        </form>
        <form method="post" action="<?= $base ?>/admin/users/invitations/<?= (int)$inv['id'] ?>/revoke" style="display:inline" onsubmit="return confirm('¿Revocar la invitación?');">
          <?= $app->csrf()->field() ?>
          <button class="btn btn-small btn-danger" type="submit">Revocar</button>
        </form>
      </td>
    </tr>
  <?php endforeach ?>
  <?php if ($invitations === []): ?>
    <tr><td colspan="6" class="muted">No hay invitaciones pendientes.</td></tr>
  <?php endif ?>
  </tbody>
</table>
<?php $view->endSection() ?>
