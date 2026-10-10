<?php
/** @var string $trackingCode */
$trackingCode = $trackingCode ?? '';
?>
<section class="page-panel">
    <div class="section-header">
        <h2>Pengajuan diterima</h2>
    </div>
    <?php if ($trackingCode !== ''): ?>
        <p>Simpan kode lacak berikut. Kode hanya ditampilkan sekali.</p>
        <p><strong>Kode lacak:</strong> <code><?= e($trackingCode) ?></code></p>
        <p><a class="btn primary" href="/lacak">Cek status</a></p>
    <?php else: ?>
        <p>Kode lacak telah ditampilkan atau tidak tersedia. Masukkan kode pada halaman cek status.</p>
        <p><a class="btn primary" href="/lacak">Cek status pengajuan</a></p>
    <?php endif; ?>
</section>
