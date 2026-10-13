<?php
$complaints = $complaints ?? [];
$statuses = $statuses ?? [];
$types = $types ?? [];
$selectedStatus = $selectedStatus ?? '';
$selectedType = $selectedType ?? '';
$statusTones = [
    'DITERIMA' => 1,
    'DIKLASIFIKASI' => 5,
    'DITINDAKLANJUTI' => 2,
    'SELESAI' => 3,
    'DITOLAK' => 4,
];
$statusCounts = array_fill_keys(array_keys($statuses), 0);
foreach ($complaints as $complaint) {
    $status = (string) ($complaint['status'] ?? '');
    $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;
}
$donutItems = [];
foreach ($statusCounts as $status => $count) {
    $donutItems[] = [
        'label' => (string) ($statuses[$status] ?? $status),
        'count' => (int) $count,
        'tone' => (int) ($statusTones[$status] ?? 5),
    ];
}
$donutTitle = 'Status aduan pada antrean';
$selectedTypeQuery = $selectedType === '' ? '' : '&jenis=' . rawurlencode((string) $selectedType);
?>
<section class="page-panel">
    <div class="section-header"><h2>Antrean KAHAYAN AMANAH</h2></div>
    <div class="dash-grid">
        <section class="dash-panel">
            <h3>Status aduan pada antrean</h3>
            <div class="chart-row">
                <?php require __DIR__ . '/../partials/donut.php'; ?>
                <ul class="st-list">
                    <?php foreach ($statusCounts as $status => $count):
                        $statusLabel = (string) ($statuses[$status] ?? $status);
                        $statusTone = (int) ($statusTones[$status] ?? 5);
                    ?>
                        <li class="tone-<?= $statusTone ?>">
                            <a href="/admin/aduan?status=<?= e(rawurlencode((string) $status)) ?><?= e($selectedTypeQuery) ?>"><span class="st-name"><?= e($statusLabel) ?>: </span><strong class="st-num"><?= e((string) (int) $count) ?></strong></a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </section>
    </div>
    <form method="get" action="/admin/aduan" class="auth-form">
        <label><span>Status</span>
            <select name="status"><option value="">Semua status</option>
                <?php foreach ($statuses as $code => $label): ?>
                    <option value="<?= e($code) ?>" <?= $selectedStatus === $code ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label><span>Jenis</span>
            <select name="jenis"><option value="">Semua jenis</option>
                <?php foreach ($types as $type): ?>
                    <option value="<?= e($type) ?>" <?= $selectedType === $type ? 'selected' : '' ?>><?= e(ucfirst(strtolower($type))) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <button class="btn primary" type="submit">Filter</button>
    </form>
    <?php if ($complaints === []): ?>
        <p class="lede">BELUM ADA DATA</p>
    <?php else: ?>
        <div class="table-wrap"><table>
            <thead><tr><th>Kode tiket</th><th>Jenis</th><th>Status</th><th>Diterima</th><th></th></tr></thead>
            <tbody><?php foreach ($complaints as $complaint): ?>
                <tr>
                    <td><?= e((string) $complaint['ticket_no']) ?></td>
                    <td><?= e((string) $complaint['jenis']) ?></td>
                    <td><?= e((string) ($statuses[$complaint['status']] ?? $complaint['status'])) ?></td>
                    <td><?= e((string) $complaint['created_at']) ?></td>
                    <td><a href="/admin/aduan/<?= e((string) $complaint['id']) ?>">Detail</a></td>
                </tr>
            <?php endforeach; ?></tbody>
        </table></div>
    <?php endif; ?>
</section>
