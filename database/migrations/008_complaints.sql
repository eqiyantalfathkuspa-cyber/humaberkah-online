CREATE TABLE IF NOT EXISTS complaints (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  ticket_no VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  track_token CHAR(23) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  jenis ENUM('PENGADUAN','ASPIRASI','SARAN','PERTANYAAN') NOT NULL,
  isi VARCHAR(4000) NOT NULL,
  kontak VARCHAR(120) NULL,
  user_id BIGINT UNSIGNED NULL,
  status ENUM('DITERIMA','DIKLASIFIKASI','DITINDAKLANJUTI','SELESAI','DITOLAK') NOT NULL DEFAULT 'DITERIMA',
  response_note TEXT NULL,
  reject_reason VARCHAR(4000) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_complaints_ticket_no (ticket_no),
  UNIQUE KEY uq_complaints_track_token (track_token),
  KEY idx_complaints_status_type_created (status, jenis, created_at),
  KEY idx_complaints_user_created (user_id, created_at),
  CONSTRAINT fk_complaints_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS complaint_history (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  complaint_id BIGINT UNSIGNED NOT NULL,
  event_type ENUM('STATUS','TANGGAPAN') NOT NULL,
  old_status ENUM('DITERIMA','DIKLASIFIKASI','DITINDAKLANJUTI','SELESAI','DITOLAK') NULL,
  new_status ENUM('DITERIMA','DIKLASIFIKASI','DITINDAKLANJUTI','SELESAI','DITOLAK') NULL,
  changed_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_complaint_history_complaint (complaint_id, id),
  CONSTRAINT fk_complaint_history_complaint FOREIGN KEY (complaint_id) REFERENCES complaints(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_complaint_history_user FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TRIGGER IF EXISTS trg_complaint_history_no_update;
CREATE TRIGGER trg_complaint_history_no_update BEFORE UPDATE ON complaint_history
FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'complaint_history append-only';

DROP TRIGGER IF EXISTS trg_complaint_history_no_delete;
CREATE TRIGGER trg_complaint_history_no_delete BEFORE DELETE ON complaint_history
FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'complaint_history append-only';

CREATE TABLE IF NOT EXISTS complaint_ip_quotas (
  ip_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  window_started_at DATETIME NOT NULL,
  submission_count TINYINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (ip_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
