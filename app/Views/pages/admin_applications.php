<?php
/** @var array<int, array<string, mixed>> $applications */
$applications = $applications ?? [];
$statusLabels = $statusLabels ?? [];
?>
<section class="page-panel">
    <div class="section-header">
        <h2>Antrean pengajuan</h2>
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
