<?php
/** @var string $title */
/** @var string $viewPath */
$currentUser = App\Core\Auth::user();
$currentRole = $currentUser['role_code'] ?? $currentUser['role'] ?? null;
$canAccessAdmin = App\Policies\Permissions::can($currentRole, 'admin.dashboard');
$canManageServices = App\Policies\Permissions::can($currentRole, 'admin.services.manage');
$canManageApplications = App\Policies\Permissions::can($currentRole, 'admin.applications.manage');
$canManageComplaints = App\Policies\Permissions::can($currentRole, 'admin.complaints.manage');
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'HUMA MANTAP') ?></title>
    <link rel="stylesheet" href="/assets/css/styles.css">
</head>
<body>
    <div class="page-shell">
        <header class="topbar">
            <div class="brand-wrap">
                <div class="brand-mark" aria-label="Logo placeholder">MAN</div>
                <div>
                    <div class="eyebrow">PROTOTIPE</div>
                    <h1 class="brand-title">HUMA MANTAP</h1>
                </div>
            </div>
            <nav class="topnav" aria-label="Navigasi utama">
                <a href="/">Beranda</a>
                <a href="/layanan">Layanan</a>
                <?php if ($currentRole !== 'ADMIN'): ?>
                    <a href="/kahayan">KAHAYAN AMANAH</a>
                <?php endif; ?>
                <a href="/lacak">Cek status pengajuan</a>
                <a href="/lacak-aduan">Lacak aduan</a>
                <a href="/laporan">Laporan</a>
                <?php if ($canAccessAdmin): ?>
                    <a href="/admin">Dashboard admin</a>
                    <a href="/admin/pengguna">Pengguna</a>
                    <a href="/admin/audit">Audit</a>
                    <?php if ($canManageServices): ?>
                        <a href="/admin/layanan">Kelola layanan</a>
                    <?php endif; ?>
                    <?php if ($canManageApplications): ?>
                        <a href="/admin/pengajuan">Antrean pengajuan</a>
                    <?php endif; ?>
                    <?php if ($canManageComplaints): ?>
                        <a href="/admin/aduan">Antrean KAHAYAN AMANAH</a>
                    <?php endif; ?>
                <?php endif; ?>
            </nav>
        </header>

        <main class="content">
            <?php require $viewPath; ?>
        </main>
    </div>
</body>
</html>
