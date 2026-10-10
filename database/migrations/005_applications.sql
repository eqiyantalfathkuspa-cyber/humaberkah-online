CREATE TABLE IF NOT EXISTS applications (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  service_id BIGINT UNSIGNED NOT NULL,
  applicant_user_id BIGINT UNSIGNED NOT NULL,
  tracking_code CHAR(23) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  request_text TEXT NOT NULL,
  status ENUM('DIAJUKAN','DIPROSES','SELESAI','DITOLAK') NOT NULL DEFAULT 'DIAJUKAN',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_applications_tracking_code (tracking_code),
  KEY idx_applications_status_created (status, created_at),
  KEY idx_applications_applicant (applicant_user_id),
  CONSTRAINT fk_applications_service FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_applications_applicant FOREIGN KEY (applicant_user_id) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS application_history (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  application_id BIGINT UNSIGNED NOT NULL,
  old_status ENUM('DIAJUKAN','DIPROSES','SELESAI','DITOLAK') NULL,
  new_status ENUM('DIAJUKAN','DIPROSES','SELESAI','DITOLAK') NOT NULL,
  changed_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_application_history_application (application_id, id),
  CONSTRAINT fk_application_history_application FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_application_history_user FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* These triggers guard direct UPDATE/DELETE statements only; foreign-key cascades may bypass them.
   Creating the triggers requires the database user to have the TRIGGER privilege. */
DROP TRIGGER IF EXISTS trg_application_history_no_update;
CREATE TRIGGER trg_application_history_no_update BEFORE UPDATE ON application_history
FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'application_history append-only';

DROP TRIGGER IF EXISTS trg_application_history_no_delete;
CREATE TRIGGER trg_application_history_no_delete BEFORE DELETE ON application_history
FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'application_history append-only';
