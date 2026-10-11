<?php
/** @var string $title */
$items = $items ?? [];
$services = $services ?? [];
$checkpoints = $checkpoints ?? [];
$message = $message ?? '';
$error = $error ?? '';
?>
<section class="page-panel">
    <div class="section-header">
        <h2>Eviden</h2>
    </div>
    <?php if ($message !== ''): ?><p class="lede"><?= e($message) ?></p><?php endif; ?>
    <?php if ($error !== ''): ?><p class="auth-error"><?= e($error) ?></p><?php endif; ?>
    <form method="post" action="/admin/eviden" enctype="multipart/form-data" class="auth-form">
        <?= App\Core\Csrf::field() ?>
        <h3>Unggah eviden</h3>
        <label>
            <span>Layanan</span>
            <select name="service_id" required>
                <option value="">Pilih layanan</option>
                <?php foreach ($services as $service): ?>
                    <option value="<?= e((string) $service['id']) ?>"><?= e((string) $service['code'] . ' — ' . $service['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            <span>Checkpoint</span>
            <select name="checkpoint_id" required>
                <option value="">Pilih checkpoint</option>
                <?php foreach ($checkpoints as $checkpoint): ?>
                    <option value="<?= e((string) $checkpoint['id']) ?>"><?= e((string) $checkpoint['code'] . ' — ' . $checkpoint['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label><span>Berkas JPG/PNG (maksimal 3, masing-masing 5 MiB)</span><input type="file" name="attachments[]" accept="image/jpeg,image/png" multiple required></label>
        <button type="submit" class="btn primary">Unggah</button>
    </form>
    <form method="get" action="/admin/eviden" class="auth-form">
        <h3>Filter eviden</h3>
        <label><span>Layanan</span>
            <select name="service_id">
                <option value="">Semua layanan</option>
                <?php foreach ($services as $service): ?>
                    <option value="<?= e((string) $service['id']) ?>" <?= (int) ($selectedServiceId ?? 0) === (int) $service['id'] ? 'selected' : '' ?>><?= e((string) $service['code'] . ' — ' . $service['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label><span>Checkpoint</span>
            <select name="checkpoint_id">
                <option value="">Semua checkpoint</option>
                <?php foreach ($checkpoints as $checkpoint): ?>
                    <option value="<?= e((string) $checkpoint['id']) ?>" <?= (int) ($selectedCheckpointId ?? 0) === (int) $checkpoint['id'] ? 'selected' : '' ?>><?= e((string) $checkpoint['code'] . ' — ' . $checkpoint['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label><span>Status</span>
            <select name="status">
                <option value="">Semua status</option>
                <?php foreach (['DIUNGGAH', 'TERVERIFIKASI', 'PERLU_PERBAIKAN'] as $statusOption): ?>
                    <option value="<?= e($statusOption) ?>" <?= ($selectedStatus ?? '') === $statusOption ? 'selected' : '' ?>><?= e($statusOption) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <button type="submit" class="btn">Terapkan filter</button>
    </form>
    <?php if ($items === []): ?>
        <p class="lede">BELUM ADA DATA.</p>
    <?php else: ?>
        <table class="data-table">
            <thead><tr><th>ID</th><th>Layanan</th><th>Checkpoint</th><th>Berkas</th><th>Status</th><th>Pengunggah / verifikator</th></tr></thead>
            <tbody>
            <?php foreach ($items as $item): ?>
                <tr>
                    <td><a href="/admin/eviden/<?= e((string) $item['id']) ?>"><?= e((string) $item['id']) ?></a></td>
                    <td><?= e((string) $item['service_code'] . ' — ' . $item['service_name']) ?></td>
                    <td><?= e((string) $item['checkpoint_code'] . ' — ' . $item['checkpoint_name']) ?></td>
                    <td><?= e((string) ($item['original_name'] ?? 'Berkas eviden')) ?></td>
                    <td><?= e((string) $item['status']) ?></td>
                    <td>
                        <?= e((string) $item['uploader_name']) ?>
                        <?php if ($item['verified_by'] !== null): ?>
                            / <?= e((string) $item['verifier_name']) ?>
                            <?php if ((int) $item['uploaded_by'] === (int) $item['verified_by']): ?>
                                <strong>diverifikasi oleh pengunggah yang sama</strong>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>
