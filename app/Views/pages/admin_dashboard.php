<?php
/** @var string $title */
$applicationCounts = $applicationCounts ?? [];
$applicationStatusLabels = $applicationStatusLabels ?? [];
$complaintCounts = $complaintCounts ?? [];
$complaintStatusLabels = $complaintStatusLabels ?? [];
$surveyMetrics = $surveyMetrics ?? [];
$average = static fn (string $key): string => ($surveyMetrics[$key] ?? null) === null
    ? 'BELUM ADA DATA'
    : number_format((float) $surveyMetrics[$key], 2, ',', '.');
?>
<section class="page-panel">
    <div class="section-header">
        <h2>Dashboard Admin</h2>
    </div>
    <p><a class="btn secondary" href="/admin/madani">Dashboard dan laporan MADANI</a></p>
    <h3>Pengajuan layanan</h3>
    <ul>
        <?php foreach ($applicationStatusLabels as $status => $label): ?>
            <li><a href="/admin/pengajuan?status=<?= e((string) $status) ?>"><?= e($label) ?>: <?= e((string) ($applicationCounts[$status] ?? 0)) ?></a></li>
        <?php endforeach; ?>
    </ul>
    <h3>Pengaduan KAHAYAN AMANAH</h3>
    <ul>
        <?php foreach ($complaintStatusLabels as $status => $label): ?>
            <li><a href="/admin/aduan?status=<?= e((string) $status) ?>"><?= e($label) ?>: <?= e((string) ($complaintCounts[$status] ?? 0)) ?></a></li>
        <?php endforeach; ?>
    </ul>
    <h3>Survei kepuasan</h3>
    <p>Jumlah responden: <?= e((string) ($surveyMetrics['respondent_count'] ?? 0)) ?></p>
    <ul>
        <li>Rata-rata kecepatan: <?= e($average('speed_average')) ?></li>
        <li>Rata-rata kejelasan: <?= e($average('clarity_average')) ?></li>
        <li>Rata-rata keramahan: <?= e($average('courtesy_average')) ?></li>
    </ul>
    <h3>Survei layanan informasi (tanpa verifikasi)</h3>
    <p>Jumlah responden: <?= e((string) $informationSurveyCount) ?></p>
    <p><a class="btn primary" href="/admin/survei">Rekap survei</a></p>
    <form method="post" action="/logout">
        <?= App\Core\Csrf::field() ?>
        <button type="submit" class="btn danger">Logout</button>
    </form>
</section>
