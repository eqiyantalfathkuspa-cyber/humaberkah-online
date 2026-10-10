<?php
/** @var string $error */
$error = $error ?? '';
?>
<section class="page-panel">
    <div class="section-header">
        <h2>Lacak KAHAYAN AMANAH</h2>
    </div>
    <?php if ($error !== ''): ?>
        <p class="auth-error"><?= e($error) ?></p>
    <?php endif; ?>
    <form method="post" action="/lacak-aduan" class="auth-form">
        <?= App\Core\Csrf::field() ?>
        <label>
            <span>Kode lacak</span>
            <input type="text" name="track_token" maxlength="23" required>
        </label>
        <button type="submit" class="btn primary">Cek status</button>
    </form>
</section>
