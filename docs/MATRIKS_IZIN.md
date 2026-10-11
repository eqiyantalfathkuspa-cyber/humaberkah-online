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
| GET /layanan | YA | YA | YA | YA |
| GET /layanan/{kode} | YA | YA | YA | YA |
| Login ke akun | YA | YA | YA | YA |
| Logout | TIDAK | YA | YA | YA |
| Portal pengguna | TIDAK | YA | YA | TIDAK |
| Dashboard admin | TIDAK | TIDAK | TIDAK | YA |
| GET /admin/pengguna | TIDAK | TIDAK | TIDAK | YA |
| POST /admin/pengguna (buat pengguna) | TIDAK | TIDAK | TIDAK | YA |
| POST /admin/pengguna/{id}/role | TIDAK | TIDAK | TIDAK | YA |
| POST /admin/pengguna/{id}/nonaktif | TIDAK | TIDAK | TIDAK | YA |
| POST /admin/pengguna/{id}/reset-sandi | TIDAK | TIDAK | TIDAK | YA |
| Lihat audit admin | TIDAK | TIDAK | TIDAK | YA |
| GET /admin/layanan | TIDAK | TIDAK | TIDAK | YA |
| GET /admin/layanan/{kode}/ubah | TIDAK | TIDAK | TIDAK | YA |
| POST /admin/layanan/{kode} | TIDAK | TIDAK | TIDAK | YA |
| GET /layanan/{kode}/ajukan | TIDAK | YA | YA | TIDAK |
| POST /layanan/{kode}/ajukan | TIDAK | YA | YA | TIDAK |
| GET /lacak | YA | YA | YA | YA |
| POST /lacak (CSRF, `applications.track.lookup`) | YA | YA | YA | YA |
| GET /kahayan (form HUM-02) | YA | YA | YA | TIDAK |
| POST /kahayan (CSRF, kirim aduan/aspirasi) | YA | YA | YA | TIDAK |
| GET /kahayan/diterima (flash satu kali) | YA | YA | YA | TIDAK |
| GET /lacak-aduan | YA | YA | YA | YA |
| POST /lacak-aduan (CSRF, cek kode aduan) | YA | YA | YA | YA |
| GET /survei | YA | YA | YA | YA |
| POST /survei (CSRF, kode lacak dalam POST) | YA | YA | YA | YA |
| GET /survei/terima | YA | YA | YA | YA |
| GET /admin/aduan | TIDAK | TIDAK | TIDAK | YA |
| GET /admin/aduan/{id} | TIDAK | TIDAK | TIDAK | YA |
| POST /admin/aduan/{id}/status | TIDAK | TIDAK | TIDAK | YA |
| POST /admin/aduan/{id}/tanggapan | TIDAK | TIDAK | TIDAK | YA |
| GET /admin/survei | TIDAK | TIDAK | TIDAK | YA |
| GET /admin/survei/ekspor | TIDAK | TIDAK | TIDAK | YA |
| GET /admin/eviden (`admin.evidence.view`) | TIDAK | TIDAK | TIDAK | YA |
| POST /admin/eviden (`admin.evidence.upload`, CSRF) | TIDAK | TIDAK | TIDAK | YA |
| GET /admin/eviden/{id} (`admin.evidence.view`) | TIDAK | TIDAK | TIDAK | YA |
| GET /admin/eviden/{id}/berkas (`admin.evidence.download`) | TIDAK | TIDAK | TIDAK | YA |
| POST /admin/eviden/{id}/verifikasi (`admin.evidence.verify`, CSRF) | TIDAK | TIDAK | TIDAK | YA |
| GET /admin/checklist-99 (`admin.checklist.view`) | TIDAK | TIDAK | TIDAK | YA |
| GET /admin/evaluasi (`admin.evaluations.view`) | TIDAK | TIDAK | TIDAK | YA |
| POST /admin/evaluasi (`admin.evaluations.create`, CSRF) | TIDAK | TIDAK | TIDAK | YA |
| GET /admin/evaluasi/{id} (`admin.evaluations.view`) | TIDAK | TIDAK | TIDAK | YA |
| GET /admin/rtl (`admin.rtl.view`) | TIDAK | TIDAK | TIDAK | YA |
| POST /admin/rtl (`admin.rtl.create`, CSRF) | TIDAK | TIDAK | TIDAK | YA |
| GET /admin/rtl/{id} (`admin.rtl.view`) | TIDAK | TIDAK | TIDAK | YA |
| POST /admin/rtl/{id}/status (`admin.rtl.update_status`, CSRF) | TIDAK | TIDAK | TIDAK | YA |
| GET /admin/dampak (`admin.impact.view`) | TIDAK | TIDAK | TIDAK | YA |
| GET /admin/dampak/{id} (`admin.impact.view`) | TIDAK | TIDAK | TIDAK | YA |
| POST /admin/dampak (`admin.impact.create`, CSRF) | TIDAK | TIDAK | TIDAK | YA |
| GET /portal/aduan/{id} (tiket milik sendiri) | TIDAK | YA | YA | TIDAK |
| GET /pengajuan/diterima | TIDAK | YA | YA | TIDAK |
| GET /admin/pengajuan | TIDAK | TIDAK | TIDAK | YA |
| GET /admin/pengajuan/{id} | TIDAK | TIDAK | TIDAK | YA |
| GET /admin/pengajuan/{id}/berkas/{docId} | TIDAK | TIDAK | TIDAK | YA |
| POST /admin/pengajuan/{id}/status | TIDAK | TIDAK | TIDAK | YA |
| Lacak permohonan dengan kode lewat form | YA | YA | YA | YA |
| Mengakses halaman admin dashboard | TIDAK | TIDAK | TIDAK | YA |
| Mengelola pengguna | TIDAK | TIDAK | TIDAK | YA |
| Membaca audit log sistem | TIDAK | TIDAK | TIDAK | YA |

Catatan implementasi Tahap 2A:
- Role per rute diambil dari `app/Policies/Permissions.php` dan tidak ditulis ulang di `Router.php`.
- `GET /logout` bukan rute legal; rute yang valid hanya `POST /logout`.
- Seluruh POST pengelolaan pengguna wajib memakai token CSRF dan setiap tindakan dicatat di `audit_logs`.
- POST perubahan standar layanan wajib memakai CSRF; semua ADMIN dapat mengubah mode dan standar layanan, dan perubahan mencatat nilai lama serta baru di `audit_logs`.
- Katalog dan detail layanan publik dapat dilihat semua role, termasuk GUEST.
- Pembuatan pengajuan sementara dibatasi pada SISWA/GURU yang login; pilihan role ini MENUNGGU POKJA 6 karena dokumen sumber belum menetapkan role pengaju. Semua POST pengajuan dan perubahan status memakai CSRF.
- Cek status memakai POST /lacak dengan CSRF; halaman formulir dan hasil tidak menyimpan cache dan melarang referrer. Hasil hanya menampilkan status serta tahap; antrean, detail, dan perubahan status hanya untuk ADMIN.
- Pengajuan sukses memakai redirect ke halaman yang menampilkan kode lacak satu kali dari flash sesi; batas awal adalah 5 pengajuan per jam per akun (MENUNGGU POKJA 6).
- Lampiran pengajuan hanya tersedia pada TU-01, TU-02, KUR-02, SAR-01, dan HUM-01; hanya ADMIN dapat mengunduhnya melalui GET /admin/pengajuan/{id}/berkas/{docId}. Halaman lacak dan portal tidak memaparkan lampiran atau nama berkas. KES-02 dan KES-04 menolak lampiran sampai keputusan tertulis Pokja 6.
- ADMIN tidak dapat menonaktifkan atau menurunkan role dirinya sendiri.
- Login "Siswa / Guru" hanya mencari role SISWA atau GURU; login "Admin Pokja 6" hanya mencari role ADMIN. Pesan kegagalan tidak membedakan penyebab.
- Pola parameter didukung Router, tetapi rute contoh yang bukan bagian aplikasi tidak didaftarkan.
- KAHAYAN AMANAH/HUM-02 menerima kiriman Guest, SISWA, dan GURU. Isi dan identitas hanya tampak pada rute ADMIN; cek kode melalui POST tidak memuat isi atau kontak.
- Seluruh POST KAHAYAN memakai CSRF. Kuota awal kiriman adalah 5 per IP per jam; GET/POST lacak memakai kode yang tidak masuk URL dan respons privat memakai no-store/no-referrer.
- Rute status admin mengikuti tabel perpindahan pada `ComplaintWorkflow` dan bagian Langkah 5 di `docs/DECISIONS.md`; riwayat bersifat append-only. Tanggapan publik maksimal 1.000 karakter.
- Rute `/survei` yang sudah ada melayani dua jalur: survei berkode untuk pengajuan berstatus SELESAI (satu kali per pengajuan) dan survei INFORMASI tanpa kode lacak (tidak terverifikasi). Keduanya memakai CSRF, honeypot, dan kuota IP terpisah; jalur INFORMASI dibatasi tiga kiriman per IP per layanan per jam. Jalur INFORMASI hanya dipilih di menu `/survei`, tanpa tautan dari halaman layanan. Tidak ada kemampuan/rute baru; POST tetap memakai `surveys.submit` untuk GUEST, SISWA, GURU, dan ADMIN. Rekap dan ekspor gabungan hanya ADMIN; kedua sumber tetap dilabeli dan direkap terpisah.
- Rute eviden memakai kemampuan `admin.evidence.view`, `admin.evidence.upload`, `admin.evidence.download`, dan `admin.evidence.verify`; keempatnya hanya untuk ADMIN. POST unggah dan verifikasi wajib CSRF. Izin diperiksa di server lewat `Permissions.php`.
- Satu eviden adalah satu berkas untuk satu layanan dan satu checkpoint. Status hanya dapat diputuskan dari `DIUNGGAH`, keputusan final, dan perbaikan dilakukan dengan unggahan baru. Pengunggah dapat memverifikasi sendiri; tampilan menandainya secara eksplisit. Detail dan unduhan hanya tersedia bagi ADMIN.
- GET `/admin/checklist-99` memakai kemampuan `admin.checklist.view` (ADMIN). Checklist membentuk pasangan layanan-checkpoint dari data aktual, menghitung status dari `evidence_items`, dan tidak menyimpan baris sel atau menyediakan rute POST.
- Catatan evaluasi per layanan-periode memakai `admin.evaluations.view` untuk GET daftar/detail dan `admin.evaluations.create` untuk POST (ADMIN saja, CSRF). Data append-only; koreksi dibuat sebagai catatan baru yang merujuk evaluasi sebelumnya pada layanan yang sama. Tidak ada rute edit/hapus, skor, atau persentase.
- RTL MADANI merujuk tepat satu evaluasi 8B. Kemampuan `admin.rtl.view` melindungi kedua GET, `admin.rtl.create` melindungi POST pencatatan, dan `admin.rtl.update_status` melindungi POST perubahan status; semua hanya ADMIN dan kedua POST memakai CSRF. Perubahan status append-only pada riwayat; tidak ada rute edit/hapus RTL atau notifikasi.
- Catatan before/after deskriptif memakai `admin.impact.view` untuk kedua GET dan `admin.impact.create` untuk POST; semua hanya ADMIN dan POST memakai CSRF. Catatan append-only, tanpa rute edit/hapus, metrik, atau klaim dampak terukur.

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
