# DECISIONS LOG

## 2026-10-09

- Keputusan: tahap 0 dimulai hanya dengan analisis dokumen dan validasi lingkungan.
- Alasan: instruksi proyek menegaskan agar tidak menulis kode aplikasi sebelum persetujuan akhir Tahap 0.
- Status: dokumen dalam penyelarasan dan belum ada keputusan final implementasi.

- Keputusan: aturan pemisahan tugas eviden membutuhkan minimal dua akun ADMIN yang berbeda untuk pengujian verifikasi oleh pihak yang bukan pengunggah.
- Alasan: agar uji keamanan dan integritas proses verifikasi dapat dibuktikan secara valid, bukannya hanya satu akun yang mengunggah dan memverifikasi sendiri.
- Status: keputusan operasional yang harus dipenuhi di tahap pengujian eviden.

## 2026-10-09 (Tahap 2 – Login & Hak Akses)

- Keputusan: kata sandi minimal 10 karakter, dengan 5 kegagalan dalam 15 menit mengunci akun dan alamat IP selama 15 menit.
- Alasan: sesuai kebijakan keamanan yang ditetapkan untuk tahap autentikasi dan pencegahan brute force.
- Status: berlaku untuk login, penguncian, dan audit log Tahap 2.

- Keputusan: sesi kedaluwarsa setelah 2 jam tanpa aktivitas; cookie login menggunakan `HttpOnly` dan `SameSite=Lax`, serta `Secure` bila memakai HTTPS.
- Alasan: mencegah penggunaan sesi yang tidak aktif dan memperkuat keamanan cookie browser.
- Status: berlaku untuk seluruh rute tersimpan di sesi.

- Keputusan: akun dibuat melalui `bin/buat-admin.php` tanpa email, dengan `username` literal untuk ADMIN, `NISN` untuk SISWA, dan `NIP` untuk GURU.
- Alasan: login harus role-aware dan tidak membolehkan identitas hanya NISN/NIP saja tanpa kombinasi role tertentu.
- Status: berlaku untuk semua akun dan validasi login.

- Keputusan: Guest tidak memiliki akun dan tidak memiliki sesi login.
- Alasan: akses publik dibatasi di sisi server dan tidak boleh menganggap guest sebagai pengguna yang terautentikasi.
- Status: berlaku untuk semua rute publik dan internal.

- Keputusan: untuk validasi tahap 2, role GUEST, SISWA, GURU, ADMIN diuji pada rute `/login`, `/portal`, `/admin`, `/admin/pengguna`, dan `/admin/audit` serta setiap hasil dicatat sebagai 200/403/redirect sesuai izin server-side.
- Alasan: hanya akses yang diverifikasi dari server dapat dijamin aman; hiding di UI tidak dianggap cukup.
- Status: menjiwai matriks izin dan tes akses lintas pengguna.

- Keputusan tahap 2A: `Router` sepenuhnya menggunakan `Permissions.php` sebagai satu sumber izin, tanpa daftar role inline per rute.
- Alasan: mencegah duplikasi logika izin dan menjaga matriks izin sebagai definisi tunggal.
- Status: berlaku untuk semua rute dalam tahap 2A.

- Keputusan tahap 2A: `clientIp()` hanya membaca `REMOTE_ADDR`; header `X-Forwarded-For` dan `CF-Connecting-IP` tidak dipakai dan `trusted_proxies` bawaan kosong.
- Alasan: mencegah spoofing IP dan menjaga keamanan log serta lockout.
- Status: berlaku pada semua pengecekan login dan audit.

- Keputusan tahap 2A: saat akun terkunci, percobaan login berikutnya tidak menulis baris `LOCKED` baru; yang dicatat hanya kegagalan hingga batas lockout dan baris `LOCKED` ketika penguncian pertama terjadi.
- Alasan: menghindari duplikasi log kunci dan menjaga akurasi lockout.
- Status: diterapkan di `Auth::processLogin()` dan `Auth::recordFailure()`.

- Keputusan tahap 2A: `session_regenerate_id(true)` harus dijalankan bila header belum terkirim; bila header sudah dikirim, login ditolak dan dicatat di audit log.
- Alasan: mencegah sesi yang dibuat tidak aman dan mencegah peringatan "headers already sent".
- Status: berlaku di `Auth::login()`.

- Keputusan tahap 2A: pengelolaan akun dilakukan melalui POST `/admin/pengguna`, `/admin/pengguna/{id}/role`, `/admin/pengguna/{id}/nonaktif`, dan `/admin/pengguna/{id}/reset-sandi`; seluruh aksi memakai CSRF dan dicatat dalam `audit_logs`.
- Alasan: menjaga semua perubahan akun tetap terbatas pada ADMIN dan dapat ditelusuri, tanpa menyimpan sandi pada catatan audit.
- Status: sandi pembuatan dan reset minimal 10 karakter; ADMIN tidak dapat menonaktifkan atau menurunkan role dirinya sendiri.

- Keputusan tahap 2A: pilihan login "Siswa / Guru" hanya mencari role SISWA atau GURU, sedangkan "Admin Pokja 6" hanya mencari ADMIN; semua kegagalan login memakai pesan yang sama.
- Alasan: membatasi pencarian akun sesuai identitas yang dipilih tanpa membocorkan apakah akun ditemukan, sandi salah, atau akun terkunci.
- Status: diterapkan pada query autentikasi role-aware.

## 2026-10-09 (Langkah 3 – Katalog Layanan)

- Keputusan final Pokja 6: layanan awal bermode FORMULIR adalah TU-01, TU-02, KUR-02, KES-02, KES-04, SAR-01, dan HUM-01; bermode INFORMASI adalah KUR-01, KES-01, dan KES-03; HUM-02 bermode MODUL_PENGADUAN. TATAP_MUKA tetap tersedia sebagai pilihan mode, tetapi belum digunakan layanan awal.
- Alasan: KUR-01 dan KES-01 ditetapkan sebagai informasi yang dapat diakses langsung melalui website; HUM-02 merupakan entri modul pengaduan, bukan formulir pengajuan katalog.
- Status: diterapkan sebagai mode awal referensi katalog; semua ADMIN dapat memperbarui mode dan standar kecuali HUM-02 harus tetap MODUL_PENGADUAN dan layanan lain tidak dapat memakai mode tersebut.

- ASSUMPTION (menunggu konfirmasi Pokja 6): untuk layanan bermode INFORMASI, tahap 3 sampai 5 tetap ditampilkan dan masing-masing diberi keterangan "Tidak berlaku, informasi dapat diakses langsung".
- Alasan: mempertahankan urutan delapan tahap referensi yang sama sambil memperjelas bahwa proses pengajuan/verifikasi tidak berlaku pada informasi yang dibaca langsung.
- Status: diterapkan sebagai satu konstanta `ServiceCatalog::INFORMATION_STEPS_NOTICE` agar dapat diubah di satu tempat.

- Keputusan final Pokja 6: tarif, SLA, unit penanggung jawab, dan dokumen wajib tidak memiliki nilai bawaan; nilai kosong ditampilkan sebagai BELUM DITETAPKAN. Dokumen wajib pada Langkah 3 hanya berupa keterangan teks.
- Alasan: mencegah prototipe menyajikan angka atau standar layanan yang belum ditetapkan secara resmi.
- Status: kolom standar dimulai NULL; unggahan berkas belum dibuat dan akan mengikuti batas JPG/PNG maksimal 5 MB pada Langkah 4.

- Keputusan tahap 2C: batas lockout per akun tetap 5 kegagalan dalam 15 menit; batas gabungan per IP adalah 30 kegagalan dalam 15 menit, dengan lockout 15 menit.
- Alasan: jaringan sekolah dapat berbagi satu IP publik melalui NAT, sehingga batas IP harus lebih longgar untuk mengurangi pemblokiran pengguna lain yang tidak bersalah.
- Kompromi: penyerang dapat menyebarkan upaya login ke banyak akun sebelum ambang IP tercapai; batas per akun tetap berlaku untuk membatasi serangan terarah. `security.ip_attempt_limit` adalah asumsi yang dapat dikonfigurasi dan saat ini bernilai 30.
- Status: diterapkan pada hitungan kegagalan login; percobaan saat lockout aktif tidak menambah atau memperpanjang catatan `LOCKED`.

## Catatan kepastian

- PHP 8.3.33 dan MySQL 8.4.3 sesuai target.
- Ekstensi yang dibutuhkan tersedia: `pdo_mysql`, `mbstring`, `fileinfo`, `openssl`.
- MySQL server lokal terdeteksi hidup dan dapat diping.
- Semua keputusan yang bersifat operasional dan layanan tetap menunggu konfirmasi dari tim Pokja 6.
