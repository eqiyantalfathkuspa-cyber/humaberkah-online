<?php
/** @var string $title */
$submitted = (bool) ($submitted ?? false);
?>
<section class="page-panel">
    <div class="section-header">
        <h2>Terima kasih</h2>
    </div>
    <p><?= $submitted ? 'Terima kasih atas masukan Anda.' : 'Survei telah diproses.' ?></p>
    <p><a href="/">Kembali ke beranda</a></p>
</section>
