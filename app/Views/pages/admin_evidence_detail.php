<?php
/** @var string $title */
/** @var array<string, mixed> $item */
?>
<section class="page-panel">
    <div class="section-header"><h2>Detail eviden #<?= e((string) $item['id']) ?></h2></div>
    <dl class="data-table">
        <dt>Layanan</dt><dd><?= e((string) $item['service_code'] . ' — ' . $item['service_name']) ?></dd>
        <dt>Checkpoint</dt><dd><?= e((string) $item['checkpoint_code'] . ' — ' . $item['checkpoint_name']) ?></dd>
        <dt>Berkas</dt><dd><?= e((string) ($item['original_name'] ?? 'Berkas eviden')) ?></dd>
        <dt>Jenis</dt><dd><?= e((string) $item['mime']) ?> (<?= e((string) $item['size_bytes']) ?> byte)</dd>
        <dt>SHA-256</dt><dd><?= e((string) $item['sha256']) ?></dd>
        <dt>Status</dt><dd><?= e((string) $item['status']) ?></dd>
        <dt>Diunggah oleh</dt><dd><?= e((string) $item['uploader_name']) ?></dd>
        <dt>Diverifikasi oleh</dt><dd><?= e((string) ($item['verifier_name'] ?? 'Belum diverifikasi')) ?></dd>
        <?php if ($sameVerifier): ?><dt>Catatan</dt><dd>diverifikasi oleh pengunggah yang sama</dd><?php endif; ?>
        <?php if ($item['review_reason'] !== null): ?><dt>Alasan perbaikan</dt><dd><?= e((string) $item['review_reason']) ?></dd><?php endif; ?>
        <dt>Waktu unggah</dt><dd><?= e((string) $item['created_at']) ?></dd>
        <?php if ($item['verified_at'] !== null): ?><dt>Waktu keputusan</dt><dd><?= e((string) $item['verified_at']) ?></dd><?php endif; ?>
    </dl>
    <p><a class="btn" href="/admin/eviden/<?= e((string) $item['id']) ?>/berkas">Unduh berkas</a></p>
    <?php if ($item['status'] === 'DIUNGGAH'): ?>
        <form method="post" action="/admin/eviden/<?= e((string) $item['id']) ?>/verifikasi" class="auth-form">
            <?= App\Core\Csrf::field() ?>
            <label><span>Keputusan</span>
                <select name="decision" required>
                    <option value="TERVERIFIKASI">TERVERIFIKASI</option>
                    <option value="PERLU_PERBAIKAN">PERLU_PERBAIKAN</option>
                </select>
            </label>
            <label><span>Alasan (wajib untuk PERLU_PERBAIKAN, maksimal 500 karakter)</span><textarea name="review_reason" maxlength="500"></textarea></label>
            <button type="submit" class="btn primary">Simpan keputusan</button>
        </form>
    <?php endif; ?>
    <p><a href="/admin/eviden">Kembali ke daftar</a></p>
</section>
