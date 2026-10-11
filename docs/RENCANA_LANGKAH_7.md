# Rencana Langkah 7 — Eviden dan Checklist 99 Sel

**Tanggal:** 2026-10-11  
**Tahap:** 7A — perencanaan, tanpa implementasi  
**Batas tahap:** hanya dokumen rencana ini yang dibuat. Tidak mengubah kode, skema, basis data, atau konfigurasi.

## 1. Temuan audit baca-saja dan dasar rencana

### Fakta dari dokumen

- `docs/BLUEPRINT.md` §1 menyatakan sistem adalah prototipe dan bukan bukti pemenuhan PMPZI/LKE; seluruh angka harus berasal dari basis data. §4 mewajibkan CSRF dan otorisasi server-side serta melarang penyimpanan data sumber nyata. §6.D menyebut eviden lintas layanan dan checkpoint, checklist sebagai instrumen kendali internal, serta empat status: `VALID`, `ADA_BELUM_VERIFIKASI`, `PERLU_PERBAIKAN`, `KOSONG`. §7 menyebut entitas `evidence_items`, `evidence_checkpoints`, dan `evidence_links`. §8 menempatkan umpan balik pada tahap 7 dan evaluasi/RTL/dampak pada tahap 8. §10 memberi pola `Permissions.php` sebagai satu sumber izin dan `ChecklistService.php` untuk matriks 99 sel. §11 mencantumkan bagian implementasi Tahap 6 (halaman 11–13, data acuan 9 titik) dan Tahap 7 (halaman 10, 14–16).
- `docs/PROMPT_AGENT.md` §1 memperingatkan bahwa 99 sel adalah instrumen kendali internal, bukan pengganti komponen atau indikator resmi LKE ZI. §5 membedakan tahap 7 (umpan balik) dari tahap 8 (evaluasi, RTL, dampak). §6 mencantumkan tabel eviden konseptual. §11 menyebut implementasi Tahap 6 untuk eviden/checklist dan Tahap 7 terpisah untuk MADANI/evaluasi/RTL/laporan. §12 melarang mengarang data atau menyatakan prototipe memenuhi PMPZI/LKE.
- `docs/ERD.md` §2–3 menyebut relasi layanan–evidence links–checkpoints, serta sembilan checkpoint konseptual:
  1. EV-01 Identitas layanan
  2. EV-02 Dokumen persyaratan
  3. EV-03 Proses verifikasi awal
  4. EV-04 Pelaksanaan SOP
  5. EV-05 Hasil layanan
  6. EV-06 Umpan balik pelanggan
  7. EV-07 Evaluasi internal
  8. EV-08 RTL / tindak lanjut
  9. EV-09 Dampak layanan
- **Catatan rujukan:** berkas Markdown tidak mempunyai nomor halaman tercetak atau stabil. Untuk menjaga rujukan dapat diverifikasi, rencana ini menggunakan judul bagian (`§`) di `BLUEPRINT.md`, `PROMPT_AGENT.md`, `ERD.md`, serta nomor halaman yang secara eksplisit dicantumkan pada peta tahapan di `PROMPT_AGENT.md` §11.

### Fakta dari kode aktual

- **Belum ada implementasi eviden/checklist:** pencarian pada `app/` dan `database/migrations/` tidak menemukan implementasi `evidence_*`, checklist, atau MADANI. Entitas di `docs/ERD.md` saat ini masih konseptual; belum ada migrasi yang membuatnya maupun data acuan EV-01 sampai EV-09.
- **Model layanan INFORMASI:** migrasi 004 mendefinisikan `services.mode` sebagai ENUM yang memuat `INFORMASI`, dan `services.is_active` sebagai `TINYINT(1) NOT NULL DEFAULT 1`. Seed migrasi memasukkan tiga layanan bermode INFORMASI: KUR-01, KES-01, dan KES-03. Pada basis data baru, nilai default membuat ketiganya aktif; skrip seed pada konflik kode tidak memperbarui `is_active`, sehingga status saat ini pada basis data yang sudah ada tidak dapat dipastikan dari berkas migrasi saja. Saya tidak membaca config atau mengakses database runtime pada tahap perencanaan. **DECISION REQUIRED:** konfirmasi daftar layanan INFORMASI yang benar-benar aktif sebelum menetapkan pilihan layanan survei atau cakupan eviden.
- Di antarmuka, layanan ditampilkan sebagai katalog/halaman detail; `service_detail.php` menyesuaikan langkah 3–5 untuk layanan bermode INFORMASI. Belum ada menu atau perilaku eviden yang terlihat di implementasi.
- Lampiran pengajuan yang ada di [ApplicationController.php](../app/Controllers/ApplicationController.php) menerima maksimal 3 JPG/PNG, maksimal 5 MiB tiap berkas. Tipe diverifikasi dengan `finfo` dan `getimagesize`; nama simpan acak 64 karakter hex dengan ekstensi terpilih dari MIME. Nama asli dibersihkan dan dipotong.
- Penyimpanan lampiran berada di `storage/private`, di luar direktori web `public`. Controller memeriksa symlink dan `realpath`; unduhan mengambil dokumen berdasarkan pasangan ID lampiran dan ID pengajuan, memvalidasi MIME/nama simpan, lalu membaca file dari controller. Rute unduh lama adalah `GET /admin/pengajuan/{id}/berkas/{docId}`, dibatasi kemampuan `admin.applications.documents.download` yang hanya mengizinkan ADMIN.
- Pengajuan dan lampiran diproses dalam transaksi database. File yang sudah dipindahkan dihapus jika transaksi rollback atau operasi gagal. Ini pola yang dapat ditinjau dan digunakan kembali, bukan alasan untuk memakai tabel `application_documents` bagi eviden.
- [007_application_documents.sql](../database/migrations/007_application_documents.sql) membuat metadata lampiran dengan nama acak unik, MIME terbatas, ukuran, SHA-256, waktu, dan FK ke pengajuan. Tabel tersebut terikat domain pengajuan dan tidak cocok sebagai tabel eviden lintas layanan/checkpoint.
- Migrasi [009_surveys.sql](../database/migrations/009_surveys.sql) dan [010_information_surveys.sql](../database/migrations/010_information_surveys.sql) menjadi contoh migrasi bernomor dan signature. [jalankan-migrasi.php](../bin/jalankan-migrasi.php) memuat deklarasi signature skema per nama migrasi; untuk migrasi baru signature direncanakan sebagai deklarasi data di dalam checker yang sudah ada, tanpa mengubah logika migrator.
- [Permissions.php](../app/Policies/Permissions.php) adalah sumber kemampuan/rute; rute tidak boleh mengandalkan penyembunyian tautan UI. Saat ini hak internal eviden/checklist belum didefinisikan.
- Pemeriksaan direktori saat audit menemukan `storage/private` ada sebagai direktori dan `public/storage` tidak ada. Pemeriksaan ini hanya baca-saja; izin tulis runtime tidak diuji pada tahap perencanaan.

### Interpretasi kerja — belum menjadi keputusan bisnis

- **ASSUMPTION untuk dibahas:** 99 sel dapat dibentuk sebagai 11 layanan katalog × 9 checkpoint EV, sesuai jumlah layanan di `PROMPT_AGENT.md` §5 dan sembilan checkpoint konseptual di ERD. Blueprint menyebut “99 sel”, tetapi tidak mendefinisikan rumus pemetaan, daftar indikator resmi, atau apakah seluruh checkpoint berlaku untuk setiap layanan.
- Empat status pada `BLUEPRINT.md` §6.D diperlakukan sebagai kosakata awal status sel. Makna operasional, kriteria penerimaan, dan siapa yang boleh menetapkan setiap status belum dijelaskan.
- Daftar EV-01–EV-09 pada ERD belum disertai spesifikasi dokumen atau kriteria bukti yang valid. Rencana tidak menetapkan eviden contoh atau mengisi data checklist seolah-olah benar.

## 2. Pembagian cakupan

### Termasuk dalam Langkah 7 — Eviden dan checklist

- Model data yang terpisah untuk definisi checkpoint, metadata berkas eviden, tautan berkas ke layanan/checkpoint, status sel checklist, dan—bila diputuskan—riwayat perubahan/verifikasi.
- Unggah, daftar, detail, verifikasi, dan unduh eviden melalui rute internal terlindungi; penyimpanan di luar webroot.
- Matriks checklist 99 sel yang dihitung dari data layanan dan checkpoint yang disetujui; keadaan kosong harus berasal dari query dan ditampilkan sebagai `KOSONG`, bukan data rekaan.
- Status sel dari himpunan yang disebut Blueprint, dengan perubahan dicatat dan akses ADMIN ditegakkan server-side.
- Audit tindakan yang relevan tanpa mencatat isi berkas atau data sensitif.
- Tes skema, validasi file, akses lintas role, integritas tautan, status checklist, dan seluruh rute baru melalui `public/index.php` asli dalam proses anak.

### Ditunda — bukan bagian rencana implementasi 7B–7F

- MADANI, evaluasi dampak, dashboard kinerja lintas modul, RTL/before-after, dan laporan/ekspor kinerja. Ini berada di `BLUEPRINT.md` §6.E dan §8 tahap 8; `PROMPT_AGENT.md` §11 juga memisahkan MADANI/evaluasi/RTL/laporan sebagai tahap berikutnya.
- Notifikasi, penerusan eviden ke pihak eksternal, publikasi eviden, dan akses publik atau pemohon ke berkas eviden.
- Pengisian 99 sel dengan status selain `KOSONG`, unggah dokumen nyata, atau klaim bahwa eviden memenuhi indikator resmi.
- Pengembangan sub-peran ADMIN. Bila pemilik menghendaki pembagian kewenangan di antara petugas dan verifikator, keputusan dan desain izin harus diselesaikan dahulu.

## 3. Pemakaian ulang dan kebutuhan baru

### Dapat dipakai ulang setelah tinjauan

- **Validasi berkas:** pola pemeriksaan struktur `$_FILES`, status upload, ukuran aktual, MIME dengan `finfo`, pemeriksaan format isi, sanitasi nama asli, dan pembatasan jumlah berkas dari `ApplicationController`. Jangan mengandalkan ekstensi atau `Content-Type` dari browser. Kebijakan JPG/PNG 3 × 5 MiB adalah kebijakan lampiran pengajuan saat ini, **bukan** kebijakan eviden yang telah disetujui.
- **Penyimpanan:** direktori `storage/private`, pembuatan dengan izin terbatas, larangan symlink, pemeriksaan `realpath`, nama penyimpanan acak, dan tidak menaruh berkas di `public/`. Perlu dipastikan jalur deployment web server tidak mempublikasikan direktori privat.
- **Atomisitas:** pola pencatatan metadata dan audit dalam transaksi serta membersihkan file yang telah dipindahkan ketika transaksi gagal. Karena filesystem dan MySQL tidak berbagi transaksi atomik, rancangan harus menangani berkas orphan/record tanpa file, termasuk kegagalan cleanup.
- **Akses berkas:** pola controller untuk memeriksa izin sebelum membaca berkas, memvalidasi ID dan nama acak yang tersimpan, mengikat entitas berkas ke layanan/checkpoint yang diminta, mengirim `Content-Disposition: attachment`, `nosniff`, dan `no-store`, serta menulis audit metadata.
- **Rute dan izin:** pola `Router` + `Permissions.php`, middleware CSRF untuk semua POST, status 404 yang tidak membocorkan objek, autentikasi ADMIN di sisi server, serta tes matriks role × route.
- **Migrasi:** urutan bernomor selanjutnya dimulai dari 011; pola ledger, checksum, parser, dan signature terbaru. Jangan mengubah migrasi 001–010 atau implementasi parser/migrator.

### Harus dirancang baru

- Tabel eviden lintas layanan/checkpoint, constraint dan indeksnya; tabel lampiran pengajuan tidak mewakili relasi ini.
- Definisi sembilan checkpoint yang disepakati dan mekanisme membentuk tepat 99 sel—tanpa mengarang indikator.
- Model status pada level sel, hubungan antara status `ADA_BELUM_VERIFIKASI`/`VALID` dan status pemeriksaan berkas, serta riwayat/aktor yang mengubah status.
- Prosedur verifikasi independen. `docs/DECISIONS.md` telah mencatat kebutuhan dua akun ADMIN yang berbeda untuk verifikasi oleh orang selain pengunggah. Perlu aturan teknis yang menolak verifikasi oleh pengunggah yang sama.
- Kebijakan unggahan eviden, termasuk tipe yang diizinkan, ukuran, jumlah per unggahan, karakteristik dokumen aktif/pasif, retention, penggantian versi, dan perlakuan dokumen berisi data pribadi.
- Rute admin untuk pengelolaan eviden dan checklist serta kemampuan eksplisit di `Permissions.php`.
- Signature skema penuh untuk setiap migrasi baru, termasuk definisi kolom, default/EXTRA, indeks dan unique, FK/aksi referensi, engine, charset/collation, dan trigger jika ada. Signature 009/010 hanya menjadi pola; signature lama tidak boleh diubah.

## 4. Usulan pemecahan bertahap

Semua nomor migrasi, nama kemampuan, dan rute di bawah adalah **usulan**, bukan izin untuk menjalankannya. Sebelum tiap tahap implementasi, keputusan §6 yang berdampak pada tahap tersebut harus dikonfirmasi. Setelah persetujuan implementasi, migrasi hanya diuji pada fixture nonce terisolasi; migrator tidak dijalankan terhadap `humamantap_dev` tanpa persetujuan tersendiri.

| Langkah | Fokus dan migrasi usulan | Rute baru dan kemampuan/role usulan | Tes kunci dan bukti entry point asli |
|---|---|---|---|
| **7B — Definisi checkpoint dan skema eviden** | `011_evidence_foundation.sql`: definisi sembilan checkpoint yang disahkan; metadata `evidence_items`; tabel penghubung eviden ke layanan/checkpoint. Pertimbangkan FK dan indeks yang membuat layanan/checkpoint tidak dapat terhapus jika dipakai. Signature lengkap 011 sebagai data saja. Jangan menambah seed eviden atau mengubah data layanan yang ada. | Belum menambah rute operasional jika tahap ini hanya fondasi skema. Tidak ada kemampuan baru. Bila keputusan menghendaki halaman awal pada tahap yang sama, gunakan kemampuan usulan `admin.evidence.view` — ADMIN. | Migrasi idempoten di fixture; signature mendeteksi drift kolom/index/FK/table; data layanan/checkpoint tepat sesuai keputusan; foreign key menolak referensi yang tidak ada. Bila halaman ditambahkan, GET daftar eviden lewat index asli untuk Guest/SISWA/GURU/ADMIN (ADMIN allowed, role lain redirect/403 sesuai Router). |
| **7C — Unggah dan pengelolaan eviden** | Memakai skema 011; migrasi tambahan hanya jika keputusan memerlukan field versi/retensi atau log aksi persisten, usulan `012_evidence_workflow.sql`. Hindari menambah kolom spekulatif. | `GET /admin/eviden` (`admin.evidence.view`, ADMIN); `GET /admin/eviden/{id}` (`admin.evidence.view`, ADMIN); `POST /admin/eviden` (`admin.evidence.upload`, CSRF, ADMIN); `GET /admin/eviden/{id}/berkas` (`admin.evidence.download`, ADMIN). | File valid dan invalid; MIME palsu; ukuran/jumlah batas; nama/path traversal; symlink; CSRF; rollback DB dan orphan cleanup; Guest/SISWA/GURU tidak dapat membaca/unggah/unduh; audit hanya metadata. Lewat server anak yang menjalankan `public/index.php` asli: upload multipart sah menyimpan tepat satu record/file di fixture storage privat, GET berkas oleh ADMIN berhasil, role lain ditolak, tidak ada respons fatal. |
| **7D — Verifikasi independen dan status eviden** | Gunakan `012_evidence_workflow.sql` bila belum ada pada 7C, atau nomor berikutnya `013_evidence_workflow.sql` bila sudah dipakai. Simpan aktor/waktu verifikasi dan alasan perbaikan bila disetujui; riwayat append-only bila dibutuhkan. Signature hanya untuk migrasi baru. | `POST /admin/eviden/{id}/verifikasi` (`admin.evidence.verify`, CSRF, ADMIN); opsional `POST /admin/eviden/{id}/status` (`admin.evidence.manage`, CSRF, ADMIN) hanya setelah aturan status ditetapkan. | Pengunggah tidak dapat memverifikasi unggahannya sendiri; akun ADMIN kedua dapat memverifikasi; status/reason invalid ditolak; transisi dan audit atomik; penghapusan/ubah riwayat langsung ditolak bila diputuskan append-only. Jalur `public/index.php` asli menguji ADMIN uploader dan ADMIN verifier pada sesi anak terpisah, serta role non-admin mendapat redirect/403. |
| **7E — Checklist 99 sel** | Usulan migrasi `013_checklist_cells.sql` (atau nomor berikutnya jika 013 telah digunakan): satu baris per pasangan layanan/checkpoint, status dari himpunan yang disahkan, kunci unik pasangan, serta actor/timestamp atau tabel riwayat bila keputusan menghendaki. Seeder migrasi hanya boleh membuat definisi struktural/checkpoint yang telah disetujui; jangan mengisi eviden atau status “valid” palsu. Signature lengkap sebagai data. | `GET /admin/checklist-99` (`admin.checklist.view`, ADMIN); `POST /admin/checklist-99/{serviceCode}/{checkpointCode}` (`admin.checklist.update`, CSRF, ADMIN), jika perubahan status lewat POST disepakati. | Tepat 99 pasangan hanya bila pemetaan 11×9 disahkan; status awal seluruh sel `KOSONG`; tidak ada pasangan duplikat; transisi/status tidak valid ditolak; tautan eviden hanya ke layanan/checkpoint terkait; XSS aman; ADMIN-only. Uji GET dan POST aktual melalui `public/index.php` dalam anak, termasuk role guest/siswa/guru, CSRF salah, penyimpanan fixture, dan render 99 sel dari query. |
| **7F — Uji integrasi dan serah terima Langkah 7** | Tidak perlu migrasi baru; hanya migrasi korektif baru bila ditemukan masalah skema dan disetujui. Tidak mengerjakan MADANI/RTL/laporan. | Tidak ada rute baru; menguji semua rute 7B–7E dan tautan dari halaman internal yang disetujui. | Jalankan tes terarah lalu seluruh suite hanya setelah bootstrap fixture ditinjau. Bukti end-to-end melalui anak `public/index.php` asli: admin unggah, admin berbeda verifikasi, status checklist berubah sesuai bukti, GET unduhan terlindungi. Laporkan keluaran tes, `git status`, dan hal yang belum diuji; tidak menyatakan pemenuhan PMPZI/LKE. |

**Catatan izin:** bila hanya ada role global `ADMIN`, kemampuan terpisah seperti `admin.evidence.upload` dan `admin.evidence.verify` tetap memberi role `ADMIN`; pembatasan “bukan pengunggah yang sama” harus diperiksa di server/controller, bukan diasumsikan oleh nama kemampuan. Jika Pokja 6 menginginkan sub-role petugas/verifikator, itu memerlukan keputusan serta rancangan permission terpisah sebelum implementasi.

## 5. Risiko dan kontrol yang direncanakan

| Risiko | Kontrol/pertanyaan desain sebelum implementasi |
|---|---|
| **Tipe berkas palsu atau file berbahaya** | Tetapkan tipe yang diperlukan terlebih dahulu. Verifikasi MIME dan format isi menggunakan parser yang sesuai; ekstensi hanya hasil keputusan server. Batasi byte, jumlah, dan ukuran request; tolak arsip/format aktif kecuali dianalisis dan disetujui. Jangan menganggap JPG/PNG 5 MiB lampiran pengajuan sebagai batas eviden. |
| **Ukuran/DoS penyimpanan** | Putuskan ukuran maksimum per file, jumlah file per unggahan/sel, kuota penyimpanan, serta penanganan request yang melampaui `post_max_size`. Tambahkan batas operasional dan tes batas atas/bawah; kuota aplikasi bukan pengganti pemantauan kapasitas. |
| **Path traversal, symlink, dan akses langsung** | Simpan dengan nama acak server-side; jangan membentuk path dari nama asli atau input. Tolak separator dan symlink, pastikan path resolved tetap berada di `storage/private`, dan layani hanya melalui controller yang memeriksa izin. Verifikasi konfigurasi deployment agar `storage/` tidak menjadi document root/alias publik. |
| **Nama file dan XSS** | Nama asli bersifat metadata tidak tepercaya: normalisasi/potong sesuai aturan, escape saat render, jangan masukkan ke header tanpa sanitasi. Unduhan memakai nama attachment generik dan `nosniff`; jangan menampilkan nama file pada halaman publik. |
| **Privasi/sensitivitas isi** | Tentukan apakah dokumen boleh berisi identitas atau informasi siswa/BK, dasar pengumpulan, siapa yang dapat melihatnya, kebutuhan redaksi, retensi, penghapusan, backup, dan respons insiden. Prinsip Blueprint melarang data nyata dan data pribadi tertentu di prototipe. Audit hanya mencatat aktor, aksi, ID objek, status, tipe/ukuran/hashes seperlunya—bukan isi file atau data sensitif. |
| **Integritas DB dan filesystem** | DDL MySQL dapat implicit commit; migrasi bertahap berurutan dan signature jangan mengubah migrasi lama. Transaksi DB tidak mencakup filesystem: rancang rollback cleanup, orphan detection/prosedur pemulihan, dan uji kegagalan setelah file dipindah sebelum metadata commit serta sebaliknya. Checksum harus berasal dari file aktual yang diterima server. |
| **Integritas verifikasi/status** | Definisikan pemisahan uploader/verifier, status item vs status sel, alasan `PERLU_PERBAIKAN`, mekanisme unggah ulang/versioning, dan riwayat perubahan. Gunakan constraint/transaksi serta pemeriksaan server-side; jangan menganggap UI dapat mencegah perubahan status tidak sah. |
| **Konflik skema / migrasi** | Jangan mengubah 001–010, tabel `application_documents`, `surveys`, atau signature lama. Gunakan nomor mulai 011; cek ledger dan skema fixture; deklarasikan signature lengkap hanya untuk setiap migrasi baru. Sebelum deployment, operator perlu memastikan status database aktual dan backup/pemulihan sesuai prosedur; rencana ini tidak menjalankan migrasi. |
| **Interpretasi angka/kelengkapan 99 sel** | Pastikan jumlah 99, cakupan semua layanan, dan status berasal dari query relasional. Jangan menyajikan checklist sebagai indikator resmi atau menampilkan persentase tanpa definisi penyebut/status yang disepakati. Data kosong tetap `KOSONG`; jangan seed bukti fiktif. |

## 6. Keputusan yang diperlukan sebelum implementasi

### DECISION REQUIRED — pemilik proyek / Pokja 6

1. **Definisi 99 sel:** apakah benar 11 layanan × 9 checkpoint EV-01–EV-09? Apakah seluruh 9 checkpoint berlaku untuk setiap layanan termasuk layanan INFORMASI dan HUM-02? Adakah pemetaan alternatif, indikator, atau titik yang dikecualikan?
2. **Rujukan checkpoint:** apakah sembilan label konseptual dalam `docs/ERD.md` disetujui sebagai checkpoint operasional? Siapa menyediakan kriteria dan contoh bukti yang dianggap cukup untuk masing-masing checkpoint? Tidak boleh mengisi definisi/isi bukti dengan karangan.
3. **Status:** definisi dan kriteria penerimaan `VALID`, `ADA_BELUM_VERIFIKASI`, `PERLU_PERBAIKAN`, `KOSONG`; status mana yang dapat dipilih manual vs dihitung dari relasi eviden; transisi yang sah; apakah alasan wajib saat meminta perbaikan; apakah sel boleh memiliki lebih dari satu eviden.
4. **Pemisahan tugas:** apakah pengunggah harus selalu berbeda dari verifikator? Catatan DECISIONS saat ini menghendaki minimal dua akun ADMIN berbeda untuk pengujian verifikasi independen. Apakah aturan berlaku untuk setiap eviden/sel, dan siapa pengganti bila hanya satu petugas tersedia?
5. **Hak akses:** apakah role `ADMIN` saat ini cukup, dengan kemampuan upload/manage/verify terpisah tetapi role sama, atau diperlukan sub-role/petugas/verifikator? Role eksternal (Guest/SISWA/GURU) diasumsikan tidak memperoleh akses eviden sampai ada keputusan lain.
6. **Cakupan layanan:** layanan mana yang boleh memiliki eviden; apakah semua sebelas layanan masuk checklist; apakah eviden boleh ditautkan lintas layanan atau dipakai ulang pada beberapa checkpoint; apakah bukti untuk KES-02/KES-04 memerlukan pembatasan khusus?
7. **Kebijakan berkas eviden:** format yang diterima (PDF, gambar, atau lainnya), ukuran per file/request, jumlah per sel dan pengajuan, batas storage, metadata/nama asli, checksum, versioning/revisi, dan apakah unggah ulang mengganti atau mempertahankan versi sebelumnya.
8. **Privasi dan retensi:** kategori data yang boleh dimuat, kebutuhan anonimisasi/redaksi, siapa dapat mengunduh, jangka simpan, kapan penghapusan diizinkan, dan kewajiban backup/pemulihan. Pastikan dokumen nyata sensitif tidak dipakai untuk tes.
9. **Audit dan riwayat:** aksi yang wajib dicatat, retensi, apakah perubahan status/evidence link harus memiliki append-only history, alasan perubahan wajib, serta batas metadata yang boleh masuk audit.
10. **Tampilan dan angka:** nama modul/label yang disetujui, apakah dashboard hanya menunjukkan status per sel atau agregat tambahan, rumus agregat jika ada, dan cara menampilkan `KOSONG` tanpa memberi kesan kemajuan/kepatuhan resmi.
11. **Pemulihan berkas orphan:** prosedur operator ketika file tersimpan tetapi metadata gagal, metadata ada tetapi file hilang, atau backup database/filesystem tidak konsisten.
12. **Penerimaan prototipe:** data uji fiktif seperti apa yang disetujui, siapa yang melakukan uji browser, serta kriteria penerimaan akhir Langkah 7 sebelum pekerjaan tahap berikutnya.

### Belum termasuk keputusan Langkah 7

Detail MADANI, evaluasi dampak, RTL/before-after, dan laporan dibawa ke tahap yang memang disetujui untuk itu; tidak diputuskan atau dibangun oleh rencana ini.

## 7. Batas pelaksanaan

- Dokumen ini adalah rencana, bukan persetujuan untuk membuat migrasi atau menyentuh database.
- Langkah berikutnya baru dapat dimulai setelah rencana dan keputusan bisnis yang memblokir disetujui.
- Setiap migrasi yang kelak diusulkan dimulai pada nomor 011, memakai fixture tes terisolasi, dan signature data untuk skema baru saja. Tidak mengubah migrasi lama, parser, ledger, maupun mekanisme fixture.
- Tes file dan akses harus menggunakan file/akun fiktif pada fixture. Uji rute baru harus melewati `public/index.php` asli dalam proses anak; tes unit/controller saja tidak cukup.
- Jangan menampilkan atau menyimpan credential, password/hash, isi berkas, data pribadi nyata, atau detail sensitif dalam laporan dan audit.
- Tidak membuat klaim bahwa aplikasi memenuhi PMPZI, LKE, atau persyaratan layanan resmi.
