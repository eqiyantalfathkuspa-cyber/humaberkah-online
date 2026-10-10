<?php
/** @var string $title */
$summaries = $summaries ?? [];
$suggestions = $suggestions ?? [];
?>
<section class="page-panel">
    <div class="section-header">
        <h2>Rekap survei kepuasan</h2>
    </div>
    <p><a class="btn primary" href="/admin/survei/ekspor">Ekspor CSV</a></p>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Layanan</th>
                    <th>Responden</th>
                    <th>Kecepatan</th>
                    <th>Kejelasan</th>
                    <th>Keramahan</th>
                    <th>Keterangan</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($summaries as $summary): ?>
                    <tr>
                        <td><?= e((string) $summary['service_code']) ?> — <?= e((string) $summary['service_name']) ?></td>
                        <td><?= e((string) $summary['respondent_count']) ?></td>
                        <td><?= e((string) $summary['speed_average']) ?></td>
                        <td><?= e((string) $summary['clarity_average']) ?></td>
                        <td><?= e((string) $summary['courtesy_average']) ?></td>
                        <td>
                            <?php if ($summary['respondent_count'] === 0): ?>
                                <span>BELUM ADA DATA</span>
                            <?php endif; ?>
                            <?php if ($summary['small_sample']): ?>
                                <span>DATA TERLALU SEDIKIT</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <h3>Saran terbaru</h3>
    <?php if ($suggestions === []): ?>
        <p>BELUM ADA DATA</p>
    <?php else: ?>
        <ul>
            <?php foreach ($suggestions as $entry): ?>
                <li>
                    <strong><?= e((string) $entry['service_code']) ?> — <?= e((string) $entry['service_name']) ?></strong>
                    <time><?= e((string) $entry['created_at']) ?></time>
                    <p><?= nl2br(e((string) $entry['suggestion'])) ?></p>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
