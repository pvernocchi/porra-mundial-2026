<?php
/** @var \App\Core\View $view */
/** @var \App\Core\Application $app */
/** @var callable $e */
/** @var int $totalUsers */
/** @var array<int, \App\Models\User> $recentUsers */
/** @var array<int, array<string, mixed>> $pendingInvites */
/** @var array<int, array<string, mixed>> $audit */
$view->extend('admin.layout', ['title' => 'Panel de administración']);
$view->section('content');
$base = $e($app->baseUrl());
?>
<div class="cards">
  <div class="card">
    <h3>Usuarios</h3>
    <p class="metric"><?= $e((string)$totalUsers) ?></p>
    <a href="<?= $base ?>/admin/users">Gestionar →</a>
  </div>
  <div class="card">
    <h3>Invitaciones pendientes</h3>
    <p class="metric"><?= $e((string)count($pendingInvites)) ?></p>
    <a href="<?= $base ?>/admin/users#invitations">Ver →</a>
  </div>
  <?php if ($app->auth()->isAdmin()): ?>
  <div class="card">
    <h3>SMTP</h3>
    <p><?= $app->mail()->isConfigured() ? '✅ Configurado' : '⚠️ No configurado' ?></p>
    <a href="<?= $base ?>/admin/communications/smtp">Configurar →</a>
  </div>
  <div class="card">
    <h3>Seguridad</h3>
    <p>Política MFA: <strong><?= $e((string)$app->settings()->get('security.mfa.policy', 'optional')) ?></strong></p>
    <a href="<?= $base ?>/admin/security">Ajustes →</a>
  </div>
  <?php endif ?>
</div>

<h2>Eventos recientes</h2>
<table class="table">
  <thead><tr><th>Fecha</th><th>Evento</th><th>Usuario</th><th>IP</th></tr></thead>
  <tbody>
  <?php foreach ($audit as $row): ?>
    <tr>
      <td><?= $e((string)$row['created_at']) ?></td>
      <td><?= $e((string)$row['event']) ?></td>
      <td><?= $e((string)($row['user_id'] ?? '—')) ?></td>
      <td><?= $e((string)($row['ip'] ?? '')) ?></td>
    </tr>
  <?php endforeach ?>
  <?php if ($audit === []): ?>
    <tr><td colspan="4" class="muted">Sin eventos.</td></tr>
  <?php endif ?>
  </tbody>
</table>
<?php $view->endSection() ?>
