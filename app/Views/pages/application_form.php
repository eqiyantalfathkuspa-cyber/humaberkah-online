<?php
/** @var array<string, mixed> $service */
$service = $service ?? [];
$error = $error ?? '';
$requestText = $requestText ?? '';
$acceptsDocuments = in_array(($service['code'] ?? ''), ['TU-01', 'TU-02', 'KUR-02', 'SAR-01', 'HUM-01'], true);
?>
<section class="page-panel">
    <div class="section-header">
        <h2>Ajukan layanan <?= e((string) ($service['code'] ?? '')) ?></h2>
    </div>
    <p><?= e((string) ($service['name'] ?? '')) ?></p>
    <?php if (($service['code'] ?? '') === 'KES-01'): ?>
        <p class="notice">Isi konseling dan data pribadi sensitif tidak disimpan di sistem.</p>
    <?php elseif (in_array(($service['code'] ?? ''), ['KES-02', 'KES-04'], true)): ?>
        <p class="notice">Permintaan terkait siswa: kirim hanya data minimum yang diperlukan. Isi permintaan hanya ditampilkan kepada administrator, bukan pada cek status publik.</p>
    <?php endif; ?>
    <p class="lede">Pengajuan dikaitkan dengan akun Anda. Jangan masukkan kata sandi atau informasi sensitif yang tidak diperlukan.</p>
    <?php if ($error !== ''): ?>
        <p class="auth-error"><?= e($error) ?></p>
    <?php endif; ?>
    <form method="post" enctype="multipart/form-data" action="/layanan/<?= e((string) ($service['code'] ?? '')) ?>/ajukan" class="auth-form">
        <?= App\Core\Csrf::field() ?>
        <label>
            <span>Uraian permintaan (maksimal 4.000 karakter)</span>
            <textarea name="request_text" maxlength="4000" rows="6" required><?= e($requestText) ?></textarea>
        </label>
        <?php if ($acceptsDocuments): ?>
            <label>
                <span>Lampiran opsional (maksimal 3 JPG/PNG, 5 MB per berkas)</span>
                <input type="file" name="attachments[]" accept=".jpg,.jpeg,.png,image/jpeg,image/png" multiple>
            </label>
        <?php endif; ?>
        <button type="submit" class="btn primary">Kirim pengajuan</button>
    </form>
    <p><a href="/layanan/<?= e((string) ($service['code'] ?? '')) ?>">Kembali ke detail layanan</a></p>
</section>
