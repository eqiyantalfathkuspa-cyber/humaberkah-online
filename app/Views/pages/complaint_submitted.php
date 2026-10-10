<?php
/** @var string $ticketNo */
$ticketNo = $ticketNo ?? '';
$trackToken = $trackToken ?? '';
?>
<section class="page-panel">
    <div class="section-header">
        <h2>KAHAYAN AMANAH diterima</h2>
    </div>
    <?php if ($ticketNo !== '' && $trackToken !== ''): ?>
        <p>Pengiriman diterima. Simpan kode tiket dan kode lacak berikut.</p>
        <p><strong>Kode tiket:</strong> <?= e($ticketNo) ?></p>
        <p><strong>Kode lacak:</strong> <?= e($trackToken) ?></p>
        <p>Kode lacak hanya ditampilkan sekali. Simpan dengan aman; kode tidak akan ditampilkan kembali.</p>
    <?php else: ?>
        <p>Kode tiket sudah tidak tersedia pada sesi ini. Hubungi Pokja 6 sesuai prosedur yang berlaku.</p>
    <?php endif; ?>
    <p><a href="/lacak-aduan">Lacak status</a></p>
</section>
