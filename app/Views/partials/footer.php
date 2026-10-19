<footer class="site-footer">
    <div class="dayak" aria-hidden="true"></div>
    <div class="footer-inner">
        <div class="footer-grid">
            <section class="footer-column">
                <h2>Identitas</h2>
                <p><strong>HUMA BERKAH</strong></p>
                <p>Platform pelayanan publik terintegrasi MAN Kota Palangka Raya. Menyatukan akses layanan, informasi, aspirasi, dan pengaduan masyarakat secara transparan serta akuntabel demi mewujudkan pelayanan madrasah yang prima.</p>
            </section>
            <section class="footer-column">
                <h2>Layanan</h2>
                <ul>
                    <li><a href="/layanan">Katalog layanan</a></li>
                    <li><a href="/lacak">Lacak tiket</a></li>
                    <?php if ($canViewPortal): ?>
                        <li><a href="/portal">Portal saya</a></li>
                    <?php endif; ?>
                </ul>
            </section>
            <section class="footer-column">
                <h2>Pengaduan</h2>
                <ul>
                    <?php if ($currentRole !== 'ADMIN'): ?>
                        <li><a href="/kahayan">Sampaikan pengaduan</a></li>
                    <?php endif; ?>
                    <li><a href="/lacak-aduan">Lacak aduan</a></li>
                </ul>
            </section>
            <section class="footer-column">
                <h2>Kontak</h2>
                <p>Informasi kontak resmi belum dicantumkan.</p>
            </section>
        </div>
    </div>
    <div class="footer-bottom">
        <p>&copy; <?= e(date('Y')) ?> HUMA BERKAH</p>
    </div>
</footer>