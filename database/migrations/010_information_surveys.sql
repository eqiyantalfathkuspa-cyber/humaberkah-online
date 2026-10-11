CREATE TABLE IF NOT EXISTS information_surveys (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  service_id BIGINT UNSIGNED NOT NULL,
  speed_rating TINYINT UNSIGNED NOT NULL,
  clarity_rating TINYINT UNSIGNED NOT NULL,
  courtesy_rating TINYINT UNSIGNED NOT NULL,
  suggestion VARCHAR(1000) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_information_surveys_service_created (service_id, created_at),
  CONSTRAINT fk_information_surveys_service FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS information_survey_ip_quotas (
  service_id BIGINT UNSIGNED NOT NULL,
  ip_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  window_started_at DATETIME NOT NULL,
  submission_count TINYINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (service_id, ip_hash),
  CONSTRAINT fk_information_survey_ip_quotas_service FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
