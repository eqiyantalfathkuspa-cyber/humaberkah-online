CREATE TABLE IF NOT EXISTS application_documents (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  application_id BIGINT UNSIGNED NOT NULL,
  nama_asli VARCHAR(255) NULL,
  nama_acak VARCHAR(68) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  mime_terdeteksi ENUM('image/jpeg','image/png') NOT NULL,
  ukuran INT UNSIGNED NOT NULL,
  sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_application_documents_nama_acak (nama_acak),
  KEY idx_application_documents_application (application_id, id),
  CONSTRAINT fk_application_documents_application FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
