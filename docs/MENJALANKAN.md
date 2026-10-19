# MENJALANKAN HUMA BERKAH

## Prasyarat

- PHP 8.3.x
- MySQL 8.4.x
- Laragon di Windows atau menjalankan server lokal yang sudah aktif
- Database `humamantap_dev` dan `humamantap_test` siap dibuat

## 1. Siapkan database

```bash
mysql -uroot -e "CREATE DATABASE IF NOT EXISTS humamantap_dev CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE DATABASE IF NOT EXISTS humamantap_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

## 2. Jalankan migrasi

```bash
php bin/jalankan-migrasi.php default
php bin/jalankan-migrasi.php test
```

## 3. Jalankan server lokal

### Opsi A: PHP built-in server

```bash
php -S localhost:8000 -t public
```

### Opsi B: Laragon virtual host

- arahkan document root ke folder `public/`
- simpan URL aplikasi sesuai virtual host Laragon yang Anda gunakan

## 4. Akses aplikasi

Buka browser ke:

```text
http://localhost:8000/
```

## 5. Jalankan tes

```bash
php tests/run.php
```

## 6. Catatan

- File `config/config.php` bersifat lokal dan tidak masuk Git.
- Setelah mereset basis data kerja, hapus cookie localhost di browser agar sesi lama tidak dipakai kembali.
- Prototipe tidak boleh menggunakan data nyata, identitas, atau dokumen sensitif.
- Semua dashboard yang menampilkan angka harus berasal dari query basis data dan bila kosong, gunakan `BELUM ADA DATA`.
