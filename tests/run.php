<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../app/Core/SqlStatementParser.php';

use App\Core\Auth;
use App\Core\Config;
use App\Core\Csrf;
use App\Core\Db;
use App\Core\IdGen;
use App\Core\Router;
use App\Core\SqlStatementParser;

$failed = 0;
ob_start();

function check(string $name, bool $ok, string $detail = ''): void
{
    global $failed;
    echo ($ok ? '[LOLOS] ' : '[GAGAL] ') . $name . ($ok || $detail === '' ? '' : ' :: ' . $detail) . PHP_EOL;
    if (!$ok) {
        $failed++;
    }
}

function runMigrationProcess(string $directory, string $database = 'test', ?string $crashAt = null): array
{
    $variableName = 'HUMA_MIGRATOR_TEST_CRASH_AT';
    $originalValue = getenv($variableName);
    if ($crashAt === null) {
        putenv($variableName);
    } else {
        putenv($variableName . '=' . $crashAt);
    }

    try {
        $command = escapeshellarg(PHP_BINARY) . ' '
            . escapeshellarg(__DIR__ . '/../bin/jalankan-migrasi.php')
            . ' ' . escapeshellarg($database) . ' ' . escapeshellarg($directory);
        $process = proc_open($command, [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes, __DIR__);
    } finally {
        if ($originalValue === false) {
            putenv($variableName);
        } else {
            putenv($variableName . '=' . $originalValue);
        }
    }

    if (!is_resource($process)) {
        return [1, 'Tidak dapat memulai proses migrator.'];
    }
    fclose($pipes[0]);
    $stdout = (string) stream_get_contents($pipes[1]);
    $stderr = (string) stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);
    return [$exitCode, $stdout . $stderr];
}

$cmd = 'php ' . escapeshellarg(__DIR__ . '/../bin/jalankan-migrasi.php') . ' test';
exec($cmd, $migOutput, $migCode);
check('Migrasi basis data pengujian berjalan dengan benar', $migCode === 0, implode(PHP_EOL, $migOutput));

ob_start();
$homeResult = Router::dispatch('GET', '/');
$homeOutput = ob_get_clean();
check('Router 200 untuk rute dikenal', $homeResult['status'] === 200 && str_contains($homeOutput, 'HUMA MANTAP'), $homeOutput);

ob_start();
$notFound = Router::dispatch('GET', '/rute-tidak-ada');
$notFoundOutput = ob_get_clean();
check('Router 404 untuk rute tidak dikenal', $notFound['status'] === 404 && str_contains($notFoundOutput, 'Halaman tidak ditemukan.'), $notFoundOutput);

ob_start();
$wrongMethod = Router::dispatch('GET', '/logout');
$wrongMethodOutput = ob_get_clean();
check('Router 405 untuk metode salah', $wrongMethod['status'] === 405 && str_contains($wrongMethodOutput, 'Metode HTTP tidak diizinkan'), $wrongMethodOutput);

$customRoutes = [
    'GET' => [
        '/contoh/{kode}' => [
            'handler' => static function (array $params = []) {
                echo e((string) ($params['kode'] ?? ''));
            },
            'ability' => 'home.public',
        ],
    ],
];
ob_start();
$parameterized = Router::dispatch('GET', '/contoh/ABC-42', $customRoutes);
$parameterizedOutput = ob_get_clean();
check('Router mendukung pola parameter', $parameterized['status'] === 200 && $parameterizedOutput === 'ABC-42', $parameterizedOutput);

$escape = e('<script>"x"</script>');
check('e() meng-escape script dan tanda kutip', $escape === '&lt;script&gt;&quot;x&quot;&lt;/script&gt;');

$rawInput = "' OR 1=1 --";
$preparedValue = Db::one('SELECT ? AS v', [$rawInput], 'test')['v'];
check('Db menolak injeksi lewat prepared statement', $preparedValue === $rawInput);

$commentStatements = SqlStatementParser::parse(
    "-- komentar satu\nSELECT 1; /* komentar blok */ SELECT 2;\n# komentar hash\nSELECT 3; -- komentar setelah statement\nSELECT 4;"
);
check(
    'Parser migrasi melewati komentar --, /* */ dan #',
    $commentStatements === ['SELECT 1', 'SELECT 2', 'SELECT 3', 'SELECT 4'],
    json_encode($commentStatements)
);
$quotedSql = 'SELECT \'it\'\'s; -- tetap literal\', "double"" quote; /* tetap literal */", `back``tick; # tetap identifier` FROM sample; SELECT 2;';
$quotedStatements = SqlStatementParser::parse($quotedSql);
check(
    'Parser migrasi mempertahankan string ber-escape, identifier backtick, dan titik koma kutipan',
    $quotedStatements === [
        'SELECT \'it\'\'s; -- tetap literal\', "double"" quote; /* tetap literal */", `back``tick; # tetap identifier` FROM sample',
        'SELECT 2',
    ],
    json_encode($quotedStatements)
);
$backslashEscapedSql = "SELECT 'a\\'b'; SELECT 2;";
$noBackslashSql = "SELECT 'a\\'; SELECT 2;";
$ansiIdentifierSql = 'SELECT "name\"; SELECT 2;';
$optimizerHintSql = 'SELECT /*+ INDEX(t idx) */ 1;';
$optimizerHintStatements = SqlStatementParser::parse($optimizerHintSql);
check(
    'Parser migrasi mempertahankan optimizer hint',
    $optimizerHintStatements === ['SELECT /*+ INDEX(t idx) */ 1'],
    json_encode($optimizerHintStatements)
);
$triggerSql = "CREATE TRIGGER trg_probe BEFORE INSERT ON sample FOR EACH ROW BEGIN "
    . "IF (NEW.a = 1) THEN CASE WHEN NEW.b = 2 THEN SET @x = 'END IF; CASE -- still literal'; ELSE SET @x = 2; END CASE; "
    . "ELSEIF NEW.a = 3 THEN BEGIN SET @nested = 1; END; END IF; "
    . "SET @done = 1; END;";
$triggerStatements = SqlStatementParser::parse($triggerSql);
check(
    'Parser migrasi mempertahankan IF, CASE, blok bersarang, dan seluruh isi trigger',
    $triggerStatements === [substr($triggerSql, 0, -1)],
    json_encode($triggerStatements)
);
$parserRejects = static function (string $sql, bool $noBackslashEscapes = false, bool $ansiQuotes = false): bool {
    try {
        SqlStatementParser::parse($sql, $noBackslashEscapes, $ansiQuotes);
    } catch (\RuntimeException) {
        return true;
    }
    return false;
};
check(
    'Parser migrasi mengikuti mode escape string MySQL',
    SqlStatementParser::parse($backslashEscapedSql) === ["SELECT 'a\\'b'", 'SELECT 2']
        && SqlStatementParser::parse($noBackslashSql, true) === ["SELECT 'a\\'", 'SELECT 2']
        && SqlStatementParser::parse($ansiIdentifierSql, false, true) === ['SELECT "name\\"', 'SELECT 2']
        && $parserRejects($backslashEscapedSql, true)
        && $parserRejects($noBackslashSql)
);
$malformedSql = [
    "SELECT 'string tidak ditutup",
    'SELECT `identifier tidak ditutup',
    'SELECT 1 /* komentar tidak ditutup',
    'CREATE TRIGGER t BEFORE INSERT ON x FOR EACH ROW BEGIN SET @x = 1;',
    'CREATE TRIGGER t BEFORE INSERT ON x FOR EACH ROW BEGIN IF NEW.a = 1 THEN SET @x = 1; END;',
    'CREATE PROCEDURE p() BEGIN SELECT 1; END;',
    'SELECT /*!80000 + 1 */ 1;',
    'DELIMITER //',
];
check(
    'Parser migrasi menolak kutipan, komentar, blok, dan bentuk SQL yang tidak didukung bila tidak lengkap',
    count(array_filter($malformedSql, $parserRejects)) === count($malformedSql)
);
$migrationStatementCounts = [];
foreach (glob(__DIR__ . '/../database/migrations/*.sql') as $migrationFile) {
    $migrationStatementCounts[basename($migrationFile)] = count(
        SqlStatementParser::parse((string) file_get_contents($migrationFile))
    );
}
ksort($migrationStatementCounts);
check(
    'Parser migrasi menerima seluruh file migrasi aktual sesuai jumlah statement',
    $migrationStatementCounts === [
        '001_schema_init.sql' => 2,
        '002_auth_schema.sql' => 7,
        '003_user_session_revocation.sql' => 1,
        '004_service_catalog.sql' => 8,
        '005_applications.sql' => 6,
        '006_application_tracking_attempts.sql' => 1,
        '007_application_documents.sql' => 1,
        '008_complaints.sql' => 7,
        '009_surveys.sql' => 2,
    ],
    json_encode($migrationStatementCounts)
);

$adoptionSuffix = bin2hex(random_bytes(4));
$adoptionDir = __DIR__ . DIRECTORY_SEPARATOR . '.migration_adoption_' . $adoptionSuffix;
$adoptionMigration = '001_schema_init.sql';
$adoptionBackupTable = 'id_counters_adoption_backup_' . $adoptionSuffix;
$baselineAdoptionLedger = Db::one(
    'SELECT migration_name, applied_at FROM schema_migrations WHERE migration_name = ?',
    [$adoptionMigration],
    'test'
);
$baselineAdoptionRun = Db::one(
    'SELECT migration_name, status, checksum, statement_count, last_completed_statement, error_message, started_at, updated_at, completed_at FROM schema_migration_runs WHERE migration_name = ?',
    [$adoptionMigration],
    'test'
);
mkdir($adoptionDir);
file_put_contents($adoptionDir . DIRECTORY_SEPARATOR . $adoptionMigration, 'SELECT 1;');
try {
    Db::pdo('test')->exec('RENAME TABLE id_counters TO ' . $adoptionBackupTable);
    Db::run(
        'DELETE FROM schema_migrations WHERE migration_name = ?',
        [$adoptionMigration],
        'test'
    );
    Db::run(
        'DELETE FROM schema_migration_runs WHERE migration_name = ?',
        [$adoptionMigration],
        'test'
    );
    Db::pdo('test')->exec(
        'CREATE TABLE id_counters (name VARCHAR(64) NOT NULL PRIMARY KEY) ENGINE=InnoDB'
    );
    [$partialAdoptionCode, $partialAdoptionOutput] = runMigrationProcess($adoptionDir);
    $partialAdoptionRun = Db::one(
        'SELECT 1 FROM schema_migration_runs WHERE migration_name = ?',
        [$adoptionMigration],
        'test'
    );
    check(
        'Migrator menghentikan skema pra-ledger parsial tanpa menandai migrasi selesai',
        $partialAdoptionCode !== 0
            && str_contains($partialAdoptionOutput, 'artefak skemanya partial')
            && $partialAdoptionRun === null,
        $partialAdoptionOutput
    );
    Db::pdo('test')->exec('DROP TABLE id_counters');

    Db::pdo('test')->exec(
        'CREATE TABLE id_counters (name VARCHAR(64) NOT NULL PRIMARY KEY, current_value BIGINT UNSIGNED NOT NULL DEFAULT 0, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB'
    );
    [$completeOrphanCode, $completeOrphanOutput] = runMigrationProcess($adoptionDir);
    check(
        'Migrator juga menghentikan artefak skema lengkap tanpa ledger dan tidak mengadopsinya otomatis',
        $completeOrphanCode !== 0
            && str_contains($completeOrphanOutput, 'artefak skemanya complete')
            && Db::one(
                'SELECT 1 FROM schema_migration_runs WHERE migration_name = ?',
                [$adoptionMigration],
                'test'
            ) === null,
        $completeOrphanOutput
    );
    Db::pdo('test')->exec('DROP TABLE id_counters');

    Db::run(
        'INSERT INTO schema_migrations (migration_name) VALUES (?)',
        [$adoptionMigration],
        'test'
    );
    [$legacyLedgerCode, $legacyLedgerOutput] = runMigrationProcess($adoptionDir);
    check(
        'Migrator meminta rekonsiliasi untuk ledger migrator lama tanpa checksum/checkpoint baru',
        $legacyLedgerCode !== 0
            && str_contains($legacyLedgerOutput, 'ledger lama tanpa checksum/checkpoint')
            && Db::one(
                'SELECT 1 FROM schema_migration_runs WHERE migration_name = ?',
                [$adoptionMigration],
                'test'
            ) === null,
        $legacyLedgerOutput
    );
    Db::run(
        'DELETE FROM schema_migrations WHERE migration_name = ?',
        [$adoptionMigration],
        'test'
    );
} finally {
    Db::pdo('test')->exec('DROP TABLE IF EXISTS id_counters');
    if (Db::one(
        'SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?',
        [$adoptionBackupTable],
        'test'
    ) !== null) {
        Db::pdo('test')->exec('RENAME TABLE ' . $adoptionBackupTable . ' TO id_counters');
    }
    Db::run('DELETE FROM schema_migrations WHERE migration_name = ?', [$adoptionMigration], 'test');
    Db::run('DELETE FROM schema_migration_runs WHERE migration_name = ?', [$adoptionMigration], 'test');
    if ($baselineAdoptionLedger !== null) {
        Db::run(
            'INSERT INTO schema_migrations (migration_name, applied_at) VALUES (?, ?)',
            [$baselineAdoptionLedger['migration_name'], $baselineAdoptionLedger['applied_at']],
            'test'
        );
    }
    if ($baselineAdoptionRun !== null) {
        Db::run(
            'INSERT INTO schema_migration_runs (migration_name, status, checksum, statement_count, last_completed_statement, error_message, started_at, updated_at, completed_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            array_values($baselineAdoptionRun),
            'test'
        );
    }
    $adoptionFile = $adoptionDir . DIRECTORY_SEPARATOR . $adoptionMigration;
    if (is_file($adoptionFile)) {
        unlink($adoptionFile);
    }
    if (is_dir($adoptionDir)) {
        rmdir($adoptionDir);
    }
}

$cmd = 'php ' . escapeshellarg(__DIR__ . '/../bin/jalankan-migrasi.php') . ' test';
exec($cmd, $migOutput, $migCode);
$afterFirst = (int) Db::one('SELECT COUNT(*) AS c FROM schema_migrations WHERE migration_name = ?', ['001_schema_init.sql'], 'test')['c'];
exec($cmd, $migOutputSecond, $migCodeSecond);
$afterSecond = (int) Db::one('SELECT COUNT(*) AS c FROM schema_migrations WHERE migration_name = ?', ['001_schema_init.sql'], 'test')['c'];
check('Migrasi 001 berjalan tanpa duplikasi', $migCode === 0 && $migCodeSecond === 0 && $afterFirst === 1 && $afterSecond === $afterFirst, implode(PHP_EOL, array_merge($migOutput, $migOutputSecond)));

$trackingBackupTable = 'application_tracking_attempts_backup_' . bin2hex(random_bytes(4));
Db::pdo('test')->exec('RENAME TABLE application_tracking_attempts TO ' . $trackingBackupTable);
try {
    exec($cmd . ' 2>&1', $incompleteAppliedOutput, $incompleteAppliedCode);
} finally {
    Db::pdo('test')->exec('RENAME TABLE ' . $trackingBackupTable . ' TO application_tracking_attempts');
}
check(
    'Migrator mendeteksi tabel skema hilang meski ledger migrasi menyatakan APPLIED',
    $incompleteAppliedCode !== 0
        && str_contains(implode(PHP_EOL, $incompleteAppliedOutput), 'tidak konsisten')
        && Db::one(
            'SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?',
            ['application_tracking_attempts'],
            'test'
        ) !== null
);

$trackingColumnFailure = false;
Db::pdo('test')->exec(
    'ALTER TABLE application_tracking_attempts MODIFY ip_address VARCHAR(46) NOT NULL'
);
try {
    exec($cmd . ' 2>&1', $trackingColumnOutput, $trackingColumnCode);
    $trackingColumnFailure = $trackingColumnCode !== 0
        && str_contains(implode(PHP_EOL, $trackingColumnOutput), 'tidak konsisten');
} finally {
    Db::pdo('test')->exec(
        'ALTER TABLE application_tracking_attempts MODIFY ip_address VARCHAR(45) NOT NULL'
    );
}
check(
    'Migrator menolak tipe/ukuran kolom IP yang berubah meski nama kolom tetap ada',
    $trackingColumnFailure,
    implode(PHP_EOL, $trackingColumnOutput)
);

$trackingIndexFailure = false;
Db::pdo('test')->exec(
    'ALTER TABLE application_tracking_attempts DROP INDEX idx_application_tracking_attempts_ip_type_time'
);
Db::pdo('test')->exec(
    'ALTER TABLE application_tracking_attempts ADD INDEX idx_application_tracking_attempts_ip_type_time (attempt_type, ip_address, created_at)'
);
try {
    exec($cmd . ' 2>&1', $trackingIndexOutput, $trackingIndexCode);
    $trackingIndexFailure = $trackingIndexCode !== 0
        && str_contains(implode(PHP_EOL, $trackingIndexOutput), 'tidak konsisten');
} finally {
    Db::pdo('test')->exec(
        'ALTER TABLE application_tracking_attempts DROP INDEX idx_application_tracking_attempts_ip_type_time'
    );
    Db::pdo('test')->exec(
        'ALTER TABLE application_tracking_attempts ADD INDEX idx_application_tracking_attempts_ip_type_time (ip_address, attempt_type, created_at)'
    );
}
check(
    'Migrator menolak indeks bernama benar dengan urutan kolom berbeda',
    $trackingIndexFailure,
    implode(PHP_EOL, $trackingIndexOutput)
);

$foreignKeyFailure = false;
Db::pdo('test')->exec(
    'ALTER TABLE applications DROP FOREIGN KEY fk_applications_service'
);
Db::pdo('test')->exec(
    'ALTER TABLE applications ADD CONSTRAINT fk_applications_service FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE ON UPDATE CASCADE'
);
try {
    exec($cmd . ' 2>&1', $foreignKeyOutput, $foreignKeyCode);
    $foreignKeyFailure = $foreignKeyCode !== 0
        && str_contains(implode(PHP_EOL, $foreignKeyOutput), 'tidak konsisten');
} finally {
    Db::pdo('test')->exec(
        'ALTER TABLE applications DROP FOREIGN KEY fk_applications_service'
    );
    Db::pdo('test')->exec(
        'ALTER TABLE applications ADD CONSTRAINT fk_applications_service FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE RESTRICT ON UPDATE CASCADE'
    );
}
check(
    'Migrator menolak foreign key bernama benar dengan aturan DELETE berbeda',
    $foreignKeyFailure,
    implode(PHP_EOL, $foreignKeyOutput)
);

$triggerFailure = false;
Db::pdo('test')->exec('DROP TRIGGER trg_application_history_no_update');
Db::pdo('test')->exec(
    'CREATE TRIGGER trg_application_history_no_update BEFORE UPDATE ON application_history FOR EACH ROW SET @huma_test_trigger_mismatch = 1'
);
try {
    exec($cmd . ' 2>&1', $triggerOutput, $triggerCode);
    $triggerFailure = $triggerCode !== 0
        && str_contains(implode(PHP_EOL, $triggerOutput), 'tidak konsisten');
} finally {
    Db::pdo('test')->exec('DROP TRIGGER trg_application_history_no_update');
    Db::pdo('test')->exec(
        "CREATE TRIGGER trg_application_history_no_update BEFORE UPDATE ON application_history FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'application_history append-only'"
    );
}
check(
    'Migrator menolak trigger bernama benar dengan aksi berbeda',
    $triggerFailure,
    implode(PHP_EOL, $triggerOutput)
);

$ledgerFailure = false;
Db::pdo('test')->exec(
    'ALTER TABLE schema_migration_runs MODIFY checksum CHAR(65) CHARACTER SET ascii COLLATE ascii_bin NOT NULL'
);
try {
    exec($cmd . ' 2>&1', $ledgerOutput, $ledgerCode);
    $ledgerFailure = $ledgerCode !== 0
        && str_contains(implode(PHP_EOL, $ledgerOutput), 'Struktur ledger migrasi tidak sesuai');
} finally {
    Db::pdo('test')->exec(
        'ALTER TABLE schema_migration_runs MODIFY checksum CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL'
    );
}
check(
    'Migrator menolak struktur kolom ledger yang berubah',
    $ledgerFailure,
    implode(PHP_EOL, $ledgerOutput)
);

$recoverySuffix = bin2hex(random_bytes(4));
$recoveryRoot = __DIR__ . DIRECTORY_SEPARATOR . '.migration_recovery_' . $recoverySuffix;
$successDir = $recoveryRoot . DIRECTORY_SEPARATOR . 'success';
$beforeFailureDir = $recoveryRoot . DIRECTORY_SEPARATOR . 'before_failure';
$partialFailureDir = $recoveryRoot . DIRECTORY_SEPARATOR . 'partial_failure';
$crashAfterFirstDir = $recoveryRoot . DIRECTORY_SEPARATOR . 'crash_after_first';
$crashBeforeCheckpointDir = $recoveryRoot . DIRECTORY_SEPARATOR . 'crash_before_checkpoint';
$crashBeforeFinalizeDir = $recoveryRoot . DIRECTORY_SEPARATOR . 'crash_before_finalize';
$crashAfterFinalizeDir = $recoveryRoot . DIRECTORY_SEPARATOR . 'crash_after_finalize';
$ledgerFailureDir = $recoveryRoot . DIRECTORY_SEPARATOR . 'ledger_failure';
$concurrentDir = $recoveryRoot . DIRECTORY_SEPARATOR . 'concurrent';
$successName = 'recovery_success_' . $recoverySuffix . '.sql';
$beforeFailureName = 'recovery_before_' . $recoverySuffix . '.sql';
$partialFailureName = 'recovery_partial_' . $recoverySuffix . '.sql';
$crashAfterFirstName = 'recovery_crash_first_' . $recoverySuffix . '.sql';
$crashBeforeCheckpointName = 'recovery_crash_checkpoint_' . $recoverySuffix . '.sql';
$crashBeforeFinalizeName = 'recovery_crash_finalize_' . $recoverySuffix . '.sql';
$crashAfterFinalizeName = 'recovery_crash_after_finalize_' . $recoverySuffix . '.sql';
$ledgerFailureName = 'recovery_ledger_failure_' . $recoverySuffix . '.sql';
$concurrentName = 'recovery_concurrent_' . $recoverySuffix . '.sql';
$successTable = 'migration_probe_success_' . $recoverySuffix;
$partialTable = 'migration_probe_partial_' . $recoverySuffix;
$crashFirstTable = 'migration_probe_crash_first_' . $recoverySuffix;
$crashSecondTable = 'migration_probe_crash_second_' . $recoverySuffix;
$checkpointFirstTable = 'migration_probe_checkpoint_first_' . $recoverySuffix;
$checkpointSecondTable = 'migration_probe_checkpoint_second_' . $recoverySuffix;
$finalizeFirstTable = 'migration_probe_finalize_first_' . $recoverySuffix;
$finalizeSecondTable = 'migration_probe_finalize_second_' . $recoverySuffix;
$afterFinalizeTable = 'migration_probe_after_finalize_' . $recoverySuffix;
$ledgerBackupTable = 'schema_migration_runs_backup_' . $recoverySuffix;
$concurrentTable = 'migration_probe_concurrent_' . $recoverySuffix;
$migrationRun = static fn (string $directory, ?string $crashAt = null): array => runMigrationProcess($directory, 'test', $crashAt);
$tableExists = static fn (string $table): bool => Db::one(
    'SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?',
    [$table],
    'test'
) !== null;
foreach ([$successDir, $beforeFailureDir, $partialFailureDir, $crashAfterFirstDir, $crashBeforeCheckpointDir, $crashBeforeFinalizeDir, $crashAfterFinalizeDir, $ledgerFailureDir, $concurrentDir] as $fixtureDir) {
    mkdir($fixtureDir, 0777, true);
}
try {
    file_put_contents(
        $successDir . DIRECTORY_SEPARATOR . $successName,
        'CREATE TABLE ' . $successTable . ' (id INT NOT NULL PRIMARY KEY); INSERT INTO ' . $successTable . ' (id) VALUES (1);'
    );
    [$successCode, $successOutput] = $migrationRun($successDir);
    [$successRerunCode, $successRerunOutput] = $migrationRun($successDir);
    $successRun = Db::one(
        'SELECT status, last_completed_statement, statement_count FROM schema_migration_runs WHERE migration_name = ?',
        [$successName],
        'test'
    );
    $successLedgerCount = (int) Db::one(
        'SELECT COUNT(*) AS c FROM schema_migrations WHERE migration_name = ?',
        [$successName],
        'test'
    )['c'];
    $successRows = $tableExists($successTable)
        ? (int) Db::one('SELECT COUNT(*) AS c FROM ' . $successTable, [], 'test')['c']
        : -1;
    check(
        'Migrasi fixture sukses tercatat APPLIED dan rerun aman tanpa mengulang DDL/DML',
        $successCode === 0
            && $successRerunCode === 0
            && ($successRun['status'] ?? null) === 'APPLIED'
            && (int) ($successRun['last_completed_statement'] ?? -1) === 2
            && (int) ($successRun['statement_count'] ?? -1) === 2
            && $successLedgerCount === 1
            && $successRows === 1,
        $successOutput . PHP_EOL . $successRerunOutput
    );

    $originalSuccessSql = 'CREATE TABLE ' . $successTable . ' (id INT NOT NULL PRIMARY KEY); INSERT INTO ' . $successTable . ' (id) VALUES (1);';
    file_put_contents($successDir . DIRECTORY_SEPARATOR . $successName, $originalSuccessSql . ' ');
    [$changedChecksumCode, $changedChecksumOutput] = $migrationRun($successDir);
    check(
        'Checksum migrasi yang berubah setelah APPLIED ditolak',
        $changedChecksumCode !== 0 && str_contains($changedChecksumOutput, 'checksum'),
        $changedChecksumOutput
    );
    file_put_contents($successDir . DIRECTORY_SEPARATOR . $successName, $originalSuccessSql);
    Db::run(
        'DELETE FROM schema_migrations WHERE migration_name = ?',
        [$successName],
        'test'
    );
    [$missingLedgerCode, $missingLedgerOutput] = $migrationRun($successDir);
    check(
        'Ledger status APPLIED tanpa baris schema_migrations terdeteksi sebagai inkonsisten',
        $missingLedgerCode !== 0 && str_contains($missingLedgerOutput, 'diblokir'),
        $missingLedgerOutput
    );
    Db::run(
        'INSERT INTO schema_migrations (migration_name) VALUES (?)',
        [$successName],
        'test'
    );

    file_put_contents(
        $beforeFailureDir . DIRECTORY_SEPARATOR . $beforeFailureName,
        'THIS IS INVALID SQL;'
    );
    [$beforeFailureCode, $beforeFailureOutput] = $migrationRun($beforeFailureDir);
    [$beforeRerunCode, $beforeRerunOutput] = $migrationRun($beforeFailureDir);
    $beforeRun = Db::one(
        'SELECT status, last_completed_statement, statement_count FROM schema_migration_runs WHERE migration_name = ?',
        [$beforeFailureName],
        'test'
    );
    $beforeLedgerCount = (int) Db::one(
        'SELECT COUNT(*) AS c FROM schema_migrations WHERE migration_name = ?',
        [$beforeFailureName],
        'test'
    )['c'];
    check(
        'Kegagalan SQL sebelum statement pertama tercatat FAILED dan rerun otomatis diblokir',
        $beforeFailureCode !== 0
            && $beforeRerunCode !== 0
            && ($beforeRun['status'] ?? null) === 'FAILED'
            && (int) ($beforeRun['last_completed_statement'] ?? -1) === 0
            && (int) ($beforeRun['statement_count'] ?? -1) === 1
            && $beforeLedgerCount === 0
            && str_contains($beforeFailureOutput, 'setelah 0/1 statement berhasil')
            && str_contains($beforeRerunOutput, 'diblokir'),
        $beforeFailureOutput . PHP_EOL . $beforeRerunOutput
    );

    file_put_contents(
        $partialFailureDir . DIRECTORY_SEPARATOR . $partialFailureName,
        'CREATE TABLE ' . $partialTable . ' (id INT NOT NULL PRIMARY KEY); THIS IS INVALID SQL;'
    );
    [$partialFailureCode, $partialFailureOutput] = $migrationRun($partialFailureDir);
    [$partialRerunCode, $partialRerunOutput] = $migrationRun($partialFailureDir);
    $partialRun = Db::one(
        'SELECT status, last_completed_statement, statement_count FROM schema_migration_runs WHERE migration_name = ?',
        [$partialFailureName],
        'test'
    );
    $partialLedgerCount = (int) Db::one(
        'SELECT COUNT(*) AS c FROM schema_migrations WHERE migration_name = ?',
        [$partialFailureName],
        'test'
    )['c'];
    $partialTableExists = Db::one(
        'SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?',
        [$partialTable],
        'test'
    ) !== null;
    check(
        'Kegagalan SQL parsial mempertahankan bukti skema, FAILED/1 dari 2, dan memblokir rerun',
        $partialFailureCode !== 0
            && $partialRerunCode !== 0
            && ($partialRun['status'] ?? null) === 'FAILED'
            && (int) ($partialRun['last_completed_statement'] ?? -1) === 1
            && (int) ($partialRun['statement_count'] ?? -1) === 2
            && $partialLedgerCount === 0
            && $partialTableExists
            && str_contains($partialFailureOutput, 'setelah 1/2 statement berhasil')
            && str_contains($partialRerunOutput, 'diblokir'),
        $partialFailureOutput . PHP_EOL . $partialRerunOutput
    );

    file_put_contents(
        $crashAfterFirstDir . DIRECTORY_SEPARATOR . $crashAfterFirstName,
        'CREATE TABLE ' . $crashFirstTable . ' (id INT NOT NULL PRIMARY KEY); CREATE TABLE ' . $crashSecondTable . ' (id INT NOT NULL PRIMARY KEY);'
    );
    [$crashFirstCode, $crashFirstOutput] = $migrationRun($crashAfterFirstDir, 'after_exec:1');
    $crashFirstRun = Db::one(
        'SELECT status, last_completed_statement, statement_count FROM schema_migration_runs WHERE migration_name = ?',
        [$crashAfterFirstName],
        'test'
    );
    $crashFirstLedger = Db::one(
        'SELECT 1 FROM schema_migrations WHERE migration_name = ?',
        [$crashAfterFirstName],
        'test'
    );
    [$crashFirstRerunCode, $crashFirstRerunOutput] = $migrationRun($crashAfterFirstDir);
    check(
        'Crash proses setelah DDL pertama mempertahankan RUNNING/checkpoint 0, dan rerun diblokir',
        $crashFirstCode === 86
            && ($crashFirstRun['status'] ?? null) === 'RUNNING'
            && (int) ($crashFirstRun['last_completed_statement'] ?? -1) === 0
            && (int) ($crashFirstRun['statement_count'] ?? -1) === 2
            && $crashFirstLedger === null
            && $tableExists($crashFirstTable)
            && !$tableExists($crashSecondTable)
            && $crashFirstRerunCode !== 0
            && str_contains($crashFirstRerunOutput, 'diblokir'),
        $crashFirstOutput . PHP_EOL . $crashFirstRerunOutput
    );

    file_put_contents(
        $crashBeforeCheckpointDir . DIRECTORY_SEPARATOR . $crashBeforeCheckpointName,
        'CREATE TABLE ' . $checkpointFirstTable . ' (id INT NOT NULL PRIMARY KEY); CREATE TABLE ' . $checkpointSecondTable . ' (id INT NOT NULL PRIMARY KEY);'
    );
    [$checkpointCrashCode, $checkpointCrashOutput] = $migrationRun($crashBeforeCheckpointDir, 'after_exec:2');
    $checkpointCrashRun = Db::one(
        'SELECT status, last_completed_statement, statement_count FROM schema_migration_runs WHERE migration_name = ?',
        [$crashBeforeCheckpointName],
        'test'
    );
    [$checkpointRerunCode, $checkpointRerunOutput] = $migrationRun($crashBeforeCheckpointDir);
    check(
        'Crash setelah DDL kedua sebelum checkpoint menyisakan RUNNING 1/2 dan rerun diblokir',
        $checkpointCrashCode === 86
            && ($checkpointCrashRun['status'] ?? null) === 'RUNNING'
            && (int) ($checkpointCrashRun['last_completed_statement'] ?? -1) === 1
            && (int) ($checkpointCrashRun['statement_count'] ?? -1) === 2
            && $tableExists($checkpointFirstTable)
            && $tableExists($checkpointSecondTable)
            && $checkpointRerunCode !== 0
            && str_contains($checkpointRerunOutput, 'diblokir'),
        $checkpointCrashOutput . PHP_EOL . $checkpointRerunOutput
    );

    file_put_contents(
        $crashBeforeFinalizeDir . DIRECTORY_SEPARATOR . $crashBeforeFinalizeName,
        'CREATE TABLE ' . $finalizeFirstTable . ' (id INT NOT NULL PRIMARY KEY); CREATE TABLE ' . $finalizeSecondTable . ' (id INT NOT NULL PRIMARY KEY);'
    );
    [$finalizeCrashCode, $finalizeCrashOutput] = $migrationRun($crashBeforeFinalizeDir, 'before_finalize');
    $finalizeCrashRun = Db::one(
        'SELECT status, last_completed_statement, statement_count FROM schema_migration_runs WHERE migration_name = ?',
        [$crashBeforeFinalizeName],
        'test'
    );
    $finalizeCrashLedger = Db::one(
        'SELECT 1 FROM schema_migrations WHERE migration_name = ?',
        [$crashBeforeFinalizeName],
        'test'
    );
    [$finalizeRerunCode, $finalizeRerunOutput] = $migrationRun($crashBeforeFinalizeDir);
    check(
        'Crash setelah semua statement sebelum finalisasi menyisakan RUNNING 2/2 tanpa ledger; rerun diblokir',
        $finalizeCrashCode === 87
            && ($finalizeCrashRun['status'] ?? null) === 'RUNNING'
            && (int) ($finalizeCrashRun['last_completed_statement'] ?? -1) === 2
            && (int) ($finalizeCrashRun['statement_count'] ?? -1) === 2
            && $finalizeCrashLedger === null
            && $tableExists($finalizeFirstTable)
            && $tableExists($finalizeSecondTable)
            && $finalizeRerunCode !== 0
            && str_contains($finalizeRerunOutput, 'diblokir'),
        $finalizeCrashOutput . PHP_EOL . $finalizeRerunOutput
    );

    file_put_contents(
        $crashAfterFinalizeDir . DIRECTORY_SEPARATOR . $crashAfterFinalizeName,
        'CREATE TABLE ' . $afterFinalizeTable . ' (id INT NOT NULL PRIMARY KEY); INSERT INTO ' . $afterFinalizeTable . ' (id) VALUES (1);'
    );
    [$afterFinalizeCode, $afterFinalizeOutput] = $migrationRun($crashAfterFinalizeDir, 'after_finalize');
    $afterFinalizeRun = Db::one(
        'SELECT status, last_completed_statement, statement_count FROM schema_migration_runs WHERE migration_name = ?',
        [$crashAfterFinalizeName],
        'test'
    );
    $afterFinalizeLedger = Db::one(
        'SELECT 1 FROM schema_migrations WHERE migration_name = ?',
        [$crashAfterFinalizeName],
        'test'
    );
    [$afterFinalizeRerunCode, $afterFinalizeRerunOutput] = $migrationRun($crashAfterFinalizeDir);
    $afterFinalizeRows = (int) Db::one('SELECT COUNT(*) AS c FROM ' . $afterFinalizeTable, [], 'test')['c'];
    check(
        'Crash sesudah APPLIED/ledger commit tetapi sebelum respons menghasilkan rerun SKIP yang aman',
        $afterFinalizeCode === 88
            && ($afterFinalizeRun['status'] ?? null) === 'APPLIED'
            && (int) ($afterFinalizeRun['last_completed_statement'] ?? -1) === 2
            && (int) ($afterFinalizeRun['statement_count'] ?? -1) === 2
            && $afterFinalizeLedger !== null
            && $afterFinalizeRerunCode === 0
            && str_contains($afterFinalizeRerunOutput, 'SKIP ' . $crashAfterFinalizeName)
            && $afterFinalizeRows === 1,
        $afterFinalizeOutput . PHP_EOL . $afterFinalizeRerunOutput
    );

    file_put_contents(
        $ledgerFailureDir . DIRECTORY_SEPARATOR . $ledgerFailureName,
        'RENAME TABLE schema_migration_runs TO ' . $ledgerBackupTable . ';'
    );
    [$ledgerFailureCode, $ledgerFailureOutput] = $migrationRun($ledgerFailureDir);
    $ledgerBackupExists = $tableExists($ledgerBackupTable);
    if ($ledgerBackupExists) {
        Db::pdo('test')->exec('RENAME TABLE ' . $ledgerBackupTable . ' TO schema_migration_runs');
    }
    $ledgerFailureRun = Db::one(
        'SELECT status, last_completed_statement FROM schema_migration_runs WHERE migration_name = ?',
        [$ledgerFailureName],
        'test'
    );
    check(
        'Kegagalan menulis checkpoint/status tidak menghasilkan laporan sukses dan menyisakan RUNNING yang terdeteksi',
        $ledgerFailureCode !== 0
            && $ledgerBackupExists
            && str_contains($ledgerFailureOutput, 'Migrator gagal')
            && !str_contains($ledgerFailureOutput, 'OK ' . $ledgerFailureName)
            && ($ledgerFailureRun['status'] ?? null) === 'RUNNING'
            && (int) ($ledgerFailureRun['last_completed_statement'] ?? -1) === 0
            && Db::one(
                'SELECT 1 FROM schema_migrations WHERE migration_name = ?',
                [$ledgerFailureName],
                'test'
            ) === null,
        $ledgerFailureOutput
    );

    file_put_contents(
        $concurrentDir . DIRECTORY_SEPARATOR . $concurrentName,
        'DO SLEEP(2); CREATE TABLE ' . $concurrentTable . ' (id INT NOT NULL PRIMARY KEY); INSERT INTO ' . $concurrentTable . ' (id) VALUES (1);'
    );
    $concurrentProcesses = [];
    foreach (['test', 'humamantap_test'] as $databaseAlias) {
        $command = escapeshellarg(PHP_BINARY) . ' '
            . escapeshellarg(__DIR__ . '/../bin/jalankan-migrasi.php')
            . ' ' . escapeshellarg($databaseAlias) . ' ' . escapeshellarg($concurrentDir);
        $pipes = [];
        $process = proc_open($command, [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes, __DIR__);
        if (is_resource($process)) {
            fclose($pipes[0]);
            $concurrentProcesses[] = ['process' => $process, 'pipes' => $pipes];
        }
    }
    $concurrentOutputs = [];
    foreach ($concurrentProcesses as $entry) {
        $stdout = (string) stream_get_contents($entry['pipes'][1]);
        $stderr = (string) stream_get_contents($entry['pipes'][2]);
        fclose($entry['pipes'][1]);
        fclose($entry['pipes'][2]);
        $concurrentOutputs[] = [
            'code' => proc_close($entry['process']),
            'output' => $stdout . $stderr,
        ];
    }
    $concurrentRun = Db::one(
        'SELECT status, statement_count, last_completed_statement FROM schema_migration_runs WHERE migration_name = ?',
        [$concurrentName],
        'test'
    );
    $concurrentRows = $tableExists($concurrentTable)
        ? (int) Db::one('SELECT COUNT(*) AS c FROM ' . $concurrentTable, [], 'test')['c']
        : -1;
    $concurrentOutput = implode(PHP_EOL, array_column($concurrentOutputs, 'output'));
    check(
        'Dua migrator paralel dengan alias DB berbeda memakai lock DB yang sama dan hanya menerapkan sekali',
        count($concurrentProcesses) === 2
            && count(array_filter($concurrentOutputs, static fn (array $result): bool => $result['code'] === 0)) === 2
            && substr_count($concurrentOutput, 'OK ' . $concurrentName) === 1
            && substr_count($concurrentOutput, 'SKIP ' . $concurrentName) === 1
            && ($concurrentRun['status'] ?? null) === 'APPLIED'
            && (int) ($concurrentRun['statement_count'] ?? -1) === 3
            && (int) ($concurrentRun['last_completed_statement'] ?? -1) === 3
            && $concurrentRows === 1,
        $concurrentOutput
    );
} finally {
    $temporaryTables = [
        $successTable,
        $partialTable,
        $crashFirstTable,
        $crashSecondTable,
        $checkpointFirstTable,
        $checkpointSecondTable,
        $finalizeFirstTable,
        $finalizeSecondTable,
        $afterFinalizeTable,
        $ledgerBackupTable,
        $concurrentTable,
    ];
    foreach ($temporaryTables as $temporaryTable) {
        Db::pdo('test')->exec('DROP TABLE IF EXISTS ' . $temporaryTable);
    }
    Db::run(
        'DELETE FROM schema_migrations WHERE migration_name IN (?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [$successName, $beforeFailureName, $partialFailureName, $crashAfterFirstName, $crashBeforeCheckpointName, $crashBeforeFinalizeName, $crashAfterFinalizeName, $ledgerFailureName, $concurrentName],
        'test'
    );
    Db::run(
        'DELETE FROM schema_migration_runs WHERE migration_name IN (?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [$successName, $beforeFailureName, $partialFailureName, $crashAfterFirstName, $crashBeforeCheckpointName, $crashBeforeFinalizeName, $crashAfterFinalizeName, $ledgerFailureName, $concurrentName],
        'test'
    );
    $migrationFiles = [
        [$successDir, $successName],
        [$beforeFailureDir, $beforeFailureName],
        [$partialFailureDir, $partialFailureName],
        [$crashAfterFirstDir, $crashAfterFirstName],
        [$crashBeforeCheckpointDir, $crashBeforeCheckpointName],
        [$crashBeforeFinalizeDir, $crashBeforeFinalizeName],
        [$crashAfterFinalizeDir, $crashAfterFinalizeName],
        [$ledgerFailureDir, $ledgerFailureName],
        [$concurrentDir, $concurrentName],
    ];
    foreach ($migrationFiles as [$fixtureDir, $migrationFile]) {
        $path = $fixtureDir . DIRECTORY_SEPARATOR . $migrationFile;
        if (is_file($path)) {
            unlink($path);
        }
    }
    foreach ([$successDir, $beforeFailureDir, $partialFailureDir, $crashAfterFirstDir, $crashBeforeCheckpointDir, $crashBeforeFinalizeDir, $crashAfterFinalizeDir, $ledgerFailureDir, $concurrentDir, $recoveryRoot] as $directory) {
        if (is_dir($directory)) {
            rmdir($directory);
        }
    }
}

$csrfScript = tempnam(__DIR__, '.csrf_');
file_put_contents($csrfScript, "<?php\n" .
    "session_start();\n" .
    "\$_POST = ['_csrf' => ''];\n" .
    "require " . var_export(__DIR__ . '/../app/Core/helpers.php', true) . ";\n" .
    "require " . var_export(__DIR__ . '/../app/Core/Config.php', true) . ";\n" .
    "require " . var_export(__DIR__ . '/../app/Core/Csrf.php', true) . ";\n" .
    "\\App\\Core\\Csrf::verify();\n");
exec(PHP_BINARY . ' ' . escapeshellarg($csrfScript), $csrfOutput, $csrfCode);
unlink($csrfScript);
check('CSRF gagal tanpa token', $csrfCode === 0 && str_contains(implode(PHP_EOL, $csrfOutput), 'Token tidak valid'), implode(PHP_EOL, $csrfOutput));

$first = IdGen::next('REQ', 'test');
$second = IdGen::next('REQ', 'test');
check('IdGen membuat nomor unik dan berurutan', preg_match('/^REQ-\d{4}-\d{4}$/', $first) === 1 && $second !== $first && preg_match('/^REQ-\d{4}-\d{4}$/', $second) === 1, $first . ' | ' . $second);

$statsPanel = [];
$statsPanelRendered = preg_match('/<section class="stats-panel".*?<\/section>/s', $homeOutput, $statsPanel) === 1;
check(
    'Beranda tidak memiliki angka karangan',
    $statsPanelRendered
        && str_contains($statsPanel[0], 'BELUM ADA DATA')
        && preg_match('/<strong>\s*\d+\s*<\/strong>/', $statsPanel[0]) !== 1,
    $statsPanelRendered ? $statsPanel[0] : 'Panel statistik tidak ditemukan pada keluaran halaman.'
);

$currentKey = Config::activeDatabaseKey();
$currentDb = Config::activeDatabaseName();
$activeDbName = Config::database($currentKey)['name'] ?? null;
$originalAppEnv = getenv('APP_ENV');
putenv('APP_ENV=development');
$developmentDatabase = Config::database('default')['name'] ?? null;
$developmentDatabaseKey = Config::activeDatabaseKey();
putenv('APP_ENV=test');
$fixtureDatabase = Config::database('test')['name'] ?? null;
if ($originalAppEnv === false) {
    putenv('APP_ENV');
} else {
    putenv('APP_ENV=' . $originalAppEnv);
}
check(
    'Pemetaan APP_ENV development tetap memakai humamantap_dev dan profil test memakai fixture nonce',
    $developmentDatabaseKey === 'default'
        && $developmentDatabase === 'humamantap_dev'
        && Config::activeDatabaseKey() === 'test'
        && $fixtureDatabase === getenv('HUMA_TEST_DATABASE_NAME')
        && preg_match('/^humamantap_test_[a-f0-9]{24}$/', (string) $fixtureDatabase) === 1
);
check(
    'Koneksi tes memakai fixture unik milik proses saat ini',
    $currentKey === 'test'
        && $currentDb === getenv('HUMA_TEST_DATABASE_NAME')
        && $activeDbName === getenv('HUMA_TEST_DATABASE_NAME')
        && str_starts_with($currentDb, 'humamantap_test_')
);

$status = trim((string) shell_exec('git ls-files -- config/config.php'));
check('config/config.php tidak masuk ke Git', $status === '', 'config/config.php terdeteksi di Git index');

$rolesSql = "INSERT INTO roles (code, name, description) VALUES ('GUEST', 'Guest', 'Pengunjung publik'), ('SISWA', 'Siswa', 'Siswa'), ('GURU', 'Guru', 'Guru'), ('ADMIN', 'Admin', 'Admin') ON DUPLICATE KEY UPDATE code = code";
Db::run($rolesSql, [], 'test');
$adminHash = password_hash('Password1234', PASSWORD_DEFAULT);
Db::run("INSERT INTO users (role_id, identifier, full_name, password_hash, is_active) VALUES ((SELECT id FROM roles WHERE code = 'ADMIN'), 'admin01', 'Admin One', ?, 1)", [$adminHash], 'test');
ob_start();
$loginOk = Auth::processLogin('admin01', 'Password1234', 'ADMIN');
$loginBuffer = ob_get_clean();
check('Login admin valid', $loginOk['ok'] === true, $loginBuffer . PHP_EOL . print_r($loginOk, true));

ob_start();
$badMsg = Auth::processLogin('admin01', 'SalahPassword', 'ADMIN');
$badBuffer = ob_get_clean();
ob_start();
$unknownMsg = Auth::processLogin('ghost-user', 'SalahPassword', 'ADMIN');
$unknownBuffer = ob_get_clean();
check('Pesan login sama untuk identitas tidak terdaftar dan kata sandi salah', $badMsg['message'] === $unknownMsg['message'], $badBuffer . ' | ' . $unknownBuffer . ' | ' . $badMsg['message'] . ' | ' . $unknownMsg['message']);

$lockoutUser = 'locked-user';
$passwordHashLock = password_hash('Password1234', PASSWORD_DEFAULT);
Db::run("INSERT INTO users (role_id, identifier, full_name, password_hash, is_active) VALUES ((SELECT id FROM roles WHERE code = 'ADMIN'), ?, 'Locked User', ?, 1)", [$lockoutUser, $passwordHashLock], 'test');
for ($i = 0; $i < 5; $i++) {
    ob_start();
    Auth::processLogin($lockoutUser, 'wrongpass', 'ADMIN');
    ob_end_clean();
}
ob_start();
$lockedResult = Auth::processLogin($lockoutUser, 'Password1234', 'ADMIN');
$lockoutBuffer = ob_get_clean();
check('Akun terkunci setelah lima kegagalan', $lockedResult['ok'] === false && str_contains($lockedResult['message'], 'terkunci'), $lockoutBuffer . PHP_EOL . $lockedResult['message']);

require_once __DIR__ . '/../app/Core/ComplaintWorkflow.php';
require __DIR__ . '/phase2b.php';
require __DIR__ . '/phase5.php';
require __DIR__ . '/phase6.php';

print PHP_EOL . ($failed === 0 ? 'SEMUA TES LOLOS' : $failed . ' TES GAGAL') . PHP_EOL;
ob_end_flush();
exit($failed === 0 ? 0 : 1);
