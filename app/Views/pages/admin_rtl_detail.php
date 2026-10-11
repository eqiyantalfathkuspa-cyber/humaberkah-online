<?php
/** @var string $title */
$availableTransitions = $availableTransitions ?? [];
$history = $history ?? [];
$statusLabels = [
    'DIRENCANAKAN' => 'Direncanakan',
    'BERJALAN' => 'Berjalan',
    'SELESAI' => 'Selesai',
];
?>
<section class="page-panel">
    <div class="section-header"><h2>Detail RTL</h2></div>
    <p class="lede">Internal prototipe, bukan klaim kepatuhan PMPZI/LKE.</p>
    <p>Peringatan privasi: gunakan data fiktif saja. Jangan masukkan data pribadi, identitas siswa, atau informasi BK.</p>
    <?php if (($error ?? '') !== ''): ?>
        <p class="error" role="alert"><?= e((string) $error) ?></p>
    <?php endif; ?>
    <dl class="data-table">
        <dt>Evaluasi</dt>
        <dd>
            <a href="/admin/evaluasi/<?= e((string) $rtl['evaluation_id']) ?>">#<?= e((string) $rtl['evaluation_id']) ?></a>
            — <?= e((string) $rtl['service_code'] . ' — ' . (string) $rtl['service_name']) ?>
            (<?= e((string) $rtl['period_start'] . ' s.d. ' . (string) $rtl['period_end']) ?>)
            <?= $rtl['corrects_evaluation_id'] !== null ? '— evaluasi dikoreksi' : '' ?>
        </dd>
        <dt>Uraian tindakan</dt><dd><?= nl2br(e((string) $rtl['action_description'])) ?></dd>
        <dt>Penanggung jawab</dt><dd><?= e((string) $rtl['responsible_name']) ?></dd>
        <dt>Tenggat</dt><dd><?= e((string) $rtl['due_date']) ?></dd>
        <dt>Status saat ini</dt><dd><?= e($statusLabels[(string) $rtl['current_status']] ?? (string) $rtl['current_status']) ?></dd>
    </dl>

    <?php if ($availableTransitions !== []): ?>
        <h3>Perbarui status</h3>
        <form method="post" action="/admin/rtl/<?= e((string) $rtl['id']) ?>/status">
            <?= App\Core\Csrf::field() ?>
            <label for="rtl-next-status">Transisi yang tersedia</label>
            <select id="rtl-next-status" name="status" required>
                <?php foreach ($availableTransitions as $status): ?>
                    <option value="<?= e($status) ?>"><?= e($statusLabels[$status] ?? $status) ?></option>
                <?php endforeach; ?>
            </select>
            <label for="rtl-status-note">Catatan status (opsional)</label>
            <textarea id="rtl-status-note" name="note" maxlength="<?= e((string) $statusNoteMaxLength) ?>"></textarea>
            <button type="submit" class="btn primary">Simpan status</button>
        </form>
    <?php endif; ?>

    <h3>Riwayat status</h3>
    <div class="table-scroll">
        <table class="data-table">
            <thead><tr><th>Status</th><th>Catatan</th><th>Diubah oleh</th><th>Waktu</th></tr></thead>
            <tbody>
                <?php foreach ($history as $entry): ?>
                    <tr>
                        <td><?= e($statusLabels[(string) $entry['status']] ?? (string) $entry['status']) ?></td>
                        <td><?= $entry['note'] === null ? '—' : nl2br(e((string) $entry['note'])) ?></td>
                        <td><?= e((string) $entry['changed_by_name']) ?></td>
                        <td><?= e((string) $entry['created_at']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <p><a class="btn" href="/admin/dampak?rtl_id=<?= e((string) $rtl['id']) ?>">Lihat catatan Before/After untuk RTL ini</a></p>
    <p><a class="btn" href="/admin/rtl">Kembali ke RTL</a></p>
</section>
