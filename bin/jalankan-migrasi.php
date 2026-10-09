<?php
declare(strict_types=1);

require __DIR__ . '/../app/Core/Config.php';
require __DIR__ . '/../app/Core/Db.php';

use App\Core\Config;
use App\Core\Db;

$database = $argv[1] ?? 'default';
$directory = __DIR__ . '/../database/migrations';

if (!is_dir($directory)) {
    fwrite(STDERR, "Direktori migrasi tidak ditemukan: $directory\n");
    exit(1);
}

$pdo = Db::pdo($database);
$pdo->exec("CREATE TABLE IF NOT EXISTS schema_migrations (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    migration_name VARCHAR(255) NOT NULL,
    applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_schema_migrations_name (migration_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$files = glob($directory . '/*.sql');
if ($files === false || $files === []) {
    echo "Tidak ada migrasi SQL untuk dijalankan.\n";
    exit(0);
}

sort($files, SORT_STRING);

foreach ($files as $file) {
    $migrationName = basename($file);
    $alreadyApplied = Db::one('SELECT 1 FROM schema_migrations WHERE migration_name = ?', [$migrationName], $database);
    if ($alreadyApplied !== null) {
        echo "SKIP $migrationName\n";
        continue;
    }

    $sql = file_get_contents($file);
    if ($sql === false) {
        fwrite(STDERR, "Gagal membaca $file\n");
        exit(1);
    }

    $statements = parseSqlStatements($sql);
    if ($statements === []) {
        fwrite(STDERR, "Tidak ada pernyataan SQL valid di $file\n");
        exit(1);
    }

    try {
        foreach ($statements as $statement) {
            $pdo->exec($statement);
        }
        Db::run('INSERT INTO schema_migrations (migration_name) VALUES (?)', [$migrationName], $database);
        echo "OK $migrationName\n";
    } catch (\Throwable $e) {
        fwrite(STDERR, "Gagal menjalankan migrasi $migrationName: " . $e->getMessage() . "\n");
        exit(1);
    }
}

function parseSqlStatements(string $sql): array
{
    $statements = [];
    $buffer = '';
    $quote = null;
    $length = strlen($sql);

    for ($i = 0; $i < $length; $i++) {
        $char = $sql[$i];
        $next = $i + 1 < $length ? $sql[$i + 1] : '';

        if ($quote !== null) {
            $buffer .= $char;
            if ($char === '\\' && $next !== '') {
                $buffer .= $next;
                $i++;
                continue;
            }
            if ($char === $quote) {
                $quote = null;
            }
            continue;
        }

        if ($char === '\'' || $char === '"') {
            $quote = $char;
            $buffer .= $char;
            continue;
        }

        if ($char === ';') {
            $statement = trim($buffer);
            if ($statement !== '') {
                $statements[] = $statement;
            }
            $buffer = '';
            continue;
        }

        $buffer .= $char;
    }

    $tail = trim($buffer);
    if ($tail !== '') {
        $statements[] = $tail;
    }

    return $statements;
}
