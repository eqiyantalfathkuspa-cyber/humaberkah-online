CREATE TABLE IF NOT EXISTS madani_evaluations (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  service_id BIGINT UNSIGNED NOT NULL,
  period_start DATE NOT NULL,
  period_end DATE NOT NULL,
  summary VARCHAR(2000) NOT NULL,
  findings VARCHAR(2000) NULL,
  conclusion VARCHAR(1000) NULL,
  corrects_evaluation_id BIGINT UNSIGNED NULL,
  created_by BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_madani_evaluations_service_period (service_id, period_start, period_end),
  KEY idx_madani_evaluations_corrects (corrects_evaluation_id),
  CONSTRAINT fk_madani_evaluations_service FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_madani_evaluations_corrects FOREIGN KEY (corrects_evaluation_id) REFERENCES madani_evaluations(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_madani_evaluations_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
