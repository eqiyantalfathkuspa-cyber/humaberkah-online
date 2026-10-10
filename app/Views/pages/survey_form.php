<?php
/** @var string $title */
$error = $error ?? '';
$trackingCode = $trackingCode ?? '';
$ratings = $ratings ?? [];
$suggestion = $suggestion ?? '';
$aspects = [
    'speed_rating' => 'Kecepatan',
    'clarity_rating' => 'Kejelasan',
    'courtesy_rating' => 'Keramahan',
];
?>
<section class="page-panel">
    <div class="section-header">
        <h2>Survei kepuasan layanan</h2>
    </div>
    <p>Survei hanya dapat diisi satu kali untuk pengajuan yang sudah selesai. Jawaban tidak mencatat identitas pengisi.</p>
    <?php if ($error !== ''): ?>
        <p class="auth-error"><?= e($error) ?></p>
    <?php endif; ?>
    <form method="post" action="/survei" class="auth-form">
        <?= App\Core\Csrf::field() ?>
        <label>
            <span>Kode lacak pengajuan</span>
            <input type="text" name="tracking_code" maxlength="23" value="<?= e($trackingCode) ?>" required>
        </label>
        <?php foreach ($aspects as $field => $label): ?>
            <label>
                <span><?= e($label) ?> (1 sampai 5)</span>
                <select name="<?= e($field) ?>" required>
                    <option value="">Pilih penilaian</option>
                    <?php for ($rating = 1; $rating <= 5; $rating++): ?>
                        <option value="<?= $rating ?>" <?= (string) ($ratings[$field] ?? '') === (string) $rating ? 'selected' : '' ?>><?= $rating ?></option>
                    <?php endfor; ?>
                </select>
            </label>
        <?php endforeach; ?>
        <label>
            <span>Saran (opsional, maksimal 1.000 karakter)</span>
            <textarea name="suggestion" rows="5" maxlength="1000"><?= e($suggestion) ?></textarea>
        </label>
        <div aria-hidden="true" style="position:absolute;left:-10000px">
            <label>Jangan diisi <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
        </div>
        <button type="submit" class="btn primary">Kirim survei</button>
    </form>
</section>
