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

## 2026-10-09 (Langkah 4a – Pengajuan tanpa berkas)

- MENUNGGU POKJA 6 — dokumen belum menetapkan role yang boleh mengajukan. Blueprint menyebut Guest dapat melihat formulir, sedangkan matriks menyebut SISWA/GURU mengakses pengajuan milik sendiri. Nilai konservatif prototipe: hanya SISWA dan GURU yang sudah login dapat mengajukan; Guest dan ADMIN ditolak.
- MENUNGGU POKJA 6 — dokumen belum merinci kolom formulir atau kebutuhan kontak. Prototipe menyimpan pemilik melalui `user_id` dan satu teks permintaan maksimal 4.000 karakter; nama/identitas tidak diduplikasi dan tidak ada kolom isian narasi konseling. KES-01 tetap INFORMASI dan tidak menerima pengajuan.
- MENUNGGU POKJA 6 — dokumen tidak menjelaskan klasifikasi sensitivitas permohonan KES-02 dan data kesiswaan/mutasi/kebutuhan khusus KES-04. Sebagai langkah konservatif, formulir keduanya meminta data minimum dan menjelaskan bahwa isi hanya terlihat oleh ADMIN, bukan cek status publik.
- MENUNGGU POKJA 6 — dokumen belum menetapkan status/transisi permohonan. Nilai konservatif sementara: DIAJUKAN → DIPROSES atau DITOLAK; DIPROSES → SELESAI atau DITOLAK; status SELESAI dan DITOLAK terminal.
- MENUNGGU POKJA 6 — dokumen belum menetapkan kebijakan rate limit cek status atau pengajuan. Prototipe membatasi cek status secara terpisah dari login dengan lockout IP 30 kegagalan dalam jendela 15 menit selama 15 menit; pengajuan dibatasi 5 per jam per akun.
- Risiko lockout cek status per IP: NAT sekolah dapat menyebabkan pengguna lain berbagi jatah dan terkena lockout; penyerang juga dapat menyalahgunakan ambang untuk DoS terhadap IP bersama. Perlu peninjauan Pokja 6 sebelum produksi.
- Kuota pengajuan 5 per jam diterapkan hanya per akun, bukan per IP; akun berbeda di IP yang sama memiliki kuota masing-masing.
- MENUNGGU POKJA 6 — jika flash sesi tidak sempat diambil (misalnya sesi hilang atau pengguna meninggalkan halaman sebelum kode terlihat), kode lacak hilang dan tidak ditampilkan ulang; prosedur pemulihan/permintaan ulang harus ditetapkan Pokja 6. Tidak ada pemulihan otomatis atau pengungkapan kode lewat log.
- Keputusan alur cek status: kode dikirim lewat POST /lacak dengan CSRF dan tidak dimasukkan ke URL; halaman cek status memakai Cache-Control: no-store dan Referrer-Policy: no-referrer.
- Keputusan PRG: pengajuan yang berhasil mengalihkan ke halaman sukses dan kode lacak ditampilkan sekali melalui flash sesi. Refresh halaman sukses tidak membuat pengajuan baru dan tidak menampilkan ulang kode.
- Keputusan keamanan implementasi: kode lacak acak kriptografis, status publik hanya menampilkan status dan tahap, antrean serta data permohonan hanya untuk ADMIN, dan unggah berkas tidak termasuk Langkah 4a.
- Batasan riwayat: trigger migrasi 005 hanya mencegah UPDATE/DELETE langsung pada `application_history`; aksi foreign-key cascade dapat melewatinya. Pembuatan trigger membutuhkan hak database `TRIGGER`.
- Alasan: meminimalkan data pribadi dan paparan sebelum kebijakan operasional disepakati.
- Status: nilai konservatif diterapkan sementara; seluruh butir MENUNGGU POKJA 6 di atas belum menjadi kebijakan final.

## 2026-10-10 (Langkah 4b – Lampiran pengajuan)

- Keputusan implementasi sementara: satu pengajuan dapat menyertakan maksimal 3 berkas JPG/PNG, maksimal 5 MiB per berkas; PDF ditunda sampai keamanannya dikaji.
- Keputusan akses: lampiran hanya dapat dilihat/diunduh ADMIN melalui rute server terlindungi. Halaman lacak dan portal tidak menampilkan berkas maupun nama berkas.
- Layanan yang menerima lampiran: TU-01, TU-02, KUR-02, SAR-01, dan HUM-01. KES-02 dan KES-04 tidak menerima lampiran sampai ada keputusan tertulis Pokja 6.
- Keputusan pengajuan 4a tidak berubah: pengajuan hanya untuk SISWA dan GURU yang sudah login.
- Status: diterapkan untuk prototipe; klasifikasi layanan KES-02/KES-04 dan penerimaan lampiran tetap MENUNGGU POKJA 6.

## 2026-10-11 (Langkah 5 – KAHAYAN AMANAH / HUM-02)

- Keputusan nilai awal: pelapor dapat Guest, SISWA, atau GURU; identitas dan kontak tidak wajib. Kontak dibatasi 120 karakter. Sistem tidak meminta NISN, NIP, atau dokumen.
- Jenis masukan dibatasi pada PENGADUAN, ASPIRASI, SARAN, dan PERTANYAAN. Isi wajib dan maksimal 4.000 karakter. Lampiran tidak diterima pada langkah ini.
- Status awal DITERIMA; perpindahan yang sah: DITERIMA → DIKLASIFIKASI atau DITOLAK; DIKLASIFIKASI → DITINDAKLANJUTI atau DITOLAK; DITINDAKLANJUTI → SELESAI. SELESAI dan DITOLAK terminal. Penolakan wajib memiliki alasan.
- Isi serta identitas pelapor hanya terlihat oleh ADMIN. Pelapor melacak dengan kode melalui POST; halaman publik hanya menampilkan kode tiket, status, tahap, dan tanggapan publik yang ditulis ADMIN, bukan isi pengaduan.
- Kuota awal adalah lima pengiriman per IP per satu jam. Alamat IP hanya disimpan sebagai SHA-256 untuk mengelola kuota. Risiko NAT sekolah: pengguna berbagi IP dan kuota; satu pengguna dapat menghabiskan jatah bersama.
- Pesan anonim tidak diteruskan kepada pihak lain dan sistem tidak mengirim notifikasi email.
- MENUNGGU POKJA 6: kebijakan identitas pelapor, SLA tindak lanjut, kategori pengaduan, serta unit/pihak yang menindaklanjuti.
- Status: nilai awal teknis prototipe; bukan kebijakan operasional final dan tidak menyatakan terpenuhinya layanan nyata.

## Audit atomisitas limiter dan pemulihan migrasi

- Keputusan implementasi limiter: kegagalan cek status tetap dihitung per alamat IP dengan jendela dan ambang konfigurasi yang sudah berlaku; pencatatan diserialisasi menggunakan MySQL `GET_LOCK` per database dan IP, lalu menghitung, menyisipkan kegagalan/kunci, dan commit dalam satu urutan kritis. Tabel percobaan menggunakan InnoDB. Nilai ambang dan durasi tidak berubah.
- Batasan kebijakan limiter: shared IP/NAT dapat menyebabkan pengguna yang tidak terkait berbagi kuota dan terkena lockout. Di balik proxy, `REMOTE_ADDR` dapat menjadi alamat proxy sehingga seluruh pengguna di belakangnya berbagi kuota; header forwarding tidak dipercaya oleh implementasi ini. Perubahan agar menerima alamat dari proxy memerlukan rancangan konfigurasi proxy tepercaya yang terpisah dan menunggu evaluasi operasional.
- Keputusan implementasi migrator: tiap migrasi baru dicatat di `schema_migration_runs` sebagai `RUNNING` sebelum statement pertama; setelah setiap statement berhasil, checkpoint diperbarui. Keberhasilan dicatat sebagai `APPLIED` dan masuk ke `schema_migrations` dalam satu transaksi DML. Kegagalan SQL ditandai `FAILED` dengan jumlah statement selesai sejauh pencatatan terakhir.
- Batasan transaksi MySQL: transaksi pada ledger tidak menjadikan DDL migrasi atomik. DDL dapat melakukan implicit commit dan objek hasil statement yang selesai dapat tetap ada bila statement berikutnya gagal. Proses yang mati mendadak dapat meninggalkan `RUNNING`; status itu sama-sama dianggap tidak pasti dan diblokir.
- Pemulihan migrasi: migrasi dengan status `RUNNING` atau `FAILED` tidak dilanjutkan dan tidak dijalankan ulang otomatis. Operator harus memeriksa checksum, error, checkpoint, ledger, dan skema aktual pada database yang tepat; setelah itu perbaikan dilakukan manual atau melalui migrasi korektif baru. Jangan menghapus ledger/status hanya untuk mengulang migrasi, dan jangan menganggap semua statement aman untuk diulang. Migrator tidak mencoba membatalkan DDL.
- Batas adopsi ledger: keadaan parsial yang dibuat oleh versi migrator sebelum `schema_migration_runs` tersedia tidak memiliki catatan `RUNNING`/`FAILED` dan tidak dapat dibedakan otomatis dari migrasi yang belum dimulai. Sebelum mengganti migrator pada database yang sudah ada, operator wajib mencocokkan ledger migrasi dengan skema aktual untuk setiap migrasi tertunda; khususnya `003_user_session_revocation.sql` menambah kolom tanpa guard, dan trigger `002_auth_schema.sql` tidak memakai pemeriksaan `IF NOT EXISTS`. Bila status tidak jelas, hentikan dan rekonsiliasi manual sebelum menjalankan migrator baru.
- Kebijakan adopsi migrator lama: migrasi yang tercantum di `schema_migrations` tetapi belum memiliki record `APPLIED` ber-checksum di `schema_migration_runs` dihentikan; migrator tidak mengisi ledger baru atau meneruskan migrasi tertunda secara otomatis. Migrasi tanpa ledger dan tanpa artefak yang dikenali boleh dimulai; artefak parsial maupun lengkap tanpa ledger menyebabkan penghentian. Operator harus memeriksa seluruh tabel/kolom/trigger, ledger lama, versi sumber, dan data referensi; koreksi hanya setelah rekonsiliasi terdokumentasi.
- Pemeriksaan konsistensi migrasi baru: status `APPLIED` harus cocok dengan row `schema_migrations`, checksum file sumber, jumlah statement, checkpoint akhir, serta signature tabel/kolom dan atribut kritis yang terdaftar untuk migrasi 001–006. Signature memeriksa definisi kolom kritis yang dipilih, urutan kolom dan keunikan indeks yang diketahui, kolom target serta aturan referensi foreign key, isi aksi trigger kritis, dan nama/tipe/nullability kolom serta indeks utama kedua ledger. Ini bukan hash definisi DDL penuh: sebagian kolom selain yang ditandai kritis hanya diperiksa keberadaannya; default/extra kolom dan indeks tambahan tidak seluruhnya divalidasi. Perbedaan di luar signature tetap memerlukan pemeriksaan manual.
- Serialisasi migrator: lock advisory memakai nama basis data aktual dari `SELECT DATABASE()`, bukan alias argumen (`test`/`humamantap_test`), sehingga dua pemanggilan dengan alias berbeda tetap memakai lock MySQL yang sama. Lock bersifat per server MySQL dan koneksi terpisah.
- Uji crash: fault injection melalui environment hanya diterima oleh proses CLI migrator dengan direktori fixture khusus di dalam proyek dan database fixture nonce milik proses tes, diverifikasi lewat nama serta token kepemilikan. Pengujian keluar dari proses tanpa finalisasi setelah DDL pertama, setelah statement kedua sebelum checkpoint, dan setelah seluruh statement sebelum finalisasi; ini meniru terminasi proses PHP dan bukan simulasi server MySQL mati atau pemutusan jaringan.
- Hak akses: pembuatan ledger status memakai `CREATE TABLE`; migrasi yang membuat trigger memerlukan hak `TRIGGER`. Kegagalan hak akses dicatat sebagai gagal/keadaan yang perlu diperiksa, bukan dilewati.
- Isolasi tes: bootstrap memakai profil koneksi `test` yang harus menunjuk konfigurasi dasar `humamantap_test`, membuat database unik `humamantap_test_<nonce>` tanpa `DROP` saat inisialisasi, dan menandainya dengan token acak. Koneksi tes menolak target lain; proses cleanup hanya menghapus database yang dibuat oleh proses tersebut setelah memverifikasi token kepemilikan. Bila bukti kepemilikan hilang, database dibiarkan utuh.
- Validasi: tes menjalankan request limiter dan migrator paralel melalui proses/koneksi terpisah serta fixture migrasi yang memaksa kegagalan SQL, crash proses, ketidakcocokan checksum, ledger lama, artefak orphan, ketidakcocokan signature kolom/index/FK/trigger/ledger. Hasil tes tidak membuktikan perilaku beban produksi atau seluruh kemungkinan crash/implicit commit.

## 2026-10-11 (Langkah 6 – Survei kepuasan dan dashboard)

- Survei hanya tersedia untuk pengajuan layanan berstatus SELESAI dan dibuka menggunakan kode lacak melalui POST; kode tidak menjadi bagian URL. Satu survei per pengajuan, dijaga UNIQUE pada `application_id`; kiriman kedua ditolak.
- Nilai awal survei: penilaian 1–5 untuk KECEPATAN, KEJELASAN, dan KERAMAHAN; saran opsional maksimal 1.000 karakter. Tabel tidak menyimpan identitas pengisi. Pertanyaan final MENUNGGU POKJA 6.
- Jawaban dan saran survei hanya dapat dilihat ADMIN melalui rekap dan ekspor; pengisi tidak memperoleh akses ke jawaban lain.
- Dashboard admin menghitung angka dari basis data. Nilai 0 ditampilkan untuk hitungan baris nyata; rata-rata tanpa responden ditampilkan `BELUM ADA DATA`. Layanan dengan responden di bawah lima diberi label `DATA TERLALU SEDIKIT`.
- Ekspor CSV hanya ADMIN. Awalan `=`, `+`, `-`, atau `@` di sel dilindungi dengan prefiks tanda kutip tunggal untuk mencegah formula CSV.
- Kuota awal pengiriman survei: lima percobaan per IP per jam, memakai lock IP bersama dan hash IP; NAT sekolah dapat menyebabkan kuota berbagi.
- MENUNGGU POKJA 6: pertanyaan final survei, skala final, apakah survei juga berlaku bagi layanan bermode INFORMASI, dan ambang minimum responden.
- Status: nilai awal prototipe, tidak menyatakan kebijakan survei final.

## Catatan kepastian

- PHP 8.3.33 dan MySQL 8.4.3 sesuai target.
- Ekstensi yang dibutuhkan tersedia: `pdo_mysql`, `mbstring`, `fileinfo`, `openssl`.
- MySQL server lokal terdeteksi hidup dan dapat diping.
- Semua keputusan yang bersifat operasional dan layanan tetap menunggu konfirmasi dari tim Pokja 6.

## 2026-10-11 (Langkah 6B – Survei layanan informasi tanpa kode lacak)

- Keputusan: sementara disetujui oleh pemilik proyek dan MENUNGGU KONFIRMASI POKJA 6. Survei memiliki tiga aspek KECEPATAN, KEJELASAN, dan KERAMAHAN, masing-masing berskala 1–5; saran opsional maksimal 1.000 karakter. Ambang responden lima tetap berlaku.
- Survei layanan INFORMASI tersedia melalui menu `/survei`, tanpa tautan di halaman layanan atau halaman lain, dan tidak memakai kode lacak.
- Karena identitas/kunci unik pengisi tidak tersedia, jawaban layanan INFORMASI tidak terverifikasi. Rata-rata dan saran tampil terpisah dari survei berkode dengan label “tanpa verifikasi”; keduanya tidak dicampur.
- Tabel `surveys` tidak diubah agar signature migrasi 009 tetap berlaku. Respons survei INFORMASI disimpan terpisah di `information_surveys`.
- Kuota awal survei INFORMASI adalah maksimal tiga kiriman per IP per layanan per jam. Hanya hash alamat IP disimpan; kuota ini terpisah dari kuota survei berkode. Risiko NAT sekolah: beberapa pengguna pada IP yang sama berbagi kuota untuk layanan yang sama.
- Status: keputusan teknis sementara prototipe, MENUNGGU KONFIRMASI POKJA 6.

## 2026-10-11 (Langkah 7B – Eviden)

- Keputusan sementara disetujui oleh pemilik proyek, MENUNGGU KONFIRMASI POKJA 6: 99 sel checklist pada tahap 7C direncanakan sebagai 11 layanan katalog × 9 checkpoint EV-01 sampai EV-09; semua checkpoint berlaku untuk semua layanan. Label diambil persis dari `docs/ERD.md`. Kriteria bukti per checkpoint belum ditetapkan dan tidak boleh dikarang.
- Satu eviden adalah satu berkas yang terikat pada satu pasangan layanan-checkpoint; satu pasangan dapat memiliki banyak eviden. Satu unggahan menerima maksimal tiga berkas dan menyimpan seluruh berkas dalam satu transaksi (semua berhasil atau tidak ada).
- Status eviden adalah `DIUNGGAH`, `TERVERIFIKASI`, atau `PERLU_PERBAIKAN`. Hanya status `DIUNGGAH` dapat diputuskan. Keputusan final; tindak lanjut dilakukan dengan mengunggah eviden baru tanpa versioning. `PERLU_PERBAIKAN` wajib beralasan maksimal 500 karakter UTF-8 valid.
- Hanya ADMIN dapat mengakses fungsi eviden. Pengunggah boleh memverifikasi evidennya sendiri. `uploaded_by` dan `verified_by` selalu direkam; antarmuka memberi penanda “diverifikasi oleh pengunggah yang sama” bila ID keduanya sama.
- Berkas sementara dibatasi pada JPG/PNG, maksimal 5 MiB per berkas dan tiga berkas per unggahan. PDF dan format lain ditolak; kebijakan format dapat ditinjau terpisah oleh Pokja 6.
- Pengujian hanya memakai data dan berkas fiktif. Retensi, pemulihan berkas orphan, dan kriteria bukti per checkpoint MENUNGGU POKJA 6. Tidak ada klaim kepatuhan PMPZI/LKE.
- Audit hanya mencatat metadata: aktor, aksi, ID eviden, layanan, checkpoint, status, MIME, ukuran, dan SHA-256. Isi berkas, nama asli, serta alasan perbaikan tidak dicatat dalam audit.

## 2026-10-11 (Langkah 7C – Checklist Sel)

- Keputusan disetujui sementara oleh pemilik proyek, MENUNGGU KONFIRMASI POKJA 6: satu sel adalah pasangan layanan dan checkpoint. Matriks dibentuk dari CROSS JOIN aktual `services` × `evidence_checkpoints`; jumlah sel berasal dari hasil query dan sel tidak disimpan.
- Status sel dihitung dengan prioritas `VALID` bila ada minimal satu eviden `TERVERIFIKASI`; selain itu `ADA_BELUM_VERIFIKASI` bila ada eviden `DIUNGGAH`; selain itu `PERLU_PERBAIKAN` bila hanya ada eviden `PERLU_PERBAIKAN`; selain itu `KOSONG`.
- Checklist adalah instrumen kendali internal prototipe, bukan indikator resmi LKE/PMPZI. Tidak menampilkan persentase atau skor.
- Layanan nonaktif tetap disertakan dalam matriks dan diberi label nonaktif. Jumlah layanan dan sel aktual harus dilaporkan dari fixture/data yang diperiksa; rencana 11 layanan × 9 checkpoint menghasilkan 99 hanya bila data aktual cocok.
- Status keputusan: sementara disetujui oleh pemilik proyek, MENUNGGU KONFIRMASI POKJA 6.

## 2026-10-11 (Langkah 8B – Evaluasi catatan terstruktur)

- Pemetaan penamaan disetujui sementara oleh pemilik proyek, MENUNGGU KONFIRMASI POKJA 6: blueprint menyebut MADANI sebagai Tahap 7 dan pengerasan sebagai Tahap 8; proyek ini menamai pekerjaan MADANI Langkah 8 (8B evaluasi, 8C RTL, 8D before/after). Langkah 8F dibatalkan; audit dan sinkronisasi dokumen menjadi bagian akhir tiap langkah. Laporan/ekspor/dashboard MADANI (8E) ditunda sampai 8B–8D selesai dan diputuskan perlu.
- Evaluasi sementara disetujui sebagai catatan teks per layanan per periode (tanggal mulai dan akhir), dengan ringkasan wajib, temuan opsional, dan kesimpulan opsional. Tidak ada skor, angka penilaian, atau persentase dan tidak ada tumpang tindih makna dengan survei kepuasan.
- Hanya ADMIN dapat mencatat dan melihat evaluasi. Satu layanan boleh memiliki beberapa catatan pada periode yang sama; tidak ada pencegahan duplikat berdasarkan periode.
- Catatan bersifat append-only: tidak ada edit atau hapus. Koreksi dilakukan dengan catatan baru yang merujuk catatan lama melalui `corrects_evaluation_id`; layanan wajib sama, catatan lama tetap dan menampilkan penanda koreksi.
- Hanya data fiktif yang boleh digunakan. Dilarang memasukkan data pribadi, identitas siswa, atau informasi BK. Audit hanya berisi aktor, aksi, ID evaluasi, layanan, periode, dan ID catatan yang dikoreksi; audit tidak memuat ringkasan, temuan, atau kesimpulan.
- Halaman menyatakan catatan internal prototipe dan tidak mengklaim kepatuhan PMPZI/LKE. Definisi evaluasi, kriteria, dan indikator menunggu Pokja 6; isi halaman blueprint 10, 14, 15, dan 16 belum diverifikasi.
- Status: disetujui sementara oleh pemilik proyek, MENUNGGU KONFIRMASI POKJA 6.

## 2026-10-11 (Langkah 8C – RTL append-only)

- RTL adalah tindak lanjut atas satu evaluasi 8B; satu evaluasi dapat memiliki banyak RTL. Evaluasi yang sudah dikoreksi tetap dapat dirujuk dan harus diberi penanda koreksi pada tampilan.
- Isi RTL adalah uraian tindakan wajib, penanggung jawab berupa nama/jabatan fiktif wajib, dan tenggat tanggal wajib.
- Status yang disetujui sementara adalah `DIRENCANAKAN`, `BERJALAN`, dan `SELESAI`; status awal `DIRENCANAKAN`. Transisi hanya maju: `DIRENCANAKAN` ke `BERJALAN` atau `SELESAI`, serta `BERJALAN` ke `SELESAI`. `SELESAI` final; tidak ada mundur atau pembatalan.
- Data RTL append-only: isi tidak dapat diedit atau dihapus. Perubahan status menjadi baris baru pada `madani_rtl_status_log`; status saat ini dihitung dari baris riwayat terbaru. Catatan status opsional maksimal 500 karakter. Kesalahan isi diperbaiki dengan RTL baru.
- Label “Lewat tenggat” dihitung dari tenggat dan tanggal server hanya untuk RTL yang belum `SELESAI`; label tidak disimpan dan tidak mengubah status.
- Hanya ADMIN yang dapat melihat atau mencatat RTL. Gunakan data fiktif saja; dilarang memasukkan data pribadi, identitas siswa, atau informasi BK. Audit hanya menyimpan aktor, aksi, ID RTL, ID evaluasi, dan status baru—tanpa uraian tindakan, penanggung jawab, atau catatan.
- Halaman menyatakan “internal prototipe, bukan klaim kepatuhan PMPZI/LKE”. Tidak ada notifikasi, skor, atau persentase.
- Definisi RTL, PIC, dan alur status serta isi halaman blueprint 10, 14, 15, dan 16 MENUNGGU KONFIRMASI POKJA 6.
- Status: disetujui sementara oleh pemilik proyek, MENUNGGU KONFIRMASI POKJA 6.

## 2026-10-11 (Langkah 8D – Before/After deskriptif append-only)

- Before/after adalah catatan naratif deskriptif per layanan: judul, kondisi sebelum beserta tanggal pengamatan, kondisi sesudah beserta tanggal pengamatan, dan sumber pengamatan wajib. Tidak ada angka terukur, skor, persentase, atau selisih hitungan.
- Keterkaitan RTL bersifat opsional. Jika dicatat, RTL harus ada, layanannya harus sama dengan layanan catatan, dan status terbaru RTL harus `SELESAI` pada saat pencatatan.
- Tanggal kondisi sebelum harus lebih awal atau sama dengan tanggal sesudah; tanggal sesudah tidak boleh melewati tanggal server.
- Catatan append-only: tidak dapat diedit atau dihapus. Koreksi dibuat sebagai catatan baru yang merujuk catatan lama pada layanan yang sama; catatan lama tetap tampil dengan penanda “dikoreksi oleh #id”.
- Hanya ADMIN dapat mencatat atau melihat. Gunakan data fiktif; jangan memasukkan data pribadi, identitas siswa, atau informasi BK. Audit hanya metadata aktor, aksi, ID catatan, ID layanan, ID RTL bila ada, dan ID yang dikoreksi bila ada; audit tidak menyimpan judul, narasi, atau sumber.
- Halaman menyatakan catatan deskriptif internal prototipe, bukan bukti sebab-akibat, bukan bukti dampak terukur, dan bukan klaim kepatuhan PMPZI/LKE.
- Definisi dan kriteria before/after serta isi halaman blueprint 10, 14, 15, dan 16 belum diverifikasi; MENUNGGU KONFIRMASI POKJA 6.
- Status: disetujui sementara oleh pemilik proyek, MENUNGGU KONFIRMASI POKJA 6.

## Keterbatasan implementasi yang diketahui (Langkah 7B–8D)

- Berkas eviden dapat menjadi orphan bila proses mati setelah pemindahan berkas ke storage dan sebelum transaksi database commit. Prosedur rekonsiliasi berkas orphan menunggu Pokja 6.
- Penanda “dikoreksi oleh” memakai `GROUP_CONCAT`, yang dibatasi oleh `group_concat_max_len` MySQL (nilai bawaan 1024 byte). Daftar koreksi dapat terpotong bila satu catatan dikoreksi sangat banyak kali.
- Append-only pada tabel catatan MADANI (`madani_evaluations`, `madani_rtl`, `madani_rtl_status_log`, dan `madani_before_after`) ditegakkan pada lapisan aplikasi dan melalui ketiadaan rute edit/hapus, bukan oleh database. Untuk deployment, gunakan akun database aplikasi khusus dengan hak minimum serta tanpa `DELETE`/`UPDATE` pada tabel-tabel catatan tersebut.
- Batas PHP `upload_max_filesize`, `post_max_size`, dan `max_file_uploads` pada PHP-FPM/web server harus diperiksa terpisah dari nilai PHP CLI.
- Zona waktu PHP server memengaruhi validasi tanggal masa depan pada catatan before/after.

## Langkah 8E — Dashboard MADANI dan laporan agregat

- Nilai awal, MENUNGGU POKJA 6: audiens hanya ADMIN. Dashboard dan ekspor bersifat hanya-baca; tidak ada tabel snapshot dan tidak ada migrasi baru.
- Metrik awal berasal dari query basis data: jumlah evaluasi MADANI per layanan; status terbaru RTL dan jumlah RTL belum selesai yang lewat tenggat menurut tanggal PHP server; jumlah catatan before/after per layanan; eviden per status; sel checklist per status; serta aduan dan pengajuan per status.
- Periode awal maksimal 366 hari inklusif dan filter layanan harus merujuk layanan yang ada. Periode tidak valid ditolak. Acuan tanggal: evaluasi memakai irisan periode evaluasi dengan filter; RTL, eviden, pengajuan, dan aduan memakai `created_at`; before/after memakai `after_observed_on`. Checklist adalah snapshot status saat ini karena pasangan layanan-checkpoint tidak memiliki tanggal historis dan tidak dipengaruhi filter periode. Aduan tidak memiliki relasi layanan sehingga filter layanan tidak mengubah hitungan aduan.
- Hitungan nol ditampilkan sebagai nol. Rata-rata, persentase, atau rasio tanpa penyebut ditampilkan `BELUM ADA DATA`. Tidak ada skor gabungan, peringkat, atau klaim pencapaian PMPZI/LKE/dampak.
- Ekspor CSV hanya memuat agregat tanpa isi evaluasi, narasi, nama berkas, kontak, atau identitas. Nilai sel yang diawali `=`, `+`, `-`, atau `@` diberi awalan kutip tunggal. Ekspor memakai header CSV/unduhan dan `Cache-Control: no-store`.
- Setiap halaman memuat penafian prototipe, bukan bukti kepatuhan, dengan data uji fiktif.
- MENUNGGU POKJA 6: definisi metrik dan penyebut, kemungkinan audiens selain ADMIN, kebutuhan ekspor resmi, serta ambang publikasi. Perubahan definisi dapat memerlukan perubahan implementasi; bila diperlukan perubahan skema, lakukan melalui migrasi baru yang terpisah.

## 2026-10-12 (Langkah 9 – Hardening pra-produksi)

- Keputusan: pseudonim IP pada kuota aduan, survei berkode, dan survei INFORMASI memakai HMAC-SHA256 atas `REMOTE_ADDR`, dengan kunci rahasia dari `security.ip_hash_key` atau override lingkungan `HUMA_IP_HASH_KEY`. Kunci harus string minimal 32 byte; bila tidak tersedia/tidak valid, kiriman gagal tertutup dengan respons 500 generik sebelum kuota atau data ditulis. Tidak ada fallback SHA-256 polos.
- Keputusan: baris kuota lama yang berisi SHA-256 polos dibiarkan kedaluwarsa alami, bukan diubah melalui migrasi. Jendela kuota satu jam membuat baris lama tidak aktif paling lama satu jam setelah `window_started_at`; karena digest lama tidak cocok dengan HMAC, batas kuota dapat mulai lagi selama sisa jendela transisi. Pembersih hanya menghapus baris setelah jendela satu jam lewat. Tidak diperlukan migrasi 015.
- Keputusan: pembersih bersama menghapus hanya baris lebih tua atau sama dengan satu jam dari tiga tabel kuota. Request publik memanggilnya dengan peluang 1/1000 setelah kunci lolos validasi; operasi manual tersedia melalui `bin/bersihkan-kuota.php`. Audit, riwayat, dan tabel append-only tidak menjadi sasaran pembersihan.
- Alasan: menghilangkan pseudonim IP tanpa kunci, mempertahankan hitungan aktif dan sejarah migrasi, serta membatasi biaya pembersihan per request.
- Operasional: ganti nilai contoh `security.ip_hash_key` dengan secret acak minimal 32 byte atau set `HUMA_IP_HASH_KEY` pada environment server. Jangan memakai nilai contoh atau menyimpan secret di repositori. Rotasi kunci membuat ruang kuota baru; baris dari kunci lama tetap sampai kedaluwarsa.
