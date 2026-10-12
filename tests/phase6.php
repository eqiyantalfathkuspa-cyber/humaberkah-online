<?php
declare(strict_types=1);

use App\Core\ApplicationWorkflow;
use App\Core\Auth;
use App\Core\Db;
use App\Core\IpQuotaHash;
use App\Core\TrackingCode;
use App\Policies\Permissions;

function phase6Application(string $status, int $serviceId, int $userId): array
{
    $trackingCode = TrackingCode::generate();
    Db::run(
        'INSERT INTO applications (service_id, applicant_user_id, tracking_code, request_text, status) VALUES (?, ?, ?, ?, ?)',
        [$serviceId, $userId, $trackingCode, 'fixture survey application', $status],
        'test'
    );
    $id = (int) Db::one(
        'SELECT id FROM applications WHERE tracking_code = ?',
        [$trackingCode],
        'test'
    )['id'];
    return ['id' => $id, 'tracking_code' => $trackingCode, 'service_id' => $serviceId];
}

function phase6Identity(?array $user = null): array
{
    return phase2bHttpIdentity($user);
}

function phase6PostRequest(array $identity, string $trackingCode, array $extra = []): array
{
    return [
        'method' => 'POST',
        'path' => '/survei',
        'cookie' => $identity['cookie'],
        'post' => array_merge([
            '_csrf' => $identity['csrf'],
            'tracking_code' => $trackingCode,
            'speed_rating' => '4',
            'clarity_rating' => '3',
            'courtesy_rating' => '5',
            'suggestion' => '',
        ], $extra),
    ];
}

function phase6ResetLoopbackQuota(): void
{
    Db::run(
        'DELETE FROM survey_ip_quotas WHERE ip_hash = ?',
        [IpQuotaHash::forAddress('127.0.0.1')],
        'test'
    );
    Db::run(
        'DELETE FROM survey_ip_quotas WHERE ip_hash = ?',
        [hash('sha256', '127.0.0.1')],
        'test'
    );
}

function phase6AddSurvey(array $application, int $speed, int $clarity, int $courtesy, string $suggestion): void
{
    Db::run(
        'INSERT INTO surveys (application_id, service_id, speed_rating, clarity_rating, courtesy_rating, suggestion) VALUES (?, ?, ?, ?, ?, ?)',
        [$application['id'], $application['service_id'], $speed, $clarity, $courtesy, $suggestion],
        'test'
    );
}

function phase6StartSurveyServer(): array
{
    $socket = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);
    if ($socket === false) {
        throw new RuntimeException('Tidak dapat memperoleh port tes HTTP.');
    }
    $address = stream_socket_get_name($socket, false);
    fclose($socket);
    if ($address === false) {
        throw new RuntimeException('Tidak dapat membaca alamat tes HTTP.');
    }
    $process = proc_open(
        [PHP_BINARY, '-S', $address, __DIR__ . '/../public/index.php'],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        __DIR__
    );
    if (!is_resource($process)) {
        throw new RuntimeException('Tidak dapat memulai proses HTTP untuk survei.');
    }
    return ['address' => $address, 'process' => $process, 'pipes' => $pipes];
}

function phase6HttpRequestWithLogs(string $path, string $cookie): array
{
    $listener = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);
    if ($listener === false) {
        throw new RuntimeException('Tidak dapat memperoleh port tes HTTP galat.');
    }
    $address = stream_socket_get_name($listener, false);
    fclose($listener);
    if ($address === false) {
        throw new RuntimeException('Tidak dapat membaca alamat tes HTTP galat.');
    }

    $logPath = tempnam(sys_get_temp_dir(), 'huma-phase6-error-');
    if ($logPath === false) {
        throw new RuntimeException('Tidak dapat membuat log sementara untuk tes HTTP galat.');
    }
    $process = proc_open(
        [PHP_BINARY, '-d', 'display_errors=1', '-d', 'log_errors=1', '-d', 'error_log=' . $logPath, '-S', $address, __DIR__ . '/../public/index.php'],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        __DIR__
    );
    if (!is_resource($process)) {
        unlink($logPath);
        throw new RuntimeException('Tidak dapat memulai server tes HTTP galat.');
    }

    $socket = false;
    for ($attempt = 0; $attempt < 40 && $socket === false; $attempt++) {
        $socket = @stream_socket_client('tcp://' . $address, $errorCode, $errorMessage, 0.25);
        if ($socket === false) {
            usleep(25000);
        }
    }
    if ($socket === false) {
        proc_terminate($process);
        foreach ($pipes as $pipe) {
            fclose($pipe);
        }
        proc_close($process);
        unlink($logPath);
        throw new RuntimeException('Server tes HTTP galat tidak menerima koneksi.');
    }

    stream_set_timeout($socket, 5);
    fwrite($socket, "GET " . $path . " HTTP/1.1\r\n"
        . 'Host: ' . $address . "\r\n"
        . 'Cookie: ' . $cookie . "\r\n"
        . "Connection: close\r\n\r\n");
    $rawResponse = stream_get_contents($socket);
    fclose($socket);

    proc_terminate($process);
    $stdout = (string) stream_get_contents($pipes[1]);
    $stderr = (string) stream_get_contents($pipes[2]);
    foreach ($pipes as $pipe) {
        fclose($pipe);
    }
    proc_close($process);
    $errorLog = file_get_contents($logPath);
    unlink($logPath);

    $parts = explode("\r\n\r\n", (string) $rawResponse, 2);
    $headers = explode("\r\n", $parts[0] ?? '');
    preg_match('/^HTTP\/\S+\s+(\d+)/', (string) ($headers[0] ?? ''), $statusMatch);
    return [
        'status' => (int) ($statusMatch[1] ?? 0),
        'headers' => $headers,
        'body' => $parts[1] ?? '',
        'logs' => (string) $errorLog . $stdout . $stderr,
    ];
}

$phase6Migration = '009_surveys.sql';
$phase6MigrationBefore = Db::one(
    'SELECT status, checksum, statement_count, last_completed_statement FROM schema_migration_runs WHERE migration_name = ?',
    [$phase6Migration],
    'test'
);
[$phase6MigrationFirstCode, $phase6MigrationFirstOutput] = runMigrationProcess(__DIR__ . '/../database/migrations');
[$phase6MigrationSecondCode, $phase6MigrationSecondOutput] = runMigrationProcess(__DIR__ . '/../database/migrations');
$phase6MigrationAfter = Db::one(
    'SELECT status, checksum, statement_count, last_completed_statement FROM schema_migration_runs WHERE migration_name = ?',
    [$phase6Migration],
    'test'
);
check(
    'Migrasi 009 sudah APPLIED dan dapat dipanggil ulang tanpa mengubah ledger',
    $phase6MigrationBefore === $phase6MigrationAfter
        && ($phase6MigrationAfter['status'] ?? null) === 'APPLIED'
        && (int) ($phase6MigrationAfter['statement_count'] ?? 0) === 2
        && (int) ($phase6MigrationAfter['last_completed_statement'] ?? 0) === 2
        && $phase6MigrationFirstCode === 0
        && $phase6MigrationSecondCode === 0,
    $phase6MigrationFirstOutput . $phase6MigrationSecondOutput
);

$phase6DriftPdo = Db::pdo('test');
$phase6DriftDetected = false;
$phase6DriftRestored = false;
try {
    $phase6DriftPdo->exec('ALTER TABLE surveys MODIFY COLUMN suggestion VARCHAR(999) NULL');
    [$phase6DriftExitCode, $phase6DriftOutput] = runMigrationProcess(__DIR__ . '/../database/migrations');
    $phase6DriftDetected = $phase6DriftExitCode !== 0
        && str_contains($phase6DriftOutput, 'skema aktual berbeda');
} finally {
    $phase6DriftPdo->exec('ALTER TABLE surveys MODIFY COLUMN suggestion VARCHAR(1000) NULL');
    [$phase6RestoreExitCode, $phase6RestoreOutput] = runMigrationProcess(__DIR__ . '/../database/migrations');
    $phase6DriftRestored = $phase6RestoreExitCode === 0;
}
check(
    'Signature migrasi 009 menolak drift kolom dan kembali valid setelah skema fixture dipulihkan',
    $phase6DriftDetected && $phase6DriftRestored,
    'drift=' . ($phase6DriftDetected ? 'terdeteksi' : 'tidak terdeteksi')
        . ', pemulihan=' . ($phase6DriftRestored ? 'lulus' : 'gagal')
);

$phase6Student = phase2bUser('SISWA', 'phase6-siswa-' . bin2hex(random_bytes(4)), 'Survei Siswa', bin2hex(random_bytes(16)));
$phase6Teacher = phase2bUser('GURU', 'phase6-guru-' . bin2hex(random_bytes(4)), 'Survei Guru', bin2hex(random_bytes(16)));
$phase6Admin = phase2bUser('ADMIN', 'phase6-admin-' . bin2hex(random_bytes(4)), 'Survei Admin', bin2hex(random_bytes(16)));
$phase6RoleIdentities = [
    'GUEST' => phase6Identity(),
    'SISWA' => phase6Identity($phase6Student),
    'GURU' => phase6Identity($phase6Teacher),
    'ADMIN' => phase6Identity($phase6Admin),
];
$phase6MatrixCases = [];
foreach ($phase6RoleIdentities as $role => $identity) {
    $phase6MatrixCases[] = ['role' => $role, 'route' => 'GET /survei', 'ability' => 'surveys.submit', 'expected' => 200, 'request' => [
        'method' => 'GET', 'path' => '/survei', 'cookie' => $identity['cookie'],
    ]];
    $phase6MatrixCases[] = ['role' => $role, 'route' => 'GET /survei/terima', 'ability' => 'surveys.submit', 'expected' => 200, 'request' => [
        'method' => 'GET', 'path' => '/survei/terima', 'cookie' => $identity['cookie'],
    ]];
    $phase6MatrixCases[] = ['role' => $role, 'route' => 'GET /admin', 'ability' => 'admin.dashboard', 'expected' => $role === 'ADMIN' ? 200 : ($role === 'GUEST' ? 302 : 403), 'request' => [
        'method' => 'GET', 'path' => '/admin', 'cookie' => $identity['cookie'],
    ]];
    $phase6MatrixCases[] = ['role' => $role, 'route' => 'POST /survei', 'ability' => 'surveys.submit', 'expected' => 422, 'request' => [
        'method' => 'POST',
        'path' => '/survei',
        'cookie' => $identity['cookie'],
        'post' => ['_csrf' => $identity['csrf']],
    ]];
    foreach (['GET /admin/survei', 'GET /admin/survei/ekspor'] as $route) {
        $isAdmin = $role === 'ADMIN';
        [$method, $path] = explode(' ', $route, 2);
        $ability = $path === '/admin/survei' ? 'admin.surveys.view' : 'admin.surveys.export';
        $phase6MatrixCases[] = ['role' => $role, 'route' => $route, 'ability' => $ability, 'expected' => $isAdmin ? 200 : ($role === 'GUEST' ? 302 : 403), 'request' => [
            'method' => $method, 'path' => $path, 'cookie' => $identity['cookie'],
        ]];
    }
}
$phase6MatrixResponses = phase2bHttpRequests(array_column($phase6MatrixCases, 'request'));
$phase6MatrixOk = count($phase6MatrixResponses) === count($phase6MatrixCases);
$phase6MatrixTable = "ROLE x RUTE LANGKAH 6\nRole | Rute | Expected | Actual\n";
foreach ($phase6MatrixCases as $index => $case) {
    $actual = (int) ($phase6MatrixResponses[$index]['status'] ?? 0);
    $allowed = Permissions::can($case['role'], $case['ability']);
    $expectedFromPermissions = $allowed
        ? (($case['request']['method'] ?? '') === 'POST' ? 422 : 200)
        : ($case['role'] === 'GUEST' ? 302 : 403);
    $phase6MatrixTable .= $case['role'] . ' | ' . $case['route'] . ' | '
        . $case['ability'] . ' | ' . ($allowed ? 'allowed' : 'denied')
        . ' | ' . $case['expected'] . ' | ' . $actual . PHP_EOL;
    $phase6MatrixOk = $phase6MatrixOk
        && $case['expected'] === $expectedFromPermissions
        && $actual === $case['expected'];
}
echo $phase6MatrixTable;
check('Matriks role x rute survei publik dan admin sesuai Permissions', $phase6MatrixOk, $phase6MatrixTable);

$phase6Service = Db::one('SELECT id FROM services WHERE code = ?', ['KUR-02'], 'test');
$phase6OtherService = Db::one('SELECT id FROM services WHERE code = ?', ['TU-01'], 'test');
$phase6EmptyAdmin = phase6Identity($phase6Admin);
$phase6EmptyResponses = phase2bHttpRequests([
    ['method' => 'GET', 'path' => '/admin/survei', 'cookie' => $phase6EmptyAdmin['cookie']],
    ['method' => 'GET', 'path' => '/admin', 'cookie' => $phase6EmptyAdmin['cookie']],
]);
$phase6EmptyDashboardCounts = Db::all(
    'SELECT status, COUNT(*) AS row_count FROM applications GROUP BY status',
    [],
    'test'
);
$phase6EmptyApplicationStatusCounts = [];
foreach (array_keys(ApplicationWorkflow::STATUS_LABELS) as $status) {
    $phase6EmptyApplicationStatusCounts[$status] = (int) Db::one(
        'SELECT COUNT(*) AS row_count FROM applications WHERE status = ?',
        [$status],
        'test'
    )['row_count'];
}
$phase6EmptyComplaintStatusCounts = [];
foreach (App\Core\ComplaintWorkflow::STATUSES as $status) {
    $phase6EmptyComplaintStatusCounts[$status] = (int) Db::one(
        'SELECT COUNT(*) AS row_count FROM complaints WHERE status = ?',
        [$status],
        'test'
    )['row_count'];
}
$phase6EmptyDashboardOk = count($phase6EmptyResponses) === 2
    && (int) $phase6EmptyResponses[0]['status'] === 200
    && str_contains($phase6EmptyResponses[0]['body'], 'BELUM ADA DATA')
    && str_contains($phase6EmptyResponses[0]['body'], 'DATA TERLALU SEDIKIT')
    && (int) $phase6EmptyResponses[1]['status'] === 200
    && str_contains($phase6EmptyResponses[1]['body'], 'Jumlah responden: 0')
    && str_contains($phase6EmptyResponses[1]['body'], 'BELUM ADA DATA');
foreach ($phase6EmptyDashboardCounts as $countRow) {
    $phase6EmptyDashboardOk = $phase6EmptyDashboardOk
        && str_contains($phase6EmptyResponses[1]['body'], (string) $countRow['row_count']);
}
foreach ($phase6EmptyApplicationStatusCounts as $status => $count) {
    $phase6EmptyDashboardOk = $phase6EmptyDashboardOk
        && str_contains($phase6EmptyResponses[1]['body'], ApplicationWorkflow::STATUS_LABELS[$status] . ': ' . $count);
}
foreach ($phase6EmptyComplaintStatusCounts as $status => $count) {
    $phase6EmptyDashboardOk = $phase6EmptyDashboardOk
        && str_contains($phase6EmptyResponses[1]['body'], App\Core\ComplaintWorkflow::STATUS_LABELS[$status] . ': ' . $count);
}
check(
    'Tanpa survei, rekap dan dashboard menampilkan BELUM ADA DATA untuk rata-rata serta hitungan aktual',
    $phase6EmptyDashboardOk,
    implode(',', array_map(static fn (array $response): string => (string) $response['status'], $phase6EmptyResponses))
);
$phase6Application = phase6Application('SELESAI', (int) $phase6Service['id'], (int) $phase6Student['id']);
$phase6IdentityGuest = phase6Identity();
phase6ResetLoopbackQuota();
$phase6ValidResponse = phase2bHttpRequests([
    phase6PostRequest($phase6IdentityGuest, $phase6Application['tracking_code'], [
        'suggestion' => '<script>alert("survey")</script>',
    ]),
])[0] ?? [];
$phase6Survey = Db::one(
    'SELECT id, application_id, service_id, speed_rating, clarity_rating, courtesy_rating, suggestion FROM surveys WHERE application_id = ?',
    [$phase6Application['id']],
    'test'
);
check(
    'Survei valid untuk pengajuan SELESAI disimpan satu kali tanpa identitas pengisi',
    (int) ($phase6ValidResponse['status'] ?? 0) === 303
        && $phase6Survey !== null
        && (int) $phase6Survey['service_id'] === (int) $phase6Service['id']
        && (int) $phase6Survey['speed_rating'] === 4
        && (int) $phase6Survey['clarity_rating'] === 3
        && (int) $phase6Survey['courtesy_rating'] === 5
        && $phase6Survey['suggestion'] === '<script>alert("survey")</script>'
        && !array_key_exists('user_id', $phase6Survey),
    (string) ($phase6ValidResponse['status'] ?? 0)
);

$phase6DuplicateIdentity = phase6Identity();
$phase6DuplicateResponse = phase2bHttpRequests([
    phase6PostRequest($phase6DuplicateIdentity, $phase6Application['tracking_code']),
])[0] ?? [];
$phase6DuplicateCount = (int) Db::one(
    'SELECT COUNT(*) AS total FROM surveys WHERE application_id = ?',
    [$phase6Application['id']],
    'test'
)['total'];
$phase6UniqueIndex = (int) Db::one(
    "SELECT COUNT(*) AS total FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'surveys' AND index_name = 'uq_surveys_application' AND non_unique = 0",
    [],
    'test'
)['total'];
check(
    'Pengiriman ganda ditolak oleh UNIQUE application_id sebagai sumber kebenaran',
    (int) ($phase6DuplicateResponse['status'] ?? 0) === 422
        && $phase6DuplicateCount === 1
        && $phase6UniqueIndex > 0,
    (string) ($phase6DuplicateResponse['status'] ?? 0)
);

$phase6ConcurrentApplication = phase6Application('SELESAI', (int) $phase6Service['id'], (int) $phase6Student['id']);
$phase6ConcurrentIdentities = [phase6Identity(), phase6Identity()];
$phase6ConcurrentRequests = [];
foreach ($phase6ConcurrentIdentities as $identity) {
    $phase6ConcurrentRequests[] = [
        'cookie' => $identity['cookie'],
        'post' => [
            '_csrf' => $identity['csrf'],
            'tracking_code' => $phase6ConcurrentApplication['tracking_code'],
            'speed_rating' => '5',
            'clarity_rating' => '5',
            'courtesy_rating' => '5',
            'suggestion' => '',
        ],
    ];
}
phase6ResetLoopbackQuota();
$phase6Servers = [phase6StartSurveyServer(), phase6StartSurveyServer()];
try {
    $phase6ConcurrentStatuses = [];
    foreach ($phase6Servers as $index => $server) {
        $phase6ConcurrentStatuses[$index] = $server['address'];
    }
    $phase6ConcurrentRawResults = [];
    $phase6Sockets = [];
    foreach ($phase6Servers as $index => $server) {
        $socket = stream_socket_client('tcp://' . $server['address'], $errorCode, $errorMessage, 5);
        if ($socket === false) {
            throw new RuntimeException('Tidak dapat membuka koneksi survei paralel.');
        }
        stream_set_timeout($socket, 10);
        $body = http_build_query($phase6ConcurrentRequests[$index]['post']);
        fwrite(
            $socket,
            "POST /survei HTTP/1.1\r\n"
                . 'Host: ' . $server['address'] . "\r\n"
                . "Connection: close\r\n"
                . 'Cookie: ' . $phase6ConcurrentRequests[$index]['cookie'] . "\r\n"
                . "Content-Type: application/x-www-form-urlencoded\r\n"
                . 'Content-Length: ' . strlen($body) . "\r\n\r\n"
                . $body
        );
        $phase6Sockets[] = $socket;
    }
    foreach ($phase6Sockets as $socket) {
        $raw = stream_get_contents($socket);
        fclose($socket);
        preg_match('/^HTTP\/\S+\s+(\d+)/', (string) $raw, $match);
        $phase6ConcurrentRawResults[] = (int) ($match[1] ?? 0);
    }
} finally {
    foreach ($phase6Servers as $server) {
        proc_terminate($server['process']);
        foreach ($server['pipes'] as $pipe) {
            fclose($pipe);
        }
        proc_close($server['process']);
    }
}
$phase6ConcurrentSurveyCount = (int) Db::one(
    'SELECT COUNT(*) AS total FROM surveys WHERE application_id = ?',
    [$phase6ConcurrentApplication['id']],
    'test'
)['total'];
sort($phase6ConcurrentRawResults);
check(
    'Dua POST paralel untuk pengajuan yang sama hanya menyimpan satu survei',
    $phase6ConcurrentRawResults === [303, 422] && $phase6ConcurrentSurveyCount === 1,
    json_encode($phase6ConcurrentRawResults)
);

$phase6InvalidStatuses = [];
foreach (['DIAJUKAN', 'DIPROSES', 'DITOLAK'] as $status) {
    $application = phase6Application($status, (int) $phase6Service['id'], (int) $phase6Student['id']);
    $phase6InvalidStatuses[] = $application['tracking_code'];
}
$phase6MalformedCode = 'HMA-2222-2222-2222';
$phase6ValidMissingCode = 'HMA-2222-2222-2222-2222';
$phase6InvalidStatuses[] = $phase6MalformedCode;
$phase6InvalidStatuses[] = $phase6ValidMissingCode;
$phase6InvalidIdentity = phase6Identity();
phase6ResetLoopbackQuota();
$phase6InvalidCodeResponses = [];
foreach ($phase6InvalidStatuses as $trackingCode) {
    $phase6InvalidCodeResponses[] = phase6PostRequest($phase6InvalidIdentity, $trackingCode);
}
$phase6InvalidResults = phase2bHttpRequests($phase6InvalidCodeResponses);
$phase6Uniform = count($phase6InvalidResults) === count($phase6InvalidStatuses);
foreach ($phase6InvalidResults as $response) {
    $phase6Uniform = $phase6Uniform
        && (int) $response['status'] === 422
        && str_contains($response['body'], 'Kode tidak valid, pengajuan belum selesai, atau survei sudah dikirim.');
}
$phase6Uniform = $phase6Uniform
    && TrackingCode::isValid($phase6ValidMissingCode)
    && count(array_unique(array_column($phase6InvalidResults, 'status'))) === 1
    && count(array_unique(array_column($phase6InvalidResults, 'body'))) === 1;
check(
    'Kode malformed, valid tetapi tidak ada, dan status non-SELESAI mendapat respons identik tanpa menyimpan survei',
    $phase6Uniform
        && (int) Db::one('SELECT COUNT(*) AS total FROM surveys WHERE application_id IN (SELECT id FROM applications WHERE tracking_code IN (?, ?, ?))', array_slice($phase6InvalidStatuses, 0, 3), 'test')['total'] === 0,
    implode(',', array_map(static fn (array $response): string => (string) $response['status'], $phase6InvalidResults))
);

$phase6ValidationCases = [
    ['speed_rating' => '0'],
    ['clarity_rating' => '6'],
    ['courtesy_rating' => '5.0'],
    ['missing_aspect' => true],
    ['suggestion' => str_repeat('x', 1001)],
    ['suggestion' => "\xC3\x28"],
];
$phase6ValidationApplication = phase6Application('SELESAI', (int) $phase6Service['id'], (int) $phase6Student['id']);
$phase6ValidationIdentity = phase6Identity();
$phase6ValidationRequests = [];
foreach ($phase6ValidationCases as $case) {
    $request = phase6PostRequest(
        $phase6ValidationIdentity,
        $phase6ValidationApplication['tracking_code'],
        array_diff_key($case, ['missing_aspect' => true])
    );
    if (isset($case['missing_aspect'])) {
        unset($request['post']['courtesy_rating']);
    }
    $phase6ValidationRequests[] = $request;
}
$phase6ValidationRequests[] = [
    'method' => 'POST',
    'path' => '/survei',
    'cookie' => $phase6ValidationIdentity['cookie'],
    'post' => array_merge(phase6PostRequest($phase6ValidationIdentity, $phase6ValidationApplication['tracking_code'])['post'], ['_csrf' => 'wrong']),
];
$phase6ValidationRequests[] = phase6PostRequest($phase6ValidationIdentity, $phase6ValidationApplication['tracking_code'], ['website' => 'bot']);
$phase6ValidationResponses = phase2bHttpRequests($phase6ValidationRequests);
check(
    'Rating di luar rentang, nonangka, kurang dari tiga aspek, saran invalid, CSRF salah, dan honeypot ditolak',
    count($phase6ValidationResponses) === 8
        && count(array_filter(array_slice($phase6ValidationResponses, 0, 6), static fn (array $response): bool => (int) $response['status'] === 422)) === 6
        && (int) ($phase6ValidationResponses[6]['status'] ?? 0) === 403
        && (int) ($phase6ValidationResponses[7]['status'] ?? 0) === 422
        && Db::one('SELECT id FROM surveys WHERE application_id = ?', [$phase6ValidationApplication['id']], 'test') === null,
    implode(',', array_map(static fn (array $response): string => (string) $response['status'], $phase6ValidationResponses))
);

$phase6QuotaApplications = [];
for ($index = 0; $index < 6; $index++) {
    $phase6QuotaApplications[] = phase6Application('SELESAI', (int) $phase6Service['id'], (int) $phase6Student['id']);
}
$phase6QuotaIdentity = phase6Identity();
phase6ResetLoopbackQuota();
$phase6QuotaRequests = [];
foreach ($phase6QuotaApplications as $application) {
    $phase6QuotaRequests[] = phase6PostRequest($phase6QuotaIdentity, $application['tracking_code']);
}
$phase6QuotaResponses = phase2bHttpRequests($phase6QuotaRequests);
check(
    'Kuota survei membatasi lima pengiriman per IP per jam tanpa menyimpan kiriman keenam',
    array_column($phase6QuotaResponses, 'status') === [303, 303, 303, 303, 303, 429]
        && (int) Db::one(
            'SELECT COUNT(*) AS total FROM surveys WHERE application_id IN (?, ?, ?, ?, ?, ?)',
            array_column($phase6QuotaApplications, 'id'),
            'test'
        )['total'] === 5,
    implode(',', array_map(static fn (array $response): string => (string) $response['status'], $phase6QuotaResponses))
);

$phase6SecondApplication = phase6Application('SELESAI', (int) $phase6Service['id'], (int) $phase6Student['id']);
phase6AddSurvey($phase6SecondApplication, 2, 4, 3, 'Saran fixture kedua');
$phase6FormulaSuggestions = ['=SUM(1+1)', '+1+1', '-1+2', '@SUM(1+1)'];
foreach ($phase6FormulaSuggestions as $index => $suggestion) {
    $application = phase6Application('SELESAI', (int) $phase6OtherService['id'], (int) $phase6Student['id']);
    phase6AddSurvey($application, 1, 2, 3, $suggestion . ' ' . $index);
}
$phase6AdminReportIdentity = phase6Identity($phase6Admin);
$phase6Report = phase2bHttpRequests([[
    'method' => 'GET',
    'path' => '/admin/survei',
    'cookie' => $phase6AdminReportIdentity['cookie'],
]])[0] ?? [];
$phase6DashboardBaseline = Db::one(
    'SELECT COUNT(*) AS respondent_count, AVG(speed_rating) AS speed_average FROM surveys',
    [],
    'test'
);
phase2bAs($phase6Admin);
$phase6Dashboard = phase2bRequest('GET', '/admin');
$phase6DashboardCounts = Db::all('SELECT status, COUNT(*) AS row_count FROM applications GROUP BY status', [], 'test');
$phase6DashboardComplaintCounts = Db::all('SELECT status, COUNT(*) AS row_count FROM complaints GROUP BY status', [], 'test');
$phase6DashboardTemplate = (string) file_get_contents(__DIR__ . '/../app/Views/pages/admin_dashboard.php');
$phase6ServiceAverage = Db::one(
    'SELECT AVG(speed_rating) AS speed_average FROM surveys WHERE service_id = ?',
    [(int) $phase6Service['id']],
    'test'
);
$phase6GlobalAverage = Db::one('SELECT AVG(speed_rating) AS speed_average FROM surveys', [], 'test');
$phase6ReportChecks = (int) ($phase6Report['status'] ?? 0) === 200
    && str_contains((string) ($phase6Report['body'] ?? ''), number_format((float) $phase6ServiceAverage['speed_average'], 2, ',', '.'))
    && str_contains((string) ($phase6Report['body'] ?? ''), '&lt;script&gt;')
    && !str_contains((string) ($phase6Report['body'] ?? ''), '<script>alert("survey")</script>')
    && str_contains((string) ($phase6Report['body'] ?? ''), 'DATA TERLALU SEDIKIT')
    && ($phase6Dashboard['status'] ?? 0) === 200
    && (int) ($phase6DashboardBaseline['respondent_count'] ?? 0) === count($phase6QuotaApplications) - 1 + 3 + count($phase6FormulaSuggestions)
    && str_contains($phase6Dashboard['body'], 'Jumlah responden: ' . $phase6DashboardBaseline['respondent_count'])
    && str_contains($phase6Dashboard['body'], 'Rata-rata kecepatan: ' . number_format((float) $phase6GlobalAverage['speed_average'], 2, ',', '.'))
    && !preg_match('/>\s*\d+(?:[.,]\d+)?\s*</', $phase6DashboardTemplate);
foreach ($phase6DashboardCounts as $countRow) {
    $phase6ReportChecks = $phase6ReportChecks
        && str_contains($phase6Dashboard['body'], (string) $countRow['row_count']);
}
foreach ($phase6DashboardComplaintCounts as $countRow) {
    $phase6ReportChecks = $phase6ReportChecks
        && str_contains($phase6Dashboard['body'], (string) $countRow['row_count']);
}
$phase6AuditSuggestion = Db::one(
    'SELECT message FROM audit_logs WHERE action = ? ORDER BY id DESC LIMIT 1',
    ['survey_submitted'],
    'test'
);
check(
    'Rekap menghitung rata-rata, meng-escape saran, audit tidak menyalin saran, dashboard berbasis data',
    $phase6ReportChecks
        && !str_contains((string) ($phase6AuditSuggestion['message'] ?? ''), '<script>'),
    json_encode([
        'report_status' => $phase6Report['status'] ?? null,
        'average' => number_format((float) $phase6ServiceAverage['speed_average'], 2, ',', '.'),
        'baseline' => $phase6DashboardBaseline,
        'counts' => $phase6DashboardCounts,
        'audit_clean' => !str_contains((string) ($phase6AuditSuggestion['message'] ?? ''), '<script>'),
        'checks' => [
            str_contains((string) ($phase6Report['body'] ?? ''), '&lt;script&gt;'),
            !str_contains((string) ($phase6Report['body'] ?? ''), '<script>alert("survey")</script>'),
            str_contains((string) ($phase6Report['body'] ?? ''), 'DATA TERLALU SEDIKIT'),
            ($phase6Dashboard['status'] ?? 0) === 200,
            (int) ($phase6DashboardBaseline['respondent_count'] ?? 0) === count($phase6QuotaApplications) - 1 + 3 + count($phase6FormulaSuggestions),
            str_contains($phase6Dashboard['body'], 'Jumlah responden: ' . $phase6DashboardBaseline['respondent_count']),
            str_contains($phase6Dashboard['body'], 'Rata-rata kecepatan: ' . number_format((float) $phase6GlobalAverage['speed_average'], 2, ',', '.')),
            !preg_match('/>\s*\d+(?:[.,]\d+)?\s*</', $phase6DashboardTemplate),
        ],
    ])
);

$phase6CsvIdentity = phase6Identity($phase6Admin);
$phase6CsvResponse = phase2bHttpRequests([[
    'method' => 'GET',
    'path' => '/admin/survei/ekspor',
    'cookie' => $phase6CsvIdentity['cookie'],
]])[0] ?? [];
$phase6CsvHeaders = implode("\n", $phase6CsvResponse['headers'] ?? []);
$phase6CsvBody = (string) ($phase6CsvResponse['body'] ?? '');
$phase6GuestCsv = phase2bHttpRequests([['method' => 'GET', 'path' => '/admin/survei/ekspor']])[0] ?? [];
check(
    'Ekspor CSV hanya ADMIN, header unduhan benar, dan empat awalan rumus diprefiks tanda kutip',
    (int) ($phase6CsvResponse['status'] ?? 0) === 200
        && stripos($phase6CsvHeaders, 'Content-Type: text/csv; charset=UTF-8') !== false
        && stripos($phase6CsvHeaders, 'Content-Disposition: attachment; filename="rekap-survei.csv"') !== false
        && (int) ($phase6GuestCsv['status'] ?? 0) === 302
        && str_contains($phase6CsvBody, "'=SUM(1+1)")
        && str_contains($phase6CsvBody, "'+1+1")
        && str_contains($phase6CsvBody, "'-1+2")
        && str_contains($phase6CsvBody, "'@SUM(1+1)"),
    $phase6CsvHeaders . PHP_EOL . $phase6CsvBody
);

$phase6RealApplication = phase6Application('SELESAI', (int) $phase6Service['id'], (int) $phase6Student['id']);
$phase6RealGuest = phase6Identity();
phase6ResetLoopbackQuota();
$phase6RealPublicResponses = phase2bHttpRequests([
    ['method' => 'GET', 'path' => '/survei'],
    phase6PostRequest($phase6RealGuest, $phase6RealApplication['tracking_code']),
    ['method' => 'GET', 'path' => '/survei/terima', 'cookie' => $phase6RealGuest['cookie']],
]);
$phase6RealAdmin = phase6Identity($phase6Admin);
$phase6RealAdminResponses = phase2bHttpRequests([
    ['method' => 'GET', 'path' => '/admin/survei', 'cookie' => $phase6RealAdmin['cookie']],
    ['method' => 'GET', 'path' => '/admin', 'cookie' => $phase6RealAdmin['cookie']],
]);
$phase6RealIndexPassed = count($phase6RealPublicResponses) === 3
    && (int) $phase6RealPublicResponses[0]['status'] === 200
    && (int) $phase6RealPublicResponses[1]['status'] === 303
    && (int) $phase6RealPublicResponses[2]['status'] === 200
    && str_contains($phase6RealPublicResponses[2]['body'], 'Terima kasih atas masukan Anda.')
    && count($phase6RealAdminResponses) === 2
    && (int) $phase6RealAdminResponses[0]['status'] === 200
    && (int) $phase6RealAdminResponses[1]['status'] === 200;
foreach (array_merge($phase6RealPublicResponses, $phase6RealAdminResponses) as $response) {
    $phase6RealIndexPassed = $phase6RealIndexPassed
        && !str_contains((string) $response['body'], 'Fatal error')
        && !str_contains((string) $response['body'], 'Class App\\');
}
check(
    'Entry point asli melayani GET/POST survei, terima kasih, rekap admin, dan dashboard admin tanpa fatal',
    $phase6RealIndexPassed
        && Db::one('SELECT id FROM surveys WHERE application_id = ?', [$phase6RealApplication['id']], 'test') !== null,
    implode(',', array_map(static fn (array $response): string => (string) $response['status'], array_merge($phase6RealPublicResponses, $phase6RealAdminResponses)))
);

$phase6ErrorAdmin = phase6Identity($phase6Admin);
$phase6ErrorTable = 'surveys_error_probe_' . bin2hex(random_bytes(4));
$phase6ErrorPdo = Db::pdo('test');
$phase6ErrorResponse = [];
try {
    $phase6ErrorPdo->exec('RENAME TABLE surveys TO ' . $phase6ErrorTable);
    $phase6ErrorResponse = phase6HttpRequestWithLogs('/admin/survei', $phase6ErrorAdmin['cookie']);
} finally {
    $phase6ErrorPdo->exec('RENAME TABLE ' . $phase6ErrorTable . ' TO surveys');
}
$phase6ErrorBody = (string) ($phase6ErrorResponse['body'] ?? '');
$phase6ErrorLogs = (string) ($phase6ErrorResponse['logs'] ?? '');
$phase6SafeFailure = (int) ($phase6ErrorResponse['status'] ?? 0) === 500
    && str_contains($phase6ErrorBody, 'Terjadi kesalahan pada server.')
    && !preg_match('/SQLSTATE|PDOException|Stack trace|surveys_error_probe|phase6\\.php|SurveyController\\.php|SELECT /i', $phase6ErrorBody)
    && str_contains($phase6ErrorLogs, 'Uncaught PDOException')
    && str_contains($phase6ErrorLogs, '42S02');
check(
    'Entry point asli menyembunyikan PDOException, mengembalikan 500 generik, dan menulis detail ke error log',
    $phase6SafeFailure,
    'status=' . ($phase6ErrorResponse['status'] ?? 0)
        . ', body_generik=' . (str_contains($phase6ErrorBody, 'Terjadi kesalahan pada server.') ? 'ya' : 'tidak')
        . ', detail_log=' . (str_contains($phase6ErrorLogs, 'Uncaught PDOException') ? 'ya' : 'tidak')
);
