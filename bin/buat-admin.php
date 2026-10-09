<?php
declare(strict_types=1);

require __DIR__ . '/../app/Core/helpers.php';
require __DIR__ . '/../app/Core/Config.php';
require __DIR__ . '/../app/Core/Db.php';

use App\Core\Db;
use App\Core\Config;

function readHiddenPassword(): string
{
    if (PHP_OS_FAMILY !== 'Windows' || PHP_SAPI !== 'cli') {
        throw new RuntimeException('Input sandi tersembunyi hanya didukung pada Windows CLI.');
    }

    $script = '$secure = Read-Host -Prompt "Kata sandi (input tersembunyi)" -AsSecureString; ' .
        '$ptr = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($secure); ' .
        'try { [Console]::Out.Write([Runtime.InteropServices.Marshal]::PtrToStringBSTR($ptr)) } ' .
        'finally { [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($ptr); $secure.Dispose() }';
    $process = proc_open(
        ['powershell.exe', '-NoLogo', '-NoProfile', '-Command', $script],
        [0 => STDIN, 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes
    );
    if (!is_resource($process)) {
        throw new RuntimeException('PowerShell untuk input sandi tersembunyi tidak tersedia.');
    }

    $password = stream_get_contents($pipes[1]);
    $error = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);
    if ($exitCode !== 0 || $password === false) {
        throw new RuntimeException('Input sandi tersembunyi gagal; proses dibatalkan.');
    }

    return $password;
}

$username = trim((string) ($argv[1] ?? ''));
if ($username === '') {
    fwrite(STDERR, "Gunakan: php bin/buat-admin.php <username>\n");
    exit(1);
}

$fullName = trim((string) ($argv[2] ?? 'Admin Pokja 6'));
$database = Config::activeDatabaseName();
$pdo = Db::pdo($database);
$roleRow = Db::one('SELECT id FROM roles WHERE code = ?', ['ADMIN'], 'default');
if ($roleRow === null) {
    fwrite(STDERR, "Rol ADMIN belum tersedia.\n");
    exit(1);
}
$roleId = (int) $roleRow['id'];
if (Db::one('SELECT id FROM users WHERE role_id = ? AND identifier = ?', [$roleId, $username], 'default') !== null) {
    fwrite(STDERR, "Username sudah ada.\n");
    exit(1);
}

try {
    $password = readHiddenPassword();
} catch (RuntimeException $exception) {
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(1);
}
if (mb_strlen($password, 'UTF-8') < 10) {
    fwrite(STDERR, "Kata sandi minimal 10 karakter.\n");
    exit(1);
}

$pdo->beginTransaction();
try {
    Db::run(
        'INSERT INTO users (role_id, identifier, full_name, password_hash, is_active) VALUES (?, ?, ?, ?, 1)',
        [$roleId, $username, $fullName, password_hash($password, PASSWORD_DEFAULT)],
        $database
    );

    $userId = (int) $pdo->lastInsertId();
    Db::run(
        'INSERT INTO audit_logs (actor_id, actor_role, action, entity_type, entity_id, message) VALUES (?, ?, ?, ?, ?, ?)',
        [null, 'SYSTEM', 'CREATE', 'users', $userId, 'Admin awal dibuat via bin/buat-admin.php'],
        $database
    );
    $pdo->commit();
} catch (PDOException $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if ($exception->getCode() === '23000') {
        fwrite(STDERR, "Username sudah ada.\n");
        exit(1);
    }
    throw $exception;
} finally {
    unset($password);
}

fwrite(STDOUT, "Admin dibuat.\n");
