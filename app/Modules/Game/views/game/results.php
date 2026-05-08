<?php
/** @var \App\Core\View $view */
/** @var \App\Core\Application $app */
/** @var callable $e */
/** @var array<int, \App\Models\GameMatch> $matches */
/** @var array<int, array<int, array{match_id: int, points: float, details: array}>> $teamBreakdowns */

use App\Core\Flags;

/** Format a point value as a signed, colored span. */
$fmtPts = function (float $v): string {
    $cls = $v >= 0 ? 'pts-positive' : 'pts-negative';
    $sign = $v >= 0 ? '+' : '';
    return '<span class="' . $cls . '">' . $sign . number_format($v, 1) . '</span>';
};

$phaseLabels = [
    'group'       => 'Fase de grupos',
    'round_of_32' => 'Dieciseisavos',
    'round_of_16' => 'Octavos de final',
    'quarter'     => 'Cuartos de final',
    'semi'        => 'Semifinales',
    'third_place' => 'Tercer puesto',
    'final'       => 'Final',
];

$view->extend('game.layout', ['title' => '📊 Resultados']);
$view->section('content');
?>

<div class="game-hero">
  <h1>📊 Resultados</h1>
  <p>Partidos jugados en el torneo</p>
</div>

<?php if ($matches === []): ?>
  <div class="alert alert-info">Aún no hay resultados registrados.</div>
<?php else: ?>
  <?php foreach ($matches as $m):
    $homeBreakdown = $teamBreakdowns[$m->homeTeamId][$m->id] ?? null;
    $awayBreakdown = $teamBreakdowns[$m->awayTeamId][$m->id] ?? null;
    $homePoints = $homeBreakdown['points'] ?? 0.0;
    $awayPoints = $awayBreakdown['points'] ?? 0.0;
  ?>
    <div class="match-card-wrapper">
      <div class="match-card match-card-collapsible" role="button" tabindex="0" aria-expanded="false">
        <div class="match-team">
          <?= Flags::img($m->homeTeamName, 28) ?>
          <span><?= $e($m->homeTeamName) ?></span>
          <span class="match-team-pts"><?= $fmtPts($homePoints) ?></span>
        </div>
        <div>
          <div class="match-score"><?= $m->homeGoals ?? '—' ?> – <?= $m->awayGoals ?? '—' ?></div>
          <div class="match-meta">
            <span class="phase-badge phase-<?= $e($m->phase) ?>"><?= $e($phaseLabels[$m->phase] ?? $m->phase) ?></span>
          </div>
        </div>
        <div class="match-team away">
          <?= Flags::img($m->awayTeamName, 28) ?>
          <span><?= $e($m->awayTeamName) ?></span>
          <span class="match-team-pts"><?= $fmtPts($awayPoints) ?></span>
        </div>
        <div class="match-expand-icon">▼</div>
      </div>
      <div class="match-breakdown" hidden>
        <div class="match-breakdown-columns">
          <!-- Home team breakdown -->
          <div class="match-breakdown-col">
            <h4><?= Flags::img($m->homeTeamName, 20) ?> <?= $e($m->homeTeamName) ?></h4>
            <?php if ($homeBreakdown && !empty($homeBreakdown['details'])): ?>
              <ul class="breakdown-list">
                <?php foreach ($homeBreakdown['details'] as $d): ?>
                  <li>
                    <span><?= $e($d['label']) ?></span>
                    <?= $fmtPts($d['value']) ?>
                  </li>
                <?php endforeach ?>
              </ul>
              <div class="breakdown-total">
                <span>Total</span>
                <?= $fmtPts($homePoints) ?>
              </div>
            <?php else: ?>
              <p class="breakdown-empty">Sin desglose</p>
            <?php endif ?>
          </div>
          <!-- Away team breakdown -->
          <div class="match-breakdown-col">
            <h4><?= Flags::img($m->awayTeamName, 20) ?> <?= $e($m->awayTeamName) ?></h4>
            <?php if ($awayBreakdown && !empty($awayBreakdown['details'])): ?>
              <ul class="breakdown-list">
                <?php foreach ($awayBreakdown['details'] as $d): ?>
                  <li>
                    <span><?= $e($d['label']) ?></span>
                    <?= $fmtPts($d['value']) ?>
                  </li>
                <?php endforeach ?>
              </ul>
              <div class="breakdown-total">
                <span>Total</span>
                <?= $fmtPts($awayPoints) ?>
              </div>
            <?php else: ?>
              <p class="breakdown-empty">Sin desglose</p>
            <?php endif ?>
          </div>
        </div>
      </div>
    </div>
  <?php endforeach ?>
<?php endif ?>

<script>
document.querySelectorAll('.match-card-collapsible').forEach(function(card) {
  function toggle() {
    var wrapper = card.closest('.match-card-wrapper');
    var bd = wrapper.querySelector('.match-breakdown');
    var expanded = card.getAttribute('aria-expanded') === 'true';
    card.setAttribute('aria-expanded', String(!expanded));
    bd.hidden = expanded;
  }
  card.addEventListener('click', toggle);
  card.addEventListener('keydown', function(ev) {
    if (ev.key === 'Enter' || ev.key === ' ') { ev.preventDefault(); toggle(); }
  });
});
</script>

<?php $view->endSection(); ?>
