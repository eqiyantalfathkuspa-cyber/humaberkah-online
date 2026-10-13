﻿<div class="home-page">
    <section class="motto-hero" aria-label="Motto HUMA MANTAP">
        <img class="motto-bird" src="/assets/img/tingang.svg" alt="" aria-hidden="true">
        <h1>HUMA MANTAP</h1>
        <p class="motto-sub">Layanan Publik MAN Kota Palangka Raya</p>
        <ul class="motto-list">
            <li>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M12 21s-7-4.5-9.5-9A5.5 5.5 0 0 1 12 6a5.5 5.5 0 0 1 9.5 6c-2.5 4.5-9.5 9-9.5 9z"/>
                </svg>
                Melayani dengan Cinta
            </li>
            <li>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M7 12V6.5a1.5 1.5 0 0 1 3 0V11"/>
                    <path d="M10 11V4.5a1.5 1.5 0 0 1 3 0V11"/>
                    <path d="M13 11V5.5a1.5 1.5 0 0 1 3 0V12"/>
                    <path d="M16 11V8a1.5 1.5 0 0 1 3 0v6a6 6 0 0 1-6 6h-1.5a6 6 0 0 1-4.8-2.4l-3-4a1.5 1.5 0 0 1 2.4-1.8L7 13"/>
                </svg>
                Mengabdi dengan Hati
            </li>
            <li>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="8" cy="13" r="3"/>
                    <circle cx="16" cy="13" r="3"/>
                    <circle cx="12" cy="7" r="3"/>
                </svg>
                Bersatu untuk Negeri
            </li>
        </ul>
    </section>

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

    <section class="pillars" aria-label="Tiga fitur utama">
        <article class="pillar tone-3">
            <div class="pillar-no" aria-hidden="true">1</div>
            <h3><a href="/layanan">Huma Amanah</a></h3>
            <p>Pelayanan publik, informasi layanan, dan pengajuan layanan masyarakat.</p>
        </article>
        <article class="pillar tone-2">
            <div class="pillar-no" aria-hidden="true">2</div>
            <h3><a href="/survei">Kahayan Aspirasi</a></h3>
            <p>Aspirasi, pengajuan, dan survei untuk penguatan layanan berbasis bukti.</p>
        </article>
        <article class="pillar tone-1">
            <div class="pillar-no" aria-hidden="true">3</div>
            <?php if ($canViewMadani): ?>
                <h3><a href="/admin/madani">Betang Madani</a></h3>
            <?php else: ?>
                <h3>Betang Madani</h3>
            <?php endif; ?>
            <p>Evaluasi, RTL, dan dampak layanan untuk perbaikan berkelanjutan.</p>
        </article>
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
