<?php
/** @var array<string, mixed> $complaint */
$complaint = $complaint ?? [];
$statusLabels = $statusLabels ?? [];
$statusSteps = $statusSteps ?? [];
?>
<section class="page-panel">
    <div class="section-header">
        <h2>Status KAHAYAN AMANAH</h2>
    </div>
    <p><strong>Kode tiket:</strong> <?= e((string) ($complaint['ticket_no'] ?? '')) ?></p>
    <p><strong>Status:</strong> <?= e((string) ($statusLabels[$complaint['status'] ?? ''] ?? '')) ?></p>
    <p><strong>Tahap:</strong> <?= e((string) ($statusSteps[$complaint['status'] ?? ''] ?? '')) ?></p>
    <?php if (!empty($complaint['response_note'])): ?>
        <h3>Tanggapan</h3>
        <p><?= nl2br(e((string) $complaint['response_note'])) ?></p>
    <?php endif; ?>
</section>
