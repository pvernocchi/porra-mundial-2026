<?php
/** @var \App\Core\View $view */
/** @var \App\Core\Application $app */
/** @var callable $e */
/** @var array<int, \App\Models\GameMatch> $matches */
/** @var array<int, \App\Models\Team> $teams */

$phaseLabels = [
    'group'       => 'Fase de grupos',
    'round_of_32' => 'Dieciseisavos',
    'round_of_16' => 'Octavos de final',
    'quarter'     => 'Cuartos de final',
    'semi'        => 'Semifinales',
    'third_place' => 'Tercer puesto',
    'final'       => 'Final',
];

$locked = $app->settings()->get('game.picks_locked', '0') === '1';

$view->extend('admin.layout', ['title' => 'Gestión de partidos']);
$view->section('content');
$base = $e($app->baseUrl());
?>

<div style="margin-bottom:1rem">
  <form method="post" action="<?= $base ?>/admin/game/picks-lock" style="display:inline">
    <?= $app->csrf()->field() ?>
    <button type="submit" class="btn <?= $locked ? 'btn-primary' : '' ?>">
      <?= $locked ? '🔓 Desbloquear selecciones' : '🔒 Bloquear selecciones' ?>
    </button>
  </form>
  <span>Estado: <?= $locked ? '<strong>Bloqueadas</strong>' : 'Abiertas' ?></span>
</div>

<h2>Nuevo partido</h2>
<form method="post" action="<?= $base ?>/admin/game/matches">
  <?= $app->csrf()->field() ?>
  <div style="display:flex;flex-wrap:wrap;gap:0.5rem;align-items:end">
    <label>Fase:
      <select name="phase">
        <?php foreach ($phaseLabels as $k => $v): ?>
          <option value="<?= $e($k) ?>"><?= $e($v) ?></option>
        <?php endforeach ?>
      </select>
    </label>
    <label>Fecha: <input type="datetime-local" name="match_date"></label>
    <label>Local:
      <select name="home_team_id" required>
        <option value="">—</option>
        <?php foreach ($teams as $t): ?>
          <option value="<?= $t->id ?>"><?= $e($t->name) ?></option>
        <?php endforeach ?>
      </select>
    </label>
    <label>Visitante:
      <select name="away_team_id" required>
        <option value="">—</option>
        <?php foreach ($teams as $t): ?>
          <option value="<?= $t->id ?>"><?= $e($t->name) ?></option>
        <?php endforeach ?>
      </select>
    </label>
    <label>Goles L: <input type="number" name="home_goals" min="0" style="width:4rem"></label>
    <label>Goles V: <input type="number" name="away_goals" min="0" style="width:4rem"></label>
    <label>🟨 L: <input type="number" name="home_yellows" min="0" value="0" style="width:4rem"></label>
    <label>🟨 V: <input type="number" name="away_yellows" min="0" value="0" style="width:4rem"></label>
    <label>🟨🟨 L: <input type="number" name="home_double_yellows" min="0" value="0" style="width:4rem"></label>
    <label>🟨🟨 V: <input type="number" name="away_double_yellows" min="0" value="0" style="width:4rem"></label>
    <label>🟥 L: <input type="number" name="home_reds" min="0" value="0" style="width:4rem"></label>
    <label>🟥 V: <input type="number" name="away_reds" min="0" value="0" style="width:4rem"></label>
    <label><input type="checkbox" name="home_comeback" value="1"> Remontada L</label>
    <label><input type="checkbox" name="away_comeback" value="1"> Remontada V</label>
    <label><input type="checkbox" name="played" value="1"> Jugado</label>
    <button type="submit" class="btn btn-primary">Crear partido</button>
  </div>
</form>

<h2>Partidos (<?= count($matches) ?>)</h2>
<?php if ($matches === []): ?>
  <p>No hay partidos registrados.</p>
<?php else: ?>
  <table class="table">
    <thead>
      <tr>
        <th>Fecha</th>
        <th>Fase</th>
        <th>Local</th>
        <th>Resultado</th>
        <th>Visitante</th>
        <th>Tarjetas L</th>
        <th>Tarjetas V</th>
        <th>Estado</th>
        <th>Acciones</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($matches as $m): ?>
      <tr>
        <td><?= $e($m->matchDate ?? '—') ?></td>
        <td><?= $e($phaseLabels[$m->phase] ?? $m->phase) ?></td>
        <td><?= $e($m->homeTeamName) ?></td>
        <td><?= $m->homeGoals !== null ? $m->homeGoals . ' – ' . $m->awayGoals : '—' ?></td>
        <td><?= $e($m->awayTeamName) ?></td>
        <td>🟨<?= $m->homeYellows ?> 🟨🟨<?= $m->homeDoubleYellows ?> 🟥<?= $m->homeReds ?></td>
        <td>🟨<?= $m->awayYellows ?> 🟨🟨<?= $m->awayDoubleYellows ?> 🟥<?= $m->awayReds ?></td>
        <td><?= $m->played ? '✅' : '⏳' ?></td>
        <td>
          <a href="<?= $base ?>/admin/game/matches/<?= $m->id ?>">Editar</a>
          <form method="post" action="<?= $base ?>/admin/game/matches/<?= $m->id ?>/delete" style="display:inline">
            <?= $app->csrf()->field() ?>
            <button type="submit" class="btn btn-link" onclick="return confirm('¿Eliminar?')">Eliminar</button>
          </form>
        </td>
      </tr>
    <?php endforeach ?>
    </tbody>
  </table>
<?php endif ?>

<?php $view->endSection(); ?>
