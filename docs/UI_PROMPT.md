# PROMPT AGENT: Redesain UI HUMA MANTAP (MAN Kota Palangka Raya)

## Cara memakai (untuk pemilik proyek)

1. Simpan berkas ini sebagai `docs/UI_PROMPT.md` di proyek.
2. Simpan gambar logo yang Anda lampirkan ke `public/assets/img/logo-man-kota-palangkaraya.png`. Sumber lain: https://mankotapalangkaraya.sch.id/src/logo/0e1b8a26d744b9a434da94e121a91e27.png
3. Nyalakan MySQL Laragon (Start All).
4. Tempel pesan ini ke chat agent, **satu tahap per pesan**:

> Baca `docs/UI_PROMPT.md` sampai habis. Kerjakan **TAHAP 0 saja**, lalu berhenti dan laporkan dengan format di bagian "Format laporan". Jangan lanjut ke tahap berikutnya sebelum saya minta.

Setelah laporan tahap selesai dan Anda cek di browser, kirim: "Lanjut TAHAP 1", dan seterusnya.

---

## 1. Peran dan tujuan

Anda mengerjakan **redesain tampilan (UI) saja** untuk aplikasi PHP 8.3 murni + MySQL (tanpa framework) bernama HUMA MANTAP, prototipe layanan publik MAN Kota Palangka Raya (Pokja 6).

Target tampilan, mengacu pada gaya situs MAN 2 Kudus:

- Bilah atas (tanggal + teks berjalan "Terbaru").
- Header berisi **logo MAN Kota Palangka Raya** dan ilustrasi **burung tingang** sebagai elemen dekoratif.
- Navigasi hijau yang menempel di atas halaman, dengan **menu dropdown bertingkat** (bukan tautan kosong).
- Beranda dengan slider dan kotak "Layanan Cepat".
- Judul seksi berlabel, kartu dengan efek hover, sidebar, footer empat kolom.
- Garis motif Dayak (segitiga emas) sebagai pemisah.
- Responsif (HP, tablet, desktop) dan mendukung mode gelap otomatis.

## 2. Aturan keras (jangan dilanggar)

**Boleh diubah:** `app/Views/layouts/*`, `app/Views/pages/*` (hanya markup/kelas CSS), `app/Views/partials/*` (baru), `public/assets/**`.

**Dilarang disentuh:** controller, model/repository, router (hanya boleh dibaca), `migrations/`, `config/`, `tests/` (kecuali Tahap 6, itu pun hanya atas persetujuan saya), `.gitignore`.

**Dilarang membaca atau menampilkan** isi `config/config.php`, sandi, atau kredensial apa pun. Jangan menjalankan perintah yang mengubah, menghapus, atau membuat database.

**Wajib dipertahankan persis:**

1. Semua keluaran dinamis tetap dibungkus `e()`. Jangan pernah echo variabel mentah.
2. Setiap formulir POST tetap memiliki `<input type="hidden" name="_csrf" ...>` dengan cara yang sama seperti sekarang.
3. Field honeypot `name="website"` tetap ada, tetap bernama itu, dan tetap tersembunyi dari pengguna (jangan ditampilkan lewat CSS baru).
4. Nama semua field formulir (`name="..."`) tidak boleh berubah. Controller membacanya berdasarkan nama.
5. `action="/lacak"` dan `action="/lacak-aduan"` tetap ada.
6. Teks yang diperiksa tes harus tetap muncul **persis**:
   `BELUM ADA DATA`, `DATA TERLALU SEDIKIT`, `Kode tiket:`,
   `Login sebagai SISWA atau GURU untuk mengajukan layanan.`,
   `Layanan ini ditangani melalui modul pengaduan.`, tautan `Kelola layanan`,
   dan peringatan privasi/data fiktif.
   Sebelum mengubah teks di halaman mana pun, jalankan `grep` (atau `Select-String`) di folder `tests/` untuk memastikan teks itu tidak diperiksa.
7. Tampilan menu per peran (Guest, SISWA, GURU, ADMIN) harus mengikuti **kondisi peran yang sudah ada** di `main.php`. Salin kondisinya apa adanya. Jangan membuat logika peran baru dan jangan memperluas hak akses.
8. **Tidak boleh ada tautan kosong** (`href="#"`). Setiap item menu harus menuju rute yang benar-benar ada. Cari rute dengan membaca file router. Jika sebuah rute tidak ada, **jangan buat item itu**, cukup catat di laporan.
9. Tautan mati `/laporan` di navigasi saat ini harus dihapus atau diarahkan ke rute yang ada (mis. `/admin/madani`).
10. Jangan menambah library pihak ketiga, CDN, font eksternal, atau gambar dari situs lain. Semua aset lokal di `public/assets/`.
11. Semua halaman harus tetap bisa dipakai **tanpa JavaScript** (dropdown bekerja lewat CSS `:hover` dan `:focus-within`). JavaScript hanya penyempurna.
12. Periksa dulu apakah aplikasi mengirim header Content-Security-Policy. Jika ya, jangan pakai JavaScript inline. Gunakan berkas `public/assets/js/site.js`.

## 3. Aturan kerja

- Kerjakan di cabang `ui`: `git checkout -b ui` (jika sudah ada, `git checkout ui`).
- **Satu tahap per sesi.** Commit kecil dan bermakna per tahap. Jangan `git add .` tanpa melihat `git status` dan `git diff --staged` lebih dulu. Pastikan tidak ada `config.php`, `storage/private/*`, atau `*.log` yang ikut ter-stage.
- Jangan klaim sesuatu berhasil sebelum Anda menjalankannya. Laporkan angka sebenarnya.
- Jika tes gagal atau tidak bisa dijalankan, **berhenti dan laporkan keluarannya**. Jangan melonggarkan asersi, jangan mengubah tes, jangan mengubah kode di luar batas di atas.
- Cara menjalankan tes (hasil ke berkas supaya tidak terpotong):

```powershell
cmd /c "php tests\run.php > %TEMP%\hasil_tes.txt 2>&1"
"exit code: $LASTEXITCODE"
$o = Get-Content "$env:TEMP\hasil_tes.txt"
"LOLOS=" + @($o | Select-String '^\[LOLOS\]').Count + " GAGAL=" + @($o | Select-String '^\[GAGAL\]').Count
$o | Select-Object -Last 3
```

- Jika `[GAGAL]` muncul, tampilkan baris-baris `[GAGAL]` saja (tanpa data sensitif).

## 4. Format laporan (akhir setiap tahap)

1. Berkas yang dibuat/diubah (daftar).
2. Hasil tes: LOLOS, GAGAL, exit code, dan perbandingannya dengan baseline Tahap 0.
3. Rute yang dipakai untuk setiap item menu, dan item yang **dilewati** karena rutenya tidak ada.
4. Hal yang menyimpang dari prompt ini dan alasannya.
5. Hash commit dan perintah untuk melihat hasilnya (`php -S localhost:8000 -t public`).
6. Apa yang perlu saya cek manual di browser.

---

## TAHAP 0: Persiapan dan baseline (hanya membaca)

1. `git status --short --branch` dan `git rev-parse HEAD`. Laporkan. Buat/pindah ke cabang `ui`.
2. Jika `tests/run.php` masih berstatus modified, **jangan commit**, cukup laporkan.
3. Baca (tanpa mengubah): `app/Views/layouts/main.php`, `public/assets/css/styles.css`, berkas router, dan daftar `app/Views/pages/`.
4. Laporkan:
   - Nama variabel CSS yang sudah ada (navy, emas, hijau, krem, dst.). **Pakai ulang nama itu**, tambah baru hanya bila perlu.
   - Bagaimana `main.php` sekarang menentukan peran dan menampilkan tautan per peran (kutip kondisi persisnya).
   - Daftar semua rute GET yang ada (path saja), dikelompokkan: publik, SISWA/GURU, ADMIN.
   - Apakah ada header CSP, dan apakah ada JavaScript sekarang.
   - Kelas tabel/panel yang dipakai (`data-table`, `table-wrap`, `table-scroll`, `page-panel`, dst.) dan berapa halaman memakai masing-masing.
5. Jalankan tes sekali (perintah di atas) dan catat sebagai **baseline**. Jika MySQL tidak aktif atau tes tidak bisa mulai, berhenti dan laporkan.
6. Usulkan pemetaan menu (tabel: label menu, rute asli, peran yang melihat). **Jangan ubah file apa pun di tahap ini.**

Menu target (sesuaikan dengan rute asli yang ditemukan):

| Menu | Isi dropdown |
|---|---|
| Beranda | (tautan langsung ke `/`) |
| Layanan | Katalog layanan, Ajukan layanan, Lacak tiket (`/lacak`) |
| KAHAYAN AMANAH | Sampaikan pengaduan, Lacak aduan (`/lacak-aduan`) |
| Survei | Isi survei (jika rute ada) |
| Zona Integritas | Eviden, Checklist, Evaluasi, RTL, Before/After (hanya yang rutenya ada dan boleh dilihat peran itu) |
| Dashboard MADANI | `/admin/madani` (ADMIN) |
| Admin | Antrean layanan, Antrean aduan, Kelola layanan, dan sisanya, dikelompokkan, bukan 17 tautan datar |
| Masuk / Keluar | Sesuai status login yang sudah ada |

---

## TAHAP 1: Aset dan token desain

Buat:

- `public/assets/img/logo-man-kota-palangkaraya.png`. Jika berkas belum ada dan Anda tidak punya akses jaringan, **berhenti dan minta saya menyimpannya**. Jangan membuat logo palsu.
- `public/assets/img/tingang.svg` (ilustrasi dekoratif, boleh diganti nanti dengan ilustrasi resmi):

```svg
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 240 160" aria-hidden="true">
  <path fill="#12284c" d="M60 70C60 40 110 30 140 50C170 40 200 60 205 90L236 142L190 126L175 152L150 116C120 126 80 116 60 70Z"/>
  <path fill="#d4a017" d="M60 62C48 44 26 48 20 62C34 58 46 62 58 74Z"/>
  <path fill="#0b6b3a" d="M60 70C30 60 10 62 4 76C20 78 40 84 60 90Z"/>
  <path fill="#fff" opacity=".85" d="M196 96L232 140L190 126Z"/>
  <circle cx="82" cy="66" r="4" fill="#fff"/>
</svg>
```

Tambahkan token ke awal `styles.css` (gunakan nama yang sudah ada bila tersedia, ini hanya contoh):

```css
:root{
  --green:#0b6b3a; --green-d:#084d2a; --gold:#d4a017; --navy:#12284c;
  --cream:#fbf6e8; --bg:#f4f1e8; --card:#ffffff; --text:#1d2433;
  --muted:#5d6678; --line:#e3dcc8;
}
@media (prefers-color-scheme:dark){
  :root{ --green:#1d8a55; --green-d:#0f5a37; --navy:#dbe6ff; --cream:#1d2330;
         --bg:#11151d; --card:#1a202c; --text:#e8ecf4; --muted:#a3adc2; --line:#2c3547; }
}
/* Garis motif Dayak */
.dayak{height:8px;
  background:
    linear-gradient(135deg,var(--gold) 25%,transparent 25%) -8px 0/16px 16px,
    linear-gradient(225deg,var(--gold) 25%,transparent 25%) -8px 0/16px 16px,
    var(--green-d);}
```

Commit: `ui: aset logo, tingang, token warna`. Jalankan tes, laporkan.

---

## TAHAP 2: Layout, header, dan navigasi dropdown

Ubah `app/Views/layouts/main.php` dan buat `app/Views/partials/topbar.php`, `header.php`, `nav.php`, `footer.php`. Jangan mengubah bagian yang memuat `$content`/isi halaman atau pemanggilan flash message yang sudah ada.

Struktur dasar (contoh):

```php
<div class="dayak"></div>
<?php require __DIR__ . '/../partials/topbar.php'; ?>
<header class="brand"><div class="wrap">
  <a href="/"><img src="/assets/img/logo-man-kota-palangkaraya.png"
       alt="Madrasah Aliyah Negeri Kota Palangkaraya"></a>
  <img class="tingang" src="/assets/img/tingang.svg" alt="" aria-hidden="true">
</div></header>
<?php require __DIR__ . '/../partials/nav.php'; ?>
```

Contoh `nav.php`. **Kondisi peran harus disalin dari `main.php` lama; variabel `$isAdmin` di bawah hanya ilustrasi:**

```php
<nav class="site-nav" id="siteNav" aria-label="Menu utama">
  <div class="wrap">
    <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="menuList">☰ Menu</button>
    <ul class="menu" id="menuList">
      <li><a href="/">Beranda</a></li>

      <li class="has-sub">
        <a href="/layanan" aria-haspopup="true">Layanan</a>   <!-- href asli dari router -->
        <ul class="sub">
          <li><a href="/layanan">Katalog Layanan</a></li>
          <li><a href="/lacak">Lacak Tiket</a></li>
        </ul>
      </li>

      <li class="has-sub">
        <a href="/aduan" aria-haspopup="true">KAHAYAN AMANAH</a>
        <ul class="sub">
          <li><a href="/aduan">Sampaikan Pengaduan</a></li>
          <li><a href="/lacak-aduan">Lacak Aduan</a></li>
        </ul>
      </li>

      <?php if ($isAdmin): /* salin kondisi asli dari main.php */ ?>
      <li class="has-sub">
        <a href="/admin" aria-haspopup="true">Admin</a>
        <ul class="sub">
          <li><a href="/admin/layanan">Kelola layanan</a></li> <!-- teks harus persis -->
          <li><a href="/admin/madani">Dashboard MADANI</a></li>
        </ul>
      </li>
      <?php endif; ?>
    </ul>
  </div>
</nav>
```

Catatan: path pada contoh adalah **tebakan ilustrasi**. Ganti dengan rute dari hasil Tahap 0. Semua teks dinamis (mis. nama pengguna) lewat `e()`.

CSS navigasi (tambahkan ke `styles.css`):

```css
.site-nav{background:var(--green);position:sticky;top:0;z-index:50}
.site-nav .wrap{display:flex;align-items:center}
.menu,.sub{list-style:none;margin:0;padding:0}
.menu{display:flex;flex-wrap:wrap}
.menu>li{position:relative}
.menu a{display:block;padding:14px 16px;color:#fff;font-weight:600}
.menu>li:hover>a,.menu>li:focus-within>a{background:var(--green-d);box-shadow:inset 0 -3px var(--gold)}
.has-sub>a::after{content:" ▾";font-size:.7rem}
.sub{display:none;position:absolute;left:0;top:100%;min-width:230px;background:var(--card);
     border:1px solid var(--line);border-top:3px solid var(--gold);box-shadow:0 8px 20px rgba(0,0,0,.18)}
.sub a{color:var(--text);font-weight:500;border-bottom:1px solid var(--line)}
.sub a:hover{background:var(--cream);color:var(--green)}
.menu li:hover>.sub,.menu li:focus-within>.sub,.menu li.open>.sub{display:block}
.nav-toggle{display:none;margin-left:auto;background:none;border:0;color:#fff;font-size:1.2rem;padding:10px}
@media (max-width:860px){
  .nav-toggle{display:block}
  .menu{display:none;width:100%;flex-direction:column}
  .site-nav.open .menu{display:flex}
  .sub{position:static;box-shadow:none;border:0;background:var(--green-d)}
  .sub a{color:#fff;padding-left:30px;border-bottom:1px solid #ffffff22}
  .menu li:hover>.sub{display:none}
  .menu li.open>.sub{display:block}
}
```

`public/assets/js/site.js` (penyempurna; dimuat dengan `<script src="/assets/js/site.js" defer></script>`):

```js
(function () {
  var nav = document.getElementById('siteNav');
  if (!nav) return;
  var btn = nav.querySelector('.nav-toggle');
  btn.addEventListener('click', function () {
    var open = nav.classList.toggle('open');
    btn.setAttribute('aria-expanded', open);
  });
  // Di layar kecil, ketukan pertama pada induk membuka submenu.
  nav.querySelectorAll('.has-sub > a').forEach(function (a) {
    a.addEventListener('click', function (e) {
      if (window.matchMedia('(max-width:860px)').matches && !a.parentNode.classList.contains('open')) {
        e.preventDefault();
        a.parentNode.classList.add('open');
      }
    });
  });
  // Teks berjalan "Terbaru" (opsional, hormati prefers-reduced-motion)
  var tick = document.getElementById('tick');
  if (tick && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    var items = JSON.parse(tick.dataset.items || '[]'), i = 0;
    if (items.length > 1) setInterval(function () {
      i = (i + 1) % items.length; tick.textContent = items[i];
    }, 4500);
  }
})();
```

Footer: empat kolom (Identitas, Layanan, Pengaduan, Kontak) + garis `.dayak` + baris hak cipta. **Pertahankan peringatan privasi/data fiktif yang sudah ada** (pindahkan ke footer bila perlu, teksnya jangan diubah).

Jangan ada angka atau data buatan di teks berjalan. Isi dari teks statis yang netral.

Commit: `ui: layout, header, navigasi dropdown, footer`. Jalankan tes, laporkan. Cek di browser per peran: Guest, SISWA, GURU, ADMIN.

---

## TAHAP 3: CSS komponen dan responsif

Di `styles.css`:

1. Satukan gaya tabel (`data-table`, `table-wrap`, `table-scroll`) menjadi satu pola: pembungkus `overflow-x:auto` + `.data-table`. Pertahankan nama kelas lama sebagai alias bila halaman masih memakainya (hapus alias hanya jika sudah tidak dipakai di semua view).
2. Komponen: `.btn`, `.btn-primary` (emas), `.card` (hover naik 3px), `.title > span` (label judul seksi hijau dengan garis bawah), `.badge`, `.alert`, field formulir (fokus jelas, label terhubung).
3. Breakpoint: 1160px (lebar maksimum), 860px (menu hamburger), 600px (kolom tunggal, tabel menggulir).
4. Kontras teks minimal WCAG AA di mode terang dan gelap. Fokus keyboard harus terlihat (`:focus-visible`).
5. Jangan menyembunyikan atau memindahkan field honeypot. Jika honeypot memakai kelas/inline style, biarkan.

Contoh judul seksi:

```css
.title{margin:0 0 12px;border-bottom:2px solid var(--green)}
.title span{display:inline-block;background:var(--green);color:#fff;padding:5px 14px;font-size:.92rem}
```

Commit: `ui: komponen dan responsif`. Tes, laporkan.

---

## TAHAP 4: Beranda

Ubah halaman beranda (cari di `app/Views/pages/`) menjadi:

1. **Slider** 3 slide statis (teks, tautan ke rute yang ada: Ajukan Layanan, KAHAYAN AMANAH, MADANI) dengan titik navigasi dan putar otomatis 6 detik. Berhenti saat kursor di atasnya dan saat `prefers-reduced-motion`. Tanpa JS, slide pertama tetap tampil.
2. **Layanan Cepat**: ubin 2 kolom, tiap ubin tautan ke rute yang ada.
3. **Katalog layanan** sebagai kartu, memakai data yang **sudah dikirim controller** ke view (jangan ubah controller). Semua teks lewat `e()`.
4. Sidebar: "Alur Pengajuan" (4 langkah) dan ringkasan MADANI.
5. Ringkasan MADANI: bila tidak ada data, tampilkan teks `BELUM ADA DATA` persis. Jangan membuat angka contoh.
6. Burung tingang samar di slider pertama, `alt=""`, `aria-hidden="true"`, `pointer-events:none`.

Commit: `ui: beranda`. Tes, laporkan.

---

## TAHAP 5: Halaman lain

Terapkan komponen baru ke halaman lain (36 halaman), **hanya mengubah markup pembungkus dan kelas**:

- Judul halaman memakai `.title`, panel memakai `.card`/`page-panel`, tabel memakai pola tabel Tahap 3.
- Formulir: tata letak rapi, tombol `.btn-primary`. Jangan mengubah `name`, `action`, `method`, `_csrf`, honeypot.
- Kerjakan per kelompok dan commit per kelompok: (a) layanan dan pengajuan, (b) aduan, (c) survei, (d) eviden/checklist/evaluasi/RTL/before-after, (e) admin dan MADANI.
- Setelah tiap kelompok, jalankan tes dan laporkan. Jika sebuah teks yang diperiksa tes berubah, **berhenti**.

---

## TAHAP 6: Verifikasi akhir

1. Jalankan tes penuh sekali dan bandingkan dengan baseline. Laporkan LOLOS/GAGAL.
2. Telusuri seluruh view dengan `grep`: tidak ada `href="#"`, tidak ada `/laporan`, tidak ada `{{`/echo tanpa `e()` pada data dinamis yang baru Anda tambah, tidak ada URL `http(s)://` ke situs lain pada aset.
3. Pastikan setiap `<form method="post">` memiliki `_csrf` dan honeypot `website` seperti semula (bandingkan `git diff` pada baris form).
4. Buat daftar cek manual untuk saya per peran (Guest, SISWA, GURU, ADMIN) dan per lebar layar (375, 768, 1280 px).
5. Jika ada asersi tes yang perlu diubah karena teks memang sengaja berubah, **jangan ubah**. Ajukan usulan ke saya (berkas, baris, teks lama, teks baru, alasan).
6. Jangan merge ke `main` dan jangan push. Itu keputusan saya.

## Definisi selesai

- Tes setara atau lebih baik dari baseline, tidak ada kegagalan baru.
- Tidak ada tautan kosong atau mati di navigasi.
- Menu per peran identik hak aksesnya dengan sebelum redesain.
- Logo tampil, burung tingang dekoratif tidak mengganggu keterbacaan atau fokus keyboard.
- Dropdown bisa dipakai dengan mouse, keyboard (Tab), dan layar sentuh.