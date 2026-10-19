# Cadangan dan Pemulihan HUMA BERKAH

Panduan operasional ini belum dijalankan sebagai bagian dari Langkah 9. Simpan semua artefak cadangan di luar folder proyek dan jangan menaruh kredensial atau secret di repositori.

## Sebelum migrasi

1. Pastikan database sasaran dan environment sudah dikonfirmasi. Hentikan kiriman aplikasi serta perubahan DDL selama snapshot agar database dan berkas tidak berubah di tengah proses.
2. Siapkan `D:\HUMA-Backups` dan `mysql_config_editor` login-path yang hanya dapat mengakses database yang dicadangkan. Login-path menyimpan kredensial secara lokal; jangan menulis kata sandi ke command line atau dokumen.
3. Dari PowerShell di root proyek, buat dump setiap database yang akan dimigrasikan. Ulangi untuk `humamantap_test` bila database itu juga akan diubah.

```powershell
New-Item -ItemType Directory -Force -Path 'D:\HUMA-Backups' | Out-Null
$stamp = Get-Date -Format 'yyyyMMdd_HHmmss'
$dump = "D:\HUMA-Backups\humamantap_dev_$stamp.sql"
mysqldump --login-path=huma-backup --single-transaction --routines --triggers --events --hex-blob --default-character-set=utf8mb4 --result-file="$dump" humamantap_dev
$dumpExit = $LASTEXITCODE
if ($dumpExit -ne 0) { throw "mysqldump gagal dengan exit code $dumpExit" }
if (!(Test-Path -LiteralPath $dump) -or (Get-Item -LiteralPath $dump).Length -le 0) { throw 'Berkas dump kosong atau tidak ditemukan.' }
Get-FileHash -Algorithm SHA256 -LiteralPath $dump
```

4. Cadangkan lampiran dan eviden dari `storage/private` ke direktori terpisah di luar proyek. Hentikan unggahan selama penyalinan. Jangan memakai `/MIR`, karena opsi itu dapat menghapus isi tujuan.

```powershell
$privateBackup = "D:\HUMA-Backups\storage-private-$stamp"
robocopy '.\storage\private' $privateBackup /E /COPY:DAT /DCOPY:DAT /R:2 /W:2
$copyExit = $LASTEXITCODE
if ($copyExit -ge 8) { throw "Cadangan storage gagal dengan exit code $copyExit" }
Get-ChildItem '.\storage\private' -File -Recurse | Measure-Object Length -Sum
Get-ChildItem $privateBackup -File -Recurse | Measure-Object Length -Sum
```

Kode keluar Robocopy 0–7 bukan kegagalan; kode 8 atau lebih berarti ada kegagalan. Bandingkan jumlah dan total ukuran berkas sumber/tujuan, lalu verifikasi SHA-256 berkas penting atau buat manifest hash untuk seluruh pohon. Simpan salinan backup dan manifest pada media terpisah. Lindungi secret `security.ip_hash_key` atau `HUMA_IP_HASH_KEY` secara terpisah di secret store; jangan menyalinnya ke repositori.

## Verifikasi dan uji pemulihan

Ukuran non-nol dan hash file membuktikan file dump tidak berubah sejak hash dibuat, bukan bahwa dump dapat dipulihkan. Uji pemulihan berkala ke database baru yang terpisah dari `humamantap_dev` dan `humamantap_test`.

Di Command Prompt (CMD), buat target kosong dan impor dump yang dipilih. `mysql` dan `mysqldump` harus berasal dari instalasi MySQL Laragon yang sesuai.

```cmd
mysql --login-path=huma-restore -e "CREATE DATABASE humamantap_restore_check CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
mysql --login-path=huma-restore humamantap_restore_check < D:\HUMA-Backups\humamantap_dev_YYYYMMDD_HHMMSS.sql
```

Pilih database pemulihan baru setiap pengujian; jangan mengimpor dump ke database yang berisi data yang perlu dipertahankan. Setelah impor, hubungkan klien SQL ke `humamantap_restore_check` dan periksa ledger:

```sql
SELECT sm.migration_name, sm.applied_at, sr.status, sr.checksum,
       sr.statement_count, sr.last_completed_statement, sr.error_message
FROM schema_migrations AS sm
LEFT JOIN schema_migration_runs AS sr ON sr.migration_name = sm.migration_name
ORDER BY sm.migration_name;

SELECT sr.migration_name, sr.status
FROM schema_migration_runs AS sr
LEFT JOIN schema_migrations AS sm ON sm.migration_name = sr.migration_name
WHERE sr.status <> 'APPLIED' OR sm.migration_name IS NULL;
```

Pastikan daftar migrasi sesuai dengan backup, status run yang ada adalah `APPLIED`, checksum/checkpoint konsisten dengan berkas migrasi yang disetujui, dan tidak ada run `RUNNING`/`FAILED` atau row ledger yang tidak berpasangan. Migrator hanya memiliki spesifikasi signature untuk objek yang didaftarkan; hasil signature `unknown` berarti struktur migrasi itu belum divalidasi oleh signature, bukan berarti skema terbukti cocok. Periksa skema aktual dan ledger; jangan menjalankan ulang migrasi untuk sekadar menguji backup.

Pulihkan `storage/private` ke direktori privat deployment dan verifikasi jumlah, ukuran, serta hash berkas terhadap manifest. Uji unduh hanya melalui rute terlindungi dengan akun uji yang sah. Jangan arahkan document root ke folder storage. Setelah pemulihan, gunakan kembali secret HMAC dari secret store atau rencanakan rotasi; rotasi membuat namespace kuota baru dan baris lama kedaluwarsa sesuai jendela satu jam.

Catat waktu backup/pemulihan, database target, hash dump, hasil ledger, jumlah/hasil verifikasi berkas, operator, dan lokasi penyimpanan. Jangan mencatat kata sandi, isi konfigurasi, IP, atau hash IP kuota.