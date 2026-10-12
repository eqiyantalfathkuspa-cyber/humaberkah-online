# Serah Terima Prototipe HUMA MANTAP

## 1. Ringkasan dan tujuan

HUMA MANTAP adalah prototipe internal layanan publik dan instrumen kerja Pokja 6. Dokumen ini memisahkan bukti yang dilaporkan pemilik proyek dari cakupan tes otomatis dan hal yang masih menunggu keputusan. Prototipe ini bukan bukti kepatuhan PMPZI/LKE, bukan pernyataan kesiapan produksi, dan tidak membuktikan dampak layanan. Gunakan hanya data uji fiktif; jangan memasukkan data pribadi, identitas siswa, atau informasi BK.

## 2. Status per fitur

Status pengujian di tabel mengikuti pilihan tingkat bukti yang ditetapkan untuk serah terima. Semua hasil browser di bawah adalah **dilaporkan pemilik proyek**; tidak dijalankan ulang dalam penyusunan dokumen ini.

| Fitur | Langkah | Rute utama | Tingkat bukti |
|---|---|---|---|
| Autentikasi dan pengelolaan pengguna | 2A–2B | `/login`, `POST /logout`, `/admin/pengguna` | Tes otomatis + diuji sebagian di browser (login dan logout dilaporkan; pengelolaan pengguna tidak dilaporkan diuji di browser) |
| Katalog layanan | 3 | `/layanan`, `/layanan/{code}` | Tes otomatis saja (belum diuji di browser) |
| Pengajuan layanan dan lampiran | 4 | `/layanan/{code}/ajukan`, `/pengajuan/diterima`, `/admin/pengajuan/{id}/berkas/{docId}` | Tes otomatis + diuji sebagian di browser (pemilik melaporkan lampiran KUR-02 diunggah dan gambar dilihat di admin; cakupan layanan lain tidak dilaporkan) |
| Kode lacak pengajuan | 4 | `/lacak` | Tes otomatis saja (belum diuji di browser) |
| KAHAYAN AMANAH / aduan | 5 | `/kahayan`, `/lacak-aduan`, `/admin/aduan` | Tes otomatis saja (belum diuji di browser) |
| Survei berkode | 6 | `/survei`, `/admin/survei` | Tes otomatis + diuji sebagian di browser (pemilik melaporkan survei berkode, rekap, dan rata-rata tampil; rincian skenario lain tidak dilaporkan) |
| Survei layanan INFORMASI | 6B | `/survei`, `/admin/survei` | Tes otomatis + diuji sebagian di browser (pemilik melaporkan survei INFORMASI dengan kuota 3 per IP per layanan per jam; perilaku tersebut diterima pemilik) |
| Dashboard admin | 6 | `/admin` | Tes otomatis saja (belum diuji di browser; uji tampilan `/admin/survei` tercatat terpisah pada baris survei) |
| Eviden | 7B | `/admin/eviden`, `/admin/eviden/{id}/berkas` | Tes otomatis + diuji sebagian di browser (pemilik melaporkan unggah JPG/PNG, isi unduhan identik, serta penolakan PDF dan berkas palsu) |
| Checklist | 7C | `/admin/checklist-99` | Tes otomatis + diuji sebagian di browser (pemilik melaporkan uji browser setelah migrasi 011; rincian skenario tidak dilaporkan) |
| Evaluasi MADANI | 8B | `/admin/evaluasi`, `/admin/evaluasi/{id}` | Tes otomatis saja (belum diuji di browser menurut laporan yang tersedia; pemilik melaporkan satu catatan tersimpan di basis data kerja, tanpa rincian metode) |
| RTL MADANI | 8C | `/admin/rtl`, `/admin/rtl/{id}`, `POST /admin/rtl/{id}/status` | Tes otomatis saja (belum diuji di browser; pemilik melewati uji tersebut dan melaporkan basis data kerja masih 0 baris) |
| Before/after MADANI | 8D | `/admin/dampak`, `/admin/dampak/{id}` | Tes otomatis saja (belum diuji di browser; pemilik melewati uji tersebut dan melaporkan basis data kerja masih 0 baris) |
| Dashboard dan laporan MADANI | 8E | `/admin/madani`, `/admin/madani/ekspor` | Tes otomatis saja (belum diuji di browser) |

**Dilaporkan pemilik proyek:** pada mesin pemilik, HEAD `8139fe1`, `php tests/run.php` menghasilkan 265 `[LOLOS]`, 0 `[GAGAL]`, exit code 0. Angka tersebut adalah hasil yang dilaporkan pemilik, bukan pengukuran yang dijalankan saat menyusun dokumen ini.

## 3. Peta rute dan izin

Role di bawah diturunkan dari ability pada `app/Core/Router.php` dan `app/Policies/Permissions.php`. Semua POST yang didaftarkan Router memakai middleware CSRF. `Semua` berarti GUEST, SISWA, GURU, dan ADMIN. Otorisasi tetap diperiksa server-side; navigasi antarmuka bukan pengganti izin. Daftar ini menyajikan rute aplikasi yang terdaftar, bukan rute hasil fallback 404/405.

| Metode | Path | Kemampuan | Role |
|---|---|---|---|
| GET | `/` | `home.public` | Semua |
| GET | `/login` | `login` | Semua |
| POST | `/login` | `login` (CSRF) | Semua |
| POST | `/logout` | `logout` (CSRF) | SISWA, GURU, ADMIN |
| GET | `/layanan` | `services.catalog` | Semua |
| GET | `/layanan/{code}` | `services.detail` | Semua |
| GET | `/layanan/{code}/ajukan` | `applications.submit` | SISWA, GURU |
| POST | `/layanan/{code}/ajukan` | `applications.submit` (CSRF) | SISWA, GURU |
| GET | `/pengajuan/diterima` | `applications.submit` | SISWA, GURU |
| GET | `/lacak` | `applications.track` | Semua |
| POST | `/lacak` | `applications.track.lookup` (CSRF) | Semua |
| GET | `/survei` | `surveys.submit` | Semua |
| POST | `/survei` | `surveys.submit` (CSRF) | Semua |
| GET | `/survei/terima` | `surveys.submit` | Semua |
| GET | `/admin` | `admin.dashboard` | ADMIN |
| GET | `/admin/pengguna` | `admin.users.manage` | ADMIN |
| POST | `/admin/pengguna` | `admin.users.create` (CSRF) | ADMIN |
| POST | `/admin/pengguna/{id}/role` | `admin.users.role` (CSRF) | ADMIN |
| POST | `/admin/pengguna/{id}/nonaktif` | `admin.users.deactivate` (CSRF) | ADMIN |
| POST | `/admin/pengguna/{id}/reset-sandi` | `admin.users.reset_password` (CSRF) | ADMIN |
| GET | `/admin/audit` | `admin.audit.view` | ADMIN |
| GET | `/admin/layanan` | `admin.services.manage` | ADMIN |
| GET | `/admin/layanan/{code}/ubah` | `admin.services.edit` | ADMIN |
| POST | `/admin/layanan/{code}` | `admin.services.update` (CSRF) | ADMIN |
| GET | `/admin/pengajuan` | `admin.applications.manage` | ADMIN |
| GET | `/admin/pengajuan/{id}` | `admin.applications.detail` | ADMIN |
| GET | `/admin/pengajuan/{id}/berkas/{docId}` | `admin.applications.documents.download` | ADMIN |
| POST | `/admin/pengajuan/{id}/status` | `admin.applications.status` (CSRF) | ADMIN |
| GET | `/kahayan` | `complaints.submit` | GUEST, SISWA, GURU |
| POST | `/kahayan` | `complaints.submit` (CSRF) | GUEST, SISWA, GURU |
| GET | `/kahayan/diterima` | `complaints.submit` | GUEST, SISWA, GURU |
| GET | `/lacak-aduan` | `complaints.track` | Semua |
| POST | `/lacak-aduan` | `complaints.track.lookup` (CSRF) | Semua |
| GET | `/portal` | `portal.view` | SISWA, GURU |
| GET | `/portal/aduan/{id}` | `portal.complaints.view` | SISWA, GURU |
| GET | `/admin/aduan` | `admin.complaints.manage` | ADMIN |
| GET | `/admin/aduan/{id}` | `admin.complaints.detail` | ADMIN |
| POST | `/admin/aduan/{id}/status` | `admin.complaints.status` (CSRF) | ADMIN |
| POST | `/admin/aduan/{id}/tanggapan` | `admin.complaints.response` (CSRF) | ADMIN |
| GET | `/admin/survei` | `admin.surveys.view` | ADMIN |
| GET | `/admin/survei/ekspor` | `admin.surveys.export` | ADMIN |
| GET | `/admin/eviden` | `admin.evidence.view` | ADMIN |
| GET | `/admin/eviden/{id}` | `admin.evidence.view` | ADMIN |
| GET | `/admin/eviden/{id}/berkas` | `admin.evidence.download` | ADMIN |
| POST | `/admin/eviden` | `admin.evidence.upload` (CSRF) | ADMIN |
| POST | `/admin/eviden/{id}/verifikasi` | `admin.evidence.verify` (CSRF) | ADMIN |
| GET | `/admin/checklist-99` | `admin.checklist.view` | ADMIN |
| GET | `/admin/evaluasi` | `admin.evaluations.view` | ADMIN |
| GET | `/admin/evaluasi/{id}` | `admin.evaluations.view` | ADMIN |
| POST | `/admin/evaluasi` | `admin.evaluations.create` (CSRF) | ADMIN |
| GET | `/admin/rtl` | `admin.rtl.view` | ADMIN |
| GET | `/admin/rtl/{id}` | `admin.rtl.view` | ADMIN |
| POST | `/admin/rtl` | `admin.rtl.create` (CSRF) | ADMIN |
| POST | `/admin/rtl/{id}/status` | `admin.rtl.update_status` (CSRF) | ADMIN |
| GET | `/admin/dampak` | `admin.impact.view` | ADMIN |
| GET | `/admin/dampak/{id}` | `admin.impact.view` | ADMIN |
| POST | `/admin/dampak` | `admin.impact.create` (CSRF) | ADMIN |
| GET | `/admin/madani` | `admin.madani.view` | ADMIN |
| GET | `/admin/madani/ekspor` | `admin.madani.export` | ADMIN |

## 4. Skema dan migrasi

Migrasi yang tersedia dan urutannya di `database/migrations/`:

| Migrasi | Tujuan |
|---|---|
| `001_schema_init.sql` | Membuat ledger `schema_migrations` dan penghitung `id_counters`. |
| `002_auth_schema.sql` | Membuat role, pengguna, percobaan login, dan audit log beserta proteksi append-only audit. |
| `003_user_session_revocation.sql` | Menambahkan token pencabutan sesi pada pengguna. |
| `004_service_catalog.sql` | Membuat kategori, katalog layanan, tahapan dan standar layanan, serta referensi katalog. |
| `005_applications.sql` | Membuat pengajuan layanan dan riwayat status pengajuan. |
| `006_application_tracking_attempts.sql` | Membuat pencatatan percobaan pelacakan pengajuan. |
| `007_application_documents.sql` | Membuat metadata lampiran yang terikat pada pengajuan. |
| `008_complaints.sql` | Membuat aduan, riwayat aduan, dan kuota berbasis IP. |
| `009_surveys.sql` | Membuat survei berkode untuk pengajuan dan kuota IP survei. |
| `010_information_surveys.sql` | Membuat survei layanan INFORMASI dan kuota IP per layanan. |
| `011_evidence_foundation.sql` | Membuat checkpoint dan metadata eviden. |
| `012_madani_evaluations.sql` | Membuat catatan evaluasi MADANI. |
| `013_madani_rtl.sql` | Membuat RTL MADANI dan log riwayat statusnya. |
| `014_madani_before_after.sql` | Membuat catatan before/after MADANI deskriptif. |

**Aturan perubahan skema:** jangan mengedit migrasi yang sudah ada atau signature historisnya. Perubahan skema setelah 014 harus diajukan sebagai migrasi bernomor baru dan signature untuk objek baru, setelah keputusan yang relevan disetujui.

**Urutan operasi yang direkomendasikan untuk operator (belum dijalankan dalam penyusunan dokumen ini):**
1. Pastikan sasaran database benar dan buat cadangan yang dapat dipulihkan.
2. Jalankan `php bin/jalankan-migrasi.php` pada lingkungan yang disetujui. Tanpa opsi direktori, migrator memakai direktori migrasi proyek sebagai default; jangan menganggap perintah ini aman untuk database tertentu tanpa memeriksa konfigurasi lingkungan dan sasaran koneksi terlebih dahulu.
3. Verifikasi ledger dan skema pada database yang sama, misalnya dengan `SHOW TABLES` lalu `SHOW CREATE TABLE <nama_tabel>;`. Jangan menyalin keluaran yang mengandung rahasia konfigurasi.
4. Catat migrasi, database sasaran, hasil verifikasi, dan lokasi cadangan di log operasional.

**Pelajaran operasional:** menurut laporan pemilik, tes fixture hijau sementara migrasi 013/014 masih tertinggal di database kerja. Ini menunjukkan tes fixture tidak membuktikan keadaan database kerja. Pemilik melaporkan bahwa migrasi 001–014 telah diterapkan pada `humamantap_dev`, 013 dan 014 dikonfirmasi melalui `SHOW TABLES`, dan empat tabel MADANI ada. Itu adalah fakta yang dilaporkan pemilik, bukan verifikasi independen pada sesi ini. Cadangan sebelum migrasi dilaporkan disimpan di luar folder proyek.

## 5. Cara menjalankan dan menguji

Jalankan server lokal dari root proyek:

```powershell
php -S localhost:8000 -t public
```

Suite otomatis:

```powershell
$output = & php tests/run.php 2>&1
$exitCode = $LASTEXITCODE
$passed = @($output | Select-String '^\[LOLOS\]').Count
$failed = @($output | Select-String '^\[GAGAL\]').Count
$output
"[LOLOS]: $passed"
"[GAGAL]: $failed"
"exit code: $exitCode"
```

Simpan `$LASTEXITCODE` segera sesudah proses berakhir; jangan memakai `$?` sebagai kode keluar proses. Jumlah harus dihitung dari keluaran proses yang benar-benar dijalankan. Suite memerlukan beberapa menit menurut informasi pemilik; jangan menjalankan beberapa suite sekaligus.

`tests/run.php` memuat bootstrap, parser SQL, lalu fase `phase2b.php`, `phase5.php`, `phase6.php`, `phase6b.php`, `phase7c.php`, `phase7b.php`, `phase8b.php`, `phase8c.php`, `phase8d.php`, dan `phase-post-audit.php`. Bootstrap membuat database fixture unik bernama berawalan `humamantap_test_` dengan token kepemilikan, menjalankan pengujian terhadap fixture itu, dan mendaftarkan pembersihan saat shutdown. Pembersihan memverifikasi token sebelum menghapus database fixture; bila kepemilikan tidak dapat dibuktikan, database dibiarkan utuh. Tes tidak boleh diarahkan ke `humamantap_dev`.

**Jika proses tes terputus:** pembersihan shutdown mungkin tidak sempat berjalan. Jangan menghapus database berdasarkan tebakan atau menghapus `humamantap_test` bersama. Identifikasi fixture nonce yang tertinggal dan bukti token kepemilikannya sesuai prosedur bootstrap terlebih dahulu; bila kepemilikan tidak bisa dibuktikan, biarkan database utuh dan minta operator melakukan rekonsiliasi. Jangan menjalankan ulang tes bertumpuk.

## 6. Keputusan sementara yang menunggu Pokja 6

Butir berikut merangkum `docs/DECISIONS.md`; semuanya tetap berstatus sementara/menunggu konfirmasi bila dinyatakan demikian di log keputusan. Perubahan kebijakan yang memengaruhi skema memerlukan migrasi baru, bukan perubahan migrasi lama.

| Modul | Nilai sementara yang dipakai | Dampak bila keputusan diubah |
|---|---|---|
| Survei | Tiga aspek KECEPATAN, KEJELASAN, KERAMAHAN skala 1–5; saran opsional maksimal 1.000 karakter; label dan rekap survei INFORMASI dipisah dari survei berkode; survei INFORMASI dibatasi tiga kiriman per IP per layanan per jam; ambang lima responden dilaporkan dalam keputusan. | Perubahan pertanyaan, skala, batas, kuota, atau pemisahan sumber dapat memerlukan validasi aplikasi dan migrasi korektif bila tipe/struktur penyimpanan berubah. |
| Eviden dan checklist | Rencana sementara 11 layanan × 9 checkpoint EV-01–EV-09; status eviden DIUNGGAH, TERVERIFIKASI, PERLU_PERBAIKAN; maksimal tiga gambar JPG/PNG per unggahan, maksimal 5 MiB per gambar; checklist dihitung dari pasangan layanan-checkpoint aktual dan prioritas status, bukan disimpan. Kriteria bukti per checkpoint belum ditetapkan. | Perubahan daftar checkpoint, status, relasi, atau metadata dapat memerlukan migrasi baru; perubahan aturan unggah/retensi perlu perubahan aplikasi dan prosedur penyimpanan. |
| Evaluasi | Catatan teks per layanan dan periode, ringkasan wajib, temuan/kesimpulan opsional; append-only, koreksi sebagai catatan baru pada layanan sama; tidak ada skor atau persentase; ADMIN saja. | Perubahan bentuk data, unit, periode, koreksi, atau kriteria dapat memerlukan migrasi korektif baru serta perubahan validasi/tampilan. |
| RTL | RTL sementara terkait satu evaluasi; uraian, penanggung jawab fiktif, dan tenggat wajib; status DIRENCANAKAN → BERJALAN → SELESAI (dengan transisi langsung DIRENCANAKAN → SELESAI); riwayat status append-only; ADMIN saja; tidak ada notifikasi. | Perubahan relasi, alur status, PIC, atau riwayat memerlukan perubahan aturan; perubahan kolom/relasi memerlukan migrasi baru. |
| Before/after | Catatan naratif per layanan: judul, teks dan tanggal sebelum/sesudah, sumber wajib; RTL opsional tetapi bila diisi harus selesai dan untuk layanan sama; tanggal sebelum ≤ sesudah dan tanggal sesudah tidak di masa depan; koreksi append-only pada layanan sama; tanpa angka, skor, persentase, atau perhitungan selisih; ADMIN saja. | Perubahan definisi narasi/pengukuran, sumber, hubungan RTL, atau koreksi dapat memerlukan migrasi korektif dan persetujuan sebelum implementasi. |
| Lain-lain | Pemisahan tugas eviden awalnya meminta dua akun ADMIN untuk membuktikan pemeriksaan selain pengunggah; implementasi saat ini memperbolehkan pengunggah memverifikasi dirinya sendiri dan menandainya. Kebijakan identitas/SLA/kategori aduan dan unit tindak lanjut, sub-role admin, kebijakan layanan tertentu, serta definisi metrik/laporan belum seluruhnya final. | Perubahan kebijakan role atau alur server memerlukan revisi izin, kode dan tes; perubahan skema memerlukan migrasi baru. |

Isi halaman blueprint 10, 14, 15, dan 16 belum diverifikasi terhadap implementasi. Keputusan yang dicatat sementara oleh pemilik proyek bukan keputusan resmi Pokja 6 sampai dikonfirmasi.

## 7. Keterbatasan yang diketahui

- Berkas eviden dapat menjadi orphan bila proses mati setelah berkas dipindahkan ke storage tetapi sebelum transaksi database commit. Prosedur rekonsiliasi menunggu Pokja 6.
- Penanda “dikoreksi oleh” menggunakan `GROUP_CONCAT`; hasilnya dapat terpotong bila banyak koreksi melampaui `group_concat_max_len` MySQL.
- Append-only pada catatan MADANI ditegakkan aplikasi dan ketiadaan rute edit/hapus, bukan oleh database. Ini bukan jaminan terhadap akun database berhak tinggi atau operasi langsung.
- Batas `upload_max_filesize`, `post_max_size`, dan `max_file_uploads` pada PHP-FPM/web server perlu diperiksa terpisah dari nilai PHP CLI.
- Zona waktu PHP server memengaruhi validasi tanggal masa depan before/after.
- Kuota dan lockout berbasis alamat IP dapat berbagi dampak pada pengguna di balik NAT atau Wi-Fi bersama. Untuk survei INFORMASI, keputusan sementara membatasi per IP per layanan per jam; limiter lain memiliki batas sesuai modul masing-masing.
- Sistem tidak mengirim notifikasi untuk alur KAHAYAN AMANAH atau RTL menurut keputusan yang tercatat.
- Tidak ada skor/persentase atau laporan MADANI. Dashboard/laporan MADANI Langkah 8E ditunda sampai Pokja 6 menyetujui definisi metrik dan kebutuhan pelaporan.
- Temuan audit sebelumnya mencatat kemungkinan file orphan serta keterbatasan `GROUP_CONCAT`; catatan dan arahan deployment terkait ada di `docs/DECISIONS.md`. Penyebutan keterbatasan tidak berarti sistem aman atau siap produksi.

## 8. Daftar periksa persiapan deployment

Daftar ini adalah pekerjaan yang perlu diperiksa, bukan pernyataan bahwa pekerjaan telah dilakukan.

- [ ] Buat akun database khusus aplikasi dengan hak minimum; jangan berikan `UPDATE`/`DELETE` pada tabel append-only sesuai kebutuhan operasional.
- [ ] Tinjau `php.ini` produksi: `display_errors=Off`, pencatatan galat yang terlindungi, `upload_max_filesize`, `post_max_size`, dan `max_file_uploads`.
- [ ] Terapkan HTTPS dan periksa atribut cookie sesi aman pada lingkungan TLS.
- [ ] Tetapkan document root hanya ke `public/`; pastikan `storage/` tidak dapat diakses melalui URL.
- [ ] Batasi izin tulis folder `storage/private/` ke proses aplikasi yang diperlukan.
- [ ] Jadwalkan cadangan dan uji pemulihan cadangan.
- [ ] Rotasi dan lindungi `config/config.php`; jangan menyalin nilainya ke laporan atau repositori.
- [ ] Buat akun admin awal dengan sandi kuat melalui `bin/buat-admin.php` sesuai dukungan terminal yang aman.
- [ ] Hapus akun uji dan data uji hanya setelah identifikasi dan persetujuan operator; jangan menghapus data tanpa verifikasi.
- [ ] Tetapkan dan konsistenkan zona waktu server PHP, database, dan prosedur operasi.

## 9. Pekerjaan yang belum dilakukan dan rekomendasi urutan

1. Lakukan uji browser RTL dan before/after setelah persetujuan pemilik; saat ini keduanya dilaporkan belum diuji di browser dan dilewati.
2. Minta konfirmasi Pokja 6 atas keputusan sementara seluruh modul, khususnya definisi/isi blueprint halaman 10, 14, 15, dan 16 sebelum membangun metrik atau laporan.
3. Tunda Langkah 8E (dashboard dan laporan MADANI) sampai definisi metrik, sumber, penyebut, periode, dan klaim yang diperbolehkan disetujui.
4. Rapikan kerapuhan tes yang diketahui pada `tests/phase7c.php`: ketergantungan pada urutan `require` dan asumsi jumlah eviden nol. Jangan mengubah assertion lama tanpa persetujuan cakupan tes tersendiri.
5. Selaraskan format `docs/MATRIKS_IZIN.md` dengan inventaris rute literal. Perbandingan Router/Permissions dengan matriks tidak menemukan perbedaan role yang teridentifikasi pada rute terdaftar, tetapi beberapa baris matriks masih berupa nama fungsi umum (contohnya “Login ke akun”, “Portal pengguna”, “Dashboard admin”, dan “Membaca audit log sistem”) alih-alih metode dan path literal; bagian bawah juga mengulang beberapa kemampuan sebagai baris umum. Ini adalah temuan kelengkapan/pemetaan dokumentasi, bukan temuan perubahan izin yang diterapkan.
6. Selesaikan daftar periksa deployment, tinjau risiko audit yang masih berlaku, dan lakukan validasi operasional terpisah sebelum penggunaan di lingkungan lain. Dokumen ini tidak menyatakan deployment telah disiapkan.

## 10. Lampiran

### Riwayat commit

Daftar berikut adalah `git log --oneline` yang dibaca saat menyusun dokumen; uraian mengikuti subjek commit.

| Commit | Uraian |
|---|---|
| `8139fe1` | Perbaikan pasca-audit 7B–8D: no-store jalur galat, validasi rentang ID, catatan keterbatasan. |
| `d763deb` | Langkah 8D: before/after MADANI, migrasi 014 dan catatan deskriptif append-only. |
| `82d1d21` | Langkah 8C: RTL MADANI, migrasi 013 dan riwayat status append-only. |
| `0385c73` | Langkah 8B: evaluasi MADANI, migrasi 012 dan catatan append-only tanpa skor. |
| `3d22043` | Langkah 7C: checklist eviden berbasis status eviden, hanya baca. |
| `f6259d8` | Langkah 7B: eviden, migrasi 011, unggah, unduh, dan verifikasi. |
| `7fbb830` | Langkah 6B: migrasi 010 dan perapihan berkas. |
| `e317687` | Langkah 6: survei kepuasan, dashboard admin, signature 009, dan handler galat global. |
| `45a688f` | Langkah 5: KAHAYAN AMANAH dan autoloader bersama. |
| `52e5197` | Langkah 4: pengajuan layanan dengan lampiran JPG/PNG. |
| `037a90b` | Tahap 2–3: autentikasi, katalog layanan, dan admin layanan. |
| `7d106b0` | Initial scaffold for HUMA MANTAP. |

### Berkas dokumentasi dan fungsi

| Berkas | Fungsi |
|---|---|
| `docs/PROMPT_AGENT.md` | Prinsip, batas kerja, role, keamanan, dan tahapan prototipe. |
| `docs/BLUEPRINT.md` | Tujuan, arsitektur, role, prinsip data, dan model domain acuan. |
| `docs/DECISIONS.md` | Log keputusan implementasi, keputusan sementara, serta keterbatasan yang diketahui. |
| `docs/MATRIKS_IZIN.md` | Matriks akses role dan catatan penerapan izin per rute/modul. |
| `docs/ERD.md` | Relasi dan ringkasan entitas skema konseptual serta catatan skema aktual yang telah ditambahkan. |
| `docs/RENCANA_LANGKAH_7.md` | Rencana langkah eviden dan checklist beserta batas cakupan serta keputusan yang diperlukan. |
| `docs/RENCANA_LANGKAH_8.md` | Rencana MADANI, pemisahan langkah 8B–8E, dan keputusan yang diperlukan sebelum pelaporan. |
| `docs/RENCANA_TES.md` | Rencana skenario uji; cuplikan yang tersedia memuat uji migrasi, `id_counters`, dan keadaan kosong. |
| `docs/SERAH_TERIMA.md` | Ringkasan serah terima, bukti pemilik, peta izin, migrasi, keterbatasan, dan pekerjaan lanjutan. |
