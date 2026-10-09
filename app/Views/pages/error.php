<?php
/** @var string $title */
/** @var string $message */
?>
<section class="error-box">
    <p class="kicker">Status <?= e($title) ?></p>
    <h2>Terjadi masalah</h2>
    <p><?= e($message) ?></p>
    <a class="btn" href="/">Kembali ke beranda</a>
</section>
