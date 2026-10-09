<?php
/** @var string $title */
/** @var array<int, array<string, mixed>> $auditLogs */
$auditLogs = $auditLogs ?? [];
?>
<section class="page-panel">
    <div class="section-header">
        <h2>Audit log</h2>
    </div>
    <?php if ($auditLogs === []): ?>
        <p class="lede">BELUM ADA DATA.</p>
    <?php else: ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Waktu</th>
                    <th>Actor</th>
                    <th>Aksi</th>
                    <th>Entitas</th>
                    <th>Pesan</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($auditLogs as $entry): ?>
                    <tr>
                        <td><?= e((string) ($entry['created_at'] ?? '')) ?></td>
                        <td><?= e((string) ($entry['actor_role'] ?? 'GUEST')) ?></td>
                        <td><?= e((string) ($entry['action'] ?? '')) ?></td>
                        <td><?= e((string) ($entry['entity_type'] ?? '')) ?></td>
                        <td><?= e((string) ($entry['message'] ?? '')) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>
