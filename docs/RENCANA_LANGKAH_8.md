# Rencana Langkah 8 — MADANI, Evaluasi, RTL, Dampak, dan Laporan

Dokumen ini adalah rencana implementasi, bukan persetujuan untuk mengubah aplikasi atau skema. Cakupan Langkah 8 di sini mengikuti permintaan proyek setelah Langkah 7C; `docs/PROMPT_AGENT.md` dan `docs/BLUEPRINT.md` menyebut modul MADANI sebagai Tahap 7 dan Tahap 8 sebagai pengerasan. Penamaan itu perlu dipertahankan agar nomor langkah internal tidak dianggap sebagai perubahan urutan tahap sumber.

## 1. Persyaratan sumber dan batas yang diketahui

### FACT — yang dinyatakan dokumen

- `docs/BLUEPRINT.md` §1: sistem adalah prototipe, bukan bukti pemenuhan PMPZI/LKE. Seluruh angka dashboard berasal dari query basis data; bila tidak ada data, tampilkan `BELUM ADA DATA`.
- `docs/BLUEPRINT.md` §3–4: ADMIN memiliki akses modul internal; pemeriksaan izin dilakukan server-side, CSRF wajib, data sumber nyata dilarang, dan informasi pribadi/BK tidak disimpan.
- `docs/BLUEPRINT.md` §6.E dan §8 tahap 8: MADANI mencakup evaluasi dampak, RTL, dan dashboard kinerja berbasis database; alur layanan memiliki tahap 8 “Evaluasi, RTL, dampak (MADANI)”.
- `docs/PROMPT_AGENT.md` §1 dan §5: evaluasi, RTL, dampak, dan pelaporan adalah tujuan MADANI; prototipe tidak boleh mengaku sudah menjalankan layanan nyata atau memenuhi PMPZI. §11 menyebut Tahap 7 sebagai MADANI/evaluasi/RTL/laporan pada halaman 10, 14, 15, dan 16.
- `docs/PROMPT_AGENT.md` §2 dan `docs/BLUEPRINT.md` §11: jangan mengarang angka/data, pekerjaan harus diselesaikan per tahap dengan bukti, dan laporan akhir tahap menyertakan berkas, perintah/keluaran tes, hal yang belum diuji, serta keputusan/data yang belum tersedia.
- `docs/ERD.md` §1–3 mencantumkan `evaluations`, `follow_up_actions`, dan `before_after` secara konseptual. Kolom yang tercatat saat ini hanya `evaluations(application_id, evaluator_id, score, notes, created_at)`, `follow_up_actions(complaint_id, action_type, action_note, actor_id, created_at)`, dan `before_after(service_id, title, before_text, after_text, created_by, created_at)`.
- `docs/RENCANA_LANGKAH_7.md` §6 “Belum termasuk keputusan Langkah 7” secara eksplisit menunda MADANI, evaluasi dampak, RTL/before-after, dan laporan. Daftar keputusan Langkah 7 juga meminta penetapan pemilik/PIC, audit/riwayat, tampilan/angka, privasi, serta penerimaan sebelum cakupan itu dimulai.
- Sumber blueprint hanya memberi rujukan halaman 10, 14, 15, dan 16; dokumen teknis yang dibaca tidak menjabarkan formulir, indikator, rumus, periode, atau kriteria penerimaan pada halaman-halaman tersebut.

### FACT — keadaan implementasi yang diperiksa

- `database/migrations/009_surveys.sql`, `010_information_surveys.sql`, dan `011_evidence_foundation.sql` membentuk pola migrasi bernomor, FK yang eksplisit, indeks, InnoDB/utf8mb4, seed idempoten untuk referensi, dan data signature migrator. Migrasi 011 adalah migrasi terbaru yang ada dalam cakupan pemeriksaan.
- Pencarian pada `database/migrations/` tidak menemukan pembuatan tabel evaluasi, RTL, atau before-after. Tabel-tabel tersebut di ERD belum merupakan skema yang tersedia dari migrasi tersebut.
- `ChecklistService::matrix()` memberi contoh query agregat satu kali untuk seluruh pasangan layanan-checkpoint. `ChecklistService::statusFromCounts()` menurunkan status dari jumlah, bukan menyimpan status matriks.
- `EvidenceController` membatasi akses di server, memvalidasi parameter terhadap data database, memakai audit metadata, dan menerapkan pola berkas privat pada alur eviden. Ini dapat menjadi referensi arsitektural, bukan pembenaran untuk menyalin kebijakan upload ke MADANI.
- `SurveyController::adminIndex()` memisahkan rekap survei berkode dan tanpa verifikasi; `export()` mengekspor sumber secara eksplisit dan `csvCell()` menetralkan awalan formula `=`, `+`, `-`, dan `@`. Saran dan rata-rata antarsumber tidak digabung.
- `AdminController::dashboard()` mengisi metrik melalui query; hitungan baris aktual dapat bernilai 0, sedangkan rata-rata tanpa responden ditampilkan `BELUM ADA DATA`. View meng-escape keluaran.
- `Router` mendaftarkan handler dan ability; `Permissions::MAP` adalah sumber role tunggal. Pola saat ini untuk halaman internal dan ekspor membatasi akses ke ADMIN.
- ERD dan daftar migrasi berbeda tingkat kematangannya: entitas MADANI di ERD masih konseptual, bukan bukti adanya kolom/tabel atau aturan proses yang telah disepakati.

### DECISION REQUIRED

1. Makna operasional “evaluasi”, “dampak”, “RTL”, dan “laporan” pada rujukan halaman 10/14/15/16, termasuk siapa pengguna, pemilik data, dan kriteria penerimaan.
2. Unit evaluasi dan kaitannya: per pengajuan, per layanan, per periode, per pengaduan, atau kombinasi. ERD saat ini menghubungkan evaluasi dengan `application_id`, sedangkan target MADANI tampak berorientasi layanan; keduanya tidak boleh disatukan tanpa keputusan.
3. Indikator, skala, rumus, sumber pengukuran, pembilang/penyebut, rentang waktu, penanganan data hilang, serta ukuran sampel. Tidak ada nilai atau rumus yang dapat disimpulkan dari blueprint.
4. Apakah RTL merupakan tindak lanjut atas evaluasi, tindak lanjut pengaduan, atau keduanya. ERD saat ini menghubungkan `follow_up_actions` ke `complaints`, yang belum membuktikan bahwa tabel itu tepat untuk RTL MADANI.
5. Kriteria before/after: metrik numerik atau narasi, sumber nilai awal/akhir, tanggal/periode pengukuran, bukti pendukung, siapa yang memvalidasi, dan apakah perubahan boleh dikoreksi.
6. Peran dan pemisahan tugas. Rencana sementara mengusulkan hanya ADMIN server-side, mengikuti `Permissions.php`; sub-role atau verifikator khusus belum ditetapkan.
7. Data yang boleh dicatat, retensi, koreksi/penghapusan, persetujuan/pengesahan data, serta batas konten laporan dan unduhan.
8. Apakah “laporan” adalah laporan yang dirender/diunduh aplikasi, laporan akhir pekerjaan implementasi, atau keduanya. Persyaratan laporan akhir tahap dalam prompt agent tidak otomatis berarti aplikasi harus menyimpan laporan atau membuat snapshot.

## 2. Pemakaian ulang dan pekerjaan baru

### Dapat dipakai ulang sebagai pola

- Query dan render metrik dashboard dari `AdminController`, dengan nilai kosong yang dibedakan dari nol yang terukur.
- Pola satu-query agregat dari `ChecklistService`; rekap satu query/grouping lebih baik daripada query per baris atau sel.
- Pemisahan sumber data dan penamaan sumber dari `SurveyController`; jangan mencampur sumber atau menyembunyikan status verifikasinya.
- Perlindungan CSV formula injection `SurveyController::csvCell()`, prepared statements, escaping HTML, respons privat/no-store untuk data internal, serta audit tanpa data sensitif.
- Pemeriksaan akses server-side dan pemetaan role/ability tunggal pada `Permissions.php` bersama deklarasi handler di `Router`.
- Pola migrasi/signature terbaru 009–011: migrasi tambahan bernomor, signature skema baru sebagai data, constraint dan indeks yang sesuai, verifikasi fixture terisolasi. Jangan memodifikasi signature atau migrasi lama.
- Dari `EvidenceController`: validasi otorisasi, transisi yang dibatasi, query terparameterisasi, dan audit metadata. Tidak ada unggahan berkas yang diminta untuk fitur MADANI ini; jangan menambahkan unggahan/tautan eviden baru tanpa keputusan tersendiri.

### Harus dirancang baru setelah keputusan

- Model domain yang memastikan evaluasi terhubung ke unit dan periode yang benar, bukan sekadar menambahkan skor.
- Siklus/status, penanggung jawab, tenggat, penyelesaian, dan riwayat append-only RTL jika alur tersebut disetujui.
- Pengukuran before/after yang memiliki provenance: sumber, waktu, definisi indikator, nilai awal/akhir, serta siapa yang memasukkan/mengesahkan data.
- Query pelaporan yang memakai definisi, periode, penyebut, pengecualian, dan label yang disetujui; ekspor membutuhkan batas kolom dan kebijakan privasi tersendiri.
- Skema, indeks, FK, batas data, aturan audit, tampilan, rute, ability, serta pengujian domain yang belum ada.

## 3. Usulan pemecahan langkah kecil

Urutan berikut adalah rekomendasi dan bergantung pada penyelesaian keputusan yang memblokir. Nomor migrasi berikutnya dimulai dari 012. Nomor dipakai hanya bila langkah benar-benar memerlukan skema baru; langkah tanpa skema tidak membuat migrasi kosong. Nama tabel/kolom di bawah bersifat usulan dan tidak boleh dianggap final sebelum keputusan bisnis terkait disetujui.

### 8B — Fondasi evaluasi

- **Prasyarat keputusan:** tetapkan unit evaluasi, hubungan ke aplikasi/layanan/periode, evaluator, kriteria/indikator, skala, sumber dan validitas data, kebijakan koreksi, serta status/approval. Tanpa itu, pembuatan skor atau kolom evaluasi diblokir.
- **Migrasi:** usulan `012_madani_evaluations.sql` untuk skema evaluasi yang telah disetujui. Jangan langsung menerapkan kolom konseptual ERD sebagai kontrak karena `application_id` saja mungkin tidak merepresentasikan evaluasi per layanan/periode. Tambahkan signature lengkap untuk objek baru sebagai data migrator; jangan mengubah 001–011 atau logika migrator.
- **Rute usulan:** `GET /admin/evaluasi` untuk daftar/form sesuai keputusan; `POST /admin/evaluasi` untuk pencatatan yang disetujui. Kemampuan: `admin.evaluations.view` dan `admin.evaluations.create`; role: ADMIN. Setiap POST memakai CSRF. Edit/hapus tidak diusulkan sebelum kebijakan koreksi dan riwayat diputuskan.
- **Tes kunci:** validasi nilai/kriteria dan unit referensi; FK; data lintas layanan/periode tidak tercampur; audit mencatat aktor dan entitas tanpa memasukkan data sensitif; akses SISWA/GURU/GUEST ditolak; CSRF; keluaran kosong membedakan `BELUM ADA DATA`; query terparameterisasi; HTML di-escape.
- **Bukti entry point:** proses anak menjalankan `public/index.php` asli untuk GET daftar/detail yang disetujui dan POST valid/invalid dengan sesi/token; buktikan ADMIN berhasil dan role lain ditolak, tanpa fatal error. Gunakan database fixture khusus dan data fiktif.
- **Verifikasi skema setelah migrasi (operator menjalankan hanya pada database kerja yang benar):**
  ```powershell
  mysql -D humamantap_dev -e "SHOW TABLES LIKE 'evaluations';"
  mysql -D humamantap_dev -e "SHOW CREATE TABLE evaluations\G"
  ```

### 8C — RTL dan tindak lanjut

- **Prasyarat keputusan:** pastikan apakah RTL berasal dari evaluasi, pengaduan, atau keduanya; tetapkan PIC, jenis tindakan, status/transisi, tenggat, alasan perubahan, bukti penyelesaian, eskalasi, dan aturan penutupan.
- **Migrasi:** usulan `013_madani_rtl.sql`. Gunakan `follow_up_actions` yang ada hanya jika Pokja 6 menyetujui semantiknya yang berorientasi `complaint_id`; jika RTL adalah tindak lanjut evaluasi/layanan, jangan memaksakan FK pengaduan dan rancang relasi yang tepat. Riwayat perubahan status perlu append-only bila keputusan mengharuskannya.
- **Rute usulan:** `GET /admin/rtl`, `GET /admin/rtl/{id}`, `POST /admin/rtl`, dan, hanya setelah transisi ditetapkan, `POST /admin/rtl/{id}/status`. Kemampuan: `admin.rtl.view`, `admin.rtl.create`, dan bila diperlukan `admin.rtl.update_status`; role: ADMIN. POST wajib CSRF.
- **Tes kunci:** relasi ke evaluasi/layanan/pengaduan sesuai keputusan; FK dan transisi; penolakan ID tidak valid; otorisasi, CSRF, audit aktor/aksi/entitas; riwayat tidak dapat diubah/dihapus bila append-only ditetapkan; daftar tenggat/status berasal dari query; tidak ada notifikasi di luar cakupan.
- **Bukti entry point:** proses anak melalui `public/index.php` untuk daftar, detail, pembuatan dan transisi yang disetujui, serta permintaan non-ADMIN; tes memakai fixture dan akun fiktif.
- **Verifikasi skema setelah migrasi:**
  ```powershell
  mysql -D humamantap_dev -e "SHOW TABLES LIKE 'madani_rtl%';"
  mysql -D humamantap_dev -e "SHOW CREATE TABLE madani_rtl_actions\G"
  ```
  Ganti nama tabel dalam perintah sesuai skema final yang disetujui. Jika RTL memakai tabel existing, periksa `SHOW CREATE TABLE follow_up_actions\G` dan jangan mengubahnya tanpa migrasi korektif baru.

### 8D — Pengukuran before/after dan dampak

- **Prasyarat keputusan:** tetapkan indikator yang boleh diukur, definisi dan satuan, sumber baseline/follow-up, waktu/periode, metode validasi, pembanding, serta batas klaim kausal. Tentukan apakah konten before/after berupa narasi, angka, atau keduanya.
- **Migrasi:** usulan `014_madani_before_after.sql` hanya bila skema konseptual `before_after` perlu kolom/provenance/relasi baru. Jangan menganggap `title`, `before_text`, `after_text` sudah cukup untuk pengukuran; jangan mengubah struktur lama yang belum ada dalam migrasi tanpa mencatat perubahan sebagai migrasi baru.
- **Rute usulan:** `GET /admin/dampak` dan `POST /admin/dampak` untuk catatan dampak, serta `GET /admin/dampak/{id}` bila kebutuhan detail disetujui. Kemampuan: `admin.impact.view` dan `admin.impact.create`; role: ADMIN. Perubahan/pengesahan hanya ditambah setelah kebijakan koreksi disepakati; POST memakai CSRF.
- **Tes kunci:** nilai awal/akhir tidak digabung antar definisi/periode; satuan dan periode tervalidasi; provenance lengkap; data kosong tidak menghasilkan angka atau klaim; koreksi mengikuti kebijakan; audit tidak membocorkan data sensitif; akses/CSRF/escaping dan relasi FK.
- **Bukti entry point:** proses anak pada `public/index.php` untuk halaman, penyimpanan fixture fiktif dan validasi, hasil pembacaan kembali, serta penolakan non-ADMIN.
- **Verifikasi skema setelah migrasi:**
  ```powershell
  mysql -D humamantap_dev -e "SHOW TABLES LIKE 'before_after';"
  mysql -D humamantap_dev -e "SHOW CREATE TABLE before_after\G"
  ```
  Jalankan hanya jika migrasi 014 telah disetujui dan diterapkan; sesuaikan nama objek dengan keputusan final.

### 8E — Dashboard MADANI dan laporan/ekspor

- **Prasyarat keputusan:** tentukan audiens, periode/filter, metrik yang disetujui, sumber data, penyebut/pengecualian, aturan data hilang dan ambang publikasi, serta apakah ekspor diperlukan. Pisahkan laporan aplikasi dari laporan bukti penyelesaian tahap.
- **Migrasi:** tidak diperlukan untuk tampilan agregat baca-saja jika seluruh sumber sudah tersimpan dan metrik telah disetujui. Jangan membuat snapshot/cache tabel atau menyimpan nilai turunan kecuali kebutuhan reproduksibilitas/as-of disetujui; bila diputuskan perlu, ajukan migrasi baru berikutnya setelah 014 dengan alasan, signature, dan kebijakan retensi.
- **Rute usulan:** `GET /admin/madani` sebagai dashboard; bila disetujui, `GET /admin/laporan` dan `GET /admin/laporan/ekspor`. Kemampuan: `admin.madani.dashboard.view`, `admin.reports.view`, `admin.reports.export`; role: ADMIN. Semua laporan internal tetap diperiksa server-side. Ekspor CSV memakai perlindungan formula injection yang setara dengan `SurveyController`.
- **Tes kunci:** semua agregat dihitung dari query dan fixture yang diketahui; nol terukur vs tidak ada data ditangani eksplisit; periode/penyebut konsisten; hasil tidak menggabungkan sumber berbeda tanpa label; CSV formula injection; HTML escaping; audit ekspor hanya metadata; akses dan filter tervalidasi; tidak ada angka statis atau persentase dengan penyebut tersembunyi.
- **Bukti entry point:** proses anak lewat `public/index.php` membuka dashboard dan laporan sebagai ADMIN, mencoba role non-ADMIN, serta menguji ekspor bila tersedia. Bandingkan hasil dengan query fixture yang eksplisit.
- **Verifikasi skema:** tidak ada migrasi pada rancangan query-only. Verifikasi objek sumber yang telah disetujui dengan `SHOW TABLES`/`SHOW CREATE TABLE` untuk nama konkret dari migrasi 012–014; jangan menambah atau menjalankan migrasi hanya untuk dashboard.

### 8F — Integrasi, audit penerimaan, dan dokumentasi

- **Prasyarat:** keputusan bisnis untuk 8B–8E telah disetujui; label, aturan, dan matriks akses final telah selaras.
- **Migrasi:** tidak ada secara default. Bila uji menemukan kebutuhan skema, ajukan migrasi tambahan berikutnya, bukan perubahan ke 001–014 atau signature lama.
- **Rute:** tidak menambah rute baru kecuali yang sudah disetujui pada langkah sebelumnya. Perbarui `Permissions.php`, `Router`, matriks izin, navigasi ADMIN, ERD, dan keputusan agar konsisten.
- **Tes kunci:** matriks role × rute, CSRF untuk seluruh POST, transisi/constraint lintas modul, regresi suite penuh setelah tes terarah, audit metadata, XSS/SQL/CSV, serta agregat berbasis fixture.
- **Bukti entry point:** seluruh alur yang disetujui diuji melalui `public/index.php` asli dalam proses anak; jalankan tes regresi yang disetujui dan catat fakta yang benar-benar diamati.
- **Verifikasi skema setelah migrasi:** tidak ada perintah migrasi pada langkah ini bila skema tidak berubah. Jika ada migrasi korektif, verifikasi tabel/constraint spesifiknya dengan `SHOW TABLES`, `SHOW CREATE TABLE`, serta pemeriksaan signature pada fixture sebelum mempertimbangkan database kerja.

## 4. Risiko dan mitigasi perencanaan

| Risiko | Pengendalian yang direncanakan |
|---|---|
| Integritas: evaluasi, RTL, dampak, dan pengaduan memiliki relasi berbeda | Putuskan unit dan relasi sebelum skema; FK/index/aksi referensi eksplisit; validasi di server; transaksi untuk operasi multi-tabel. |
| Angka atau persentase karangan, penyebut tersembunyi, atau data kosong dianggap nol | Setiap metrik harus punya definisi, sumber, periode, pembilang, penyebut, pengecualian, serta label keadaan kosong yang disetujui. Tanpa data cukup, tampilkan `BELUM ADA DATA`; jangan tampilkan persentase/skor. |
| Klaim kepatuhan PMPZI/LKE | Tampilkan sebagai prototipe/instrumen internal; jangan menyebut dashboard atau laporan sebagai bukti kepatuhan maupun dampak kausal. |
| Privasi atau data sensitif masuk ke evaluasi/RTL | Gunakan fixture fiktif; minimalkan kolom; tetapkan role, retensi, pengungkapan, ekspor, dan audit sebelum menyimpan data. Jangan menyimpan informasi pribadi/BK. |
| Before/after dimanipulasi, tidak sebanding, atau dianggap bukti sebab-akibat | Catat definisi/sumber/waktu/periode dan pengesah; pertahankan riwayat koreksi sesuai kebijakan; labeli deskriptif; jangan menarik atribusi kausal tanpa rancangan evaluasi yang sah. |
| Audit memuat narasi sensitif atau terlalu banyak isi | Tentukan daftar metadata yang boleh diaudit; jangan menyalin catatan/narasi atau nilai sensitif ke pesan audit. Batasi siapa yang melihat detail dan ekspor. |
| Ekspor membocorkan data atau menjalankan formula spreadsheet | Batasi kolom dan ability ekspor; terapkan formula-neutralization pada setiap sel; audit ekspor metadata saja. |
| Benturan semantik dan skema | ERD MADANI masih konseptual dan belum dimigrasikan; review ERD/matriks sebelum tiap migrasi. Mulai dari 012 dan jangan mengubah migrasi/signature 001–011. |
| Migrasi gagal parsial karena DDL MySQL tidak atomik | Tes migrator/signature di fixture terisolasi; signature lengkap untuk objek baru; inspeksi ledger dan skema aktual sebelum pemulihan; koreksi hanya melalui migrasi baru yang terdokumentasi. |
| Kebijakan atau metrik berubah setelah data dicatat | Simpan definisi/periode yang relevan atau versi indikator hanya jika disetujui; jangan mengubah arti historis secara diam-diam. |

## 5. Keputusan bisnis sebelum implementasi

### Memblokir desain skema dan alur

1. **Evaluasi:** objek yang dievaluasi, kaitan terhadap aplikasi/layanan/periode, siapa evaluator dan validator, kriteria/indikator, skala, sumber data, waktu pengukuran, serta aturan koreksi/pengesahan.
2. **RTL:** hubungan dengan evaluasi dan/atau pengaduan; siapa PIC; status/transisi; prioritas, tenggat, eskalasi, bukti penyelesaian, dan aturan penutupan.
3. **Before/after:** narasi atau metrik; definisi/satuan indikator; baseline/follow-up dan sumber; periode; validasi; perubahan yang dapat diterima; batas klaim dampak.
4. **Privasi dan akses:** kategori data yang boleh disimpan; role/sub-role yang boleh membaca, mencatat, mengesahkan, atau mengekspor; retensi/penghapusan; kebutuhan pemisahan tugas dan audit.
5. **Laporan:** apakah ada laporan aplikasi tersimpan atau hanya rekap hasil; audiens, format/kolom, filter waktu, batas ekspor, label metodologi, dan approval.
6. **Penerimaan:** nama/isi halaman blueprint 10/14/15/16, skenario bisnis, data fiktif yang disetujui, serta siapa pemilik data dan penguji Pokja 6.

### Dapat menjadi nilai awal teknis sementara, setelah disetujui pemilik proyek

- Akses awal hanya ADMIN melalui `Permissions.php`, tanpa sub-role; ini tidak menyelesaikan kebutuhan pemisahan tugas bila Pokja 6 meminta verifikasi independen.
- SSR dengan prepared statements, CSRF, escaping, query teragregasi, no-store untuk data internal, audit metadata minimal, dan ekspor CSV yang dinetralkan adalah pola implementasi, bukan keputusan indikator.
- Tidak menyimpan data nyata, tidak mengarang metrik, tidak menghitung persentase tanpa definisi penyebut, dan menandai prototipe/non-kepatuhan sebagai batas tetap.
- Mulai dengan laporan baca-saja berbasis query tanpa snapshot, bila laporan dan definisi metrik sudah disetujui. Hindari persentase/skor sampai ada keputusan eksplisit.
- Gunakan migrasi berurutan mulai 012 hanya setelah kebutuhan tabel disahkan; data/signature migrasi lama tetap tidak berubah.

Keputusan yang berstatus menunggu tetap harus dicatat di `docs/DECISIONS.md` pada langkah implementasi yang relevan. Rencana ini sendiri tidak mengubah berkas lain, skema, atau aplikasi.
