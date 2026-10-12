<?php
declare(strict_types=1);

require __DIR__ . '/../app/autoload.php';

use App\Core\Config;
use App\Core\QuotaCleaner;

try {
    $deleted = QuotaCleaner::cleanExpired(Config::activeDatabaseKey());
    $total = array_sum($deleted);
    fwrite(STDOUT, 'Baris kuota kedaluwarsa dihapus: ' . $total . PHP_EOL);
    foreach ($deleted as $table => $count) {
        fwrite(STDOUT, $table . ': ' . $count . PHP_EOL);
    }
    exit(0);
} catch (Throwable) {
    fwrite(STDERR, "Pembersihan kuota gagal.\n");
    exit(1);
}