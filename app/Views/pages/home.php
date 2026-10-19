<div class="home-page">
    <div class="hero-grid">
        <section class="home-carousel" role="region" aria-roledescription="carousel" aria-label="Informasi layanan">
        <div class="carousel-slides">
            <article class="carousel-slide is-active" role="group" aria-roledescription="slide" aria-label="Pelayanan publik">
                <div class="carousel-copy">
                    <p class="kicker">HUMA AMANAH</p>
                    <h2>Pelayanan publik</h2>
                    <p>Temukan informasi layanan dan jalur pengajuan yang tersedia.</p>
                    <a class="btn primary" href="/layanan">Lihat layanan</a>
                </div>
                <img class="carousel-art" src="/assets/img/tingang.svg" alt="" aria-hidden="true" width="240" height="160">
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
                <img class="carousel-art" src="/assets/img/perisai-dayak.svg" alt="" aria-hidden="true" width="240" height="160">
            </article>
            <article class="carousel-slide" role="group" aria-roledescription="slide" aria-label="Survei">
                <div class="carousel-copy">
                    <p class="kicker">Survei</p>
                    <h2>Bagikan umpan balik</h2>
                    <p>Survei tersedia untuk menyampaikan umpan balik melalui jalur yang disediakan.</p>
                    <a class="btn primary" href="/survei">Buka survei</a>
                </div>
                <img class="carousel-art" src="/assets/img/huma-betang.svg" alt="" aria-hidden="true" width="240" height="160">
            </article>
        </div>
        <div class="carousel-controls" aria-label="Pilih slide">
            <button type="button" data-carousel-slide="0" aria-label="Tampilkan slide Pelayanan publik"></button>
            <button type="button" data-carousel-slide="1" aria-label="Tampilkan slide KAHAYAN AMANAH"></button>
            <button type="button" data-carousel-slide="2" aria-label="Tampilkan slide Survei"></button>
        </div>
        </section>

        <section class="home-quick" aria-labelledby="quick-heading">
            <h2 id="quick-heading" class="section-title"><span>Layanan Cepat</span></h2>
            <div class="quick-service-grid">
                <a class="quick-service" href="/layanan">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/></svg>
                    Katalog layanan
                </a>
                <a class="quick-service" href="/lacak">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                    Lacak tiket
                </a>
                <?php if ($currentRole !== 'ADMIN'): ?>
                    <a class="quick-service" href="/kahayan">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 11v2a1 1 0 0 0 1 1h3l7 4V6L7 10H4a1 1 0 0 0-1 1z"/><path d="M18 9a4 4 0 0 1 0 6"/></svg>
                        Sampaikan pengaduan
                    </a>
                <?php endif; ?>
                <a class="quick-service" href="/lacak-aduan">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>
                    Lacak aduan
                </a>
                <a class="quick-service" href="/survei">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 20V10M12 20V4M19 20v-7"/></svg>
                    Survei
                </a>
                <?php if ($canViewPortal): ?>
                    <a class="quick-service" href="/portal">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg>
                        Portal saya
                    </a>
                <?php endif; ?>
                <?php if ($currentUser === null): ?>
                    <a class="quick-service" href="/login">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 4h3a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-3"/><path d="m10 17 5-5-5-5M15 12H3"/></svg>
                        Masuk
                    </a>
                <?php endif; ?>
            </div>
        </section>
    </div>

    <div class="home-cols">
        <div>
            <section class="box home-catalogue" aria-labelledby="catalogue-heading">
                <h2 id="catalogue-heading" class="section-title"><span>Katalog layanan</span></h2>
                <p>Jelajahi informasi layanan dan pilih layanan yang sesuai.</p>
                <a class="btn secondary" href="/layanan">Lihat katalog layanan</a>
            </section>
            <section class="box motto-hero" aria-label="Motto HUMA BERKAH">
                <img class="motto-bird" src="/assets/img/tingang.svg" alt="" aria-hidden="true">
                <h1>HUMA BERKAH</h1>
                <p class="motto-sub">Sistem Integrasi Pengelolaan Layanan Publik berbasis Web MAN Kota Palangka Raya</p>
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
                <section class="pillars" aria-label="Tiga fitur utama">
                    <article class="pillar tone-3">
                        <div class="pillar-no" aria-hidden="true">1</div>
                        <h3><a href="/layanan">HUMA AMANAH</a></h3>
                        <p><i>Informasi &amp; Pengajuan Layanan</i></p>
                    </article>
                    <article class="pillar tone-2">
                        <div class="pillar-no" aria-hidden="true">2</div>
                        <h3><a href="/survei">KAHAYAN INSPIRASI</a></h3>
                        <p><i>Aspirasi, Pengaduan &amp; Survei</i></p>
                    </article>
                    <article class="pillar tone-1">
                        <div class="pillar-no" aria-hidden="true">3</div>
                        <?php if ($canViewMadani): ?>
                            <h3><a href="/admin/madani">BETANG MADANI</a></h3>
                        <?php else: ?>
                            <h3>BETANG MADANI</h3>
                        <?php endif; ?>
                        <p><i>Evaluasi, RTL &amp; Dampak Layanan</i></p>
                    </article>
                </section>
            </section>
        </div>
        <div>
            <section class="box home-journey" aria-labelledby="journey-heading">
                <div class="journey-heading">
                    <h2 id="journey-heading" class="journey-heading-label">Alur Pengaduan (anonim)</h2>
                    <div class="journey-tabs" role="tablist" aria-label="Pilih alur pengaduan">
                        <button
                            type="button"
                            class="journey-tab"
                            id="journey-tab-huma"
                            role="tab"
                            aria-selected="true"
                            aria-controls="journey-panel-huma"
                            tabindex="0"
                            aria-label="Alur Huma Amanah"
                        ><span>Alur Pengaduan (Gst)</span></button>
                        <button
                            type="button"
                            class="journey-tab"
                            id="journey-tab-layanan"
                            role="tab"
                            aria-selected="false"
                            aria-controls="journey-panel-layanan"
                            tabindex="-1"
                            aria-label="Alur Pengaduan Layanan"
                        ><span>Alur Pengaduan (Log)</span></button>
                    </div>
                </div>
                <div
                    class="journey-panel"
                    id="journey-panel-huma"
                    role="tabpanel"
                    aria-labelledby="journey-tab-huma"
                    tabindex="0"
                >
                    <p class="journey-audience">Untuk Masyarakat (Guest)</p>
                    <ol>
                        <li>Buka KAHAYAN AMANAH tanpa perlu login.</li>
                        <li>Pilih jenis pengaduan, aspirasi, saran, atau pertanyaan.</li>
                        <li>Tulis isi pesan; kontak bersifat opsional.</li>
                        <li>Kirim, simpan kode lacak, lalu gunakan untuk memeriksa status.</li>
                    </ol>
                    <p><a href="/kahayan">Sampaikan melalui KAHAYAN AMANAH</a></p>
                </div>
                <div
                    class="journey-panel"
                    id="journey-panel-layanan"
                    role="tabpanel"
                    aria-labelledby="journey-tab-layanan"
                    tabindex="0"
                    hidden
                >   <p class="journey-audience">Untuk Siswa/Guru dan Tependik (Login)</p>
                    <ol>
                        <li>Login sebagai SISWA atau GURU atau Tependik.</li>
                        <li>Pilih layanan dan isi formulir.</li>
                        <li>Unggah lampiran JPG/PNG.</li>
                        <li>Simpan kode tiket lalu lacak.</li>
                    </ol>
                    <p><a href="/layanan">Pilih dan sampaikan aduan melalui LAYAN</a></p>

                </div>
            </section>
            <div class="box">
                <h2 class="section-title"><span>Ringkasan MADANI</span></h2>
                <section class="stats-panel" aria-label="Ketersediaan informasi layanan">
                    <p>BELUM ADA DATA</p>
                </section>
            </div>
        </div>
    </div>
</div>
