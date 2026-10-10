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
- survey_answers
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
- satu `surveys` memiliki banyak `survey_answers`
- satu `services` memiliki banyak `evidence_links`
- satu `evidence_checkpoints` memiliki banyak `evidence_links`
- satu `applications` memiliki banyak `evaluations`
- satu `complaints` memiliki banyak `follow_up_actions`
- satu `services` memiliki banyak `before_after`
- semua perubahan dicatat di `audit_logs`
- `id_counters` menyimpan nomor tiket dan token berikutnya

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
- `complaint_no`
- `reporter_id`
- `reporter_name`
- `subject`
- `body`
- `status`
- `created_at`
- `resolved_by`

### complaint_history
- `id`
- `complaint_id`
- `from_status`
- `to_status`
- `changed_by`
- `note`
- `changed_at`
- Catatan: tabel append-only; update dan delete ditolak.

### surveys
- `id`
- `service_id`
- `title`
- `status`
- `opened_at`
- `closed_at`

### survey_answers
- `id`
- `survey_id`
- `respondent_id` (nullable)
- `response_token`
- `answers_json`
- `submitted_at`

### evidence_items
- `id`
- `service_id`
- `title`
- `file_name`
- `storage_path`
- `uploaded_by`
- `verified_by`
- `verification_status`
- `created_at`
- `verified_at`

### evidence_checkpoints
- `id`
- `code`
- `label`
- `description`
- `sort_order`

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
- `evidence_links` harus menjaga banyak ke banyak secara eksplisit: satu layanan bisa punya banyak checkpoint, dan satu checklist bisa terhubung ke banyak eviden.

## 5. ENUM lengkap (konsep awal)

- `roles.code`: `GUEST`, `SISWA`, `GURU`, `ADMIN`
- `users.status`: `ACTIVE`, `INACTIVE`, `LOCKED`
- `services.mode`: `FORMULIR`, `INFORMASI`, `TATAP_MUKA`
- `services.status`: `DRAFT`, `PUBLISHED`, `ARCHIVED`
- `applications.channel`: `WEBSITE`, `SOSIAL_MEDIA`, `LANGSUNG`
- `applications.status`: `DIAJUKAN`, `DIVERIFIKASI`, `DIPROSES`, `SELESAI`, `DITOLAK`
- `complaints.status`: `BARU`, `DIPROSES`, `SELESAI`, `DITOLAK`
- `surveys.status`: `DRAFT`, `PUBLISHED`, `CLOSED`
- `evidence_items.verification_status`: `BELUM_DIVERIFIKASI`, `VALID`, `PERLU_PERBAIKAN`, `DITOLAK`
- `login_attempts.result`: `SUCCESS`, `FAILED`, `LOCKED`
- `audit_logs.action`: `CREATE`, `UPDATE`, `DELETE`, `APPROVE`, `REJECT`, `VIEW`

## 6. Catatan

ERD ini menyajikan struktur konseptual yang cukup untuk Tahap 0: memvalidasi kebutuhan data, relasi utama, alasan audit, serta aturan append-only. Struktur detail SQL akan dibangun di tahap implementasi berikutnya setelah keputusan layanan final disetujui.
