# ERD (ENTITY RELATIONSHIP DIAGRAM) - KONSEP AWAL

## 1. Entitas utama

- roles
- users
- login_attempts
- service_categories
- services
- service_steps
- service_standards
- applications
- application_documents
- application_history
- complaints
- complaint_history
- surveys
- evidence_items
- evidence_checkpoints
- evidence_links
- evaluations
- follow_up_actions
- before_after
- audit_logs
- id_counters

## 2. Hubungan terancang

- satu `roles` memiliki banyak `users`
- satu `service_categories` memiliki banyak `services`
- satu `services` memiliki banyak `service_steps`
- satu `services` memiliki banyak `service_standards`
- satu `services` memiliki banyak `applications`
- satu `users` dapat memiliki banyak `applications` sebagai pemohon
- satu `applications` memiliki banyak `application_documents`
- satu `applications` memiliki banyak `application_history`
- satu `users` dapat mengirim banyak `complaints`
- satu `complaints` memiliki banyak `complaint_history`
- satu `applications` memiliki paling banyak satu `surveys` (UNIQUE `surveys.application_id`)
- satu `services` memiliki banyak `surveys`
- satu `services` bermode INFORMASI memiliki banyak `information_surveys`
- satu `services` memiliki banyak bucket `information_survey_ip_quotas`, masing-masing dibedakan oleh hash IP
- satu `services` memiliki banyak `evidence_items`
- satu `evidence_checkpoints` memiliki banyak `evidence_items`
- satu `users` dapat mengunggah banyak `evidence_items` melalui `uploaded_by`
- satu `users` dapat memutuskan banyak `evidence_items` melalui `verified_by`
- satu `applications` memiliki banyak `evaluations`
- satu `complaints` memiliki banyak `follow_up_actions`
- satu `services` memiliki banyak `before_after`
- semua perubahan dicatat di `audit_logs`
- `id_counters` menyimpan nomor tiket dan token berikutnya
- `complaints.user_id` mereferensikan `users.id` dengan `ON DELETE SET NULL`; `complaint_history.complaint_id` mereferensikan `complaints.id` dengan `ON DELETE CASCADE`, dan `changed_by` mereferensikan `users.id` dengan `ON DELETE SET NULL`.
- Status HUM-02 dan perpindahan sahnya berada di `app/Core/ComplaintWorkflow.php`; keputusan yang sama dicatat di `docs/DECISIONS.md`.

## 3. Ringkasan tabel dan kolom kunci

### roles
- `id`
- `code` (GUEST, SISWA, GURU, ADMIN)
- `name`
- `description`

### users
- `id`
- `role_id`
- `username` atau `identifier`
- `password_hash`
- `full_name`
- `nisn` / `nip` (opsional, tidak dipakai untuk autentikasi utama)
- `status`
- `last_login_at`
- `created_at`

### login_attempts
- `id`
- `user_id` (nullable)
- `ip_address`
- `result` (SUCCESS, FAILED, LOCKED)
- `attempted_at`

### service_categories
- `id`
- `code` (TU, KUR, KES, SAR, HUM)
- `name`
- `sort_order`

### services
- `id`
- `category_id`
- `code` (TU-01, KUR-02, dst.)
- `name`
- `mode` (FORMULIR, INFORMASI, TATAP_MUKA)
- `status` (DRAFT, PUBLISHED, ARCHIVED)
- `description`

### service_steps
- `id`
- `service_id`
- `step_no`
- `step_title`
- `step_description`
- `sort_order`

### service_standards
- `id`
- `service_id`
- `standard_name`
- `sla_days`
- `responsible_unit`
- `fee_amount`

### applications
- `id`
- `ticket_no`
- `track_token`
- `service_id`
- `applicant_id` (nullable untuk Guest)
- `applicant_name`
- `channel`
- `status`
- `staff_note`
- `created_at`

### application_documents
- `id`
- `application_id`
- `nama_asli` (nullable, dibersihkan)
- `nama_acak` (nama penyimpanan acak di `storage/private/`)
- `mime_terdeteksi` (`image/jpeg` atau `image/png`)
- `ukuran` (byte)
- `sha256` (hash isi berkas yang dihitung server)
- `created_at`

Relasi `application_documents.application_id` mereferensikan `applications.id` dengan penghapusan berantai. Batas aplikasi: maksimal 3 JPG/PNG, 5 MiB per berkas; PDF belum diterima.

### application_history
- `id`
- `application_id`
- `from_status`
- `to_status`
- `changed_by`
- `note`
- `changed_at`
- Catatan: tabel append-only; update dan delete ditolak.

### complaints
- `id`
- `ticket_no` (unik; CMP-YYYY-NNNN dari `id_counters`)
- `track_token` (unik, token acak)
- `jenis` (PENGADUAN, ASPIRASI, SARAN, PERTANYAAN)
- `isi` (wajib, maksimal 4.000 karakter)
- `kontak` (opsional, maksimal 120 karakter)
- `user_id` (nullable untuk Guest; identitas hanya terlihat ADMIN)
- `status` (DITERIMA, DIKLASIFIKASI, DITINDAKLANJUTI, SELESAI, DITOLAK)
- `response_note` (tanggapan publik ADMIN, opsional)
- `reject_reason` (wajib saat DITOLAK)
- `created_at`
- `updated_at`
- Catatan: isi dan identitas tidak dipaparkan pada pelacakan publik; tidak ada lampiran.

### complaint_history
- `id`
- `complaint_id`
- `event_type` (STATUS atau TANGGAPAN)
- `old_status`
- `new_status`
- `changed_by`
- `created_at`
- Catatan: tabel append-only; update dan delete ditolak.

### complaint_ip_quotas
- `ip_hash` (SHA-256 alamat REMOTE_ADDR; primary key, bukan alamat IP mentah)
- `window_started_at`
- `submission_count` (kuota awal 5 kiriman per IP per satu jam)

### surveys
- `id`
- `application_id` (unik, FK ke `applications.id`)
- `service_id`
- `speed_rating` (TINYINT UNSIGNED; validasi aplikasi 1–5)
- `clarity_rating` (TINYINT UNSIGNED; validasi aplikasi 1–5)
- `courtesy_rating` (TINYINT UNSIGNED; validasi aplikasi 1–5)
- `suggestion` (opsional, maksimal 1.000 karakter)
- `created_at`
- Catatan: satu respon per pengajuan SELESAI; identitas pengisi tidak disimpan.

### survey_ip_quotas
- `ip_hash` (SHA-256 alamat REMOTE_ADDR, primary key)
- `window_started_at`
- `submission_count` (kuota awal lima per IP per satu jam)

### information_surveys
- `id` (primary key)
- `service_id` (FK ke `services.id`; aplikasi hanya menerima layanan aktif bermode INFORMASI)
- `speed_rating`, `clarity_rating`, `courtesy_rating` (TINYINT UNSIGNED; validasi aplikasi 1–5)
- `suggestion` (opsional, maksimal 1.000 karakter)
- `created_at`
- Catatan: survei tanpa kode lacak dan tanpa identitas pengisi; data berlabel “tanpa verifikasi” dan direkap terpisah dari `surveys`.

### information_survey_ip_quotas
- `service_id` (FK ke `services.id`)
- `ip_hash` (SHA-256 alamat REMOTE_ADDR; pasangan `service_id`, `ip_hash` menjadi primary key)
- `window_started_at`
- `submission_count` (maksimal tiga kiriman per IP per layanan per satu jam sebagai nilai awal)

### evidence_items
- Definisi konseptual sebelumnya telah digantikan oleh struktur aktual pada bagian `evidence_items (migrasi 011)` di bawah.

### evidence_checkpoints
- Definisi konseptual sebelumnya telah digantikan oleh struktur aktual pada bagian `evidence_checkpoints (migrasi 011)` di bawah.

- 9 titik pembuktian yang harus tersedia secara konseptual:
  1. EV-01: Identitas layanan
  2. EV-02: Dokumen persyaratan
  3. EV-03: Proses verifikasi awal
  4. EV-04: Pelaksanaan SOP
  5. EV-05: Hasil layanan
  6. EV-06: Umpan balik pelanggan
  7. EV-07: Evaluasi internal
  8. EV-08: RTL / tindak lanjut
  9. EV-09: Dampak layanan

### evidence_links
- `id`
- `service_id`
- `checkpoint_id`
- `evidence_id`
- `created_at`
- Catatan: relasi many-to-many antara layanan dan checkpoint, lalu ke eviden.

**Catatan implementasi Langkah 7B:** tabel `evidence_links` di atas masih model konseptual dan belum dibuat. Migrasi 011 mengikat setiap `evidence_items` langsung ke tepat satu `service_id` dan satu `checkpoint_id`; pasangan tersebut dapat memiliki banyak eviden. Tidak ada tabel checklist/sel pada 7B.

### evidence_checkpoints (migrasi 011)
- `id` (`BIGINT UNSIGNED`, PK)
- `code` (`EV-01` sampai `EV-09`, unik)
- `name`

Seed label: EV-01 Identitas layanan; EV-02 Dokumen persyaratan; EV-03 Proses verifikasi awal; EV-04 Pelaksanaan SOP; EV-05 Hasil layanan; EV-06 Umpan balik pelanggan; EV-07 Evaluasi internal; EV-08 RTL / tindak lanjut; EV-09 Dampak layanan. Kriteria bukti per checkpoint belum ditetapkan dan MENUNGGU POKJA 6.

### evidence_items (migrasi 011)
- `id` (`BIGINT UNSIGNED`, PK)
- `service_id` → `services.id` (`ON DELETE RESTRICT`)
- `checkpoint_id` → `evidence_checkpoints.id` (`ON DELETE RESTRICT`)
- `status`: `DIUNGGAH`, `TERVERIFIKASI`, `PERLU_PERBAIKAN`
- `stored_name` (nama simpan acak unik), `original_name` (nama tersanitasi nullable)
- `mime` (`image/jpeg` atau `image/png`), `size_bytes`, `sha256`
- `uploaded_by` → `users.id` (`ON DELETE RESTRICT`)
- `verified_by` → `users.id` (nullable, `ON DELETE RESTRICT`)
- `verified_at`, `review_reason`, `created_at`

Satu eviden adalah satu berkas dan satu pasangan layanan-checkpoint; maksimal tiga berkas JPG/PNG per unggahan, masing-masing maksimal 5 MiB, disimpan semua-atau-tidak-sama-sekali. Hanya status `DIUNGGAH` yang dapat diputuskan dan keputusan final; perbaikan menggunakan unggahan baru tanpa versioning. Alasan `PERLU_PERBAIKAN` wajib, maksimal 500 karakter UTF-8 valid. Pengunggah boleh memverifikasi sendiri; daftar/detail memberi penanda tersebut. Hanya ADMIN dapat mengakses, mengelola, dan mengunduh. Audit hanya menyimpan aktor, aksi, ID eviden, layanan, checkpoint, status, MIME, ukuran, dan SHA-256; tidak menyimpan isi, nama asli, atau alasan.

### evaluations
- `id`
- `application_id`
- `evaluator_id`
- `score`
- `notes`
- `created_at`

### follow_up_actions
- `id`
- `complaint_id`
- `action_type`
- `action_note`
- `actor_id`
- `created_at`

### before_after
- `id`
- `service_id`
- `title`
- `before_text`
- `after_text`
- `created_by`
- `created_at`

### audit_logs
- `id`
- `entity_type`
- `entity_id`
- `action`
- `actor_id`
- `old_value_json`
- `new_value_json`
- `performed_at`

### id_counters
- `id`
- `name`
- `current_value`
- `updated_at`

## 4. Keharusan relasi dan aturan validasi

- `evidence_items.uploaded_by` dan `evidence_items.verified_by` wajib mereferensikan `users.id`.
- `application_documents.application_id` wajib mereferensikan `applications.id`; identitas pengunggah tidak disimpan terpisah karena pemilik tercatat pada pengajuan.
- `application_history` dan `complaint_history` harus bersifat append-only; trigger `BEFORE UPDATE` dan `BEFORE DELETE` menolak perubahan.
- Setiap tabel riwayat minimal mencatat `from_status`, `to_status`, `changed_by`, `note`, `changed_at`.
- Pada migrasi 011, relasi layanan-checkpoint-eviden langsung dinyatakan oleh FK `evidence_items.service_id` dan `evidence_items.checkpoint_id`; `evidence_links` belum diimplementasikan.

## 5. ENUM lengkap (konsep awal)

- `roles.code`: `GUEST`, `SISWA`, `GURU`, `ADMIN`
- `users.status`: `ACTIVE`, `INACTIVE`, `LOCKED`
- `services.mode`: `FORMULIR`, `INFORMASI`, `TATAP_MUKA`
- `services.status`: `DRAFT`, `PUBLISHED`, `ARCHIVED`
- `applications.channel`: `WEBSITE`, `SOSIAL_MEDIA`, `LANGSUNG`
- `applications.status`: `DIAJUKAN`, `DIVERIFIKASI`, `DIPROSES`, `SELESAI`, `DITOLAK`
- `complaints.status`: `DITERIMA`, `DIKLASIFIKASI`, `DITINDAKLANJUTI`, `SELESAI`, `DITOLAK`
- `surveys.status`: `DRAFT`, `PUBLISHED`, `CLOSED`
- `evidence_items.verification_status`: `BELUM_DIVERIFIKASI`, `VALID`, `PERLU_PERBAIKAN`, `DITOLAK`
- `login_attempts.result`: `SUCCESS`, `FAILED`, `LOCKED`
- `audit_logs.action`: `CREATE`, `UPDATE`, `DELETE`, `APPROVE`, `REJECT`, `VIEW`

## 6. Catatan

ERD ini menyajikan struktur konseptual yang cukup untuk Tahap 0: memvalidasi kebutuhan data, relasi utama, alasan audit, serta aturan append-only. Struktur detail SQL akan dibangun di tahap implementasi berikutnya setelah keputusan layanan final disetujui.
