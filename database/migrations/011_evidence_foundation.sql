CREATE TABLE IF NOT EXISTS evidence_checkpoints (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  code VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  name VARCHAR(160) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_evidence_checkpoints_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO evidence_checkpoints (code, name) VALUES
  ('EV-01', 'Identitas layanan'),
  ('EV-02', 'Dokumen persyaratan'),
  ('EV-03', 'Proses verifikasi awal'),
  ('EV-04', 'Pelaksanaan SOP'),
  ('EV-05', 'Hasil layanan'),
  ('EV-06', 'Umpan balik pelanggan'),
  ('EV-07', 'Evaluasi internal'),
  ('EV-08', 'RTL / tindak lanjut'),
  ('EV-09', 'Dampak layanan')
ON DUPLICATE KEY UPDATE name = VALUES(name);

CREATE TABLE IF NOT EXISTS evidence_items (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  service_id BIGINT UNSIGNED NOT NULL,
  checkpoint_id BIGINT UNSIGNED NOT NULL,
  status ENUM('DIUNGGAH','TERVERIFIKASI','PERLU_PERBAIKAN') NOT NULL DEFAULT 'DIUNGGAH',
  stored_name VARCHAR(68) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  original_name VARCHAR(255) NULL,
  mime ENUM('image/jpeg','image/png') NOT NULL,
  size_bytes INT UNSIGNED NOT NULL,
  sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  uploaded_by BIGINT UNSIGNED NOT NULL,
  verified_by BIGINT UNSIGNED NULL,
  verified_at DATETIME NULL,
  review_reason VARCHAR(500) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_evidence_items_stored_name (stored_name),
  KEY idx_evidence_items_service_checkpoint_status (service_id, checkpoint_id, status, created_at),
  CONSTRAINT fk_evidence_items_service FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_evidence_items_checkpoint FOREIGN KEY (checkpoint_id) REFERENCES evidence_checkpoints(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_evidence_items_uploaded_by FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_evidence_items_verified_by FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
