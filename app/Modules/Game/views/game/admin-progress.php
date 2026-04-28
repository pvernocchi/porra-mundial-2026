<?php
/** @var \App\Core\View $view */
/** @var \App\Core\Application $app */
/** @var callable $e */
/** @var array<int, \App\Models\Team> $teams */
/** @var array<int, array<string, mixed>> $progress */
/** @var array<int, array<string, mixed>> $awards */

$achievementLabels = [
    'passed_group'  => 'Pasó fase de grupos (+3)',
    'round_of_16'   => 'Octavos de final (+3)',
    'quarter'       => 'Cuartos de final (+4)',
    'semi'          => 'Semifinales (+6)',
    'final'         => 'Final (+8)',
    'champion'      => '🏆 Campeón (+12)',
    'last_in_group' => 'Último del grupo (−1)',
];

$awardLabels = [
    'mvp'          => 'MVP del torneo (+3)',
    'golden_boot'  => 'Goleador del torneo (+2)',
    'golden_glove' => 'Portero del torneo (+2)',
    'best_young'   => 'Jugador joven del torneo (+2)',
];

$view->extend('admin.layout', ['title' => 'Avances y premios']);
$view->section('content');
$base = $e($app->baseUrl());
?>

<h2>Añadir avance de torneo</h2>
<form method="post" action="<?= $base ?>/admin/game/progress">
  <?= $app->csrf()->field() ?>
  <div style="display:flex;flex-wrap:wrap;gap:0.5rem;align-items:end">
    <label>Selección:
      <select name="team_id" required>
        <option value="">—</option>
        <?php foreach ($teams as $t): ?>
          <option value="<?= $t->id ?>"><?= $e($t->name) ?></option>
        <?php endforeach ?>
      </select>
    </label>
    <label>Logro:
      <select name="achievement" required>
        <option value="">—</option>
        <?php foreach ($achievementLabels as $k => $v): ?>
          <option value="<?= $e($k) ?>"><?= $e($v) ?></option>
        <?php endforeach ?>
      </select>
    </label>
    <button type="submit" class="btn btn-primary">Añadir</button>
  </div>
</form>

<h3>Avances registrados</h3>
<?php if ($progress === []): ?>
  <p>No hay avances registrados.</p>
<?php else: ?>
  <table class="table">
    <thead><tr><th>Selección</th><th>Logro</th><th>Acciones</th></tr></thead>
    <tbody>
    <?php foreach ($progress as $p): ?>
      <tr>
        <td><?= $e((string)$p['team_name']) ?></td>
        <td><?= $e($achievementLabels[$p['achievement']] ?? $p['achievement']) ?></td>
        <td>
          <form method="post" action="<?= $base ?>/admin/game/progress/<?= (int)$p['team_id'] ?>/<?= $e((string)$p['achievement']) ?>/delete" style="display:inline">
            <?= $app->csrf()->field() ?>
            <button type="submit" class="btn btn-link">Eliminar</button>
          </form>
        </td>
      </tr>
    <?php endforeach ?>
    </tbody>
  </table>
<?php endif ?>

<hr>

<h2>Premios individuales</h2>
<form method="post" action="<?= $base ?>/admin/game/awards">
  <?= $app->csrf()->field() ?>
  <div style="display:flex;flex-wrap:wrap;gap:0.5rem;align-items:end">
    <label>Premio:
      <select name="award_type" required>
        <option value="">—</option>
        <?php foreach ($awardLabels as $k => $v): ?>
          <option value="<?= $e($k) ?>"><?= $e($v) ?></option>
        <?php endforeach ?>
      </select>
    </label>
    <label>Selección:
      <select name="team_id" required>
        <option value="">—</option>
        <?php foreach ($teams as $t): ?>
          <option value="<?= $t->id ?>"><?= $e($t->name) ?></option>
        <?php endforeach ?>
      </select>
    </label>
    <label>Jugador: <input type="text" name="player_name" placeholder="Nombre del jugador"></label>
    <button type="submit" class="btn btn-primary">Asignar</button>
  </div>
</form>

<h3>Premios asignados</h3>
<?php if ($awards === []): ?>
  <p>No hay premios asignados.</p>
<?php else: ?>
  <table class="table">
    <thead><tr><th>Premio</th><th>Selección</th><th>Jugador</th><th>Acciones</th></tr></thead>
    <tbody>
    <?php foreach ($awards as $a): ?>
      <tr>
        <td><?= $e($awardLabels[$a['award_type']] ?? $a['award_type']) ?></td>
        <td><?= $e((string)$a['team_name']) ?></td>
        <td><?= $e((string)($a['player_name'] ?? '—')) ?></td>
        <td>
          <form method="post" action="<?= $base ?>/admin/game/awards/<?= $e((string)$a['award_type']) ?>/delete" style="display:inline">
            <?= $app->csrf()->field() ?>
            <button type="submit" class="btn btn-link">Eliminar</button>
          </form>
        </td>
      </tr>
    <?php endforeach ?>
    </tbody>
  </table>
<?php endif ?>

<?php $view->endSection(); ?>
