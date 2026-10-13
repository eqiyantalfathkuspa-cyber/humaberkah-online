<?php
/** @var array<string,mixed> $report */
/** @var array<int,array{id:int,code:string,name:string}> $services */
$filters = $report['filters'];
$query = array_filter([
    'period_start' => $filters['period_start'],
    'period_end' => $filters['period_end'],
    'service_id' => $filters['service_id'] === null ? null : (string) $filters['service_id'],
], static fn ($value): bool => $value !== null && $value !== '');
$queryString = http_build_query($query);
$sourceUrl = static fn (string $path): string => $path . ($queryString === '' ? '' : '?' . $queryString);
$label = static fn (array $labels, string $key): string => (string) ($labels[$key] ?? $key);
$evidenceTones = [
    'DIUNGGAH' => 1,
    'TERVERIFIKASI' => 3,
    'PERLU_PERBAIKAN' => 4,
];
$checklistPresentation = [
    'VALID' => ['tone' => 3],
    'ADA_BELUM_VERIFIKASI' => ['tone' => 2],
    'PERLU_PERBAIKAN' => ['tone' => 4],
    'KOSONG' => ['tone' => 5],
];
$applicationTones = [
    'DIAJUKAN' => 1,
    'DIPROSES' => 2,
    'SELESAI' => 3,
    'DITOLAK' => 4,
];
$complaintTones = [
    'DITERIMA' => 1,
    'DIKLASIFIKASI' => 5,
    'DITINDAKLANJUTI' => 2,
    'SELESAI' => 3,
    'DITOLAK' => 4,
];
$checklistCounts = [];
$checklistTotal = 0;
foreach ($checklistPresentation as $status => $presentation) {
    $checklistCounts[$status] = max(0, (int) ($report['checklist_statuses'][$status] ?? 0));
    $checklistTotal += $checklistCounts[$status];
}
$checklistAriaLabel = 'Valid ' . $checklistCounts['VALID']
    . ', Ada belum diverifikasi ' . $checklistCounts['ADA_BELUM_VERIFIKASI']
    . ', Perlu perbaikan ' . $checklistCounts['PERLU_PERBAIKAN']
    . ', Kosong ' . $checklistCounts['KOSONG']
    . ' dari ' . $checklistTotal . ' sel';
?>
<section class="page-panel">
    <div class="section-header">
        <h2>Dashboard MADANI</h2>
        <a class="btn secondary" href="/admin/madani/ekspor<?= $queryString === '' ? '' : '?' . e($queryString) ?>">Ekspor CSV agregat</a>
    </div>
    <p>Prototipe internal; bukan bukti kepatuhan PMPZI/LKE. Data uji bersifat fiktif.</p>
    <p>Rata-rata, persentase, atau rasio tanpa penyebut: <strong>BELUM ADA DATA</strong>.</p>
    <form method="get" action="/admin/madani" class="form-grid">
        <label>Tanggal mulai
            <input type="date" name="period_start" value="<?= e((string) ($filters['period_start'] ?? '')) ?>">
        </label>
        <label>Tanggal akhir
            <input type="date" name="period_end" value="<?= e((string) ($filters['period_end'] ?? '')) ?>">
        </label>
        <label>Layanan
            <select name="service_id">
                <option value="">Semua layanan</option>
                <?php foreach ($services as $service): ?>
                    <option value="<?= e((string) $service['id']) ?>" <?= (int) $service['id'] === $filters['service_id'] ? 'selected' : '' ?>>
                        <?= e((string) $service['code']) ?> — <?= e((string) $service['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <button class="btn primary" type="submit">Terapkan filter</button>
    </form>

    <h3>Evaluasi MADANI dan catatan before/after per layanan</h3>
    <div class="table-wrap"><table>
        <thead><tr><th>Layanan</th><th>Evaluasi</th><th>Before/after</th></tr></thead>
        <tbody>
        <?php foreach ($report['services'] as $service): ?>
            <tr>
                <th><?= e((string) $service['code']) ?> — <?= e((string) $service['name']) ?></th>
                <td><a href="<?= e($sourceUrl('/admin/evaluasi')) ?>"><?= e((string) $service['evaluation_count']) ?></a></td>
                <td><a href="<?= e($sourceUrl('/admin/dampak')) ?>"><?= e((string) $service['before_after_count']) ?></a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>

    <div class="kpi-row">
        <article class="kpi"><span>Total pengajuan</span><strong><?= e((string) (int) array_sum($report['application_statuses'])) ?></strong></article>
        <article class="kpi"><span>Total aduan</span><strong><?= e((string) (int) array_sum($report['complaint_statuses'])) ?></strong></article>
        <article class="kpi"><span>Total RTL</span><strong><?= e((string) (int) array_sum($report['rtl_statuses'])) ?></strong></article>
        <article class="kpi"><span>Total eviden</span><strong><?= e((string) (int) array_sum($report['evidence_statuses'])) ?></strong></article>
    </div>
    <div class="dash-grid">
        <section class="dash-panel">
            <h3>RTL berdasarkan status terbaru</h3>
            <?php
            $rtlLabels = [
                'DIRENCANAKAN' => 'Direncanakan',
                'BERJALAN' => 'Berjalan',
                'SELESAI' => 'Selesai',
            ];
            $rtlTones = [
                'DIRENCANAKAN' => 1,
                'BERJALAN' => 2,
                'SELESAI' => 3,
            ];
            $donutItems = [];
            $rtlStackValues = [];
            $rtlStackTones = [];
            $rtlStackLabel = 'Komposisi status RTL';
            foreach ($report['rtl_statuses'] as $status => $total) {
                $rtlCount = max(0, (int) $total);
                $rtlLabel = $rtlLabels[$status] ?? (string) $status;
                $rtlTone = (int) ($rtlTones[$status] ?? 5);
                $donutItems[] = [
                    'label' => $rtlLabel,
                    'count' => $rtlCount,
                    'tone' => $rtlTone,
                ];
                $rtlStackValues[] = $rtlCount;
                $rtlStackTones[] = $rtlTone;
                $rtlStackLabel .= ', ' . $rtlLabel . ' ' . $rtlCount;
            }
            $donutTitle = 'RTL berdasarkan status terbaru';
            ?>
            <div class="chart-row">
                <?php require __DIR__ . '/../partials/donut.php'; ?>
                <ul class="st-list">
                    <?php foreach ($report['rtl_statuses'] as $status => $total):
                        $rtlCount = max(0, (int) $total);
                        $rtlLabel = $rtlLabels[$status] ?? (string) $status;
                        $rtlTone = (int) ($rtlTones[$status] ?? 5);
                    ?>
                        <li class="tone-<?= $rtlTone ?>">
                            <a href="<?= e($sourceUrl('/admin/rtl')) ?>"><span class="st-name"><?= e($rtlLabel) ?></span><strong class="st-num"><?= e((string) $rtlCount) ?></strong></a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <a class="st-alert" href="<?= e($sourceUrl('/admin/rtl')) ?>">Belum selesai dan lewat tenggat: <?= e((string) max(0, (int) $report['rtl_overdue'])) ?></a>
            <?php
            $rtlStackTotal = array_sum($rtlStackValues);
            $rtlStackDenominator = max(1, $rtlStackTotal);
            $rtlStackOffset = 0.0;
            ?>
            <svg class="stackbar" viewBox="0 0 100 8" role="img" aria-label="<?= e($rtlStackLabel) ?>" preserveAspectRatio="none">
                <rect class="bg" width="100" height="8" rx="4"/>
                <?php foreach ($rtlStackValues as $index => $rtlStackValue):
                    $rtlStackWidth = $rtlStackValue / $rtlStackDenominator * 100;
                    if ($rtlStackWidth <= 0) {
                        continue;
                    }
                ?>
                    <rect class="seg tone-<?= (int) $rtlStackTones[$index] ?>" x="<?= round($rtlStackOffset, 2) ?>" width="<?= round($rtlStackWidth, 2) ?>" height="8"/>
                    <?php $rtlStackOffset += $rtlStackWidth; ?>
                <?php endforeach; ?>
            </svg>
        </section>
        <section class="dash-panel">
            <h3>Eviden per status</h3>
            <?php
            $donutItems = [];
            foreach ($report['evidence_statuses'] as $status => $total) {
                $donutItems[] = [
                    'label' => $label($report['evidence_labels'], (string) $status),
                    'count' => max(0, (int) $total),
                    'tone' => (int) ($evidenceTones[$status] ?? 5),
                ];
            }
            $donutTitle = 'Eviden per status';
            ?>
            <div class="chart-row">
                <?php require __DIR__ . '/../partials/donut.php'; ?>
                <ul class="st-list">
                    <?php foreach ($report['evidence_statuses'] as $status => $total):
                        $statusLabel = $label($report['evidence_labels'], (string) $status);
                        $statusCount = max(0, (int) $total);
                    ?>
                        <li class="tone-<?= (int) ($evidenceTones[$status] ?? 5) ?>">
                            <a href="<?= e($sourceUrl('/admin/eviden')) ?>"><span class="st-name"><?= e($statusLabel) ?>: </span><strong class="st-num"><?= e((string) $statusCount) ?></strong></a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </section>
        <section class="dash-panel">
            <h3>Sel checklist per status</h3>
            <div class="cl99" role="img" aria-label="<?= e($checklistAriaLabel) ?>">
                <?php foreach ($checklistPresentation as $status => $presentation): ?>
                    <?php for ($cellIndex = 0; $cellIndex < $checklistCounts[$status]; $cellIndex++): ?>
                        <span class="tone-<?= (int) $presentation['tone'] ?>" aria-hidden="true"></span>
                    <?php endfor; ?>
                <?php endforeach; ?>
            </div>
            <ul class="st-list">
                <?php foreach ($report['checklist_statuses'] as $status => $total):
                    $statusLabel = $label($report['checklist_labels'], (string) $status);
                    $statusCount = max(0, (int) $total);
                ?>
                    <li class="tone-<?= (int) ($checklistPresentation[$status]['tone'] ?? 5) ?>">
                        <a href="<?= e($sourceUrl('/admin/checklist-99')) ?>"><span class="st-name"><?= e($statusLabel) ?>: </span><strong class="st-num"><?= e((string) $statusCount) ?></strong></a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
        <section class="dash-panel">
            <h3>Pengajuan per status</h3>
            <?php
            $donutItems = [];
            foreach ($report['application_statuses'] as $status => $total) {
                $donutItems[] = [
                    'label' => $label($report['application_labels'], (string) $status),
                    'count' => max(0, (int) $total),
                    'tone' => (int) ($applicationTones[$status] ?? 5),
                ];
            }
            $donutTitle = 'Pengajuan per status';
            ?>
            <div class="chart-row">
                <?php require __DIR__ . '/../partials/donut.php'; ?>
                <ul class="st-list">
                    <?php foreach ($report['application_statuses'] as $status => $total):
                        $statusLabel = $label($report['application_labels'], (string) $status);
                        $statusCount = max(0, (int) $total);
                    ?>
                        <li class="tone-<?= (int) ($applicationTones[$status] ?? 5) ?>">
                            <a href="<?= e($sourceUrl('/admin/pengajuan')) ?>"><span class="st-name"><?= e($statusLabel) ?>: </span><strong class="st-num"><?= e((string) $statusCount) ?></strong></a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </section>
        <section class="dash-panel">
            <h3>Aduan per status</h3>
            <?php
            $donutItems = [];
            foreach ($report['complaint_statuses'] as $status => $total) {
                $donutItems[] = [
                    'label' => $label($report['complaint_labels'], (string) $status),
                    'count' => max(0, (int) $total),
                    'tone' => (int) ($complaintTones[$status] ?? 5),
                ];
            }
            $donutTitle = 'Aduan per status';
            ?>
            <div class="chart-row">
                <?php require __DIR__ . '/../partials/donut.php'; ?>
                <ul class="st-list">
                    <?php foreach ($report['complaint_statuses'] as $status => $total):
                        $statusLabel = $label($report['complaint_labels'], (string) $status);
                        $statusCount = max(0, (int) $total);
                    ?>
                        <li class="tone-<?= (int) ($complaintTones[$status] ?? 5) ?>">
                            <a href="<?= e($sourceUrl('/admin/aduan')) ?>"><span class="st-name"><?= e($statusLabel) ?>: </span><strong class="st-num"><?= e((string) $statusCount) ?></strong></a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </section>
    </div>
    <p>Checklist merupakan snapshot status saat ini dan tidak dibatasi oleh filter periode. Aduan tidak terhubung ke layanan; filter layanan tidak mengubah hitungan aduan.</p>
</section>
