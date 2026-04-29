<?php
/** @var \App\Core\View $view */
/** @var \App\Core\Application $app */
/** @var callable $e */
/** @var \App\Models\User $user */
/** @var int $picksCount */
/** @var float $totalScore */
/** @var int $rank */
/** @var int $totalPlayers */
/** @var array<int, array{id: int, name: string, pot: int}> $pickedTeams */

use App\Core\Flags;

$view->extend('game.layout', ['title' => 'Inicio']);
$view->section('content');
$base    = $e($app->baseUrl());
$isAdmin = $app->auth()->canManageUsers();

$rankLabel  = $rank > 0 ? '#' . $rank : '—';
$rankSub    = $totalPlayers > 0 ? 'de ' . $totalPlayers . ' jugadores' : 'Sin clasificación';
$picksDone  = $picksCount >= 6;

// Countdown to Mundial 2026 opening match (11 Jun 2026, 18:00 UTC)
$mundialDate      = new \DateTime('2026-06-11 18:00:00', new \DateTimeZone('UTC'));
$now              = new \DateTime('now', new \DateTimeZone('UTC'));
$diff             = ($now < $mundialDate) ? $now->diff($mundialDate) : null;
$cdDays           = $diff ? $diff->days : 0;
$cdHours          = $diff ? $diff->h : 0;
$cdMins           = $diff ? $diff->i : 0;
$cdSecs           = $diff ? $diff->s : 0;
$mundialStarted   = ($diff === null);
$mundialTimestamp = $mundialDate->getTimestamp();
$picksProgress    = $picksCount > 0 ? (int)round($picksCount / 6 * 100) : 0;
?>

<!-- ░░ HERO ░░ -->
<section class="h-hero">
  <div class="h-hero-aurora" aria-hidden="true"></div>
  <div class="h-hero-floaters" aria-hidden="true">
    <span class="h-hero-f1">⚽</span>
    <span class="h-hero-f2">🏆</span>
    <span class="h-hero-f3">🌍</span>
  </div>
  <div class="h-hero-content">
    <div class="h-hero-balls" aria-hidden="true">⚽ 🏆 🌍</div>
    <h1 class="h-hero-title">¡Hola, <?= $e($user->fullName) ?>! 👋</h1>
    <p class="h-hero-sub">
      Bienvenido a la <strong>Porra del Mundial 2026</strong>.<br>
      Elige tus equipos, acumula puntos y sube al podio. 🚀
    </p>
    <?php if (!$picksDone): ?>
      <a href="<?= $base ?>/game/picks" class="h-hero-cta">
        ✏️ Completar mis selecciones &rarr;
      </a>
    <?php else: ?>
      <span class="h-hero-badge">✅ ¡Selecciones completas!</span>
    <?php endif ?>
    <?php if (!$mundialStarted): ?>
    <div class="h-countdown" id="h-countdown" data-target="<?= $mundialTimestamp ?>">
      <div class="h-cd-unit">
        <span class="h-cd-value" id="cd-days"><?= str_pad((string)$cdDays, 2, '0', STR_PAD_LEFT) ?></span>
        <span class="h-cd-label">Días</span>
      </div>
      <div class="h-cd-unit">
        <span class="h-cd-value" id="cd-hours"><?= str_pad((string)$cdHours, 2, '0', STR_PAD_LEFT) ?></span>
        <span class="h-cd-label">Horas</span>
      </div>
      <div class="h-cd-unit">
        <span class="h-cd-value" id="cd-mins"><?= str_pad((string)$cdMins, 2, '0', STR_PAD_LEFT) ?></span>
        <span class="h-cd-label">Min</span>
      </div>
      <div class="h-cd-unit">
        <span class="h-cd-value" id="cd-secs"><?= str_pad((string)$cdSecs, 2, '0', STR_PAD_LEFT) ?></span>
        <span class="h-cd-label">Seg</span>
      </div>
    </div>
    <?php else: ?>
    <p class="h-countdown-started">🎉 ¡El Mundial ya está en marcha!</p>
    <?php endif ?>
  </div>
</section>

<!-- ░░ STATS ░░ -->
<section class="h-stats">
  <div class="h-stat h-stat--picks">
    <span class="h-stat-emoji">✅</span>
    <span class="h-stat-value">
      <span class="js-count" data-target="<?= $picksCount ?>">0</span><small>/6</small>
    </span>
    <span class="h-stat-label">Equipos elegidos</span>
    <div class="h-picks-bar">
      <div class="h-picks-bar-fill" style="width:<?= $picksProgress ?>%"></div>
    </div>
  </div>
  <div class="h-stat h-stat--score">
    <span class="h-stat-emoji">⭐</span>
    <span class="h-stat-value"><?= number_format($totalScore, 1) ?></span>
    <span class="h-stat-label">Puntos totales</span>
  </div>
  <div class="h-stat h-stat--rank">
    <span class="h-stat-emoji">🏅</span>
    <span class="h-stat-value"><?= $rankLabel ?></span>
    <span class="h-stat-label"><?= $rankSub ?></span>
  </div>
</section>

<!-- ░░ NAV CARDS ░░ -->
<section class="h-cards">

  <a href="<?= $base ?>/game/picks" class="h-card h-card--picks">
    <span class="h-card-icon-box">⚽</span>
    <div class="h-card-body">
      <h2>Selecciones</h2>
      <p>Elige 1 equipo de cada bombo para competir en la porra</p>
      <?php if (!$picksDone): ?>
        <span class="h-card-badge h-card-badge--pending">⚠️ Pendientes</span>
      <?php else: ?>
        <span class="h-card-badge h-card-badge--complete">✅ Completadas</span>
      <?php endif ?>
    </div>
    <span class="h-card-arrow">›</span>
  </a>

  <a href="<?= $base ?>/game/my-scores" class="h-card h-card--scores">
    <span class="h-card-icon-box">📊</span>
    <div class="h-card-body">
      <h2>Mis Puntos</h2>
      <p>Consulta tu puntuación detallada y desglose por equipo</p>
    </div>
    <span class="h-card-arrow">›</span>
  </a>

  <a href="<?= $base ?>/game/leaderboard" class="h-card h-card--leaderboard">
    <span class="h-card-icon-box">🏆</span>
    <div class="h-card-body">
      <h2>Clasificación</h2>
      <p>Mira el ranking general y compara con otros jugadores</p>
    </div>
    <span class="h-card-arrow">›</span>
  </a>

  <a href="<?= $base ?>/game/results" class="h-card h-card--results">
    <span class="h-card-icon-box">📅</span>
    <div class="h-card-body">
      <h2>Resultados</h2>
      <p>Consulta los partidos jugados y sus marcadores</p>
    </div>
    <span class="h-card-arrow">›</span>
  </a>

  <a href="<?= $base ?>/account/mfa" class="h-card h-card--account">
    <span class="h-card-icon-box">👤</span>
    <div class="h-card-body">
      <h2>Mi Cuenta</h2>
      <p>Configura tu seguridad y autenticación en dos pasos</p>
    </div>
    <span class="h-card-arrow">›</span>
  </a>

  <?php if ($isAdmin): ?>
  <a href="<?= $base ?>/admin" class="h-card h-card--admin">
    <span class="h-card-icon-box">⚙️</span>
    <div class="h-card-body">
      <h2>Administración</h2>
      <p>Gestiona partidos, usuarios y configuración del torneo</p>
    </div>
    <span class="h-card-arrow">›</span>
  </a>
  <?php endif ?>

</section>

<!-- ░░ PICKED TEAMS ░░ -->
<?php if (!empty($pickedTeams)): ?>
<section class="h-teams">
  <h2 class="h-teams-title">🎯 Tus Selecciones</h2>
  <div class="h-teams-grid">
    <?php foreach ($pickedTeams as $team): ?>
      <div class="h-team-card">
        <span class="h-team-pot-badge">Bombo <?= $team['pot'] ?></span>
        <?= Flags::img($team['name'], 32) ?>
        <span class="h-team-name"><?= $e($team['name']) ?></span>
      </div>
    <?php endforeach ?>
  </div>
</section>
<?php endif ?>

<!-- ░░ CTA ░░ -->
<section class="h-cta">
  <?php if (!$picksDone): ?>
    <p class="h-cta-title">🎮 ¡Completa tus selecciones!</p>
    <p class="h-cta-sub">Elige un equipo de cada uno de los 6 bombos para participar plenamente en la porra.</p>
    <a href="<?= $base ?>/game/picks" class="h-cta-btn">✏️ Hacer mis selecciones &rarr;</a>
  <?php else: ?>
    <p class="h-cta-title">🏆 ¡Ya estás listo para ganar!</p>
    <p class="h-cta-sub">Tus selecciones están completas. Sigue la clasificación y anima a tus equipos.</p>
    <a href="<?= $base ?>/game/leaderboard" class="h-cta-btn">📊 Ver clasificación &rarr;</a>
  <?php endif ?>
</section>

<script>
/* Countdown to Mundial 2026 */
(function () {
  var el = document.getElementById('h-countdown');
  if (!el) return;
  var target = parseInt(el.dataset.target, 10) * 1000;
  function pad(n) { return String(n).padStart(2, '0'); }
  function tick() {
    var diff = target - Date.now();
    if (diff <= 0) {
      el.innerHTML = '<p class="h-countdown-started">\uD83C\uDF89 \u00A1El Mundial ya est\u00E1 en marcha!</p>';
      return;
    }
    var d = Math.floor(diff / 86400000);
    var h = Math.floor((diff % 86400000) / 3600000);
    var m = Math.floor((diff % 3600000) / 60000);
    var s = Math.floor((diff % 60000) / 1000);
    var ds = document.getElementById('cd-days');
    var hs = document.getElementById('cd-hours');
    var ms = document.getElementById('cd-mins');
    var ss = document.getElementById('cd-secs');
    if (ds) ds.textContent = pad(d);
    if (hs) hs.textContent = pad(h);
    if (ms) ms.textContent = pad(m);
    if (ss) ss.textContent = pad(s);
  }
  tick();
  setInterval(tick, 1000);
})();

/* Count-up animation for picks stat */
(function () {
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
  document.querySelectorAll('.js-count').forEach(function (el) {
    var target = parseFloat(el.dataset.target) || 0;
    var start  = performance.now();
    var dur    = 900;
    function step(now) {
      var p = Math.min((now - start) / dur, 1);
      var e = 1 - Math.pow(1 - p, 3);
      el.textContent = Math.round(target * e);
      if (p < 1) requestAnimationFrame(step);
    }
    el.textContent = '0';
    requestAnimationFrame(step);
  });
})();

/* Card tilt on desktop hover */
(function () {
  if ('ontouchstart' in window) return;
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
  document.querySelectorAll('.h-card').forEach(function (card) {
    card.addEventListener('mousemove', function (e) {
      var r = card.getBoundingClientRect();
      var x = (e.clientX - r.left) / r.width  - 0.5;
      var y = (e.clientY - r.top)  / r.height - 0.5;
      card.style.transform = 'translateY(-5px) perspective(600px) rotateX(' + (-y * 5) + 'deg) rotateY(' + (x * 5) + 'deg)';
    });
    card.addEventListener('mouseleave', function () {
      card.style.transform = '';
    });
  });
})();
</script>

<?php $view->endSection(); ?>
