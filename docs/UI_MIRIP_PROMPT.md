# PROMPT AGENT: Samakan UI HUMA BERKAH dengan Pratinjau (versi "mirip persis")

## Cara memakai (untuk pemilik proyek)

1. Simpan berkas ini sebagai `docs/UI_MIRIP_PROMPT.md`.
2. Salin `Pratinjau_UI_HUMA_MANTAP.html` ke `docs/referensi/Pratinjau_UI_HUMA_MANTAP.html`. Berkas ini adalah **sumber kebenaran visual**. Perhatian: baris ke-30 kira-kira berisi logo base64 yang sangat panjang, jadi agent harus membacanya terpotong (lihat bagian 1).
3. Nyalakan MySQL Laragon (Start All).
4. Tempel pesan ini ke chat agent, **satu tahap per pesan**:

> Baca `docs/UI_PROMPT.md` bagian 2-4 (aturan umum), `docs/UI_REVISI_PROMPT.md` bagian 2 (aturan revisi), lalu `docs/UI_MIRIP_PROMPT.md` sampai habis. Kerjakan **TAHAP 0 saja** dari UI_MIRIP_PROMPT, lalu berhenti dan laporkan dengan format laporan.

Setelah laporan Anda cek di browser (buka pratinjau dan `http://localhost:8000` berdampingan), kirim "Lanjut TAHAP 1", dan seterusnya.

---

## 1. Tujuan dan cara membaca referensi

Tujuan: seluruh tampilan aplikasi (beranda **dan** halaman dalam) memiliki tema, warna, tipografi, bentuk komponen, dan perilaku yang sama dengan `docs/referensi/Pratinjau_UI_HUMA_MANTAP.html`. Ini **perubahan tampilan saja**, tidak ada perubahan logika.

Cara membaca referensi tanpa membanjiri konteks:

```powershell
# tampilkan seluruh berkas, baris yang lebih dari 400 karakter dipotong
Get-Content docs\referensi\Pratinjau_UI_HUMA_MANTAP.html | ForEach-Object { if ($_.Length -gt 400) { $_.Substring(0,400) + ' ...[dipotong]' } else { $_ } }
```

Semua CSS penting pratinjau ada di blok `<style>` (baris 8-96 kira-kira) dan semua JS di blok `<script>` terakhir. **Salin nilainya persis** (warna, ukuran, jarak, radius, transisi). Jangan "memperbaiki" atau menafsirkan ulang desainnya.

### Aturan nama kelas

Pratinjau memakai nama kelas pendek (`.top`, `.brand`, `.hero`, `.quick`, `.tile`, `.box`, `.card`). Proyek sudah punya nama kelas sendiri (`.topbar`, `.site-header`, `.home-carousel`, `.home-quick`, `.quick-service`, dst.) yang dipakai view dan mungkin tes. **Pertahankan nama kelas proyek; pakai nilai visual dari pratinjau.** Tabel padanan:

| Pratinjau | Proyek | Catatan |
|---|---|---|
| `.dayak` | `.dayak` | sama persis |
| `.top` / `.top .wrap` | `.topbar` / `.topbar-inner` | |
| `.ticker`, `#tick` | `.ticker`, `#tick` | sudah ada, pertahankan JS |
| `header.brand` | `.site-header` / `.site-header-inner` | |
| `nav.main` / `.menu` / `.sub` | `.site-nav` / `.menu` / `.sub` | |
| `.burger` | `.nav-toggle` | |
| `.hero` (grid 2fr 1fr) | **baru:** `.hero-grid` | pembungkus carousel + Layanan Cepat |
| `.slider`, `.slide`, `.dots` | `.home-carousel`, `.carousel-slide`, `.carousel-controls` | |
| `.quick`, `.tiles`, `.tile` | `.home-quick`, `.quick-service-grid`, `.quick-service` | |
| `.title > span` | **baru:** `.section-title` | judul seksi berlabel hijau |
| `.cols` | **baru:** `.home-cols` | |
| `.box` | **baru:** `.box` (juga dipakai `.page-panel`) | |
| `.card`, `.cards` | **baru:** `.card-lite`, `.card-grid` | hindari bentrok dengan `.card` yang mungkin sudah ada |
| `.steps` | `.home-journey ol` | |
| `.empty` | `.stats-panel` | |
| `footer`, `.grid`, `.bar` | `.site-footer`, `.footer-grid`, `.footer-bottom` | |
| `.a11y`, `.fab` | **baru:** `.a11y-tools`, `.fab-kahayan` | |

---

## 2. Aturan keras

Semua aturan di `docs/UI_PROMPT.md` bagian 2 dan 3 serta `docs/UI_REVISI_PROMPT.md` bagian 2 **tetap berlaku** (batas file, `e()`, `_csrf`, honeypot `website`, nama field, larangan membaca `config.php`, tes dilindungi, staging satu per satu, tidak merge/push). Tambahan khusus:

1. **Controller, Router, model, migrasi, config, tes: dilarang diubah.** Boleh diubah: `app/Views/**`, `public/assets/**`.
2. Teks yang dipakai tes harus tetap persis. Yang sudah diketahui: `BELUM ADA DATA`, `DATA TERLALU SEDIKIT`, kata `HUMA BERKAH` di keluaran `/`, dan pola `<section class="stats-panel"` yang di dalamnya memuat `BELUM ADA DATA` (tes memakai regex `/<section class="stats-panel".*?<\/section>/s`, jadi atribut `class` harus tepat `stats-panel` dan di dalam elemen itu tidak boleh ada `</section>` lain sebelum `BELUM ADA DATA`). Sebelum mengubah markup bagian mana pun, `grep` di `tests/` dulu.
3. Satu `<h1>` per halaman. Beranda sudah punya `<h1>HUMA BERKAH</h1>` di blok motto; pertahankan.
4. Pratinjau memakai beberapa hal yang **melanggar aturan proyek**. Terapkan padanannya, jangan salin mentah:

| Di pratinjau | Yang dilakukan di proyek |
|---|---|
| Emoji (📝 🔎 📣 📬 📊 🏛️) | Ikon SVG inline `aria-hidden="true"` (set ikon ada di Lampiran B) |
| `href="#"` | Dilarang. Pakai rute yang benar-benar ada, atau **jangan buat item itu** dan catat di laporan |
| Menu Profil, Visi dan Misi, Pokja 6, Pengumuman, "Layanan contoh 1-5" | Rute/data tidak ada dan controller tidak boleh diubah. **Jangan dibuat.** Catat di laporan |
| Menu Zona Integritas dan Dashboard MADANI di tingkat atas untuk semua orang | Tampilkan hanya sesuai kondisi peran/izin yang sudah ada di `layouts/main.php` (salin kondisinya) |
| Item "Masuk" selalu tampil | Tetap hanya untuk tamu (`$currentUser === null`) |
| `<script>` inline | Semua JS di `public/assets/js/site.js` |
| Font "Segoe UI" | Tumpukan font sistem saja (tanpa font eksternal), lihat Tahap 1 |

5. Semua halaman tetap utuh **tanpa JavaScript**. Dropdown tetap bekerja lewat `:hover` dan `:focus-within`. Tombol A+/A− disembunyikan tanpa JS (Tahap 4).
6. Kontras: teks ≥ 4.5:1, elemen non-teks ≥ 3:1, di mode terang **dan** gelap. Hitung, jangan menebak. Catatan dari saya untuk Anda: pratinjau sendiri **gagal** di tiga tempat (lihat Tahap 1), jadi nilai di sana sudah saya sesuaikan sedikit.
7. Tidak boleh ada tautan atau fitur yang menjanjikan sesuatu yang belum ada.

## 3. Aturan kerja dan format laporan

Sama seperti `UI_PROMPT.md` bagian 3 dan 4 (cabang `ui`, satu tahap per sesi, commit kecil, jangan klaim berhasil sebelum dijalankan, berhenti bila tes gagal atau `git diff --check` gagal dua kali). Tambahkan pada **setiap** laporan:

1. Tabel "Pratinjau vs hasil": untuk setiap bagian yang dikerjakan, tulis nilai di pratinjau dan nilai di proyek (warna, ukuran, jarak) dan tandai sama / sengaja berbeda (+ alasan).
2. Daftar literal tes yang dilindungi dan buktinya utuh.
3. Item pratinjau yang **dilewati** karena rute/data tidak ada.
4. Hasil tes: LOLOS, GAGAL, dibanding baseline Tahap 0.

---

## TAHAP 0: Audit dan baseline (hanya membaca)

Jangan ubah file, jangan commit. Laporkan:

1. `git status --short --branch`; pindah/buat cabang `ui` bila belum.
2. Jalankan tes satu kali dan catat baseline (LOLOS/GAGAL).
3. Baca pratinjau (cara di bagian 1) dan `public/assets/css/styles.css`. Laporkan:
   - Variabel CSS yang ada vs variabel pratinjau (tabel selisih nilai: `--green`, `--green-d`, `--gold`, `--navy`, `--cream`, `--bg`, `--card`, `--text`, `--muted`, `--line`).
   - Semua tempat `var(--cream)`, `var(--navy)`, `var(--white)` dipakai sebagai **latar** vs sebagai **teks** (penting: di proyek `--cream` adalah latar halaman, di pratinjau `--cream` adalah latar ubin/kartu dan `--bg` latar halaman).
   - Apakah ada header CSP (periksa `public/index.php` dan `Router.php`), dan apakah `font-family` diwarisi tombol/input.
   - Apakah ada `@media (prefers-color-scheme: dark)` ganda.
4. Daftar rute GET yang ada (path saja) dari `Router.php`, dikelompokkan publik / SISWA-GURU / ADMIN. Tandai mana yang menjawab item pratinjau: Layanan, Lacak, KAHAYAN AMANAH, Survei, Zona Integritas, Dashboard MADANI.
5. Apakah view beranda menerima data layanan atau pengumuman dari controller (periksa variabel yang tersedia di `home.php`). Bila tidak, kartu katalog dan Pengumuman **tidak dibuat**.
6. Inventaris selisih struktural antara pratinjau dan `layouts/main.php`: lebar pembungkus (`.page-shell` 1200px + padding 24px vs pratinjau lebar penuh dengan `.wrap` 1160px), latar topbar (navy vs hijau tua), latar footer (navy vs hijau tua), font judul (Georgia vs sans).
7. Rencana pemetaan tahap 1-6 dengan daftar berkas yang akan disentuh.

---

## TAHAP 1: Token, tipografi, dasar (hanya `styles.css`)

BATAS FILE: `public/assets/css/styles.css`. Tidak ada view yang diubah. Izin tes: sekali di akhir.

### 1a. Token warna

Ganti **nilai** token yang sudah ada (jangan menghapus nama; tambahkan alias bila perlu). Nilai terang dari pratinjau:

```css
:root{
  --green:#0b6b3a; --green-d:#084d2a; --gold:#d4a017; --navy:#12284c;
  --cream:#fbf6e8; --bg:#f4f1e8; --card:#ffffff;
  --text:#1d2433; --muted:#5d6678; --line:#e3dcc8;
  --gold-hi:#e0b23a;     /* BARU: emas untuk TEKS di atas hijau tua (kontras 5.03:1; #d4a017 hanya 4.20:1) */
  --green-ink:#0b6b3a;   /* BARU: hijau untuk TEKS di atas kartu/krem (6.12:1) */
}
```

Aturan peta lama → baru yang wajib dicek lewat `grep` penggunaan:

- `body{background:var(--bg)}` (bukan `--cream`). Di pratinjau `--cream` hanya latar ubin, kartu, hover submenu, dan kotak kosong.
- `--white` tetap putih untuk teks di atas hijau/emas; jangan dijadikan alias `--card` di mode gelap tanpa memeriksa pemakaiannya.
- `--border` dan `--line` harus sama nilainya (`#e3dcc8`); `--grey` dan `--muted` sama (`#5d6678`).

Mode gelap (satu blok `@media (prefers-color-scheme: dark)` saja, gabungkan dengan yang ada). Nilai pratinjau, dengan dua koreksi kontras yang sudah saya hitung:

```css
@media (prefers-color-scheme:dark){
  :root{
    --green:#19794b;      /* pratinjau: #1d8a55 -> teks putih di nav hanya 4.36:1; ini 5.42:1 */
    --green-d:#0f5a37;
    --gold-hi:#e4bd60;
    --green-ink:#4cc38a;  /* hijau untuk TEKS di permukaan gelap (7.37:1); pratinjau memakai #1d8a55 = 3.74:1, gagal */
    --cream:#1d2330; --bg:#11151d; --card:#1a202c;
    --text:#e8ecf4; --muted:#a3adc2; --line:#2c3547;
  }
}
```

Pemakaian: **teks** berwarna hijau (judul kartu `h4`, tautan hijau, hover submenu) memakai `var(--green-ink)`, bukan `var(--green)`. `--green` hanya untuk latar (nav, label judul, nomor langkah). Tulis hasil hitungan kontras di laporan untuk pasangan: putih/nav, `--gold-hi`/`--green-d` (topbar dan footer), `--green-ink`/`--card`, `--green-ink`/`--cream`, `--muted`/`--card`, `--muted`/`--cream`, di terang dan gelap.

Aturan `--navy` di mode gelap: pratinjau mengubah `--navy` menjadi `#dbe6ff` karena dipakai sebagai latar tombol A+/A−. Di proyek `--navy` mungkin dipakai sebagai latar di tempat lain. **Jangan** mengubah `--navy` di mode gelap sebelum hasil grep Tahap 0 menunjukkan aman; kalau tidak aman, buat token baru `--a11y-bg` untuk tombol A+/A−.

### 1b. Tipografi dan dasar

Tanpa font eksternal. Pratinjau memakai sans-serif untuk semuanya, termasuk judul:

```css
html{font-size:100%;scroll-padding-top:env(safe-area-inset-top,0px)}  /* persen, supaya A+/A− bisa bekerja */
body{margin:0;background:var(--bg);color:var(--text);
     font-family:"Segoe UI",system-ui,-apple-system,Roboto,Arial,sans-serif;line-height:1.55}
h1,h2,h3,h4{font-family:inherit}          /* hapus Georgia dari semua judul */
a{color:inherit;text-decoration:none}
button,input,select,textarea{font:inherit;color:inherit}
```

Hati-hati dengan `a{text-decoration:none}` global: pastikan tautan di dalam teks (paragraf, tabel, `.page-panel`) tetap terlihat sebagai tautan (garis bawah atau warna `--green-ink`). Aturan `.menu a`, `.tile`, `.card-lite` memang tanpa garis bawah.

### 1c. Komponen dasar yang dipakai semua halaman

```css
.dayak{height:8px;background:
  linear-gradient(135deg,var(--gold) 25%,transparent 25%) -8px 0/16px 16px,
  linear-gradient(225deg,var(--gold) 25%,transparent 25%) -8px 0/16px 16px,var(--green-d)}

.section-title{margin:0 0 12px;font-size:1rem;border-bottom:2px solid var(--green)}
.section-title span{display:inline-block;background:var(--green);color:#fff;padding:5px 14px;font-size:.92rem}

.box{background:var(--card);border:1px solid var(--line);border-radius:10px;padding:16px;margin-bottom:16px}

.btn{display:inline-block;background:var(--gold);color:#1b1b1b;padding:9px 18px;border-radius:6px;
     font-weight:700;border:0;cursor:pointer;text-decoration:none}
.btn:hover{filter:brightness(1.08)}
.btn:focus-visible,a:focus-visible,button:focus-visible{outline:3px solid var(--gold);outline-offset:2px}
```

Periksa `.btn.primary` dan `.btn.secondary` yang sudah ada: `.primary` = gaya emas di atas; `.secondary` = latar transparan, `border:2px solid var(--green)`, teks `var(--green-ink)`. Jangan mengubah gaya `.btn` bahaya/hapus yang ada.

Hentikan di sini, jalankan tes sekali, laporkan.

---

## TAHAP 2: Kerangka halaman (layout + CSS) — topbar, header, nav, footer

BATAS FILE: `app/Views/layouts/main.php`, `app/Views/partials/{topbar,header,nav,footer}.php`, `styles.css`. Pertahankan **semua** kondisi peran di `main.php` dan `nav.php` apa adanya.

### 2a. Pembungkus lebar penuh

Pratinjau: bilah (topbar, header, nav, footer) lebar penuh dengan isi di tengah max 1160px, bukan satu kotak 1200px berpadding. Ubah `.page-shell` agar tidak membatasi, dan batasi di dalam:

```css
.page-shell{max-width:none;margin:0;padding:0}
.topbar-inner,.site-header-inner,.site-nav-inner,.footer-inner,.footer-grid-wrap,.content{
  max-width:1160px;margin:0 auto;padding-left:16px;padding-right:16px}
.content{padding-top:18px;padding-bottom:28px}
```

Hapus margin negatif lama (`.topbar{margin:-24px -24px 0}`, `.site-footer{margin:36px -24px -24px}`). Setelah ini, **periksa semua halaman dalam** (admin, formulir, tabel) tidak patah.

### 2b. Topbar

```css
.topbar{background:var(--green-d);color:#fff;font-size:.82rem;margin:0;padding:6px 0}
.topbar-inner{display:flex;gap:16px;align-items:center;justify-content:space-between;padding-top:0;padding-bottom:0}
.ticker-label{color:var(--gold-hi);margin-right:8px;text-transform:none}
.ticker-text{transition:opacity .4s}
```

Markup `topbar.php`: sisakan **tanggal di kiri** dan **ticker "Terbaru:" di kanan**. Blok `.topbar-identity` (PROTOTIPE + nama aplikasi) dihapus dari tampilan (cek `grep` tes untuk `PROTOTIPE`; bila ada tes yang membacanya, sembunyikan dengan `.sr-only`, bukan hapus). Tanggal: biarkan `<time ...><?= e(date('d-m-Y')) ?></time>` sebagai cadangan tanpa JS, dan ditimpa JS menjadi "Selasa, 13 Oktober 2026" (Tahap 4).

### 2c. Header

```css
.site-header{background:var(--card);border-bottom:1px solid var(--line);position:relative;overflow:hidden;padding:0}
.site-header-inner{display:flex;align-items:center;justify-content:space-between;padding-top:12px;padding-bottom:12px}
.brand-logo{height:64px;width:auto;max-width:100%}      /* gantikan width:min(500px,62vw) */
.tingang{width:150px;height:auto;opacity:.9;flex:none}
.brand-title{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap} /* tetap ada di HTML untuk nama aksesibel dan tes */
```

Di mobile (`max-width:860px`): `.tingang{width:90px}`.

### 2d. Navigasi

Nilai visual dari pratinjau, nama kelas proyek:

```css
.site-nav{background:var(--green);position:sticky;top:env(safe-area-inset-top,0px);z-index:50}
.menu a{display:block;padding:14px 16px;color:#fff;font-weight:600;font-size:.93rem}
.menu>li:hover>a,.menu>li:focus-within>a,.menu>li.open>a,.menu>li>a[aria-current="page"]{
  background:var(--green-d);box-shadow:inset 0 -3px var(--gold)}
.has-sub>a::after{content:" \25BE";font-size:.7rem}
.sub{display:none;position:absolute;left:0;top:100%;min-width:230px;background:var(--card);
     border:1px solid var(--line);border-top:3px solid var(--gold);box-shadow:0 8px 20px rgba(0,0,0,.18)}
.sub a{color:var(--text);font-weight:500;padding:10px 16px;border-bottom:1px solid var(--line)}
.sub a:hover,.sub a:focus-visible{background:var(--cream);color:var(--green-ink)}
.sub .sub{left:100%;top:-3px}                 /* flyout tingkat 3 */
.sub .has-sub>a::after{content:" \25B8"}
.sub li{position:relative}
```

Pertahankan aturan yang sudah ada untuk `.has-sub:hover>.sub`, `:focus-within`, `.open`, dan `[aria-expanded="false"]`.

Penanda halaman aktif (opsional tapi sesuai pratinjau `li.active`): di `nav.php`, tambahkan `aria-current="page"` pada tautan yang path-nya sama dengan `parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH)`. Bandingkan sebagai string, bungkus keluaran dengan `e()`, dan jangan tambah logika peran.

Menu Admin: pertahankan pengelompokan yang ada (`.menu-group`), hanya samakan warnanya: judul grup `color:var(--green-ink)`, latar mengikuti `.sub`. **Jangan** mengubah isi atau urutan hak akses.

Mobile (≤860px), nilai pratinjau:

```css
@media (max-width:860px){
  .nav-toggle{display:block;margin-left:auto;background:none;border:0;color:#fff;font-size:1rem;padding:10px;cursor:pointer}
  .nav-toggle::before{content:"\2630  "}
  .site-nav-inner{display:flex;flex-wrap:wrap}
  .menu{display:none;width:100%;flex-direction:column}
  .site-nav.open .menu{display:flex}
  .sub,.sub .sub{position:static;box-shadow:none;border:0;background:var(--green-d)}
  .sub a{color:#fff;border-bottom:1px solid #ffffff22;padding-left:30px}
  .menu li:hover>.sub{display:none}
  .menu li.open>.sub{display:block}
}
```

Periksa JS menu di `site.js` tetap cocok (kelas `open` pada `.site-nav` dan `li`).

### 2e. Footer

```css
.site-footer{background:var(--green-d);color:#e9efe9;margin:10px 0 0;padding:0}
.footer-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:20px;padding:26px 16px}
.footer-column h2{margin:0 0 8px;color:var(--gold-hi);font-size:.95rem;font-weight:700}
.footer-column p,.footer-column a{font-size:.86rem;display:block;margin:3px 0;opacity:.92;text-decoration:none}
.footer-column a:hover{color:var(--gold-hi)}
.privacy-notice{margin:0;padding:12px 16px;font-size:.82rem;border-top:1px solid rgba(255,255,255,.2)}
.footer-bottom{background:#052f1a;color:#e9efe9;text-align:center;padding:10px;font-size:.8rem}
@media (max-width:860px){.footer-grid{grid-template-columns:1fr 1fr}}
```

Di `footer.php`, pindahkan satu `<div class="dayak">` ke **paling atas** footer (di pratinjau pita Dayak ada di atas isi footer, bukan di antara isi dan bar bawah). **Peringatan privasi tetap tampil** (tes dan aturan proyek), hanya gayanya yang disesuaikan.

Jalankan tes sekali, laporkan.

---

## TAHAP 3: Beranda

BATAS FILE: `app/Views/pages/home.php`, `styles.css`, (ikon SVG inline di view). Pertahankan: semua kondisi peran, `<h1>HUMA BERKAH</h1>`, `<section class="stats-panel"` + `BELUM ADA DATA`, atribut ARIA carousel, atribut `data-carousel-slide`, dan `site.js` carousel.

### 3a. Urutan beranda (target)

Urutan di pratinjau adalah: **hero (carousel 2/3 + Layanan Cepat 1/3)** → **dua kolom (katalog + alur)**. Konten motto dan tiga pilar sudah disetujui sebelumnya (UI_REVISI_PROMPT), jadi **tidak dibuang**, tetapi dipindah ke bawah supaya bagian atas persis seperti pratinjau. Keputusan ini bisa saya balik bila Anda mau.

```
<div class="home-page">
  <div class="hero-grid">
    <section class="home-carousel" ...>          ← carousel yang sudah ada
    <section class="home-quick" ...>             ← Layanan Cepat yang sudah ada
  </div>
  <div class="home-cols">
    <div>
      <section class="box home-catalogue">       ← katalog (CTA, lihat 3d)
      <section class="box motto-hero">           ← motto + h1 + tiga pilar (lihat 3e)
    </div>
    <div>
      <section class="box home-journey">         ← Alur Pengajuan
      <div class="box">
        <h2 class="section-title"><span>Ringkasan MADANI</span></h2>
        <section class="stats-panel" aria-label="Ketersediaan informasi layanan"><p>BELUM ADA DATA</p></section>
      </div>
    </div>
  </div>
</div>
```

Catatan regex tes: `<section class="stats-panel"` harus tetap persis dan tidak boleh ada `</section>` di antara pembukanya dan `BELUM ADA DATA`. Judul "Ringkasan MADANI" diletakkan **di luar** `stats-panel`. Teks "Ringkasan MADANI" hanya boleh dipakai bila tidak bentrok dengan tes (`grep`); bila ragu, pakai "Ketersediaan Informasi".

### 3b. Hero + carousel (nilai pratinjau, kelas proyek)

```css
.hero-grid{display:grid;grid-template-columns:2fr 1fr;gap:16px;margin:0 0 18px}
.home-carousel{position:relative;border-radius:10px;overflow:hidden;min-height:300px;background:var(--green-d);color:#fff}
.carousel-slides{position:absolute;inset:0}
.carousel-slide{position:absolute;inset:0;padding:32px;display:flex;flex-direction:column;justify-content:flex-end;
  opacity:0;visibility:hidden;transition:opacity .6s,visibility .6s;
  background:linear-gradient(160deg,var(--green) 0%,var(--navy) 130%)}
.carousel-slide.is-active{opacity:1;visibility:visible}
.carousel-slide:nth-child(2){background:linear-gradient(160deg,#7a5a05,var(--green-d))}
.carousel-slide:nth-child(3){background:linear-gradient(160deg,#173a73,var(--green-d))}
.carousel-copy h2{margin:0 0 8px;font-size:1.6rem;max-width:80%;color:#fff}
.carousel-copy p{margin:0 0 14px;max-width:70%;opacity:.92}
.carousel-slide .kicker{position:absolute;top:18px;left:32px;margin:0;background:var(--gold);color:#222;
  padding:2px 10px;border-radius:3px;font-size:.78rem;font-weight:700;letter-spacing:0;text-transform:uppercase}
.carousel-tingang{position:absolute;right:16px;top:16px;width:170px;height:auto;opacity:.28;
  filter:brightness(0) invert(1)}                       /* SVG berwarna jadi putih seperti ikon di pratinjau */
.carousel-controls{position:absolute;right:16px;bottom:14px;display:flex;gap:6px}
.carousel-controls button{position:relative;width:11px;height:11px;padding:0;border-radius:50%;border:0;
  background:#ffffff80;cursor:pointer}
.carousel-controls button::after{content:"";position:absolute;inset:-7px}   /* area sentuh ≥ 24px */
.carousel-controls button[aria-pressed="true"]{background:var(--gold)}
@media (max-width:860px){.hero-grid{grid-template-columns:1fr}.carousel-copy h2,.carousel-copy p{max-width:100%}}
```

Tanpa JS, slide pertama (`is-active` di markup) tetap tampil. Pastikan teks putih di atas gradien lolos 4.5:1 (hitung pada titik paling terang gradien).

### 3c. Layanan Cepat (ubin dengan ikon SVG)

Judul memakai `.section-title`. Ubin 2 kolom:

```css
.home-quick{background:var(--card);border:1px solid var(--line);border-radius:10px;padding:14px}
.quick-service-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.quick-service{background:var(--cream);border:1px solid var(--line);border-radius:8px;padding:12px 8px;
  text-align:center;font-size:.84rem;font-weight:600;transition:transform .15s,box-shadow .15s}
.quick-service:hover,.quick-service:focus-visible{transform:translateY(-3px);box-shadow:0 6px 14px rgba(0,0,0,.14);border-color:var(--gold)}
.quick-service svg{display:block;width:28px;height:28px;margin:0 auto 4px;color:var(--green-ink)}
@media (prefers-reduced-motion:reduce){.quick-service,.card-lite{transition:none}.quick-service:hover{transform:none}}
```

Markup tiap ubin (pertahankan kondisi peran yang sudah ada, ganti hanya isinya):

```php
<a class="quick-service" href="/lacak">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
    Lacak tiket
</a>
```

Padanan ikon (path di Lampiran B): Katalog layanan = daftar, Lacak tiket = kaca pembesar, Sampaikan pengaduan = megafon, Lacak aduan = amplop, Survei = grafik batang, Portal saya = orang, Masuk = masuk. Teks tautan **tidak diubah**.

### 3d. Kolom bawah

```css
.home-cols{display:grid;grid-template-columns:2fr 1fr;gap:16px;margin-bottom:28px}
@media (max-width:860px){.home-cols{grid-template-columns:1fr}}
.home-journey ol{counter-reset:s;list-style:none;margin:0;padding:0}
.home-journey li{counter-increment:s;padding:8px 0 8px 38px;position:relative}
.home-journey li::before{content:counter(s);position:absolute;left:0;top:6px;width:26px;height:26px;border-radius:50%;
  background:var(--green);color:#fff;text-align:center;line-height:26px;font-weight:700;font-size:.85rem}
.stats-panel{background:var(--cream);border:1px dashed var(--gold);border-radius:8px;padding:12px;
  font-size:.88rem;color:var(--muted);box-shadow:none;margin-top:0}
.stats-panel p{margin:0}
```

Judul "Katalog layanan" dan "Alur Pengajuan" dibungkus `.section-title > span`; elemen heading aslinya (`h2`) dipertahankan, hanya diberi kelas dan `span`. **Kartu katalog contoh dan daftar Pengumuman di pratinjau tidak dibuat** kecuali Tahap 0 menemukan data yang sudah dikirim controller ke view. Blok `.home-catalogue` tetap berupa kotak berisi satu paragraf dan satu tombol `.btn.secondary` "Lihat katalog layanan".

### 3e. Motto dan tiga pilar (dipindah ke bawah, gaya disamakan)

Tampilkan di dalam `.box`: `<h1>` berukuran 1.4rem (tetap satu-satunya h1), subjudul `.muted`, tiga motto sebagai baris ikon+teks (SVG yang sudah ada), tiga pilar sebagai kartu bergaya pratinjau:

```css
.motto-hero h1{margin:0 0 4px;font-size:1.4rem;color:var(--green-ink)}
.motto-hero .motto-bird{display:none}                 /* burung sudah ada di header dan carousel */
.pillars{display:grid;grid-template-columns:repeat(auto-fill,minmax(190px,1fr));gap:12px;margin-top:14px}
.pillar{border:1px solid var(--line);border-top:4px solid var(--t,var(--green));border-radius:8px;padding:14px;background:var(--cream);
  transition:transform .15s,box-shadow .15s}
.pillar:hover{transform:translateY(-3px);box-shadow:0 6px 14px rgba(0,0,0,.14)}
.pillar h3{margin:0 0 4px;color:var(--green-ink);font-size:.95rem}
.pillar p{margin:0;font-size:.82rem;color:var(--muted)}
```

Jalankan tes sekali, laporkan (termasuk tangkapan perbandingan yang diminta di Tahap 6 untuk beranda, tanpa gambar bila tidak bisa: cukup tabel nilai).

---

## TAHAP 4: Tombol A+/A−, tombol melayang, dan penyempurna JS

BATAS FILE: `app/Views/layouts/main.php`, `public/assets/js/site.js`, `styles.css`.

### 4a. Markup (di `main.php`, sebelum `</body>`, di luar `.page-shell`)

```php
<div class="a11y-tools" role="group" aria-label="Ukuran teks">
    <button type="button" id="aplus" aria-label="Perbesar teks">A+</button>
    <button type="button" id="aminus" aria-label="Perkecil teks">A&minus;</button>
</div>
<?php if ($currentRole !== 'ADMIN'): ?>
    <a class="fab-kahayan" href="/kahayan">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 11v2a1 1 0 0 0 1 1h3l7 4V6L7 10H4a1 1 0 0 0-1 1z"/><path d="M18 9a4 4 0 0 1 0 6"/></svg>
        KAHAYAN AMANAH
    </a>
<?php else: ?>
    <a class="fab-kahayan" href="/lacak-aduan">Lacak aduan</a>
<?php endif; ?>
```

Kondisi `$currentRole !== 'ADMIN'` sudah ada di proyek (dipakai nav dan beranda). Verifikasi rute `/kahayan` dan `/lacak-aduan` ada.

### 4b. CSS

```css
.a11y-tools{display:none;position:fixed;left:16px;bottom:calc(16px + env(safe-area-inset-bottom,0px));z-index:60;gap:6px}
.js .a11y-tools{display:flex}                          /* tanpa JS tombol tidak muncul */
.a11y-tools button{width:42px;height:42px;border-radius:50%;border:0;background:var(--a11y-bg,var(--navy));
  color:var(--a11y-fg,#fff);font-weight:700;cursor:pointer;box-shadow:0 3px 10px rgba(0,0,0,.3)}
.fab-kahayan{position:fixed;right:16px;bottom:calc(16px + env(safe-area-inset-bottom,0px));z-index:60;
  display:inline-flex;align-items:center;gap:8px;background:var(--gold);color:#1b1b1b;padding:12px 16px;
  border-radius:30px;font-weight:700;box-shadow:0 3px 10px rgba(0,0,0,.3);text-decoration:none}
.fab-kahayan svg{width:20px;height:20px}
@media print{.a11y-tools,.fab-kahayan{display:none}}
```

Mode gelap: `--a11y-bg:#dbe6ff; --a11y-fg:#12284c;` (ini yang di pratinjau dihasilkan dari `--navy` yang dibalik). Pastikan `.fab-kahayan` tidak menutupi tombol kirim formulir di mobile: tambahkan `padding-bottom:72px` pada `.content` di ≤860px.

### 4c. JS (tambahkan di `site.js`, jangan buat berkas baru, jangan inline)

```js
// Ukuran teks A+/A- (pratinjau: 85%-130%, langkah 10)
(function () {
  var KEY = 'hm-fs', fs = 100;
  try { var s = parseInt(window.localStorage.getItem(KEY), 10); if (s >= 85 && s <= 130) { fs = s; } } catch (e) {}
  function setFs(v) {
    fs = Math.min(130, Math.max(85, v));
    document.documentElement.style.fontSize = fs + '%';
    try { window.localStorage.setItem(KEY, String(fs)); } catch (e) {}
  }
  setFs(fs);
  var plus = document.getElementById('aplus'), minus = document.getElementById('aminus');
  if (plus) { plus.addEventListener('click', function () { setFs(fs + 10); }); }
  if (minus) { minus.addEventListener('click', function () { setFs(fs - 10); }); }
})();

// Tanggal berbahasa Indonesia (cadangan tanpa JS tetap format d-m-Y dari server)
(function () {
  var el = document.querySelector('.topbar-date');
  if (!el) { return; }
  try {
    el.textContent = new Date().toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
  } catch (e) {}
})();
```

Ticker dan carousel yang sudah ada **tidak diubah** (hormati `prefers-reduced-motion`). Pastikan `html{font-size:100%}` dari Tahap 1b sudah menggantikan `16px`; kalau tidak, A+/A− tidak berpengaruh pada ukuran `rem`.

---

## TAHAP 5: Halaman dalam (tema menyeluruh)

BATAS FILE: `styles.css` terutama; view hanya bila tak ada cara lain lewat CSS. Target: halaman layanan, detail layanan, formulir pengajuan/aduan/survei, lacak, login, portal, dan semua halaman admin **terlihat satu keluarga** dengan beranda.

Prinsip: ubah lewat token dan kelas yang sudah ada (`.page-panel`, `.info-card`, `.auth-card`, `.error-box`, tabel, formulir), bukan lewat markup baru.

```css
/* Panel dan kartu: ikut gaya .box pratinjau */
.page-panel,.info-card,.auth-card,.error-box,.dash-panel{
  background:var(--card);border:1px solid var(--line);border-radius:10px;box-shadow:none}
.page-panel{padding:16px}
.info-card:hover{transform:translateY(-3px);box-shadow:0 6px 14px rgba(0,0,0,.14);transition:transform .15s,box-shadow .15s}

/* Judul halaman dan seksi */
.page-panel>h1:first-child,.page-panel>h2:first-child{margin:0 0 12px;font-size:1.25rem;color:var(--green-ink);
  border-bottom:2px solid var(--green);padding-bottom:6px}

/* Formulir */
input,select,textarea{background:var(--card);color:var(--text);border:1px solid var(--line);border-radius:6px;padding:9px 10px}
input:focus-visible,select:focus-visible,textarea:focus-visible{outline:3px solid var(--gold);outline-offset:1px;border-color:var(--green)}
label{font-weight:600}

/* Tabel */
table{border-collapse:collapse;width:100%}
th{background:var(--cream);color:var(--text);text-align:left}
th,td{border-bottom:1px solid var(--line);padding:9px 10px}
tbody tr:hover{background:var(--cream)}

/* Tautan konten */
.page-panel a:not(.btn){color:var(--green-ink);text-decoration:underline;text-underline-offset:2px}
```

Jangan mengubah tampilan honeypot `name="website"`. Jangan mengubah lebar kolom atau struktur tabel. Bila Tahap UI_REVISI sebelumnya sudah menambahkan komponen `.donut`, `.kpi-row`, `.dash-grid`, samakan token mereka (`--tone-*`) tanpa menghapus aturannya.

Daftar yang harus dicek satu per satu (laporkan status tiap baris): `/layanan`, detail layanan, formulir pengajuan, `/lacak`, formulir aduan, `/lacak-aduan`, `/survei`, `/login`, `/portal`, `/admin` dan setiap halaman admin yang ada, halaman error. Cek di 390px dan 1280px, mode terang dan gelap.

---

## TAHAP 6: Verifikasi terhadap pratinjau (hanya membaca + perbaikan kecil)

1. Buka `docs/referensi/Pratinjau_UI_HUMA_MANTAP.html` (klik dua kali) dan `http://localhost:8000/` berdampingan, lebar 1280px dan 390px, terang dan gelap.
2. Tempel snippet ini di konsol DevTools **pada kedua halaman**, lalu bandingkan keluarannya. Laporkan tabel selisihnya:

```js
(function () {
  var pairs = [
    ['topbar', '.top, .topbar'], ['header', 'header.brand, .site-header'], ['nav', 'nav.main, .site-nav'],
    ['link-nav', '.menu>li>a'], ['hero', '.hero, .hero-grid'], ['slider', '.slider, .home-carousel'],
    ['quick', '.quick, .home-quick'], ['tile', '.tile, .quick-service'], ['box', '.box'],
    ['footer', 'footer, .site-footer'], ['btn', '.btn']
  ];
  var out = pairs.map(function (p) {
    var el = document.querySelector(p[1]); if (!el) { return [p[0], 'TIDAK ADA']; }
    var c = getComputedStyle(el), r = el.getBoundingClientRect();
    return [p[0], Math.round(r.width) + 'x' + Math.round(r.height), c.backgroundColor, c.color, c.borderRadius, c.fontFamily.split(',')[0], c.fontSize];
  });
  console.table(out);
})();
```

3. Daftar periksa visual (jawab ya/tidak untuk tiap butir):
   - Pita Dayak ada di atas halaman dan di atas isi footer.
   - Bilah atas hijau tua: tanggal panjang bahasa Indonesia di kiri, "Terbaru:" emas di kanan, teks berganti dengan fade.
   - Header putih: logo setinggi 64px di kiri, burung tingang 150px di kanan.
   - Nav hijau menempel di atas saat scroll; hover memberi latar hijau tua + garis emas 3px di bawah; dropdown berbingkai emas 3px di atas.
   - Hero: carousel 2/3 kiri, Layanan Cepat 1/3 kanan; slide berganti tiap 6 detik dengan tombol titik; label emas kecil di pojok kiri atas slide; burung putih transparan di kanan atas slide pertama.
   - Ubin Layanan Cepat 2 kolom, terangkat 3px saat hover dengan batas emas.
   - Judul seksi berupa label hijau dengan garis bawah hijau.
   - Kartu dan kotak: radius 10px, garis `--line`, tanpa bayangan berat.
   - Footer hijau tua 4 kolom (2 kolom di HP), judul kolom emas, bar bawah `#052f1a`.
   - Tombol A+/A− bulat di kiri bawah, tombol KAHAYAN AMANAH bulat-lonjong emas di kanan bawah.
   - Mode gelap: semua butir di atas tetap terbaca, tidak ada latar putih tersisa.
   - Tanpa JS (matikan JS di DevTools): halaman utuh, dropdown masih terbuka lewat hover/fokus, tombol A+/A− tidak muncul.
4. Jalankan tes terakhir dan bandingkan dengan baseline Tahap 0. Laporkan juga: semua item pratinjau yang sengaja berbeda beserta alasannya.

---

## Lampiran A: ringkasan selisih utama yang harus hilang

| Area | Sebelum (prototipe) | Sesudah (pratinjau) |
|---|---|---|
| Warna utama | hijau `#376852`, navy `#182B3D` | hijau `#0b6b3a`, hijau tua `#084d2a` |
| Latar halaman | krem `#F7F3E8` | `#f4f1e8` (`--bg`), krem hanya untuk ubin/kartu |
| Font judul | Georgia | sans sistem, sama dengan isi |
| Topbar dan footer | navy | hijau tua |
| Lebar | satu kotak 1200px + padding 24px | bilah lebar penuh, isi 1160px |
| Hero | motto dulu, carousel penuh, Layanan Cepat terpisah | carousel 2/3 + Layanan Cepat 1/3 sejajar |
| Layanan Cepat | tautan teks | ubin 2 kolom + ikon SVG + efek hover |
| A+/A−, tombol melayang | tidak ada | ada |
| Tanggal | `13-10-2026` | "Selasa, 13 Oktober 2026" |

## Lampiran B: set ikon SVG (viewBox 24, `stroke="currentColor"`, `stroke-width="2"`, `fill="none"`, round)

| Ikon | Isi `<svg>` |
|---|---|
| Daftar (katalog) | `<path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/>` |
| Kaca pembesar (lacak) | `<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>` |
| Megafon (pengaduan) | `<path d="M3 11v2a1 1 0 0 0 1 1h3l7 4V6L7 10H4a1 1 0 0 0-1 1z"/><path d="M18 9a4 4 0 0 1 0 6"/>` |
| Amplop (lacak aduan) | `<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>` |
| Grafik batang (survei) | `<path d="M5 20V10M12 20V4M19 20v-7"/>` |
| Orang (portal) | `<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>` |
| Masuk (login) | `<path d="M15 4h3a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-3"/><path d="m10 17 5-5-5-5M15 12H3"/>` |