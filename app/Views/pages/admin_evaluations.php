<?php
/** @var string $title */
$services = $services ?? [];
$evaluations = $evaluations ?? [];
$previousEvaluations = $previousEvaluations ?? [];
$formValues = $formValues ?? [];
$selectedServiceId = $selectedServiceId ?? null;
?>
<section class="page-panel">
    <div class="section-header"><h2>Evaluasi layanan</h2></div>
    <p class="lede">Catatan internal. Ini bukan penilaian resmi dan tidak menyatakan kepatuhan PMPZI/LKE.</p>
    <p>Peringatan privasi: gunakan data fiktif saja. Jangan masukkan data pribadi, identitas siswa, atau informasi BK.</p>

    <?php if (($error ?? '') !== ''): ?>
        <p class="error" role="alert"><?= e((string) $error) ?></p>
    <?php endif; ?>
    <?php if (!empty($filterError)): ?>
        <p class="error" role="alert">Filter layanan tidak valid.</p>
    <?php endif; ?>

    <h3>Catat evaluasi</h3>
    <form method="post" action="/admin/evaluasi">
        <?= App\Core\Csrf::field() ?>
        <label for="evaluation-service">Layanan</label>
        <select id="evaluation-service" name="service_id" required>
            <option value="">Pilih layanan</option>
            <?php foreach ($services as $service): ?>
                <option value="<?= e((string) $service['id']) ?>" <?= (string) ($formValues['service_id'] ?? $selectedServiceId ?? '') === (string) $service['id'] ? 'selected' : '' ?>>
                    <?= e((string) $service['code'] . ' — ' . $service['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <label for="period-start">Tanggal mulai periode</label>
        <input id="period-start" type="date" name="period_start" value="<?= e((string) ($formValues['period_start'] ?? '')) ?>" required>
        <label for="period-end">Tanggal akhir periode</label>
        <input id="period-end" type="date" name="period_end" value="<?= e((string) ($formValues['period_end'] ?? '')) ?>" required>
        <label for="summary">Ringkasan</label>
        <textarea id="summary" name="summary" maxlength="2000" required><?= e((string) ($formValues['summary'] ?? '')) ?></textarea>
        <label for="findings">Temuan (opsional)</label>
        <textarea id="findings" name="findings" maxlength="2000"><?= e((string) ($formValues['findings'] ?? '')) ?></textarea>
        <label for="conclusion">Kesimpulan (opsional)</label>
        <textarea id="conclusion" name="conclusion" maxlength="1000"><?= e((string) ($formValues['conclusion'] ?? '')) ?></textarea>
        <label for="corrects-evaluation">Mengoreksi catatan lama (opsional)</label>
        <select id="corrects-evaluation" name="corrects_evaluation_id">
            <option value="">Bukan koreksi</option>
            <?php foreach ($previousEvaluations as $previous): ?>
                <option value="<?= e((string) $previous['id']) ?>" <?= (string) ($formValues['corrects_evaluation_id'] ?? '') === (string) $previous['id'] ? 'selected' : '' ?>>
                    <?= e('#' . (string) $previous['id'] . ' — ' . (string) $previous['service_code'] . ' — ' . (string) $previous['period_start'] . ' s.d. ' . (string) $previous['period_end']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn primary">Simpan catatan</button>
    </form>

    <h3>Riwayat evaluasi</h3>
    <form method="get" action="/admin/evaluasi">
        <label for="service-filter">Filter layanan</label>
        <select id="service-filter" name="service_id">
            <option value="">Semua layanan</option>
            <?php foreach ($services as $service): ?>
                <option value="<?= e((string) $service['id']) ?>" <?= (string) ($selectedServiceId ?? '') === (string) $service['id'] ? 'selected' : '' ?>>
                    <?= e((string) $service['code'] . ' — ' . $service['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn">Filter</button>
    </form>
    <?php if ($evaluations === []): ?>
        <p>BELUM ADA DATA</p>
    <?php else: ?>
        <div class="table-scroll">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Layanan</th>
                        <th>Periode</th>
                        <th>Ringkasan</th>
                        <th>Catatan koreksi</th>
                        <th>Dibuat</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($evaluations as $evaluation): ?>
                        <?php $correctedBy = trim((string) ($evaluation['corrected_by_ids'] ?? '')); ?>
                        <tr>
                            <td><?= e((string) $evaluation['service_code'] . ' — ' . $evaluation['service_name']) ?></td>
                            <td><?= e((string) $evaluation['period_start'] . ' s.d. ' . (string) $evaluation['period_end']) ?></td>
                            <td><a href="/admin/evaluasi/<?= e((string) $evaluation['id']) ?>"><?= e((string) $evaluation['summary']) ?></a></td>
                            <td>
                                <?php if ($evaluation['corrects_evaluation_id'] !== null): ?>
                                    Mengoreksi <a href="/admin/evaluasi/<?= e((string) $evaluation['corrects_evaluation_id']) ?>">catatan #<?= e((string) $evaluation['corrects_evaluation_id']) ?></a>
                                <?php endif; ?>
                                <?php if ($correctedBy !== ''): ?>
                                    <?php foreach (explode(',', $correctedBy) as $correctionId): ?>
                                        <a href="/admin/evaluasi/<?= e($correctionId) ?>">Dikoreksi oleh #<?= e($correctionId) ?></a>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </td>
                            <td><?= e((string) $evaluation['created_at']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
