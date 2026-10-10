<?php
/** @var string $status */
/** @var string $stage */
?>
<section class="page-panel">
    <div class="section-header">
        <h2>Status pengajuan</h2>
    </div>
    <p><strong>Status:</strong> <?= e($status ?? '') ?></p>
    <p><strong>Tahap:</strong> <?= e($stage ?? '') ?></p>
</section>
