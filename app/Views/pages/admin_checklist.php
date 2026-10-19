<?php
/** @var string $title */
$services = $services ?? [];
$checkpoints = $checkpoints ?? [];
$summary = $summary ?? [];
$cellCount = (int) ($cell_count ?? 0);
$statusLabels = [
    'VALID' => 'VALID',
    'ADA_BELUM_VERIFIKASI' => 'ADA_BELUM_VERIFIKASI',
    'PERLU_PERBAIKAN' => 'PERLU_PERBAIKAN',
    'KOSONG' => 'KOSONG',
];
?>
<section class="page-panel">
    <div class="section-header"><h2>Checklist Eviden</h2></div>
    <p class="lede">Instrumen kendali internal; bukan indikator resmi LKE/PMPZI. Status dihitung dari eviden dan tidak disimpan sebagai sel. Tidak ada persentase atau skor.</p>
    <p>Jumlah sel: <?= e((string) $cellCount) ?></p>
    <dl class="data-table">
        <?php foreach ($statusLabels as $status => $label): ?>
            <dt><?= e($label) ?></dt><dd><?= e((string) ($summary[$status] ?? 0)) ?></dd>
        <?php endforeach; ?>
    </dl>
    <?php if ($services === [] || $checkpoints === []): ?>
        <p class="lede">Belum ada layanan atau checkpoint untuk membentuk matriks.</p>
    <?php else: ?>
        <div class="table-scroll">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Layanan</th>
                        <?php foreach ($checkpoints as $checkpoint): ?>
                            <th><?= e((string) $checkpoint['code']) ?><br><?= e((string) $checkpoint['name']) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($services as $service): ?>
                        <tr>
                            <th scope="row">
                                <?= e((string) $service['code'] . ' — ' . $service['name']) ?>
                                <?php if (!$service['is_active']): ?><span>(nonaktif)</span><?php endif; ?>
                            </th>
                            <?php foreach ($service['cells'] as $cell): ?>
                                <?php
                                $filterUrl = '/admin/eviden?' . http_build_query([
                                    'service_id' => $service['id'],
                                    'checkpoint_id' => $cell['checkpoint_id'],
                                ]);
                                ?>
                                <td>
                                    <a href="<?= e($filterUrl) ?>">
                                        <?= e($statusLabels[(string) $cell['status']] ?? (string) $cell['status']) ?>
                                        (<?= e((string) $cell['evidence_count']) ?>)
                                    </a>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
