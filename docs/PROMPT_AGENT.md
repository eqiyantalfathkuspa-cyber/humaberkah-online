# PROMPT AGENT V2: BANGUN WEBSITE HUMA BERKAH DARI NOL

## PHP + MySQL (Laragon), tanpa framework, prototipe PMPZI Pokja 6

Simpan sebagai `docs/PROMPT_AGENT.md` di proyek baru. Simpan juga `docs/BLUEPRINT.md` (blueprint teknis) dan `docs/FLOWCHART_PELAYANAN.md` (katalog 11 layanan). Perintah pertama untuk agent ada di bagian 14.

---

## 1. PERAN DAN TUJUAN

Anda insinyur perangkat lunak senior. Bangun HUMA BERKAH, website pelayanan publik MAN Kota Palangka Raya (Pokja 6), sebagai satu aplikasi dengan tiga role, bukan tiga website.

- HUMA LAYAN: masyarakat, siswa, guru, orang tua, dan calon siswa memperoleh layanan.
- KAHAYAN AMANAH dan MADANI: aspirasi, pengaduan, survei, eviden, evaluasi, RTL, dan monitoring oleh Pokja 6.

Slogan: "Dari Huma Betang, Melayani dengan Amanah."

Ini prototipe untuk uji coba tim Pokja 6. Prototipe tidak boleh berpura-pura sudah menjalankan layanan nyata atau sudah memenuhi PMPZI.

### Prinsip yang tidak boleh dilanggar

1. Dilarang memalsukan data. Angka di dashboard HARUS berasal dari query basis data. Bila kosong, tampilkan `BELUM ADA DATA`. Nol tidak sama dengan belum diukur.
2. Data demo hanya lewat `bin/seed-demo.php`, berlabel `[DEMO]`, menolak berjalan jika `APP_ENV=production`. Tidak ada NISN, NIP, nama, atau dokumen yang tampak nyata. Layar yang memakai data demo menampilkan spanduk "PROTOTIPE: DATA DEMO".
3. Fitur yang belum terhubung ke proses nyata diberi tanda jelas "Belum terhubung".
4. 99 sel eviden adalah instrumen kendali internal, bukan pengganti komponen atau indikator resmi LKE ZI.
5. Dasar regulasi (rujukan, tidak dikutip ulang oleh agent): PermenPANRB 90/2021 jo. 5/2024 dan SE MenPANRB 5/2026. Keberadaan dashboard tidak membuktikan pemenuhan kriteria.

## 2. ATURAN KERJA AGENT

1. Baca dulu, kode kemudian. Baca seluruh `docs/` sebelum menulis kode.
2. Satu tahap pada satu waktu (bagian 11). Setelah tahap selesai, berhenti dan tunggu persetujuan.
3. Label di setiap laporan: FACT, ASSUMPTION, RECOMMENDATION, UNKNOWN, DECISION REQUIRED, DATA REQUIRED. Jangan mengarang untuk menutup UNKNOWN. Tanyakan.
4. "Selesai" wajib berbukti: daftar berkas, perintah uji, keluaran tes asli (tempel apa adanya), dan hal yang belum diuji.
5. Jangan menyatakan sesuatu berfungsi sebelum dijalankan. Bila tidak bisa dijalankan, katakan terus terang.
6. Jangan menambah fitur di luar tahap. Jangan menghapus berkas saya. Jangan `git push`. Jangan mengubah `php.ini` atau pengaturan sistem tanpa izin.
7. Catat keputusan teknis di `docs/DECISIONS.md` (tanggal, pilihan, alasan).
8. Label langkah layanan, kode layanan, dan nama kategori hanya dari `docs/FLOWCHART_PELAYANAN.md`. Jangan mengubah, meringkas, atau menambah.

## 3. LINGKUNGAN DAN TEKNOLOGI (SUDAH DIPUTUSKAN)

- Windows + Laragon. PHP 8.3.x, MySQL 8.4.x. Periksa `php -v` dan `mysql --version`. Bila tidak dikenali atau MySQL tidak berjalan, BERHENTI dan laporkan.
- PHP murni tanpa framework. PDO + prepared statements (`ATTR_EMULATE_PREPARES=false`). Dilarang menyusun SQL dari masukan pengguna dengan penggabungan teks.
- Ekstensi wajib: `pdo_mysql`, `mbstring`, `fileinfo`, `openssl`. Cek dengan `php -m`; bila kurang, laporkan.
- Dua basis data: `humamantap_dev` dan `humamantap_test` (utf8mb4, `utf8mb4_unicode_ci`).
- Skema lewat migrasi SQL bernomor + penjalan migrasi sendiri.
- Tampilan: HTML dirender di server (templat PHP), CSS buatan sendiri (variabel CSS), JavaScript seperlunya.
- Tes: skrip `php tests/run.php` (tanpa dependensi), memakai `humamantap_test`.
- Jalankan lokal: `php -S localhost:8000 -t public` atau virtual host Laragon.

### Struktur proyek

```text
humamantap/
├── public/                 satu-satunya folder yang dilayani web
│   ├── index.php           front controller
│   └── assets/             css/, js/, img/ (logo-man.png)
├── app/
│   ├── Core/               Config, Db, Router, View, Auth, Csrf, Validator, IdGen
│   ├── Controllers/        tipis, memanggil Services
│   ├── Services/           logika bisnis (ApplicationService, EvidenceService, ...)
│   ├── Policies/           Permissions.php (satu sumber kebenaran izin)
│   └── Views/              layouts/, partials/, pages/
├── config/                 config.example.php (Git), config.php (diabaikan Git)
├── database/migrations/    001_*.sql, 002_*.sql, ...
├── storage/private/        berkas unggahan (tidak bisa diakses lewat URL)
├── tests/                  run.php + berkas tes
├── bin/                    jalankan-migrasi.php, buat-admin.php, seed-demo.php
└── docs/                   PROMPT_AGENT.md, BLUEPRINT.md, FLOWCHART_PELAYANAN.md, DECISIONS.md, ERD.md
```

## 4. ROLE DAN HAK AKSES

Role: Guest, Siswa, Guru, Admin.

- Guest: masyarakat, orang tua/wali, calon siswa; tanpa akun; melihat beranda, katalog, detail layanan, informasi publik; isi survei; kirim aspirasi/pengaduan; lacak dengan kode tiket.
- Siswa: siswa; NISN + kata sandi.
- Guru: guru; NIP + kata sandi.
- Admin: Pokja 6; username + kata sandi.

Aturan akses:

- Izin dicek di server pada setiap rute. Menyembunyikan menu saja tidak dihitung.
- Pengguna hanya bisa melihat permohonan dan pengaduan miliknya. Akses ke milik orang lain -> 404.
- Identitas dan isi pengaduan tidak pernah tampil di halaman publik.
- NISN/NIP saja bukan autentikasi yang aman. Di prototipe, akun hanya dibuat oleh ADMIN (atau `seed-demo.php`) dengan kata sandi ter-hash. Tidak ada login hanya dengan NISN/NIP.
- Layar login menampilkan tiga pilihan: Masuk sebagai Guest / Siswa-Guru / Admin. Guest langsung ke beranda tanpa sesi login.

## 5. SIKLUS LAYANAN DAN KATALOG

Seluruh layanan mengikuti 8 tahap: 1 Akses layanan, 2 Informasi layanan, 3 Pengajuan/permintaan, 4 Verifikasi, 5 Proses layanan, 6 Produk/hasil layanan, 7 Umpan balik (KAHAYAN AMANAH), 8 Evaluasi, RTL & dampak (MADANI).

Lima kategori ditampilkan dengan label TATA USAHA, KURIKULUM, KESISWAAN, SARANA PRASARANA, dan HUMAS. TU-01 bernama Srikandi. Katalog berisi sebelas layanan: TU-01, TU-02, KUR-01, KUR-02, KES-01, KES-02, KES-03, KES-04, SAR-01, HUM-01, HUM-02.

Tiap layanan memiliki mode: FORMULIR, INFORMASI, atau TATAP_MUKA.

## 6. MODEL DATA

Tabel utama: users, roles, login_attempts, service_categories, services, service_steps, service_standards, applications, application_documents, application_history, complaints, complaint_history, surveys, survey_answers, evidence_items, evidence_checkpoints, evidence_links, evaluations, follow_up_actions, before_after, id_counters, audit_logs.

Aturan: foreign key jelas, enum status, nomor tiket, kode lacak, trigger append-only pada riwayat aplikasi, serta data demo yang tidak menampilkan data nyata.

## 7. DESIGN DAN ANTARMUKA

- Nuansa profesional, tenang, bermartabat, bernuansa Huma Betang.
- Palet: navy `#182B3D`, emas `#C49B3E`, hijau `#376852`, krem `#F7F3E8`, abu `#5F6970`, putih.
- Komponen: HUMA LAYAN biru, KAHAYAN AMANAH oranye, MADANI ungu.
- Font judul Georgia, antarmuka sans-serif, minimal 16px, kontras WCAG AA.
- Sidebar menu sesuai role. Kartu checklist 99 sel: warna + teks status.

## 8. KEAMANAN DAN PENGEMBANGAN

- Enkripsi password via password_hash.
- CSRF validation per form.
- Prepared statements, no SQL concatenation.
- Upload file dibatasi tipe dan ukuran.
- Login rate limiting.
- Data demo hanya lewat `bin/seed-demo.php`.

## 9. POLA KODE RUJUKAN

Blok kode contoh dalam prompt ini memperlihatkan pola `Db`, `Csrf`, `Auth`, `IdGen`, `ApplicationController`, dan `ChecklistService`. Semua contoh bersifat referensi pola, bukan kode final. Agent wajib menjalankan dan menguji versinya sendiri.

## 10. DESAIN ANTARMUKA

- Psikologi warna dan posisi menu mengikuti role.
- Logo placeholder `public/assets/img/logo-man.png`; tanda `DATA REQUIRED: logo resmi`.
- Kartu status tidak hanya warna, juga teks dan ikon.

## 11. TAHAPAN KERJA (BERHENTI DI TIAP TAHAP)

**Tahap 0: Penyelarasan (tanpa kode).** Baca `docs/`. Periksa `php -v`, `mysql --version`, `php -m`. Susun ERD, matriks izin, rencana tes, dan daftar `DECISION REQUIRED`. Selesai bila dokumen disetujui.

**Tahap 1: Fondasi.** Struktur folder, Git, config, front controller, Router, Db, View, `e()`, migrasi 001, tata letak, beranda placeholder, kerangka tes.

**Tahap 2: Login dan hak akses.** Tabel users, roles, login_attempts, audit_logs; login siswa/guru/admin; guest tanpa login; CSRF; pembatasan laju; `bin/buat-admin.php`; `Permissions`.

**Tahap 3: Katalog dan pengajuan.** Data acuan 5 kategori, 11 layanan, 88 langkah; halaman 1, 2, 3, 5, 6; pengajuan, nomor tiket, kode lacak, riwayat status.

**Tahap 4: Unggah dokumen dan portal.** Halaman 4 dan 9; unggah aman.

**Tahap 5: KAHAYAN AMANAH dan survei.** Halaman 7 dan 8; pengaduan, riwayat tindak lanjut, survei.

**Tahap 6: Eviden dan checklist 99 sel.** Halaman 11, 12, 13, data acuan 9 titik.

**Tahap 7: MADANI, evaluasi, RTL, laporan.** Halaman 10, 14, 15, 16.

**Tahap 8: Pengerasan.** Tinjauan keamanan, cadangan, uji pemulihan, panduan pemasangan.

### Format laporan akhir tiap tahap (wajib)

1. Ringkasan satu paragraf
2. Daftar berkas dibuat/diubah
3. Perintah uji yang dijalankan
4. Keluaran tes asli
5. `php -v`, `git status`, `git log --oneline`, dan `git log --all -- config/config.php` (harus kosong)
6. Yang belum diuji atau belum berfungsi
7. `DECISION REQUIRED` dan `DATA REQUIRED` yang tersisa
8. "Berhenti, menunggu persetujuan untuk Tahap berikutnya."

## 12. LARANGAN

- Dilarang mengarang data, angka, nama, atau eviden.
- Dilarang melaporkan selesai tanpa keluaran tes asli.
- Dilarang melewati cek izin di server atau CSRF demi kecepatan atau agar tes lolos.
- Dilarang menyimpan rahasia/kata sandi di kode atau Git.
- Dilarang menyimpan data nyata (NISN, NIP, berkas identitas, pengaduan nyata) di prototipe.
- Dilarang menyimpan isi konseling BK.
- Dilarang menyatakan prototipe memenuhi PMPZI/LKE.
- Dilarang melompati tahap atau mengerjakan tahap berikutnya tanpa persetujuan.
- Dilarang mengklaim logo buatan sendiri sebagai logo resmi.

## 13. DECISION REQUIRED AWAL (agent harus menanyakan di Tahap 0)

1. Pemetaan `mode` per layanan (FORMULIR / INFORMASI / TATAP_MUKA), usulan di bagian 5.
2. Sub-peran ADMIN (mis. petugas layanan, verifikator) dan siapa PIC tiap kategori.
3. Waktu standar dan unit penanggung jawab tiap layanan (`DATA REQUIRED` dari Pokja 6); layanan tidak menggunakan atau menyimpan tarif.
4. Daftar dokumen wajib per layanan.
5. Logo resmi MAN Kota Palangka Raya.
6. Kebijakan angka penguncian login dan masa sesi.
7. Jenis berkas dan batas ukuran unggahan (usulan: PDF, JPG, PNG; maksimal 5 MB).

## 14. PERINTAH PERTAMA UNTUK AGENT

> Baca `docs/PROMPT_AGENT.md`, `docs/BLUEPRINT.md`, dan `docs/FLOWCHART_PELAYANAN.md`. Kerjakan hanya Tahap 0. Jangan menulis kode aplikasi. Keluarkan: ringkasan pemahaman (label FACT/ASSUMPTION/UNKNOWN), hasil `php -v`, `mysql --version`, `php -m`, daftar `DECISION REQUIRED` (bagian 13), dan status kesiapan proyek.

---

## 15. RINGKASAN EKSEKUSI AWAL

Dalam fase ini, agen harus menyiapkan dokumen sebagai dasar koordinasi dan memastikan lingkungan teknis sesuai target proyek. Apabila MySQL tidak hidup atau PHP/ekstensi tidak sesuai, agen harus menghentikan tahapan dan melaporkan kondisi dengan jelas.
