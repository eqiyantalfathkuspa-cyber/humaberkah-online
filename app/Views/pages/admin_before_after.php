<?php
/** @var string $title */
$services = $services ?? [];
$rtls = $rtls ?? [];
$filterRtls = $filterRtls ?? [];
$records = $records ?? [];
$correctableRecords = $correctableRecords ?? [];
$formValues = $formValues ?? [];
$selectedServiceId = $selectedServiceId ?? null;
$selectedRtlId = $selectedRtlId ?? null;
?>
<section class="page-panel">
    <div class="section-header"><h2>Catatan Before/After</h2></div>
    <p class="lede">Catatan deskriptif internal; bukan bukti sebab-akibat, bukan bukti dampak terukur, dan bukan klaim kepatuhan PMPZI/LKE.</p>
    <p>Peringatan privasi: gunakan data fiktif saja. Jangan masukkan data pribadi, identitas siswa, atau informasi BK.</p>
    <?php if (($error ?? '') !== ''): ?>
        <p class="error" role="alert"><?= e((string) $error) ?></p>
    <?php endif; ?>
    <?php if (!empty($filterError)): ?>
        <p class="error" role="alert">Filter catatan tidak valid.</p>
    <?php endif; ?>

    <h3>Catat kondisi deskriptif</h3>
    <form method="post" action="/admin/dampak">
        <?= App\Core\Csrf::field() ?>
        <label for="impact-service">Layanan</label>
        <select id="impact-service" name="service_id" required>
            <option value="">Pilih layanan</option>
            <?php foreach ($services as $service): ?>
                <option value="<?= e((string) $service['id']) ?>" <?= (string) ($formValues['service_id'] ?? $selectedServiceId ?? '') === (string) $service['id'] ? 'selected' : '' ?>>
                    <?= e((string) $service['code'] . ' — ' . $service['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <label for="impact-rtl">RTL selesai (opsional)</label>
        <select id="impact-rtl" name="rtl_id">
            <option value="">Tanpa RTL</option>
            <?php foreach ($rtls as $rtl): ?>
                <option value="<?= e((string) $rtl['id']) ?>" <?= (string) ($formValues['rtl_id'] ?? $selectedRtlId ?? '') === (string) $rtl['id'] ? 'selected' : '' ?>>
                    <?= e('RTL #' . (string) $rtl['id'] . ' — ' . (string) $rtl['service_code'] . ' — ' . (string) $rtl['period_start'] . ' s.d. ' . (string) $rtl['period_end']) ?>
                    <?= $rtl['corrects_evaluation_id'] !== null ? ' — evaluasi dikoreksi' : '' ?>
                </option>
            <?php endforeach; ?>
        </select>
        <label for="impact-title">Judul</label>
        <input id="impact-title" name="title" maxlength="<?= e((string) $titleMaxLength) ?>" value="<?= e((string) ($formValues['title'] ?? '')) ?>" required>
        <label for="impact-before">Kondisi sebelum (narasi)</label>
        <textarea id="impact-before" name="before_text" maxlength="<?= e((string) $narrativeMaxLength) ?>" required><?= e((string) ($formValues['before_text'] ?? '')) ?></textarea>
        <label for="impact-before-date">Tanggal pengamatan sebelum</label>
        <input id="impact-before-date" type="date" name="before_observed_on" value="<?= e((string) ($formValues['before_observed_on'] ?? '')) ?>" required>
        <label for="impact-after">Kondisi sesudah (narasi)</label>
        <textarea id="impact-after" name="after_text" maxlength="<?= e((string) $narrativeMaxLength) ?>" required><?= e((string) ($formValues['after_text'] ?? '')) ?></textarea>
        <label for="impact-after-date">Tanggal pengamatan sesudah</label>
        <input id="impact-after-date" type="date" name="after_observed_on" value="<?= e((string) ($formValues['after_observed_on'] ?? '')) ?>" required>
        <label for="impact-source">Sumber pengamatan</label>
        <textarea id="impact-source" name="source_note" maxlength="<?= e((string) $sourceMaxLength) ?>" required><?= e((string) ($formValues['source_note'] ?? '')) ?></textarea>
        <label for="impact-correction">Mengoreksi catatan lama (opsional)</label>
        <select id="impact-correction" name="corrects_before_after_id">
            <option value="">Bukan koreksi</option>
            <?php foreach ($correctableRecords as $record): ?>
                <option value="<?= e((string) $record['id']) ?>" <?= (string) ($formValues['corrects_before_after_id'] ?? '') === (string) $record['id'] ? 'selected' : '' ?>>
                    <?= e('#' . (string) $record['id'] . ' — ' . (string) $record['service_code'] . ' — ' . (string) $record['title']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn primary">Simpan catatan</button>
    </form>

    <h3>Daftar catatan</h3>
    <form method="get" action="/admin/dampak">
        <label for="impact-service-filter">Layanan</label>
        <select id="impact-service-filter" name="service_id">
            <option value="">Semua layanan</option>
            <?php foreach ($services as $service): ?>
                <option value="<?= e((string) $service['id']) ?>" <?= (string) ($selectedServiceId ?? '') === (string) $service['id'] ? 'selected' : '' ?>>
                    <?= e((string) $service['code'] . ' — ' . $service['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <label for="impact-rtl-filter">RTL</label>
        <select id="impact-rtl-filter" name="rtl_id">
            <option value="">Semua RTL</option>
            <?php foreach ($filterRtls as $rtl): ?>
                <option value="<?= e((string) $rtl['id']) ?>" <?= (string) ($selectedRtlId ?? '') === (string) $rtl['id'] ? 'selected' : '' ?>>
                    <?= e('RTL #' . (string) $rtl['id'] . ' — ' . (string) $rtl['service_code'] . ' — ' . (string) $rtl['period_start']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn">Filter</button>
    </form>
    <?php if ($records === []): ?>
        <p>BELUM ADA DATA</p>
    <?php else: ?>
        <div class="table-scroll">
            <table class="data-table">
                <thead>
                    <tr><th>Layanan</th><th>Judul</th><th>Kondisi sebelum</th><th>Kondisi sesudah</th><th>Koreksi</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($records as $record): ?>
                        <?php $correctedBy = trim((string) ($record['corrected_by_ids'] ?? '')); ?>
                        <tr>
                            <td><?= e((string) $record['service_code'] . ' — ' . $record['service_name']) ?></td>
                            <td><a href="/admin/dampak/<?= e((string) $record['id']) ?>"><?= e((string) $record['title']) ?></a></td>
                            <td><?= e((string) $record['before_observed_on']) ?> — <?= e((string) $record['before_text']) ?></td>
                            <td><?= e((string) $record['after_observed_on']) ?> — <?= e((string) $record['after_text']) ?></td>
                            <td>
                                <?php if ($record['corrects_before_after_id'] !== null): ?>
                                    Mengoreksi <a href="/admin/dampak/<?= e((string) $record['corrects_before_after_id']) ?>">catatan #<?= e((string) $record['corrects_before_after_id']) ?></a>
                                <?php endif; ?>
                                <?php if ($correctedBy !== ''): ?>
                                    <?php foreach (explode(',', $correctedBy) as $correctionId): ?>
                                        <a href="/admin/dampak/<?= e($correctionId) ?>">Dikoreksi oleh #<?= e($correctionId) ?></a>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
