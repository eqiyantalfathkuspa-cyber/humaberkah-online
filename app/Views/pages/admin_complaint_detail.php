<?php
$complaint = $complaint ?? [];
$history = $history ?? [];
$statuses = $statuses ?? [];
$nextStatuses = $nextStatuses ?? [];
$message = $message ?? '';
?>
<section class="page-panel">
    <div class="section-header"><h2>Detail KAHAYAN AMANAH <?= e((string) ($complaint['ticket_no'] ?? '')) ?></h2></div>
    <?php if ($message !== ''): ?><p class="<?= http_response_code() >= 400 ? 'auth-error' : 'lede' ?>"><?= e($message) ?></p><?php endif; ?>
    <dl>
        <dt>Jenis</dt><dd><?= e((string) ($complaint['jenis'] ?? '')) ?></dd>
        <dt>Pelapor</dt><dd><?= e((string) ($complaint['reporter_name'] ?? 'Anonim')) ?><?= !empty($complaint['reporter_identifier']) ? ' (' . e((string) $complaint['reporter_identifier']) . ')' : '' ?></dd>
        <dt>Kontak</dt><dd><?= e((string) ($complaint['kontak'] ?? '—')) ?></dd>
        <dt>Status</dt><dd><?= e((string) ($statuses[$complaint['status'] ?? ''] ?? '')) ?></dd>
        <dt>Isi</dt><dd><?= nl2br(e((string) ($complaint['isi'] ?? ''))) ?></dd>
        <?php if (!empty($complaint['reject_reason'])): ?>
            <dt>Alasan penolakan</dt><dd><?= nl2br(e((string) $complaint['reject_reason'])) ?></dd>
        <?php endif; ?>
        <?php if (!empty($complaint['response_note'])): ?>
            <dt>Tanggapan publik saat ini</dt><dd><?= nl2br(e((string) $complaint['response_note'])) ?></dd>
        <?php endif; ?>
    </dl>
    <?php if ($nextStatuses !== []): ?>
        <form method="post" action="/admin/aduan/<?= e((string) $complaint['id']) ?>/status" class="auth-form">
            <?= App\Core\Csrf::field() ?>
            <label><span>Status berikutnya</span><select name="status" required>
                <?php foreach ($nextStatuses as $nextStatus): ?>
                    <option value="<?= e($nextStatus) ?>"><?= e((string) ($statuses[$nextStatus] ?? $nextStatus)) ?></option>
                <?php endforeach; ?>
            </select></label>
            <label><span>Alasan penolakan (wajib bila ditolak)</span><textarea name="reject_reason" maxlength="1000" rows="3"></textarea></label>
            <button type="submit" class="btn primary">Perbarui status</button>
        </form>
    <?php endif; ?>
    <form method="post" action="/admin/aduan/<?= e((string) $complaint['id']) ?>/tanggapan" class="auth-form">
        <?= App\Core\Csrf::field() ?>
        <label><span>Tanggapan publik (maksimal 1.000 karakter)</span><textarea name="response_note" maxlength="1000" rows="4" required><?= e((string) ($complaint['response_note'] ?? '')) ?></textarea></label>
        <button type="submit" class="btn primary">Simpan tanggapan</button>
    </form>
    <h3>Riwayat</h3>
    <ol><?php foreach ($history as $entry): ?>
        <li>
            <?= e((string) ($entry['event_type'] === 'STATUS' ? ($statuses[$entry['new_status'] ?? ''] ?? 'Perubahan status') : 'Tanggapan publik diperbarui')) ?>
            — <?= e((string) $entry['created_at']) ?>
            <?= !empty($entry['actor_name']) ? ' — ' . e((string) $entry['actor_name']) : '' ?>
        </li>
    <?php endforeach; ?></ol>
    <p><a href="/admin/aduan">Kembali ke antrean</a></p>
</section>
