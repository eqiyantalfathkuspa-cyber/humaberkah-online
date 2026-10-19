# FLOWCHART PELAYANAN HUMA BERKAH

## 1. Kategori dan layanan

### A. TATA USAHA (TU)
- TU-01: Srikandi
- TU-02: ADMINISTRASI MANDIRI

### B. KURIKULUM (KUR)
- KUR-01: Layanan Informasi Kurikulum dan Pembelajaran
- KUR-02: Layanan Pengajuan / Penyesuaian Administrasi Kurikulum

### C. KESISWAAN (KES)
- KES-01: Layanan Informasi BK / Konseling / Kesejahteraan Siswa
- KES-02: Layanan Pengajuan Kegiatan / Permohonan Siswa
- KES-03: Layanan Informasi PMBM / Pendaftaran / Layanan Informasi Siswa
- KES-04: Layanan Pengelolaan Data Kesiswaan / Mutasi / Kebutuhan Khusus

### D. SARANA PRASARANA (SAR)
- SAR-01: Layanan Sarana dan Prasarana

### E. HUMAS (HUM)
- HUM-01: Layanan Pengelolaan dan Koordinasi Program Hubungan Masyarakat
- HUM-02: Modul Pengaduan / Aspirasi / KAHAYAN AMANAH

## 2. Tahap layanan (8 tahap)

Untuk setiap layanan, berlaku urutan tahap berikut:

1. Akses layanan
2. Informasi layanan
3. Pengajuan / permintaan
4. Verifikasi
5. Proses layanan
6. Produk / hasil layanan
7. Umpan balik (KAHAYAN AMANAH)
8. Evaluasi, RTL & dampak (MADANI)

## 3. Ringkasan per layanan

| Kode | Nama layanan | Kategori | Mode awal (final) | Catatan |
|---|---|---|---|---|
| TU-01 | Srikandi | TATA USAHA | FORMULIR | pengajuan administrasi umum |
| TU-02 | ADMINISTRASI MANDIRI | TATA USAHA | FORMULIR | dokumen administratif |
| KUR-01 | Layanan Informasi Kurikulum dan Pembelajaran | KURIKULUM | INFORMASI | informasi dan orientasi |
| KUR-02 | Layanan Pengajuan / Penyesuaian Administrasi Kurikulum | KURIKULUM | FORMULIR | pengajuan/penyesuaian |
| KES-01 | Layanan Informasi BK / Konseling / Kesejahteraan Siswa | KESISWAAN | INFORMASI | data sensitif tidak disimpan |
| KES-02 | Layanan Pengajuan Kegiatan / Permohonan Siswa | KESISWAAN | FORMULIR | pengajuan layanan siswa |
| KES-03 | Layanan Informasi PMBM / Pendaftaran / Layanan Informasi Siswa | KESISWAAN | INFORMASI | halaman informasi |
| KES-04 | Layanan Pengelolaan Data Kesiswaan / Mutasi / Kebutuhan Khusus | KESISWAAN | FORMULIR | pengelolaan data administratif |
| SAR-01 | Layanan Sarana dan Prasarana | SARANA PRASARANA | FORMULIR | kebutuhan fasilitas |
| HUM-01 | Layanan Pengelolaan dan Koordinasi Program Hubungan Masyarakat | HUMAS | FORMULIR | pengelolaan koordinasi |
| HUM-02 | Modul Pengaduan / Aspirasi / KAHAYAN AMANAH | HUMAS | MODUL_PENGADUAN | pengaduan dan aspirasi |

## 4. Catatan penting

- Prototipe tidak menyimpan isi konseling BK dan data pribadi siswa yang sensitif.
- KES-01 dibatasi pada informasi dan penjadwalan sederhana, bukan konseling lengkap.
- HUM-02 pada prinsipnya adalah modul pengaduan/aspirasi, bukan layanan umum penuh.
- Mode awal final mengikuti keputusan Pokja 6: FORMULIR untuk TU-01, TU-02, KUR-02, KES-02, KES-04, SAR-01, dan HUM-01; INFORMASI untuk KUR-01, KES-01, dan KES-03; MODUL_PENGADUAN untuk HUM-02.
- TATAP_MUKA tetap tersedia sebagai pilihan mode layanan, tetapi belum digunakan oleh layanan awal.
- Label dan nama kategori harus selalu mengikuti dokumen ini dan tidak boleh diubah oleh agent.

## 5. Daftar 88 langkah layanan

Berikut representasi katalog langkah per layanan yang digunakan sebagai acuan data dalam migrasi `service_steps`. Setiap layanan berisi 8 tahap, dan setiap tahap dapat dibagi ke beberapa sublangkah. Total acuan yang dimaksud adalah 88 baris logis dalam skema data.

### TU-01
- 1. Akses layanan
- 2. Informasi layanan
- 3. Pengajuan permintaan
- 4. Verifikasi data
- 5. Proses layanan
- 6. Produk layanan
- 7. Umpan balik
- 8. Evaluasi dan dampak

### TU-02
- 1. Akses layanan
- 2. Informasi layanan
- 3. Pengajuan dokumen
- 4. Verifikasi dokumen
- 5. Proses administrasi
- 6. Produk dokumen
- 7. Umpan balik
- 8. Evaluasi dan dampak

### KUR-01
- 1. Akses layanan
- 2. Informasi kurikulum
- 3. Pemetaan kebutuhan
- 4. Verifikasi kebutuhan
- 5. Proses informasi pembelajaran
- 6. Hasil penjelasan
- 7. Umpan balik
- 8. Evaluasi dan dampak

### KUR-02
- 1. Akses layanan
- 2. Informasi layanan
- 3. Pengajuan perubahan
- 4. Verifikasi perubahan
- 5. Proses penyesuaian
- 6. Hasil penyesuaian
- 7. Umpan balik
- 8. Evaluasi dan dampak

### KES-01
- 1. Akses layanan
- 2. Informasi BK
- 3. Penjadwalan awal
- 4. Verifikasi kebutuhan
- 5. Proses layanan informasi
- 6. Hasil penjadwalan
- 7. Umpan balik
- 8. Evaluasi dan dampak

### KES-02
- 1. Akses layanan
- 2. Informasi layanan
- 3. Pengajuan kebutuhan
- 4. Verifikasi kebutuhan
- 5. Proses layanan
- 6. Hasil layanan
- 7. Umpan balik
- 8. Evaluasi dan dampak

### KES-03
- 1. Akses layanan
- 2. Informasi PMBM
- 3. Penjelasan prosedur
- 4. Verifikasi kebutuhan informasi
- 5. Proses informatif
- 6. Hasil informasi
- 7. Umpan balik
- 8. Evaluasi dan dampak

### KES-04
- 1. Akses layanan
- 2. Informasi layanan
- 3. Pengajuan kebutuhan data
- 4. Verifikasi kebutuhan data
- 5. Proses pemutakhiran data
- 6. Hasil dokumentasi data
- 7. Umpan balik
- 8. Evaluasi dan dampak

### SAR-01
- 1. Akses layanan
- 2. Informasi kebutuhan
- 3. Pengajuan kebutuhan sarana
- 4. Verifikasi kebutuhan
- 5. Proses pemenuhan sarana
- 6. Produk fasilitas
- 7. Umpan balik
- 8. Evaluasi dan dampak

### HUM-01
- 1. Akses layanan
- 2. Informasi program
- 3. Pengajuan koordinasi
- 4. Verifikasi kebutuhan
- 5. Proses koordinasi
- 6. Hasil koordinasi
- 7. Umpan balik
- 8. Evaluasi dan dampak

### HUM-02
- 1. Akses modul pengaduan
- 2. Informasi ruang laporan
- 3. Pengiriman aspirasi atau pengaduan
- 4. Verifikasi laporan
- 5. Proses penanganan
- 6. Produk tindak lanjut
- 7. Umpan balik
- 8. Evaluasi dan dampak

## 6. Catatan penggunaan

- Dokumen ini menjadi sumber label layanan dan kategori.
- Agent tidak boleh mengubah label, mempersempit, atau menambah kategori baru.
- Nama, kode layanan, dan urutan langkah harus konsisten dengan tabel ini agar data migrasi dan UI tetap koheren.
