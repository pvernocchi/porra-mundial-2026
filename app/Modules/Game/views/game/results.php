<?php
/** @var \App\Core\View $view */
/** @var \App\Core\Application $app */
/** @var callable $e */
/** @var array<int, \App\Models\GameMatch> $matches */

$phaseLabels = [
    'group'       => 'Fase de grupos',
    'round_of_32' => 'Dieciseisavos',
    'round_of_16' => 'Octavos de final',
    'quarter'     => 'Cuartos de final',
    'semi'        => 'Semifinales',
    'third_place' => 'Tercer puesto',
    'final'       => 'Final',
];

$view->extend('game.layout', ['title' => 'Resultados']);
$view->section('content');
?>

<?php if ($matches === []): ?>
  <p>Aún no hay resultados registrados.</p>
<?php else: ?>
  <table class="table">
    <thead>
      <tr>
        <th>Fecha</th>
        <th>Fase</th>
        <th>Local</th>
        <th>Resultado</th>
        <th>Visitante</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($matches as $m): ?>
      <tr>
        <td><?= $e($m->matchDate ?? '—') ?></td>
        <td><?= $e($phaseLabels[$m->phase] ?? $m->phase) ?></td>
        <td><?= $e($m->homeTeamName) ?></td>
        <td><strong><?= $m->homeGoals ?? '—' ?> – <?= $m->awayGoals ?? '—' ?></strong></td>
        <td><?= $e($m->awayTeamName) ?></td>
      </tr>
    <?php endforeach ?>
    </tbody>
  </table>
<?php endif ?>

<?php $view->endSection(); ?>
