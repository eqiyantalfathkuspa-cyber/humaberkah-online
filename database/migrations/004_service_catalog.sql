CREATE TABLE IF NOT EXISTS service_categories (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  code VARCHAR(16) NOT NULL,
  name VARCHAR(120) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_service_categories_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS services (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  code VARCHAR(16) NOT NULL,
  name VARCHAR(255) NOT NULL,
  category_id BIGINT UNSIGNED NOT NULL,
  mode ENUM('FORMULIR','INFORMASI','TATAP_MUKA','MODUL_PENGADUAN') NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_services_code (code),
  KEY idx_services_category (category_id),
  CONSTRAINT fk_services_category FOREIGN KEY (category_id) REFERENCES service_categories(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS service_steps (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  service_id BIGINT UNSIGNED NOT NULL,
  step_no TINYINT UNSIGNED NOT NULL,
  step_name VARCHAR(160) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_service_steps_service_step (service_id, step_no),
  CONSTRAINT fk_service_steps_service FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS service_standards (
  service_id BIGINT UNSIGNED NOT NULL,
  fee_text VARCHAR(500) NULL,
  sla_text VARCHAR(500) NULL,
  responsible_unit VARCHAR(500) NULL,
  required_documents_text TEXT NULL,
  updated_by BIGINT UNSIGNED NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (service_id),
  CONSTRAINT fk_service_standards_service FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_service_standards_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO service_categories (code, name) VALUES
  ('TU', 'Tata Usaha'),
  ('KUR', 'Kurikulum'),
  ('KES', 'Kesiswaan'),
  ('SAR', 'Sarana Prasarana'),
  ('HUM', 'Humasi')
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO services (code, name, category_id, mode)
SELECT seed.code, seed.name, categories.id, seed.mode
FROM (
  SELECT 'TU-01' AS code, 'Layanan Informasi Umum / Pengurusan Administrasi Umum' AS name, 'TU' AS category_code, 'FORMULIR' AS mode
  UNION ALL SELECT 'TU-02', 'Layanan Pengelolaan Dokumen dan Data Administratif', 'TU', 'FORMULIR'
  UNION ALL SELECT 'KUR-01', 'Layanan Informasi Kurikulum dan Pembelajaran', 'KUR', 'INFORMASI'
  UNION ALL SELECT 'KUR-02', 'Layanan Pengajuan / Penyesuaian Administrasi Kurikulum', 'KUR', 'FORMULIR'
  UNION ALL SELECT 'KES-01', 'Layanan Informasi BK / Konseling / Kesejahteraan Siswa', 'KES', 'INFORMASI'
  UNION ALL SELECT 'KES-02', 'Layanan Pengajuan Kegiatan / Permohonan Siswa', 'KES', 'FORMULIR'
  UNION ALL SELECT 'KES-03', 'Layanan Informasi PMBM / Pendaftaran / Layanan Informasi Siswa', 'KES', 'INFORMASI'
  UNION ALL SELECT 'KES-04', 'Layanan Pengelolaan Data Kesiswaan / Mutasi / Kebutuhan Khusus', 'KES', 'FORMULIR'
  UNION ALL SELECT 'SAR-01', 'Layanan Sarana dan Prasarana', 'SAR', 'FORMULIR'
  UNION ALL SELECT 'HUM-01', 'Layanan Pengelolaan dan Koordinasi Program Hubungan Masyarakat', 'HUM', 'FORMULIR'
  UNION ALL SELECT 'HUM-02', 'Modul Pengaduan / Aspirasi / KAHAYAN AMANAH', 'HUM', 'MODUL_PENGADUAN'
) AS seed
INNER JOIN service_categories AS categories ON categories.code = seed.category_code
ON DUPLICATE KEY UPDATE name = VALUES(name), category_id = VALUES(category_id);

INSERT INTO service_steps (service_id, step_no, step_name)
SELECT services.id, seed.step_no, seed.step_name
FROM (
  SELECT 'TU-01' AS service_code, 1 AS step_no, 'Akses layanan' AS step_name
  UNION ALL SELECT 'TU-01', 2, 'Informasi layanan'
  UNION ALL SELECT 'TU-01', 3, 'Pengajuan permintaan'
  UNION ALL SELECT 'TU-01', 4, 'Verifikasi data'
  UNION ALL SELECT 'TU-01', 5, 'Proses layanan'
  UNION ALL SELECT 'TU-01', 6, 'Produk layanan'
  UNION ALL SELECT 'TU-01', 7, 'Umpan balik'
  UNION ALL SELECT 'TU-01', 8, 'Evaluasi dan dampak'
  UNION ALL SELECT 'TU-02', 1, 'Akses layanan'
  UNION ALL SELECT 'TU-02', 2, 'Informasi layanan'
  UNION ALL SELECT 'TU-02', 3, 'Pengajuan dokumen'
  UNION ALL SELECT 'TU-02', 4, 'Verifikasi dokumen'
  UNION ALL SELECT 'TU-02', 5, 'Proses administrasi'
  UNION ALL SELECT 'TU-02', 6, 'Produk dokumen'
  UNION ALL SELECT 'TU-02', 7, 'Umpan balik'
  UNION ALL SELECT 'TU-02', 8, 'Evaluasi dan dampak'
  UNION ALL SELECT 'KUR-01', 1, 'Akses layanan'
  UNION ALL SELECT 'KUR-01', 2, 'Informasi kurikulum'
  UNION ALL SELECT 'KUR-01', 3, 'Pemetaan kebutuhan'
  UNION ALL SELECT 'KUR-01', 4, 'Verifikasi kebutuhan'
  UNION ALL SELECT 'KUR-01', 5, 'Proses informasi pembelajaran'
  UNION ALL SELECT 'KUR-01', 6, 'Hasil penjelasan'
  UNION ALL SELECT 'KUR-01', 7, 'Umpan balik'
  UNION ALL SELECT 'KUR-01', 8, 'Evaluasi dan dampak'
  UNION ALL SELECT 'KUR-02', 1, 'Akses layanan'
  UNION ALL SELECT 'KUR-02', 2, 'Informasi layanan'
  UNION ALL SELECT 'KUR-02', 3, 'Pengajuan perubahan'
  UNION ALL SELECT 'KUR-02', 4, 'Verifikasi perubahan'
  UNION ALL SELECT 'KUR-02', 5, 'Proses penyesuaian'
  UNION ALL SELECT 'KUR-02', 6, 'Hasil penyesuaian'
  UNION ALL SELECT 'KUR-02', 7, 'Umpan balik'
  UNION ALL SELECT 'KUR-02', 8, 'Evaluasi dan dampak'
  UNION ALL SELECT 'KES-01', 1, 'Akses layanan'
  UNION ALL SELECT 'KES-01', 2, 'Informasi BK'
  UNION ALL SELECT 'KES-01', 3, 'Penjadwalan awal'
  UNION ALL SELECT 'KES-01', 4, 'Verifikasi kebutuhan'
  UNION ALL SELECT 'KES-01', 5, 'Proses layanan informasi'
  UNION ALL SELECT 'KES-01', 6, 'Hasil penjadwalan'
  UNION ALL SELECT 'KES-01', 7, 'Umpan balik'
  UNION ALL SELECT 'KES-01', 8, 'Evaluasi dan dampak'
  UNION ALL SELECT 'KES-02', 1, 'Akses layanan'
  UNION ALL SELECT 'KES-02', 2, 'Informasi layanan'
  UNION ALL SELECT 'KES-02', 3, 'Pengajuan kebutuhan'
  UNION ALL SELECT 'KES-02', 4, 'Verifikasi kebutuhan'
  UNION ALL SELECT 'KES-02', 5, 'Proses layanan'
  UNION ALL SELECT 'KES-02', 6, 'Hasil layanan'
  UNION ALL SELECT 'KES-02', 7, 'Umpan balik'
  UNION ALL SELECT 'KES-02', 8, 'Evaluasi dan dampak'
  UNION ALL SELECT 'KES-03', 1, 'Akses layanan'
  UNION ALL SELECT 'KES-03', 2, 'Informasi PMBM'
  UNION ALL SELECT 'KES-03', 3, 'Penjelasan prosedur'
  UNION ALL SELECT 'KES-03', 4, 'Verifikasi kebutuhan informasi'
  UNION ALL SELECT 'KES-03', 5, 'Proses informatif'
  UNION ALL SELECT 'KES-03', 6, 'Hasil informasi'
  UNION ALL SELECT 'KES-03', 7, 'Umpan balik'
  UNION ALL SELECT 'KES-03', 8, 'Evaluasi dan dampak'
  UNION ALL SELECT 'KES-04', 1, 'Akses layanan'
  UNION ALL SELECT 'KES-04', 2, 'Informasi layanan'
  UNION ALL SELECT 'KES-04', 3, 'Pengajuan kebutuhan data'
  UNION ALL SELECT 'KES-04', 4, 'Verifikasi kebutuhan data'
  UNION ALL SELECT 'KES-04', 5, 'Proses pemutakhiran data'
  UNION ALL SELECT 'KES-04', 6, 'Hasil dokumentasi data'
  UNION ALL SELECT 'KES-04', 7, 'Umpan balik'
  UNION ALL SELECT 'KES-04', 8, 'Evaluasi dan dampak'
  UNION ALL SELECT 'SAR-01', 1, 'Akses layanan'
  UNION ALL SELECT 'SAR-01', 2, 'Informasi kebutuhan'
  UNION ALL SELECT 'SAR-01', 3, 'Pengajuan kebutuhan sarana'
  UNION ALL SELECT 'SAR-01', 4, 'Verifikasi kebutuhan'
  UNION ALL SELECT 'SAR-01', 5, 'Proses pemenuhan sarana'
  UNION ALL SELECT 'SAR-01', 6, 'Produk fasilitas'
  UNION ALL SELECT 'SAR-01', 7, 'Umpan balik'
  UNION ALL SELECT 'SAR-01', 8, 'Evaluasi dan dampak'
  UNION ALL SELECT 'HUM-01', 1, 'Akses layanan'
  UNION ALL SELECT 'HUM-01', 2, 'Informasi program'
  UNION ALL SELECT 'HUM-01', 3, 'Pengajuan koordinasi'
  UNION ALL SELECT 'HUM-01', 4, 'Verifikasi kebutuhan'
  UNION ALL SELECT 'HUM-01', 5, 'Proses koordinasi'
  UNION ALL SELECT 'HUM-01', 6, 'Hasil koordinasi'
  UNION ALL SELECT 'HUM-01', 7, 'Umpan balik'
  UNION ALL SELECT 'HUM-01', 8, 'Evaluasi dan dampak'
  UNION ALL SELECT 'HUM-02', 1, 'Akses modul pengaduan'
  UNION ALL SELECT 'HUM-02', 2, 'Informasi ruang laporan'
  UNION ALL SELECT 'HUM-02', 3, 'Pengiriman aspirasi atau pengaduan'
  UNION ALL SELECT 'HUM-02', 4, 'Verifikasi laporan'
  UNION ALL SELECT 'HUM-02', 5, 'Proses penanganan'
  UNION ALL SELECT 'HUM-02', 6, 'Produk tindak lanjut'
  UNION ALL SELECT 'HUM-02', 7, 'Umpan balik'
  UNION ALL SELECT 'HUM-02', 8, 'Evaluasi dan dampak'
) AS seed
INNER JOIN services ON services.code = seed.service_code
ON DUPLICATE KEY UPDATE step_name = VALUES(step_name);

INSERT INTO service_standards (service_id)
SELECT id FROM services
ON DUPLICATE KEY UPDATE service_id = VALUES(service_id);
