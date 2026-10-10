<?php
$complaints = $complaints ?? [];
$statuses = $statuses ?? [];
$types = $types ?? [];
$selectedStatus = $selectedStatus ?? '';
$selectedType = $selectedType ?? '';
?>
<section class="page-panel">
    <div class="section-header"><h2>Antrean KAHAYAN AMANAH</h2></div>
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
