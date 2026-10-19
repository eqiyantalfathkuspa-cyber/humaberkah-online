<?php
/** @var string $title */
/** @var array<int, array<string, mixed>> $complaints */
$complaints = $complaints ?? [];
$complaintStatusLabels = $complaintStatusLabels ?? [];
?>
<section class="page-panel">
    <div class="section-header">
        <h2>Portal pengguna</h2>
    </div>
    <p class="lede">Portal SISWA / GURU / TENDIK menampilkan status pengajuan dan tiket KAHAYAN milik Anda.</p>
    <h3>Tiket KAHAYAN AMANAH milik Anda</h3>
    <?php if ($complaints === []): ?>
        <p>Belum ada tiket.</p>
    <?php else: ?>
        <ul>
            <?php foreach ($complaints as $complaint): ?>
                <li>
                    <a href="/portal/aduan/<?= e((string) $complaint['id']) ?>"><?= e((string) $complaint['ticket_no']) ?></a> —
                    <?= e((string) ($complaintStatusLabels[$complaint['status']] ?? $complaint['status'])) ?> —
                    <?= e((string) $complaint['created_at']) ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
    <form method="post" action="/logout">
        <?= '<input type="hidden" name="_csrf" value="' . e(App\Core\Csrf::token()) . '">'; ?>
        <button type="submit" class="btn danger">Logout</button>
    </form>
</section>
