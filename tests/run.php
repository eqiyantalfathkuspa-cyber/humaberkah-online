<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Core\Auth;
use App\Core\Config;
use App\Core\Csrf;
use App\Core\Db;
use App\Core\IdGen;
use App\Core\Router;

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
check('Nama basis data aktif mengikuti APP_ENV', $currentKey === 'test' && $currentDb === 'humamantap_test' && $activeDbName === 'humamantap_test', $currentKey . ' / ' . $currentDb . ' -> ' . ($activeDbName ?? 'tidak-tersedia'));

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

require __DIR__ . '/phase2b.php';

print PHP_EOL . ($failed === 0 ? 'SEMUA TES LOLOS' : $failed . ' TES GAGAL') . PHP_EOL;
ob_end_flush();
exit($failed === 0 ? 0 : 1);
