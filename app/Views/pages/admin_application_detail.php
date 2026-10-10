<?php
/** @var array<string, mixed> $application */
$application = $application ?? [];
$history = $history ?? [];
$statusLabels = $statusLabels ?? [];
$nextStatuses = $nextStatuses ?? [];
$message = $message ?? '';
$documents = $documents ?? [];
?>
<section class="page-panel">
    <div class="section-header">
        <h2>Detail pengajuan <?= e((string) ($application['tracking_code'] ?? '')) ?></h2>
    </div>
    <?php if ($message !== ''): ?>
        <p class="<?= http_response_code() >= 400 ? 'auth-error' : 'lede' ?>"><?= e($message) ?></p>
    <?php endif; ?>
    <dl>
        <dt>Layanan</dt><dd><?= e((string) ($application['service_code'] ?? '')) ?> — <?= e((string) ($application['service_name'] ?? '')) ?></dd>
        <dt>Pemohon</dt><dd><?= e((string) ($application['applicant_name'] ?? '')) ?> (<?= e((string) ($application['applicant_identifier'] ?? '')) ?>)</dd>
        <dt>Status</dt><dd><?= e((string) ($statusLabels[$application['status'] ?? ''] ?? ($application['status'] ?? ''))) ?></dd>
        <dt>Diajukan</dt><dd><?= e((string) ($application['created_at'] ?? '')) ?></dd>
        <dt>Uraian permintaan</dt><dd><?= nl2br(e((string) ($application['request_text'] ?? ''))) ?></dd>
    </dl>
    <h3>Lampiran</h3>
    <?php if ($documents === []): ?>
        <p>Tidak ada lampiran.</p>
    <?php else: ?>
        <ul>
            <?php foreach ($documents as $document): ?>
                <li>
                    <?= e((string) $document['mime_terdeteksi']) ?> —
                    <?= e(number_format((int) $document['ukuran'])) ?> byte
                    <a href="/admin/pengajuan/<?= e((string) ($application['id'] ?? '')) ?>/berkas/<?= e((string) $document['id']) ?>">Unduh</a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
    <?php if ($nextStatuses !== []): ?>
        <form method="post" action="/admin/pengajuan/<?= e((string) ($application['id'] ?? '')) ?>/status" class="auth-form">
            <?= App\Core\Csrf::field() ?>
            <label>
                <span>Status berikutnya</span>
                <select name="status" required>
                    <?php foreach ($nextStatuses as $nextStatus): ?>
                        <option value="<?= e($nextStatus) ?>"><?= e((string) ($statusLabels[$nextStatus] ?? $nextStatus)) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button type="submit" class="btn primary">Perbarui status</button>
        </form>
    <?php endif; ?>
    <h3>Riwayat status</h3>
    <ol>
        <?php foreach ($history as $entry): ?>
            <li>
                <?= e((string) ($statusLabels[$entry['new_status']] ?? $entry['new_status'])) ?>
                — <?= e((string) $entry['created_at']) ?>
                <?php if (!empty($entry['changed_by_name'])): ?>
                    — <?= e((string) $entry['changed_by_name']) ?>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ol>
    <p><a href="/admin/pengajuan">Kembali ke antrean pengajuan</a></p>
</section>
