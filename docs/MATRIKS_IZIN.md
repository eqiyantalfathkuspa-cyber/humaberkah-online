# MATRIKS IZIN HUMA MANTAP

## 1. Prinsip dasar

- Semua izin harus dicek di server, bukan hanya disembunyikan di UI.
- Pengguna hanya boleh melihat data milik sendiri.
- Guest tidak memiliki sesi login dan hanya dapat mengakses halaman publik.
- Siswa dan Guru dibedakan sebagai role berbeda, tetapi di UI bisa ditampilkan sebagai satu pilihan "Siswa / Guru".
- Admin memiliki akses penuh terhadap modul internal, namun pembatasan sub-peran ditunda sampai keputusan final.

## 2. Matriks izin role × rute / kemampuan

| Rute / kemampuan | GUEST | SISWA | GURU | ADMIN |
|---|---:|---:|---:|---:|
| Halaman beranda publik | YA | YA | YA | YA |
| Katalog layanan publik | YA | YA | YA | YA |
| Detail layanan publik | YA | YA | YA | YA |
| Form survei publik | YA | YA | YA | YA |
| Kirim aspirasi / pengaduan | YA | YA | YA | YA |
| Lacak permohonan dengan token | YA | YA | YA | YA |
| Login ke akun | TIDAK | YA | YA | YA |
| Melihat status permohonan sendiri | TIDAK | YA | YA | YA |
| Melihat riwayat permohonan orang lain | TIDAK | TIDAK | TIDAK | YA |
| Mengajukan layanan FORMULIR | YA (jika layanan terbuka publik) | YA | YA | YA |
| Mengakses halaman admin dashboard | TIDAK | TIDAK | TIDAK | YA |
| Mengelola pengguna | TIDAK | TIDAK | TIDAK | YA |
| Mengunggah bukti / eviden | TIDAK | TIDAK | TIDAK | YA |
| Memverifikasi eviden | TIDAK | TIDAK | TIDAK | YA |
| Melihat seluruh aplikasi sistem | TIDAK | TIDAK | TIDAK | YA |
| Melihat seluruh pengaduan sistem | TIDAK | TIDAK | TIDAK | YA |
| Melihat pengaduan milik sendiri | TIDAK | YA | YA | YA |
| Mengedit pengaduan milik sendiri | TIDAK | YA (jika status mengizinkan) | YA (jika status mengizinkan) | YA |
| Mengakses halaman BK / data sensitif siswa | TIDAK | TIDAK | TIDAK | YA (internal; tidak menyimpan data sensitif) |
| Mengakses endpoint upload berbahaya / file rahasia | TIDAK | TIDAK | TIDAK | TIDAK |
| Menambah / menghapus layanan acuan | TIDAK | TIDAK | TIDAK | YA |
| Membaca audit log sistem | TIDAK | TIDAK | TIDAK | YA |

## 3. Aturan khusus

### Guest
- Guest tidak memiliki sesi login dan tidak diberikan akses terhadap data internal.
- Guest hanya bisa melihat publik dan melacak dengan kode lacak yang valid.
- Akses ke data milik pemohon lain harus menghasilkan 404, bukan pesan yang membocorkan keberadaan data.

### SISWA
- SISWA hanya boleh mengakses permohonan, pengaduan, dan data yang terkait akun dirinya.
- SISWA tidak boleh melihat catatan administratif admin atau data pengguna lain.
- Jika ada layanan yang memerlukan role khusus, aturan pengecekan tetap dilakukan di server.

### GURU
- GURU memiliki hak yang sama dengan SISWA untuk kepentingan data yang bersifat milik sendiri.
- GURU tidak boleh mengakses data siswa yang bukan miliknya.
- Akses ke halaman internal hanya melalui role ADMIN.

### ADMIN
- ADMIN memegang semua izin internal.
- ADMIN tetap dibatasi oleh aturan server-side yang tidak membolehkan mengakses data milik orang lain bila route tidak diizinkan.
- Untuk pemisahan tugas eviden, minimal diperlukan dua akun ADMIN yang berbeda agar uji verifikasi bukan pengunggah bisa dijalankan.

## 4. Catatan implementasi yang relevan untuk Tahap 0

- Matriks ini adalah sumber referensi saat menyusun `app/Policies/Permissions.php`.
- Semua keputusan di sini harus dijalankan dalam tes akses lintas pengguna di tahap berikutnya.
- Penyingkapan menu hanya dengan CSS/HTML tidak dianggap sebagai proteksi; audit di server wajib.
