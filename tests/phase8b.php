<?php
declare(strict_types=1);

use App\Core\Db;
use App\Controllers\EvaluationController;
use App\Policies\Permissions;

$phase8bMigration = '012_madani_evaluations.sql';
$phase8bMigrationBefore = Db::one(
    'SELECT status, checksum, statement_count, last_completed_statement FROM schema_migration_runs WHERE migration_name = ?',
    [$phase8bMigration],
    'test'
);
[$phase8bRerunCode, $phase8bRerunOutput] = runMigrationProcess(__DIR__ . '/../database/migrations');
$phase8bMigrationAfter = Db::one(
    'SELECT status, checksum, statement_count, last_completed_statement FROM schema_migration_runs WHERE migration_name = ?',
    [$phase8bMigration],
    'test'
);
$phase8bLegacyRuns = Db::all(
    'SELECT migration_name, status FROM schema_migration_runs WHERE migration_name IN (?, ?, ?) ORDER BY migration_name',
    ['009_surveys.sql', '010_information_surveys.sql', '011_evidence_foundation.sql'],
    'test'
);
$phase8bLegacyRunsValid = count($phase8bLegacyRuns) === 3;
foreach ($phase8bLegacyRuns as $run) {
    $phase8bLegacyRunsValid = $phase8bLegacyRunsValid && $run['status'] === 'APPLIED';
}
check(
    'Migrasi 012 idempoten dan signature 009–011 tetap APPLIED',
    $phase8bMigrationBefore !== null
        && $phase8bMigrationBefore === $phase8bMigrationAfter
        && (int) $phase8bMigrationAfter['statement_count'] === 1
        && (int) $phase8bMigrationAfter['last_completed_statement'] === 1
        && $phase8bRerunCode === 0
        && $phase8bLegacyRunsValid,
    $phase8bRerunOutput
);

$phase8bDriftDetected = false;
$phase8bDriftRestored = false;
Db::pdo('test')->exec('ALTER TABLE madani_evaluations MODIFY COLUMN summary VARCHAR(1999) NOT NULL');
try {
    [$phase8bDriftCode, $phase8bDriftOutput] = runMigrationProcess(__DIR__ . '/../database/migrations');
    $phase8bDriftDetected = $phase8bDriftCode !== 0
        && str_contains($phase8bDriftOutput, 'skema aktual berbeda');
} finally {
    Db::pdo('test')->exec('ALTER TABLE madani_evaluations MODIFY COLUMN summary VARCHAR(2000) NOT NULL');
    [$phase8bRestoreCode, $phase8bRestoreOutput] = runMigrationProcess(__DIR__ . '/../database/migrations');
    $phase8bDriftRestored = $phase8bRestoreCode === 0;
}
check(
    'Signature 012 menolak drift panjang kolom lalu lulus setelah pemulihan',
    $phase8bDriftDetected && $phase8bDriftRestored,
    'drift=' . ($phase8bDriftDetected ? 'ditolak' : 'tidak ditolak')
        . '; restore=' . ($phase8bDriftRestored ? 'lulus' : 'gagal')
        . '; ' . $phase8bRestoreOutput
);

$phase8bAdmin = phase2bUser(
    'ADMIN',
    'phase8b-admin-' . bin2hex(random_bytes(5)),
    'Admin Evaluasi Uji',
    bin2hex(random_bytes(16))
);
$phase8bSiswa = phase2bUser(
    'SISWA',
    'phase8b-siswa-' . bin2hex(random_bytes(5)),
    'Siswa Evaluasi Uji',
    bin2hex(random_bytes(16))
);
$phase8bGuru = phase2bUser(
    'GURU',
    'phase8b-guru-' . bin2hex(random_bytes(5)),
    'Guru Evaluasi Uji',
    bin2hex(random_bytes(16))
);
$phase8bRoleUsers = [
    'GUEST' => null,
    'SISWA' => $phase8bSiswa,
    'GURU' => $phase8bGuru,
    'ADMIN' => $phase8bAdmin,
];
$phase8bIdentities = [];
foreach ($phase8bRoleUsers as $role => $user) {
    $phase8bIdentities[$role] = phase2bHttpIdentity($user);
}
$phase8bForeignIdentity = phase2bHttpIdentity(null);
$phase8bServices = Db::all('SELECT id, code FROM services ORDER BY code', [], 'test');
$phase8bServiceA = $phase8bServices[0] ?? null;
$phase8bServiceB = $phase8bServices[1] ?? null;
$phase8bFixtureReady = is_array($phase8bServiceA)
    && is_array($phase8bServiceB)
    && (int) Db::one('SELECT COUNT(*) AS n FROM madani_evaluations', [], 'test')['n'] === 0;
$phase8bStart = '2026-01-01';
$phase8bEnd = '2026-01-01';

check(
    'Fixture evaluasi dimulai kosong dan layanan fiktif tersedia',
    $phase8bFixtureReady,
    'fixture=' . ($phase8bFixtureReady ? 'kosong' : 'tidak siap')
);
check(
    'Periode valid mencakup batas 366 hari dan menolak tanggal tidak sah',
    EvaluationController::periodIsValid('2026-01-01', '2026-01-01')
    && EvaluationController::periodIsValid('2024-01-01', '2025-01-01')
    && !EvaluationController::periodIsValid('2024-01-01', '2025-01-02')
        && !EvaluationController::periodIsValid('2026-02-30', '2026-03-01')
        && !EvaluationController::periodIsValid('2026-01-02', '2026-01-01')
);
check(
    'Kemampuan evaluasi hanya ADMIN',
    Permissions::can('ADMIN', 'admin.evaluations.view')
        && Permissions::can('ADMIN', 'admin.evaluations.create')
        && !Permissions::can('GUEST', 'admin.evaluations.view')
        && !Permissions::can('SISWA', 'admin.evaluations.create')
        && !Permissions::can('GURU', 'admin.evaluations.view')
);

$phase8bRouteCases = [
    ['GET', '/admin/evaluasi', 'admin.evaluations.view'],
    ['GET', '/admin/evaluasi/999999999', 'admin.evaluations.view'],
    ['POST', '/admin/evaluasi', 'admin.evaluations.create'],
];
$phase8bRequests = [];
$phase8bExpected = [];
foreach ($phase8bRouteCases as [$method, $path, $ability]) {
    foreach ($phase8bRoleUsers as $role => $user) {
        $identity = $phase8bIdentities[$role];
        $payload = ['_csrf' => $identity['csrf']];
        if ($method === 'POST') {
            $payload += [
                'service_id' => '999999999',
                'period_start' => $phase8bStart,
                'period_end' => $phase8bEnd,
                'summary' => 'Matriks akses fixture',
            ];
        }
        $phase8bRequests[] = [
            'method' => $method,
            'path' => $path,
            'cookie' => $identity['cookie'],
            'post' => $payload,
        ];
        $phase8bExpected[] = ['role' => $role, 'method' => $method, 'path' => $path, 'ability' => $ability];
    }
}
$phase8bResponses = phase2bHttpRequests($phase8bRequests);
$phase8bMatrixPassed = count($phase8bResponses) === count($phase8bExpected);
echo PHP_EOL . "MATRIKS ROLE x RUTE LANGKAH 8B\nRole | Rute | Ability | Hasil | Harapan" . PHP_EOL;
foreach ($phase8bExpected as $index => $case) {
    $responseStatus = (int) ($phase8bResponses[$index]['status'] ?? 0);
    $expectedStatus = match ([$case['role'], $case['method'], $case['path']]) {
        ['ADMIN', 'GET', '/admin/evaluasi'] => 200,
        ['ADMIN', 'GET', '/admin/evaluasi/999999999'] => 404,
        ['ADMIN', 'POST', '/admin/evaluasi'] => 422,
        default => $case['role'] === 'GUEST' ? 302 : 403,
    };
    $phase8bMatrixPassed = $phase8bMatrixPassed
        && Permissions::can($case['role'], $case['ability']) === ($case['role'] === 'ADMIN')
        && $responseStatus === $expectedStatus;
    echo $case['role'] . ' | ' . $case['method'] . ' ' . $case['path'] . ' | '
        . $case['ability'] . ' | ' . $responseStatus . ' | ' . $expectedStatus . PHP_EOL;
}
check('Matriks role × rute evaluasi cocok dengan Permissions dan public/index.php', $phase8bMatrixPassed);

$phase8bCsrfRequests = [
    ['method' => 'POST', 'path' => '/admin/evaluasi', 'cookie' => $phase8bIdentities['ADMIN']['cookie'], 'post' => []],
    [
        'method' => 'POST',
        'path' => '/admin/evaluasi',
        'cookie' => $phase8bIdentities['ADMIN']['cookie'],
        'post' => ['_csrf' => 'wrong-token'],
    ],
    [
        'method' => 'POST',
        'path' => '/admin/evaluasi',
        'cookie' => $phase8bIdentities['ADMIN']['cookie'],
        'post' => ['_csrf' => $phase8bForeignIdentity['csrf']],
    ],
];
$phase8bCsrfResponses = phase2bHttpRequests($phase8bCsrfRequests);
check(
    'POST evaluasi menolak CSRF tanpa token, salah, dan token sesi lain',
    count($phase8bCsrfResponses) === 3
        && array_reduce(
            $phase8bCsrfResponses,
            static fn (bool $passed, array $response): bool => $passed && (int) ($response['status'] ?? 0) === 403,
            true
        )
);

$phase8bAdminIdentity = $phase8bIdentities['ADMIN'];
$phase8bListEmptyResponse = phase2bHttpRequests([[
    'method' => 'GET',
    'path' => '/admin/evaluasi',
    'cookie' => $phase8bAdminIdentity['cookie'],
]])[0] ?? [];
check(
    'Daftar evaluasi kosong menampilkan BELUM ADA DATA dan peringatan privasi',
    (int) ($phase8bListEmptyResponse['status'] ?? 0) === 200
        && str_contains((string) ($phase8bListEmptyResponse['body'] ?? ''), 'BELUM ADA DATA')
        && str_contains((string) ($phase8bListEmptyResponse['body'] ?? ''), 'Jangan masukkan data pribadi, identitas siswa, atau informasi BK.')
        && in_array('Cache-Control: no-store', $phase8bListEmptyResponse['headers'] ?? [], true)
);

$phase8bToken = bin2hex(random_bytes(8));
$phase8bBaseSummary = 'Ringkasan evaluasi ' . $phase8bToken;
$phase8bBasePayload = [
    '_csrf' => $phase8bAdminIdentity['csrf'],
    'service_id' => (string) ($phase8bServiceA['id'] ?? 0),
    'period_start' => $phase8bStart,
    'period_end' => $phase8bEnd,
    'summary' => $phase8bBaseSummary,
    'findings' => 'Temuan privat ' . $phase8bToken,
    'conclusion' => 'Kesimpulan privat ' . $phase8bToken,
];
$phase8bBaseResponse = phase2bHttpRequests([[
    'method' => 'POST',
    'path' => '/admin/evaluasi',
    'cookie' => $phase8bAdminIdentity['cookie'],
    'post' => $phase8bBasePayload,
]])[0] ?? [];
$phase8bBaseRow = Db::one(
    'SELECT id, service_id, period_start, period_end, summary, findings, conclusion, corrects_evaluation_id, created_by FROM madani_evaluations WHERE summary = ?',
    [$phase8bBaseSummary],
    'test'
);
$phase8bBaseId = (int) ($phase8bBaseRow['id'] ?? 0);
check(
    'Entry point asli menyimpan evaluasi sah dengan aktor ADMIN dan redirect detail',
    (int) ($phase8bBaseResponse['status'] ?? 0) === 303
        && $phase8bBaseRow !== null
        && (int) $phase8bBaseRow['created_by'] === (int) $phase8bAdmin['id']
        && str_contains(implode("\n", $phase8bBaseResponse['headers'] ?? []), '/admin/evaluasi/' . $phase8bBaseId)
);

$phase8bDuplicateSummary = 'Duplikat periode ' . $phase8bToken;
$phase8bDuplicateResponse = phase2bHttpRequests([[
    'method' => 'POST',
    'path' => '/admin/evaluasi',
    'cookie' => $phase8bAdminIdentity['cookie'],
    'post' => array_replace($phase8bBasePayload, ['summary' => $phase8bDuplicateSummary]),
]])[0] ?? [];
$phase8bSamePeriodCount = (int) Db::one(
    'SELECT COUNT(*) AS n FROM madani_evaluations WHERE service_id = ? AND period_start = ? AND period_end = ?',
    [(int) ($phase8bServiceA['id'] ?? 0), $phase8bStart, $phase8bEnd],
    'test'
)['n'];
check(
    'Dua catatan untuk layanan dan periode yang sama diperbolehkan',
    (int) ($phase8bDuplicateResponse['status'] ?? 0) === 303 && $phase8bSamePeriodCount === 2
);

$phase8bInvalidPayloads = [];
$phase8bInvalidPayloads['layanan tidak ada'] = ['service_id' => '999999999'];
$phase8bInvalidPayloads['tanggal kalender tidak valid'] = ['period_start' => '2026-02-30'];
$phase8bInvalidPayloads['mulai sesudah akhir'] = ['period_start' => '2026-01-02'];
$phase8bInvalidPayloads['rentang melebihi 366 hari'] = ['period_start' => '2024-01-01', 'period_end' => '2025-01-02'];
$phase8bInvalidPayloads['ringkasan kosong'] = ['summary' => ''];
$phase8bInvalidPayloads['ringkasan melebihi batas multibyte'] = ['summary' => str_repeat('é', 2001)];
$phase8bInvalidPayloads['temuan melebihi batas multibyte'] = ['findings' => str_repeat('界', 2001)];
$phase8bInvalidPayloads['kesimpulan melebihi batas'] = ['conclusion' => str_repeat('x', 1001)];
$phase8bInvalidPayloads['UTF-8 ringkasan tidak valid'] = ['summary' => "\xC3\x28"];
$phase8bInvalidPayloads['UTF-8 temuan tidak valid'] = ['findings' => "\xC3\x28"];
$phase8bInvalidPayloads['ID koreksi tidak ada'] = ['corrects_evaluation_id' => '999999999'];
$phase8bInvalidPayloads['ID koreksi bukan numerik'] = ['corrects_evaluation_id' => 'not-an-id'];
$phase8bInvalidPayloads['temuan bertipe array'] = ['findings' => ['bukan', 'teks']];
$phase8bInvalidSentinel = 'Invalid validation ' . $phase8bToken;
foreach ($phase8bInvalidPayloads as $case => &$overrides) {
    $overrides = array_replace($phase8bBasePayload, [
        'service_id' => (string) ($phase8bServiceA['id'] ?? 0),
        'summary' => $phase8bInvalidSentinel . ' ' . $case,
    ], $overrides);
    if ($case === 'layanan tidak ada') {
        $overrides['service_id'] = '999999999';
    }
}
unset($overrides);
$phase8bInvalidRequests = [];
foreach ($phase8bInvalidPayloads as $payload) {
    $phase8bInvalidRequests[] = [
        'method' => 'POST',
        'path' => '/admin/evaluasi',
        'cookie' => $phase8bAdminIdentity['cookie'],
        'post' => $payload,
    ];
}
$phase8bInvalidResponses = phase2bHttpRequests($phase8bInvalidRequests);
$phase8bInvalidPassed = count($phase8bInvalidResponses) === count($phase8bInvalidPayloads);
foreach ($phase8bInvalidResponses as $response) {
    $phase8bInvalidPassed = $phase8bInvalidPassed && (int) ($response['status'] ?? 0) === 422;
}
$phase8bInvalidTokenCount = (int) Db::one(
    'SELECT COUNT(*) AS n FROM madani_evaluations WHERE summary LIKE ?',
    [$phase8bInvalidSentinel . '%'],
    'test'
)['n'];
check(
    'Validasi menolak layanan/tanggal/teks/UTF-8/koreksi invalid tanpa menyimpan',
    $phase8bInvalidPassed && $phase8bInvalidTokenCount === 0,
    'requests=' . count($phase8bInvalidResponses) . '; unexpected rows=' . $phase8bInvalidTokenCount
);

$phase8bBoundarySummary = str_repeat('é', 2000);
$phase8bBoundaryResponse = phase2bHttpRequests([[
    'method' => 'POST',
    'path' => '/admin/evaluasi',
    'cookie' => $phase8bAdminIdentity['cookie'],
    'post' => array_replace($phase8bBasePayload, [
        'summary' => $phase8bBoundarySummary,
        'findings' => str_repeat('界', 2000),
        'conclusion' => str_repeat('ü', 1000),
    ]),
]])[0] ?? [];
check(
    'Batas tepat tiap kolom teks menerima karakter multibyte berdasarkan karakter',
    (int) ($phase8bBoundaryResponse['status'] ?? 0) === 303
        && (int) Db::one(
            'SELECT COUNT(*) AS n FROM madani_evaluations WHERE summary = ? AND CHAR_LENGTH(findings) = ? AND CHAR_LENGTH(conclusion) = ?',
            [$phase8bBoundarySummary, 2000, 1000],
            'test'
        )['n'] === 1
);

$phase8bOtherServiceBase = 'Layanan kedua ' . $phase8bToken;
$phase8bOtherBaseResponse = phase2bHttpRequests([[
    'method' => 'POST',
    'path' => '/admin/evaluasi',
    'cookie' => $phase8bAdminIdentity['cookie'],
    'post' => array_replace($phase8bBasePayload, [
        'service_id' => (string) ($phase8bServiceB['id'] ?? 0),
        'summary' => $phase8bOtherServiceBase,
    ]),
]])[0] ?? [];
$phase8bOtherBaseId = (int) (Db::one(
    'SELECT id FROM madani_evaluations WHERE summary = ?',
    [$phase8bOtherServiceBase],
    'test'
)['id'] ?? 0);
$phase8bBeforeCorrection = $phase8bBaseId > 0
    ? Db::one(
        'SELECT id, service_id, period_start, period_end, summary, findings, conclusion, corrects_evaluation_id, created_by, created_at FROM madani_evaluations WHERE id = ?',
        [$phase8bBaseId],
        'test'
    )
    : null;
$phase8bCrossServiceResponse = phase2bHttpRequests([[
    'method' => 'POST',
    'path' => '/admin/evaluasi',
    'cookie' => $phase8bAdminIdentity['cookie'],
    'post' => array_replace($phase8bBasePayload, [
        'summary' => 'Cross service correction ' . $phase8bToken,
        'corrects_evaluation_id' => (string) $phase8bOtherBaseId,
    ]),
]])[0] ?? [];
$phase8bCorrectionSummary = 'Koreksi yang sah ' . $phase8bToken;
$phase8bCorrectionResponse = phase2bHttpRequests([[
    'method' => 'POST',
    'path' => '/admin/evaluasi',
    'cookie' => $phase8bAdminIdentity['cookie'],
    'post' => array_replace($phase8bBasePayload, [
        'summary' => $phase8bCorrectionSummary,
        'corrects_evaluation_id' => (string) $phase8bBaseId,
    ]),
]])[0] ?? [];
$phase8bCorrectionRow = Db::one(
    'SELECT id, corrects_evaluation_id FROM madani_evaluations WHERE summary = ?',
    [$phase8bCorrectionSummary],
    'test'
);
$phase8bCorrectionId = (int) ($phase8bCorrectionRow['id'] ?? 0);
$phase8bAfterCorrection = $phase8bBaseId > 0
    ? Db::one(
        'SELECT id, service_id, period_start, period_end, summary, findings, conclusion, corrects_evaluation_id, created_by, created_at FROM madani_evaluations WHERE id = ?',
        [$phase8bBaseId],
        'test'
    )
    : null;
$phase8bOriginalDetail = phase2bHttpRequests([[
    'method' => 'GET',
    'path' => '/admin/evaluasi/' . $phase8bBaseId,
    'cookie' => $phase8bAdminIdentity['cookie'],
]])[0] ?? [];
$phase8bCorrectionDetail = phase2bHttpRequests([[
    'method' => 'GET',
    'path' => '/admin/evaluasi/' . $phase8bCorrectionId,
    'cookie' => $phase8bAdminIdentity['cookie'],
]])[0] ?? [];
check(
    'Koreksi append-only merujuk catatan lama, menjaga layanan sama, dan menampilkan kedua arah',
    (int) ($phase8bOtherBaseResponse['status'] ?? 0) === 303
        && (int) ($phase8bCrossServiceResponse['status'] ?? 0) === 422
        && (int) ($phase8bCorrectionResponse['status'] ?? 0) === 303
        && (int) ($phase8bCorrectionRow['corrects_evaluation_id'] ?? 0) === $phase8bBaseId
        && $phase8bBeforeCorrection === $phase8bAfterCorrection
        && str_contains((string) ($phase8bOriginalDetail['body'] ?? ''), 'Dikoreksi oleh #' . $phase8bCorrectionId)
        && str_contains((string) ($phase8bCorrectionDetail['body'] ?? ''), 'Mengoreksi <a href="/admin/evaluasi/' . $phase8bBaseId . '">catatan #' . $phase8bBaseId),
    'other=' . (string) ($phase8bOtherBaseResponse['status'] ?? 0)
        . '; cross=' . (string) ($phase8bCrossServiceResponse['status'] ?? 0)
        . '; correction=' . (string) ($phase8bCorrectionResponse['status'] ?? 0)
        . '; base=' . $phase8bBaseId
        . '; corrected=' . (string) ($phase8bCorrectionRow['corrects_evaluation_id'] ?? 'null')
        . '; original-unchanged=' . ($phase8bBeforeCorrection === $phase8bAfterCorrection ? 'yes' : 'no')
        . '; original-marker=' . (str_contains((string) ($phase8bOriginalDetail['body'] ?? ''), 'Dikoreksi oleh #' . $phase8bCorrectionId) ? 'yes' : 'no')
        . '; correction-marker=' . (str_contains((string) ($phase8bCorrectionDetail['body'] ?? ''), 'Mengoreksi <a href="/admin/evaluasi/' . $phase8bBaseId . '">catatan #' . $phase8bBaseId) ? 'yes' : 'no')
);

$phase8bScriptSummary = '<script>alert("evaluation-' . $phase8bToken . '")</script>';
$phase8bScriptResponse = phase2bHttpRequests([[
    'method' => 'POST',
    'path' => '/admin/evaluasi',
    'cookie' => $phase8bAdminIdentity['cookie'],
    'post' => array_replace($phase8bBasePayload, ['summary' => $phase8bScriptSummary]),
]])[0] ?? [];
$phase8bScriptId = (int) (Db::one(
    'SELECT id FROM madani_evaluations WHERE summary = ?',
    [$phase8bScriptSummary],
    'test'
)['id'] ?? 0);
$phase8bScriptPages = phase2bHttpRequests([
    ['method' => 'GET', 'path' => '/admin/evaluasi', 'cookie' => $phase8bAdminIdentity['cookie']],
    ['method' => 'GET', 'path' => '/admin/evaluasi/' . $phase8bScriptId, 'cookie' => $phase8bAdminIdentity['cookie']],
]);
$phase8bEscapingPassed = (int) ($phase8bScriptResponse['status'] ?? 0) === 303
    && count($phase8bScriptPages) === 2;
foreach ($phase8bScriptPages as $page) {
    $body = (string) ($page['body'] ?? '');
    $phase8bEscapingPassed = $phase8bEscapingPassed
        && (int) ($page['status'] ?? 0) === 200
        && str_contains($body, '&lt;script&gt;')
        && !str_contains($body, $phase8bScriptSummary);
}
check('Ringkasan HTML di daftar dan detail di-escape', $phase8bEscapingPassed);

$phase8bAuditRows = Db::all(
    'SELECT actor_id, action, entity_type, entity_id, message FROM audit_logs WHERE action = ? AND entity_id IN (?, ?, ?, ?)',
    ['evaluation_created', $phase8bBaseId, $phase8bCorrectionId, $phase8bOtherBaseId, $phase8bScriptId],
    'test'
);
$phase8bAuditSafe = count($phase8bAuditRows) >= 4;
foreach ($phase8bAuditRows as $auditRow) {
    $message = (string) ($auditRow['message'] ?? '');
    $phase8bAuditSafe = $phase8bAuditSafe
        && (int) $auditRow['actor_id'] === (int) $phase8bAdmin['id']
        && $auditRow['entity_type'] === 'madani_evaluations'
        && $auditRow['action'] === 'evaluation_created'
        && !str_contains($message, $phase8bToken)
        && !str_contains($message, '<script>');
}
check('Audit memuat aktor/aksi/entitas dan metadata tanpa isi evaluasi', $phase8bAuditSafe);

$phase8bForeignKeysEnforced = true;
$phase8bInvalidFkRows = [
    [
        'service_id' => 999999999,
        'created_by' => (int) $phase8bAdmin['id'],
        'corrects_evaluation_id' => null,
    ],
    [
        'service_id' => (int) ($phase8bServiceA['id'] ?? 0),
        'created_by' => 999999999,
        'corrects_evaluation_id' => null,
    ],
    [
        'service_id' => (int) ($phase8bServiceA['id'] ?? 0),
        'created_by' => (int) $phase8bAdmin['id'],
        'corrects_evaluation_id' => 999999999,
    ],
];
foreach ($phase8bInvalidFkRows as $fkRow) {
    try {
        Db::run(
            'INSERT INTO madani_evaluations (service_id, period_start, period_end, summary, corrects_evaluation_id, created_by) VALUES (?, ?, ?, ?, ?, ?)',
            [$fkRow['service_id'], $phase8bStart, $phase8bEnd, 'FK fixture ' . bin2hex(random_bytes(8)), $fkRow['corrects_evaluation_id'], $fkRow['created_by']],
            'test'
        );
        $phase8bForeignKeysEnforced = false;
    } catch (PDOException) {
    }
}
check('FK evaluasi menolak layanan, pengguna, dan referensi koreksi yang tidak ada', $phase8bForeignKeysEnforced);

$phase8bNoMutationSource = (string) file_get_contents(__DIR__ . '/../app/Controllers/EvaluationController.php');
$phase8bNoUpdateDelete = preg_match('/\b(?:UPDATE|DELETE)\s+madani_evaluations\b/i', $phase8bNoMutationSource) !== 1;
$phase8bNoEditRouteResponse = phase2bHttpRequests([
    [
        'method' => 'POST',
        'path' => '/admin/evaluasi/' . $phase8bBaseId,
        'cookie' => $phase8bAdminIdentity['cookie'],
        'post' => ['_csrf' => $phase8bAdminIdentity['csrf']],
    ],
    [
        'method' => 'POST',
        'path' => '/admin/evaluasi/' . $phase8bBaseId . '/hapus',
        'cookie' => $phase8bAdminIdentity['cookie'],
        'post' => ['_csrf' => $phase8bAdminIdentity['csrf']],
    ],
    [
        'method' => 'PUT',
        'path' => '/admin/evaluasi/' . $phase8bBaseId,
        'cookie' => $phase8bAdminIdentity['cookie'],
    ],
    [
        'method' => 'DELETE',
        'path' => '/admin/evaluasi/' . $phase8bBaseId,
        'cookie' => $phase8bAdminIdentity['cookie'],
    ],
]);
check(
    'Evaluasi tidak menyediakan operasi edit/hapus atau SQL UPDATE/DELETE',
    $phase8bNoUpdateDelete
        && (int) ($phase8bNoEditRouteResponse[0]['status'] ?? 0) === 405
        && in_array((int) ($phase8bNoEditRouteResponse[1]['status'] ?? 0), [404, 405], true)
        && in_array((int) ($phase8bNoEditRouteResponse[2]['status'] ?? 0), [404, 405], true)
        && in_array((int) ($phase8bNoEditRouteResponse[3]['status'] ?? 0), [404, 405], true)
);

$phase8bMutationFile = tempnam(__DIR__, '.evaluation_mutant_');
$phase8bMutationProbe = tempnam(__DIR__, '.evaluation_probe_');
$phase8bMutationPassed = false;
$phase8bMutationOutput = [];
$phase8bMutationExit = -1;
try {
    $phase8bControllerSource = file_get_contents(__DIR__ . '/../app/Controllers/EvaluationController.php');
    $phase8bMutantSource = is_string($phase8bControllerSource)
        ? str_replace(
            ['final class EvaluationController', '$start > $end'],
            ['final class EvaluationControllerMutant', '$start < $end'],
            $phase8bControllerSource,
            $phase8bMutations
        )
        : '';
    $phase8bMutationReady = is_string($phase8bControllerSource)
        && $phase8bMutations === 2
        && is_string($phase8bMutationFile)
        && is_string($phase8bMutationProbe)
        && file_put_contents($phase8bMutationFile, $phase8bMutantSource) !== false;
    if ($phase8bMutationReady) {
        $probe = "<?php\nrequire " . var_export(__DIR__ . '/../app/autoload.php', true) . ";\n"
            . "require " . var_export($phase8bMutationFile, true) . ";\n"
            . "if (\\App\\Controllers\\EvaluationControllerMutant::periodIsValid('2026-01-02', '2026-01-01')) { "
            . "echo '[GAGAL] Periode terbalik diterima oleh mutasi.' . PHP_EOL; exit(1); }\n"
            . "echo '[LOLOS] Periode terbalik ditolak.' . PHP_EOL;\n";
        $phase8bMutationReady = file_put_contents($phase8bMutationProbe, $probe) !== false;
    }
    if ($phase8bMutationReady) {
        exec(
            escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($phase8bMutationProbe) . ' 2>&1',
            $phase8bMutationOutput,
            $phase8bMutationExit
        );
        $phase8bMutationPassed = $phase8bMutationExit === 1
            && str_contains(implode(PHP_EOL, $phase8bMutationOutput), '[GAGAL] Periode terbalik diterima oleh mutasi.');
    }
} finally {
    if (is_string($phase8bMutationFile) && is_file($phase8bMutationFile)) {
        unlink($phase8bMutationFile);
    }
    if (is_string($phase8bMutationProbe) && is_file($phase8bMutationProbe)) {
        unlink($phase8bMutationProbe);
    }
}
$phase8bOriginalRecovered = !EvaluationController::periodIsValid('2026-01-02', '2026-01-01');
echo 'Bukti mutasi salinan sementara: '
    . ($phase8bMutationOutput[0] ?? '[GAGAL] Proses mutant tidak menghasilkan keluaran tes.')
    . ' (exit code ' . $phase8bMutationExit . '); implementasi asli: '
    . ($phase8bOriginalRecovered ? '[LOLOS] Periode terbalik ditolak.' : '[GAGAL] Validasi asli berubah.') . PHP_EOL;
check(
    'Mutasi pemeriksaan urutan tanggal benar-benar merah dan implementasi asli tetap hijau',
    $phase8bMutationPassed && $phase8bOriginalRecovered,
    'replacement count=' . (string) ($phase8bMutations ?? 'n/a')
        . '; exit=' . $phase8bMutationExit
        . '; mutant=' . implode(PHP_EOL, $phase8bMutationOutput)
);
