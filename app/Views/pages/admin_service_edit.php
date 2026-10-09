<?php
/** @var array<string, mixed> $service */
$service = $service ?? [];
$message = $message ?? '';
$isError = $isError ?? false;
$modeLabels = $modeLabels ?? [];
$availableModes = ($service['code'] ?? '') === 'HUM-02'
    ? ['MODUL_PENGADUAN']
    : array_values(array_filter(App\Core\ServiceCatalog::MODES, static fn(string $mode): bool => $mode !== 'MODUL_PENGADUAN'));
?>
<section class="page-panel">
    <div class="section-header">
        <h2>Ubah layanan <?= e((string) ($service['code'] ?? '')) ?></h2>
    </div>
    <?php if ($message !== ''): ?>
        <p class="<?= $isError ? 'auth-error' : 'lede' ?>"><?= e($message) ?></p>
    <?php endif; ?>
    <p><?= e((string) ($service['name'] ?? '')) ?> — <?= e((string) ($service['category_name'] ?? '')) ?></p>
    <form method="post" action="/admin/layanan/<?= e((string) ($service['code'] ?? '')) ?>" class="auth-form">
        <?= App\Core\Csrf::field() ?>
        <label>
            <span>Mode</span>
            <?php if (($service['code'] ?? '') === 'HUM-02'): ?>
                <input type="hidden" name="mode" value="MODUL_PENGADUAN">
                <input type="text" value="<?= e((string) ($modeLabels['MODUL_PENGADUAN'] ?? 'MODUL_PENGADUAN')) ?>" readonly>
            <?php else: ?>
                <select name="mode" required>
                    <?php foreach ($availableModes as $mode): ?>
                        <option value="<?= e($mode) ?>" <?= ($service['mode'] ?? '') === $mode ? 'selected' : '' ?>><?= e((string) ($modeLabels[$mode] ?? $mode)) ?></option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>
        </label>
        <label><span>Tarif</span><input type="text" name="fee_text" maxlength="500" value="<?= e((string) ($service['fee_text'] ?? '')) ?>"></label>
        <label><span>SLA</span><input type="text" name="sla_text" maxlength="500" value="<?= e((string) ($service['sla_text'] ?? '')) ?>"></label>
        <label><span>Unit penanggung jawab</span><input type="text" name="responsible_unit" maxlength="500" value="<?= e((string) ($service['responsible_unit'] ?? '')) ?>"></label>
        <label><span>Dokumen wajib (keterangan teks)</span><textarea name="required_documents_text" maxlength="4000" rows="5"><?= e((string) ($service['required_documents_text'] ?? '')) ?></textarea></label>
        <button type="submit" class="btn primary">Simpan perubahan</button>
    </form>
    <p><a href="/admin/layanan">Kembali ke daftar layanan</a></p>
</section>
