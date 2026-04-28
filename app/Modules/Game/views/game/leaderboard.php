<?php
/** @var \App\Core\View $view */
/** @var \App\Core\Application $app */
/** @var callable $e */
/** @var array<int, array{user_id: int, full_name: string, total: float, teams: array}> $board */

$view->extend('game.layout', ['title' => 'Clasificación']);
$view->section('content');
?>

<?php if ($board === []): ?>
  <p>Aún no hay participantes con equipos seleccionados.</p>
<?php else: ?>
  <table class="table">
    <thead>
      <tr>
        <th>#</th>
        <th>Jugador</th>
        <th>B1</th>
        <th>B2</th>
        <th>B3</th>
        <th>B4</th>
        <th>B5</th>
        <th>B6</th>
        <th>Total</th>
      </tr>
    </thead>
    <tbody>
    <?php $rank = 0; $prevTotal = null; $pos = 0; ?>
    <?php foreach ($board as $entry): ?>
      <?php
        $pos++;
        if ($entry['total'] !== $prevTotal) {
            $rank = $pos;
            $prevTotal = $entry['total'];
        }
        // Index teams by pot
        $teamsByPot = [];
        foreach ($entry['teams'] as $t) {
            $teamsByPot[$t['pot']] = $t;
        }
      ?>
      <tr>
        <td><strong><?= $rank ?></strong></td>
        <td><?= $e($entry['full_name']) ?></td>
        <?php for ($p = 1; $p <= 6; $p++): ?>
          <td>
            <?php if (isset($teamsByPot[$p])): ?>
              <?= $e($teamsByPot[$p]['team_name']) ?>
              <small>(<?= number_format($teamsByPot[$p]['points'], 1) ?>)</small>
            <?php else: ?>
              —
            <?php endif ?>
          </td>
        <?php endfor ?>
        <td><strong><?= number_format($entry['total'], 1) ?></strong></td>
      </tr>
    <?php endforeach ?>
    </tbody>
  </table>

  <h2>Criterios de puntuación</h2>
  <div class="cards">
    <div class="card">
      <h3>Resultados</h3>
      <ul>
        <li>✅ Victoria: +3</li>
        <li>➖ Empate: +1</li>
      </ul>
    </div>
    <div class="card">
      <h3>Avances</h3>
      <ul>
        <li>Pasar fase de grupos: +3</li>
        <li>Octavos: +3</li>
        <li>Cuartos: +4</li>
        <li>Semifinales: +6</li>
        <li>Final: +8</li>
        <li>🏆 Campeón: +12</li>
      </ul>
    </div>
    <div class="card">
      <h3>Bonus</h3>
      <ul>
        <li>3+ goles en un partido: +2</li>
        <li>Portería a cero: +1</li>
        <li>Remontada: +1</li>
      </ul>
    </div>
    <div class="card">
      <h3>Penalizaciones</h3>
      <ul>
        <li>Amarilla: −0,2</li>
        <li>Doble amarilla: −1</li>
        <li>Roja: −2</li>
        <li>Recibir 3+ goles: −2</li>
        <li>Último del grupo: −1</li>
      </ul>
    </div>
    <div class="card">
      <h3>Premios individuales</h3>
      <ul>
        <li>MVP: +3</li>
        <li>Goleador: +2</li>
        <li>Portero: +2</li>
        <li>Joven: +2</li>
      </ul>
    </div>
  </div>
<?php endif ?>

<?php $view->endSection(); ?>
