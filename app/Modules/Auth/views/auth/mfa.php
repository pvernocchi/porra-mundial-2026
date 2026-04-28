<?php
/** @var \App\Core\Application $app */
/** @var callable $e */
/** @var ?string $error */
/** @var string $next */
/** @var bool $hasTotp */
/** @var bool $hasWebauthn */
$base = $e($app->baseUrl());
?>
<!doctype html>
<html lang="es"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Verificación · <?= $e((string)$app->config()->get('site.name', 'Porra')) ?></title>
<link rel="stylesheet" href="<?= $base ?>/assets/css/app.css">
</head><body class="install">
<main class="container narrow">
<h1>Verificación en dos pasos</h1>
<?php if ($error): ?><div class="alert alert-danger"><?= $e($error) ?></div><?php endif ?>

<?php if ($hasTotp): ?>
<form method="post" action="<?= $base ?>/login/mfa">
  <?= $app->csrf()->field() ?>
  <input type="hidden" name="next" value="<?= $e($next) ?>">
  <input type="hidden" name="method" value="totp">
  <label>Código de la aplicación de autenticación
    <input type="text" name="code" inputmode="numeric" pattern="\d{6}" maxlength="6" required autofocus autocomplete="one-time-code">
  </label>
  <button class="btn btn-primary" type="submit">Verificar</button>
</form>
<?php endif ?>

<?php if ($hasWebauthn): ?>
<?php if ($hasTotp): ?><hr><?php endif ?>
<p><?= $hasTotp ? 'O usa' : 'Usa' ?> una llave de seguridad (YubiKey), Windows Hello o Touch ID:</p>
<button class="btn <?= $hasTotp ? 'btn-secondary' : 'btn-primary' ?>" id="webauthn-btn" type="button">Usar llave de seguridad / Windows Hello</button>
<p class="muted"><small>Requiere que el sitio se sirva sobre HTTPS.</small></p>
<p id="webauthn-status" class="muted" style="display:none"></p>
<script>
async function _doWebauthnLogin() {
  const btn = document.getElementById('webauthn-btn');
  const status = document.getElementById('webauthn-status');
  btn.disabled = true;
  btn.textContent = 'Verificando…';
  status.style.display = 'none';
  try {
    const optRes = await fetch('<?= $base ?>/api/webauthn/login-options', {credentials: 'same-origin'});
    if (!optRes.ok) { const e = await optRes.json(); throw new Error(e.error || 'Error obteniendo opciones.'); }
    const options = await optRes.json();
    options.challenge = _b64url2buf(options.challenge);
    if (options.allowCredentials) {
      options.allowCredentials = options.allowCredentials.map(c => ({...c, id: _b64url2buf(c.id)}));
    }
    const assertion = await navigator.credentials.get({publicKey: options});
    btn.textContent = 'Validando…';
    const body = {
      id: _buf2b64url(assertion.rawId),
      rawId: _buf2b64url(assertion.rawId),
      type: assertion.type,
      response: {
        clientDataJSON: _buf2b64url(assertion.response.clientDataJSON),
        authenticatorData: _buf2b64url(assertion.response.authenticatorData),
        signature: _buf2b64url(assertion.response.signature),
        userHandle: assertion.response.userHandle ? _buf2b64url(assertion.response.userHandle) : null,
      },
    };
    const loginRes = await fetch('<?= $base ?>/api/webauthn/login', {
      method: 'POST', credentials: 'same-origin',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify(body),
    });
    const result = await loginRes.json();
    if (!loginRes.ok || !result.ok) throw new Error(result.error || 'Verificación fallida.');
    window.location.href = result.redirect || '<?= $base ?>/admin';
  } catch (e) {
    if (e.name === 'NotAllowedError') {
      status.textContent = 'Operación cancelada.';
      status.style.display = '';
    } else {
      status.textContent = e.message || 'Error inesperado.';
      status.style.display = '';
    }
    btn.disabled = false;
    btn.textContent = 'Usar llave de seguridad / Windows Hello';
  }
}
document.getElementById('webauthn-btn')?.addEventListener('click', _doWebauthnLogin);
<?php if (!$hasTotp): ?>
// Auto-trigger WebAuthn when it's the only MFA method available.
if (window.PublicKeyCredential) {
  setTimeout(_doWebauthnLogin, 300);
} else {
  document.getElementById('webauthn-status').textContent = 'Tu navegador no soporta WebAuthn. Usa un navegador moderno (Chrome, Edge, Firefox, Safari).';
  document.getElementById('webauthn-status').style.display = '';
  document.getElementById('webauthn-btn').disabled = true;
}
<?php endif ?>
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
<?php endif ?>

<hr>
<details><summary>¿No tienes acceso al segundo factor? Usa un código de recuperación</summary>
  <form method="post" action="<?= $base ?>/login/mfa" style="margin-top:1em">
    <?= $app->csrf()->field() ?>
    <input type="hidden" name="next" value="<?= $e($next) ?>">
    <input type="hidden" name="method" value="recovery">
    <label>Código de recuperación <input type="text" name="code" required></label>
    <button class="btn btn-secondary" type="submit">Verificar</button>
  </form>
</details>

</main></body></html>
