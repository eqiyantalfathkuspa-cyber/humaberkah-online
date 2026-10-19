# RENCANA TES HUMA BERKAH

## 1. Tujuan

Dokumen ini memuat rencana pengujian awal untuk Tahap 0 hingga Tahap 8. Fokus utama adalah menjamin keamanan, validitas data, integritas relasi, serta kepatuhan terhadap aturan prototipe: tidak memalsukan data, tidak membocorkan informasi milik orang lain, dan tidak melangkahi tahapan implementasi.

## 2. Prinsip pengujian

- Semua tes harus dijalankan pada database uji `humamantap_test`.
- Semua angka dashboard harus diturunkan dari query basis data; bila data kosong, tampilkan `BELUM ADA DATA`.
- Data demo hanya boleh ditambahkan melalui script `bin/seed-demo.php` dan tidak boleh berjalan pada environment produksi.
- Izin harus diperiksa di server pada setiap rute.
- Setiap hasil tes harus menampilkan keluaran asli, bukan ringkasan yang dibuat-buat.

## 3. Rencana tes per tahap

### Tahap 0: Penyelarasan dokumen dan lingkungan
- Cek PHP CLI: `php -v`
- Cek MySQL: `mysql --version`
- Cek modul PHP: `php -m`
- Validasi keberadaan dokumentasi: `PROMPT_AGENT.md`, `BLUEPRINT.md`, `FLOWCHART_PELAYANAN.md`, `ERD.md`, `MATRIKS_IZIN.md`, `RENCANA_TES.md`, `DECISIONS.md`
- Validasi bahwa semua keputusan kritis belum disetujui dan ditandai sebagai `DECISION REQUIRED`

### Tahap 1: Fondasi
- Tes struktur folder minimal
- Tes koneksi DB ke `humamantap_dev` dan `humamantap_test`
- Tes migrasi berurutan `001_*.sql`, `002_*.sql`, dst.
- Tes `schema_migrations` lain telah tercatat
- Tes `id_counters` dapat menghasilkan nomor unik
- Tes halaman depan menampilkan placeholder dengan `BELUM ADA DATA`
- Tes `config.php` tidak ada di riwayat Git

### Tahap 2: Login dan hak akses
- Matriks role × rute diizinkan / ditolak
- Tes CSRF pada semua form mutasi
- Tes login dengan password hash valid dan invalid
- Tes rate limit login (gagal berulang menutup akun / memblokir sementara)
- Tes guest tanpa sesi tidak bisa mengakses layar internal
- Tes `bin/buat-admin.php` membuat akun admin dengan hash yang valid
- Tes `Permissions.php` sebagai satu sumber kebenaran izin

### Tahap 3: Katalog dan pengajuan
- Tes 5 kategori muncul di DB
- Tes 11 layanan muncul di DB
- Tes 88 langkah layanan atau dataset agar konsisten dengan `FLOWCHART_PELAYANAN.md`
- Tes nomor tiket unik: `REQ-YYYY-0001`, `REQ-YYYY-0002`, dst.
- Tes token pelacakan unik dan tidak bisa diprediksi
- Tes lacak aplikasi tanpa membocorkan identitas pemohon
- Tes pengajuan layanan sesuai mode `FORMULIR`/`INFORMASI`/`TATAP_MUKA`

### Tahap 4: Unggah dokumen dan portal
- Tes file PDF, JPG, PNG diizinkan sesuai kebijakan
- Tes file berbahaya ditolak: `.php`, `.exe`, `.js`, `.svg`, `.html`
- Tes ukuran file melebihi batas ditolak
- Tes download file tanpa izin menghasilkan 403 atau 404
- Tes upload oleh pengguna tidak berhak ditolak

### Tahap 5: KAHAYAN AMANAH dan survei
- Tes penggunaan hak milik pada pengaduan: pengguna hanya melihat miliknya
- Tes pengguna lain tidak bisa mengakses pengaduan milik orang lain
- Tes survei hanya dapat dikirim sekali per pengguna/identitas yang ditentukan
- Tes rekap survei hanya membaca data valid
- Tes riwayat tindak lanjut append-only

### Tahap 6: Eviden dan checklist 99 sel
- Tes selalu 99 sel bila data acuan lengkap
- Tes `evidence_checkpoints` berjumlah 9 titik pembuktian
- Tes status checklist: `KOSONG`, `ADA_BELUM_VERIFIKASI`, `PERLU_PERBAIKAN`, `VALID`
- Tes pemisahan tugas eviden: uploader dan verifier harus berbeda (minimal dua akun ADMIN)
- Tes `evidence_links` tidak kehilangan relasi layanan × checkpoint × eviden
- Tes `evidence_items.verification_status` berubah sesuai alur verifikasi

### Tahap 7: MADANI, evaluasi, RTL, laporan
- Tes semua angka dashboard berasal dari query DB
- Tes nilai kosong menampilkan `BELUM ADA DATA`
- Tes daftar laporan mengarah ke data yang valid dan tidak dipalsukan
- Tes evaluasi, RTL, dan dampak dapat terkait dengan aplikasi yang benar

### Tahap 8: Pengerasan
- Tes backup dan restore `mysqldump`
- Tes pemulihan basis data dari cadangan
- Tes konfigurasi keamanan yang tidak memerlukan modifikasi `php.ini`
- Tes keamanan terakhir untuk CSRF, file upload, dan akses rute

## 4. Tes akses lintas pengguna (wajib)

### 4.1 Akses permohonan milik orang lain
- Buat akun A dan akun B dengan role SISWA/GURU.
- A membuat permohonan.
- B mencoba membuka detail permohonan A.
- Harus menghasilkan 404, tanpa mengungkapkan keberadaan data.

### 4.2 Akses pengaduan milik orang lain
- A membuat pengaduan.
- B mencoba membuka pengaduan A.
- Harus menghasilkan 404.

### 4.3 Akses admin tanpa sesi
- Guest/ Siswa / Guru mencoba membuka route admin.
- Harus ditolak dengan 403/redirect ke login.

## 5. Tes CSRF

- Form login tidak memerlukan CSRF jika bukan form mutasi; form pengajuan, upload, pengaduan, survei, dan pengelolaan admin harus memerlukan token.
- Token yang salah atau kosong harus ditolak.
- Akses tanpa token ke form mutasi harus gagal dengan 403.

## 6. Tes unggahan berbahaya

- File dengan ekstensi `.php`, `.phtml`, `.phar`, `.exe`, `.js`, `.svg`, `.html` harus ditolak.
- File melebihi batas ukuran `5 MB` harus ditolak.
- File dengan MIME tidak sesuai harus ditolak.
- Output harus hanya menyimpan nama berkas terenkripsi di area private, bukan di path web publik.

## 7. Tes trigger append-only

- `application_history` atau `complaint_history` tidak boleh di-update atau dihapus secara manual.
- Trigger harus memunculkan error SQL dan menghentikan operasi.
- Log perubahan status hanya ditambahkan sebagai entri baru.

## 8. Tes matriks 99 sel

- Data acuan `evidence_checkpoints` harus berjumlah 9 titik.
- Data `services` setidaknya 11 layanan.
- Query matriks harus menghitung total `11 × 9 = 99` baris.
- Status per baris harus mengikuti aturan:
  - `KOSONG`
  - `ADA_BELUM_VERIFIKASI`
  - `PERLU_PERBAIKAN`
  - `VALID`
- Jika ada data kosong, status tidak boleh disamakan dengan angka 0.

## 9. Tes dasar data acuan

- `service_categories` minimal 5 kategori: TU, KUR, KES, SAR, HUM.
- `services` minimal 11 layanan sesuai flowchart.
- `service_steps` minimal 88 langkah yang koheren dengan katalog layanan.
- Nama layanan dan kategori harus mengikuti file `FLOWCHART_PELAYANAN.md` tanpa modifikasi.

## 10. Tes penanda prototipe dan data demo

- Semua layar yang memakai data demo harus menampilkan banner `PROTOTIPE: DATA DEMO`.
- Data demo harus membawa label `[DEMO]` di narasi/hasil log.
- `APP_ENV=production` harus menolak menjalankan `bin/seed-demo.php`.

## 11. Catatan

Rencana tes ini menjadi acuan untuk pengujian pada tahap implementasi berikutnya. Di Tahap 0, fokusnya adalah validasi dokumen, lingkungan, dan keputusan arsitektur sebelum aplikasi mulai dikembangkan.
