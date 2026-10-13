<div class="home-page">
    <section class="home-carousel" role="region" aria-roledescription="carousel" aria-label="Informasi layanan">
        <div class="carousel-slides">
            <article class="carousel-slide is-active" role="group" aria-roledescription="slide" aria-label="Pelayanan publik">
                <div class="carousel-copy">
                    <p class="kicker">HUMA LAYAN</p>
                    <h2>Pelayanan publik</h2>
                    <p>Temukan informasi layanan dan jalur pengajuan yang tersedia dalam prototipe ini.</p>
                    <a class="btn primary" href="/layanan">Lihat layanan</a>
                </div>
                <img class="carousel-tingang" src="/assets/img/tingang.svg" alt="" aria-hidden="true" width="240" height="160">
            </article>
            <article class="carousel-slide" role="group" aria-roledescription="slide" aria-label="KAHAYAN AMANAH">
                <div class="carousel-copy">
                    <p class="kicker">KAHAYAN AMANAH</p>
                    <h2>Aspirasi dan pengaduan</h2>
                    <p>Sampaikan aspirasi, saran, pertanyaan, atau pengaduan melalui kanal yang tersedia.</p>
                    <?php if ($currentRole === 'ADMIN'): ?>
                        <a class="btn primary" href="/lacak-aduan">Lacak aduan</a>
                    <?php else: ?>
                        <a class="btn primary" href="/kahayan">Sampaikan pengaduan</a>
                    <?php endif; ?>
                </div>
            </article>
            <article class="carousel-slide" role="group" aria-roledescription="slide" aria-label="Survei">
                <div class="carousel-copy">
                    <p class="kicker">Survei</p>
                    <h2>Bagikan umpan balik</h2>
                    <p>Survei tersedia untuk menyampaikan umpan balik melalui jalur yang disediakan.</p>
                    <a class="btn primary" href="/survei">Buka survei</a>
                </div>
            </article>
        </div>
        <div class="carousel-controls" aria-label="Pilih slide">
            <button type="button" data-carousel-slide="0" aria-label="Tampilkan slide Pelayanan publik"></button>
            <button type="button" data-carousel-slide="1" aria-label="Tampilkan slide KAHAYAN AMANAH"></button>
            <button type="button" data-carousel-slide="2" aria-label="Tampilkan slide Survei"></button>
        </div>
    </section>

    <section class="home-quick" aria-labelledby="quick-heading">
        <h2 id="quick-heading">Layanan Cepat</h2>
        <div class="quick-service-grid">
            <a class="quick-service" href="/layanan">Katalog layanan</a>
            <a class="quick-service" href="/lacak">Lacak tiket</a>
            <?php if ($currentRole !== 'ADMIN'): ?>
                <a class="quick-service" href="/kahayan">Sampaikan pengaduan</a>
            <?php endif; ?>
            <a class="quick-service" href="/lacak-aduan">Lacak aduan</a>
            <a class="quick-service" href="/survei">Survei</a>
            <?php if ($canViewPortal): ?>
                <a class="quick-service" href="/portal">Portal saya</a>
            <?php endif; ?>
            <?php if ($currentUser === null): ?>
                <a class="quick-service" href="/login">Masuk</a>
            <?php endif; ?>
        </div>
    </section>

    <div class="home-lower">
        <section class="home-catalogue" aria-labelledby="catalogue-heading">
            <h2 id="catalogue-heading">Katalog layanan</h2>
            <p>Jelajahi informasi layanan dan pilih layanan yang sesuai.</p>
            <a class="btn secondary" href="/layanan">Lihat katalog layanan</a>
        </section>
        <section class="home-journey" aria-labelledby="journey-heading">
            <h2 id="journey-heading">Alur Pengajuan</h2>
            <ol>
                <li>Login sebagai SISWA atau GURU.</li>
                <li>Pilih layanan dan isi formulir.</li>
                <li>Unggah lampiran JPG/PNG.</li>
                <li>Simpan kode tiket lalu lacak.</li>
            </ol>
        </section>
    </div>

    <section class="stats-panel" aria-label="Ketersediaan informasi layanan">
        <p>BELUM ADA DATA</p>
    </section>
</div>
