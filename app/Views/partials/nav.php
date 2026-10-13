<nav class="site-nav" id="siteNav" aria-label="Menu utama">
    <div class="site-nav-inner">
        <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="menuList">Menu</button>
        <ul class="menu" id="menuList">
            <li><a href="/">Beranda</a></li>
            <li class="has-sub">
                <a href="/layanan" aria-haspopup="true">Layanan</a>
                <ul class="sub">
                    <li><a href="/layanan">Katalog layanan</a></li>
                    <li><a href="/lacak">Lacak tiket</a></li>
                    <?php if ($canViewPortal): ?>
                        <li><a href="/portal">Portal saya</a></li>
                    <?php endif; ?>
                </ul>
            </li>
            <?php if ($currentRole !== 'ADMIN'): ?>
                <li class="has-sub">
                    <a href="/kahayan" aria-haspopup="true">KAHAYAN AMANAH</a>
                    <ul class="sub">
                        <li><a href="/kahayan">Sampaikan pengaduan</a></li>
                        <li><a href="/lacak-aduan">Lacak aduan</a></li>
                    </ul>
                </li>
            <?php else: ?>
                <li><a href="/lacak-aduan">Lacak aduan</a></li>
            <?php endif; ?>
            <li><a href="/survei">Survei</a></li>
            <?php if ($canAccessAdmin): ?>
                <li class="has-sub">
                    <a href="/admin" aria-haspopup="true">Admin</a>
                    <ul class="sub admin-menu">
                        <?php if ($canManageApplications || $canManageComplaints): ?>
                            <li class="menu-group">
                                <span class="menu-group-title">Antrean</span>
                                <ul class="menu-group-items">
                                    <?php if ($canManageApplications): ?>
                                        <li><a href="/admin/pengajuan">Antrean pengajuan</a></li>
                                    <?php endif; ?>
                                    <?php if ($canManageComplaints): ?>
                                        <li><a href="/admin/aduan">Antrean KAHAYAN AMANAH</a></li>
                                    <?php endif; ?>
                                </ul>
                            </li>
                        <?php endif; ?>
                        <?php if ($canManageServices || $canViewSurveys): ?>
                            <li class="menu-group">
                                <span class="menu-group-title">Layanan &amp; Survei</span>
                                <ul class="menu-group-items">
                                    <?php if ($canManageServices): ?>
                                        <li><a href="/admin/layanan">Kelola layanan</a></li>
                                    <?php endif; ?>
                                    <?php if ($canViewSurveys): ?>
                                        <li><a href="/admin/survei">Hasil survei</a></li>
                                    <?php endif; ?>
                                </ul>
                            </li>
                        <?php endif; ?>
                        <?php if ($canViewEvidence || $canViewChecklist || $canViewEvaluations || $canViewRtl || $canViewImpact || $canViewMadani): ?>
                            <li class="menu-group">
                                <span class="menu-group-title">Zona Integritas</span>
                                <ul class="menu-group-items">
                                    <?php if ($canViewEvidence): ?>
                                        <li><a href="/admin/eviden">Eviden</a></li>
                                    <?php endif; ?>
                                    <?php if ($canViewChecklist): ?>
                                        <li><a href="/admin/checklist-99">Checklist 99</a></li>
                                    <?php endif; ?>
                                    <?php if ($canViewEvaluations): ?>
                                        <li><a href="/admin/evaluasi">Evaluasi</a></li>
                                    <?php endif; ?>
                                    <?php if ($canViewRtl): ?>
                                        <li><a href="/admin/rtl">RTL</a></li>
                                    <?php endif; ?>
                                    <?php if ($canViewImpact): ?>
                                        <li><a href="/admin/dampak">Before/After</a></li>
                                    <?php endif; ?>
                                    <?php if ($canViewMadani): ?>
                                        <li><a href="/admin/madani">Dashboard MADANI</a></li>
                                    <?php endif; ?>
                                </ul>
                            </li>
                        <?php endif; ?>
                        <?php if ($canAccessAdmin || $canViewUsers || $canViewAudit): ?>
                            <li class="menu-group">
                                <span class="menu-group-title">Sistem</span>
                                <ul class="menu-group-items">
                                    <?php if ($canAccessAdmin): ?>
                                        <li><a href="/admin">Dasbor</a></li>
                                    <?php endif; ?>
                                    <?php if ($canViewUsers): ?>
                                        <li><a href="/admin/pengguna">Pengguna</a></li>
                                    <?php endif; ?>
                                    <?php if ($canViewAudit): ?>
                                        <li><a href="/admin/audit">Audit</a></li>
                                    <?php endif; ?>
                                </ul>
                            </li>
                        <?php endif; ?>
                    </ul>
                </li>
            <?php endif; ?>
            <?php if ($currentUser === null): ?>
                <li class="menu-login"><a href="/login">Masuk</a></li>
            <?php endif; ?>
        </ul>
    </div>
</nav>