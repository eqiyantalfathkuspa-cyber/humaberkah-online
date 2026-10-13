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
$applicationTones = [
    'DIAJUKAN' => 1,
    'DIPROSES' => 2,
    'SELESAI' => 3,
    'DITOLAK' => 4,
];
$complaintTones = [
    'DITERIMA' => 1,
    'DIKLASIFIKASI' => 5,
    'DITINDAKLANJUTI' => 2,
    'SELESAI' => 3,
    'DITOLAK' => 4,
];
?>
<section class="page-panel">
    <div class="section-header">
        <h2>Dashboard Admin</h2>
    </div>
    <p><a class="btn secondary" href="/admin/madani">Dashboard dan laporan MADANI</a></p>
    <div class="kpi-row">
        <article class="kpi"><span>Total pengajuan</span><strong><?= e((string) (int) array_sum($applicationCounts)) ?></strong></article>
        <article class="kpi"><span>Total aduan</span><strong><?= e((string) (int) array_sum($complaintCounts)) ?></strong></article>
        <article class="kpi"><span>Responden survei kepuasan</span><strong><?= e((string) (int) ($surveyMetrics['respondent_count'] ?? 0)) ?></strong></article>
        <article class="kpi"><span>Responden survei layanan informasi</span><strong><?= e((string) (int) $informationSurveyCount) ?></strong></article>
    </div>
    <div class="dash-grid">
        <section class="dash-panel">
            <h3>Pengajuan layanan</h3>
            <div class="chart-row">
                <?php
                $donutItems = [];
                foreach ($applicationStatusLabels as $status => $label) {
                    $donutItems[] = [
                        'label' => $label,
                        'count' => (int) ($applicationCounts[$status] ?? 0),
                        'tone' => (int) ($applicationTones[$status] ?? 5),
                    ];
                }
                $donutTitle = 'Pengajuan layanan';
                require __DIR__ . '/../partials/donut.php';
                ?>
                <ul class="st-list">
                    <?php foreach ($applicationStatusLabels as $status => $label):
                        $count = (int) ($applicationCounts[$status] ?? 0);
                    ?>
                        <li class="tone-<?= (int) ($applicationTones[$status] ?? 5) ?>">
                            <a href="/admin/pengajuan?status=<?= e((string) $status) ?>"><?= e($label) ?>: <?= e((string) $count) ?></a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </section>
        <section class="dash-panel">
            <h3>Pengaduan KAHAYAN AMANAH</h3>
            <div class="chart-row">
                <?php
                $donutItems = [];
                foreach ($complaintStatusLabels as $status => $label) {
                    $donutItems[] = [
                        'label' => $label,
                        'count' => (int) ($complaintCounts[$status] ?? 0),
                        'tone' => (int) ($complaintTones[$status] ?? 5),
                    ];
                }
                $donutTitle = 'Pengaduan KAHAYAN AMANAH';
                require __DIR__ . '/../partials/donut.php';
                ?>
                <ul class="st-list">
                    <?php foreach ($complaintStatusLabels as $status => $label):
                        $count = (int) ($complaintCounts[$status] ?? 0);
                    ?>
                        <li class="tone-<?= (int) ($complaintTones[$status] ?? 5) ?>">
                            <a href="/admin/aduan?status=<?= e((string) $status) ?>"><?= e($label) ?>: <?= e((string) $count) ?></a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </section>
        <section class="dash-panel">
            <h3>Survei kepuasan</h3>
            <p>Jumlah responden: <?= e((string) ($surveyMetrics['respondent_count'] ?? 0)) ?></p>
            <ul>
                <li>Rata-rata kecepatan: <?= e($average('speed_average')) ?></li>
                <li>Rata-rata kejelasan: <?= e($average('clarity_average')) ?></li>
                <li>Rata-rata keramahan: <?= e($average('courtesy_average')) ?></li>
            </ul>
            <?php foreach (['speed_average', 'clarity_average', 'courtesy_average'] as $metricKey): ?>
                <?php if (is_numeric($surveyMetrics[$metricKey] ?? null)): ?>
                    <div class="meter" aria-hidden="true">
                        <?php
                        $meterValue = (float) $surveyMetrics[$metricKey];
                        $meterMax = 5;
                        require __DIR__ . '/../partials/meter_bar.php';
                        ?>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </section>
        <section class="dash-panel">
            <h3>Survei layanan informasi (tanpa verifikasi)</h3>
            <p>Jumlah responden: <?= e((string) (int) $informationSurveyCount) ?></p>
            <div class="kpi">
                <strong><?= e((string) (int) $informationSurveyCount) ?></strong>
                <span>Responden survei layanan informasi</span>
            </div>
            <p><a class="btn primary" href="/admin/survei">Rekap survei</a></p>
        </section>
    </div>
    <form method="post" action="/logout">
        <?= App\Core\Csrf::field() ?>
        <button type="submit" class="btn danger">Logout</button>
    </form>
</section>
