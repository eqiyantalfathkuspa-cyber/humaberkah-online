<?php
/** @var string $title */
$evaluations = $evaluations ?? [];
$rtls = $rtls ?? [];
$formValues = $formValues ?? [];
$selectedStatus = $selectedStatus ?? '';
$selectedEvaluationId = $selectedEvaluationId ?? null;
$statusLabels = [
    'DIRENCANAKAN' => 'Direncanakan',
    'BERJALAN' => 'Berjalan',
    'SELESAI' => 'Selesai',
];
?>
<section class="page-panel">
    <div class="section-header"><h2>Rencana Tindak Lanjut</h2></div>
    <p class="lede">Internal prototipe, bukan klaim kepatuhan PMPZI/LKE.</p>
    <p>Peringatan privasi: gunakan data fiktif saja. Jangan masukkan data pribadi, identitas siswa, atau informasi BK.</p>
    <?php if (($error ?? '') !== ''): ?>
        <p class="error" role="alert"><?= e((string) $error) ?></p>
    <?php endif; ?>
    <?php if (!empty($filterError)): ?>
        <p class="error" role="alert">Filter RTL tidak valid.</p>
    <?php endif; ?>

    <h3>Catat RTL</h3>
    <form method="post" action="/admin/rtl">
        <?= App\Core\Csrf::field() ?>
        <label for="rtl-evaluation">Evaluasi</label>
        <select id="rtl-evaluation" name="evaluation_id" required>
            <option value="">Pilih evaluasi</option>
            <?php foreach ($evaluations as $evaluation): ?>
                <option value="<?= e((string) $evaluation['id']) ?>" <?= (string) ($formValues['evaluation_id'] ?? $selectedEvaluationId ?? '') === (string) $evaluation['id'] ? 'selected' : '' ?>>
                    <?= e('#' . (string) $evaluation['id'] . ' — ' . (string) $evaluation['service_code'] . ' — ' . (string) $evaluation['period_start'] . ' s.d. ' . (string) $evaluation['period_end']) ?>
                    <?= $evaluation['corrects_evaluation_id'] !== null ? ' — evaluasi dikoreksi' : '' ?>
                </option>
            <?php endforeach; ?>
        </select>
        <label for="rtl-action">Uraian tindakan</label>
        <textarea id="rtl-action" name="action_description" maxlength="<?= e((string) $actionDescriptionMaxLength) ?>" required><?= e((string) ($formValues['action_description'] ?? '')) ?></textarea>
        <label for="rtl-responsible">Penanggung jawab (nama atau jabatan fiktif)</label>
        <input id="rtl-responsible" name="responsible_name" maxlength="<?= e((string) $responsibleNameMaxLength) ?>" value="<?= e((string) ($formValues['responsible_name'] ?? '')) ?>" required>
        <label for="rtl-due-date">Tenggat</label>
        <input id="rtl-due-date" type="date" name="due_date" value="<?= e((string) ($formValues['due_date'] ?? '')) ?>" required>
        <button type="submit" class="btn primary">Simpan RTL</button>
    </form>

    <h3>Daftar RTL</h3>
    <form method="get" action="/admin/rtl">
        <label for="rtl-status-filter">Status</label>
        <select id="rtl-status-filter" name="status">
            <option value="">Semua status</option>
            <?php foreach ($statusLabels as $status => $label): ?>
                <option value="<?= e($status) ?>" <?= $selectedStatus === $status ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
        <label for="rtl-evaluation-filter">Evaluasi</label>
        <select id="rtl-evaluation-filter" name="evaluation_id">
            <option value="">Semua evaluasi</option>
            <?php foreach ($evaluations as $evaluation): ?>
                <option value="<?= e((string) $evaluation['id']) ?>" <?= (string) ($selectedEvaluationId ?? '') === (string) $evaluation['id'] ? 'selected' : '' ?>>
                    <?= e('#' . (string) $evaluation['id'] . ' — ' . (string) $evaluation['service_code'] . ' — ' . (string) $evaluation['period_start']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn">Filter</button>
    </form>

    <?php if ($rtls === []): ?>
        <p>BELUM ADA DATA</p>
    <?php else: ?>
        <div class="table-scroll">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Evaluasi</th>
                        <th>Tindakan</th>
                        <th>Penanggung jawab</th>
                        <th>Tenggat</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rtls as $rtl): ?>
                        <?php $overdue = $rtl['current_status'] !== 'SELESAI' && (string) $rtl['due_date'] < date('Y-m-d'); ?>
                        <tr>
                            <td>
                                <a href="/admin/evaluasi/<?= e((string) $rtl['evaluation_id']) ?>">Evaluasi #<?= e((string) $rtl['evaluation_id']) ?></a>
                                — <?= e((string) $rtl['service_code'] . ' — ' . (string) $rtl['period_start']) ?>
                                <?= $rtl['corrects_evaluation_id'] !== null ? '(evaluasi dikoreksi)' : '' ?>
                            </td>
                            <td><?= e((string) $rtl['action_description']) ?></td>
                            <td><?= e((string) $rtl['responsible_name']) ?></td>
                            <td><?= e((string) $rtl['due_date']) ?><?= $overdue ? ' — Lewat tenggat' : '' ?></td>
                            <td><?= e($statusLabels[(string) $rtl['current_status']] ?? (string) $rtl['current_status']) ?></td>
                            <td><a href="/admin/rtl/<?= e((string) $rtl['id']) ?>">Detail</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
