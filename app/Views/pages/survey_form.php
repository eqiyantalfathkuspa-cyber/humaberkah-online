<?php
/** @var string $title */
$error = $error ?? '';
$trackingCode = $trackingCode ?? '';
$ratings = $ratings ?? [];
$suggestion = $suggestion ?? '';
$informationServices = $informationServices ?? [];
$serviceId = $serviceId ?? '';
$surveyType = $surveyType ?? 'tracked';
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
    <p>Survei berkode hanya dapat diisi satu kali untuk pengajuan yang sudah selesai. Survei layanan informasi tidak memerlukan kode lacak dan tidak memverifikasi pengisi; jawaban tidak mencatat identitas pengisi.</p>
    <?php if ($error !== ''): ?>
        <p class="auth-error"><?= e($error) ?></p>
    <?php endif; ?>
    <form method="post" action="/survei" class="auth-form">
        <?= App\Core\Csrf::field() ?>
        <label>
            <span>Pilih jalur survei</span>
            <select name="survey_type" required>
                <option value="tracked" <?= $surveyType === 'tracked' ? 'selected' : '' ?>>Punya kode lacak pengajuan</option>
                <option value="information" <?= $surveyType === 'information' ? 'selected' : '' ?>>Layanan informasi (tanpa kode lacak)</option>
            </select>
        </label>
        <label>
            <span>Kode lacak pengajuan (untuk jalur berkode)</span>
            <input type="text" name="tracking_code" maxlength="23" value="<?= e($trackingCode) ?>">
        </label>
        <label>
            <span>Layanan informasi (untuk jalur tanpa kode lacak)</span>
            <select name="service_id">
                <option value="">Pilih layanan informasi</option>
                <?php foreach ($informationServices as $service): ?>
                    <option value="<?= e((string) $service['id']) ?>" <?= (string) $service['id'] === (string) $serviceId ? 'selected' : '' ?>>
                        <?= e((string) $service['code']) ?> — <?= e((string) $service['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
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
