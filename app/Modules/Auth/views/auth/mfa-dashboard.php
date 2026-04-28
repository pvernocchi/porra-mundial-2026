<?php
/** @var \App\Core\Application $app */
/** @var callable $e */
/** @var array<int, array<string, mixed>> $creds */
/** @var int $unusedCodes */
/** @var bool $rpAllowed */
/** @var ?string $msg */
/** @var bool $pending */
$base = $e($app->baseUrl());
?>
<!doctype html><html lang="es"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>MFA · <?= $e((string)$app->config()->get('site.name', 'Porra')) ?></title>
<link rel="stylesheet" href="<?= $base ?>/assets/css/app.css"></head>
<body class="<?= $pending ? 'install' : 'admin' ?>">
<?php if (!$pending): ?>
<header class="topbar"><div class="container">
  <a class="brand" href="<?= $base ?>/admin"><?= $e((string)$app->config()->get('site.name', 'Porra')) ?></a>
  <nav><a href="<?= $base ?>/admin">Inicio</a></nav>
</div></header>
<?php endif ?>
<main class="container <?= $pending ? 'narrow' : '' ?>">
<h1>Métodos de autenticación de dos factores (MFA)</h1>

<?php if ($pending): ?>
  <div class="alert alert-warning">Tu cuenta requiere MFA. Configura al menos un método para continuar.</div>
<?php endif ?>

<?php if ($msg): ?><div class="alert alert-info"><?= $e($msg) ?></div><?php endif ?>

<h2>Métodos registrados</h2>
<?php if ($creds === []): ?>
  <p class="muted">Aún no tienes métodos MFA registrados.</p>
<?php else: ?>
  <table class="table">
    <thead><tr><th>Tipo</th><th>Etiqueta</th><th>Creado</th><th>Último uso</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($creds as $c): ?>
      <tr>
        <td><?= $e((string)$c['type']) ?></td>
        <td><?= $e((string)$c['label']) ?></td>
        <td><?= $e((string)$c['created_at']) ?></td>
        <td><?= $e((string)($c['last_used_at'] ?? '—')) ?></td>
        <td>
          <form method="post" action="<?= $base ?>/account/mfa/<?= (int)$c['id'] ?>/delete" style="display:inline"
                onsubmit="return confirm('¿Eliminar este método MFA?');">
            <?= $app->csrf()->field() ?>
            <button class="btn btn-small btn-danger" type="submit">Eliminar</button>
          </form>
        </td>
      </tr>
    <?php endforeach ?>
    </tbody>
  </table>
<?php endif ?>

<h2>Añadir un método</h2>
<div class="cards">
  <div class="card">
    <h3>Aplicación de autenticación (TOTP)</h3>
    <p>Google Authenticator, 1Password, Authy, etc.</p>
    <a class="btn btn-primary" href="<?= $base ?>/account/mfa/totp/new">Configurar TOTP</a>
  </div>
  <div class="card">
    <h3>Llave de seguridad / Windows Hello</h3>
    <p>YubiKey, llaves FIDO2, Windows Hello, Touch ID. <strong>Requiere HTTPS.</strong></p>
    <?php if ($rpAllowed): ?>
      <button class="btn btn-primary" id="add-webauthn">Añadir llave</button>
    <?php else: ?>
      <button class="btn btn-primary" disabled>Requiere HTTPS</button>
      <p class="muted"><small>Activa SSL en tu hosting (AutoSSL/Let's Encrypt) para habilitar esta opción.</small></p>
    <?php endif ?>
  </div>
</div>

<?php if ($creds !== []): ?>
<h2>Códigos de recuperación</h2>
<p>Te quedan <strong><?= (int)$unusedCodes ?></strong> códigos sin usar.</p>
<form method="post" action="<?= $base ?>/account/mfa/recovery"
      onsubmit="return confirm('Esto invalidará los códigos anteriores. ¿Continuar?');">
  <?= $app->csrf()->field() ?>
  <button class="btn btn-secondary" type="submit">Regenerar códigos</button>
</form>
<?php endif ?>

<script>
document.getElementById('add-webauthn')?.addEventListener('click', async () => {
  const btn = document.getElementById('add-webauthn');
  btn.disabled = true;
  btn.textContent = 'Registrando…';
  try {
    const optRes = await fetch('<?= $base ?>/account/mfa/webauthn/register-options', {credentials: 'same-origin'});
    if (!optRes.ok) { const e = await optRes.json(); throw new Error(e.error || 'Error obteniendo opciones.'); }
    const options = await optRes.json();
    // Decode base64url fields for the WebAuthn API.
    options.challenge = _b64url2buf(options.challenge);
    options.user.id = _b64url2buf(options.user.id);
    if (options.excludeCredentials) {
      options.excludeCredentials = options.excludeCredentials.map(c => ({...c, id: _b64url2buf(c.id)}));
    }
    const credential = await navigator.credentials.create({publicKey: options});
    const label = prompt('Nombre para esta llave (opcional):', '') || '';
    const body = {
      id: _buf2b64url(credential.rawId),
      rawId: _buf2b64url(credential.rawId),
      type: credential.type,
      response: {
        clientDataJSON: _buf2b64url(credential.response.clientDataJSON),
        attestationObject: _buf2b64url(credential.response.attestationObject),
        transports: credential.response.getTransports ? credential.response.getTransports() : [],
      },
      label: label,
    };
    const regRes = await fetch('<?= $base ?>/account/mfa/webauthn/register', {
      method: 'POST', credentials: 'same-origin',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify(body),
    });
    const result = await regRes.json();
    if (!regRes.ok || !result.ok) throw new Error(result.error || 'Error al registrar la llave.');
    if (result.recoveryCodes) {
      alert('Códigos de recuperación (guárdalos en un lugar seguro):\n\n' + result.recoveryCodes.join('\n'));
    }
    location.reload();
  } catch (e) {
    if (e.name === 'NotAllowedError') { alert('Operación cancelada por el usuario.'); }
    else { alert(e.message || 'Error inesperado.'); }
    btn.disabled = false;
    btn.textContent = 'Añadir llave';
  }
});
function _b64url2buf(b64) {
  const s = b64.replace(/-/g, '+').replace(/_/g, '/');
  const bin = atob(s + '='.repeat((4 - s.length % 4) % 4));
  return Uint8Array.from(bin, c => c.charCodeAt(0)).buffer;
}
function _buf2b64url(buf) {
  const bytes = new Uint8Array(buf);
  let s = '';
  bytes.forEach(b => s += String.fromCharCode(b));
  return btoa(s).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
}
</script>
</main></body></html>
