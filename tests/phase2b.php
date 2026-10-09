<?php
declare(strict_types=1);

use App\Core\Auth;
use App\Core\Config;
use App\Core\Csrf;
use App\Core\Db;
use App\Core\Router;
use App\Core\ServiceCatalog;
use App\Core\View;
use App\Policies\Permissions;

require_once __DIR__ . '/../app/Controllers/AuthController.php';
require_once __DIR__ . '/../app/Controllers/PortalController.php';
require_once __DIR__ . '/../app/Controllers/AdminController.php';
require_once __DIR__ . '/../app/Core/ServiceCatalog.php';
require_once __DIR__ . '/../app/Controllers/ServiceController.php';

$catalogSql = file_get_contents(__DIR__ . '/../database/migrations/004_service_catalog.sql');
$catalogPdo = Db::pdo('test');
$catalogStatements = array_filter(array_map('trim', explode(';', (string) $catalogSql)));
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
    $userId = (int) Db::pdo('test')->lastInsertId();
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
    '/portal' => 'portal.view',
    '/admin' => 'admin.dashboard',
    '/admin/pengguna' => 'admin.users.manage',
    '/admin/audit' => 'admin.audit.view',
    '/admin/layanan' => 'admin.services.manage',
    '/admin/layanan/TU-01/ubah' => 'admin.services.edit',
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
    'Layanan INFORMASI tidak menawarkan pengajuan; FORMULIR menjelaskan pengajuan menyusul',
    !str_contains($updatedPublicDetail['body'], '>Ajukan<')
        && str_contains($updatedPublicDetail['body'], ServiceCatalog::INFORMATION_STEPS_NOTICE)
        && str_contains($formCatalogDetailResponse['body'], 'Ajukan')
        && str_contains($formCatalogDetailResponse['body'], 'Pengajuan melalui website menyusul pada Langkah 4.')
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

$badMethod = phase2bRequest('GET', '/admin/pengguna/' . $guru['id'] . '/role');
check('Metode salah pada endpoint mutasi ditolak 405', $badMethod['status'] === 405);

check(
    'Invariant: setidaknya satu ADMIN aktif setelah setiap permintaan role/nonaktif',
    $phase2bAdminInvariantPassed,
    implode('; ', $phase2bAdminInvariantEvidence)
);
