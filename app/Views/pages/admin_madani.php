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
    </div>

    <h3>Eviden per status</h3>
    <ul>
        <?php foreach ($report['evidence_statuses'] as $status => $total): ?>
            <li><a href="<?= e($sourceUrl('/admin/eviden')) ?>"><?= e($label($report['evidence_labels'], (string) $status)) ?>: <?= e((string) $total) ?></a></li>
        <?php endforeach; ?>
    </ul>

    <h3>Sel checklist per status</h3>
    <ul>
        <?php foreach ($report['checklist_statuses'] as $status => $total): ?>
            <li><a href="<?= e($sourceUrl('/admin/checklist-99')) ?>"><?= e($label($report['checklist_labels'], (string) $status)) ?>: <?= e((string) $total) ?></a></li>
        <?php endforeach; ?>
    </ul>

    <h3>Pengajuan per status</h3>
    <ul>
        <?php foreach ($report['application_statuses'] as $status => $total): ?>
            <li><a href="<?= e($sourceUrl('/admin/pengajuan')) ?>"><?= e($label($report['application_labels'], (string) $status)) ?>: <?= e((string) $total) ?></a></li>
        <?php endforeach; ?>
    </ul>

    <h3>Aduan per status</h3>
    <ul>
        <?php foreach ($report['complaint_statuses'] as $status => $total): ?>
            <li><a href="<?= e($sourceUrl('/admin/aduan')) ?>"><?= e($label($report['complaint_labels'], (string) $status)) ?>: <?= e((string) $total) ?></a></li>
        <?php endforeach; ?>
    </ul>
    <p>Checklist merupakan snapshot status saat ini dan tidak dibatasi oleh filter periode. Aduan tidak terhubung ke layanan; filter layanan tidak mengubah hitungan aduan.</p>
</section>
