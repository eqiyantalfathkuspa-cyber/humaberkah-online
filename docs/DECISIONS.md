# DECISIONS LOG

## 2026-10-09

- Keputusan: tahap 0 dimulai hanya dengan analisis dokumen dan validasi lingkungan.
- Alasan: instruksi proyek menegaskan agar tidak menulis kode aplikasi sebelum persetujuan akhir Tahap 0.
- Status: dokumen dalam penyelarasan dan belum ada keputusan final implementasi.

- Keputusan: aturan pemisahan tugas eviden membutuhkan minimal dua akun ADMIN yang berbeda untuk pengujian verifikasi oleh pihak yang bukan pengunggah.
- Alasan: agar uji keamanan dan integritas proses verifikasi dapat dibuktikan secara valid, bukannya hanya satu akun yang mengunggah dan memverifikasi sendiri.
- Status: keputusan operasional yang harus dipenuhi di tahap pengujian eviden.

## Catatan kepastian

- PHP 8.3.33 dan MySQL 8.4.3 sesuai target.
- Ekstensi yang dibutuhkan tersedia: `pdo_mysql`, `mbstring`, `fileinfo`, `openssl`.
- MySQL server lokal terdeteksi hidup dan dapat diping.
- Semua keputusan yang bersifat operasional dan layanan tetap menunggu konfirmasi dari tim Pokja 6.
