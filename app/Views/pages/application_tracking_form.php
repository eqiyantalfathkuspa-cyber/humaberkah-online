<?php
/** @var string $error */
/** @var string $trackingCode */
$error = $error ?? '';
$trackingCode = $trackingCode ?? '';
?>
<section class="page-panel">
    <div class="section-header">
        <h2>Cek status pengajuan</h2>
    </div>
    <?php if ($error !== ''): ?>
        <p class="auth-error"><?= e($error) ?></p>
    <?php endif; ?>
    <form method="post" action="/lacak" class="auth-form">
        <?= App\Core\Csrf::field() ?>
        <label>
            <span>Kode lacak</span>
            <input type="text" name="tracking_code" value="<?= e($trackingCode) ?>" maxlength="23" required>
        </label>
        <button type="submit" class="btn primary">Cek status</button>
    </form>
</section>
