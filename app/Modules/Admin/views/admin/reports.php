<?php
/** @var \App\Core\View $view */
/** @var \App\Core\Application $app */
/** @var callable $e */
/** @var array<int, array<string, mixed>> $topLeaders */
/** @var array<int, array<string, mixed>> $topUpward */
/** @var array<int, array<string, mixed>> $topDownward */
/** @var array<int, array<string, mixed>> $bestTeams */
/** @var array<int, array<string, mixed>> $worstTeams */
/** @var array<int, array<string, mixed>> $mostPicked */
/** @var array<int, array{teams: array, participants: array}> $identicalGroups */
/** @var ?string $previousAt */
/** @var bool $hasPrevious */
/** @var bool $snapshotSaved */
/** @var string $teamsText */
$view->extend('admin.layout', ['title' => 'Reportes para difusión']);
$view->section('content');
$base = $e($app->baseUrl());
?>
<p class="muted">
  Resumen del estado del juego para copiar y pegar en un chat de Teams.
  <?php if ($hasPrevious): ?>
    Movimientos comparados con el snapshot del <strong><?= $e((string)$previousAt) ?></strong> UTC.
  <?php else: ?>
    Aún no hay snapshot anterior; toma uno para empezar a registrar movimientos.
  <?php endif ?>
</p>

<?php if ($snapshotSaved): ?>
  <div class="alert alert-info">Snapshot del ranking actual guardado correctamente.</div>
<?php endif ?>

<form method="post" action="<?= $base ?>/admin/reports/snapshot" style="margin-bottom:1.5em">
  <?= $app->csrf()->field() ?>
  <button type="submit" class="btn">Guardar snapshot del ranking actual</button>
</form>

<h2>Texto listo para Teams</h2>
<p class="muted">Copia el contenido de abajo y pégalo en el chat de Teams.</p>
<textarea id="report-teams-text" rows="20" style="width:100%;font-family:ui-monospace,Consolas,monospace" readonly><?= $e($teamsText) ?></textarea>
<p>
  <button type="button" class="btn" onclick="(function(){var t=document.getElementById('report-teams-text');t.select();document.execCommand('copy');})()">Copiar al portapapeles</button>
</p>

<h2>Top 5 líderes</h2>
<table class="table">
  <thead><tr><th>#</th><th>Participante</th><th>Puntos</th></tr></thead>
  <tbody>
  <?php foreach ($topLeaders as $row): ?>
    <tr>
      <td><?= $e((string)$row['position']) ?></td>
      <td><?= $e((string)$row['display_name']) ?></td>
      <td><?= $e((string)$row['total']) ?></td>
    </tr>
  <?php endforeach ?>
  <?php if ($topLeaders === []): ?>
    <tr><td colspan="3" class="muted">Sin datos.</td></tr>
  <?php endif ?>
  </tbody>
</table>

<h2>Movimientos en el ranking</h2>
<?php if (!$hasPrevious): ?>
  <p class="muted">Aún no hay un snapshot anterior con el que comparar.</p>
<?php else: ?>
  <h3>Top 3 ascendentes</h3>
  <table class="table">
    <thead><tr><th>Participante</th><th>Anterior</th><th>Actual</th><th>Δ</th></tr></thead>
    <tbody>
    <?php foreach ($topUpward as $row): ?>
      <tr>
        <td><?= $e((string)$row['display_name']) ?></td>
        <td><?= $e((string)$row['previous_position']) ?></td>
        <td><?= $e((string)$row['current_position']) ?></td>
        <td>+<?= $e((string)$row['delta']) ?></td>
      </tr>
    <?php endforeach ?>
    <?php if ($topUpward === []): ?>
      <tr><td colspan="4" class="muted">Sin movimientos ascendentes significativos.</td></tr>
    <?php endif ?>
    </tbody>
  </table>

  <h3>Top 3 descendentes</h3>
  <table class="table">
    <thead><tr><th>Participante</th><th>Anterior</th><th>Actual</th><th>Δ</th></tr></thead>
    <tbody>
    <?php foreach ($topDownward as $row): ?>
      <tr>
        <td><?= $e((string)$row['display_name']) ?></td>
        <td><?= $e((string)$row['previous_position']) ?></td>
        <td><?= $e((string)$row['current_position']) ?></td>
        <td><?= $e((string)$row['delta']) ?></td>
      </tr>
    <?php endforeach ?>
    <?php if ($topDownward === []): ?>
      <tr><td colspan="4" class="muted">Sin movimientos descendentes significativos.</td></tr>
    <?php endif ?>
    </tbody>
  </table>
<?php endif ?>

<h2>Selecciones nacionales</h2>
<h3>Mejor rendimiento</h3>
<table class="table">
  <thead><tr><th>Selección</th><th>Bombo</th><th>Puntos</th></tr></thead>
  <tbody>
  <?php foreach ($bestTeams as $row): ?>
    <tr>
      <td><?= $e((string)$row['team_name']) ?></td>
      <td><?= $e((string)$row['pot']) ?></td>
      <td><?= $e((string)$row['points']) ?></td>
    </tr>
  <?php endforeach ?>
  <?php if ($bestTeams === []): ?>
    <tr><td colspan="3" class="muted">Sin datos.</td></tr>
  <?php endif ?>
  </tbody>
</table>

<h3>Peor rendimiento</h3>
<table class="table">
  <thead><tr><th>Selección</th><th>Bombo</th><th>Puntos</th></tr></thead>
  <tbody>
  <?php foreach ($worstTeams as $row): ?>
    <tr>
      <td><?= $e((string)$row['team_name']) ?></td>
      <td><?= $e((string)$row['pot']) ?></td>
      <td><?= $e((string)$row['points']) ?></td>
    </tr>
  <?php endforeach ?>
  <?php if ($worstTeams === []): ?>
    <tr><td colspan="3" class="muted">Sin datos.</td></tr>
  <?php endif ?>
  </tbody>
</table>

<h3>Más elegidas</h3>
<table class="table">
  <thead><tr><th>Selección</th><th>Bombo</th><th>Elecciones</th></tr></thead>
  <tbody>
  <?php foreach ($mostPicked as $row): ?>
    <tr>
      <td><?= $e((string)$row['team_name']) ?></td>
      <td><?= $e((string)$row['pot']) ?></td>
      <td><?= $e((string)$row['picks']) ?></td>
    </tr>
  <?php endforeach ?>
  <?php if ($mostPicked === []): ?>
    <tr><td colspan="3" class="muted">Sin elecciones registradas.</td></tr>
  <?php endif ?>
  </tbody>
</table>

<h2>Participantes con elecciones idénticas</h2>
<?php if ($identicalGroups === []): ?>
  <p class="muted">No hay coincidencias entre participantes.</p>
<?php else: ?>
  <?php foreach ($identicalGroups as $g): ?>
    <table class="table">
      <thead>
        <tr><th colspan="3">
          Selecciones:
          <?php $teamLabels = [];
          foreach ($g['teams'] as $t) {
              $teamLabels[] = 'B' . (int)$t['pot'] . ': ' . (string)$t['team_name'];
          }
          echo $e(implode(' · ', $teamLabels)); ?>
        </th></tr>
        <tr><th>Participante</th><th>Club</th></tr>
      </thead>
      <tbody>
      <?php foreach ($g['participants'] as $p): ?>
        <tr>
          <td><?= $e((string)$p['full_name']) ?></td>
          <td><?= $e((string)$p['team_name']) ?></td>
        </tr>
      <?php endforeach ?>
      </tbody>
    </table>
  <?php endforeach ?>
<?php endif ?>

<?php $view->endSection() ?>
