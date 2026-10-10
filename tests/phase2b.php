<?php
declare(strict_types=1);

use App\Core\Auth;
use App\Core\ApplicationWorkflow;
use App\Core\ApplicationTrackingLimiter;
use App\Core\Config;
use App\Core\Csrf;
use App\Core\Db;
use App\Core\Router;
use App\Core\ServiceCatalog;
use App\Core\SqlStatementParser;
use App\Core\TrackingCode;
use App\Core\View;
use App\Policies\Permissions;

require_once __DIR__ . '/../app/Controllers/AuthController.php';
require_once __DIR__ . '/../app/Controllers/PortalController.php';
require_once __DIR__ . '/../app/Controllers/AdminController.php';
require_once __DIR__ . '/../app/Core/ServiceCatalog.php';
require_once __DIR__ . '/../app/Controllers/ServiceController.php';
require_once __DIR__ . '/../app/Core/TrackingCode.php';
require_once __DIR__ . '/../app/Core/ApplicationWorkflow.php';
require_once __DIR__ . '/../app/Core/ApplicationTrackingLimiter.php';
require_once __DIR__ . '/../app/Controllers/ApplicationController.php';

$catalogSql = file_get_contents(__DIR__ . '/../database/migrations/004_service_catalog.sql');
$catalogPdo = Db::pdo('test');
$catalogStatements = SqlStatementParser::parse((string) $catalogSql);
foreach ($catalogStatements as $catalogStatement) {
    $catalogPdo->exec($catalogStatement);
}
$catalogCountsAfterFirstApply = [
    'categories' => (int) Db::one('SELECT COUNT(*) AS n FROM service_categories', [], 'test')['n'],
    'services' => (int) Db::one('SELECT COUNT(*) AS n FROM services', [], 'test')['n'],
    'steps' => (int) Db::one('SELECT COUNT(*) AS n FROM service_steps', [], 'test')['n'],
    'standards' => (int) Db::one('SELECT COUNT(*) AS n FROM service_standards', [], 'test')['n'],
];
$emptyStandards = (int) Db::one(
    'SELECT COUNT(*) AS n FROM service_standards WHERE fee_text IS NOT NULL OR sla_text IS NOT NULL OR responsible_unit IS NOT NULL OR required_documents_text IS NOT NULL',
    [],
    'test'
)['n'];
foreach ($catalogStatements as $catalogStatement) {
    $catalogPdo->exec($catalogStatement);
}
$catalogCountsAfterSecondApply = [
    'categories' => (int) Db::one('SELECT COUNT(*) AS n FROM service_categories', [], 'test')['n'],
    'services' => (int) Db::one('SELECT COUNT(*) AS n FROM services', [], 'test')['n'],
    'steps' => (int) Db::one('SELECT COUNT(*) AS n FROM service_steps', [], 'test')['n'],
    'standards' => (int) Db::one('SELECT COUNT(*) AS n FROM service_standards', [], 'test')['n'],
];
check(
    'Migrasi katalog dua kali idempoten tanpa menggandakan data',
    $catalogCountsAfterFirstApply === ['categories' => 5, 'services' => 11, 'steps' => 88, 'standards' => 11]
        && $catalogCountsAfterSecondApply === $catalogCountsAfterFirstApply
        && $emptyStandards === 0
);

$flowchart = (string) file_get_contents(__DIR__ . '/../docs/FLOWCHART_PELAYANAN.md');
$actualCategories = [];
foreach (Db::all('SELECT code, name FROM service_categories ORDER BY id', [], 'test') as $category) {
    $actualCategories[$category['code']] = $category['name'];
}
preg_match_all('/^- ([A-Z]{2,3}-\d{2}): (.+?)\s*$/m', substr($flowchart, 0, (int) strpos($flowchart, '## 2. Tahap layanan')), $serviceMatches, PREG_SET_ORDER);
$expectedServices = [];
foreach ($serviceMatches as $serviceMatch) {
    $expectedServices[$serviceMatch[1]] = trim($serviceMatch[2]);
}
$summaryStart = strpos($flowchart, '## 3. Ringkasan per layanan');
$summaryEnd = strpos($flowchart, '## 4. Catatan penting');
$summary = substr($flowchart, (int) $summaryStart, (int) $summaryEnd - (int) $summaryStart);
preg_match_all('/^\| ([A-Z]{2,3}-\d{2}) \| .+? \| (.+?) \| (?:FORMULIR|INFORMASI|TATAP_MUKA|MODUL_PENGADUAN) \|/m', $summary, $summaryMatches, PREG_SET_ORDER);
$expectedCategories = [];
foreach ($summaryMatches as $summaryMatch) {
    $categoryCode = substr($summaryMatch[1], 0, strpos($summaryMatch[1], '-'));
    $expectedCategories[$categoryCode] = trim($summaryMatch[2]);
}
$actualServices = [];
foreach (Db::all('SELECT code, name FROM services ORDER BY code', [], 'test') as $service) {
    $actualServices[$service['code']] = $service['name'];
}
$expectedSteps = [];
preg_match_all('/^### ([A-Z]{2,3}-\d{2})\s*\R((?:(?:- \d+\. .*)\R?)+)/m', $flowchart, $stepSections, PREG_SET_ORDER);
foreach ($stepSections as $stepSection) {
    preg_match_all('/^- (\d+)\. (.+)$/m', $stepSection[2], $stepMatches, PREG_SET_ORDER);
    foreach ($stepMatches as $stepMatch) {
        $expectedSteps[$stepSection[1]][(int) $stepMatch[1]] = trim($stepMatch[2]);
    }
}
$actualSteps = [];
foreach (Db::all(
    'SELECT s.code, ss.step_no, ss.step_name FROM service_steps ss INNER JOIN services s ON s.id = ss.service_id ORDER BY s.code, ss.step_no',
    [],
    'test'
) as $step) {
    $actualSteps[$step['code']][(int) $step['step_no']] = $step['step_name'];
}
ksort($actualCategories);
ksort($expectedCategories);
ksort($actualServices);
ksort($expectedServices);
ksort($actualSteps);
ksort($expectedSteps);
$stepSequencesValid = count($actualSteps) === 11;
foreach ($actualSteps as $steps) {
    $stepSequencesValid = $stepSequencesValid && array_keys($steps) === range(1, 8);
}
check(
    'Kategori dan nama layanan persis seperti FLOWCHART_PELAYANAN.md',
    $actualCategories === $expectedCategories && $actualServices === $expectedServices,
    'categories=' . json_encode($actualCategories, JSON_UNESCAPED_UNICODE)
        . '; expectedCategories=' . json_encode($expectedCategories, JSON_UNESCAPED_UNICODE)
        . '; services=' . json_encode($actualServices, JSON_UNESCAPED_UNICODE)
        . '; expectedServices=' . json_encode($expectedServices, JSON_UNESCAPED_UNICODE)
);
check(
    'Setiap layanan memiliki tepat 8 tahap berurutan dan labelnya sama dengan flowchart',
    $stepSequencesValid && $actualSteps === $expectedSteps,
    'sequencesValid=' . ($stepSequencesValid ? 'yes' : 'no')
        . '; actualStepCodes=' . implode(',', array_keys($actualSteps))
        . '; expectedStepCodes=' . implode(',', array_keys($expectedSteps))
);
$initialModes = Db::all('SELECT code, mode FROM services ORDER BY code', [], 'test');
$modeCounts = array_count_values(array_column($initialModes, 'mode'));
$expectedInitialModes = [
    'TU-01' => 'FORMULIR',
    'TU-02' => 'FORMULIR',
    'KUR-01' => 'INFORMASI',
    'KUR-02' => 'FORMULIR',
    'KES-01' => 'INFORMASI',
    'KES-02' => 'FORMULIR',
    'KES-03' => 'INFORMASI',
    'KES-04' => 'FORMULIR',
    'SAR-01' => 'FORMULIR',
    'HUM-01' => 'FORMULIR',
    'HUM-02' => 'MODUL_PENGADUAN',
];
$actualInitialModes = [];
foreach ($initialModes as $initialMode) {
    $actualInitialModes[$initialMode['code']] = $initialMode['mode'];
}
ksort($actualInitialModes);
ksort($expectedInitialModes);
check(
    'Mode awal layanan sesuai keputusan Pokja 6',
    $actualInitialModes === $expectedInitialModes
        && ($modeCounts['FORMULIR'] ?? 0) === 7
        && ($modeCounts['INFORMASI'] ?? 0) === 3
        && ($modeCounts['MODUL_PENGADUAN'] ?? 0) === 1
        && count($modeCounts) === 3,
    json_encode($modeCounts)
);

$phase2bAdminInvariantPassed = true;
$phase2bAdminInvariantEvidence = [];

function phase2bUser(string $role, string $identifier, string $name, string $password, int $active = 1): array
{
    $token = bin2hex(random_bytes(32));
    Db::run(
        'INSERT INTO users (role_id, identifier, full_name, password_hash, is_active, auth_token) VALUES ((SELECT id FROM roles WHERE code = ?), ?, ?, ?, ?, ?)',
        [$role, $identifier, $name, password_hash($password, PASSWORD_DEFAULT), $active, $token],
        'test'
    );
    $userId = (int) Db::one(
        'SELECT id FROM users WHERE role_id = (SELECT id FROM roles WHERE code = ?) AND identifier = ?',
        [$role, $identifier],
        'test'
    )['id'];
    return ['id' => $userId, 'role' => $role, 'identifier' => $identifier, 'name' => $name, 'password' => $password, 'token' => $token];
}

function phase2bAs(?array $user): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    $_SESSION = [];
    if ($user !== null) {
        $_SESSION['user'] = [
            'id' => $user['id'],
            'role' => $user['role'],
            'role_code' => $user['role'],
            'identifier' => $user['identifier'],
            'full_name' => $user['name'],
            'auth_token' => $user['token'],
        ];
        $_SESSION['login_time'] = time();
        $_SESSION['last_activity'] = time();
    }
}

function phase2bHttpIdentity(?array $user): array
{
    $previousId = session_id();
    $newId = session_create_id();
    session_write_close();
    session_id($newId);
    session_start();
    phase2bAs($user);
    $csrf = Csrf::token();
    session_write_close();
    session_id($previousId);
    session_start();
    return ['cookie' => session_name() . '=' . $newId, 'csrf' => $csrf];
}

function phase2bRequest(string $method, string $path, array $post = []): array
{
    $_POST = $post;
    $_SERVER['REQUEST_METHOD'] = $method;
    http_response_code(200);
    ob_start();
    $result = Router::dispatch($method, $path);
    $body = (string) ob_get_clean();
    if ($method === 'POST' && preg_match('#^/admin/pengguna/[^/]+/(?:role|nonaktif)$#', $path) === 1) {
        $activeAdminCount = (int) Db::one(
            'SELECT COUNT(*) AS n FROM users u INNER JOIN roles r ON r.id = u.role_id WHERE r.code = ? AND u.is_active = 1',
            ['ADMIN'],
            'test'
        )['n'];
        if ($activeAdminCount < 1) {
            $GLOBALS['phase2bAdminInvariantPassed'] = false;
            $GLOBALS['phase2bAdminInvariantEvidence'][] = $path . ' left ' . $activeAdminCount . ' active admins';
        }
    }
    return ['result' => $result, 'status' => http_response_code(), 'body' => $body];
}

function phase2bForeignCsrfToken(): string
{
    $currentId = session_id();
    session_write_close();
    session_id(session_create_id());
    session_start();
    $_SESSION = [];
    $token = Csrf::token();
    session_write_close();
    session_id($currentId);
    session_start();
    return $token;
}

function phase2bSessionCookieHeaders(): array
{
    $socket = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);
    if ($socket === false) {
        return [];
    }
    $address = stream_socket_get_name($socket, false);
    fclose($socket);
    if ($address === false) {
        return [];
    }

    $process = proc_open(
        [PHP_BINARY, '-S', $address, __DIR__ . '/cookie_probe.php'],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        __DIR__
    );
    if (!is_resource($process)) {
        return [];
    }

    $headers = [];
    try {
        $context = stream_context_create(['http' => ['timeout' => 3, 'ignore_errors' => true]]);
        for ($attempt = 0; $attempt < 20; $attempt++) {
            $body = @file_get_contents('http://' . $address . '/', false, $context);
            if ($body !== false) {
                $headers = $http_response_header ?? [];
                break;
            }
            usleep(50000);
        }
    } finally {
        proc_terminate($process);
        fclose($pipes[0]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);
    }

    return $headers;
}

function phase2bHttpRequests(array $requests, array $serverIni = []): array
{
    $socket = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);
    if ($socket === false) {
        return [];
    }
    $address = stream_socket_get_name($socket, false);
    fclose($socket);
    if ($address === false) {
        return [];
    }

    $serverCommand = [PHP_BINARY];
    foreach ($serverIni as $setting) {
        $serverCommand[] = '-d';
        $serverCommand[] = $setting;
    }
    $serverCommand[] = '-S';
    $serverCommand[] = $address;
    $serverCommand[] = __DIR__ . '/../public/index.php';
    $process = proc_open(
        $serverCommand,
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        __DIR__
    );
    if (!is_resource($process)) {
        return [];
    }

    $responses = [];
    try {
        foreach ($requests as $request) {
            $headers = ['Connection: close'];
            if (isset($request['cookie'])) {
                $headers[] = 'Cookie: ' . $request['cookie'];
            }
            $requestBody = '';
            if ($request['method'] === 'POST') {
                if (isset($request['files'])) {
                    $boundary = 'huma-' . bin2hex(random_bytes(16));
                    $body = '';
                    foreach (($request['post'] ?? []) as $name => $value) {
                        $body .= '--' . $boundary . "\r\n"
                            . 'Content-Disposition: form-data; name="' . $name . '"' . "\r\n\r\n"
                            . (string) $value . "\r\n";
                    }
                    foreach ($request['files'] as $file) {
                        $body .= '--' . $boundary . "\r\n"
                            . 'Content-Disposition: form-data; name="attachments[]"; filename="' . addcslashes((string) $file['name'], "\"\\") . '"' . "\r\n"
                            . 'Content-Type: ' . (string) ($file['type'] ?? 'application/octet-stream') . "\r\n\r\n"
                            . $file['content'] . "\r\n";
                    }
                    $body .= '--' . $boundary . "--\r\n";
                    $headers[] = 'Content-Type: multipart/form-data; boundary=' . $boundary;
                    $requestBody = $body;
                } else {
                    $headers[] = 'Content-Type: application/x-www-form-urlencoded';
                    $requestBody = http_build_query($request['post'] ?? []);
                }
            }

            $socket = stream_socket_client('tcp://' . $address, $errorCode, $errorMessage, 5);
            if ($socket === false) {
                $responses[] = ['status' => 0, 'headers' => [], 'body' => '', 'error' => $errorMessage];
                continue;
            }
            stream_set_timeout($socket, 5);
            $rawRequest = $request['method'] . ' ' . $request['path'] . " HTTP/1.1\r\n"
                . 'Host: ' . $address . "\r\n"
                . implode("\r\n", $headers) . "\r\n"
                . 'Content-Length: ' . strlen($requestBody) . "\r\n\r\n"
                . $requestBody;
            fwrite($socket, $rawRequest);
            $rawResponse = stream_get_contents($socket);
            fclose($socket);
            $rawResponse = $rawResponse === false ? '' : $rawResponse;
            $responseParts = explode("\r\n\r\n", $rawResponse, 2);
            $responseHeaderLines = explode("\r\n", $responseParts[0] ?? '');
            preg_match('/^HTTP\\/\\S+\\s+(\\d+)/', (string) ($responseHeaderLines[0] ?? ''), $statusMatch);
            $responses[] = [
                'status' => (int) ($statusMatch[1] ?? 0),
                'headers' => $responseHeaderLines,
                'body' => $responseParts[1] ?? '',
            ];
        }
    } finally {
        proc_terminate($process);
        fclose($pipes[0]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);
    }

    return $responses;
}

function phase2bConcurrentTrackingAttempts(string $ipAddress, int $workerCount): array
{
    $workerPath = tempnam(__DIR__, '.track_worker_');
    if ($workerPath === false) {
        return ['started' => false, 'all_succeeded' => false, 'locked' => 0];
    }
    $startPath = $workerPath . '.start';
    $readyMarkerPaths = [];
    $workerScript = "<?php\n"
        . "putenv('APP_ENV=test');\n"
        . "\$_SERVER['APP_ENV'] = 'test';\n"
        . "require " . var_export(__DIR__ . '/../app/Core/helpers.php', true) . ";\n"
        . "require " . var_export(__DIR__ . '/../app/Core/Config.php', true) . ";\n"
        . "require " . var_export(__DIR__ . '/../app/Core/Db.php', true) . ";\n"
        . "require " . var_export(__DIR__ . '/../app/Policies/Permissions.php', true) . ";\n"
        . "require " . var_export(__DIR__ . '/../app/Core/Auth.php', true) . ";\n"
        . "require " . var_export(__DIR__ . '/../app/Core/ApplicationTrackingLimiter.php', true) . ";\n"
        . "file_put_contents(\$argv[1], \"ready\\n\", LOCK_EX);\n"
        . "\$deadline = microtime(true) + 20;\n"
        . "while (!file_exists(" . var_export($startPath, true) . ")) { if (microtime(true) >= \$deadline) { fwrite(STDERR, 'barrier timeout'); exit(2); } usleep(1000); }\n"
        . "try { echo \\App\\Core\\ApplicationTrackingLimiter::recordFailure("
        . var_export($ipAddress, true) . ", 'test') ? 'LOCKED' : 'OPEN'; }\n"
        . "catch (\\Throwable \$exception) { fwrite(STDERR, get_class(\$exception)); exit(1); }\n";
    file_put_contents($workerPath, $workerScript);

    $processes = [];
    $started = false;
    $allSucceeded = true;
    $locked = 0;
    try {
        for ($index = 0; $index < $workerCount; $index++) {
            $readyMarkerPath = $workerPath . '.ready.' . $index;
            $readyMarkerPaths[] = $readyMarkerPath;
            $pipes = [];
            $process = proc_open([PHP_BINARY, $workerPath, $readyMarkerPath], [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ], $pipes, __DIR__);
            if (!is_resource($process)) {
                $allSucceeded = false;
                break;
            }
            fclose($pipes[0]);
            $processes[] = ['process' => $process, 'stdout' => $pipes[1], 'stderr' => $pipes[2]];
        }

        $deadline = microtime(true) + 20;
        while (count(array_filter($readyMarkerPaths, static fn (string $path): bool => is_file($path))) < count($processes) && microtime(true) < $deadline) {
            usleep(10000);
        }
        $started = count($processes) === $workerCount
            && count(array_filter($readyMarkerPaths, static fn (string $path): bool => is_file($path))) === $workerCount;
        if ($started) {
            file_put_contents($startPath, 'go');
        } else {
            $allSucceeded = false;
            file_put_contents($startPath, 'stop');
        }

        foreach ($processes as $entry) {
            $stdout = trim((string) stream_get_contents($entry['stdout']));
            $stderr = trim((string) stream_get_contents($entry['stderr']));
            fclose($entry['stdout']);
            fclose($entry['stderr']);
            $exitCode = proc_close($entry['process']);
            $allSucceeded = $allSucceeded && $exitCode === 0 && $stderr === '' && in_array($stdout, ['OPEN', 'LOCKED'], true);
            $locked += $stdout === 'LOCKED' ? 1 : 0;
        }
        $processes = [];
    } finally {
        foreach ($processes as $entry) {
            if (is_resource($entry['process'])) {
                $status = proc_get_status($entry['process']);
                if ($status['running']) {
                    proc_terminate($entry['process']);
                }
                proc_close($entry['process']);
            }
            foreach (['stdout', 'stderr'] as $pipeName) {
                if (is_resource($entry[$pipeName])) {
                    fclose($entry[$pipeName]);
                }
            }
        }
        foreach ([$workerPath, $startPath, ...$readyMarkerPaths] as $temporaryPath) {
            if (is_file($temporaryPath)) {
                unlink($temporaryPath);
            }
        }
    }

    return ['started' => $started, 'all_succeeded' => $allSucceeded, 'locked' => $locked];
}

$suffix = bin2hex(random_bytes(5));
$securityConfig = Config::get('security', []);
check(
    'Konfigurasi batas login akun/IP dan jendela lockout',
    is_array($securityConfig)
        && ($securityConfig['login_attempt_limit'] ?? null) === 5
        && ($securityConfig['ip_attempt_limit'] ?? null) === 30
        && ($securityConfig['lockout_window_seconds'] ?? null) === 15 * 60
);
$admin = phase2bUser('ADMIN', 'p2b-admin-' . $suffix, 'Admin Uji', 'secret-admin-' . $suffix);
$siswa = phase2bUser('SISWA', 'p2b-siswa-' . $suffix, 'Siswa Uji', 'secret-siswa-' . $suffix);
$guru = phase2bUser('GURU', 'p2b-guru-' . $suffix, 'Guru Uji', 'secret-guru-' . $suffix);
$inactive = phase2bUser('SISWA', 'p2b-inactive-' . $suffix, 'Nonaktif Uji', 'secret-inactive-' . $suffix, 0);
$adminTarget = phase2bUser('ADMIN', 'p2b-admin-target-' . $suffix, 'Admin Target', 'secret-target-' . $suffix);
$matrixTrackingCode = TrackingCode::generate();
Db::run(
    'INSERT INTO applications (service_id, applicant_user_id, tracking_code, request_text, status) VALUES ((SELECT id FROM services WHERE code = ?), ?, ?, ?, ?)',
    ['TU-01', (int) $siswa['id'], $matrixTrackingCode, 'Data fixture matriks', ApplicationWorkflow::INITIAL_STATUS],
    'test'
);
$matrixApplicationId = (int) Db::one(
    'SELECT id FROM applications WHERE tracking_code = ?',
    [$matrixTrackingCode],
    'test'
)['id'];
Db::run(
    'INSERT INTO application_history (application_id, old_status, new_status, changed_by) VALUES (?, NULL, ?, ?)',
    [$matrixApplicationId, ApplicationWorkflow::INITIAL_STATUS, (int) $siswa['id']],
    'test'
);
$cliCommand = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/../bin/buat-admin.php') . ' ' . escapeshellarg($admin['identifier']) . ' 2>&1';
exec($cliCommand, $cliOutput, $cliExit);
$cliText = implode(PHP_EOL, $cliOutput);
$cliSource = file_get_contents(__DIR__ . '/../bin/buat-admin.php');
check('CLI admin menolak username ganda sebelum meminta sandi; tidak mencetak sandi', $cliExit !== 0 && str_contains($cliText, 'Username sudah ada.') && !str_contains($cliText, $admin['password']));
check('CLI admin memakai Read-Host -AsSecureString dan gagal tertutup', str_contains((string) $cliSource, 'Read-Host -Prompt') && str_contains((string) $cliSource, '-AsSecureString') && str_contains((string) $cliSource, 'Input sandi tersembunyi gagal'));

$getRoutes = [
    '/' => 'home.public',
    '/login' => 'login',
    '/layanan' => 'services.catalog',
    '/layanan/KES-01' => 'services.detail',
    '/layanan/TU-01/ajukan' => 'applications.submit',
    '/pengajuan/diterima' => 'applications.submit',
    '/lacak' => 'applications.track',
    '/portal' => 'portal.view',
    '/admin' => 'admin.dashboard',
    '/admin/pengguna' => 'admin.users.manage',
    '/admin/audit' => 'admin.audit.view',
    '/admin/layanan' => 'admin.services.manage',
    '/admin/layanan/TU-01/ubah' => 'admin.services.edit',
    '/admin/pengajuan' => 'admin.applications.manage',
    '/admin/pengajuan/' . $matrixApplicationId => 'admin.applications.detail',
];
$accounts = ['GUEST' => null, 'SISWA' => $siswa, 'GURU' => $guru, 'ADMIN' => $admin];
$matrixPassed = true;
$matrixEvidence = [];
foreach ($accounts as $role => $account) {
    foreach ($getRoutes as $path => $ability) {
        phase2bAs($account);
        $response = phase2bRequest('GET', $path);
        $allowed = Permissions::can($role, $ability);
        $matchesPolicy = $allowed
            ? in_array($response['status'], [200, 302], true)
            : in_array($response['status'], $role === 'GUEST' ? [302, 403] : [403], true);
        $matrixPassed = $matrixPassed && $matchesPolicy;
        if (!$matchesPolicy) {
            $matrixEvidence[] = $role . ' ' . $path . '=' . $response['status'] . ' ability=' . $ability;
        }
    }
}
check('Matriks role x seluruh rute GET konsisten dengan Permissions', $matrixPassed, implode('; ', $matrixEvidence));
phase2bAs(null);
$guestHome = phase2bRequest('GET', '/');
phase2bAs($admin);
$adminHome = phase2bRequest('GET', '/');
check(
    'Navigasi memuat katalog publik dan tautan Kelola layanan sesuai izin ADMIN',
    str_contains($guestHome['body'], '<a href="/layanan">Layanan</a>')
        && !str_contains($guestHome['body'], 'Kelola layanan')
        && str_contains($adminHome['body'], '<a href="/admin/layanan">Kelola layanan</a>')
        && str_contains($adminHome['body'], '<a href="/admin/pengajuan">Antrean pengajuan</a>')
);
$matrixDocumentation = file_get_contents(__DIR__ . '/../docs/MATRIKS_IZIN.md');
$documentedAccountRoutes = [
    'GET /admin/pengguna',
    'POST /admin/pengguna (buat pengguna)',
    'POST /admin/pengguna/{id}/role',
    'POST /admin/pengguna/{id}/nonaktif',
    'POST /admin/pengguna/{id}/reset-sandi',
    'GET /admin/layanan',
    'GET /admin/layanan/{kode}/ubah',
    'POST /admin/layanan/{kode}',
    'GET /layanan/{kode}/ajukan',
    'POST /layanan/{kode}/ajukan',
    'GET /lacak',
    'POST /lacak',
    'GET /pengajuan/diterima',
    'GET /admin/pengajuan',
    'GET /admin/pengajuan/{id}',
    'POST /admin/pengajuan/{id}/status',
];
$docsContainRoutes = true;
foreach ($documentedAccountRoutes as $documentedRoute) {
    $docsContainRoutes = $docsContainRoutes && str_contains((string) $matrixDocumentation, $documentedRoute);
}
check('Rute manajemen pengguna dalam tes tercantum di matriks izin', $docsContainRoutes);

$postRoutes = [
    '/logout' => ['logout', []],
    '/admin/pengguna' => ['admin.users.create', ['identifier' => 'matrix-' . $suffix, 'full_name' => 'Matrix', 'role' => 'SISWA', 'password' => 'secret-matrix-' . $suffix]],
    '/admin/pengguna/' . $siswa['id'] . '/role' => ['admin.users.role', ['role' => 'GURU']],
    '/admin/pengguna/' . $adminTarget['id'] . '/nonaktif' => ['admin.users.deactivate', []],
    '/admin/pengguna/' . $guru['id'] . '/reset-sandi' => ['admin.users.reset_password', ['password' => 'secret-matrix-reset-' . $suffix]],
    '/admin/layanan/TU-01' => ['admin.services.update', ['mode' => 'FORMULIR', 'fee_text' => '', 'sla_text' => '', 'responsible_unit' => '', 'required_documents_text' => '']],
    '/layanan/TU-01/ajukan' => ['applications.submit', ['request_text' => 'Uji matriks']],
    '/lacak' => ['applications.track.lookup', ['tracking_code' => 'invalid-matrix-code']],
    '/admin/pengajuan/' . $matrixApplicationId . '/status' => ['admin.applications.status', ['status' => 'DIPROSES']],
];
$postMatrixPassed = true;
$postMatrixEvidence = [];
foreach (['GUEST' => null, 'SISWA' => $siswa, 'GURU' => $guru, 'ADMIN' => $admin] as $role => $account) {
    foreach ($postRoutes as $path => [$ability, $payload]) {
        if (Permissions::can($role, $ability)) {
            continue;
        }
        phase2bAs($account);
        $payload['_csrf'] = Csrf::token();
        $response = phase2bRequest('POST', $path, $payload);
        $expectedDenial = $role === 'GUEST'
            ? in_array($response['status'], [302, 403], true)
            : $response['status'] === 403;
        $postMatrixPassed = $postMatrixPassed && $expectedDenial;
        if (!$expectedDenial) {
            $postMatrixEvidence[] = $role . ' POST ' . $path . '=' . $response['status'] . ' ability=' . $ability;
        }
    }
}
check('Matriks role x rute POST menolak semua role di luar Permissions', $postMatrixPassed, implode('; ', $postMatrixEvidence));

$postLoginPassed = true;
foreach ($accounts as $role => $account) {
    $loginAccount = $account ?? $admin;
    phase2bAs(null);
    $_SERVER['REMOTE_ADDR'] = '203.0.113.' . random_int(10, 240);
    $loginRole = $role === 'ADMIN' || $role === 'GUEST' ? 'ADMIN' : 'SISWA_GURU';
    $postLogin = phase2bRequest('POST', '/login', [
        '_csrf' => Csrf::token(),
        'identifier' => $loginAccount['identifier'],
        'password' => $loginAccount['password'],
        'login_role' => $loginRole,
    ]);
    $postLoginPassed = $postLoginPassed && Permissions::can($role, 'login') && in_array($postLogin['status'], [200, 302], true);
    $currentToken = (string) Db::one('SELECT auth_token FROM users WHERE id = ?', [$loginAccount['id']], 'test')['auth_token'];
    if ($admin['id'] === $loginAccount['id']) {
        $admin['token'] = $currentToken;
    } elseif ($siswa['id'] === $loginAccount['id']) {
        $siswa['token'] = $currentToken;
    } elseif ($guru['id'] === $loginAccount['id']) {
        $guru['token'] = $currentToken;
    }
    if ($account !== null) {
        $account['token'] = $currentToken;
    }

    phase2bAs($account);
    $postLogout = phase2bRequest('POST', '/logout', ['_csrf' => Csrf::token()]);
    $logoutAllowed = Permissions::can($role, 'logout');
    $logoutMatches = $logoutAllowed
        ? $postLogout['status'] === 302
        : ($role === 'GUEST' && in_array($postLogout['status'], [302, 403], true));
    $postLoginPassed = $postLoginPassed && $logoutMatches;
}
check('Matriks role x rute POST login/logout sesuai Permissions', $postLoginPassed);

$adminPosts = [
    '/login' => ['identifier' => $admin['identifier'], 'password' => $admin['password'], 'login_role' => 'ADMIN'],
    '/logout' => [],
    '/admin/pengguna' => ['identifier' => 'csrf-' . $suffix, 'full_name' => 'CSRF Test', 'role' => 'SISWA', 'password' => 'secret-csrf-' . $suffix],
    '/admin/pengguna/' . $siswa['id'] . '/role' => ['role' => 'GURU'],
    '/admin/pengguna/' . $adminTarget['id'] . '/nonaktif' => [],
    '/admin/pengguna/' . $guru['id'] . '/reset-sandi' => ['password' => 'secret-reset-' . $suffix],
    '/admin/layanan/TU-01' => ['mode' => 'FORMULIR', 'fee_text' => '', 'sla_text' => '', 'responsible_unit' => '', 'required_documents_text' => ''],
    '/lacak' => ['tracking_code' => 'invalid-csrf-code'],
    '/admin/pengajuan/' . $matrixApplicationId . '/status' => ['status' => 'DIPROSES'],
];
$csrfPassed = true;
foreach ($adminPosts as $path => $payload) {
    foreach (['missing', 'wrong', 'foreign'] as $tokenCase) {
        phase2bAs($admin);
        $token = match ($tokenCase) {
            'missing' => null,
            'wrong' => 'invalid-token-' . $suffix,
            'foreign' => phase2bForeignCsrfToken(),
        };
        if ($token !== null) {
            $payload['_csrf'] = $token;
        }
        $response = phase2bRequest('POST', $path, $payload);
        $csrfPassed = $csrfPassed && $response['status'] === 403;
    }
}
check('CSRF rute nyata menolak token kosong, salah, dan milik sesi lain', $csrfPassed);

phase2bAs(null);
$catalogIndexResponse = phase2bRequest('GET', '/layanan');
$catalogDetailResponse = phase2bRequest('GET', '/layanan/KES-01');
$formCatalogDetailResponse = phase2bRequest('GET', '/layanan/TU-02');
$complaintCatalogDetailResponse = phase2bRequest('GET', '/layanan/HUM-02');
$unknownCatalogDetail = phase2bRequest('GET', '/layanan/NO-99');
check(
    'Katalog dan detail layanan publik dapat diakses tanpa login; kode tidak dikenal 404',
    $catalogIndexResponse['status'] === 200
        && $catalogDetailResponse['status'] === 200
        && $unknownCatalogDetail['status'] === 404
);
check(
    'HUM-02 hanya menjelaskan modul pengaduan tanpa formulir',
    $complaintCatalogDetailResponse['status'] === 200
        && str_contains($complaintCatalogDetailResponse['body'], 'Layanan ini ditangani melalui modul pengaduan.')
        && !str_contains($complaintCatalogDetailResponse['body'], '<form')
        && !str_contains($complaintCatalogDetailResponse['body'], '>Ajukan<')
);
check(
    'Standar kosong tampil BELUM DITETAPKAN dan KES-01 memuat catatan privasi',
    str_contains($catalogDetailResponse['body'], 'BELUM DITETAPKAN')
        && str_contains($catalogDetailResponse['body'], 'Isi konseling dan data pribadi sensitif tidak disimpan di sistem.')
        && str_contains($catalogDetailResponse['body'], ServiceCatalog::INFORMATION_STEPS_NOTICE)
);
$informationStepNoticeCount = substr_count($catalogDetailResponse['body'], ServiceCatalog::INFORMATION_STEPS_NOTICE);
check(
    'Tahap 3 sampai 5 mode INFORMASI tetap tampil dengan satu aturan keterangan',
    $informationStepNoticeCount === 3
);

phase2bAs($siswa);
$trackingProbeCodes = [];
for ($index = 0; $index < 200; $index++) {
    $trackingProbeCodes[] = TrackingCode::generate();
}
$trackingAlphabet = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
$nextTrackingCode = static function (string $code) use ($trackingAlphabet): string {
    $characters = str_replace('-', '', substr($code, 4));
    for ($index = strlen($characters) - 1; $index >= 0; $index--) {
        $alphabetIndex = strpos($trackingAlphabet, $characters[$index]);
        if ($alphabetIndex < strlen($trackingAlphabet) - 1) {
            $characters[$index] = $trackingAlphabet[$alphabetIndex + 1];
            break;
        }
        $characters[$index] = $trackingAlphabet[0];
    }
    return 'HMA-' . implode('-', str_split($characters, 4));
};
$hasSequentialTrackingPair = false;
for ($index = 1; $index < count($trackingProbeCodes); $index++) {
    $hasSequentialTrackingPair = $hasSequentialTrackingPair
        || $nextTrackingCode($trackingProbeCodes[$index - 1]) === $trackingProbeCodes[$index]
        || $nextTrackingCode($trackingProbeCodes[$index]) === $trackingProbeCodes[$index - 1];
}
$trackingProbeSorted = $trackingProbeCodes;
sort($trackingProbeSorted, SORT_STRING);
check(
    'Kode lacak 200 sampel unik, berformat tetap, dan tidak berurutan',
    count(array_unique($trackingProbeCodes)) === 200
        && count(array_filter($trackingProbeCodes, [TrackingCode::class, 'isValid'])) === 200
        && strlen($trackingProbeCodes[0]) === 23
        && !$hasSequentialTrackingPair
        && $trackingProbeCodes !== $trackingProbeSorted
);

$applicationRequestText = '<script>alert("uji")</script> ' . "' OR 1=1 -- " . '🙂';
$applicationCreateResponse = phase2bRequest('POST', '/layanan/TU-01/ajukan', [
    '_csrf' => Csrf::token(),
    'request_text' => $applicationRequestText,
]);
$applicationsAfterSubmit = (int) Db::one(
    'SELECT COUNT(*) AS n FROM applications WHERE applicant_user_id = ?',
    [(int) $siswa['id']],
    'test'
)['n'];
$applicationSuccessResponse = phase2bRequest('GET', '/pengajuan/diterima');
$trackingCodeMatch = [];
preg_match('/<code>([^<]+)<\/code>/', $applicationSuccessResponse['body'], $trackingCodeMatch);
$createdTrackingCode = html_entity_decode((string) ($trackingCodeMatch[1] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
$applicationSuccessRefresh = phase2bRequest('GET', '/pengajuan/diterima');
$applicationsAfterSuccessRefresh = (int) Db::one(
    'SELECT COUNT(*) AS n FROM applications WHERE applicant_user_id = ?',
    [(int) $siswa['id']],
    'test'
)['n'];
$createdApplication = Db::one(
    'SELECT id, service_id, applicant_user_id, tracking_code, request_text, status FROM applications WHERE tracking_code = ?',
    [$createdTrackingCode],
    'test'
);
$applicationCreateAudit = Db::one(
    'SELECT actor_id, action, entity_type, entity_id, message FROM audit_logs WHERE action = ? AND entity_id = ? ORDER BY id DESC LIMIT 1',
    ['application_submitted', (int) ($createdApplication['id'] ?? 0)],
    'test'
);
$applicationCountBeforeInvalid = (int) Db::one(
    'SELECT COUNT(*) AS n FROM applications WHERE applicant_user_id = ?',
    [(int) $siswa['id']],
    'test'
)['n'];
$emptyApplication = phase2bRequest('POST', '/layanan/TU-01/ajukan', ['_csrf' => Csrf::token(), 'request_text' => '   ']);
$longApplication = phase2bRequest('POST', '/layanan/TU-01/ajukan', ['_csrf' => Csrf::token(), 'request_text' => str_repeat('x', 4001)]);
$invalidUtf8Application = phase2bRequest('POST', '/layanan/TU-01/ajukan', ['_csrf' => Csrf::token(), 'request_text' => "\xC3\x28"]);
$applicationCountAfterInvalid = (int) Db::one(
    'SELECT COUNT(*) AS n FROM applications WHERE applicant_user_id = ?',
    [(int) $siswa['id']],
    'test'
)['n'];
check(
    'Pengajuan FORMULIR menerima UTF-8, HTML dan SQL sebagai teks; input kosong, invalid UTF-8, dan terlalu panjang ditolak',
    $applicationCreateResponse['status'] === 303
        && !str_contains($applicationCreateResponse['body'], $createdTrackingCode)
        && $applicationSuccessResponse['status'] === 200
        && substr_count($applicationSuccessResponse['body'], $createdTrackingCode) === 1
        && !str_contains($applicationSuccessRefresh['body'], $createdTrackingCode)
        && $applicationsAfterSubmit === $applicationsAfterSuccessRefresh
        && $createdApplication !== null
        && $createdApplication['request_text'] === $applicationRequestText
        && $createdApplication['status'] === ApplicationWorkflow::INITIAL_STATUS
        && $emptyApplication['status'] === 422
        && $longApplication['status'] === 422
        && $invalidUtf8Application['status'] === 422
        && $applicationCountAfterInvalid === $applicationCountBeforeInvalid
);

$rateLimitedUser = phase2bUser('SISWA', 'p2b-rate-limit-' . $suffix, 'Pengaju Terbatas', 'secret-rate-limit-' . $suffix);
phase2bAs($rateLimitedUser);
$rateLimitResponses = [];
for ($index = 0; $index < 5; $index++) {
    $rateLimitResponses[] = phase2bRequest('POST', '/layanan/TU-01/ajukan', [
        '_csrf' => Csrf::token(),
        'request_text' => 'Pengajuan dalam kuota ' . $index,
    ]);
}
$rateLimitedCountBeforeSixth = (int) Db::one(
    'SELECT COUNT(*) AS n FROM applications WHERE applicant_user_id = ?',
    [(int) $rateLimitedUser['id']],
    'test'
)['n'];
$sixthApplicationResponse = phase2bRequest('POST', '/layanan/TU-01/ajukan', [
    '_csrf' => Csrf::token(),
    'request_text' => 'Pengajuan keenam',
]);
$rateLimitedCountAfterSixth = (int) Db::one(
    'SELECT COUNT(*) AS n FROM applications WHERE applicant_user_id = ?',
    [(int) $rateLimitedUser['id']],
    'test'
)['n'];
$otherRateUser = phase2bUser('SISWA', 'p2b-rate-other-' . $suffix, 'Pengaju Lain', 'secret-rate-other-' . $suffix);
phase2bAs($otherRateUser);
$otherRateResponse = phase2bRequest('POST', '/layanan/TU-01/ajukan', [
    '_csrf' => Csrf::token(),
    'request_text' => 'Akun lain tidak terpengaruh',
]);
check(
    'Kuota lima pengajuan per jam menolak pengajuan keenam tanpa menyimpan baris dan tidak membatasi akun lain',
    count(array_filter($rateLimitResponses, static fn (array $response): bool => $response['status'] === 303)) === 5
        && $rateLimitedCountBeforeSixth === 5
        && $sixthApplicationResponse['status'] === 429
        && $rateLimitedCountAfterSixth === $rateLimitedCountBeforeSixth
        && $otherRateResponse['status'] === 303
);

$informationApplicationResponse = phase2bRequest('POST', '/layanan/KUR-01/ajukan', [
    '_csrf' => Csrf::token(),
    'request_text' => 'Tidak boleh',
]);
$complaintApplicationResponse = phase2bRequest('POST', '/layanan/HUM-02/ajukan', [
    '_csrf' => Csrf::token(),
    'request_text' => 'Tidak boleh',
]);
$unknownApplicationResponse = phase2bRequest('POST', '/layanan/NO-99/ajukan', [
    '_csrf' => Csrf::token(),
    'request_text' => 'Tidak boleh',
]);
$kes01ApplicationResponse = phase2bRequest('POST', '/layanan/KES-01/ajukan', [
    '_csrf' => Csrf::token(),
    'request_text' => 'Isi konseling tidak boleh disimpan',
]);
phase2bAs($siswa);
$kes02FormResponse = phase2bRequest('GET', '/layanan/KES-02/ajukan');
$kes04FormResponse = phase2bRequest('GET', '/layanan/KES-04/ajukan');
check(
    'Server menolak pengajuan INFORMASI, HUM-02 dan kode tidak dikenal; KES-01 memberi catatan privasi',
    $informationApplicationResponse['status'] === 422
        && $complaintApplicationResponse['status'] === 422
        && $unknownApplicationResponse['status'] === 404
        && $kes01ApplicationResponse['status'] === 422
        && str_contains($kes01ApplicationResponse['body'], 'Isi konseling dan data pribadi sensitif tidak disimpan di sistem.')
);
check(
    'Formulir KES-02 dan KES-04 menampilkan catatan minimisasi data siswa',
    $kes02FormResponse['status'] === 200
        && $kes04FormResponse['status'] === 200
        && str_contains($kes02FormResponse['body'], 'kirim hanya data minimum yang diperlukan')
        && str_contains($kes04FormResponse['body'], 'bukan pada cek status publik')
);

$submissionCsrfPassed = true;
foreach (['missing', 'wrong', 'foreign'] as $tokenCase) {
    phase2bAs($siswa);
    $token = match ($tokenCase) {
        'missing' => null,
        'wrong' => 'invalid-submit-token-' . $suffix,
        'foreign' => phase2bForeignCsrfToken(),
    };
    $payload = ['request_text' => 'CSRF pengajuan ' . $tokenCase];
    if ($token !== null) {
        $payload['_csrf'] = $token;
    }
    $response = phase2bRequest('POST', '/layanan/TU-01/ajukan', $payload);
    $submissionCsrfPassed = $submissionCsrfPassed && $response['status'] === 403;
}
check('CSRF rute pengajuan menolak token kosong, salah, dan milik sesi lain', $submissionCsrfPassed);

phase2bAs(null);
$trackingForm = phase2bRequest('GET', '/lacak');
$trackingCsrfPassed = true;
foreach (['missing', 'wrong', 'foreign'] as $tokenCase) {
    phase2bAs(null);
    $token = match ($tokenCase) {
        'missing' => null,
        'wrong' => 'invalid-tracking-token-' . $suffix,
        'foreign' => phase2bForeignCsrfToken(),
    };
    $payload = ['tracking_code' => 'invalid-code'];
    if ($token !== null) {
        $payload['_csrf'] = $token;
    }
    $response = phase2bRequest('POST', '/lacak', $payload);
    $trackingCsrfPassed = $trackingCsrfPassed && $response['status'] === 403;
}
check(
    'Cek status memakai GET formulir dan menolak POST tanpa CSRF valid',
    $trackingForm['status'] === 200
        && str_contains($trackingForm['body'], 'action="/lacak"')
        && !str_contains($trackingForm['body'], '/lacak/')
        && $trackingCsrfPassed
);

phase2bAs($admin);
$applicationAdminDetail = phase2bRequest('GET', '/admin/pengajuan/' . (int) ($createdApplication['id'] ?? 0));
$trackingKnownIp = '198.51.100.210';
$_SERVER['REMOTE_ADDR'] = $trackingKnownIp;
$trackingMalformed = phase2bRequest('POST', '/lacak', ['_csrf' => Csrf::token(), 'tracking_code' => 'not-a-valid-code']);
$trackingNonString = phase2bRequest('POST', '/lacak', ['_csrf' => Csrf::token(), 'tracking_code' => ['not-a-string']]);
$trackingUnknown = phase2bRequest('POST', '/lacak', ['_csrf' => Csrf::token(), 'tracking_code' => 'HMA-2222-2222-2222-2223']);
$_SERVER['REMOTE_ADDR'] = '198.51.100.211';
$trackingPublicStatus = phase2bRequest('POST', '/lacak', ['_csrf' => Csrf::token(), 'tracking_code' => $createdTrackingCode]);
check(
    'Cek status tidak membocorkan data pemohon dan menyamakan kode salah format dengan kode tidak ada',
    $trackingMalformed['status'] === 404
        && $trackingNonString['status'] === 404
        && $trackingNonString['body'] === $trackingMalformed['body']
        && $trackingUnknown['status'] === 404
        && $trackingMalformed['body'] === $trackingUnknown['body']
        && $trackingPublicStatus['status'] === 200
        && str_contains($trackingPublicStatus['body'], 'Diajukan')
        && str_contains($trackingPublicStatus['body'], 'Pengajuan permintaan')
        && !str_contains($trackingPublicStatus['body'], (string) $siswa['name'])
        && !str_contains($trackingPublicStatus['body'], (string) $siswa['identifier'])
        && !str_contains($trackingPublicStatus['body'], $applicationRequestText)
        && $applicationCreateAudit !== null
        && !str_contains((string) $applicationCreateAudit['message'], $applicationRequestText)
        && !str_contains((string) $applicationCreateAudit['message'], $createdTrackingCode)
        && !str_contains($trackingPublicStatus['body'], '<script>')
);
check(
    'Rincian HTML/SQL permintaan hanya tampil ter-escape pada antrean ADMIN',
    $applicationAdminDetail['status'] === 200
        && !str_contains($applicationAdminDetail['body'], '<script>')
        && str_contains($applicationAdminDetail['body'], '&lt;script&gt;')
        && str_contains($applicationAdminDetail['body'], '&quot;uji&quot;')
        && str_contains($applicationAdminDetail['body'], e($applicationRequestText))
        && !str_contains((string) ($createdApplication['tracking_code'] ?? ''), "' OR 1=1")
);

$trackingRateIp = '198.51.100.212';
$_SERVER['REMOTE_ADDR'] = $trackingRateIp;
for ($index = 0; $index < 30; $index++) {
    phase2bRequest('POST', '/lacak', ['_csrf' => Csrf::token(), 'tracking_code' => 'INVALID-' . $index]);
}
$trackingFailures = (int) Db::one(
    'SELECT COUNT(*) AS n FROM application_tracking_attempts WHERE ip_address = ? AND attempt_type = ?',
    [$trackingRateIp, 'FAILURE'],
    'test'
)['n'];
$trackingTokenLogged = Db::one(
    'SELECT column_name FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name IN (?, ?)',
    ['application_tracking_attempts', 'identifier', 'tracking_code'],
    'test'
);
$trackingIpLocks = (int) Db::one(
    'SELECT COUNT(*) AS n FROM application_tracking_attempts WHERE ip_address = ? AND attempt_type = ?',
    [$trackingRateIp, 'LOCKED'],
    'test'
)['n'];
$trackingLockedInvalid = phase2bRequest('POST', '/lacak', ['_csrf' => Csrf::token(), 'tracking_code' => 'bad-locked-code']);
$trackingLockedValid = phase2bRequest('POST', '/lacak', ['_csrf' => Csrf::token(), 'tracking_code' => $createdTrackingCode]);
$_SERVER['REMOTE_ADDR'] = '198.51.100.213';
$trackingOtherIpValid = phase2bRequest('POST', '/lacak', ['_csrf' => Csrf::token(), 'tracking_code' => $createdTrackingCode]);
$_SERVER['REMOTE_ADDR'] = $trackingRateIp;
$loginAfterTrackingLock = Auth::processLogin($siswa['identifier'], $siswa['password'], 'SISWA_GURU');
$siswa['token'] = (string) Db::one(
    'SELECT auth_token FROM users WHERE id = ?',
    [(int) $siswa['id']],
    'test'
)['auth_token'];
$loginRateIp = '198.51.100.214';
for ($index = 0; $index < 30; $index++) {
    Auth::recordFailure('login-track-isolation-' . $index, null, $loginRateIp, 'test');
}
$_SERVER['REMOTE_ADDR'] = $loginRateIp;
phase2bAs(null);
$trackingAfterLoginLock = phase2bRequest('POST', '/lacak', [
    '_csrf' => Csrf::token(),
    'tracking_code' => $createdTrackingCode,
]);
check(
    'Hitungan cek status dan login terpisah per IP; IP lain dan pengenal global tidak terkunci',
    $trackingFailures === 30
        && $trackingIpLocks === 1
        && $trackingTokenLogged === null
        && $trackingLockedInvalid['status'] === 429
        && $trackingLockedValid['status'] === 429
        && $trackingLockedValid['body'] === $trackingLockedInvalid['body']
        && $trackingOtherIpValid['status'] === 200
        && $loginAfterTrackingLock['ok'] === true
        && Auth::isLockedOut('login-track-isolation-29', $loginRateIp, 'test')
        && $trackingAfterLoginLock['status'] === 200
        && !Auth::isLockedOut($siswa['identifier'], $trackingRateIp, 'test')
        && !Auth::isLockedOut('application_tracking_lookup', null, 'test')
        && (int) Db::one(
            'SELECT COUNT(*) AS n FROM login_attempts WHERE identifier = ?',
            ['application_tracking_lookup'],
            'test'
        )['n'] === 0
    ,
    'failures=' . $trackingFailures
        . '; locks=' . $trackingIpLocks
        . '; lockedInvalid=' . $trackingLockedInvalid['status']
        . '; lockedValid=' . $trackingLockedValid['status']
        . '; otherIp=' . $trackingOtherIpValid['status']
        . '; loginAfterTracking=' . ($loginAfterTrackingLock['ok'] ? 'ok' : 'failed')
        . '; loginLock=' . (Auth::isLockedOut('login-track-isolation-29', $loginRateIp, 'test') ? 'yes' : 'no')
        . '; trackingAfterLoginLock=' . $trackingAfterLoginLock['status']
        . '; accountLock=' . (Auth::isLockedOut($siswa['identifier'], $trackingRateIp, 'test') ? 'yes' : 'no')
);

$concurrentTrackingIp = '192.0.2.250';
$concurrentAttempts = phase2bConcurrentTrackingAttempts($concurrentTrackingIp, 36);
$concurrentFailureCount = (int) Db::one(
    'SELECT COUNT(*) AS n FROM application_tracking_attempts WHERE ip_address = ? AND attempt_type = ?',
    [$concurrentTrackingIp, 'FAILURE'],
    'test'
)['n'];
$concurrentLockCount = (int) Db::one(
    'SELECT COUNT(*) AS n FROM application_tracking_attempts WHERE ip_address = ? AND attempt_type = ?',
    [$concurrentTrackingIp, 'LOCKED'],
    'test'
)['n'];
check(
    'Limiter paralel menserialisasi 36 kegagalan pada IP yang sama tanpa melewati ambang 30',
    $concurrentAttempts['started']
        && $concurrentAttempts['all_succeeded']
        && $concurrentAttempts['locked'] === 7
        && $concurrentFailureCount === 30
        && $concurrentLockCount === 1,
    'started=' . ($concurrentAttempts['started'] ? 'yes' : 'no')
        . '; processes=' . ($concurrentAttempts['all_succeeded'] ? 'ok' : 'failed')
        . '; lockedResponses=' . $concurrentAttempts['locked']
        . '; failures=' . $concurrentFailureCount
        . '; lockRows=' . $concurrentLockCount
);

$natSharedIp = '203.0.113.250';
$_SERVER['REMOTE_ADDR'] = $natSharedIp;
phase2bAs($siswa);
$natSiswaFailure = phase2bRequest('POST', '/lacak', [
    '_csrf' => Csrf::token(),
    'tracking_code' => 'invalid-from-siswa',
]);
phase2bAs($guru);
$natGuruFailure = phase2bRequest('POST', '/lacak', [
    '_csrf' => Csrf::token(),
    'tracking_code' => 'invalid-from-guru',
]);
for ($index = 2; $index < $securityConfig['ip_attempt_limit']; $index++) {
    phase2bAs($index % 2 === 0 ? $siswa : $guru);
    phase2bRequest('POST', '/lacak', [
        '_csrf' => Csrf::token(),
        'tracking_code' => 'invalid-shared-ip-' . $index,
    ]);
}
$natSharedFailureCount = (int) Db::one(
    'SELECT COUNT(*) AS n FROM application_tracking_attempts WHERE ip_address = ? AND attempt_type = ?',
    [$natSharedIp, 'FAILURE'],
    'test'
)['n'];
$natSharedLockCount = (int) Db::one(
    'SELECT COUNT(*) AS n FROM application_tracking_attempts WHERE ip_address = ? AND attempt_type = ?',
    [$natSharedIp, 'LOCKED'],
    'test'
)['n'];
phase2bAs($guru);
$natOtherUserLocked = phase2bRequest('POST', '/lacak', [
    '_csrf' => Csrf::token(),
    'tracking_code' => 'invalid-after-shared-lock',
]);
check(
    'Dua pengguna di balik shared IP berbagi ambang cek status dan lockout sesuai kebijakan per-IP',
    $natSiswaFailure['status'] === 404
        && $natGuruFailure['status'] === 404
        && $natSharedFailureCount === $securityConfig['ip_attempt_limit']
        && $natSharedLockCount === 1
        && $natOtherUserLocked['status'] === 429
);

$lockWindowSeconds = (int) $securityConfig['lockout_window_seconds'];
Db::run(
    'UPDATE application_tracking_attempts SET created_at = DATE_SUB(NOW(), INTERVAL ? SECOND) WHERE ip_address = ?',
    [$lockWindowSeconds + 1, $concurrentTrackingIp],
    'test'
);
$expiredLockIsActive = ApplicationTrackingLimiter::isLockedOut($concurrentTrackingIp, 'test');
$failureAfterWindow = ApplicationTrackingLimiter::recordFailure($concurrentTrackingIp, 'test');
$failuresAfterWindow = (int) Db::one(
    'SELECT COUNT(*) AS n FROM application_tracking_attempts WHERE ip_address = ? AND attempt_type = ? AND created_at >= DATE_SUB(NOW(), INTERVAL ? SECOND)',
    [$concurrentTrackingIp, 'FAILURE', $lockWindowSeconds],
    'test'
)['n'];
check(
    'Jendela limiter mengakhiri lockout dan memulai hitungan baru setelah kedaluwarsa',
    !$expiredLockIsActive && !$failureAfterWindow && $failuresAfterWindow === 1
);

$databaseFailureIp = '203.0.113.251';
$renamedAttemptsTable = 'application_tracking_attempts_backup_' . bin2hex(random_bytes(4));
$databaseFailurePropagated = false;
Db::pdo('test')->exec('RENAME TABLE application_tracking_attempts TO ' . $renamedAttemptsTable);
try {
    ApplicationTrackingLimiter::recordFailure($databaseFailureIp, 'test');
} catch (PDOException) {
    $databaseFailurePropagated = true;
} finally {
    Db::pdo('test')->exec('RENAME TABLE ' . $renamedAttemptsTable . ' TO application_tracking_attempts');
}
$lockReleasedAfterDatabaseFailure = ApplicationTrackingLimiter::recordFailure($databaseFailureIp, 'test') === false;
check(
    'Kegagalan pencatatan basis data diteruskan dan lock dilepas agar pencatatan dapat pulih',
    $databaseFailurePropagated && $lockReleasedAfterDatabaseFailure
);

$probeSessionId = session_id();
phase2bAs($siswa);
$probeCsrfToken = Csrf::token();
session_write_close();
$probeCookie = session_name() . '=' . $probeSessionId;
$applicationsBeforeHeaderProbe = (int) Db::one('SELECT COUNT(*) AS n FROM applications', [], 'test')['n'];
$headerProbeResponses = phase2bHttpRequests([
    ['method' => 'GET', 'path' => '/lacak', 'cookie' => $probeCookie],
    ['method' => 'POST', 'path' => '/lacak', 'cookie' => $probeCookie, 'post' => ['_csrf' => $probeCsrfToken, 'tracking_code' => $createdTrackingCode]],
    ['method' => 'POST', 'path' => '/layanan/TU-01/ajukan', 'cookie' => $probeCookie, 'post' => ['_csrf' => $probeCsrfToken, 'request_text' => 'PRG HTTP probe']],
    ['method' => 'GET', 'path' => '/pengajuan/diterima', 'cookie' => $probeCookie],
    ['method' => 'GET', 'path' => '/pengajuan/diterima', 'cookie' => $probeCookie],
]);
$hasPrivateHeaders = static function (array $response): bool {
    $headers = array_map('strtolower', $response['headers'] ?? []);
    return in_array('cache-control: no-store', $headers, true)
        && in_array('referrer-policy: no-referrer', $headers, true);
};
$redirectHeaderPresent = static function (array $response): bool {
    foreach ($response['headers'] ?? [] as $header) {
        if (strtolower($header) === 'location: /pengajuan/diterima') {
            return true;
        }
    }
    return false;
};
$probeTrackingCodeMatch = [];
preg_match('/<code>([^<]+)<\/code>/', (string) ($headerProbeResponses[3]['body'] ?? ''), $probeTrackingCodeMatch);
$probeTrackingCode = html_entity_decode((string) ($probeTrackingCodeMatch[1] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
$applicationsAfterHeaderProbe = (int) Db::one('SELECT COUNT(*) AS n FROM applications', [], 'test')['n'];
session_id($probeSessionId);
session_start();
phase2bAs($siswa);
check(
    'Halaman lacak dan sukses mengirim header privasi; refresh tidak mengulang pengajuan atau menampilkan kode',
    count($headerProbeResponses) === 5
        && $headerProbeResponses[0]['status'] === 200
        && $headerProbeResponses[1]['status'] === 200
        && $headerProbeResponses[2]['status'] === 303
        && $redirectHeaderPresent($headerProbeResponses[2])
        && $headerProbeResponses[3]['status'] === 200
        && $probeTrackingCode !== ''
        && $probeTrackingCode !== $createdTrackingCode
        && $hasPrivateHeaders($headerProbeResponses[0])
        && $hasPrivateHeaders($headerProbeResponses[1])
        && $hasPrivateHeaders($headerProbeResponses[3])
        && str_contains($headerProbeResponses[3]['body'], 'Kode hanya ditampilkan sekali.')
        && substr_count($headerProbeResponses[3]['body'], $probeTrackingCode) === 1
        && !str_contains($headerProbeResponses[4]['body'], $probeTrackingCode)
        && $hasPrivateHeaders($headerProbeResponses[4])
        && $applicationsAfterHeaderProbe === $applicationsBeforeHeaderProbe + 1
);

$applicationMigrationSql = file_get_contents(__DIR__ . '/../database/migrations/005_applications.sql');
$applicationMigrationStatements = SqlStatementParser::parse((string) $applicationMigrationSql);
$applicationCountBeforeRerun = (int) Db::one('SELECT COUNT(*) AS n FROM applications', [], 'test')['n'];
$historyCountBeforeRerun = (int) Db::one('SELECT COUNT(*) AS n FROM application_history', [], 'test')['n'];
foreach ($applicationMigrationStatements as $applicationMigrationStatement) {
    Db::pdo('test')->exec($applicationMigrationStatement);
}
$applicationCountAfterRerun = (int) Db::one('SELECT COUNT(*) AS n FROM applications', [], 'test')['n'];
$historyCountAfterRerun = (int) Db::one('SELECT COUNT(*) AS n FROM application_history', [], 'test')['n'];
$applicationAfterRerun = Db::one(
    'SELECT request_text, tracking_code FROM applications WHERE id = ?',
    [(int) ($createdApplication['id'] ?? 0)],
    'test'
);
check(
    'Migrasi 005 idempoten dan mempertahankan data pengajuan saat dijalankan ulang',
    $applicationCountAfterRerun === $applicationCountBeforeRerun
        && $historyCountAfterRerun === $historyCountBeforeRerun
        && $applicationAfterRerun !== null
        && $applicationAfterRerun['request_text'] === $applicationRequestText
        && $applicationAfterRerun['tracking_code'] === $createdTrackingCode
);

phase2bAs($admin);
$invalidApplicationId = phase2bRequest('GET', '/admin/pengajuan/not-a-number');
$invalidStatusType = phase2bRequest('POST', '/admin/pengajuan/' . (int) ($createdApplication['id'] ?? 0) . '/status', [
    '_csrf' => Csrf::token(),
    'status' => ['DIPROSES'],
]);
$validTransitionFirst = phase2bRequest('POST', '/admin/pengajuan/' . (int) ($createdApplication['id'] ?? 0) . '/status', [
    '_csrf' => Csrf::token(),
    'status' => 'DIPROSES',
]);
$validTransitionComplete = phase2bRequest('POST', '/admin/pengajuan/' . (int) ($createdApplication['id'] ?? 0) . '/status', [
    '_csrf' => Csrf::token(),
    'status' => 'SELESAI',
]);
check(
    'ID non-numerik ditolak dan transisi DIAJUKAN ke DIPROSES ke SELESAI berhasil',
    $invalidApplicationId['status'] === 404
        && $validTransitionFirst['status'] === 200
        && $validTransitionComplete['status'] === 200,
    'badId=' . $invalidApplicationId['status']
        . '; first=' . $validTransitionFirst['status']
        . '; complete=' . $validTransitionComplete['status']
        . '; application=' . json_encode(Db::one('SELECT status FROM applications WHERE id = ?', [(int) ($createdApplication['id'] ?? 0)], 'test'))
);
check(
    'Input status non-string ditolak tanpa peringatan konversi PHP',
    $invalidStatusType['status'] === 404
        && !str_contains($invalidStatusType['body'], 'Array to string conversion')
);

$statusAuditRows = Db::all(
    'SELECT actor_id, action, entity_type, entity_id, message FROM audit_logs WHERE action = ? AND entity_id = ? ORDER BY id',
    ['application_status_changed', (int) ($createdApplication['id'] ?? 0)],
    'test'
);
$statusAuditValues = array_map(
    static fn(array $row): ?array => json_decode((string) $row['message'], true),
    $statusAuditRows
);
$applicationHistoryRows = Db::all(
    'SELECT old_status, new_status, changed_by FROM application_history WHERE application_id = ? ORDER BY id',
    [(int) ($createdApplication['id'] ?? 0)],
    'test'
);
check(
    'Riwayat dan audit mencatat setiap perubahan status tanpa isi permintaan',
    count($statusAuditRows) === 2
        && count($applicationHistoryRows) === 3
        && $statusAuditRows[0]['actor_id'] == $admin['id']
        && $statusAuditRows[0]['entity_type'] === 'applications'
        && ($statusAuditValues[0]['old']['status'] ?? null) === 'DIAJUKAN'
        && ($statusAuditValues[0]['new']['status'] ?? null) === 'DIPROSES'
        && ($statusAuditValues[1]['old']['status'] ?? null) === 'DIPROSES'
        && ($statusAuditValues[1]['new']['status'] ?? null) === 'SELESAI'
        && !str_contains((string) $statusAuditRows[0]['message'], $applicationRequestText)
        && $applicationHistoryRows[1]['old_status'] === 'DIAJUKAN'
        && $applicationHistoryRows[1]['new_status'] === 'DIPROSES'
        && $applicationHistoryRows[2]['old_status'] === 'DIPROSES'
        && $applicationHistoryRows[2]['new_status'] === 'SELESAI'
);

$rejectedApplicationResults = [];
$lastTransitionIsAudited = static function (int $applicationId, string $oldStatus, string $newStatus) use ($admin): bool {
    $audit = Db::one(
        'SELECT actor_id, message FROM audit_logs WHERE action = ? AND entity_id = ? ORDER BY id DESC LIMIT 1',
        ['application_status_changed', $applicationId],
        'test'
    );
    $auditValues = $audit === null ? null : json_decode((string) $audit['message'], true);
    $history = Db::one(
        'SELECT old_status, new_status, changed_by FROM application_history WHERE application_id = ? ORDER BY id DESC LIMIT 1',
        [$applicationId],
        'test'
    );
    return $audit !== null
        && (int) $audit['actor_id'] === (int) $admin['id']
        && ($auditValues['old']['status'] ?? null) === $oldStatus
        && ($auditValues['new']['status'] ?? null) === $newStatus
        && $history !== null
        && $history['old_status'] === $oldStatus
        && $history['new_status'] === $newStatus
        && (int) $history['changed_by'] === (int) $admin['id'];
};
foreach (['DITOLAK', 'DIPROSES'] as $initialTarget) {
    phase2bAs($siswa);
    $rejectedCreateResponse = phase2bRequest('POST', '/layanan/TU-01/ajukan', [
        '_csrf' => Csrf::token(),
        'request_text' => 'Permintaan untuk uji transisi',
    ]);
    $rejectedSuccessResponse = phase2bRequest('GET', '/pengajuan/diterima');
    $rejectedCodeMatch = [];
    preg_match('/<code>([^<]+)<\/code>/', $rejectedSuccessResponse['body'], $rejectedCodeMatch);
    $rejectedCode = html_entity_decode((string) ($rejectedCodeMatch[1] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $rejectedFixture = Db::one('SELECT id FROM applications WHERE tracking_code = ?', [$rejectedCode], 'test');
    if ($rejectedFixture === null) {
        $rejectedApplicationResults[] = false;
        continue;
    }
    $rejectedId = (int) $rejectedFixture['id'];
    phase2bAs($admin);
    $rejectedApplicationResults[] = $rejectedCreateResponse['status'] === 303
        && $rejectedSuccessResponse['status'] === 200;
    if ($initialTarget === 'DIPROSES') {
        $processResponse = phase2bRequest('POST', '/admin/pengajuan/' . $rejectedId . '/status', [
            '_csrf' => Csrf::token(),
            'status' => 'DIPROSES',
        ]);
        $rejectedApplicationResults[] = $processResponse['status'] === 200
            && $lastTransitionIsAudited($rejectedId, 'DIAJUKAN', 'DIPROSES');
    }
    $rejectResponse = phase2bRequest('POST', '/admin/pengajuan/' . $rejectedId . '/status', [
        '_csrf' => Csrf::token(),
        'status' => 'DITOLAK',
    ]);
    $rejectedApplicationResults[] = $rejectResponse['status'] === 200
        && $lastTransitionIsAudited($rejectedId, $initialTarget === 'DIPROSES' ? 'DIPROSES' : 'DIAJUKAN', 'DITOLAK');
}
check(
    'Transisi sah ke DITOLAK berhasil dan setiap transisi memiliki riwayat serta audit',
    $rejectedApplicationResults !== [] && !in_array(false, $rejectedApplicationResults, true),
    json_encode($rejectedApplicationResults)
);

$invalidTransitionResults = [];
$allStatuses = array_keys(ApplicationWorkflow::STATUS_LABELS);
phase2bAs($admin);
foreach ($allStatuses as $fromStatus) {
    $fixtureCode = TrackingCode::generate();
    Db::run(
        'INSERT INTO applications (service_id, applicant_user_id, tracking_code, request_text, status) VALUES ((SELECT id FROM services WHERE code = ?), ?, ?, ?, ?)',
        ['TU-01', (int) $siswa['id'], $fixtureCode, 'Fixture status transition', $fromStatus],
        'test'
    );
    $fixtureApplicationId = (int) Db::one(
        'SELECT id FROM applications WHERE tracking_code = ?',
        [$fixtureCode],
        'test'
    )['id'];
    Db::run(
        'INSERT INTO application_history (application_id, old_status, new_status, changed_by) VALUES (?, NULL, ?, ?)',
        [$fixtureApplicationId, $fromStatus, (int) $siswa['id']],
        'test'
    );
    foreach ($allStatuses as $toStatus) {
        if (ApplicationWorkflow::canTransition($fromStatus, $toStatus)) {
            continue;
        }
        $historyBefore = (int) Db::one('SELECT COUNT(*) AS n FROM application_history WHERE application_id = ?', [$fixtureApplicationId], 'test')['n'];
        $auditBefore = (int) Db::one('SELECT COUNT(*) AS n FROM audit_logs WHERE action = ? AND entity_id = ?', ['application_status_changed', $fixtureApplicationId], 'test')['n'];
        $invalidTransitionResponse = phase2bRequest('POST', '/admin/pengajuan/' . $fixtureApplicationId . '/status', [
            '_csrf' => Csrf::token(),
            'status' => $toStatus,
        ]);
        $stateAfter = Db::one('SELECT status FROM applications WHERE id = ?', [$fixtureApplicationId], 'test');
        $historyAfter = (int) Db::one('SELECT COUNT(*) AS n FROM application_history WHERE application_id = ?', [$fixtureApplicationId], 'test')['n'];
        $auditAfter = (int) Db::one('SELECT COUNT(*) AS n FROM audit_logs WHERE action = ? AND entity_id = ?', ['application_status_changed', $fixtureApplicationId], 'test')['n'];
        $invalidTransitionResults[] = $invalidTransitionResponse['status'] === 422
            && $stateAfter !== null
            && $stateAfter['status'] === $fromStatus
            && $historyAfter === $historyBefore
            && $auditAfter === $auditBefore;
    }
}
check(
    'Semua transisi status yang tidak diizinkan ditolak tanpa mengubah status, riwayat, atau audit',
    $invalidTransitionResults !== [] && !in_array(false, $invalidTransitionResults, true),
    json_encode($invalidTransitionResults)
);

$queueAccessResults = [];
foreach (['GUEST' => null, 'SISWA' => $siswa, 'GURU' => $guru, 'ADMIN' => $admin] as $role => $account) {
    phase2bAs($account);
    $queue = phase2bRequest('GET', '/admin/pengajuan');
    $detail = phase2bRequest('GET', '/admin/pengajuan/' . (int) ($createdApplication['id'] ?? 0));
    $statusChange = phase2bRequest('POST', '/admin/pengajuan/' . (int) ($createdApplication['id'] ?? 0) . '/status', [
        '_csrf' => Csrf::token(),
        'status' => 'INVALID',
    ]);
    if ($role === 'ADMIN') {
        $queueAccessResults[$role] = $queue['status'] === 200
            && $detail['status'] === 200
            && $statusChange['status'] === 404;
    } else {
        $denied = $role === 'GUEST'
            ? static fn (int $status): bool => in_array($status, [302, 403], true)
            : static fn (int $status): bool => $status === 403;
        $queueAccessResults[$role] = $denied($queue['status'])
            && $denied($detail['status'])
            && $denied($statusChange['status']);
    }
}
check(
    'Matriks antrean, detail, dan perubahan status mencakup GUEST, SISWA, GURU, dan ADMIN',
    $queueAccessResults === ['GUEST' => true, 'SISWA' => true, 'GURU' => true, 'ADMIN' => true]
);

phase2bAs($guru);
$validGuruApplication = phase2bRequest('POST', '/layanan/TU-02/ajukan', [
    '_csrf' => Csrf::token(),
    'request_text' => 'Pengajuan dari guru',
]);
check('Guru dengan sesi aktif dapat mengajukan layanan FORMULIR', $validGuruApplication['status'] === 303);

phase2bAs($admin);
$unknownAdminEdit = phase2bRequest('GET', '/admin/layanan/NO-99/ubah');
$unknownAdminUpdate = phase2bRequest('POST', '/admin/layanan/NO-99', [
    '_csrf' => Csrf::token(),
    'mode' => 'FORMULIR',
    'fee_text' => '',
    'sla_text' => '',
    'responsible_unit' => '',
    'required_documents_text' => '',
]);
check('Kode layanan admin tidak dikenal menghasilkan 404', $unknownAdminEdit['status'] === 404 && $unknownAdminUpdate['status'] === 404);

$invalidServiceMode = phase2bRequest('POST', '/admin/layanan/TU-01', [
    '_csrf' => Csrf::token(),
    'mode' => 'TIDAK_VALID',
    'fee_text' => '',
    'sla_text' => '',
    'responsible_unit' => '',
    'required_documents_text' => '',
]);
$invalidComplaintMode = phase2bRequest('POST', '/admin/layanan/HUM-02', [
    '_csrf' => Csrf::token(),
    'mode' => 'FORMULIR',
    'fee_text' => '',
    'sla_text' => '',
    'responsible_unit' => '',
    'required_documents_text' => '',
]);
$complaintModeForOtherService = phase2bRequest('POST', '/admin/layanan/TU-01', [
    '_csrf' => Csrf::token(),
    'mode' => 'MODUL_PENGADUAN',
    'fee_text' => '',
    'sla_text' => '',
    'responsible_unit' => '',
    'required_documents_text' => '',
]);
$oversizedStandard = phase2bRequest('POST', '/admin/layanan/TU-01', [
    '_csrf' => Csrf::token(),
    'mode' => 'FORMULIR',
    'fee_text' => str_repeat('x', 501),
    'sla_text' => '',
    'responsible_unit' => '',
    'required_documents_text' => '',
]);
check(
    'Mode ENUM, aturan khusus HUM-02, dan panjang teks standar divalidasi',
    $invalidServiceMode['status'] === 422
        && $invalidComplaintMode['status'] === 422
        && $complaintModeForOtherService['status'] === 422
        && $oversizedStandard['status'] === 422
);

$xssStandard = '<script>alert("xss")</script> "kutip"';
$sqlStandard = "' OR 1=1 --";
$serviceUpdateResponse = phase2bRequest('POST', '/admin/layanan/TU-01', [
    '_csrf' => Csrf::token(),
    'mode' => 'INFORMASI',
    'fee_text' => '  ' . $xssStandard . '  ',
    'sla_text' => '   ',
    'responsible_unit' => $sqlStandard,
    'required_documents_text' => '<img src=x onerror="alert(1)"> dokumen',
]);
$updatedService = Db::one(
    'SELECT s.id, s.mode, ss.fee_text, ss.sla_text, ss.responsible_unit, ss.required_documents_text, ss.updated_by FROM services s INNER JOIN service_standards ss ON ss.service_id = s.id WHERE s.code = ?',
    ['TU-01'],
    'test'
);
$serviceAudit = Db::one(
    'SELECT actor_id, action, entity_type, entity_id, message FROM audit_logs WHERE action = ? AND entity_type = ? AND entity_id = ? ORDER BY id DESC LIMIT 1',
    ['update_service', 'services', (int) ($updatedService['id'] ?? 0)],
    'test'
);
$serviceAuditValues = $serviceAudit === null ? null : json_decode((string) $serviceAudit['message'], true);
check(
    'Admin menyimpan mode dan standar layanan; teks dipangkas dan kosong menjadi NULL',
    $serviceUpdateResponse['status'] === 200
        && $updatedService !== null
        && $updatedService['mode'] === 'INFORMASI'
        && $updatedService['fee_text'] === $xssStandard
        && $updatedService['sla_text'] === null
        && $updatedService['responsible_unit'] === $sqlStandard
        && $updatedService['required_documents_text'] === '<img src=x onerror="alert(1)"> dokumen'
        && (int) $updatedService['updated_by'] === $admin['id'],
    'status=' . $serviceUpdateResponse['status']
        . '; mode=' . ($updatedService['mode'] ?? 'null')
        . '; slaNull=' . (($updatedService['sla_text'] ?? null) === null ? 'yes' : 'no')
        . '; actorMatches=' . ((int) ($updatedService['updated_by'] ?? 0) === $admin['id'] ? 'yes' : 'no')
);
check(
    'Audit perubahan layanan menyimpan aktor, entitas, nilai lama dan baru tanpa data autentikasi',
    $serviceAudit !== null
        && (int) $serviceAudit['actor_id'] === $admin['id']
        && $serviceAudit['entity_type'] === 'services'
        && (int) $serviceAudit['entity_id'] === (int) $updatedService['id']
        && ($serviceAuditValues['old']['mode'] ?? null) === 'FORMULIR'
        && ($serviceAuditValues['new']['mode'] ?? null) === 'INFORMASI'
        && ($serviceAuditValues['new']['responsible_unit'] ?? null) === $sqlStandard
        && ($serviceAuditValues['new']['required_documents_text'] ?? null) === '<img src=x onerror="alert(1)"> dokumen'
        && !preg_match('/\$2y\$[0-9]{2}\$/', (string) $serviceAudit['message'])
);
$updatedPublicDetail = phase2bRequest('GET', '/layanan/TU-01');
$updatedAdminEdit = phase2bRequest('GET', '/admin/layanan/TU-01/ubah');
$updatedAdminIndex = phase2bRequest('GET', '/admin/layanan');
check(
    'Nilai standar layanan di-escape pada detail publik dan halaman admin',
    !str_contains($updatedPublicDetail['body'], '<script>')
        && !str_contains($updatedPublicDetail['body'], '<img src=x')
        && !str_contains($updatedAdminEdit['body'], '<script>')
        && !str_contains($updatedAdminEdit['body'], '<img src=x')
        && !str_contains($updatedAdminIndex['body'], '<script>')
);
check(
    'Layanan INFORMASI tidak menawarkan pengajuan; formulir meminta login pada pengguna publik',
    !str_contains($updatedPublicDetail['body'], '>Ajukan<')
        && str_contains($updatedPublicDetail['body'], ServiceCatalog::INFORMATION_STEPS_NOTICE)
        && !str_contains($formCatalogDetailResponse['body'], 'Pengajuan melalui website menyusul pada Langkah 4.')
        && str_contains($formCatalogDetailResponse['body'], 'Login sebagai SISWA atau GURU untuk mengajukan layanan.')
        && str_contains($updatedAdminIndex['body'], 'Informasi langsung di website')
        && str_contains($updatedAdminIndex['body'], '>3 / 4<')
);
check(
    'SQL pada teks standar disimpan sebagai teks literal',
    $updatedService !== null && $updatedService['responsible_unit'] === $sqlStandard
);

$maximumStandard = str_repeat('é🙂', 250);
$maximumDocuments = str_repeat('é🙂', 2000);
$maximumStandards = [
    'fee_text' => $maximumStandard,
    'sla_text' => $maximumStandard,
    'responsible_unit' => $maximumStandard,
    'required_documents_text' => $maximumDocuments,
];
$maximumStandardsResponse = phase2bRequest('POST', '/admin/layanan/TU-01', [
    '_csrf' => Csrf::token(),
    'mode' => 'INFORMASI',
] + $maximumStandards);
$maximumStandardsService = Db::one(
    'SELECT s.id, s.mode, ss.fee_text, ss.sla_text, ss.responsible_unit, ss.required_documents_text FROM services s INNER JOIN service_standards ss ON ss.service_id = s.id WHERE s.code = ?',
    ['TU-01'],
    'test'
);
$maximumStandardsAudit = Db::one(
    'SELECT message FROM audit_logs WHERE action = ? AND entity_type = ? AND entity_id = ? ORDER BY id DESC LIMIT 1',
    ['update_service', 'services', (int) ($maximumStandardsService['id'] ?? 0)],
    'test'
);
$maximumStandardsAuditValues = $maximumStandardsAudit === null
    ? null
    : json_decode((string) $maximumStandardsAudit['message'], true);
$auditMessageColumn = Db::one(
    'SELECT DATA_TYPE AS data_type, CHARACTER_MAXIMUM_LENGTH AS max_length FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
    ['audit_logs', 'message'],
    'test'
);
check(
    'Standar maksimum UTF-8 berhasil disimpan dan tercatat utuh dalam kolom audit',
    $maximumStandardsResponse['status'] === 200
        && $auditMessageColumn !== null
        && $auditMessageColumn['data_type'] === 'text'
        && (int) $auditMessageColumn['max_length'] === 65535
        && $maximumStandardsService !== null
        && $maximumStandardsService['mode'] === 'INFORMASI'
        && $maximumStandardsService['fee_text'] === $maximumStandard
        && $maximumStandardsService['sla_text'] === $maximumStandard
        && $maximumStandardsService['responsible_unit'] === $maximumStandard
        && $maximumStandardsService['required_documents_text'] === $maximumDocuments
        && $maximumStandardsAudit !== null
        && is_array($maximumStandardsAuditValues)
        && ($maximumStandardsAuditValues['new']['mode'] ?? null) === 'INFORMASI'
        && ($maximumStandardsAuditValues['new']['fee_text'] ?? null) === $maximumStandard
        && ($maximumStandardsAuditValues['new']['sla_text'] ?? null) === $maximumStandard
        && ($maximumStandardsAuditValues['new']['responsible_unit'] ?? null) === $maximumStandard
        && ($maximumStandardsAuditValues['new']['required_documents_text'] ?? null) === $maximumDocuments
        && strlen((string) $maximumStandardsAudit['message']) < (int) $auditMessageColumn['max_length']
);

$maximumStandardB = str_repeat('🙂é', 250);
$maximumDocumentsB = str_repeat('🙂é', 2000);
$maximumStandardsB = [
    'fee_text' => $maximumStandardB,
    'sla_text' => $maximumStandardB,
    'responsible_unit' => $maximumStandardB,
    'required_documents_text' => $maximumDocumentsB,
];
$maximumStandardsBResponse = phase2bRequest('POST', '/admin/layanan/TU-01', [
    '_csrf' => Csrf::token(),
    'mode' => 'INFORMASI',
] + $maximumStandardsB);
$maximumStandardsBAudit = Db::one(
    'SELECT message FROM audit_logs WHERE action = ? AND entity_type = ? AND entity_id = ? ORDER BY id DESC LIMIT 1',
    ['update_service', 'services', (int) ($maximumStandardsService['id'] ?? 0)],
    'test'
);
$maximumStandardsBAuditValues = $maximumStandardsBAudit === null
    ? null
    : json_decode((string) $maximumStandardsBAudit['message'], true);
$maximumStandardsBAuditBytes = strlen((string) ($maximumStandardsBAudit['message'] ?? ''));
check(
    'Audit dua kondisi maksimum UTF-8 menyimpan nilai lama dan baru utuh (' . $maximumStandardsBAuditBytes . ' byte)',
    $maximumStandardsBResponse['status'] === 200
        && $maximumStandardsBAudit !== null
        && is_array($maximumStandardsBAuditValues)
        && ($maximumStandardsBAuditValues['old']['fee_text'] ?? null) === $maximumStandard
        && ($maximumStandardsBAuditValues['old']['sla_text'] ?? null) === $maximumStandard
        && ($maximumStandardsBAuditValues['old']['responsible_unit'] ?? null) === $maximumStandard
        && ($maximumStandardsBAuditValues['old']['required_documents_text'] ?? null) === $maximumDocuments
        && ($maximumStandardsBAuditValues['new']['fee_text'] ?? null) === $maximumStandardB
        && ($maximumStandardsBAuditValues['new']['sla_text'] ?? null) === $maximumStandardB
        && ($maximumStandardsBAuditValues['new']['responsible_unit'] ?? null) === $maximumStandardB
        && ($maximumStandardsBAuditValues['new']['required_documents_text'] ?? null) === $maximumDocumentsB
        && $maximumStandardsBAuditBytes < (int) $auditMessageColumn['max_length']
);

foreach ($catalogStatements as $catalogStatement) {
    $catalogPdo->exec($catalogStatement);
}
$serviceAfterCatalogRerun = Db::one(
    'SELECT s.mode, ss.fee_text, ss.sla_text, ss.responsible_unit, ss.required_documents_text FROM services s INNER JOIN service_standards ss ON ss.service_id = s.id WHERE s.code = ?',
    ['TU-01'],
    'test'
);
check(
    'Migrasi katalog ulang mempertahankan mode dan keempat standar yang disimpan admin',
    $serviceAfterCatalogRerun !== null
        && $serviceAfterCatalogRerun['mode'] === 'INFORMASI'
        && $serviceAfterCatalogRerun['fee_text'] === $maximumStandardB
        && $serviceAfterCatalogRerun['sla_text'] === $maximumStandardB
        && $serviceAfterCatalogRerun['responsible_unit'] === $maximumStandardB
        && $serviceAfterCatalogRerun['required_documents_text'] === $maximumDocumentsB
);

phase2bAs($admin);
$createResponse = phase2bRequest('POST', '/admin/pengguna', [
    '_csrf' => Csrf::token(),
    'identifier' => 'p2b-created-' . $suffix,
    'full_name' => 'Pengguna Buatan',
    'role' => 'SISWA',
    'password' => 'secret-created-' . $suffix,
]);
$createdUser = Db::one('SELECT id FROM users WHERE identifier = ?', ['p2b-created-' . $suffix], 'test');
check('POST buat pengguna valid berhasil dan tersimpan', $createResponse['status'] === 200 && $createdUser !== null);

phase2bAs($admin);
$invalidRoleResponse = phase2bRequest('POST', '/admin/pengguna', [
    '_csrf' => Csrf::token(),
    'identifier' => 'p2b-invalid-role-' . $suffix,
    'full_name' => 'Role Tidak Valid',
    'role' => 'GUEST',
    'password' => 'secret-created-' . $suffix,
]);
$shortPasswordResponse = phase2bRequest('POST', '/admin/pengguna', [
    '_csrf' => Csrf::token(),
    'identifier' => 'p2b-short-' . $suffix,
    'full_name' => 'Sandi Pendek',
    'role' => 'SISWA',
    'password' => 'short',
]);
check('Pembuatan menolak role tidak valid dan sandi kurang dari 10 karakter', $invalidRoleResponse['status'] === 422 && $shortPasswordResponse['status'] === 422);

phase2bAs($admin);
$duplicateResponse = phase2bRequest('POST', '/admin/pengguna', [
    '_csrf' => Csrf::token(),
    'identifier' => $siswa['identifier'],
    'full_name' => 'Duplikat',
    'role' => 'SISWA',
    'password' => 'secret-duplicate-' . $suffix,
]);
check('Pembuatan menolak username ganda pada role sama', $duplicateResponse['status'] === 422);

$selfRoleResponse = phase2bRequest('POST', '/admin/pengguna/' . $admin['id'] . '/role', [
    '_csrf' => Csrf::token(),
    'role' => 'SISWA',
]);
$selfDeactivateResponse = phase2bRequest('POST', '/admin/pengguna/' . $admin['id'] . '/nonaktif', ['_csrf' => Csrf::token()]);
$invalidRoleUpdate = phase2bRequest('POST', '/admin/pengguna/' . $siswa['id'] . '/role', ['_csrf' => Csrf::token(), 'role' => 'GUEST']);
$shortResetResponse = phase2bRequest('POST', '/admin/pengguna/' . $guru['id'] . '/reset-sandi', ['_csrf' => Csrf::token(), 'password' => 'short']);
check('Admin tidak dapat mengubah dirinya sendiri; role dan sandi reset divalidasi', $selfRoleResponse['status'] === 422 && $selfDeactivateResponse['status'] === 422 && $invalidRoleUpdate['status'] === 422 && $shortResetResponse['status'] === 422);

$badIdRole = phase2bRequest('POST', '/admin/pengguna/abc/role', ['_csrf' => Csrf::token(), 'role' => 'GURU']);
$missingIdRole = phase2bRequest('POST', '/admin/pengguna/999999999/role', ['_csrf' => Csrf::token(), 'role' => 'GURU']);
$badIdDeactivate = phase2bRequest('POST', '/admin/pengguna/nope/nonaktif', ['_csrf' => Csrf::token()]);
$missingIdDeactivate = phase2bRequest('POST', '/admin/pengguna/999999999/nonaktif', ['_csrf' => Csrf::token()]);
$badIdReset = phase2bRequest('POST', '/admin/pengguna/not-a-number/reset-sandi', ['_csrf' => Csrf::token(), 'password' => 'secret-missing-' . $suffix]);
$missingIdReset = phase2bRequest('POST', '/admin/pengguna/999999999/reset-sandi', ['_csrf' => Csrf::token(), 'password' => 'secret-missing-' . $suffix]);
check('ID non-numerik dan pengguna tidak ada ditolak untuk semua operasi berbasis ID', $badIdRole['status'] === 422 && $missingIdRole['status'] === 404 && $badIdDeactivate['status'] === 422 && $missingIdDeactivate['status'] === 404 && $badIdReset['status'] === 422 && $missingIdReset['status'] === 404);

$targetRoleResponse = phase2bRequest('POST', '/admin/pengguna/' . $siswa['id'] . '/role', ['_csrf' => Csrf::token(), 'role' => 'GURU']);
$targetDeactivateResponse = phase2bRequest('POST', '/admin/pengguna/' . $adminTarget['id'] . '/nonaktif', ['_csrf' => Csrf::token()]);
$targetResetResponse = phase2bRequest('POST', '/admin/pengguna/' . $guru['id'] . '/reset-sandi', ['_csrf' => Csrf::token(), 'password' => 'secret-reset-valid-' . $suffix]);
check('Ubah role, nonaktifkan pengguna lain, dan reset sandi valid', $targetRoleResponse['status'] === 200 && $targetDeactivateResponse['status'] === 200 && $targetResetResponse['status'] === 200);

Db::run(
    'UPDATE users SET is_active = 0 WHERE role_id = (SELECT id FROM roles WHERE code = ?) AND id NOT IN (?, ?)',
    ['ADMIN', $admin['id'], $adminTarget['id']],
    'test'
);
Db::run('UPDATE users SET is_active = 1 WHERE id = ?', [$adminTarget['id']], 'test');
$adminCountBeforeLastGuard = (int) Db::one(
    'SELECT COUNT(*) AS n FROM users u INNER JOIN roles r ON r.id = u.role_id WHERE r.code = ? AND u.is_active = 1',
    ['ADMIN'],
    'test'
)['n'];
$lastAdminDeactivateResponse = phase2bRequest('POST', '/admin/pengguna/' . $adminTarget['id'] . '/nonaktif', ['_csrf' => Csrf::token()]);
$adminCountAfterDeactivate = (int) Db::one(
    'SELECT COUNT(*) AS n FROM users u INNER JOIN roles r ON r.id = u.role_id WHERE r.code = ? AND u.is_active = 1',
    ['ADMIN'],
    'test'
)['n'];
Db::run('UPDATE users SET is_active = 1 WHERE id = ?', [$adminTarget['id']], 'test');
$lastAdminDemoteResponse = phase2bRequest('POST', '/admin/pengguna/' . $adminTarget['id'] . '/role', ['_csrf' => Csrf::token(), 'role' => 'SISWA']);
$adminCountAfterDemote = (int) Db::one(
    'SELECT COUNT(*) AS n FROM users u INNER JOIN roles r ON r.id = u.role_id WHERE r.code = ? AND u.is_active = 1',
    ['ADMIN'],
    'test'
)['n'];
check(
    'Invariant admin aktif tetap terpenuhi setelah nonaktif dan perubahan role',
    $adminCountBeforeLastGuard === 2
        && $lastAdminDeactivateResponse['status'] === 200
        && $adminCountAfterDeactivate === 1
        && $lastAdminDemoteResponse['status'] === 200
        && $adminCountAfterDemote === 1
);
Db::run(
    'UPDATE users SET role_id = (SELECT id FROM roles WHERE code = ?), is_active = 0 WHERE id = ?',
    ['ADMIN', $adminTarget['id']],
    'test'
);
$lastAdminSelfRoleResponse = phase2bRequest('POST', '/admin/pengguna/' . $admin['id'] . '/role', ['_csrf' => Csrf::token(), 'role' => 'SISWA']);
$lastAdminSelfDeactivateResponse = phase2bRequest('POST', '/admin/pengguna/' . $admin['id'] . '/nonaktif', ['_csrf' => Csrf::token()]);
$activeAdminAfterGuard = (int) Db::one(
    'SELECT COUNT(*) AS n FROM users u INNER JOIN roles r ON r.id = u.role_id WHERE r.code = ? AND u.is_active = 1',
    ['ADMIN'],
    'test'
)['n'];
check(
    'Guard transaksi menolak perubahan yang menghapus admin aktif terakhir',
    $lastAdminSelfRoleResponse['status'] === 422
        && $lastAdminSelfDeactivateResponse['status'] === 422
        && str_contains($lastAdminSelfRoleResponse['body'], 'minimal satu admin aktif')
        && str_contains($lastAdminSelfDeactivateResponse['body'], 'minimal satu admin aktif')
        && $activeAdminAfterGuard === 1
);

$sameIdentifier = 'p2b-shared-' . $suffix;
$sameStudent = phase2bUser('SISWA', $sameIdentifier, 'Akun Siswa Bersama', 'secret-shared-siswa-' . $suffix);
$sameAdmin = phase2bUser('ADMIN', $sameIdentifier, 'Akun Admin Bersama', 'secret-shared-admin-' . $suffix);
phase2bUser('GURU', $sameIdentifier, 'Akun Guru Bersama', 'secret-shared-guru-' . $suffix);
$wrongRoleFound = Auth::findUserByIdentifier($sameIdentifier, 'SISWA_GURU', 'test');
$adminRoleFound = Auth::findUserByIdentifier($sameIdentifier, 'ADMIN', 'test');
$invalidRoleFound = Auth::findUserByIdentifier($sameIdentifier, 'INVALID', 'test');
$emptyRoleFound = Auth::findUserByIdentifier($sameIdentifier, '', 'test');
check('Pilihan login cocok dengan SISWA/GURU, membatasi ADMIN, dan role kosong/tidak valid tidak mencari', in_array($wrongRoleFound['role_code'] ?? '', ['SISWA', 'GURU'], true) && ($adminRoleFound['role_code'] ?? '') === 'ADMIN' && $invalidRoleFound === null && $emptyRoleFound === null);

$genericMessages = [];
foreach ([
    ['missing-' . $suffix, 'secret-any-' . $suffix, 'ADMIN'],
    [$admin['identifier'], 'wrong-secret-' . $suffix, 'ADMIN'],
    [$admin['identifier'], $admin['password'], 'SISWA_GURU'],
    [$inactive['identifier'], $inactive['password'], 'SISWA_GURU'],
] as [$identifier, $password, $role]) {
    $_SERVER['REMOTE_ADDR'] = '192.0.2.' . random_int(10, 240);
    $genericMessages[] = Auth::processLogin($identifier, $password, $role)['message'];
}
$lockedFixture = phase2bUser('SISWA', 'p2b-locked-' . $suffix, 'Akun Terkunci', 'secret-locked-' . $suffix);
$_SERVER['REMOTE_ADDR'] = '192.0.2.241';
for ($i = 0; $i < 5; $i++) {
    Auth::processLogin($lockedFixture['identifier'], 'wrong-secret-' . $suffix, 'SISWA_GURU');
}
$genericMessages[] = Auth::processLogin($lockedFixture['identifier'], $lockedFixture['password'], 'SISWA_GURU')['message'];
check('Pesan gagal identik untuk akun hilang, sandi/role salah, nonaktif, dan terkunci', count(array_unique($genericMessages)) === 1);
$sameIpUser = phase2bUser('SISWA', 'p2b-same-ip-' . $suffix, 'Akun dari IP Sama', 'secret-same-ip-' . $suffix);
$sameIpLogin = Auth::processLogin($sameIpUser['identifier'], $sameIpUser['password'], 'SISWA_GURU');
check(
    'Lima kegagalan mengunci akun saja, bukan akun lain dari IP yang sama',
    Auth::isLockedOut($lockedFixture['identifier'], '192.0.2.241', 'test')
        && !Auth::isLockedOut('p2b-unrelated-' . $suffix, '192.0.2.241', 'test')
        && $sameIpLogin['ok'] === true
);

$ipPolicyUser = phase2bUser('SISWA', 'p2b-ip-policy-' . $suffix, 'Akun Uji Kebijakan IP', 'secret-ip-policy-' . $suffix);
$policyIp = '198.51.100.201';
$_SERVER['REMOTE_ADDR'] = $policyIp;
$_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.114.210';
$_SERVER['HTTP_CF_CONNECTING_IP'] = '203.0.114.211';
for ($i = 1; $i <= 29; $i++) {
    Auth::processLogin('p2b-ip-failure-' . $i . '-' . $suffix, 'wrong-secret-' . $suffix, 'SISWA_GURU');
}
$failuresAt29 = (int) Db::one(
    'SELECT COUNT(*) AS n FROM login_attempts WHERE ip_address = ? AND attempt_type = "FAILURE"',
    [$policyIp],
    'test'
)['n'];
$ipLockedAt29 = Auth::isLockedOut('p2b-unrelated-' . $suffix, $policyIp, 'test');
Auth::processLogin('p2b-ip-failure-30-' . $suffix, 'wrong-secret-' . $suffix, 'SISWA_GURU');
$ipLockedAt30 = Auth::isLockedOut('p2b-unrelated-' . $suffix, $policyIp, 'test');
$ipLockRows = (int) Db::one(
    'SELECT COUNT(*) AS n FROM login_attempts WHERE ip_address = ? AND user_id IS NULL AND attempt_type = "LOCKED"',
    [$policyIp],
    'test'
)['n'];
$fakeHeaderAttempts = (int) Db::one(
    'SELECT COUNT(*) AS n FROM login_attempts WHERE ip_address IN (?, ?)',
    [$_SERVER['HTTP_X_FORWARDED_FOR'], $_SERVER['HTTP_CF_CONNECTING_IP']],
    'test'
)['n'];
$ipBlockedValidLogin = Auth::processLogin($ipPolicyUser['identifier'], $ipPolicyUser['password'], 'SISWA_GURU');
$ipBlockedLogin = Auth::processLogin($ipPolicyUser['identifier'], 'wrong-secret-' . $suffix, 'SISWA_GURU');
$ipLockRowsAfterRepeat = (int) Db::one(
    'SELECT COUNT(*) AS n FROM login_attempts WHERE ip_address = ? AND user_id IS NULL AND attempt_type = "LOCKED"',
    [$policyIp],
    'test'
)['n'];
$ipLockTimeBeforeExpiry = Db::one(
    'SELECT MAX(created_at) AS t FROM login_attempts WHERE ip_address = ? AND user_id IS NULL AND attempt_type = "LOCKED"',
    [$policyIp],
    'test'
)['t'];
check(
    'Ambang IP: 29 gagal belum mengunci, kegagalan ke-30 mengunci setelah gabungan semua akun',
    $failuresAt29 === 29 && !$ipLockedAt29 && $ipLockedAt30 && $ipLockRows === 1
);
check(
    'Lockout IP menolak login valid dengan pesan seragam; header proxy tidak mempengaruhi hitungan',
    $ipBlockedValidLogin['ok'] === false
        && $ipBlockedValidLogin['message'] === $genericMessages[0]
        && $ipBlockedLogin['message'] === $genericMessages[0]
        && $fakeHeaderAttempts === 0
);
check(
    'Percobaan saat IP terkunci tidak menambah atau memperpanjang LOCKED',
    $ipLockRowsAfterRepeat === 1
        && $ipLockTimeBeforeExpiry === Db::one(
            'SELECT MAX(created_at) AS t FROM login_attempts WHERE ip_address = ? AND user_id IS NULL AND attempt_type = "LOCKED"',
            [$policyIp],
            'test'
        )['t']
);
Db::run(
    'UPDATE login_attempts SET created_at = DATE_SUB(NOW(), INTERVAL 16 MINUTE) WHERE ip_address = ? AND user_id IS NULL AND attempt_type = "LOCKED"',
    [$policyIp],
    'test'
);
$_SERVER['REMOTE_ADDR'] = '198.51.100.202';
$loginFromDifferentIp = Auth::processLogin($ipPolicyUser['identifier'], $ipPolicyUser['password'], 'SISWA_GURU');
$_SERVER['REMOTE_ADDR'] = $policyIp;
$loginAfterIpLockExpiry = Auth::processLogin($ipPolicyUser['identifier'], $ipPolicyUser['password'], 'SISWA_GURU');
check(
    'Kunci IP berakhir setelah 15 menit; IP lain dapat login sebelum masa kunci berakhir',
    $loginFromDifferentIp['ok'] === true && $loginAfterIpLockExpiry['ok'] === true
);
unset($_SERVER['HTTP_X_FORWARDED_FOR'], $_SERVER['HTTP_CF_CONNECTING_IP']);

$_SERVER['REMOTE_ADDR'] = '198.51.100.77';
$_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.10';
$_SERVER['HTTP_CF_CONNECTING_IP'] = '203.0.113.11';
$trustedIp = Auth::clientIp();
unset($_SERVER['HTTP_X_FORWARDED_FOR'], $_SERVER['HTTP_CF_CONNECTING_IP']);
$_SERVER['REMOTE_ADDR'] = '';
$emptyClientIp = Auth::clientIp();
Auth::recordAttempt('p2b-no-ip-' . $suffix, null, 'FAILURE', $emptyClientIp, null, 'test');
$noIpStored = Db::one('SELECT ip_address FROM login_attempts WHERE identifier = ?', ['p2b-no-ip-' . $suffix], 'test');
$noIpLock = phase2bUser('SISWA', 'p2b-no-ip-lock-' . $suffix, 'Tanpa IP', 'secret-no-ip-' . $suffix);
for ($i = 0; $i < 5; $i++) {
    Auth::processLogin($noIpLock['identifier'], 'wrong-secret-' . $suffix, 'SISWA_GURU');
}
$noIpHasNoSharedLock = Auth::isLockedOut('p2b-other-no-ip-' . $suffix, '', 'test') === false;
check('clientIp hanya REMOTE_ADDR; IP kosong tidak menjadi alamat bersama', $trustedIp === '198.51.100.77' && $emptyClientIp === '' && $noIpStored !== null && $noIpStored['ip_address'] === null && $noIpHasNoSharedLock);

$_SERVER['REMOTE_ADDR'] = '198.51.100.88';
$lockedRowsBefore = (int) Db::one('SELECT COUNT(*) AS n FROM login_attempts WHERE user_id = ? AND attempt_type = "LOCKED"', [$lockedFixture['id']], 'test')['n'];
$lockedTimeBefore = Db::one('SELECT MAX(created_at) AS t FROM login_attempts WHERE user_id = ? AND attempt_type = "LOCKED"', [$lockedFixture['id']], 'test')['t'];
$lockedResultWrong = Auth::processLogin($lockedFixture['identifier'], 'wrong-secret-' . $suffix, 'SISWA_GURU');
$lockedRowsAfter = (int) Db::one('SELECT COUNT(*) AS n FROM login_attempts WHERE user_id = ? AND attempt_type = "LOCKED"', [$lockedFixture['id']], 'test')['n'];
check('Percobaan terkunci berikutnya tidak menambah atau memperpanjang LOCKED', $lockedResultWrong['ok'] === false && $lockedRowsBefore === 1 && $lockedRowsAfter === 1 && $lockedTimeBefore === Db::one('SELECT MAX(created_at) AS t FROM login_attempts WHERE user_id = ? AND attempt_type = "LOCKED"', [$lockedFixture['id']], 'test')['t']);
Db::run('UPDATE login_attempts SET created_at = DATE_SUB(NOW(), INTERVAL 16 MINUTE) WHERE user_id = ? AND attempt_type = "LOCKED"', [$lockedFixture['id']], 'test');
$expiredLockResult = Auth::processLogin($lockedFixture['identifier'], $lockedFixture['password'], 'SISWA_GURU');
check('Lockout akun berakhir sesudah 15 menit', $expiredLockResult['ok'] === true);

$_SERVER['REMOTE_ADDR'] = '198.51.100.101';
$sessionSiswa = phase2bUser('SISWA', 'p2b-session-' . $suffix, 'Siswa Sesi', 'secret-session-' . $suffix);
phase2bAs(null);
$beforeId = session_id();
$rotationResult = Auth::processLogin($sessionSiswa['identifier'], $sessionSiswa['password'], 'SISWA_GURU');
$afterId = session_id();
check('Login merotasi ID sesi', $rotationResult['ok'] === true && $beforeId !== $afterId, 'login berhasil=' . ($rotationResult['ok'] ? 'ya' : 'tidak') . '; ID berganti=' . ($beforeId !== $afterId ? 'ya' : 'tidak'));
check('Durasi sesi ditetapkan 2 jam', Auth::sessionLifetimeSeconds() === 7200);
$cookieHeaders = implode("\n", phase2bSessionCookieHeaders());
check('Cookie sesi HTTP memiliki HttpOnly dan SameSite=Lax', preg_match('/set-cookie:.*httponly.*samesite=lax/i', $cookieHeaders) === 1);

$_SESSION['last_activity'] = time() - 7201;
check('Sesi kedaluwarsa setelah dua jam tanpa aktivitas', Auth::user() === null);
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

phase2bAs($admin);
$oldSiswaToken = $siswa['token'];
$deactivateForSession = phase2bRequest('POST', '/admin/pengguna/' . $siswa['id'] . '/nonaktif', ['_csrf' => Csrf::token()]);
phase2bAs($siswa);
check('Sesi lama pengguna nonaktif tidak dapat dipakai', $deactivateForSession['status'] === 200 && Auth::user() === null);
Db::run('UPDATE users SET is_active = 1 WHERE id = ?', [$siswa['id']], 'test');
$siswa['token'] = (string) Db::one('SELECT auth_token FROM users WHERE id = ?', [$siswa['id']], 'test')['auth_token'];
phase2bAs($admin);
$resetForSession = phase2bRequest('POST', '/admin/pengguna/' . $siswa['id'] . '/reset-sandi', ['_csrf' => Csrf::token(), 'password' => 'secret-reset-session-' . $suffix]);
$siswa['token'] = $oldSiswaToken;
phase2bAs($siswa);
check('Sesi lama setelah reset sandi tidak dapat dipakai', $resetForSession['status'] === 200 && Auth::user() === null);

$missingUserId = (int) Db::one('SELECT COALESCE(MAX(id), 0) + 1 AS id FROM users', [], 'test')['id'];
$missingSessionUser = [
    'id' => $missingUserId,
    'role' => 'SISWA',
    'name' => 'Pengguna Sesi Basi',
    'identifier' => 'stale-session-' . $suffix,
    'token' => bin2hex(random_bytes(32)),
];
$stalePortalResponse = null;
$stalePortalError = null;
try {
    phase2bAs($missingSessionUser);
    $stalePortalResponse = phase2bRequest('GET', '/portal');
} catch (Throwable $exception) {
    $stalePortalError = $exception::class;
}
$staleAdminResponse = null;
$staleAdminError = null;
try {
    phase2bAs($missingSessionUser);
    $staleAdminResponse = phase2bRequest('GET', '/admin');
} catch (Throwable $exception) {
    $staleAdminError = $exception::class;
}
check(
    'Sesi dengan pengguna yang tidak ada diarahkan ke login dari portal dan admin tanpa galat',
    $stalePortalResponse !== null
        && $stalePortalResponse['status'] === 302
        && $staleAdminResponse !== null
        && $staleAdminResponse['status'] === 302
        && $stalePortalError === null
        && $staleAdminError === null
);
$staleSessionAudit = Db::one(
    'SELECT actor_id FROM audit_logs WHERE action = ? AND entity_type = ? AND entity_id = ? ORDER BY id DESC LIMIT 1',
    ['logout', 'session', $missingUserId],
    'test'
);
check(
    'Logout sesi basi mencatat aktor NULL bila pengguna sudah tidak ada',
    $staleSessionAudit !== null && $staleSessionAudit['actor_id'] === null
);

phase2bAs($admin);
$logoutResponse = phase2bRequest('POST', '/logout', ['_csrf' => Csrf::token()]);
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
$_SESSION = [];
$adminAfterLogout = phase2bRequest('GET', '/admin');
check('Sesudah logout, akses /admin sebagai guest ditolak', $logoutResponse['status'] === 302 && in_array($adminAfterLogout['status'], [302, 403], true));

$xssIdentifier = 'p2b-xss-' . $suffix . '<script>alert(1)</script>"';
$xssName = 'Nama <script>alert(1)</script> "kutip"';
phase2bAs($admin);
$xssCreate = phase2bRequest('POST', '/admin/pengguna', [
    '_csrf' => Csrf::token(),
    'identifier' => $xssIdentifier,
    'full_name' => $xssName,
    'role' => 'SISWA',
    'password' => 'secret-xss-' . $suffix,
]);
$usersPage = phase2bRequest('GET', '/admin/pengguna');
Auth::audit($admin['id'], 'ADMIN', 'xss_test', 'users', 0, $xssName, 'test');
$auditPage = phase2bRequest('GET', '/admin/audit');
phase2bAs($guru);
$portalPage = phase2bRequest('GET', '/portal');
ob_start();
View::render('pages/admin_users', ['title' => 'XSS', 'users' => [], 'message' => $xssName, 'messageIsError' => true]);
$flashPage = (string) ob_get_clean();
$escapedXss = e($xssName);
check('XSS: HTML berbahaya di-escape pada halaman pengguna/audit/portal/flash/layout', $xssCreate['status'] === 200 && !str_contains($usersPage['body'], '<script>') && !str_contains($auditPage['body'], '<script>') && !str_contains($portalPage['body'], '<script>') && !str_contains($flashPage, '<script>') && str_contains($flashPage, $escapedXss));

$auditText = implode(' ', array_map(
    static fn(array $row): string => implode(' ', array_map(static fn(mixed $value): string => (string) ($value ?? ''), $row)),
    Db::all('SELECT actor_id, actor_role, action, entity_type, entity_id, message FROM audit_logs', [], 'test')
));
$auditFixture = Db::one('SELECT actor_id, action, entity_type, entity_id FROM audit_logs WHERE action = ? AND entity_id = ?', ['create_user', (int) ($createdUser['id'] ?? 0)], 'test');
$managementAuditPassed = true;
check('Audit memuat aktor, aksi, entitas dan tidak memuat hash/sandi', $auditFixture !== null && (int) $auditFixture['actor_id'] === $admin['id'] && $auditFixture['entity_type'] === 'users' && !preg_match('/\$2y\$[0-9]{2}\$/', $auditText) && !str_contains($auditText, 'secret-'));
foreach (['create_user', 'change_user_role', 'deactivate_user', 'reset_user_password'] as $managementAction) {
    $auditActor = Db::one('SELECT actor_id, entity_type FROM audit_logs WHERE action = ? ORDER BY id DESC LIMIT 1', [$managementAction], 'test');
    $managementAuditPassed = $managementAuditPassed && $auditActor !== null && (int) $auditActor['actor_id'] === $admin['id'] && $auditActor['entity_type'] === 'users';
}
check('Buat/role/nonaktif/reset masing-masing tercatat dengan aktor dan entitas pengguna', $managementAuditPassed);
check('Audit login berhasil/gagal, lockout, dan logout tercatat', Db::one('SELECT id FROM audit_logs WHERE action = ?', ['login_success'], 'test') !== null && Db::one('SELECT id FROM audit_logs WHERE action = ?', ['login_failed'], 'test') !== null && Db::one('SELECT id FROM audit_logs WHERE action = ?', ['account_locked'], 'test') !== null && Db::one('SELECT id FROM audit_logs WHERE action = ?', ['logout'], 'test') !== null);
check('Halaman audit tidak menampilkan hash password', !preg_match('/\$2y\$[0-9]{2}\$/', $auditPage['body']));

$sqlIdentifier = "' OR 1=1 --";
phase2bAs($admin);
$sqlCreate = phase2bRequest('POST', '/admin/pengguna', [
    '_csrf' => Csrf::token(),
    'identifier' => $sqlIdentifier,
    'full_name' => 'Input SQL uji',
    'role' => 'SISWA',
    'password' => 'secret-sql-' . $suffix,
]);
$sqlExact = Db::one('SELECT id FROM users WHERE role_id = (SELECT id FROM roles WHERE code = ?) AND identifier = ?', ['SISWA', $sqlIdentifier], 'test');
$sqlLogin = Auth::findUserByIdentifier("' OR 1=1 --' UNION SELECT", 'SISWA_GURU', 'test');
check('Input SQL pada pengelolaan/login diperlakukan sebagai data', $sqlCreate['status'] === 200 && $sqlExact !== null && $sqlLogin === null);

$uploadStudent = phase2bUser('SISWA', 'p2b-upload-' . $suffix, 'Penguji Lampiran', 'secret-upload-' . $suffix);
$otherApplicant = phase2bUser('GURU', 'p2b-upload-other-' . $suffix, 'Penguji Pengajuan Lain', 'secret-other-upload-' . $suffix);
$uploadServiceMode = (string) Db::one('SELECT mode FROM services WHERE code = ?', ['TU-01'], 'test')['mode'];
Db::run('UPDATE services SET mode = ? WHERE code = ?', ['FORMULIR', 'TU-01'], 'test');
$studentHttpIdentity = phase2bHttpIdentity($uploadStudent);
$otherHttpIdentity = phase2bHttpIdentity($otherApplicant);
$adminHttpIdentity = phase2bHttpIdentity($admin);
$guruHttpIdentity = phase2bHttpIdentity($guru);
$guestHttpIdentity = phase2bHttpIdentity(null);
$pngBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jZioAAAAASUVORK5CYII=', true);
$jpegBytes = base64_decode('/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////2wBDAf//////////////////////////////////////////////////////////////////////////////////////wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAb/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIQAxAAAAH/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAEFAqf/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oACAEDAQE/AV//xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oACAECAQE/AV//xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAY/Al//xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAE/IT//2Q==', true);
$gifBytes = base64_decode('R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==', true);
$uploadBasePost = ['_csrf' => $studentHttpIdentity['csrf'], 'request_text' => 'Permohonan uji lampiran'];
$studentApplicationsBeforeUpload = (int) Db::one('SELECT COUNT(*) AS n FROM applications WHERE applicant_user_id = ?', [$uploadStudent['id']], 'test')['n'];
$privateDirectory = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'private';
$privateFilesBeforeUpload = is_dir($privateDirectory)
    ? array_values(array_diff(scandir($privateDirectory) ?: [], ['.', '..']))
    : [];
$uploadResponses = phase2bHttpRequests([
    [
        'method' => 'POST',
        'path' => '/layanan/TU-01/ajukan',
        'cookie' => $studentHttpIdentity['cookie'],
        'post' => $uploadBasePost,
        'files' => [['name' => 'nama-asli-jpg-' . $suffix . '.jpg', 'type' => 'text/plain', 'content' => $jpegBytes]],
    ],
    [
        'method' => 'POST',
        'path' => '/layanan/TU-01/ajukan',
        'cookie' => $studentHttpIdentity['cookie'],
        'post' => $uploadBasePost,
        'files' => [['name' => 'nama-asli-png-' . $suffix . '.png', 'type' => 'application/octet-stream', 'content' => $pngBytes]],
    ],
    [
        'method' => 'POST',
        'path' => '/layanan/TU-01/ajukan',
        'cookie' => $studentHttpIdentity['cookie'],
        'post' => $uploadBasePost,
        'files' => [['name' => 'php-disamarkan-' . $suffix . '.jpg', 'type' => 'image/jpeg', 'content' => '<?php echo "not an image"; ?>']],
    ],
    [
        'method' => 'POST',
        'path' => '/layanan/TU-01/ajukan',
        'cookie' => $studentHttpIdentity['cookie'],
        'post' => $uploadBasePost,
        'files' => [['name' => 'gambar-gif-' . $suffix . '.gif', 'type' => 'image/gif', 'content' => $gifBytes]],
    ],
    [
        'method' => 'POST',
        'path' => '/layanan/TU-01/ajukan',
        'cookie' => $studentHttpIdentity['cookie'],
        'post' => $uploadBasePost,
        'files' => [['name' => 'dokumen-' . $suffix . '.pdf', 'type' => 'application/pdf', 'content' => '%PDF-1.7 fake fixture']],
    ],
    [
        'method' => 'POST',
        'path' => '/layanan/TU-01/ajukan',
        'cookie' => $studentHttpIdentity['cookie'],
        'post' => $uploadBasePost,
        'files' => [['name' => 'vektor-' . $suffix . '.svg', 'type' => 'image/svg+xml', 'content' => '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>']],
    ],
    [
        'method' => 'POST',
        'path' => '/layanan/TU-01/ajukan',
        'cookie' => $studentHttpIdentity['cookie'],
        'post' => $uploadBasePost,
        'files' => [['name' => 'lebih-besar-' . $suffix . '.png', 'type' => 'image/png', 'content' => str_repeat('x', 5 * 1024 * 1024 + 1)]],
    ],
    [
        'method' => 'POST',
        'path' => '/layanan/TU-01/ajukan',
        'cookie' => $studentHttpIdentity['cookie'],
        'post' => $uploadBasePost,
        'files' => array_fill(0, 4, ['name' => 'empat-' . $suffix . '.png', 'type' => 'image/png', 'content' => $pngBytes]),
    ],
    [
        'method' => 'POST',
        'path' => '/layanan/TU-01/ajukan',
        'cookie' => $studentHttpIdentity['cookie'],
        'post' => ['_csrf' => 'invalid-csrf-token', 'request_text' => 'Permohonan tanpa CSRF'],
        'files' => [['name' => 'csrf-' . $suffix . '.png', 'type' => 'image/png', 'content' => $pngBytes]],
    ],
    [
        'method' => 'POST',
        'path' => '/layanan/KES-02/ajukan',
        'cookie' => $studentHttpIdentity['cookie'],
        'post' => $uploadBasePost,
        'files' => [['name' => 'paksa-kes02-' . $suffix . '.png', 'type' => 'image/png', 'content' => $pngBytes]],
    ],
    [
        'method' => 'POST',
        'path' => '/layanan/KES-04/ajukan',
        'cookie' => $studentHttpIdentity['cookie'],
        'post' => $uploadBasePost,
        'files' => [['name' => 'paksa-kes04-' . $suffix . '.png', 'type' => 'image/png', 'content' => $pngBytes]],
    ],
]);
$uploadApplications = Db::all(
    'SELECT id, tracking_code FROM applications WHERE applicant_user_id = ? ORDER BY id',
    [$uploadStudent['id']],
    'test'
);
$uploadDocuments = Db::all(
    'SELECT d.id, d.application_id, d.nama_asli, d.nama_acak, d.mime_terdeteksi, d.ukuran, d.sha256 FROM application_documents d INNER JOIN applications a ON a.id = d.application_id WHERE a.applicant_user_id = ? ORDER BY d.id',
    [$uploadStudent['id']],
    'test'
);
$storedDocumentChecks = count($uploadDocuments) === 2;
foreach ($uploadDocuments as $document) {
    $storedPath = $privateDirectory . DIRECTORY_SEPARATOR . $document['nama_acak'];
    $storedDocumentChecks = $storedDocumentChecks
        && preg_match('/^[a-f0-9]{64}\.(?:jpg|png)$/D', $document['nama_acak']) === 1
        && $document['nama_acak'] !== $document['nama_asli']
        && is_file($storedPath)
        && hash_file('sha256', $storedPath) === $document['sha256']
        && !str_starts_with(strtolower($storedPath), strtolower(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR));
}
check(
    'JPG/PNG sah diterima dari MIME terdeteksi, disimpan privat dengan nama acak dan SHA-256 server',
    count($uploadResponses) === 11
        && $uploadResponses[0]['status'] === 303
        && $uploadResponses[1]['status'] === 303
        && count($uploadApplications) === $studentApplicationsBeforeUpload + 2
        && $storedDocumentChecks
        && count($privateFilesBeforeUpload) + 2 === count(array_values(array_diff(scandir($privateDirectory) ?: [], ['.', '..']))),
    'statuses=' . json_encode(array_column($uploadResponses, 'status'))
        . '; documents=' . count($uploadDocuments)
);
$invalidUploadStatuses = true;
foreach (array_slice($uploadResponses, 2, 6) as $response) {
    $invalidUploadStatuses = $invalidUploadStatuses && $response['status'] === 422;
}
check(
    'PHP bernama JPG, GIF, PDF, SVG, berkas >5 MiB, dan empat lampiran ditolak',
    $invalidUploadStatuses
        && str_contains($uploadResponses[2]['body'], 'Hanya berkas JPG atau PNG')
        && str_contains($uploadResponses[6]['body'], 'Berkas terlalu besar')
        && str_contains($uploadResponses[7]['body'], 'Maksimal 3 berkas'),
    'statuses=' . json_encode(array_column($uploadResponses, 'status'))
);
check(
    'CSRF salah dan lampiran paksa KES-02/KES-04 tidak menyimpan dokumen',
    $uploadResponses[8]['status'] === 403
        && $uploadResponses[9]['status'] === 422
        && $uploadResponses[10]['status'] === 422
        && (int) Db::one('SELECT COUNT(*) AS n FROM application_documents', [], 'test')['n'] === 2,
    'statuses=' . json_encode(array_column($uploadResponses, 'status'))
);

$phase2bPreviousSessionUser = $_SESSION['user'] ?? null;
$phase2bPreviousIdentity = is_array($phase2bPreviousSessionUser) ? [
    'id' => $phase2bPreviousSessionUser['id'] ?? null,
    'role' => $phase2bPreviousSessionUser['role_code'] ?? $phase2bPreviousSessionUser['role'] ?? null,
    'identifier' => $phase2bPreviousSessionUser['identifier'] ?? null,
    'name' => $phase2bPreviousSessionUser['full_name'] ?? $phase2bPreviousSessionUser['name'] ?? null,
    'token' => $phase2bPreviousSessionUser['auth_token'] ?? $phase2bPreviousSessionUser['token'] ?? null,
] : null;
phase2bAs($uploadStudent);
$kes02Form = phase2bRequest('GET', '/layanan/KES-02/ajukan');
$kes04Form = phase2bRequest('GET', '/layanan/KES-04/ajukan');
phase2bAs($phase2bPreviousIdentity);
check(
    'Form KES-02/KES-04 tidak menampilkan input lampiran',
    !str_contains($kes02Form['body'], 'name="attachments[]"')
        && !str_contains($kes04Form['body'], 'name="attachments[]"')
);

$privateFilesBeforeStorageFailure = is_dir($privateDirectory)
    ? array_values(array_diff(scandir($privateDirectory) ?: [], ['.', '..']))
    : [];
$applicationsBeforeStorageFailure = (int) Db::one('SELECT COUNT(*) AS n FROM applications WHERE applicant_user_id = ?', [$uploadStudent['id']], 'test')['n'];
$pdo = Db::pdo('test');
$pdo->exec("CREATE TRIGGER trg_test_fail_application_document BEFORE INSERT ON application_documents FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'fixture document persistence failure'");
try {
    $storageFailureResponses = phase2bHttpRequests([[
        'method' => 'POST',
        'path' => '/layanan/TU-01/ajukan',
        'cookie' => $studentHttpIdentity['cookie'],
        'post' => $uploadBasePost,
        'files' => [['name' => 'gagal-simpan-' . $suffix . '.png', 'type' => 'image/png', 'content' => $pngBytes]],
    ]]);
} finally {
    $pdo->exec('DROP TRIGGER IF EXISTS trg_test_fail_application_document');
}
$privateFilesAfterStorageFailure = is_dir($privateDirectory)
    ? array_values(array_diff(scandir($privateDirectory) ?: [], ['.', '..']))
    : [];
check(
    'Kegagalan menyimpan metadata lampiran membatalkan pengajuan dan membersihkan berkas fisik',
    count($storageFailureResponses) === 1
        && $storageFailureResponses[0]['status'] === 500
        && (int) Db::one('SELECT COUNT(*) AS n FROM applications WHERE applicant_user_id = ?', [$uploadStudent['id']], 'test')['n'] === $applicationsBeforeStorageFailure
        && (int) Db::one('SELECT COUNT(*) AS n FROM application_documents', [], 'test')['n'] === 2
        && $privateFilesAfterStorageFailure === $privateFilesBeforeStorageFailure,
    'status=' . json_encode(array_column($storageFailureResponses, 'status'))
        . '; applications=' . (int) Db::one('SELECT COUNT(*) AS n FROM applications WHERE applicant_user_id = ?', [$uploadStudent['id']], 'test')['n']
        . '/' . $applicationsBeforeStorageFailure
        . '; document_count=' . (int) Db::one('SELECT COUNT(*) AS n FROM application_documents', [], 'test')['n']
        . '; files=' . count($privateFilesAfterStorageFailure) . '/' . count($privateFilesBeforeStorageFailure)
);

$otherApplicationResponse = phase2bHttpRequests([[
    'method' => 'POST',
    'path' => '/layanan/TU-01/ajukan',
    'cookie' => $otherHttpIdentity['cookie'],
    'post' => ['_csrf' => $otherHttpIdentity['csrf'], 'request_text' => 'Pengajuan pemilik lain'],
]]);
$otherApplication = Db::one(
    'SELECT id FROM applications WHERE applicant_user_id = ? ORDER BY id DESC LIMIT 1',
    [$otherApplicant['id']],
    'test'
);
$documentId = (int) $uploadDocuments[0]['id'];
$applicationId = (int) $uploadDocuments[0]['application_id'];
$trackingCode = (string) $uploadApplications[0]['tracking_code'];
$adminDetail = phase2bHttpRequests([[
    'method' => 'GET',
    'path' => '/admin/pengajuan/' . $applicationId,
    'cookie' => $adminHttpIdentity['cookie'],
]]);
$portalAndTracking = phase2bHttpRequests([
    ['method' => 'GET', 'path' => '/portal', 'cookie' => $studentHttpIdentity['cookie']],
    ['method' => 'GET', 'path' => '/lacak', 'cookie' => $guestHttpIdentity['cookie']],
    [
        'method' => 'POST',
        'path' => '/lacak',
        'cookie' => $guestHttpIdentity['cookie'],
        'post' => ['_csrf' => $guestHttpIdentity['csrf'], 'tracking_code' => $trackingCode],
    ],
]);
$unprivilegedDownloads = phase2bHttpRequests([
    ['method' => 'GET', 'path' => '/admin/pengajuan/' . $applicationId . '/berkas/' . $documentId, 'cookie' => $guestHttpIdentity['cookie']],
    ['method' => 'GET', 'path' => '/admin/pengajuan/' . $applicationId . '/berkas/' . $documentId, 'cookie' => $studentHttpIdentity['cookie']],
    ['method' => 'GET', 'path' => '/admin/pengajuan/' . $applicationId . '/berkas/' . $documentId, 'cookie' => $otherHttpIdentity['cookie']],
]);
$adminDownloads = phase2bHttpRequests([
    ['method' => 'GET', 'path' => '/admin/pengajuan/' . $applicationId . '/berkas/' . $documentId, 'cookie' => $adminHttpIdentity['cookie']],
    ['method' => 'GET', 'path' => '/admin/pengajuan/' . (int) ($otherApplication['id'] ?? 0) . '/berkas/' . $documentId, 'cookie' => $adminHttpIdentity['cookie']],
    ['method' => 'GET', 'path' => '/admin/pengajuan/' . $applicationId . '/berkas/' . $documentId . '999999', 'cookie' => $adminHttpIdentity['cookie']],
]);
$storedDocumentPath = $privateDirectory . DIRECTORY_SEPARATOR . $uploadDocuments[0]['nama_acak'];
$hiddenDocumentPath = $storedDocumentPath . '.hidden-test';
$physicalFileHidden = rename($storedDocumentPath, $hiddenDocumentPath);
if (!$physicalFileHidden) {
    throw new RuntimeException('Tidak dapat menyiapkan fixture berkas privat yang hilang.');
}
try {
    $missingPhysicalDownload = phase2bHttpRequests([[
        'method' => 'GET',
        'path' => '/admin/pengajuan/' . $applicationId . '/berkas/' . $documentId,
        'cookie' => $adminHttpIdentity['cookie'],
    ]]);
} finally {
    if (!rename($hiddenDocumentPath, $storedDocumentPath)) {
        throw new RuntimeException('Tidak dapat memulihkan fixture berkas privat.');
    }
}
$downloadHeaderText = implode("\n", $adminDownloads[0]['headers'] ?? []);
$publicDocumentLeak = false;
foreach (array_slice($portalAndTracking, 0, 3) as $response) {
    $publicDocumentLeak = $publicDocumentLeak
        || str_contains($response['body'], (string) $uploadDocuments[0]['nama_asli'])
        || str_contains($response['body'], (string) $uploadDocuments[0]['nama_acak'])
        || str_contains($response['body'], (string) $uploadDocuments[0]['sha256'])
        || str_contains($response['body'], 'storage/private');
}
$uploadAuditCount = (int) Db::one(
    'SELECT COUNT(*) AS n FROM audit_logs WHERE action = ? AND entity_type = ?',
    ['application_document_uploaded', 'application_documents'],
    'test'
)['n'];
$downloadAuditCount = (int) Db::one(
    'SELECT COUNT(*) AS n FROM audit_logs WHERE action = ? AND entity_type = ? AND entity_id = ?',
    ['application_document_downloaded', 'application_documents', $documentId],
    'test'
)['n'];
check(
    'Lampiran tercatat di audit dan detail ADMIN hanya menampilkan ukuran/tipe',
    $uploadAuditCount === 2
        && $downloadAuditCount === 1
        && ($adminDetail[0]['status'] ?? 0) === 200
        && str_contains($adminDetail[0]['body'], (string) $uploadDocuments[0]['mime_terdeteksi'])
        && str_contains($adminDetail[0]['body'], (string) $uploadDocuments[0]['ukuran'])
        && !str_contains($adminDetail[0]['body'], (string) $uploadDocuments[0]['nama_asli'])
        && !str_contains($adminDetail[0]['body'], (string) $uploadDocuments[0]['sha256'])
        && !str_contains($adminDetail[0]['body'], 'storage/private')
);
check('Portal dan cek status tidak menampilkan nama/jalur/hash lampiran', !$publicDocumentLeak);
$downloadStatuses = array_column($unprivilegedDownloads, 'status');
check(
    'Unduh lampiran guest diarahkan login, SISWA/GURU ditolak, ADMIN mendapat header aman dan lampiran lintas pengajuan 404',
    $downloadStatuses === [302, 403, 403]
        && ($adminDownloads[0]['status'] ?? 0) === 200
        && str_contains(strtolower($downloadHeaderText), 'content-type: image/jpeg')
        && str_contains(strtolower($downloadHeaderText), 'content-disposition: attachment;')
        && str_contains(strtolower($downloadHeaderText), 'x-content-type-options: nosniff')
        && str_contains(strtolower($downloadHeaderText), 'cache-control: no-store')
        && ($adminDownloads[0]['body'] ?? '') === file_get_contents($privateDirectory . DIRECTORY_SEPARATOR . $uploadDocuments[0]['nama_acak'])
        && ($adminDownloads[1]['status'] ?? 0) === 404
        && ($adminDownloads[2]['status'] ?? 0) === 404
        && ($missingPhysicalDownload[0]['status'] ?? 0) === 404
        && ($otherApplicationResponse[0]['status'] ?? 0) === 303,
    'guest/student/teacher=' . json_encode($downloadStatuses)
        . '; admin/mismatch/missing=' . json_encode(array_column($adminDownloads, 'status'))
        . '; physical_missing=' . json_encode(array_column($missingPhysicalDownload, 'status'))
        . '; other_submission=' . json_encode(array_column($otherApplicationResponse, 'status'))
        . '; headers=' . json_encode($adminDownloads[0]['headers'] ?? [])
        . '; content_matches=' . ((($adminDownloads[0]['body'] ?? '') === file_get_contents($privateDirectory . DIRECTORY_SEPARATOR . $uploadDocuments[0]['nama_acak'])) ? 'yes' : 'no')
);

$oversizedPostIdentity = phase2bHttpIdentity($uploadStudent);
$oversizedPostResponse = phase2bHttpRequests([[
    'method' => 'POST',
    'path' => '/layanan/TU-01/ajukan',
    'cookie' => $oversizedPostIdentity['cookie'],
    'post' => ['_csrf' => $oversizedPostIdentity['csrf'], 'request_text' => 'Permohonan terlalu besar'],
    'files' => [['name' => 'post-terlalu-besar.png', 'type' => 'image/png', 'content' => str_repeat('x', 2048)]],
]], ['post_max_size=1K', 'upload_max_filesize=6M']);
check(
    'POST melebihi post_max_size menampilkan Berkas terlalu besar, bukan 403 CSRF',
    ($oversizedPostResponse[0]['status'] ?? 0) === 413
        && str_contains($oversizedPostResponse[0]['body'] ?? '', 'Berkas terlalu besar')
);

foreach ($uploadDocuments as $document) {
    $storedPath = $privateDirectory . DIRECTORY_SEPARATOR . $document['nama_acak'];
    if (is_file($storedPath)) {
        unlink($storedPath);
    }
}
Db::run('UPDATE services SET mode = ? WHERE code = ?', [$uploadServiceMode, 'TU-01'], 'test');

$badMethod = phase2bRequest('GET', '/admin/pengguna/' . $guru['id'] . '/role');
check('Metode salah pada endpoint mutasi ditolak 405', $badMethod['status'] === 405);

check(
    'Invariant: setidaknya satu ADMIN aktif setelah setiap permintaan role/nonaktif',
    $phase2bAdminInvariantPassed,
    implode('; ', $phase2bAdminInvariantEvidence)
);
