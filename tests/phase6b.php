<?php
declare(strict_types=1);

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Db;
use App\Policies\Permissions;

function phase6bSubmit(string $ip, string $serviceId, array $overrides = [], ?string $csrf = null): array
{
    if ($csrf === null) {
        phase2bAs(null);
        $csrf = Csrf::token();
    }
    $_SERVER['REMOTE_ADDR'] = $ip;
    return phase2bRequest('POST', '/survei', array_merge([
        '_csrf' => $csrf,
        'survey_type' => 'information',
        'service_id' => $serviceId,
        'speed_rating' => '4',
        'clarity_rating' => '3',
        'courtesy_rating' => '5',
        'suggestion' => '',
        'website' => '',
    ], $overrides));
}

function phase6bServiceId(string $code): int
{
    return (int) (Db::one('SELECT id FROM services WHERE code = ?', [$code], 'test')['id'] ?? 0);
}

function phase6bHttpRequestUsingIndex(string $indexPath): array
{
    $listener = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);
    if ($listener === false) {
        return ['status' => 0, 'body' => ''];
    }
    $address = stream_socket_get_name($listener, false);
    fclose($listener);
    if ($address === false) {
        return ['status' => 0, 'body' => ''];
    }
    $process = proc_open(
        [PHP_BINARY, '-S', $address, $indexPath],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        __DIR__
    );
    if (!is_resource($process)) {
        return ['status' => 0, 'body' => ''];
    }
    $socket = false;
    try {
        for ($attempt = 0; $attempt < 40 && $socket === false; $attempt++) {
            $socket = @stream_socket_client('tcp://' . $address, $errorCode, $errorMessage, 0.25);
            if ($socket === false) {
                usleep(25000);
            }
        }
        if ($socket === false) {
            return ['status' => 0, 'body' => ''];
        }
        stream_set_timeout($socket, 5);
        fwrite($socket, "GET /survei HTTP/1.1\r\nHost: " . $address . "\r\nConnection: close\r\n\r\n");
        $rawResponse = (string) stream_get_contents($socket);
        $parts = explode("\r\n\r\n", $rawResponse, 2);
        $headers = explode("\r\n", $parts[0] ?? '');
        preg_match('/^HTTP\/\S+\s+(\d+)/', (string) ($headers[0] ?? ''), $statusMatch);
        return ['status' => (int) ($statusMatch[1] ?? 0), 'body' => $parts[1] ?? ''];
    } finally {
        if (is_resource($socket)) {
            fclose($socket);
        }
        proc_terminate($process);
        foreach ($pipes as $pipe) {
            fclose($pipe);
        }
        proc_close($process);
    }
}

$phase6bMigrationName = '010_information_surveys.sql';
$phase6bMigrationBefore = Db::one(
    'SELECT status, checksum, statement_count, last_completed_statement FROM schema_migration_runs WHERE migration_name = ?',
    [$phase6bMigrationName],
    'test'
);
[$phase6bMigrationFirstCode, $phase6bMigrationFirstOutput] = runMigrationProcess(__DIR__ . '/../database/migrations');
[$phase6bMigrationSecondCode, $phase6bMigrationSecondOutput] = runMigrationProcess(__DIR__ . '/../database/migrations');
$phase6bMigrationAfter = Db::one(
    'SELECT status, checksum, statement_count, last_completed_statement FROM schema_migration_runs WHERE migration_name = ?',
    [$phase6bMigrationName],
    'test'
);
check(
    'Migrasi 010 idempoten dan signature 009 tetap valid',
    ($phase6bMigrationBefore['status'] ?? null) === 'APPLIED'
        && $phase6bMigrationBefore === $phase6bMigrationAfter
        && (int) ($phase6bMigrationAfter['statement_count'] ?? 0) === 2
        && (int) ($phase6bMigrationAfter['last_completed_statement'] ?? 0) === 2
        && $phase6bMigrationFirstCode === 0
        && $phase6bMigrationSecondCode === 0,
    $phase6bMigrationFirstOutput . $phase6bMigrationSecondOutput
);

$phase6bDriftPdo = Db::pdo('test');
$phase6bDriftDetected = false;
$phase6bDriftRestored = false;
try {
    $phase6bDriftPdo->exec('ALTER TABLE information_surveys MODIFY COLUMN speed_rating SMALLINT UNSIGNED NOT NULL');
    [$phase6bDriftExitCode, $phase6bDriftOutput] = runMigrationProcess(__DIR__ . '/../database/migrations');
    $phase6bDriftDetected = $phase6bDriftExitCode !== 0
        && str_contains($phase6bDriftOutput, 'skema aktual berbeda');
} finally {
    $phase6bDriftPdo->exec('ALTER TABLE information_surveys MODIFY COLUMN speed_rating TINYINT UNSIGNED NOT NULL');
    [$phase6bRestoreExitCode, $phase6bRestoreOutput] = runMigrationProcess(__DIR__ . '/../database/migrations');
    $phase6bDriftRestored = $phase6bRestoreExitCode === 0;
}
check(
    'Signature 010 mendeteksi drift kolom pada fixture lalu valid kembali setelah dipulihkan',
    $phase6bDriftDetected && $phase6bDriftRestored,
    'drift=' . ($phase6bDriftDetected ? 'terdeteksi' : 'tidak terdeteksi')
        . ', pemulihan=' . ($phase6bDriftRestored ? 'lulus' : 'gagal')
        . '; ' . $phase6bRestoreOutput
);

$phase6bAdmin = phase2bUser(
    'ADMIN',
    'phase6b-admin-' . bin2hex(random_bytes(5)),
    'Survei Informasi Admin',
    bin2hex(random_bytes(16))
);
$phase6bStudent = phase2bUser(
    'SISWA',
    'phase6b-siswa-' . bin2hex(random_bytes(5)),
    'Survei Informasi Siswa',
    bin2hex(random_bytes(16))
);
$phase6bInitialInfoCount = (int) Db::one('SELECT COUNT(*) AS n FROM information_surveys', [], 'test')['n'];
phase2bAs($phase6bAdmin);
$phase6bEmptyDashboard = phase2bRequest('GET', '/admin');
$phase6bEmptySummary = phase2bRequest('GET', '/admin/survei');
check(
    'Dashboard menghitung nol dan rekap tanpa responden menampilkan BELUM ADA DATA',
    $phase6bInitialInfoCount === 0
        && str_contains($phase6bEmptyDashboard['body'], 'Jumlah responden: 0')
        && str_contains($phase6bEmptySummary['body'], 'BELUM ADA DATA')
);

$phase6bRoleIdentities = $phase6RoleIdentities;
$phase6bMatrixRequests = [];
$phase6bMatrixExpected = [];
foreach ($phase6bRoleIdentities as $role => $identity) {
    $phase6bMatrixRequests[] = [
        'method' => 'GET',
        'path' => '/survei',
        'cookie' => $identity['cookie'],
    ];
    $phase6bMatrixExpected[] = ['role' => $role, 'method' => 'GET', 'expected' => 200];
    $phase6bMatrixRequests[] = [
        'method' => 'POST',
        'path' => '/survei',
        'cookie' => $identity['cookie'],
        'post' => [
            '_csrf' => $identity['csrf'],
            'survey_type' => 'information',
            'service_id' => 'invalid-id',
            'speed_rating' => '4',
            'clarity_rating' => '3',
            'courtesy_rating' => '5',
        ],
    ];
    $phase6bMatrixExpected[] = ['role' => $role, 'method' => 'POST', 'expected' => 422];
}
$phase6bMatrixResponses = phase2bHttpRequests($phase6bMatrixRequests);
$phase6bMatrixPassed = count($phase6bMatrixResponses) === count($phase6bMatrixExpected);
echo PHP_EOL . "Matriks izin Langkah 6B: role | route | ability | hasil | harapan" . PHP_EOL;
foreach ($phase6bMatrixExpected as $index => $case) {
    $response = $phase6bMatrixResponses[$index] ?? ['status' => 0];
    $ability = 'surveys.submit';
    $allowed = Permissions::can($case['role'], $ability);
    $phase6bMatrixPassed = $phase6bMatrixPassed
        && $allowed
        && $response['status'] === $case['expected'];
    echo $case['role'] . ' | ' . $case['method'] . ' /survei | ' . $ability
        . ' | ' . ($allowed ? 'YA' : 'TIDAK') . ' / ' . $response['status']
        . ' | ' . $case['expected'] . PHP_EOL;
}
check('Matriks role x rute survei INFORMASI sesuai Permissions dan respons server', $phase6bMatrixPassed);

$phase6bInformationServiceId = phase6bServiceId('KES-01');
$phase6bOtherInformationServiceId = phase6bServiceId('KES-03');
$phase6bNonInformationServiceId = phase6bServiceId('KUR-02');
$phase6bSubmittedSuggestion = 'Saran informasi ' . bin2hex(random_bytes(6));
$phase6bFormIdentity = phase6Identity();
$phase6bForm = phase2bHttpRequests([[
    'method' => 'GET',
    'path' => '/survei',
    'cookie' => $phase6bFormIdentity['cookie'],
]])[0] ?? [];
$phase6bFormListsActiveInformation = str_contains((string) ($phase6bForm['body'] ?? ''), 'KUR-01')
    && str_contains((string) ($phase6bForm['body'] ?? ''), 'KES-01')
    && str_contains((string) ($phase6bForm['body'] ?? ''), 'KES-03');
check(
    'Form survei menawarkan jalur tanpa kode dan hanya menampilkan layanan INFORMASI aktif',
    $phase6bForm['status'] === 200
        && str_contains((string) $phase6bForm['body'], 'Layanan informasi (tanpa kode lacak)')
        && $phase6bFormListsActiveInformation
);

$phase6bRowsBeforeValid = (int) Db::one('SELECT COUNT(*) AS n FROM information_surveys', [], 'test')['n'];
$phase6bValidResponse = phase6bSubmit('198.51.100.31', (string) $phase6bInformationServiceId, [
    'suggestion' => $phase6bSubmittedSuggestion,
]);
$phase6bRowsAfterValid = (int) Db::one('SELECT COUNT(*) AS n FROM information_surveys', [], 'test')['n'];
$phase6bValidAudit = Db::one(
    "SELECT actor_id, actor_role, action, entity_type, message FROM audit_logs WHERE action = 'information_survey_submitted' ORDER BY id DESC LIMIT 1",
    [],
    'test'
);
check(
    'Kiriman survei INFORMASI sah tersimpan tanpa identitas dan audit tidak menyalin saran',
    $phase6bValidResponse['status'] === 303
        && $phase6bRowsAfterValid === $phase6bRowsBeforeValid + 1
        && ($phase6bValidAudit['actor_id'] ?? null) === null
        && ($phase6bValidAudit['actor_role'] ?? null) === 'GUEST'
        && ($phase6bValidAudit['entity_type'] ?? null) === 'information_surveys'
        && !str_contains((string) ($phase6bValidAudit['message'] ?? ''), $phase6bSubmittedSuggestion)
);

$phase6bInvalidBaseline = (int) Db::one('SELECT COUNT(*) AS n FROM information_surveys', [], 'test')['n'];
$phase6bInvalidServiceId = $phase6bOtherInformationServiceId;
Db::run('UPDATE services SET is_active = 0 WHERE id = ?', [$phase6bInvalidServiceId], 'test');
phase2bAs(null);
$phase6bSharedCsrf = Csrf::token();
$phase6bInvalidServiceResponses = [
    phase6bSubmit('198.51.100.32', (string) $phase6bNonInformationServiceId, [], $phase6bSharedCsrf),
    phase6bSubmit('198.51.100.32', (string) $phase6bInvalidServiceId, [], $phase6bSharedCsrf),
    phase6bSubmit('198.51.100.32', '999999999', [], $phase6bSharedCsrf),
    phase6bSubmit('198.51.100.32', '12x', [], $phase6bSharedCsrf),
];
Db::run('UPDATE services SET is_active = 1 WHERE id = ?', [$phase6bInvalidServiceId], 'test');
$phase6bInvalidServiceSameResponse = true;
foreach ($phase6bInvalidServiceResponses as $response) {
    $phase6bInvalidServiceSameResponse = $phase6bInvalidServiceSameResponse
        && $response['status'] === 422
        && $response['body'] === $phase6bInvalidServiceResponses[0]['body'];
}
check(
    'Layanan non-INFORMASI, nonaktif, tidak ada, dan ID termanipulasi ditolak seragam tanpa baris survei',
    $phase6bInvalidServiceSameResponse
        && (int) Db::one('SELECT COUNT(*) AS n FROM information_surveys', [], 'test')['n'] === $phase6bInvalidBaseline
);

$phase6bValidationBaseline = (int) Db::one('SELECT COUNT(*) AS n FROM information_surveys', [], 'test')['n'];
$phase6bValidationCases = [
    phase6bSubmit('198.51.100.33', (string) $phase6bInformationServiceId, ['speed_rating' => '6']),
    phase6bSubmit('198.51.100.33', (string) $phase6bInformationServiceId, ['speed_rating' => 'teks']),
    phase6bSubmit('198.51.100.33', (string) $phase6bInformationServiceId, ['courtesy_rating' => '']),
    phase6bSubmit('198.51.100.33', (string) $phase6bInformationServiceId, ['suggestion' => str_repeat('x', 1001)]),
    phase6bSubmit('198.51.100.33', (string) $phase6bInformationServiceId, ['suggestion' => "\xC3\x28"]),
    phase6bSubmit('198.51.100.33', (string) $phase6bInformationServiceId, ['_csrf' => 'wrong-token']),
    phase6bSubmit('198.51.100.33', (string) $phase6bInformationServiceId, ['website' => 'bot']),
];
$phase6bValidationPassed = count($phase6bValidationCases) === 7;
foreach ($phase6bValidationCases as $index => $response) {
    $phase6bValidationPassed = $phase6bValidationPassed
        && $response['status'] === ($index === 5 ? 403 : 422);
}
check(
    'Rating, saran panjang/UTF-8, CSRF salah, dan honeypot menolak tanpa menyimpan',
    $phase6bValidationPassed
        && (int) Db::one('SELECT COUNT(*) AS n FROM information_surveys', [], 'test')['n'] === $phase6bValidationBaseline
);

$phase6bQuotaBaseline = (int) Db::one('SELECT COUNT(*) AS n FROM information_surveys', [], 'test')['n'];
$phase6bQuotaResponses = [];
for ($phase6bQuotaAttempt = 0; $phase6bQuotaAttempt < 4; $phase6bQuotaAttempt++) {
    $phase6bQuotaResponses[] = phase6bSubmit(
        '198.51.100.41',
        (string) $phase6bInformationServiceId
    )['status'];
}
$phase6bOtherIpResponse = phase6bSubmit('198.51.100.42', (string) $phase6bInformationServiceId);
$phase6bOtherServiceResponse = phase6bSubmit('198.51.100.41', (string) $phase6bOtherInformationServiceId);
$phase6bQuotaRows = (int) Db::one('SELECT COUNT(*) AS n FROM information_surveys', [], 'test')['n'];
$phase6bQuotaPassed = $phase6bQuotaResponses === [303, 303, 303, 429]
    && $phase6bOtherIpResponse['status'] === 303
    && $phase6bOtherServiceResponse['status'] === 303
    && $phase6bQuotaRows === $phase6bQuotaBaseline + 5
    && Db::one(
        'SELECT ip_hash FROM information_survey_ip_quotas WHERE service_id = ? AND ip_hash = ?',
        [$phase6bInformationServiceId, hash('sha256', '198.51.100.41')],
        'test'
    ) !== null;
check(
    'Kuota INFORMASI membatasi tiga kiriman per IP/layanan per jam dan terpisah menurut IP/layanan',
    $phase6bQuotaPassed,
    json_encode([$phase6bQuotaResponses, $phase6bOtherIpResponse['status'], $phase6bOtherServiceResponse['status']])
);

$phase6bCrossIpBefore = (int) Db::one(
    'SELECT COUNT(*) AS n FROM information_surveys WHERE service_id = ?',
    [$phase6bInformationServiceId],
    'test'
)['n'];
phase6bSubmit('198.51.100.51', (string) $phase6bInformationServiceId);
phase6bSubmit('198.51.100.52', (string) $phase6bInformationServiceId);
$phase6bCrossIpAfter = (int) Db::one(
    'SELECT COUNT(*) AS n FROM information_surveys WHERE service_id = ?',
    [$phase6bInformationServiceId],
    'test'
)['n'];
check(
    'Dua kiriman berurutan dari IP berbeda tersimpan terpisah tanpa duplikasi palsu',
    $phase6bCrossIpAfter === $phase6bCrossIpBefore + 2
);

$phase6bXssSuggestion = '<script>alert("info-survey")</script>';
Db::run(
    'INSERT INTO information_surveys (service_id, speed_rating, clarity_rating, courtesy_rating, suggestion) VALUES (?, ?, ?, ?, ?)',
    [phase6bServiceId('KUR-01'), 2, 2, 2, 'Nilai uji pertama'],
    'test'
);
Db::run(
    'INSERT INTO information_surveys (service_id, speed_rating, clarity_rating, courtesy_rating, suggestion) VALUES (?, ?, ?, ?, ?)',
    [phase6bServiceId('KUR-01'), 4, 4, 4, 'Nilai uji kedua'],
    'test'
);
Db::run(
    'INSERT INTO information_surveys (service_id, speed_rating, clarity_rating, courtesy_rating, suggestion) VALUES (?, ?, ?, ?, ?)',
    [$phase6bInformationServiceId, 2, 4, 5, $phase6bXssSuggestion],
    'test'
);
Db::run(
    'INSERT INTO information_surveys (service_id, speed_rating, clarity_rating, courtesy_rating, suggestion) VALUES (?, ?, ?, ?, ?)',
    [$phase6bInformationServiceId, 4, 2, 3, 'Saran pembanding ' . bin2hex(random_bytes(5))],
    'test'
);
$phase6bCodedApplication = phase6Application('SELESAI', $phase6bInformationServiceId, (int) $phase6bStudent['id']);
phase6AddSurvey($phase6bCodedApplication, 1, 1, 1, 'Catatan berkode ' . bin2hex(random_bytes(5)));
$phase6bAdminIdentity = phase6Identity($phase6bAdmin);
$phase6bAdminSummaryResponse = phase2bHttpRequests([[
    'method' => 'GET',
    'path' => '/admin/survei',
    'cookie' => $phase6bAdminIdentity['cookie'],
]])[0] ?? [];
$phase6bSummaryBody = (string) ($phase6bAdminSummaryResponse['body'] ?? '');
$phase6bInformationStart = strpos($phase6bSummaryBody, '<h2>Survei layanan informasi (tanpa verifikasi)</h2>');
$phase6bInformationSection = $phase6bInformationStart === false
    ? ''
    : substr($phase6bSummaryBody, $phase6bInformationStart);
$phase6bKnownAverage = Db::one(
    'SELECT COUNT(*) AS respondent_count, AVG(speed_rating) AS speed_average FROM information_surveys WHERE service_id = ?',
    [phase6bServiceId('KUR-01')],
    'test'
);
$phase6bExpectedAverage = number_format((float) $phase6bKnownAverage['speed_average'], 2, ',', '.');
$phase6bAuditContainsSuggestion = (int) Db::one(
    'SELECT COUNT(*) AS n FROM audit_logs WHERE action = ? AND message LIKE ?',
    ['information_survey_submitted', '%' . $phase6bSubmittedSuggestion . '%'],
    'test'
)['n'];
check(
    'Rekap INFORMASI terpisah, menghitung rata-rata, menandai nol/sampel kecil, escape XSS dan audit tanpa saran',
    $phase6bAdminSummaryResponse['status'] === 200
        && str_contains($phase6bInformationSection, 'KES-01')
        && (int) $phase6bKnownAverage['respondent_count'] === 2
        && (float) $phase6bKnownAverage['speed_average'] === 3.0
        && str_contains($phase6bInformationSection, 'KUR-01')
        && str_contains($phase6bInformationSection, '2')
        && str_contains($phase6bInformationSection, $phase6bExpectedAverage)
        && str_contains($phase6bInformationSection, 'tanpa verifikasi')
        && str_contains($phase6bInformationSection, 'DATA TERLALU SEDIKIT')
        && str_contains($phase6bInformationSection, '&lt;script&gt;')
        && !str_contains($phase6bInformationSection, $phase6bXssSuggestion)
        && $phase6bAuditContainsSuggestion === 0
);

$phase6bFormulaSuggestions = ['=SUM(1,1)', '+1+1', '-1+1', '@SUM(1,1)'];
foreach ($phase6bFormulaSuggestions as $phase6bFormulaSuggestion) {
    Db::run(
        'INSERT INTO information_surveys (service_id, speed_rating, clarity_rating, courtesy_rating, suggestion) VALUES (?, ?, ?, ?, ?)',
        [$phase6bInformationServiceId, 3, 3, 3, $phase6bFormulaSuggestion],
        'test'
    );
}
$phase6bCsvResponses = phase2bHttpRequests([
    [
        'method' => 'GET',
        'path' => '/admin/survei/ekspor',
        'cookie' => $phase6bAdminIdentity['cookie'],
    ],
    ['method' => 'GET', 'path' => '/admin/survei/ekspor'],
    [
        'method' => 'GET',
        'path' => '/admin/survei/ekspor',
        'cookie' => $phase6RoleIdentities['SISWA']['cookie'],
    ],
]);
$phase6bCsvBody = (string) ($phase6bCsvResponses[0]['body'] ?? '');
$phase6bCsvHeaders = implode("\n", $phase6bCsvResponses[0]['headers'] ?? []);
$phase6bCsvPassed = ($phase6bCsvResponses[0]['status'] ?? 0) === 200
    && ($phase6bCsvResponses[1]['status'] ?? 0) === 302
    && ($phase6bCsvResponses[2]['status'] ?? 0) === 403
    && stripos($phase6bCsvHeaders, 'Content-Type: text/csv') !== false
    && stripos($phase6bCsvHeaders, 'Content-Disposition: attachment; filename="rekap-survei.csv"') !== false
    && str_contains($phase6bCsvBody, 'BERKODE')
    && str_contains($phase6bCsvBody, 'TANPA_VERIFIKASI');
foreach ($phase6bFormulaSuggestions as $phase6bFormulaSuggestion) {
    $phase6bCsvPassed = $phase6bCsvPassed
        && str_contains($phase6bCsvBody, "'" . $phase6bFormulaSuggestion);
}
check(
    'CSV membedakan sumber, menetralkan empat awalan rumus, dan hanya ADMIN yang dapat mengunduh',
    $phase6bCsvPassed,
    substr($phase6bCsvBody, 0, 300)
);

$phase6bFinalInfoCount = (int) Db::one('SELECT COUNT(*) AS n FROM information_surveys', [], 'test')['n'];
phase2bAs($phase6bAdmin);
$phase6bPopulatedDashboard = phase2bRequest('GET', '/admin');
$phase6bDashboardSource = (string) file_get_contents(__DIR__ . '/../app/Views/pages/admin_dashboard.php');
check(
    'Dashboard memakai hitungan INFORMASI dari basis data dan tampilan tidak menanam angka jumlah',
    str_contains($phase6bPopulatedDashboard['body'], 'Jumlah responden: ' . $phase6bFinalInfoCount)
        && str_contains($phase6bDashboardSource, '$informationSurveyCount')
        && !preg_match('/informationSurveyCount\s*=\s*[1-9][0-9]*/', $phase6bDashboardSource)
);

$phase6bRealFormResponse = phase2bHttpRequests([['method' => 'GET', 'path' => '/survei']])[0] ?? [];
$phase6bRealCsrf = '';
$phase6bRealCookie = '';
if (preg_match('/name="_csrf" value="([^"]+)"/', (string) ($phase6bRealFormResponse['body'] ?? ''), $phase6bCsrfMatch) === 1) {
    $phase6bRealCsrf = $phase6bCsrfMatch[1];
}
foreach (($phase6bRealFormResponse['headers'] ?? []) as $phase6bHeader) {
    if (preg_match('/^Set-Cookie:\s*([^=]+)=([^;]+)/i', $phase6bHeader, $phase6bCookieMatch) === 1) {
        $phase6bRealCookie = $phase6bCookieMatch[1] . '=' . $phase6bCookieMatch[2];
        break;
    }
}
$phase6bLoopbackHash = hash('sha256', '127.0.0.1');
Db::run(
    'DELETE FROM information_survey_ip_quotas WHERE service_id = ? AND ip_hash = ?',
    [$phase6bInformationServiceId, $phase6bLoopbackHash],
    'test'
);
$phase6bRealRowsBefore = (int) Db::one(
    'SELECT COUNT(*) AS n FROM information_surveys WHERE service_id = ?',
    [$phase6bInformationServiceId],
    'test'
)['n'];
$phase6bRealPostResponse = phase2bHttpRequests([[
    'method' => 'POST',
    'path' => '/survei',
    'cookie' => $phase6bRealCookie,
    'post' => [
        '_csrf' => $phase6bRealCsrf,
        'survey_type' => 'information',
        'service_id' => (string) $phase6bInformationServiceId,
        'speed_rating' => '4',
        'clarity_rating' => '4',
        'courtesy_rating' => '4',
        'suggestion' => 'Entry point asli',
        'website' => '',
    ],
]])[0] ?? [];
$phase6bRealRowsAfter = (int) Db::one(
    'SELECT COUNT(*) AS n FROM information_surveys WHERE service_id = ?',
    [$phase6bInformationServiceId],
    'test'
)['n'];
$phase6bAdminEntryIdentity = phase6Identity($phase6bAdmin);
$phase6bRealAdminResponses = phase2bHttpRequests([
    ['method' => 'GET', 'path' => '/admin/survei', 'cookie' => $phase6bAdminEntryIdentity['cookie']],
    ['method' => 'GET', 'path' => '/admin', 'cookie' => $phase6bAdminEntryIdentity['cookie']],
]);
$phase6bNormalSurveyResponse = $phase6bRealFormResponse;
$phase6bMissingLoaderResponse = ['status' => 0, 'body' => ''];
$phase6bMissingLoaderPath = tempnam(__DIR__, '.phase6b_missing_loader_');
$phase6bBrokenIndexPath = tempnam(__DIR__ . '/../public', '.phase6b_broken_index_');
if ($phase6bMissingLoaderPath !== false && $phase6bBrokenIndexPath !== false) {
    $phase6bBrokenIndexPathPhp = $phase6bBrokenIndexPath . '.php';
    unlink($phase6bBrokenIndexPath);
    $phase6bIndexContents = (string) file_get_contents(__DIR__ . '/../public/index.php');
    file_put_contents(
        $phase6bMissingLoaderPath,
        "<?php\nspl_autoload_register(static function (string \$class): void {\n"
            . "    if (\$class === 'App\\\\Controllers\\\\SurveyController') {\n"
            . "        throw new Error('Deliberate test: survey controller loading blocked');\n"
            . "    }\n"
            . "});\nrequire_once " . var_export(__DIR__ . '/../app/autoload.php', true) . ";\n"
    );
    $phase6bBrokenIndexContents = str_replace(
        "require __DIR__ . '/../app/autoload.php';",
        'require ' . var_export($phase6bMissingLoaderPath, true) . ';',
        $phase6bIndexContents
    );
    try {
        file_put_contents($phase6bBrokenIndexPathPhp, $phase6bBrokenIndexContents);
        $phase6bMissingLoaderResponse = phase6bHttpRequestUsingIndex($phase6bBrokenIndexPathPhp);
    } finally {
        if (is_file($phase6bMissingLoaderPath)) {
            unlink($phase6bMissingLoaderPath);
        }
        if (is_file($phase6bBrokenIndexPathPhp)) {
            unlink($phase6bBrokenIndexPathPhp);
        }
    }
}
$phase6bFailureDemonstration = $phase6bMissingLoaderPath !== false
    && $phase6bBrokenIndexPath !== false
    && $phase6bMissingLoaderResponse['status'] === 500
    && str_contains($phase6bMissingLoaderResponse['body'], 'Terjadi kesalahan pada server.')
    && !str_contains($phase6bMissingLoaderResponse['body'], 'SurveyController')
    && $phase6bNormalSurveyResponse['status'] === 200;
echo '[FAULT INJECTION] copy dengan autoload SurveyController diblokir: functional assertion status=200 mendapat '
    . $phase6bMissingLoaderResponse['status']
    . ' (GAGAL); entry point asli setelah pemulihan status='
    . $phase6bNormalSurveyResponse['status'] . PHP_EOL;
check(
    'Entry point asli melayani GET/POST survei dan rute ADMIN; probe kelas yang sengaja gagal dimuat terdeteksi lalu pulih',
    $phase6bNormalSurveyResponse['status'] === 200
        && $phase6bRealCsrf !== ''
        && $phase6bRealCookie !== ''
        && $phase6bRealPostResponse['status'] === 303
        && $phase6bRealRowsAfter === $phase6bRealRowsBefore + 1
        && count($phase6bRealAdminResponses) === 2
        && ($phase6bRealAdminResponses[0]['status'] ?? 0) === 200
        && ($phase6bRealAdminResponses[1]['status'] ?? 0) === 200
        && $phase6bFailureDemonstration,
    'probe missing-loader status=' . $phase6bMissingLoaderResponse['status']
        . '; actual GET=' . $phase6bNormalSurveyResponse['status']
);
