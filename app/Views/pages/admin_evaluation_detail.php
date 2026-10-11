<?php
/** @var string $title */
$evaluation = $evaluation ?? [];
$correctedBy = trim((string) ($evaluation['corrected_by_ids'] ?? ''));
?>
<section class="page-panel">
    <div class="section-header"><h2>Detail evaluasi</h2></div>
    <p class="lede">Catatan internal prototipe; bukan pernyataan kepatuhan PMPZI/LKE.</p>
    <dl class="data-table">
        <dt>Layanan</dt><dd><?= e((string) $evaluation['service_code'] . ' — ' . $evaluation['service_name']) ?></dd>
        <dt>Periode</dt><dd><?= e((string) $evaluation['period_start'] . ' s.d. ' . (string) $evaluation['period_end']) ?></dd>
        <dt>Ringkasan</dt><dd><?= nl2br(e((string) $evaluation['summary'])) ?></dd>
        <dt>Temuan</dt><dd><?= $evaluation['findings'] === null ? 'Tidak ada catatan' : nl2br(e((string) $evaluation['findings'])) ?></dd>
        <dt>Kesimpulan</dt><dd><?= $evaluation['conclusion'] === null ? 'Tidak ada catatan' : nl2br(e((string) $evaluation['conclusion'])) ?></dd>
        <dt>Dicatat oleh</dt><dd><?= e((string) $evaluation['author_name']) ?></dd>
        <dt>Waktu pencatatan</dt><dd><?= e((string) $evaluation['created_at']) ?></dd>
        <?php if ($evaluation['corrects_evaluation_id'] !== null): ?>
            <dt>Koreksi</dt><dd>Mengoreksi <a href="/admin/evaluasi/<?= e((string) $evaluation['corrects_evaluation_id']) ?>">catatan #<?= e((string) $evaluation['corrects_evaluation_id']) ?></a></dd>
        <?php endif; ?>
        <?php if ($correctedBy !== ''): ?>
            <dt>Status koreksi</dt>
            <dd>
                <?php foreach (explode(',', $correctedBy) as $correctionId): ?>
                    <a href="/admin/evaluasi/<?= e($correctionId) ?>">Dikoreksi oleh #<?= e($correctionId) ?></a>
                <?php endforeach; ?>
            </dd>
        <?php endif; ?>
    </dl>
    <p><a class="btn" href="/admin/evaluasi">Kembali ke evaluasi</a></p>
</section>
