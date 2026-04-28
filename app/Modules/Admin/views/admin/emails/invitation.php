<?php
/** @var callable $e */
/** @var string $fullName */
/** @var string $url */
/** @var string $expires */
/** @var string $siteName */
?>
<p>Hola <?= $e($fullName) ?>,</p>
<p>Has sido invitado a unirte a <strong><?= $e($siteName) ?></strong>. Para crear tu cuenta haz clic en el siguiente enlace:</p>
<p><a href="<?= $e($url) ?>"><?= $e($url) ?></a></p>
<p>Este enlace caduca el <strong><?= $e($expires) ?> UTC</strong> (48 horas desde que se generó). Si caduca, pídele al administrador que te envíe uno nuevo.</p>
<p>Si no esperabas esta invitación, puedes ignorar este correo.</p>
<hr>
<p style="color:#777;font-size:90%">— <?= $e($siteName) ?></p>
