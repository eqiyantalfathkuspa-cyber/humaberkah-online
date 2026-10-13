<?php
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$currentPageAttribute = static fn (string $path): string => $currentPath === $path
    ? ' aria-current="' . e('page') . '"'
    : '';
?>
<nav class="site-nav" id="siteNav" aria-label="Menu utama">
    <div class="site-nav-inner">
        <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="menuList">Menu</button>
        <ul class="menu" id="menuList">
            <li><a href="/"<?= $currentPageAttribute('/') ?>>Beranda</a></li>
            <li class="has-sub">
                <a href="/layanan"<?= $currentPageAttribute('/layanan') ?>>Layanan</a>
                <ul class="sub">
                    <li><a href="/layanan"<?= $currentPageAttribute('/layanan') ?>>Katalog layanan</a></li>
                    <li><a href="/lacak"<?= $currentPageAttribute('/lacak') ?>>Lacak tiket</a></li>
                    <?php if ($canViewPortal): ?>
                        <li><a href="/portal"<?= $currentPageAttribute('/portal') ?>>Portal saya</a></li>
                    <?php endif; ?>
                </ul>
            </li>
            <?php if ($currentRole !== 'ADMIN'): ?>
                <li class="has-sub">
                    <a href="/kahayan" aria-haspopup="true"<?= $currentPageAttribute('/kahayan') ?>>KAHAYAN AMANAH</a>
                    <ul class="sub">
                        <li><a href="/kahayan"<?= $currentPageAttribute('/kahayan') ?>>Sampaikan pengaduan</a></li>
                        <li><a href="/lacak-aduan"<?= $currentPageAttribute('/lacak-aduan') ?>>Lacak aduan</a></li>
                    </ul>
                </li>
            <?php else: ?>
                <li><a href="/lacak-aduan"<?= $currentPageAttribute('/lacak-aduan') ?>>Lacak aduan</a></li>
            <?php endif; ?>
            <li><a href="/survei"<?= $currentPageAttribute('/survei') ?>>Survei</a></li>
            <?php if ($canAccessAdmin): ?>
                <li class="has-sub">
                    <a href="/admin" aria-haspopup="true"<?= $currentPageAttribute('/admin') ?>>Admin</a>
                    <ul class="sub admin-menu">
                        <?php if ($canManageApplications || $canManageComplaints): ?>
                            <li class="menu-group">
                                <span class="menu-group-title">Antrean</span>
                                <ul class="menu-group-items">
                                    <?php if ($canManageApplications): ?>
                                        <li><a href="/admin/pengajuan"<?= $currentPageAttribute('/admin/pengajuan') ?>>Antrean pengajuan</a></li>
                                    <?php endif; ?>
                                    <?php if ($canManageComplaints): ?>
                                        <li><a href="/admin/aduan"<?= $currentPageAttribute('/admin/aduan') ?>>Antrean KAHAYAN AMANAH</a></li>
                                    <?php endif; ?>
                                </ul>
                            </li>
                        <?php endif; ?>
                        <?php if ($canManageServices || $canViewSurveys): ?>
                            <li class="menu-group">
                                <span class="menu-group-title">Layanan &amp; Survei</span>
                                <ul class="menu-group-items">
                                    <?php if ($canManageServices): ?>
                                        <li><a href="/admin/layanan"<?= $currentPageAttribute('/admin/layanan') ?>>Kelola layanan</a></li>
                                    <?php endif; ?>
                                    <?php if ($canViewSurveys): ?>
                                        <li><a href="/admin/survei"<?= $currentPageAttribute('/admin/survei') ?>>Hasil survei</a></li>
                                    <?php endif; ?>
                                </ul>
                            </li>
                        <?php endif; ?>
                        <?php if ($canViewEvidence || $canViewChecklist || $canViewEvaluations || $canViewRtl || $canViewImpact || $canViewMadani): ?>
                            <li class="menu-group">
                                <span class="menu-group-title">Zona Integritas</span>
                                <ul class="menu-group-items">
                                    <?php if ($canViewEvidence): ?>
                                        <li><a href="/admin/eviden"<?= $currentPageAttribute('/admin/eviden') ?>>Eviden</a></li>
                                    <?php endif; ?>
                                    <?php if ($canViewChecklist): ?>
                                        <li><a href="/admin/checklist-99"<?= $currentPageAttribute('/admin/checklist-99') ?>>Checklist 99</a></li>
                                    <?php endif; ?>
                                    <?php if ($canViewEvaluations): ?>
                                        <li><a href="/admin/evaluasi"<?= $currentPageAttribute('/admin/evaluasi') ?>>Evaluasi</a></li>
                                    <?php endif; ?>
                                    <?php if ($canViewRtl): ?>
                                        <li><a href="/admin/rtl"<?= $currentPageAttribute('/admin/rtl') ?>>RTL</a></li>
                                    <?php endif; ?>
                                    <?php if ($canViewImpact): ?>
                                        <li><a href="/admin/dampak"<?= $currentPageAttribute('/admin/dampak') ?>>Before/After</a></li>
                                    <?php endif; ?>
                                    <?php if ($canViewMadani): ?>
                                        <li><a href="/admin/madani"<?= $currentPageAttribute('/admin/madani') ?>>Dashboard MADANI</a></li>
                                    <?php endif; ?>
                                </ul>
                            </li>
                        <?php endif; ?>
                        <?php if ($canAccessAdmin || $canViewUsers || $canViewAudit): ?>
                            <li class="menu-group">
                                <span class="menu-group-title">Sistem</span>
                                <ul class="menu-group-items">
                                    <?php if ($canAccessAdmin): ?>
                                        <li><a href="/admin"<?= $currentPageAttribute('/admin') ?>>Dasbor</a></li>
                                    <?php endif; ?>
                                    <?php if ($canViewUsers): ?>
                                        <li><a href="/admin/pengguna"<?= $currentPageAttribute('/admin/pengguna') ?>>Pengguna</a></li>
                                    <?php endif; ?>
                                    <?php if ($canViewAudit): ?>
                                        <li><a href="/admin/audit"<?= $currentPageAttribute('/admin/audit') ?>>Audit</a></li>
                                    <?php endif; ?>
                                </ul>
                            </li>
                        <?php endif; ?>
                    </ul>
                </li>
            <?php endif; ?>
            <?php if ($currentUser === null): ?>
                <li class="menu-login"><a href="/login"<?= $currentPageAttribute('/login') ?>>Masuk</a></li>
            <?php else: ?>
                <li class="menu-logout">
                    <form method="post" action="/logout" class="menu-logout-form">
                        <?= App\Core\Csrf::field() ?>
                        <button type="submit" class="menu-logout-button">Keluar</button>
                    </form>
                </li>
            <?php endif; ?>
        </ul>
    </div>
</nav>