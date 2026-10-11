<?php
/** @var string $title */
$record = $record ?? [];
$correctedBy = trim((string) ($record['corrected_by_ids'] ?? ''));
?>
<section class="page-panel">
    <div class="section-header"><h2>Detail catatan Before/After</h2></div>
    <p class="lede">Catatan deskriptif internal prototipe; bukan bukti sebab-akibat, bukan bukti dampak terukur, dan bukan klaim kepatuhan PMPZI/LKE.</p>
    <p>Peringatan privasi: gunakan data fiktif saja. Jangan masukkan data pribadi, identitas siswa, atau informasi BK.</p>
    <dl class="data-table">
        <dt>Layanan</dt><dd><?= e((string) $record['service_code'] . ' — ' . (string) $record['service_name']) ?></dd>
        <dt>Judul</dt><dd><?= e((string) $record['title']) ?></dd>
        <dt>Kondisi sebelum</dt><dd><?= e((string) $record['before_observed_on']) ?> — <?= nl2br(e((string) $record['before_text'])) ?></dd>
        <dt>Kondisi sesudah</dt><dd><?= e((string) $record['after_observed_on']) ?> — <?= nl2br(e((string) $record['after_text'])) ?></dd>
        <dt>Sumber pengamatan</dt><dd><?= nl2br(e((string) $record['source_note'])) ?></dd>
        <dt>RTL terkait</dt>
        <dd>
            <?php if ($record['rtl_id'] === null): ?>
                Tidak dikaitkan dengan RTL
            <?php else: ?>
                <a href="/admin/rtl/<?= e((string) $record['rtl_id']) ?>">RTL #<?= e((string) $record['rtl_id']) ?></a>
            <?php endif; ?>
        </dd>
        <?php if ($record['corrects_before_after_id'] !== null): ?>
            <dt>Koreksi</dt>
            <dd>Mengoreksi <a href="/admin/dampak/<?= e((string) $record['corrects_before_after_id']) ?>">catatan #<?= e((string) $record['corrects_before_after_id']) ?></a></dd>
        <?php endif; ?>
        <?php if ($correctedBy !== ''): ?>
            <dt>Status koreksi</dt>
            <dd>
                <?php foreach (explode(',', $correctedBy) as $correctionId): ?>
                    <a href="/admin/dampak/<?= e($correctionId) ?>">Dikoreksi oleh #<?= e($correctionId) ?></a>
                <?php endforeach; ?>
            </dd>
        <?php endif; ?>
        <dt>Dicatat oleh</dt><dd><?= e((string) $record['author_name']) ?></dd>
        <dt>Waktu pencatatan</dt><dd><?= e((string) $record['created_at']) ?></dd>
    </dl>
    <p><a class="btn" href="/admin/dampak">Kembali ke catatan</a></p>
</section>
