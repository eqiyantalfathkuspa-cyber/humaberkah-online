<?php
declare(strict_types=1);

use App\Core\Db;
use App\Policies\Permissions;

function phase8dCheck(string $name, bool $passed, string $detail = ''): void
{
    global $phase8dFailures;
    echo ($passed ? '[LOLOS] ' : '[GAGAL] ') . $name . ($passed || $detail === '' ? '' : ' :: ' . $detail) . PHP_EOL;
    if (!$passed) {
        $phase8dFailures++;
    }
}

function phase8dRunMigrationProcess(string $directory, string $database = 'test'): array
{
    $process = proc_open([
        PHP_BINARY,
        __DIR__ . '/../bin/jalankan-migrasi.php',
        $database,
        $directory,
    ], [
        0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w'],
    ], $pipes, __DIR__);
    if (!is_resource($process)) {
        return [1, 'Tidak dapat memulai migrator untuk fixture.'];
    }
    fclose($pipes[0]);
    $stdout = (string) stream_get_contents($pipes[1]);
    $stderr = (string) stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    return [proc_close($process), $stdout . $stderr];
}

function phase8dUser(string $role, string $identifier, string $name): array
{
    $authToken = bin2hex(random_bytes(32));
    Db::run(
        'INSERT INTO users (role_id, identifier, full_name, password_hash, is_active, auth_token) VALUES ((SELECT id FROM roles WHERE code = ?), ?, ?, ?, 1, ?)',
        [$role, $identifier, $name, password_hash(bin2hex(random_bytes(24)), PASSWORD_DEFAULT), $authToken],
        'test'
    );
    $userId = (int) Db::one(
        'SELECT id FROM users WHERE role_id = (SELECT id FROM roles WHERE code = ?) AND identifier = ?',
        [$role, $identifier],
        'test'
    )['id'];
    return ['id' => $userId, 'role' => $role, 'identifier' => $identifier, 'name' => $name, 'token' => $authToken];
}

function phase8dHttpIdentity(?array $user): array
{
    $previousId = session_id();
    session_write_close();
    $newId = session_create_id();
    session_id($newId);
    session_start();
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
    $csrf = App\Core\Csrf::token();
    session_write_close();
    session_id($previousId);
    session_start();
    return ['cookie' => session_name() . '=' . $newId, 'csrf' => $csrf];
}

function phase8dForeignCsrfToken(): string
{
    $currentId = session_id();
    session_write_close();
    session_id(session_create_id());
    session_start();
    $_SESSION = [];
    $token = App\Core\Csrf::token();
    session_write_close();
    session_id($currentId);
    session_start();
    return $token;
}

function phase8dHttpRequests(array $requests): array
{
    $server = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);
    if ($server === false) {
        return [];
    }
    $address = stream_socket_get_name($server, false);
    fclose($server);
    if ($address === false) {
        return [];
    }
    $process = proc_open(
        [PHP_BINARY, '-S', $address, __DIR__ . '/../public/index.php'],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        __DIR__
    );
    if (!is_resource($process)) {
        return [];
    }
    fclose($pipes[0]);
    $responses = [];
    try {
        foreach ($requests as $request) {
            $body = $request['method'] === 'POST' ? http_build_query($request['post'] ?? []) : '';
            $headers = [
                'Host: ' . $address,
                'Connection: close',
            ];
            if (isset($request['cookie'])) {
                $headers[] = 'Cookie: ' . $request['cookie'];
            }
            if ($request['method'] === 'POST') {
                $headers[] = 'Content-Type: application/x-www-form-urlencoded';
            }
            $socket = false;
            for ($attempt = 0; $attempt < 20 && $socket === false; $attempt++) {
                $socket = @stream_socket_client('tcp://' . $address, $connectError, $connectMessage, 1);
                if ($socket === false) {
                    usleep(25000);
                }
            }
            if ($socket === false) {
                $responses[] = ['status' => 0, 'headers' => [], 'body' => ''];
                continue;
            }
            stream_set_timeout($socket, 5);
            fwrite(
                $socket,
                $request['method'] . ' ' . $request['path'] . " HTTP/1.1\r\n"
                    . implode("\r\n", $headers) . "\r\n"
                    . 'Content-Length: ' . strlen($body) . "\r\n\r\n"
                    . $body
            );
            $rawResponse = (string) stream_get_contents($socket);
            fclose($socket);
            [$rawHeaders, $responseBody] = array_pad(explode("\r\n\r\n", $rawResponse, 2), 2, '');
            $headerLines = explode("\r\n", $rawHeaders);
            preg_match('/^HTTP\/\S+\s+(\d+)/', (string) ($headerLines[0] ?? ''), $statusMatch);
            $responses[] = [
                'status' => (int) ($statusMatch[1] ?? 0),
                'headers' => $headerLines,
                'body' => $responseBody,
            ];
        }
    } finally {
        proc_terminate($process);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);
    }
    return $responses;
}

$phase8dFailures = 0;
$phase8dMigration = '014_madani_before_after.sql';
$phase8dMigrationBefore = Db::one(
    'SELECT status, checksum, statement_count, last_completed_statement FROM schema_migration_runs WHERE migration_name = ?',
    [$phase8dMigration],
    'test'
);
[$phase8dRerunCode, $phase8dRerunOutput] = phase8dRunMigrationProcess(__DIR__ . '/../database/migrations');
$phase8dMigrationAfter = Db::one(
    'SELECT status, checksum, statement_count, last_completed_statement FROM schema_migration_runs WHERE migration_name = ?',
    [$phase8dMigration],
    'test'
);
$phase8dEarlierRuns = Db::all(
    'SELECT migration_name, status FROM schema_migration_runs WHERE migration_name IN (?, ?, ?, ?, ?)',
    ['009_surveys.sql', '010_information_surveys.sql', '011_evidence_foundation.sql', '012_madani_evaluations.sql', '013_madani_rtl.sql'],
    'test'
);
$phase8dEarlierValid = count($phase8dEarlierRuns) === 5;
foreach ($phase8dEarlierRuns as $run) {
    $phase8dEarlierValid = $phase8dEarlierValid && $run['status'] === 'APPLIED';
}
phase8dCheck(
    'Migrasi 014 idempoten dan signature 009–013 tetap APPLIED',
    $phase8dMigrationBefore !== null
        && $phase8dMigrationBefore === $phase8dMigrationAfter
        && (int) $phase8dMigrationAfter['statement_count'] === 1
        && (int) $phase8dMigrationAfter['last_completed_statement'] === 1
        && $phase8dRerunCode === 0
        && $phase8dEarlierValid,
    $phase8dRerunOutput
);

$phase8dDriftDetected = false;
$phase8dDriftRestored = false;
Db::pdo('test')->exec('ALTER TABLE madani_before_after MODIFY COLUMN title VARCHAR(199) NOT NULL');
try {
    [$phase8dDriftCode, $phase8dDriftOutput] = phase8dRunMigrationProcess(__DIR__ . '/../database/migrations');
    $phase8dDriftDetected = $phase8dDriftCode !== 0
        && str_contains($phase8dDriftOutput, 'skema aktual berbeda');
} finally {
    Db::pdo('test')->exec('ALTER TABLE madani_before_after MODIFY COLUMN title VARCHAR(200) NOT NULL');
    [$phase8dRestoreCode, $phase8dRestoreOutput] = phase8dRunMigrationProcess(__DIR__ . '/../database/migrations');
    $phase8dDriftRestored = $phase8dRestoreCode === 0;
}
phase8dCheck(
    'Signature 014 menolak drift dan lulus kembali setelah pemulihan',
    $phase8dDriftDetected && $phase8dDriftRestored,
    'drift=' . ($phase8dDriftDetected ? 'ditolak' : 'tidak ditolak')
        . '; restore=' . ($phase8dDriftRestored ? 'lulus' : 'gagal')
        . '; ' . $phase8dRestoreOutput
);

$phase8dToken = bin2hex(random_bytes(6));
$phase8dAdmin = phase8dUser('ADMIN', 'phase8d-admin-' . $phase8dToken, 'Admin Before After Fiktif');
$phase8dSiswa = phase8dUser('SISWA', 'phase8d-siswa-' . $phase8dToken, 'Siswa Before After Fiktif');
$phase8dGuru = phase8dUser('GURU', 'phase8d-guru-' . $phase8dToken, 'Guru Before After Fiktif');
$phase8dIdentities = [
    'GUEST' => phase8dHttpIdentity(null),
    'SISWA' => phase8dHttpIdentity($phase8dSiswa),
    'GURU' => phase8dHttpIdentity($phase8dGuru),
    'ADMIN' => phase8dHttpIdentity($phase8dAdmin),
];
$phase8dServices = Db::all('SELECT id, code FROM services ORDER BY code LIMIT 2', [], 'test');
$phase8dServiceId = (int) ($phase8dServices[0]['id'] ?? 0);
$phase8dOtherServiceId = (int) ($phase8dServices[1]['id'] ?? 0);
$phase8dBasePayload = [
    '_csrf' => $phase8dIdentities['ADMIN']['csrf'],
    'service_id' => (string) $phase8dServiceId,
    'rtl_id' => '',
    'title' => 'Catatan Before After ' . $phase8dToken,
    'before_text' => 'Kondisi sebelum fiktif ' . $phase8dToken,
    'before_observed_on' => date('Y-m-d', strtotime('-2 days')),
    'after_text' => 'Kondisi sesudah fiktif ' . $phase8dToken,
    'after_observed_on' => date('Y-m-d', strtotime('-1 day')),
    'source_note' => 'Sumber pengamatan fiktif ' . $phase8dToken,
    'corrects_before_after_id' => '',
];

$phase8dIdentityMap = [
    'GUEST' => $phase8dIdentities['GUEST'],
    'SISWA' => $phase8dIdentities['SISWA'],
    'GURU' => $phase8dIdentities['GURU'],
    'ADMIN' => $phase8dIdentities['ADMIN'],
];
$phase8dRoutePaths = ['/admin/dampak', '/admin/dampak/987654321'];
$phase8dMatrixRequests = [];
$phase8dMatrixExpected = [];
foreach (['GUEST', 'SISWA', 'GURU', 'ADMIN'] as $role) {
    foreach ($phase8dRoutePaths as $path) {
        $phase8dMatrixRequests[] = ['method' => 'GET', 'path' => $path, 'cookie' => $phase8dIdentityMap[$role]['cookie']];
        $phase8dMatrixExpected[] = $role === 'ADMIN' ? ($path === '/admin/dampak' ? 200 : 404) : ($role === 'GUEST' ? 302 : 403);
    }
    $phase8dMatrixRequests[] = [
        'method' => 'POST',
        'path' => '/admin/dampak',
        'cookie' => $phase8dIdentityMap[$role]['cookie'],
        'post' => ['_csrf' => $phase8dIdentityMap[$role]['csrf']],
    ];
    $phase8dMatrixExpected[] = $role === 'ADMIN' ? 422 : ($role === 'GUEST' ? 302 : 403);
}
$phase8dMatrixResponses = phase8dHttpRequests($phase8dMatrixRequests);
$phase8dMatrixPassed = count($phase8dMatrixResponses) === count($phase8dMatrixExpected);
foreach ($phase8dMatrixExpected as $index => $expectedStatus) {
    $actualStatus = (int) ($phase8dMatrixResponses[$index]['status'] ?? 0);
    $phase8dMatrixPassed = $phase8dMatrixPassed && $actualStatus === $expectedStatus;
}
$phase8dMatrixPaths = ['/admin/dampak', '/admin/dampak/{id}', 'POST /admin/dampak'];
echo '[MATRIKS 8D] Rute | GUEST | SISWA | GURU | ADMIN' . PHP_EOL;
foreach ($phase8dMatrixPaths as $routeIndex => $routeLabel) {
    $statuses = [];
    foreach (['GUEST', 'SISWA', 'GURU', 'ADMIN'] as $roleIndex => $role) {
        $statuses[] = (string) ($phase8dMatrixResponses[$roleIndex * 3 + $routeIndex]['status'] ?? 0);
    }
    echo '[MATRIKS 8D] ' . $routeLabel . ' | ' . implode(' | ', $statuses) . PHP_EOL;
}
phase8dCheck('Matriks akses 8D pada rute daftar, detail, dan pencatatan', $phase8dMatrixPassed);

$phase8dForeignCsrf = phase8dForeignCsrfToken();
$phase8dCsrfRequests = [];
foreach (['missing', 'wrong', 'foreign'] as $csrfCase) {
    $payload = $phase8dBasePayload;
    $payload['title'] .= ' csrf-' . $csrfCase;
    if ($csrfCase === 'missing') {
        unset($payload['_csrf']);
    } elseif ($csrfCase === 'wrong') {
        $payload['_csrf'] = 'invalid-' . $phase8dToken;
    } else {
        $payload['_csrf'] = $phase8dForeignCsrf;
    }
    $phase8dCsrfRequests[] = [
        'method' => 'POST',
        'path' => '/admin/dampak',
        'cookie' => $phase8dIdentities['ADMIN']['cookie'],
        'post' => $payload,
    ];
}
$phase8dCsrfResponses = phase8dHttpRequests($phase8dCsrfRequests);
phase8dCheck(
    'POST before/after menolak token CSRF kosong, salah, dan milik sesi lain',
    count($phase8dCsrfResponses) === 3
        && count(array_filter($phase8dCsrfResponses, static fn (array $response): bool => (int) $response['status'] === 403)) === 3
);

$phase8dBasePayload['title'] = '<script>"before-' . $phase8dToken . '"</script>';
$phase8dBasePayload['before_text'] = '<img src=x onerror="before-' . $phase8dToken . '">';
$phase8dBasePayload['after_text'] = '<script>after-' . $phase8dToken . '</script>';
$phase8dCreateResponse = phase8dHttpRequests([[
    'method' => 'POST',
    'path' => '/admin/dampak',
    'cookie' => $phase8dIdentities['ADMIN']['cookie'],
    'post' => $phase8dBasePayload,
]])[0] ?? [];
$phase8dRecordId = (int) (Db::one(
    'SELECT id FROM madani_before_after WHERE title = ?',
    [$phase8dBasePayload['title']],
    'test'
)['id'] ?? 0);
$phase8dCreatedRecord = Db::one(
    'SELECT service_id, rtl_id, title, before_text, after_text, source_note, created_by FROM madani_before_after WHERE id = ?',
    [$phase8dRecordId],
    'test'
);
$phase8dAudit = Db::one(
    'SELECT actor_id, action, entity_type, entity_id, message FROM audit_logs WHERE entity_type = ? AND entity_id = ? ORDER BY id DESC LIMIT 1',
    ['madani_before_after', $phase8dRecordId],
    'test'
);
$phase8dCreatedAuditMetadata = json_decode((string) ($phase8dAudit['message'] ?? ''), true);
$phase8dListAndDetail = phase8dHttpRequests([
    ['method' => 'GET', 'path' => '/admin/dampak', 'cookie' => $phase8dIdentities['ADMIN']['cookie']],
    ['method' => 'GET', 'path' => '/admin/dampak/' . $phase8dRecordId, 'cookie' => $phase8dIdentities['ADMIN']['cookie']],
]);
$phase8dEscaped = count($phase8dListAndDetail) === 2;
foreach ($phase8dListAndDetail as $page) {
    $phase8dEscaped = $phase8dEscaped
        && str_contains((string) $page['body'], '&lt;script&gt;')
        && !str_contains((string) $page['body'], '<script>after-' . $phase8dToken);
}
phase8dCheck(
    'Catatan tanpa RTL tercatat dengan aktor, audit metadata saja, dan XSS escaped pada daftar/detail',
    (int) ($phase8dCreateResponse['status'] ?? 0) === 303
        && $phase8dRecordId > 0
        && (int) ($phase8dCreatedRecord['created_by'] ?? 0) === (int) $phase8dAdmin['id']
        && $phase8dCreatedRecord['rtl_id'] === null
        && (int) ($phase8dAudit['actor_id'] ?? 0) === (int) $phase8dAdmin['id']
        && ($phase8dAudit['action'] ?? '') === 'before_after_created'
        && ($phase8dAudit['entity_type'] ?? '') === 'madani_before_after'
        && (int) ($phase8dAudit['entity_id'] ?? 0) === $phase8dRecordId
        && is_array($phase8dCreatedAuditMetadata)
        && array_keys($phase8dCreatedAuditMetadata) === ['service_id', 'rtl_id', 'corrects_before_after_id']
        && !str_contains((string) ($phase8dAudit['message'] ?? ''), $phase8dBasePayload['title'])
        && $phase8dEscaped
);

$phase8dCorrectionPayload = array_replace($phase8dBasePayload, [
    '_csrf' => $phase8dIdentities['ADMIN']['csrf'],
    'title' => 'Koreksi catatan ' . $phase8dToken,
    'before_text' => 'Narasi koreksi sebelum ' . $phase8dToken,
    'after_text' => 'Narasi koreksi sesudah ' . $phase8dToken,
    'corrects_before_after_id' => (string) $phase8dRecordId,
]);
$phase8dOriginalBeforeCorrection = Db::one(
    'SELECT * FROM madani_before_after WHERE id = ?',
    [$phase8dRecordId],
    'test'
);
$phase8dCorrectionResponse = phase8dHttpRequests([[
    'method' => 'POST',
    'path' => '/admin/dampak',
    'cookie' => $phase8dIdentities['ADMIN']['cookie'],
    'post' => $phase8dCorrectionPayload,
]])[0] ?? [];
$phase8dCorrectionId = (int) (Db::one(
    'SELECT id FROM madani_before_after WHERE title = ?',
    [$phase8dCorrectionPayload['title']],
    'test'
)['id'] ?? 0);
$phase8dOriginalAfterCorrection = Db::one(
    'SELECT * FROM madani_before_after WHERE id = ?',
    [$phase8dRecordId],
    'test'
);
$phase8dCorrectionPages = phase8dHttpRequests([
    ['method' => 'GET', 'path' => '/admin/dampak', 'cookie' => $phase8dIdentities['ADMIN']['cookie']],
    ['method' => 'GET', 'path' => '/admin/dampak/' . $phase8dRecordId, 'cookie' => $phase8dIdentities['ADMIN']['cookie']],
    ['method' => 'GET', 'path' => '/admin/dampak/' . $phase8dCorrectionId, 'cookie' => $phase8dIdentities['ADMIN']['cookie']],
]);
phase8dCheck(
    'Koreksi append-only mempertahankan catatan lama dan menampilkan penanda dua arah',
    (int) ($phase8dCorrectionResponse['status'] ?? 0) === 303
        && $phase8dCorrectionId > 0
        && $phase8dOriginalBeforeCorrection === $phase8dOriginalAfterCorrection
        && count($phase8dCorrectionPages) === 3
        && str_contains((string) $phase8dCorrectionPages[1]['body'], 'Dikoreksi oleh #' . $phase8dCorrectionId)
        && str_contains((string) $phase8dCorrectionPages[2]['body'], 'Mengoreksi')
);

$phase8dEvaluationSummary = 'Evaluasi dampak fiktif ' . $phase8dToken;
Db::run(
    'INSERT INTO madani_evaluations (service_id, period_start, period_end, summary, created_by) VALUES (?, ?, ?, ?, ?)',
    [$phase8dServiceId, date('Y-m-d', strtotime('-10 days')), date('Y-m-d', strtotime('-10 days')), $phase8dEvaluationSummary, (int) $phase8dAdmin['id']],
    'test'
);
$phase8dEvaluationId = (int) Db::one(
    'SELECT id FROM madani_evaluations WHERE summary = ?',
    [$phase8dEvaluationSummary],
    'test'
)['id'];
$phase8dRtlNote = 'RTL completed for before-after test';
Db::run(
    'INSERT INTO madani_rtl (evaluation_id, action_description, responsible_name, due_date, created_by) VALUES (?, ?, ?, ?, ?)',
    [$phase8dEvaluationId, 'RTL fiktif selesai', 'Penanggung jawab fiktif', date('Y-m-d', strtotime('+10 years')), (int) $phase8dAdmin['id']],
    'test'
);
$phase8dRtlId = (int) Db::one(
    'SELECT id FROM madani_rtl WHERE evaluation_id = ? AND action_description = ?',
    [$phase8dEvaluationId, 'RTL fiktif selesai'],
    'test'
)['id'];
Db::run('INSERT INTO madani_rtl_status_log (rtl_id, status, changed_by) VALUES (?, ?, ?)', [$phase8dRtlId, 'DIRENCANAKAN', (int) $phase8dAdmin['id']], 'test');
Db::run('INSERT INTO madani_rtl_status_log (rtl_id, status, changed_by) VALUES (?, ?, ?)', [$phase8dRtlId, 'SELESAI', (int) $phase8dAdmin['id']], 'test');
$phase8dRtlPayload = array_replace($phase8dBasePayload, [
    '_csrf' => $phase8dIdentities['ADMIN']['csrf'],
    'title' => 'Catatan dengan RTL selesai ' . $phase8dToken,
    'rtl_id' => (string) $phase8dRtlId,
]);
$phase8dRtlResponse = phase8dHttpRequests([[
    'method' => 'POST',
    'path' => '/admin/dampak',
    'cookie' => $phase8dIdentities['ADMIN']['cookie'],
    'post' => $phase8dRtlPayload,
]])[0] ?? [];
$phase8dRtlRecord = Db::one(
    'SELECT id, rtl_id, service_id FROM madani_before_after WHERE title = ?',
    [$phase8dRtlPayload['title']],
    'test'
);
phase8dCheck(
    'Catatan dengan RTL berstatus terbaru SELESAI pada layanan sama diterima',
    (int) ($phase8dRtlResponse['status'] ?? 0) === 303
        && (int) ($phase8dRtlRecord['rtl_id'] ?? 0) === $phase8dRtlId
        && (int) ($phase8dRtlRecord['service_id'] ?? 0) === $phase8dServiceId
);

$phase8dOtherEvaluationSummary = 'Evaluasi layanan lain ' . $phase8dToken;
Db::run(
    'INSERT INTO madani_evaluations (service_id, period_start, period_end, summary, created_by) VALUES (?, ?, ?, ?, ?)',
    [$phase8dOtherServiceId, date('Y-m-d', strtotime('-10 days')), date('Y-m-d', strtotime('-10 days')), $phase8dOtherEvaluationSummary, (int) $phase8dAdmin['id']],
    'test'
);
$phase8dOtherEvaluationId = (int) Db::one(
    'SELECT id FROM madani_evaluations WHERE summary = ?',
    [$phase8dOtherEvaluationSummary],
    'test'
)['id'];
Db::run(
    'INSERT INTO madani_rtl (evaluation_id, action_description, responsible_name, due_date, created_by) VALUES (?, ?, ?, ?, ?)',
    [$phase8dOtherEvaluationId, 'RTL layanan lain selesai', 'Penanggung jawab lain', date('Y-m-d', strtotime('+10 years')), (int) $phase8dAdmin['id']],
    'test'
);
$phase8dOtherRtlId = (int) Db::one(
    'SELECT id FROM madani_rtl WHERE evaluation_id = ? AND action_description = ?',
    [$phase8dOtherEvaluationId, 'RTL layanan lain selesai'],
    'test'
)['id'];
Db::run('INSERT INTO madani_rtl_status_log (rtl_id, status, changed_by) VALUES (?, ?, ?)', [$phase8dOtherRtlId, 'SELESAI', (int) $phase8dAdmin['id']], 'test');
$phase8dPendingEvaluationSummary = 'Evaluasi RTL belum selesai ' . $phase8dToken;
Db::run(
    'INSERT INTO madani_evaluations (service_id, period_start, period_end, summary, created_by) VALUES (?, ?, ?, ?, ?)',
    [$phase8dServiceId, date('Y-m-d', strtotime('-10 days')), date('Y-m-d', strtotime('-10 days')), $phase8dPendingEvaluationSummary, (int) $phase8dAdmin['id']],
    'test'
);
$phase8dPendingEvaluationId = (int) Db::one(
    'SELECT id FROM madani_evaluations WHERE summary = ?',
    [$phase8dPendingEvaluationSummary],
    'test'
)['id'];
Db::run(
    'INSERT INTO madani_rtl (evaluation_id, action_description, responsible_name, due_date, created_by) VALUES (?, ?, ?, ?, ?)',
    [$phase8dPendingEvaluationId, 'RTL belum selesai', 'Penanggung jawab belum selesai', date('Y-m-d', strtotime('+10 years')), (int) $phase8dAdmin['id']],
    'test'
);
$phase8dPendingRtlId = (int) Db::one(
    'SELECT id FROM madani_rtl WHERE evaluation_id = ? AND action_description = ?',
    [$phase8dPendingEvaluationId, 'RTL belum selesai'],
    'test'
)['id'];
Db::run('INSERT INTO madani_rtl_status_log (rtl_id, status, changed_by) VALUES (?, ?, ?)', [$phase8dPendingRtlId, 'DIRENCANAKAN', (int) $phase8dAdmin['id']], 'test');

$phase8dInvalidPayloads = [];
$phase8dInvalidPayloads['missing service'] = array_replace($phase8dBasePayload, ['title' => 'invalid service ' . $phase8dToken, 'service_id' => '999999999']);
$phase8dInvalidPayloads['missing RTL'] = array_replace($phase8dBasePayload, ['title' => 'missing RTL ' . $phase8dToken, 'rtl_id' => '999999999']);
$phase8dInvalidPayloads['RTL incomplete'] = array_replace($phase8dBasePayload, ['title' => 'RTL incomplete ' . $phase8dToken, 'rtl_id' => (string) $phase8dPendingRtlId]);
$phase8dInvalidPayloads['RTL different service'] = array_replace($phase8dBasePayload, ['title' => 'RTL different service ' . $phase8dToken, 'rtl_id' => (string) $phase8dOtherRtlId]);
$phase8dInvalidPayloads['invalid date'] = array_replace($phase8dBasePayload, ['title' => 'invalid date ' . $phase8dToken, 'before_observed_on' => '2026-02-30']);
$phase8dInvalidPayloads['date order'] = array_replace($phase8dBasePayload, ['title' => 'date order ' . $phase8dToken, 'before_observed_on' => date('Y-m-d', strtotime('-1 day')), 'after_observed_on' => date('Y-m-d', strtotime('-2 days'))]);
$phase8dInvalidPayloads['future after date'] = array_replace($phase8dBasePayload, ['title' => 'future date ' . $phase8dToken, 'after_observed_on' => date('Y-m-d', strtotime('+1 day'))]);
$phase8dInvalidPayloads['empty title'] = array_replace($phase8dBasePayload, ['title' => 'empty title ' . $phase8dToken, 'title' => '   ']);
$phase8dInvalidPayloads['empty before narrative'] = array_replace($phase8dBasePayload, ['title' => 'empty before ' . $phase8dToken, 'before_text' => ' ']);
$phase8dInvalidPayloads['empty after narrative'] = array_replace($phase8dBasePayload, ['title' => 'empty after ' . $phase8dToken, 'after_text' => ' ']);
$phase8dInvalidPayloads['empty source'] = array_replace($phase8dBasePayload, ['title' => 'empty source ' . $phase8dToken, 'source_note' => ' ']);
$phase8dInvalidPayloads['title bound plus one'] = array_replace($phase8dBasePayload, ['title' => str_repeat('t', 201)]);
$phase8dInvalidPayloads['multibyte title bound plus one'] = array_replace($phase8dBasePayload, ['title' => str_repeat('é', 201)]);
$phase8dInvalidPayloads['narrative bound plus one'] = array_replace($phase8dBasePayload, ['title' => 'narrative overflow ' . $phase8dToken, 'before_text' => str_repeat('n', 2001)]);
$phase8dInvalidPayloads['source bound plus one'] = array_replace($phase8dBasePayload, ['title' => 'source overflow ' . $phase8dToken, 'source_note' => str_repeat('s', 501)]);
$phase8dInvalidPayloads['invalid UTF-8'] = array_replace($phase8dBasePayload, ['title' => 'invalid utf8 ' . $phase8dToken, 'after_text' => "\xC3\x28"]);
$phase8dInvalidPayloads['missing correction'] = array_replace($phase8dBasePayload, ['title' => 'missing correction ' . $phase8dToken, 'corrects_before_after_id' => '999999999']);
$phase8dInvalidPayloads['cross-service correction'] = array_replace($phase8dBasePayload, ['title' => 'cross correction ' . $phase8dToken, 'service_id' => (string) $phase8dOtherServiceId, 'corrects_before_after_id' => (string) $phase8dRecordId]);
$phase8dInvalidRequests = [];
foreach ($phase8dInvalidPayloads as $caseName => $payload) {
    $payload['_csrf'] = $phase8dIdentities['ADMIN']['csrf'];
    $phase8dInvalidRequests[] = [
        'method' => 'POST',
        'path' => '/admin/dampak',
        'cookie' => $phase8dIdentities['ADMIN']['cookie'],
        'post' => $payload,
    ];
}
$phase8dInvalidResponses = phase8dHttpRequests($phase8dInvalidRequests);
$phase8dAllInvalidRejected = count($phase8dInvalidResponses) === count($phase8dInvalidPayloads);
foreach ($phase8dInvalidResponses as $response) {
    $phase8dAllInvalidRejected = $phase8dAllInvalidRejected && (int) $response['status'] === 422;
}
$phase8dInvalidRecordsCreated = 0;
foreach ($phase8dInvalidPayloads as $invalidPayload) {
    $phase8dInvalidRecordsCreated += (int) Db::one(
        'SELECT COUNT(*) AS n FROM madani_before_after WHERE created_by = ? AND title = ?',
        [(int) $phase8dAdmin['id'], $invalidPayload['title']],
        'test'
    )['n'];
}
phase8dCheck(
    'Validasi menolak identitas relasi, tanggal, teks kosong/berlebih/multibyte/UTF-8, dan koreksi lintas layanan',
    $phase8dAllInvalidRejected && $phase8dInvalidRecordsCreated === 0
);

$phase8dBoundaryPayload = array_replace($phase8dBasePayload, [
    '_csrf' => $phase8dIdentities['ADMIN']['csrf'],
    'title' => str_repeat('b', 200),
    'before_text' => str_repeat('a', 2000),
    'after_text' => str_repeat('é', 2000),
    'source_note' => str_repeat('s', 500),
]);
$phase8dBoundaryResponse = phase8dHttpRequests([[
    'method' => 'POST',
    'path' => '/admin/dampak',
    'cookie' => $phase8dIdentities['ADMIN']['cookie'],
    'post' => $phase8dBoundaryPayload,
]])[0] ?? [];
phase8dCheck('Batas teks tepat diterima untuk judul, narasi multibyte, dan sumber', (int) ($phase8dBoundaryResponse['status'] ?? 0) === 303);

$phase8dForeignKeyBase = [
    'service_id' => $phase8dServiceId,
    'rtl_id' => null,
    'title' => 'FK probe ' . $phase8dToken,
    'before_text' => 'Before',
    'before_observed_on' => date('Y-m-d'),
    'after_text' => 'After',
    'after_observed_on' => date('Y-m-d'),
    'source_note' => 'Source',
    'corrects_before_after_id' => null,
    'created_by' => (int) $phase8dAdmin['id'],
];
$phase8dForeignKeyRejected = [];
foreach ([
    'service_id' => 999999999,
    'rtl_id' => 999999999,
    'created_by' => 999999999,
    'corrects_before_after_id' => 999999999,
] as $foreignField => $foreignValue) {
    $candidate = array_replace($phase8dForeignKeyBase, [$foreignField => $foreignValue]);
    try {
        Db::run(
            'INSERT INTO madani_before_after (service_id, rtl_id, title, before_text, before_observed_on, after_text, after_observed_on, source_note, corrects_before_after_id, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            array_values($candidate),
            'test'
        );
        $phase8dForeignKeyRejected[] = false;
    } catch (PDOException) {
        $phase8dForeignKeyRejected[] = true;
    }
}
phase8dCheck('FK menolak layanan, RTL, pengguna, dan catatan koreksi yang tidak ada', $phase8dForeignKeyRejected === [true, true, true, true]);

$phase8dNoRouteResponse = phase8dHttpRequests([
    ['method' => 'GET', 'path' => '/admin/dampak/' . $phase8dRecordId . '/hapus', 'cookie' => $phase8dIdentities['ADMIN']['cookie']],
    ['method' => 'POST', 'path' => '/admin/dampak/' . $phase8dRecordId, 'cookie' => $phase8dIdentities['ADMIN']['cookie'], 'post' => ['_csrf' => $phase8dIdentities['ADMIN']['csrf']]],
    ['method' => 'GET', 'path' => '/admin/dampak?service_id=999999999', 'cookie' => $phase8dIdentities['ADMIN']['cookie']],
    ['method' => 'GET', 'path' => '/admin/dampak?rtl_id=999999999', 'cookie' => $phase8dIdentities['ADMIN']['cookie']],
]);
$phase8dControllerSource = (string) file_get_contents(__DIR__ . '/../app/Controllers/BeforeAfterController.php');
$phase8dViewSource = (string) file_get_contents(__DIR__ . '/../app/Views/pages/admin_before_after.php')
    . (string) file_get_contents(__DIR__ . '/../app/Views/pages/admin_before_after_detail.php');
$phase8dNoMutation = preg_match('/(?:UPDATE|DELETE\s+FROM)\s+madani_before_after/i', $phase8dControllerSource) !== 1;
$phase8dNoMetrics = preg_match('/(persentase|skor|selisih|impact_score|percentage)/i', $phase8dControllerSource . $phase8dViewSource) !== 1;
$phase8dEmptyPage = '';
ob_start();
App\Core\View::render('pages/admin_before_after', [
    'title' => 'Catatan Before/After',
    'services' => [],
    'rtls' => [],
    'filterRtls' => [],
    'records' => [],
    'correctableRecords' => [],
    'formValues' => [],
    'selectedServiceId' => null,
    'selectedRtlId' => null,
    'filterError' => false,
    'error' => '',
    'titleMaxLength' => 200,
    'narrativeMaxLength' => 2000,
    'sourceMaxLength' => 500,
]);
$phase8dEmptyPage = (string) ob_get_clean();
$phase8dNoStorePage = phase8dHttpRequests([[
    'method' => 'GET',
    'path' => '/admin/dampak',
    'cookie' => $phase8dIdentities['ADMIN']['cookie'],
]])[0] ?? [];
phase8dCheck(
    'Append-only tanpa rute edit/hapus, filter tervalidasi, tidak ada metrik, empty state tersedia',
    count($phase8dNoRouteResponse) === 4
        && (int) $phase8dNoRouteResponse[0]['status'] === 404
        && (int) $phase8dNoRouteResponse[1]['status'] === 405
        && (int) $phase8dNoRouteResponse[2]['status'] === 422
        && (int) $phase8dNoRouteResponse[3]['status'] === 422
        && $phase8dNoMutation
        && $phase8dNoMetrics
        && str_contains($phase8dEmptyPage, 'BELUM ADA DATA')
        && preg_match('/Cache-Control:\s*no-store/i', implode("\n", $phase8dNoStorePage['headers'] ?? [])) === 1
);

$phase8dRtlDetail = phase8dHttpRequests([[
    'method' => 'GET',
    'path' => '/admin/rtl/' . $phase8dRtlId,
    'cookie' => $phase8dIdentities['ADMIN']['cookie'],
]])[0] ?? [];
$phase8dPermissionMatch = Permissions::routeRoles('admin.impact.view') === ['ADMIN']
    && Permissions::routeRoles('admin.impact.create') === ['ADMIN']
    && str_contains((string) $phase8dRtlDetail['body'], '/admin/dampak?rtl_id=' . $phase8dRtlId);
phase8dCheck('Permissions ADMIN-only sinkron dan detail RTL menautkan catatan layanan', $phase8dPermissionMatch);

$phase8dMutationPath = tempnam(__DIR__, '.before_after_mutation_');
$phase8dMutationScriptPath = is_string($phase8dMutationPath) ? $phase8dMutationPath . '.php' : '';
$phase8dMutationOutput = '';
$phase8dMutationRecovered = false;
if (is_string($phase8dMutationPath) && $phase8dMutationScriptPath !== '') {
    $sourcePath = __DIR__ . '/../app/Controllers/BeforeAfterController.php';
    $mutatedSource = str_replace(
        '$before <= $after',
        'true',
        (string) file_get_contents($sourcePath),
        $mutationCount
    );
    $mutationPath = $phase8dMutationPath . '.controller.php';
    $childSource = "<?php\n"
        . "require " . var_export(__DIR__ . '/../app/autoload.php', true) . ";\n"
        . "require " . var_export($mutationPath, true) . ";\n"
        . "if (\\App\\Controllers\\BeforeAfterController::datesAreValid('2026-03-02', '2026-03-01', '2026-03-03')) { echo '[GAGAL] date-order validation mutation was detected'; exit(0); }\n"
        . "echo '[LOLOS] mutation unexpectedly survived'; exit(1);\n";
    file_put_contents($mutationPath, $mutatedSource);
    file_put_contents($phase8dMutationScriptPath, $childSource);
    try {
        $process = proc_open([PHP_BINARY, $phase8dMutationScriptPath], [
            0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w'],
        ], $pipes, __DIR__);
        if (is_resource($process)) {
            fclose($pipes[0]);
            $phase8dMutationOutput = trim((string) stream_get_contents($pipes[1]));
            $mutationErrorOutput = trim((string) stream_get_contents($pipes[2]));
            fclose($pipes[1]);
            fclose($pipes[2]);
            $phase8dMutationExitCode = proc_close($process);
            $phase8dMutationRecovered = $phase8dMutationExitCode === 0
                && str_contains($phase8dMutationOutput, '[GAGAL]')
                && $mutationCount === 1
                && $mutationErrorOutput === '';
        }
    } finally {
        foreach ([$phase8dMutationPath, $phase8dMutationScriptPath, $mutationPath] as $temporaryPath) {
            if (is_file($temporaryPath)) {
                unlink($temporaryPath);
            }
        }
    }
}
$phase8dOriginalRejectsReverseDate = !App\Controllers\BeforeAfterController::datesAreValid(
    '2026-03-02',
    '2026-03-01',
    '2026-03-03'
);
echo '[MUTASI PROSES ANAK] ' . $phase8dMutationOutput . PHP_EOL;
echo '[MUTASI PEMULIHAN] ' . ($phase8dOriginalRejectsReverseDate ? '[LOLOS] validasi asli menolak tanggal terbalik' : '[GAGAL] validasi asli menerima tanggal terbalik') . PHP_EOL;
phase8dCheck('Mutasi validasi tanggal benar-benar merah di child process lalu kode asli tetap menolak', $phase8dMutationRecovered && $phase8dOriginalRejectsReverseDate);

$failed += $phase8dFailures;
