<?php
/** @var array<int, array<string, mixed>> $applications */
$applications = $applications ?? [];
$statusLabels = $statusLabels ?? [];
$statusTones = [
    'DIAJUKAN' => 1,
    'DIPROSES' => 2,
    'SELESAI' => 3,
    'DITOLAK' => 4,
];
$statusCounts = array_fill_keys(array_keys($statusLabels), 0);
foreach ($applications as $application) {
    $status = (string) ($application['status'] ?? '');
    $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;
}
$donutItems = [];
foreach ($statusCounts as $status => $count) {
    $donutItems[] = [
        'label' => (string) ($statusLabels[$status] ?? $status),
        'count' => (int) $count,
        'tone' => (int) ($statusTones[$status] ?? 5),
    ];
}
$donutTitle = 'Status pengajuan pada antrean';
?>
<section class="page-panel">
    <div class="section-header">
        <h2>Antrean pengajuan</h2>
    </div>
    <div class="dash-grid">
        <section class="dash-panel">
            <h3>Status pengajuan pada antrean</h3>
            <div class="chart-row">
                <?php require __DIR__ . '/../partials/donut.php'; ?>
                <ul class="st-list">
                    <?php foreach ($statusCounts as $status => $count):
                        $statusLabel = (string) ($statusLabels[$status] ?? $status);
                        $statusTone = (int) ($statusTones[$status] ?? 5);
                    ?>
                        <li class="tone-<?= $statusTone ?>">
                            <a href="/admin/pengajuan?status=<?= e(rawurlencode((string) $status)) ?>"><span class="st-name"><?= e($statusLabel) ?>: </span><strong class="st-num"><?= e((string) (int) $count) ?></strong></a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </section>
    </div>
    <?php if ($applications === []): ?>
        <p class="lede">BELUM ADA DATA</p>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr><th>Kode lacak</th><th>Layanan</th><th>Pemohon</th><th>Status</th><th>Diajukan</th><th></th></tr>
                </thead>
                <tbody>
                    <?php foreach ($applications as $application): ?>
                        <tr>
                            <td><?= e((string) $application['tracking_code']) ?></td>
                            <td><?= e((string) $application['service_code']) ?> — <?= e((string) $application['service_name']) ?></td>
                            <td><?= e((string) $application['applicant_name']) ?></td>
                            <td><?= e((string) ($statusLabels[$application['status']] ?? $application['status'])) ?></td>
                            <td><?= e((string) $application['created_at']) ?></td>
                            <td><a href="/admin/pengajuan/<?= e((string) $application['id']) ?>">Detail</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
