CREATE TABLE IF NOT EXISTS madani_rtl (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  evaluation_id BIGINT UNSIGNED NOT NULL,
  action_description VARCHAR(2000) NOT NULL,
  responsible_name VARCHAR(150) NOT NULL,
  due_date DATE NOT NULL,
  created_by BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_madani_rtl_evaluation (evaluation_id),
  KEY idx_madani_rtl_due_date (due_date),
  CONSTRAINT fk_madani_rtl_evaluation FOREIGN KEY (evaluation_id) REFERENCES madani_evaluations(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_madani_rtl_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS madani_rtl_status_log (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  rtl_id BIGINT UNSIGNED NOT NULL,
  status ENUM('DIRENCANAKAN','BERJALAN','SELESAI') NOT NULL,
  note VARCHAR(500) NULL,
  changed_by BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_madani_rtl_status_log_rtl_id (rtl_id, id),
  CONSTRAINT fk_madani_rtl_status_log_rtl FOREIGN KEY (rtl_id) REFERENCES madani_rtl(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_madani_rtl_status_log_changed_by FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
