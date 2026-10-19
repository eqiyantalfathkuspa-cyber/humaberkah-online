# BLUEPRINT TEKNIS HUMA BERKAH

## 1. Tujuan proyek

HUMA BERKAH adalah prototipe website pelayanan publik MAN Kota Palangka Raya, dibangun sebagai satu aplikasi multi-role yang mencakup:

- HUMA LAYAN untuk layanan masyarakat dan pemangku kepentingan eksternal
- KAHAYAN AMANAH untuk pengaduan, aspirasi, survei, eviden, evaluasi, RTL, monitoring
- MADANI untuk evaluasi, dampak, dan pelaporan layanan

Semua modul diposisikan sebagai satu sistem, bukan tiga website terpisah. Prototipe bukan bukti pemenuhan PMPZI atau LKE. Semua angka dashboard harus diperoleh dari query basis data dan bila tidak ada data harus ditampilkan `BELUM ADA DATA`.

## 2. Konteks teknis

- Bahasa: PHP 8.3.x
- Database: MySQL 8.4.x
- Arsitektur: PHP murni tanpa framework
- Mode akses: server-side rendering, CSS buatan sendiri, JavaScript terbatas
- Database utama: `humamantap_dev`
- Database uji: `humamantap_test`
- Semua query memakai PDO prepared statement dengan `ATTR_EMULATE_PREPARES=false`
- Struktur folder mengikuti blueprint proyek

## 3. Role dan hak akses

### Guest
- tidak login
- melihat beranda, katalog layanan, detail layanan, halaman publik, formulir survei, aspirasi, pengaduan, tracking

### Siswa/Guru
- login dengan akun ter-hash
- melihat status permohonan sendiri
- melihat pengaduan sendiri
- hanya data milik sendiri yang dapat diakses

### Admin
- login dengan akun admin ter-hash
- akses terhadap semua modul internal
- memelihara data layanan, pengajuan, dokumentasi, eviden, evaluasi, laporan

## 4. Prinsip data dan keamanan

- Data demo hanya di `bin/seed-demo.php`
- Data demo menolak berjalan saat `APP_ENV=production`
- Semua data sumber nyata dilarang disimpan di prototipe
- Keinformasian data pribadi dan data BK tidak disimpan di sistem prototipe
- CSRF dan server-side authorization wajib
- Tidak ada SQL build dari input pengguna

## 5. Struktur aplikasi

```text
humamantap/
├── public/
│   ├── index.php
│   └── assets/
├── app/
│   ├── Core/
│   ├── Controllers/
│   ├── Services/
│   ├── Policies/
│   └── Views/
├── config/
├── database/migrations/
├── storage/private/
├── tests/
├── bin/
└── docs/
```

## 6. Domain utama

### A. Layanan publik
- katalog layanan
- detail tahap layanan
- status pengajuan
- kode lacak
- nomor tiket otentik

### B. Pengaduan dan aspirasi
- form pengaduan
- log riwayat tindak lanjut
- pembatasan akses per pengguna

### C. Survey dan evaluasi
- survei layanan
- pencegahan pengiriman ganda
- rekap hasil

### D. Eviden dan checklist 99 sel
- lintas layanan dan checkpoint
- status: VALID, ADA_BELUM_VERIFIKASI, PERLU_PERBAIKAN, KOSONG
- instrumen kendali internal

### E. MADANI
- evaluasi dampak
- RTL
- dashboard kinerja dengan data dari DB

## 7. Model data inti

Tabel utama:

- users
- roles
- login_attempts
- service_categories
- services
- service_steps
- service_standards
- applications
- application_documents
- application_history
- complaints
- complaint_history
- surveys
- survey_answers
- evidence_items
- evidence_checkpoints
- evidence_links
- evaluations
- follow_up_actions
- before_after
- id_counters
- audit_logs

### Aturan umum database

- primary key dan foreign key jelas
- `ON DELETE` dipastikan jelas
- enum untuk status yang konsisten
- nomor tiket serta token pelacakan unik
- riwayat harus append-only untuk perubahan status
- trigger dibuat per pernyataan SQL agar migrasi aman

## 8. Proses layanan yang mendasari

Semua layanan mengikuti 8 tahap:

1. Akses layanan
2. Informasi layanan
3. Pengajuan / permintaan
4. Verifikasi
5. Proses layanan
6. Produk / hasil layanan
7. Umpan balik (KAHAYAN AMANAH)
8. Evaluasi, RTL, dampak (MADANI)

## 9. Kategori dan layanan

Dibuat dari katalog layanan yang dianut oleh flowchart. Total layanan: 11. Kategori: TU, KUR, KES, SAR, HUM.

Layanan yang harus diakomodasi:

- TU-01
- TU-02
- KUR-01
- KUR-02
- KES-01
- KES-02
- KES-03
- KES-04
- SAR-01
- HUM-01
- HUM-02

## 10. Contoh pola implementasi

Blueprint ini membolehkan pola pendekatan sebagai berikut:

- `app/Core/Db.php` untuk koneksi PDO dan query helper
- `app/Core/Csrf.php` untuk validasi token form
- `app/Core/Auth.php` untuk guard role
- `app/Core/IdGen.php` untuk nomor tiket berurutan
- `app/Policies/Permissions.php` sebagai satu sumber kebenaran izin
- `app/Services/ChecklistService.php` untuk matriks 99 sel

## 11. Decision required

Pada tahap 0, keputusan yang belum final dan harus diputuskan oleh pihak Pengguna/Tim Pokja 6:

1. mode per layanan: FORMULIR, INFORMASI, atau TATAP_MUKA
2. sub-peran admin (petugas layanan, verifikator, dsb.)
3. SLA dan unit penanggung jawab layanan; biaya/tarif tidak digunakan atau disimpan
4. dokumen wajib tiap layanan
5. logo resmi MAN Kota Palangka Raya
6. kebijakan rate limit dan masa sesi
7. jenis dan ukuran berkas unggahan

## 12. Status proyek saat ini

Proyek baru. Tahap 0 sedang berjalan: penyelarasan dokumen, validasi lingkungan PHP/MySQL, dan identifikasi item keputusan yang harus disepakati sebelum coding aplikasi dimulai.
