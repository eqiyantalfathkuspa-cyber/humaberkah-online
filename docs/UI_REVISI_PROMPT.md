# PROMPT AGENT: Revisi UI HUMA MANTAP (motto, dashboard visual, tanpa tampilan mentah)

## Cara memakai (untuk pemilik proyek)

1. Simpan berkas ini sebagai `docs/UI_REVISI_PROMPT.md`.
2. Nyalakan MySQL Laragon (Start All).
3. Tempel pesan ini ke chat agent, **satu tahap per pesan**:

> Baca `docs/UI_PROMPT.md` (aturan umum) dan `docs/UI_REVISI_PROMPT.md` sampai habis. Kerjakan **TAHAP 0 saja** dari UI_REVISI_PROMPT, lalu berhenti dan laporkan dengan format laporan.

Setelah laporan Anda cek di browser, kirim "Lanjut TAHAP 1", dan seterusnya.

Pratinjau visual yang sudah disetujui: https://claude.ai/artifact/QQ3iLMrBV5Gyj4sWadSzAT (agent tidak bisa membukanya, semua kode penting ada di bawah).

---

## 1. Tujuan revisi

1. **Motto dan identitas.** Beranda menampilkan:
   - HUMA MANTAP, subjudul "Layanan Publik MAN Kota Palangka Raya"
   - Motto: *Melayani dengan Cinta*, *Mengabdi dengan Hati*, *Bersatu untuk Negeri*
   - Tiga fitur utama:
     1. **Huma Amanah**: Pelayanan Publik, Informasi Layanan & Pengajuan Layanan Masyarakat
     2. **Kahayan Aspirasi**: Aspirasi, Pengajuan, dan Survei untuk Penguatan Layanan Berbasis Bukti
     3. **Betang Madani**: Evaluasi, RTL dan Dampak Layanan untuk Perbaikan Berkelanjutan
2. **Dashboard Admin visual.** Data pengajuan layanan, pengaduan KAHAYAN AMANAH, dan survei tampil sebagai grafik donat, kartu status yang bisa diklik, dan batang skor.
3. **Tidak ada tampilan mentah.** Daftar status (RTL, Eviden, Sel checklist, Pengajuan, Aduan) dan elemen lain tidak boleh tampil sebagai teks polos/bullet bawaan browser. Semua memakai komponen yang menyatu dengan font dan warna aplikasi, termasuk mode gelap.

## 2. Aturan keras

Semua aturan di `docs/UI_PROMPT.md` bagian 2 dan 3 **tetap berlaku** (batas file, `e()`, `_csrf`, honeypot `website`, nama field, larangan membaca `config.php`, tidak ada `href="#"`, tidak ada aset eksternal, staging satu per satu, `tests/run.php` dan `logo-man*.png` tidak boleh ter-stage, tidak merge/push). Tambahan khusus revisi ini:

1. **Controller dan Router tidak boleh diubah.** Grafik hanya boleh memakai variabel/angka yang **sudah** dikirim controller ke view. Jika sebuah angka tidak tersedia di view, jangan menambahkannya: laporkan.
2. **Literal tes dilindungi.** Sebelum mengubah satu bagian view, `grep` di `tests/` untuk teks/HTML bagian itu. Semua literal yang ditemukan harus **tetap persis sama** (urutan atribut, tanda baca, huruf besar-kecil). Contoh yang diketahui: `BELUM ADA DATA`, `DATA TERLALU SEDIKIT`, `<section class="stats-panel"`, serta anchor admin. Jika literal tidak bisa dipertahankan, berhenti dan laporkan.
3. **Grafik dirender di server** (SVG/CSS dari PHP), bukan JavaScript. Halaman harus utuh tanpa JS. JS boleh dipakai hanya untuk penyempurna kecil.
4. **Warna tidak boleh menjadi satu-satunya penanda.** Setiap status punya label teks dan angka. Elemen grafik non-teks butuh kontras ≥3:1 terhadap latar (terang dan gelap); teks ≥4.5:1. Laporkan hasil hitungan.
5. **Angka asli, bukan data contoh.** Jangan menambahkan data buatan. Keadaan kosong (semua 0) harus tampil rapi: cincin donat abu-abu, angka 0.
6. **Jangan mengganti nama modul yang sudah ada** (mis. "KAHAYAN AMANAH" untuk pengaduan di nav dan di tes). Nama fitur utama (Huma Amanah, Kahayan Aspirasi, Betang Madani) hanya muncul sebagai konten di beranda.
7. Ikon memakai SVG sederhana inline (`aria-hidden="true"`), **bukan emoji**, agar tampil konsisten di Windows.
8. Satu `<h1>` per halaman. Periksa dulu apakah `home.php` sudah punya `<h1>`.

## 3. Aturan kerja dan format laporan

Sama seperti `UI_PROMPT.md` bagian 3 dan 4: cabang `ui`, satu tahap per sesi, commit kecil, jalankan tes **hanya bila diizinkan di pesan tahap**, tampilkan baris `[GAGAL]` bila ada, berhenti bila `git diff --check` atau lint gagal dua kali. Tambahkan pada setiap laporan: daftar literal tes yang dilindungi dan terbukti utuh.

---

## TAHAP 0: Audit (hanya membaca, tanpa tes, tanpa perubahan)

Jangan mengubah file, jangan commit, jangan menjalankan tes. Laporkan:

1. `git status --short --branch`, `git log --oneline -8`. Apakah Tahap 5B ("ui: tata kelas layanan dan login") sudah ada di riwayat? Apakah `.service-category`, `.service-list`, `.service-standards`, `.service-steps`, `.auth-copy` sudah punya aturan di `styles.css`?
2. **Peta daftar status.** Untuk setiap daftar berikut, tunjukkan: view, route/halaman tempat ia muncul, variabel data yang dipakai, dan potongan HTML-nya saat ini (tanpa mengubah):
   - Dashboard admin (`/admin`): Pengajuan layanan (Diajukan/Diproses/Selesai/Ditolak), Pengaduan KAHAYAN AMANAH (Diterima/Diklasifikasi/Ditindaklanjuti/Selesai/Ditolak), Survei kepuasan (jumlah responden, rata-rata kecepatan/kejelasan/keramahan), Survei layanan informasi (jumlah responden).
   - RTL berdasarkan status terbaru (Direncanakan/Berjalan/Selesai/Belum selesai dan lewat tenggat).
   - Eviden per status (Diunggah/Terverifikasi/Perlu perbaikan).
   - Sel checklist per status (Valid/Ada, belum diverifikasi/Perlu perbaikan/Kosong) dari 99 sel.
   - Pengajuan per status dan Aduan per status (di halaman mana saja muncul).
3. **Literal tes dilindungi** untuk setiap bagian di atas (file dan baris tes, teks persisnya). Khususnya: apakah tes mengunci bentuk anchor seperti `<a href="/admin/pengajuan?status=DIAJUKAN">Diajukan: 0</a>`.
4. Apakah checklist 99 sel tersedia sebagai daftar per sel (dengan status masing-masing) atau hanya hitungan per status?
5. Inventaris **elemen mentah** di seluruh `app/Views/pages` dan `layouts`/`partials`: daftar `<ul>/<ol>/<dl>` yang tidak berada dalam wadah berkelas dan tidak punya gaya di CSS (sebutkan file:baris). Juga cek apakah `button, input, select, textarea` mewarisi `font-family` (periksa `styles.css`).
6. Cara view memanggil partial dan helper (`e()`, pola `require`/`include`), supaya partial baru mengikuti pola yang sama.
7. Usul pemetaan: bagian mana dikerjakan di Tahap 2 (beranda), 3 (dashboard admin), 4 (halaman lain).

---

## TAHAP 1: Komponen CSS dan partial (belum dipakai di view)

BATAS FILE: `public/assets/css/styles.css` dan partial baru di `app/Views/partials/`. Tidak ada view yang diubah. Izin: jalankan `php tests/run.php` SATU kali di akhir, bandingkan dengan baseline terakhir (288 LOLOS / 0 GAGAL).

### 1a. Token warna status

Gabungkan ke blok `:root` dan blok mode gelap yang sudah ada (jangan membuat blok `@media` ganda jika sudah ada):

```css
:root{
  --tone-1:#1d4ed8; /* biru: baru/diajukan/direncanakan/diunggah */
  --tone-2:#a35f00; /* kuning tua: proses/berjalan/belum diverifikasi */
  --tone-3:#0b6b3a; /* hijau: selesai/valid/terverifikasi */
  --tone-4:#b42318; /* merah: ditolak/perlu perbaikan/lewat tenggat */
  --tone-5:#6b7280; /* abu: netral/kosong/diklasifikasi */
}
@media (prefers-color-scheme:dark){
  :root{
    --tone-1:#7aa2ff; --tone-2:#f0b04a; --tone-3:#4cc38a;
    --tone-4:#ff8f84; --tone-5:#9aa3b2;
  }
}
.tone-1{--t:var(--tone-1)} .tone-2{--t:var(--tone-2)} .tone-3{--t:var(--tone-3)}
.tone-4{--t:var(--tone-4)} .tone-5{--t:var(--tone-5)}
```

### 1b. Dasar tipografi (penyebab "teks tidak menyatu dengan font")

```css
button,input,select,textarea{font:inherit;color:inherit}
.page-panel :where(p,ul,ol,dl){margin:0 0 12px}
.page-panel :where(ul,ol){padding-left:1.2em}
```

Sesuaikan dengan selector wadah halaman yang ada. Jangan mengubah tampilan field honeypot.

### 1c. Komponen (pakai nama variabel yang sudah ada: `--white`, `--cream`, `--border`, `--grey`, `--text`, `--green`, `--green-d`, `--gold`, `--navy`, `--shadow`; sesuaikan bila berbeda)

```css
/* Donat (SVG dirender server) */
.donut{width:160px;height:160px;flex:none}
.donut-track{stroke:var(--border)}
.donut-seg{stroke:var(--t);transition:stroke-dasharray .6s}
.donut-num{font-family:inherit;font-size:7px;font-weight:700;fill:var(--text)}
.donut-cap{font-family:inherit;font-size:2.6px;fill:var(--grey)}

/* Panel grafik */
.dash-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:14px}
.dash-panel{background:var(--white);border:1px solid var(--border);border-radius:12px;padding:16px;box-shadow:var(--shadow)}
.dash-panel h2,.dash-panel h3{margin:0 0 12px;font-size:1.05rem}
.chart-row{display:flex;flex-wrap:wrap;gap:18px;align-items:center}

/* Kartu ringkasan (KPI) */
.kpi-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;margin-bottom:14px}
.kpi{background:var(--white);border:1px solid var(--border);border-radius:12px;padding:14px}
.kpi strong{display:block;font-size:1.7rem;line-height:1.2}
.kpi span{color:var(--grey);font-size:.85rem}

/* Daftar status (menggantikan bullet mentah). Gaya dipasang pada wadah <ul>/<li>,
   anchor di dalamnya TIDAK diubah markup-nya. */
.st-list{list-style:none;margin:0;padding:0;display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:8px;flex:1}
.st-list li{margin:0}
.st-list a{display:flex;align-items:center;gap:8px;padding:10px 12px;border:1px solid var(--border);
  border-left:5px solid var(--t);border-radius:8px;background:var(--cream);color:var(--text);
  font-weight:600;text-decoration:none;transition:transform .15s,box-shadow .15s}
.st-list a::after{content:"\2192";margin-left:auto;color:var(--grey)}
.st-list a:hover,.st-list a:focus-visible{transform:translateY(-2px);box-shadow:0 5px 12px rgba(0,0,0,.14)}

/* Batang bertumpuk dan batang skor (SVG) */
.stackbar,.meter-bar{width:100%;height:14px;display:block}
.stackbar .bg,.meter-bar .bg{fill:var(--border)}
.stackbar .seg{fill:var(--t)}
.meter-bar .fill{fill:var(--gold)}
.meter{margin:10px 0}
.meter-head{display:flex;justify-content:space-between;gap:8px;font-size:.9rem;margin-bottom:4px}

/* 99 sel checklist */
.cl99{display:grid;grid-template-columns:repeat(auto-fill,minmax(22px,1fr));gap:4px;margin:0 0 12px}
.cl99 span{aspect-ratio:1;border-radius:4px;background:var(--t)}

/* Hero motto dan fitur utama */
.motto-hero{position:relative;overflow:hidden;border-radius:14px;padding:30px 26px;color:#fff;
  background:linear-gradient(150deg,var(--green),var(--navy) 130%)}
.motto-hero h1{margin:0;font-size:2rem;letter-spacing:.5px}
.motto-sub{margin:2px 0 16px;opacity:.92}
.motto-list{display:flex;flex-wrap:wrap;gap:10px;margin:0;padding:0;list-style:none}
.motto-list li{display:flex;align-items:center;gap:8px;background:#ffffff1f;border:1px solid #ffffff40;
  border-radius:30px;padding:8px 16px;font-weight:600}
.motto-list svg{width:18px;height:18px;flex:none}
.motto-bird{position:absolute;right:14px;top:10px;width:170px;opacity:.22;pointer-events:none}
.pillars{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:14px;margin:16px 0}
.pillar{background:var(--white);border:1px solid var(--border);border-top:5px solid var(--t);
  border-radius:12px;padding:18px;transition:transform .2s,box-shadow .2s}
.pillar:hover{transform:translateY(-4px);box-shadow:0 10px 22px rgba(0,0,0,.14)}
.pillar-no{width:40px;height:40px;border-radius:50%;background:var(--t);color:#fff;display:grid;place-items:center;font-weight:700;margin-bottom:8px}
.pillar h3{margin:0 0 4px}
.pillar p{margin:0;color:var(--grey);font-size:.92rem}
@media (prefers-reduced-motion:reduce){.donut-seg,.st-list a,.pillar{transition:none}}
@media (max-width:600px){.motto-hero h1{font-size:1.6rem}.motto-bird{width:100px}}
```

Catatan: teks `#fff` di atas `--green` harus tetap ≥4.5:1 di kedua mode; jika tidak, pakai `--green-d`. `.pillar-no` memakai tone sebagai latar: periksa kontras teks putih pada `--tone-2` (kuning tua) dan di mode gelap (tone terang), ganti teks menjadi `--navy` bila perlu.

### 1d. Partial donat

`app/Views/partials/donut.php` (ikuti pola partial dan helper `e()` yang ditemukan di Tahap 0). Input: `$donutTitle` (string) dan `$donutItems` (array `['label'=>..., 'count'=>int, 'tone'=>1..5]`). Keliling lingkaran dibuat tepat 100 sehingga persen = panjang busur.

```php
<?php
$total = 0;
foreach ($donutItems as $it) { $total += (int)$it['count']; }
$offset = 0;
?>
<svg class="donut" viewBox="0 0 42 42" role="img"
     aria-label="<?= e($donutTitle) ?>: total <?= (int)$total ?>">
  <circle class="donut-track" cx="21" cy="21" r="15.9155" fill="none" stroke-width="6"/>
  <?php if ($total > 0): foreach ($donutItems as $it):
      $pct = (int)$it['count'] / $total * 100;
      if ($pct <= 0) { continue; } ?>
    <circle class="donut-seg tone-<?= (int)$it['tone'] ?>" cx="21" cy="21" r="15.9155"
            fill="none" stroke-width="6"
            stroke-dasharray="<?= round($pct, 2) ?> <?= round(100 - $pct, 2) ?>"
            stroke-dashoffset="<?= round(25 - $offset, 2) ?>"/>
    <?php $offset += $pct; endforeach; endif; ?>
  <text class="donut-num" x="21" y="22.5" text-anchor="middle"><?= (int)$total ?></text>
  <text class="donut-cap" x="21" y="27" text-anchor="middle">total</text>
</svg>
```

Buat juga `meter_bar.php` (input `$meterValue` float atau null, `$meterMax` default 5) berisi SVG `viewBox="0 0 100 8"` dengan `<rect class="bg" width="100" height="8" rx="4"/>` dan `<rect class="fill" width="{persen}" height="8" rx="4"/>` hanya bila nilai tidak null (angka lewat atribut SVG, bukan atribut `style`).

Commit: `ui: komponen grafik, token status, dasar tipografi`. Tes sekali, laporkan.

---

## TAHAP 2: Beranda (motto dan tiga fitur utama)

BATAS FILE: `app/Views/pages/home.php`, `styles.css`, bila perlu satu partial. Izin: jalankan tes SATU kali di akhir.

Syarat khusus:
- Tambahkan `.motto-hero` di **atas** slider yang sudah ada dan tiga kartu `.pillars` **di bawah** Layanan Cepat. Slider, Layanan Cepat, Alur Pengajuan, `.stats-panel` dan `BELUM ADA DATA` tidak boleh hilang atau berubah teksnya.
- Jika `home.php` sudah memiliki `<h1>`, pindahkan peran `<h1>` ke hero dan turunkan yang lama menjadi `<h2>` hanya jika tidak ada literal tes yang bergantung padanya.
- Kartu Huma Amanah menaut ke `/layanan`; Kahayan Aspirasi menaut ke `/survei` (dan `/kahayan` hanya untuk peran yang boleh, sesuai kondisi lama); **Betang Madani hanya berupa tautan ke `/admin/madani` untuk ADMIN**, untuk peran lain tampil tanpa tautan. Gunakan kondisi peran yang sama seperti `nav.php`.

Contoh markup (sesuaikan kondisi peran dengan yang ada):

```php
<section class="motto-hero">
  <img class="motto-bird" src="/assets/img/tingang.svg" alt="" aria-hidden="true">
  <h1>HUMA MANTAP</h1>
  <p class="motto-sub">Layanan Publik MAN Kota Palangka Raya</p>
  <ul class="motto-list">
    <li>
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
        <path d="M12 21s-7-4.5-9.5-9A5.5 5.5 0 0 1 12 6a5.5 5.5 0 0 1 9.5 6c-2.5 4.5-9.5 9-9.5 9z"/>
      </svg>Melayani dengan Cinta
    </li>
    <li><!-- ikon SVG garis sederhana -->Mengabdi dengan Hati</li>
    <li><!-- ikon SVG garis sederhana -->Bersatu untuk Negeri</li>
  </ul>
</section>

<section class="pillars" aria-label="Tiga fitur utama">
  <article class="pillar tone-3">
    <div class="pillar-no" aria-hidden="true">1</div>
    <h3>Huma Amanah</h3>
    <p>Pelayanan publik, informasi layanan, dan pengajuan layanan masyarakat.</p>
  </article>
  <article class="pillar tone-2">
    <div class="pillar-no" aria-hidden="true">2</div>
    <h3>Kahayan Aspirasi</h3>
    <p>Aspirasi, pengajuan, dan survei untuk penguatan layanan berbasis bukti.</p>
  </article>
  <article class="pillar tone-1">
    <div class="pillar-no" aria-hidden="true">3</div>
    <h3>Betang Madani</h3>
    <p>Evaluasi, RTL, dan dampak layanan untuk perbaikan berkelanjutan.</p>
  </article>
</section>
```

Warna kartu boleh disesuaikan (hijau, emas, navy) agar serasi dengan identitas; pastikan kontras. Ikon "tangan" dan "jabat tangan" cukup bentuk garis sederhana, jangan menyalin ikon berhak cipta.

Commit: `ui: motto dan tiga fitur utama di beranda`. Tes sekali, laporkan.

---

## TAHAP 3: Dashboard Admin (`/admin`) visual

BATAS FILE: view dashboard admin (hasil Tahap 0), `styles.css`, partial. Izin: tes SATU kali.

Susunan: baris `.kpi-row` (Pengajuan total, Aduan total, Responden survei, dan satu angka relevan yang sudah ada), lalu `.dash-grid` berisi panel: Pengajuan layanan, Pengaduan KAHAYAN AMANAH, Survei kepuasan, Survei layanan informasi. Tiap panel status berisi donat dan daftar status.

**Pola utama: daftar status tetap `<ul><li><a>` dengan teks `Label: N` persis seperti sekarang; hanya wadahnya diberi kelas.** Contoh (variabel `$counts` hanyalah ilustrasi; pakai nama variabel yang sudah ada di view):

```php
<section class="dash-panel">
  <h2>Pengajuan layanan</h2>
  <div class="chart-row">
    <?php
      $donutTitle = 'Pengajuan layanan';
      $donutItems = [
        ['label'=>'Diajukan','count'=>(int)$counts['DIAJUKAN'],'tone'=>1],
        ['label'=>'Diproses','count'=>(int)$counts['DIPROSES'],'tone'=>2],
        ['label'=>'Selesai','count'=>(int)$counts['SELESAI'],'tone'=>3],
        ['label'=>'Ditolak','count'=>(int)$counts['DITOLAK'],'tone'=>4],
      ];
      require __DIR__ . '/../partials/donut.php';
    ?>
    <ul class="st-list">
      <li class="tone-1"><a href="/admin/pengajuan?status=DIAJUKAN">Diajukan: <?= (int)$counts['DIAJUKAN'] ?></a></li>
      <li class="tone-2"><a href="/admin/pengajuan?status=DIPROSES">Diproses: <?= (int)$counts['DIPROSES'] ?></a></li>
      <li class="tone-3"><a href="/admin/pengajuan?status=SELESAI">Selesai: <?= (int)$counts['SELESAI'] ?></a></li>
      <li class="tone-4"><a href="/admin/pengajuan?status=DITOLAK">Ditolak: <?= (int)$counts['DITOLAK'] ?></a></li>
    </ul>
  </div>
</section>
```

Aturan tahap ini:
- Bila hasil `grep` menunjukkan **tidak ada tes** yang mengunci bentuk anchor, anchor boleh memuat angka besar terpisah (`<a ...><strong>12</strong> Diajukan</a>`). Jika ada tes yang mengunci, pertahankan persis seperti contoh di atas.
- Nada warna: Pengajuan (Diajukan 1, Diproses 2, Selesai 3, Ditolak 4). Aduan (Diterima 1, Diklasifikasi 5, Ditindaklanjuti 2, Selesai 3, Ditolak 4).
- Survei kepuasan: tampilkan "Jumlah responden", lalu tiga `.meter` (kecepatan, kejelasan, keramahan). Teks `BELUM ADA DATA` dan `DATA TERLALU SEDIKIT` tetap persis seperti kondisi lama, termasuk kapan ia muncul; batang skor hanya digambar bila ada nilai. Contoh:

```php
<div class="meter">
  <div class="meter-head"><span>Rata-rata kecepatan</span><strong><?= /* nilai atau teks lama persis */ ?></strong></div>
  <?php $meterValue = $avgSpeed ?? null; $meterMax = 5; require __DIR__ . '/../partials/meter_bar.php'; ?>
</div>
```

- Survei layanan informasi (tanpa verifikasi): panel kecil dengan jumlah responden.
- Semua teks dinamis lewat `e()`, angka lewat `(int)`/`(float)`.
- Tautan hanya ke rute yang ada. Halaman tetap utuh tanpa JS.

Commit: `ui: dashboard admin visual`. Tes sekali, laporkan.

---

## TAHAP 4: RTL, Eviden, Checklist, Pengajuan, Aduan (di mana pun muncul)

BATAS FILE: view yang ditemukan di Tahap 0 (kemungkinan dashboard MADANI dan halaman admin terkait), `styles.css`, partial. Izin: tes SATU kali per kelompok commit.

Kerjakan per kelompok dan commit per kelompok, dengan pola yang sama seperti Tahap 3 (donat + `.st-list`):

1. **RTL berdasarkan status terbaru:** Direncanakan (1), Berjalan (2), Selesai (3), Belum selesai dan lewat tenggat (4). Tambahan: `.stackbar` bertumpuk untuk perbandingan cepat (contoh di bawah).
2. **Eviden per status:** Diunggah (1), Terverifikasi (3), Perlu perbaikan (4).
3. **Sel checklist per status:** Valid (3), Ada, belum diverifikasi (2), Perlu perbaikan (4), Kosong (5). Tampilkan 99 kotak `.cl99`. Bila view hanya punya hitungan, buat kotak dari hitungan (urutan: Valid, Ada belum diverifikasi, Perlu perbaikan, Kosong); jika ada data per sel, ikuti urutan sel aslinya. Pembungkus `role="img"` dengan `aria-label` ringkasan; kotak `aria-hidden="true"`.
4. **Pengajuan per status** dan **Aduan per status** (di halaman selain dashboard): gunakan partial dan pola yang sama.

Contoh batang bertumpuk (SVG, angka lewat atribut):

```php
<?php $x = 0; $tot = max(1, array_sum($vals)); ?>
<svg class="stackbar" viewBox="0 0 100 8" role="img" aria-label="Komposisi status RTL" preserveAspectRatio="none">
  <rect class="bg" width="100" height="8" rx="4"/>
  <?php foreach ($vals as $i => $n): $w = $n / $tot * 100; if ($w <= 0) { continue; } ?>
    <rect class="seg tone-<?= (int)$tones[$i] ?>" x="<?= round($x, 2) ?>" width="<?= round($w, 2) ?>" height="8"/>
  <?php $x += $w; endforeach; ?>
</svg>
```

Contoh kotak checklist:

```php
<div class="cl99" role="img" aria-label="Valid <?= (int)$c['valid'] ?>, Perlu perbaikan <?= (int)$c['fix'] ?>, Kosong <?= (int)$c['empty'] ?> dari 99 sel">
  <?php foreach ($cellTones as $tone): ?><span class="tone-<?= (int)$tone ?>" aria-hidden="true"></span><?php endforeach; ?>
</div>
```

Syarat:
- Teks `Label: N` dan tautan yang ada tetap persis seperti kondisi lama (cek literal tes dulu).
- Jangan menghilangkan tautan filter (`?status=...`) yang sudah ada.
- Tabel dan formulir di halaman-halaman itu tidak diubah markup-nya.

---

## TAHAP 5: Penyapuan "tampilan mentah" dan verifikasi akhir (baca-saja, tanpa tes)

1. Berdasarkan inventaris Tahap 0 poin 5, laporkan elemen mentah yang **masih** tersisa (file:baris), dan tandai mana yang bisa diselesaikan hanya dengan CSS. Jangan memperbaiki di tahap ini; usulkan saja.
2. `git diff --name-only master..ui`: pastikan hanya berisi view yang disebut di tahap-tahap ini, partial baru, `styles.css`, `site.js`, aset `public/assets/img`, dan `docs/`. Pastikan **tidak ada** perubahan pada controller, Router, Permissions, migrations, config, tests.
3. `grep` di `app/Views`: tidak ada `href="#"`, tidak ada `<script>` inline, tidak ada URL `http(s)://` pada `src`/`href` aset, tidak ada atribut `style=` baru (selain wrapper honeypot yang sudah ada).
4. Daftar setiap `<?= ... ?>` pada file yang baru/diubah yang **bukan** `e(...)`, `(int)`, atau `(float)`, beserta alasan aman.
5. Hitung ulang kontras (terang dan gelap) untuk: teks putih pada hero, tone sebagai batas/isian grafik terhadap `--white` (≥3:1), teks `.st-list a`, dan `.pillar-no`.
6. Checklist cek manual untuk saya: ADMIN, GUEST, SISWA, GURU × terang/gelap × 375/768/1280 px; keadaan kosong (semua 0) dan keadaan berisi (setelah saya membuat data uji fiktif); tanpa JS; keyboard (Tab ke tiap kartu status).

Jangan merge dan jangan push. Lalu berhenti.