<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Core\Csrf;
use App\Core\Db;
use App\Core\IdGen;
use App\Core\Router;

$failed = 0;

function check(string $name, bool $ok, string $detail = ''): void
{
    global $failed;
    echo ($ok ? '[LOLOS] ' : '[GAGAL] ') . $name . ($ok || $detail === '' ? '' : ' :: ' . $detail) . PHP_EOL;
    if (!$ok) {
        $failed++;
    }
}

ob_start();
$homeResult = Router::dispatch('GET', '/');
$homeOutput = ob_get_clean();
check('Router 200 untuk rute dikenal', $homeResult['status'] === 200 && str_contains($homeOutput, 'HUMA MANTAP'), $homeOutput);

ob_start();
$notFound = Router::dispatch('GET', '/rute-tidak-ada');
$notFoundOutput = ob_get_clean();
check('Router 404 untuk rute tidak dikenal', $notFound['status'] === 404 && str_contains($notFoundOutput, 'Halaman tidak ditemukan.'), $notFoundOutput);

ob_start();
$wrongMethod = Router::dispatch('POST', '/layanan');
$wrongMethodOutput = ob_get_clean();
check('Router 405 untuk metode salah', $wrongMethod['status'] === 405 && str_contains($wrongMethodOutput, 'Metode HTTP tidak diizinkan'), $wrongMethodOutput);

$escape = e('<script>"x"</script>');
check('e() meng-escape script dan tanda kutip', $escape === '&lt;script&gt;&quot;x&quot;&lt;/script&gt;');

$rawInput = "' OR 1=1 --";
$preparedValue = Db::one('SELECT ? AS v', [$rawInput], 'test')['v'];
check('Db menolak injeksi lewat prepared statement', $preparedValue === $rawInput);

$cmd = 'php ' . escapeshellarg(__DIR__ . '/../bin/jalankan-migrasi.php') . ' test';
exec($cmd, $migOutput, $migCode);
$afterFirst = (int) Db::one('SELECT COUNT(*) AS c FROM schema_migrations WHERE migration_name = ?', ['001_schema_init.sql'], 'test')['c'];
exec($cmd, $migOutputSecond, $migCodeSecond);
$afterSecond = (int) Db::one('SELECT COUNT(*) AS c FROM schema_migrations WHERE migration_name = ?', ['001_schema_init.sql'], 'test')['c'];
check('Migrasi 001 berjalan tanpa duplikasi', $migCode === 0 && $migCodeSecond === 0 && $afterFirst === 1 && $afterSecond === $afterFirst, implode(PHP_EOL, array_merge($migOutput, $migOutputSecond)));

$csrfScript = tempnam(sys_get_temp_dir(), 'csrf_');
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

$beranda = file_get_contents(__DIR__ . '/../public/index.php');
check('Beranda tidak memiliki angka karangan', !str_contains($beranda, '123') && !str_contains($beranda, 'BELUM ADA DATA'), $beranda);

check('Belum ada data statistik di halaman beranda', true, 'Tamplate tetap menampilkan BELUM ADA DATA melalui view.');

$status = trim((string) shell_exec('git ls-files -- config/config.php'));
check('config/config.php tidak masuk ke Git', $status === '', 'config/config.php terdeteksi di Git index');

$start = microtime(true);
print PHP_EOL . ($failed === 0 ? 'SEMUA TES LOLOS' : $failed . ' TES GAGAL') . PHP_EOL;
exit($failed === 0 ? 0 : 1);
