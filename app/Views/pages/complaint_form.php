<?php
/** @var string $error */
$error = $error ?? '';
$selectedType = $selectedType ?? '';
$body = $body ?? '';
$contact = $contact ?? '';
?>
<section class="page-panel">
    <div class="section-header">
        <h2>KAHAYAN AMANAH</h2>
    </div>
    <p class="lede">Kirim pengaduan, aspirasi, saran, atau pertanyaan. Identitas tidak wajib; tanpa lampiran.</p>
    <p>Isi hanya dapat dilihat oleh ADMIN. Pesan anonim tidak diteruskan kepada pihak lain dan tidak ada notifikasi email.</p>
    <?php if ($error !== ''): ?>
        <p class="auth-error"><?= e($error) ?></p>
    <?php endif; ?>
    <form method="post" action="/kahayan" class="auth-form">
        <?= App\Core\Csrf::field() ?>
        <label>
            <span>Jenis</span>
            <select name="jenis" required>
                <option value="">Pilih jenis</option>
                <?php foreach (App\Core\ComplaintWorkflow::TYPES as $type): ?>
                    <option value="<?= e($type) ?>" <?= $selectedType === $type ? 'selected' : '' ?>><?= e(ucfirst(strtolower($type))) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            <span>Isi (maksimal 4.000 karakter)</span>
            <textarea name="isi" rows="8" maxlength="4000" required><?= e($body) ?></textarea>
        </label>
        <label>
            <span>Kontak (opsional, maksimal 120 karakter)</span>
            <input type="text" name="kontak" maxlength="120" value="<?= e($contact) ?>">
        </label>
        <div aria-hidden="true" style="position:absolute;left:-10000px">
            <label>Jangan diisi <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
        </div>
        <button type="submit" class="btn primary">Kirim</button>
    </form>
</section>
