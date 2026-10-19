<?php
/** @var array<int, array<string, mixed>> $services */
$services = $services ?? [];
$modeLabels = $modeLabels ?? [];
?>
<section class="page-panel">
    <div class="section-header">
        <h2>Kelola layanan</h2>
    </div>
    <?php if ($services === []): ?>
        <p class="lede">BELUM ADA DATA.</p>
    <?php else: ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Kode</th>
                    <th>Nama layanan</th>
                    <th>Kategori</th>
                    <th>Mode</th>
                    <th>Kelengkapan standar</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($services as $service): ?>
                    <tr>
                        <td><?= e((string) $service['code']) ?></td>
                        <td><?= e((string) $service['name']) ?></td>
                        <td><?= e((string) $service['category_name']) ?></td>
                        <td><?= e((string) ($modeLabels[$service['mode']] ?? $service['mode'])) ?></td>
                        <td><?= e((string) $service['completed_standards']) ?> / 3</td>
                        <td><a class="btn" href="/admin/layanan/<?= e((string) $service['code']) ?>/ubah">Ubah</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>
