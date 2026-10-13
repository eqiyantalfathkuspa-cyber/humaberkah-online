# HUMA MANTAP

HUMA MANTAP adalah prototipe layanan informasi dan administrasi MAN Kota Palangka Raya.

**Peringatan: PROTOTIPE. Gunakan DATA FIKTIF saja; jangan masukkan data nyata.**

## Kebutuhan

- PHP 8.3 dengan ekstensi `pdo_mysql`, `mbstring`, dan `fileinfo`.
- MySQL atau MariaDB yang berjalan.

## Pemasangan lokal (Laragon)

1. Jalankan MySQL dari Laragon dan buat basis data `humamantap_dev` dengan charset `utf8mb4`.
2. Salin konfigurasi contoh, lalu isi koneksi basis data dan kunci keamanan:

   ```powershell
   Copy-Item config/config.example.php config/config.php
   ```

3. Dari direktori proyek, jalankan migrasi:

   ```sh
   php bin/jalankan-migrasi.php
   ```

4. Jalankan server lokal:

   ```sh
   php -S localhost:8000 -t public
   ```

## Tes

Jalankan `php tests/run.php` dari direktori proyek. Suite membuat basis data fixture terisolasi sendiri; MySQL harus aktif dan pengguna basis data perlu izin membuat serta menghapus basis data.

## Struktur

- `app/Views/`: tampilan halaman.
- `public/assets/`: CSS, JavaScript, dan gambar.
- `database/migrations/`: skema dan perubahan basis data.
- `docs/`: dokumentasi dan catatan proyek.
- `tests/`: suite tes dan fixture.

## Keamanan

- Sebelum aplikasi dibuka ke jaringan, ganti sandi akun administrator bawaan/demo. Proyek tidak menyediakan akun default.
- Jangan commit `config/config.php`; berkas itu berisi konfigurasi lokal dan diabaikan Git.
- Pastikan server web tidak dapat mengeksekusi berkas di folder unggahan `storage/private/`.