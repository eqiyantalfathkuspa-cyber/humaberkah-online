<?php
declare(strict_types=1);

use App\Core\Db;
use App\Controllers\RtlController;
use App\Policies\Permissions;

$phase8cMigration = '013_madani_rtl.sql';
$phase8cMigrationBefore = Db::one(
    'SELECT status, checksum, statement_count, last_completed_statement FROM schema_migration_runs WHERE migration_name = ?',
    [$phase8cMigration],
    'test'
);
[$phase8cRerunCode, $phase8cRerunOutput] = runMigrationProcess(__DIR__ . '/../database/migrations');
$phase8cMigrationAfter = Db::one(
    'SELECT status, checksum, statement_count, last_completed_statement FROM schema_migration_runs WHERE migration_name = ?',
    [$phase8cMigration],
    'test'
);
$phase8cEarlierRuns = Db::all(
    'SELECT migration_name, status FROM schema_migration_runs WHERE migration_name IN (?, ?, ?, ?)',
    ['009_surveys.sql', '010_information_surveys.sql', '011_evidence_foundation.sql', '012_madani_evaluations.sql'],
    'test'
);
$phase8cEarlierValid = count($phase8cEarlierRuns) === 4;
foreach ($phase8cEarlierRuns as $run) {
    $phase8cEarlierValid = $phase8cEarlierValid && $run['status'] === 'APPLIED';
}
check(
    'Migrasi 013 idempoten dan signature 009–012 tetap APPLIED',
    $phase8cMigrationBefore !== null
        && $phase8cMigrationBefore === $phase8cMigrationAfter
        && (int) $phase8cMigrationAfter['statement_count'] === 2
        && (int) $phase8cMigrationAfter['last_completed_statement'] === 2
        && $phase8cRerunCode === 0
        && $phase8cEarlierValid,
    $phase8cRerunOutput
);

$phase8cDriftDetected = false;
$phase8cDriftRestored = false;
Db::pdo('test')->exec('ALTER TABLE madani_rtl MODIFY COLUMN responsible_name VARCHAR(149) NOT NULL');
try {
    [$phase8cDriftCode, $phase8cDriftOutput] = runMigrationProcess(__DIR__ . '/../database/migrations');
    $phase8cDriftDetected = $phase8cDriftCode !== 0
        && str_contains($phase8cDriftOutput, 'skema aktual berbeda');
} finally {
    Db::pdo('test')->exec('ALTER TABLE madani_rtl MODIFY COLUMN responsible_name VARCHAR(150) NOT NULL');
    [$phase8cRestoreCode, $phase8cRestoreOutput] = runMigrationProcess(__DIR__ . '/../database/migrations');
    $phase8cDriftRestored = $phase8cRestoreCode === 0;
}
check(
    'Signature 013 menolak drift dan lulus kembali setelah pemulihan',
    $phase8cDriftDetected && $phase8cDriftRestored,
    'drift=' . ($phase8cDriftDetected ? 'ditolak' : 'tidak ditolak')
        . '; restore=' . ($phase8cDriftRestored ? 'lulus' : 'gagal')
        . '; ' . $phase8cRestoreOutput
);

$phase8cToken = bin2hex(random_bytes(6));
$phase8cAdmin = phase2bUser(
    'ADMIN',
    'phase8c-admin-' . $phase8cToken,
    'Admin RTL Fiktif',
    bin2hex(random_bytes(16))
);
$phase8cAdminTwo = phase2bUser(
    'ADMIN',
    'phase8c-admin-two-' . $phase8cToken,
    'Admin RTL Kedua Fiktif',
    bin2hex(random_bytes(16))
);
$phase8cSiswa = phase2bUser(
    'SISWA',
    'phase8c-siswa-' . $phase8cToken,
    'Siswa RTL Fiktif',
    bin2hex(random_bytes(16))
);
$phase8cGuru = phase2bUser(
    'GURU',
    'phase8c-guru-' . $phase8cToken,
    'Guru RTL Fiktif',
    bin2hex(random_bytes(16))
);
$phase8cIdentities = [
    'GUEST' => phase2bHttpIdentity(null),
    'SISWA' => phase2bHttpIdentity($phase8cSiswa),
    'GURU' => phase2bHttpIdentity($phase8cGuru),
    'ADMIN' => phase2bHttpIdentity($phase8cAdmin),
];
$phase8cSecondAdminIdentity = phase2bHttpIdentity($phase8cAdminTwo);
$phase8cService = Db::one('SELECT id FROM services ORDER BY code LIMIT 1', [], 'test');
Db::run(
    'INSERT INTO madani_evaluations (service_id, period_start, period_end, summary, created_by) VALUES (?, ?, ?, ?, ?)',
    [
        (int) ($phase8cService['id'] ?? 0),
        '2026-01-01',
        '2026-01-01',
        'Evaluasi fixture RTL ' . $phase8cToken,
        (int) $phase8cAdmin['id'],
    ],
    'test'
);
$phase8cEvaluationId = (int) Db::one(
    'SELECT id FROM madani_evaluations WHERE summary = ?',
    ['Evaluasi fixture RTL ' . $phase8cToken],
    'test'
)['id'];
Db::run(
    'INSERT INTO madani_evaluations (service_id, period_start, period_end, summary, corrects_evaluation_id, created_by) VALUES (?, ?, ?, ?, ?, ?)',
    [
        (int) ($phase8cService['id'] ?? 0),
        '2026-01-01',
        '2026-01-01',
        'Koreksi evaluasi fixture RTL ' . $phase8cToken,
        $phase8cEvaluationId,
        (int) $phase8cAdmin['id'],
    ],
    'test'
);
$phase8cCorrectedEvaluationId = (int) Db::one(
    'SELECT id FROM madani_evaluations WHERE summary = ?',
    ['Koreksi evaluasi fixture RTL ' . $phase8cToken],
    'test'
)['id'];
$phase8cBasePayload = [
    '_csrf' => $phase8cIdentities['ADMIN']['csrf'],
    'evaluation_id' => (string) $phase8cEvaluationId,
    'action_description' => 'Rencana tindakan fiktif ' . $phase8cToken,
    'responsible_name' => 'Jabatan fiktif ' . $phase8cToken,
    'due_date' => date('Y-m-d', strtotime('+30 days')),
];
$phase8cCreatedResponse = phase2bHttpRequests([[
    'method' => 'POST',
    'path' => '/admin/rtl',
    'cookie' => $phase8cIdentities['ADMIN']['cookie'],
    'post' => $phase8cBasePayload,
]])[0] ?? [];
$phase8cBaseRtlId = (int) (Db::one(
    'SELECT id FROM madani_rtl WHERE action_description = ?',
    [$phase8cBasePayload['action_description']],
    'test'
)['id'] ?? 0);
$phase8cCorrectedRtlPayload = array_replace($phase8cBasePayload, [
    'evaluation_id' => (string) $phase8cCorrectedEvaluationId,
    'action_description' => 'RTL evaluasi yang dikoreksi ' . $phase8cToken,
]);
$phase8cCorrectedRtlResponse = phase2bHttpRequests([[
    'method' => 'POST',
    'path' => '/admin/rtl',
    'cookie' => $phase8cIdentities['ADMIN']['cookie'],
    'post' => $phase8cCorrectedRtlPayload,
]])[0] ?? [];
$phase8cCorrectedRtlId = (int) (Db::one(
    'SELECT id FROM madani_rtl WHERE action_description = ?',
    [$phase8cCorrectedRtlPayload['action_description']],
    'test'
)['id'] ?? 0);
$phase8cCorrectedPages = phase2bHttpRequests([
    ['method' => 'GET', 'path' => '/admin/rtl', 'cookie' => $phase8cIdentities['ADMIN']['cookie']],
    ['method' => 'GET', 'path' => '/admin/rtl/' . $phase8cCorrectedRtlId, 'cookie' => $phase8cIdentities['ADMIN']['cookie']],
    ['method' => 'GET', 'path' => '/admin/rtl?status=DIRENCANAKAN&evaluation_id=' . $phase8cCorrectedEvaluationId, 'cookie' => $phase8cIdentities['ADMIN']['cookie']],
    ['method' => 'GET', 'path' => '/admin/rtl?status=STATUS_TIDAK_VALID', 'cookie' => $phase8cIdentities['ADMIN']['cookie']],
    ['method' => 'GET', 'path' => '/admin/rtl?evaluation_id=bukan-id', 'cookie' => $phase8cIdentities['ADMIN']['cookie']],
]);
check(
    'RTL dapat merujuk evaluasi yang dikoreksi, menampilkan penandanya, dan memvalidasi filter',
    (int) ($phase8cCorrectedRtlResponse['status'] ?? 0) === 303
        && count($phase8cCorrectedPages) === 5
        && str_contains((string) ($phase8cCorrectedPages[0]['body'] ?? ''), 'evaluasi dikoreksi')
        && str_contains((string) ($phase8cCorrectedPages[1]['body'] ?? ''), 'evaluasi dikoreksi')
        && (int) ($phase8cCorrectedPages[2]['status'] ?? 0) === 200
        && (int) ($phase8cCorrectedPages[3]['status'] ?? 0) === 422
        && (int) ($phase8cCorrectedPages[4]['status'] ?? 0) === 422
);
$phase8cMatrixPayload = array_replace($phase8cBasePayload, [
    'action_description' => 'Matriks status RTL fixture ' . $phase8cToken,
]);
$phase8cMatrixCreate = phase2bHttpRequests([[
    'method' => 'POST',
    'path' => '/admin/rtl',
    'cookie' => $phase8cIdentities['ADMIN']['cookie'],
    'post' => $phase8cMatrixPayload,
]])[0] ?? [];
$phase8cMatrixRtlId = (int) (Db::one(
    'SELECT id FROM madani_rtl WHERE action_description = ?',
    [$phase8cMatrixPayload['action_description']],
    'test'
)['id'] ?? 0);

$phase8cPermissions = Permissions::can('ADMIN', 'admin.rtl.view')
    && Permissions::can('ADMIN', 'admin.rtl.create')
    && Permissions::can('ADMIN', 'admin.rtl.update_status')
    && !Permissions::can('GUEST', 'admin.rtl.view')
    && !Permissions::can('SISWA', 'admin.rtl.create')
    && !Permissions::can('GURU', 'admin.rtl.update_status')
    && RtlController::availableTransitions('DIRENCANAKAN') === ['BERJALAN', 'SELESAI']
    && RtlController::availableTransitions('BERJALAN') === ['SELESAI']
    && RtlController::availableTransitions('SELESAI') === [];
check('Permissions hanya memberi akses ADMIN dan status memiliki seluruh transisi maju yang ditetapkan', $phase8cPermissions);

$phase8cRouteCases = [
    ['GET', '/admin/rtl', 'admin.rtl.view'],
    ['GET', '/admin/rtl/' . $phase8cBaseRtlId, 'admin.rtl.view'],
    ['POST', '/admin/rtl', 'admin.rtl.create'],
    ['POST', '/admin/rtl/' . $phase8cMatrixRtlId . '/status', 'admin.rtl.update_status'],
];
$phase8cRequests = [];
$phase8cExpected = [];
foreach ($phase8cRouteCases as [$method, $path, $ability]) {
    foreach ($phase8cIdentities as $role => $identity) {
        $payload = ['_csrf' => $identity['csrf']];
        if ($method === 'POST' && $path === '/admin/rtl') {
            $payload = array_replace($phase8cBasePayload, [
                '_csrf' => $identity['csrf'],
                'action_description' => 'Matriks RTL ' . $role . ' ' . $phase8cToken,
            ]);
        } elseif ($method === 'POST') {
            $payload += ['status' => 'BERJALAN', 'note' => 'Matriks status fiktif'];
        }
        $phase8cRequests[] = [
            'method' => $method,
            'path' => $path,
            'cookie' => $identity['cookie'],
            'post' => $payload,
        ];
        $phase8cExpected[] = ['role' => $role, 'method' => $method, 'path' => $path, 'ability' => $ability];
    }
}
$phase8cResponses = phase2bHttpRequests($phase8cRequests);
$phase8cMatrixPassed = count($phase8cResponses) === count($phase8cExpected);
echo PHP_EOL . "MATRIKS ROLE x RUTE LANGKAH 8C\nRole | Rute | Ability | Hasil | Harapan" . PHP_EOL;
foreach ($phase8cExpected as $index => $case) {
    $actual = (int) ($phase8cResponses[$index]['status'] ?? 0);
    $expected = match ([$case['role'], $case['method']]) {
        ['ADMIN', 'GET'] => $case['path'] === '/admin/rtl' ? 200 : 200,
        ['ADMIN', 'POST'] => 303,
        default => $case['role'] === 'GUEST' ? 302 : 403,
    };
    $phase8cMatrixPassed = $phase8cMatrixPassed
        && Permissions::can($case['role'], $case['ability']) === ($case['role'] === 'ADMIN')
        && $actual === $expected;
    echo $case['role'] . ' | ' . $case['method'] . ' ' . $case['path'] . ' | '
        . $case['ability'] . ' | ' . $actual . ' | ' . $expected . PHP_EOL;
}
$phase8cListResponseHeaders = implode("\n", $phase8cResponses[0]['headers'] ?? []);
check(
    'Matriks role x empat rute RTL cocok dengan Permissions dan entry point public/index.php',
    $phase8cMatrixPassed
        && (int) ($phase8cCreatedResponse['status'] ?? 0) === 303
        && (int) ($phase8cMatrixCreate['status'] ?? 0) === 303
        && str_contains(strtolower($phase8cListResponseHeaders), 'cache-control: no-store')
);

$phase8cCsrfRequests = [];
foreach (['/admin/rtl', '/admin/rtl/' . $phase8cBaseRtlId . '/status'] as $path) {
    foreach ([[], ['_csrf' => 'wrong-token'], ['_csrf' => phase2bForeignCsrfToken()]] as $tokenPayload) {
        $phase8cCsrfRequests[] = [
            'method' => 'POST',
            'path' => $path,
            'cookie' => $phase8cIdentities['ADMIN']['cookie'],
            'post' => $tokenPayload,
        ];
    }
}
$phase8cCsrfResponses = phase2bHttpRequests($phase8cCsrfRequests);
$phase8cCsrfPassed = count($phase8cCsrfResponses) === 6;
foreach ($phase8cCsrfResponses as $response) {
    $phase8cCsrfPassed = $phase8cCsrfPassed && (int) ($response['status'] ?? 0) === 403;
}
check('Kedua POST RTL menolak CSRF tanpa token, token salah, dan token sesi lain', $phase8cCsrfPassed);

$phase8cInitialLog = Db::one(
    'SELECT rtl_id, status, note, changed_by FROM madani_rtl_status_log WHERE rtl_id = ? ORDER BY id LIMIT 1',
    [$phase8cBaseRtlId],
    'test'
);
$phase8cCreatedAudit = Db::one(
    'SELECT actor_id, action, entity_type, entity_id, message FROM audit_logs WHERE action = ? AND entity_id = ? ORDER BY id DESC LIMIT 1',
    ['rtl_created', $phase8cBaseRtlId],
    'test'
);
check(
    'RTL tersimpan bersama riwayat awal dan audit aktor dalam transaksi',
    (int) ($phase8cCreatedResponse['status'] ?? 0) === 303
        && $phase8cBaseRtlId > 0
        && $phase8cInitialLog !== null
        && $phase8cInitialLog['status'] === 'DIRENCANAKAN'
        && (int) $phase8cInitialLog['changed_by'] === (int) $phase8cAdmin['id']
        && (int) ($phase8cCreatedAudit['actor_id'] ?? 0) === (int) $phase8cAdmin['id']
        && ($phase8cCreatedAudit['action'] ?? '') === 'rtl_created'
        && ($phase8cCreatedAudit['entity_type'] ?? '') === 'madani_rtl'
        && (int) ($phase8cCreatedAudit['entity_id'] ?? 0) === $phase8cBaseRtlId
        && str_contains((string) ($phase8cCreatedAudit['message'] ?? ''), '"status_new":"DIRENCANAKAN"')
);

$phase8cRollbackAction = 'Rollback RTL ' . $phase8cToken;
$phase8cRollbackTrigger = 'trg_phase8c_rtl_audit_' . bin2hex(random_bytes(4));
Db::pdo('test')->exec(
    "CREATE TRIGGER `{$phase8cRollbackTrigger}` BEFORE INSERT ON audit_logs FOR EACH ROW "
        . "BEGIN IF NEW.action = 'rtl_created' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'rtl rollback fixture'; END IF; END"
);
try {
    $phase8cRollbackResponse = phase2bHttpRequests([[
        'method' => 'POST',
        'path' => '/admin/rtl',
        'cookie' => $phase8cIdentities['ADMIN']['cookie'],
        'post' => array_replace($phase8cBasePayload, [
            '_csrf' => $phase8cIdentities['ADMIN']['csrf'],
            'action_description' => $phase8cRollbackAction,
        ]),
    ]])[0] ?? [];
} finally {
    Db::pdo('test')->exec("DROP TRIGGER `{$phase8cRollbackTrigger}`");
}
$phase8cRollbackRows = (int) Db::one(
    'SELECT COUNT(*) AS n FROM madani_rtl WHERE action_description = ?',
    [$phase8cRollbackAction],
    'test'
)['n'];
check(
    'Kegagalan audit membatalkan RTL dan riwayat awal dalam transaksi yang sama',
    (int) ($phase8cRollbackResponse['status'] ?? 0) === 500
        && $phase8cRollbackRows === 0
        && !str_contains((string) ($phase8cRollbackResponse['body'] ?? ''), 'rtl rollback fixture')
);

$phase8cCountBeforeInvalid = (int) Db::one(
    'SELECT COUNT(*) AS n FROM madani_rtl WHERE evaluation_id = ?',
    [$phase8cEvaluationId],
    'test'
)['n'];
$phase8cInvalidDescriptions = [
    'invalid-evaluation-' . $phase8cToken,
    'invalid-date-' . $phase8cToken,
    '',
    'too-long-action-' . $phase8cToken,
    'too-long-responsible-' . $phase8cToken,
    'invalid-responsible-' . $phase8cToken,
    'invalid-utf8-' . $phase8cToken,
];
$phase8cInvalidPayloads = [
    array_replace($phase8cBasePayload, ['evaluation_id' => '999999999', 'action_description' => $phase8cInvalidDescriptions[0]]),
    array_replace($phase8cBasePayload, ['due_date' => '2026-02-30', 'action_description' => $phase8cInvalidDescriptions[1]]),
    array_replace($phase8cBasePayload, ['action_description' => $phase8cInvalidDescriptions[2]]),
    array_replace($phase8cBasePayload, ['action_description' => str_repeat('x', 2001)]),
    array_replace($phase8cBasePayload, ['responsible_name' => str_repeat('界', 151), 'action_description' => $phase8cInvalidDescriptions[4]]),
    array_replace($phase8cBasePayload, ['responsible_name' => '', 'action_description' => $phase8cInvalidDescriptions[5]]),
    array_replace($phase8cBasePayload, ['action_description' => "\xC3\x28" . $phase8cToken]),
];
foreach ($phase8cInvalidPayloads as $index => &$invalidPayload) {
    $invalidPayload['action_description'] = $invalidPayload['action_description']
        ?? $phase8cInvalidDescriptions[$index];
}
unset($invalidPayload);
$phase8cInvalidRequests = [];
foreach ($phase8cInvalidPayloads as $payload) {
    $phase8cInvalidRequests[] = [
        'method' => 'POST',
        'path' => '/admin/rtl',
        'cookie' => $phase8cIdentities['ADMIN']['cookie'],
        'post' => array_replace($payload, ['_csrf' => $phase8cIdentities['ADMIN']['csrf']]),
    ];
}
$phase8cInvalidResponses = phase2bHttpRequests($phase8cInvalidRequests);
$phase8cInvalidPassed = count($phase8cInvalidResponses) === count($phase8cInvalidPayloads);
foreach ($phase8cInvalidResponses as $response) {
    $phase8cInvalidPassed = $phase8cInvalidPassed && (int) ($response['status'] ?? 0) === 422;
}
$phase8cCountAfterInvalid = (int) Db::one(
    'SELECT COUNT(*) AS n FROM madani_rtl WHERE evaluation_id = ?',
    [$phase8cEvaluationId],
    'test'
)['n'];
check(
    'Validasi evaluasi, tanggal, teks wajib, batas karakter, dan UTF-8 menolak tanpa menyimpan baris',
    $phase8cInvalidPassed && $phase8cCountBeforeInvalid === $phase8cCountAfterInvalid
);

$phase8cMaxAction = str_repeat('界', 2000);
$phase8cMaxResponsible = str_repeat('é', 150);
$phase8cMaxDescription = 'batas-tepat-' . $phase8cToken;
$phase8cMaxResponse = phase2bHttpRequests([[
    'method' => 'POST',
    'path' => '/admin/rtl',
    'cookie' => $phase8cIdentities['ADMIN']['cookie'],
    'post' => array_replace($phase8cBasePayload, [
        '_csrf' => $phase8cIdentities['ADMIN']['csrf'],
        'action_description' => $phase8cMaxAction,
        'responsible_name' => $phase8cMaxResponsible,
    ]),
]])[0] ?? [];
$phase8cMaxRow = Db::one(
    'SELECT action_description, responsible_name FROM madani_rtl WHERE action_description = ?',
    [$phase8cMaxAction],
    'test'
);
check(
    'Batas tepat RTL menerima teks multibyte berdasarkan karakter',
    (int) ($phase8cMaxResponse['status'] ?? 0) === 303
        && mb_strlen((string) ($phase8cMaxRow['action_description'] ?? ''), 'UTF-8') === 2000
        && mb_strlen((string) ($phase8cMaxRow['responsible_name'] ?? ''), 'UTF-8') === 150
);

$phase8cTransitionSnapshot = Db::all(
    'SELECT id, rtl_id, status, note, changed_by, created_at FROM madani_rtl_status_log WHERE rtl_id = ? ORDER BY id',
    [$phase8cBaseRtlId],
    'test'
);
$phase8cTransitionResponses = phase2bHttpRequests([
    [
        'method' => 'POST',
        'path' => '/admin/rtl/' . $phase8cBaseRtlId . '/status',
        'cookie' => $phase8cIdentities['ADMIN']['cookie'],
        'post' => [
            '_csrf' => $phase8cIdentities['ADMIN']['csrf'],
            'status' => 'BERJALAN',
            'note' => str_repeat('界', 500),
        ],
    ],
    [
        'method' => 'POST',
        'path' => '/admin/rtl/' . $phase8cBaseRtlId . '/status',
        'cookie' => $phase8cIdentities['ADMIN']['cookie'],
        'post' => [
            '_csrf' => $phase8cIdentities['ADMIN']['csrf'],
            'status' => 'SELESAI',
            'note' => str_repeat('界', 501),
        ],
    ],
    [
        'method' => 'POST',
        'path' => '/admin/rtl/' . $phase8cBaseRtlId . '/status',
        'cookie' => $phase8cIdentities['ADMIN']['cookie'],
        'post' => ['_csrf' => $phase8cIdentities['ADMIN']['csrf'], 'status' => 'SELESAI', 'note' => 'Selesai fiktif'],
    ],
    [
        'method' => 'POST',
        'path' => '/admin/rtl/' . $phase8cBaseRtlId . '/status',
        'cookie' => $phase8cIdentities['ADMIN']['cookie'],
        'post' => ['_csrf' => $phase8cIdentities['ADMIN']['csrf'], 'status' => 'BERJALAN', 'note' => 'Transisi mundur ditolak'],
    ],
    [
        'method' => 'POST',
        'path' => '/admin/rtl/' . $phase8cBaseRtlId . '/status',
        'cookie' => $phase8cIdentities['ADMIN']['cookie'],
        'post' => ['_csrf' => $phase8cIdentities['ADMIN']['csrf'], 'status' => 'SELESAI', 'note' => 'Status sama ditolak'],
    ],
    [
        'method' => 'POST',
        'path' => '/admin/rtl/' . $phase8cBaseRtlId . '/status',
        'cookie' => $phase8cIdentities['ADMIN']['cookie'],
        'post' => ['_csrf' => $phase8cIdentities['ADMIN']['csrf'], 'status' => 'DIBATALKAN', 'note' => 'Status asing ditolak'],
    ],
]);
$phase8cTransitionAfter = Db::all(
    'SELECT id, rtl_id, status, note, changed_by, created_at FROM madani_rtl_status_log WHERE rtl_id = ? ORDER BY id',
    [$phase8cBaseRtlId],
    'test'
);
$phase8cLatestStatus = Db::one(
    'SELECT status FROM madani_rtl_status_log WHERE rtl_id = ? ORDER BY id DESC LIMIT 1',
    [$phase8cBaseRtlId],
    'test'
);
$phase8cTransitionPassed = count($phase8cTransitionResponses) === 6
    && (int) ($phase8cTransitionResponses[0]['status'] ?? 0) === 303
    && (int) ($phase8cTransitionResponses[1]['status'] ?? 0) === 422
    && (int) ($phase8cTransitionResponses[2]['status'] ?? 0) === 303
    && array_slice(array_column($phase8cTransitionResponses, 'status'), 3) === [422, 422, 422]
    && count($phase8cTransitionAfter) === count($phase8cTransitionSnapshot) + 2
    && $phase8cLatestStatus['status'] === 'SELESAI'
    && mb_strlen((string) ($phase8cTransitionAfter[count($phase8cTransitionSnapshot)]['note'] ?? ''), 'UTF-8') === 500
    && array_slice($phase8cTransitionAfter, 0, count($phase8cTransitionSnapshot)) === $phase8cTransitionSnapshot;
check('Transisi sah maju append-only; transisi mundur, berulang, atau di luar daftar ditolak', $phase8cTransitionPassed);
$phase8cCurrentStatusPages = phase2bHttpRequests([
    ['method' => 'GET', 'path' => '/admin/rtl', 'cookie' => $phase8cIdentities['ADMIN']['cookie']],
    ['method' => 'GET', 'path' => '/admin/rtl/' . $phase8cBaseRtlId, 'cookie' => $phase8cIdentities['ADMIN']['cookie']],
]);
check(
    'Status saat ini di daftar dan detail berasal dari baris riwayat terbaru',
    count($phase8cCurrentStatusPages) === 2
        && str_contains((string) ($phase8cCurrentStatusPages[0]['body'] ?? ''), '>Selesai</td>')
        && str_contains((string) ($phase8cCurrentStatusPages[1]['body'] ?? ''), '>Selesai</dd>')
);

$phase8cDirectCompletePayload = array_replace($phase8cBasePayload, [
    '_csrf' => $phase8cIdentities['ADMIN']['csrf'],
    'action_description' => 'Rencana selesai langsung ' . $phase8cToken,
]);
$phase8cDirectCompleteResponse = phase2bHttpRequests([[
    'method' => 'POST',
    'path' => '/admin/rtl',
    'cookie' => $phase8cIdentities['ADMIN']['cookie'],
    'post' => $phase8cDirectCompletePayload,
]])[0] ?? [];
$phase8cDirectCompleteId = (int) (Db::one(
    'SELECT id FROM madani_rtl WHERE action_description = ?',
    [$phase8cDirectCompletePayload['action_description']],
    'test'
)['id'] ?? 0);
$phase8cDirectCompleteStatus = phase2bHttpRequests([[
    'method' => 'POST',
    'path' => '/admin/rtl/' . $phase8cDirectCompleteId . '/status',
    'cookie' => $phase8cIdentities['ADMIN']['cookie'],
    'post' => ['_csrf' => $phase8cIdentities['ADMIN']['csrf'], 'status' => 'SELESAI', 'note' => 'Selesai langsung'],
]])[0] ?? [];
check(
    'Transisi DIRENCANAKAN langsung ke SELESAI sah',
    (int) ($phase8cDirectCompleteResponse['status'] ?? 0) === 303
        && (int) ($phase8cDirectCompleteStatus['status'] ?? 0) === 303
);

$phase8cConcurrentPayload = array_replace($phase8cBasePayload, [
    '_csrf' => $phase8cIdentities['ADMIN']['csrf'],
    'action_description' => 'Rencana race ' . $phase8cToken,
]);
$phase8cConcurrentCreate = phase2bHttpRequests([[
    'method' => 'POST',
    'path' => '/admin/rtl',
    'cookie' => $phase8cIdentities['ADMIN']['cookie'],
    'post' => $phase8cConcurrentPayload,
]])[0] ?? [];
$phase8cConcurrentRtlId = (int) (Db::one(
    'SELECT id FROM madani_rtl WHERE action_description = ?',
    [$phase8cConcurrentPayload['action_description']],
    'test'
)['id'] ?? 0);
$phase8cRaceMarker = tempnam(__DIR__, '.rtl_race_');
$phase8cRaceStart = $phase8cRaceMarker . '.start';
$phase8cRaceWorkers = [
    ['ready' => $phase8cRaceMarker . '.ready_a', 'identity' => $phase8cIdentities['ADMIN']],
    ['ready' => $phase8cRaceMarker . '.ready_b', 'identity' => $phase8cSecondAdminIdentity],
];
$phase8cWorkerScript = $phase8cRaceMarker . '.php';
$phase8cWorkerSource = "<?php\n"
    . "\$_SERVER['APP_ENV'] = 'test'; putenv('APP_ENV=test');\n"
    . "require " . var_export(__DIR__ . '/../app/autoload.php', true) . ";\n"
    . "session_id(\$argv[2]);\n"
    . "session_start(); session_write_close();\n"
    . "file_put_contents(\$argv[1], 'ready', LOCK_EX);\n"
    . "\$deadline = microtime(true) + 20;\n"
    . "while (!file_exists(" . var_export($phase8cRaceStart, true) . ")) { if (microtime(true) >= \$deadline) { exit(2); } usleep(1000); }\n"
    . "\$_SERVER['REQUEST_METHOD'] = 'POST'; \$_POST = "
    . "['_csrf' => \$argv[3], 'status' => 'BERJALAN', 'note' => 'Catatan race fiktif'];\n"
    . "ob_start(); \\App\\Core\\Router::dispatch('POST', '/admin/rtl/" . $phase8cConcurrentRtlId . "/status'); ob_end_clean();\n"
    . "echo http_response_code();\n";
$phase8cRaceStatuses = [];
$phase8cRaceStarted = false;
if (is_string($phase8cRaceMarker)) {
    file_put_contents($phase8cWorkerScript, $phase8cWorkerSource);
    $phase8cProcesses = [];
    try {
        foreach ($phase8cRaceWorkers as $worker) {
            $pipes = [];
            $sessionId = substr(
                $worker['identity']['cookie'],
                strpos($worker['identity']['cookie'], '=') + 1
            );
            $process = proc_open([
                PHP_BINARY,
                $phase8cWorkerScript,
                $worker['ready'],
                $sessionId,
                $worker['identity']['csrf'],
            ], [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ], $pipes, __DIR__);
            if (!is_resource($process)) {
                continue;
            }
            fclose($pipes[0]);
            $phase8cProcesses[] = ['process' => $process, 'stdout' => $pipes[1], 'stderr' => $pipes[2]];
        }
        $deadline = microtime(true) + 20;
        $readyPaths = array_column($phase8cRaceWorkers, 'ready');
        while (count(array_filter($readyPaths, static fn (string $path): bool => is_file($path))) < count($phase8cProcesses)
            && microtime(true) < $deadline) {
            usleep(10000);
        }
        $phase8cRaceStarted = count($phase8cProcesses) === 2
            && count(array_filter($readyPaths, static fn (string $path): bool => is_file($path))) === 2;
        if ($phase8cRaceStarted) {
            file_put_contents($phase8cRaceStart, 'go');
        } else {
            file_put_contents($phase8cRaceStart, 'stop');
        }
        foreach ($phase8cProcesses as $entry) {
            $stdout = trim((string) stream_get_contents($entry['stdout']));
            $stderr = trim((string) stream_get_contents($entry['stderr']));
            fclose($entry['stdout']);
            fclose($entry['stderr']);
            $exitCode = proc_close($entry['process']);
            $phase8cRaceStatuses[] = ['status' => (int) $stdout, 'exit' => $exitCode, 'stderr' => $stderr];
        }
    } finally {
        foreach ([$phase8cRaceMarker, $phase8cRaceStart, ...$readyPaths, $phase8cWorkerScript] as $temporaryPath) {
            if (is_file($temporaryPath)) {
                unlink($temporaryPath);
            }
        }
    }
}
$phase8cRaceLog = Db::all(
    'SELECT status FROM madani_rtl_status_log WHERE rtl_id = ? ORDER BY id',
    [$phase8cConcurrentRtlId],
    'test'
);
$phase8cRaceStatusCodes = array_column($phase8cRaceStatuses, 'status');
sort($phase8cRaceStatusCodes);
check(
    'Dua proses status serentak mengunci baris RTL dan hanya satu transisi menang',
    (int) ($phase8cConcurrentCreate['status'] ?? 0) === 303
        && $phase8cRaceStarted
        && $phase8cRaceStatusCodes === [303, 422]
        && count($phase8cRaceLog) === 2
        && $phase8cRaceLog[0]['status'] === 'DIRENCANAKAN'
        && $phase8cRaceLog[1]['status'] === 'BERJALAN'
        && count(array_filter($phase8cRaceStatuses, static fn (array $entry): bool => $entry['exit'] !== 0 || $entry['stderr'] !== '')) === 0
);

$phase8cOverdueRtls = [];
foreach ([
    ['past', date('Y-m-d', strtotime('-10 years'))],
    ['future', date('Y-m-d', strtotime('+10 years'))],
    ['done', date('Y-m-d', strtotime('-10 years'))],
] as [$kind, $dueDate]) {
    $action = 'Tenggat ' . $kind . ' ' . $phase8cToken;
    phase2bHttpRequests([[
        'method' => 'POST',
        'path' => '/admin/rtl',
        'cookie' => $phase8cIdentities['ADMIN']['cookie'],
        'post' => array_replace($phase8cBasePayload, [
            '_csrf' => $phase8cIdentities['ADMIN']['csrf'],
            'action_description' => $action,
            'due_date' => $dueDate,
        ]),
    ]]);
    $phase8cOverdueRtls[$kind] = (int) (Db::one(
        'SELECT id FROM madani_rtl WHERE action_description = ?',
        [$action],
        'test'
    )['id'] ?? 0);
}
phase2bHttpRequests([[
    'method' => 'POST',
    'path' => '/admin/rtl/' . $phase8cOverdueRtls['done'] . '/status',
    'cookie' => $phase8cIdentities['ADMIN']['cookie'],
    'post' => ['_csrf' => $phase8cIdentities['ADMIN']['csrf'], 'status' => 'SELESAI', 'note' => 'Tenggat selesai fiktif'],
]]);
$phase8cListBody = (string) (phase2bHttpRequests([[
    'method' => 'GET',
    'path' => '/admin/rtl',
    'cookie' => $phase8cIdentities['ADMIN']['cookie'],
]])[0]['body'] ?? '');
$phase8cPastPosition = strpos($phase8cListBody, 'Tenggat past ' . $phase8cToken);
$phase8cFuturePosition = strpos($phase8cListBody, 'Tenggat future ' . $phase8cToken);
$phase8cDonePosition = strpos($phase8cListBody, 'Tenggat done ' . $phase8cToken);
$phase8cPastRow = $phase8cPastPosition === false ? '' : substr($phase8cListBody, $phase8cPastPosition, 400);
$phase8cFutureRow = $phase8cFuturePosition === false ? '' : substr($phase8cListBody, $phase8cFuturePosition, 400);
$phase8cDoneRow = $phase8cDonePosition === false ? '' : substr($phase8cListBody, $phase8cDonePosition, 400);
check(
    'Label lewat tenggat dihitung hanya untuk RTL belum selesai dengan tenggat yang lewat',
    $phase8cPastPosition !== false
        && str_contains($phase8cPastRow, 'Lewat tenggat')
        && $phase8cFuturePosition !== false
        && !str_contains($phase8cFutureRow, 'Lewat tenggat')
        && $phase8cDonePosition !== false
        && !str_contains($phase8cDoneRow, 'Lewat tenggat')
);

$phase8cXssAction = '<script>alert("rtl-' . $phase8cToken . '")</script>';
$phase8cXssResponsible = '<img src=x onerror="rtl-' . $phase8cToken . '">';
$phase8cXssCreate = phase2bHttpRequests([[
    'method' => 'POST',
    'path' => '/admin/rtl',
    'cookie' => $phase8cIdentities['ADMIN']['cookie'],
    'post' => array_replace($phase8cBasePayload, [
        '_csrf' => $phase8cIdentities['ADMIN']['csrf'],
        'action_description' => $phase8cXssAction,
        'responsible_name' => $phase8cXssResponsible,
    ]),
]])[0] ?? [];
$phase8cXssRtlId = (int) (Db::one(
    'SELECT id FROM madani_rtl WHERE action_description = ?',
    [$phase8cXssAction],
    'test'
)['id'] ?? 0);
$phase8cXssPages = phase2bHttpRequests([
    ['method' => 'GET', 'path' => '/admin/rtl', 'cookie' => $phase8cIdentities['ADMIN']['cookie']],
    ['method' => 'GET', 'path' => '/admin/rtl/' . $phase8cXssRtlId, 'cookie' => $phase8cIdentities['ADMIN']['cookie']],
]);
$phase8cXssPassed = (int) ($phase8cXssCreate['status'] ?? 0) === 303 && count($phase8cXssPages) === 2;
foreach ($phase8cXssPages as $page) {
    $body = (string) ($page['body'] ?? '');
    $phase8cXssPassed = $phase8cXssPassed
        && (int) ($page['status'] ?? 0) === 200
        && str_contains($body, '&lt;script&gt;')
        && str_contains($body, '&lt;img')
        && !str_contains($body, $phase8cXssAction)
        && !str_contains($body, $phase8cXssResponsible);
}
$phase8cEvaluationDetail = phase2bHttpRequests([[
    'method' => 'GET',
    'path' => '/admin/evaluasi/' . $phase8cEvaluationId,
    'cookie' => $phase8cIdentities['ADMIN']['cookie'],
]])[0] ?? [];
$phase8cXssPassed = $phase8cXssPassed
    && str_contains((string) ($phase8cEvaluationDetail['body'] ?? ''), '/admin/rtl?evaluation_id=' . $phase8cEvaluationId);
check('XSS RTL ter-escape di daftar/detail dan evaluasi menyediakan tautan filter RTL', $phase8cXssPassed);

$phase8cAuditRows = Db::all(
    'SELECT actor_id, action, entity_type, entity_id, message FROM audit_logs WHERE entity_type = ? AND entity_id IN (?, ?, ?, ?)',
    ['madani_rtl', $phase8cBaseRtlId, $phase8cDirectCompleteId, $phase8cConcurrentRtlId, $phase8cXssRtlId],
    'test'
);
$phase8cAuditSafe = count($phase8cAuditRows) >= 5;
foreach ($phase8cAuditRows as $auditRow) {
    $message = (string) ($auditRow['message'] ?? '');
    $phase8cAuditSafe = $phase8cAuditSafe
        && in_array((int) $auditRow['actor_id'], [(int) $phase8cAdmin['id'], (int) $phase8cAdminTwo['id']], true)
        && in_array($auditRow['action'], ['rtl_created', 'rtl_status_changed'], true)
        && str_contains($message, '"evaluation_id":')
        && str_contains($message, '"status_new":')
        && !str_contains($message, $phase8cToken)
        && !str_contains($message, '<script>')
        && !str_contains($message, 'Catatan race fiktif')
        && !str_contains($message, 'Mulai tindakan fiktif');
}
check('Audit RTL memuat metadata saja tanpa uraian, PIC, atau catatan', $phase8cAuditSafe);

$phase8cFkEnforced = true;
try {
    Db::run(
        'INSERT INTO madani_rtl (evaluation_id, action_description, responsible_name, due_date, created_by) VALUES (?, ?, ?, ?, ?)',
        [$phase8cEvaluationId, 'FK pengguna fiktif', 'PIC fiktif', date('Y-m-d'), 999999999],
        'test'
    );
    $phase8cFkEnforced = false;
} catch (PDOException) {
}
try {
    Db::run(
        'INSERT INTO madani_rtl (evaluation_id, action_description, responsible_name, due_date, created_by) VALUES (?, ?, ?, ?, ?)',
        [999999999, 'FK evaluasi fiktif', 'PIC fiktif', date('Y-m-d'), (int) $phase8cAdmin['id']],
        'test'
    );
    $phase8cFkEnforced = false;
} catch (PDOException) {
}
try {
    Db::run(
        'INSERT INTO madani_rtl_status_log (rtl_id, status, changed_by) VALUES (?, ?, ?)',
        [999999999, 'DIRENCANAKAN', (int) $phase8cAdmin['id']],
        'test'
    );
    $phase8cFkEnforced = false;
} catch (PDOException) {
}
try {
    Db::run(
        'INSERT INTO madani_rtl_status_log (rtl_id, status, changed_by) VALUES (?, ?, ?)',
        [$phase8cBaseRtlId, 'BERJALAN', 999999999],
        'test'
    );
    $phase8cFkEnforced = false;
} catch (PDOException) {
}
check('FK RTL dan riwayat menolak evaluasi, RTL, atau pengguna yang tidak ada', $phase8cFkEnforced);

$phase8cSource = (string) file_get_contents(__DIR__ . '/../app/Controllers/RtlController.php');
$phase8cViews = (string) file_get_contents(__DIR__ . '/../app/Views/pages/admin_rtl.php')
    . (string) file_get_contents(__DIR__ . '/../app/Views/pages/admin_rtl_detail.php');
$phase8cAppendOnly = preg_match('/\bDELETE\s+FROM\s+madani_rtl(?:_status_log)?\b/i', $phase8cSource) !== 1
    && preg_match('/\bUPDATE\s+madani_rtl(?:_status_log)?\b/i', $phase8cSource) !== 1
    && str_contains($phase8cSource, 'SELECT rtl_id, MAX(id) AS latest_id FROM madani_rtl_status_log GROUP BY rtl_id');
$phase8cNoMutationRoutes = phase2bHttpRequests([
    ['method' => 'POST', 'path' => '/admin/rtl/' . $phase8cBaseRtlId, 'cookie' => $phase8cIdentities['ADMIN']['cookie'], 'post' => ['_csrf' => $phase8cIdentities['ADMIN']['csrf']]],
    ['method' => 'POST', 'path' => '/admin/rtl/' . $phase8cBaseRtlId . '/hapus', 'cookie' => $phase8cIdentities['ADMIN']['cookie'], 'post' => ['_csrf' => $phase8cIdentities['ADMIN']['csrf']]],
    ['method' => 'PUT', 'path' => '/admin/rtl/' . $phase8cBaseRtlId, 'cookie' => $phase8cIdentities['ADMIN']['cookie']],
    ['method' => 'DELETE', 'path' => '/admin/rtl/' . $phase8cBaseRtlId, 'cookie' => $phase8cIdentities['ADMIN']['cookie']],
    ['method' => 'GET', 'path' => '/admin/rtl/bukan-id', 'cookie' => $phase8cIdentities['ADMIN']['cookie']],
    [
        'method' => 'POST',
        'path' => '/admin/rtl/999999999/status',
        'cookie' => $phase8cIdentities['ADMIN']['cookie'],
        'post' => ['_csrf' => $phase8cIdentities['ADMIN']['csrf'], 'status' => 'BERJALAN'],
    ],
]);
$phase8cNoMutationRoutesPassed = count($phase8cNoMutationRoutes) === 6;
foreach ($phase8cNoMutationRoutes as $index => $response) {
    $phase8cNoMutationRoutesPassed = $phase8cNoMutationRoutesPassed
        && in_array((int) ($response['status'] ?? 0), $index >= 4 ? [404] : [404, 405], true);
}
$phase8cEmptyViewOutput = '';
ob_start();
App\Core\View::render('pages/admin_rtl', [
    'title' => 'RTL fixture kosong',
    'evaluations' => [],
    'rtls' => [],
    'actionDescriptionMaxLength' => 2000,
    'responsibleNameMaxLength' => 150,
    'statusNoteMaxLength' => 500,
]);
$phase8cEmptyViewOutput = (string) ob_get_clean();
check(
    'RTL append-only tanpa rute edit/hapus; halaman kosong eksplisit dan tampilan tanpa angka tetap',
    $phase8cAppendOnly
        && $phase8cNoMutationRoutesPassed
        && str_contains($phase8cEmptyViewOutput, 'BELUM ADA DATA')
        && str_contains($phase8cViews, 'Lewat tenggat')
        && !str_contains($phase8cViews, '99')
        && !str_contains($phase8cViews, 'score')
        && !str_contains($phase8cViews, 'persentase')
);

$phase8cMutationSource = str_replace(
    'final class RtlController',
    'final class RtlControllerMutation',
    $phase8cSource,
    $phase8cClassCount
);
$phase8cMutationSource = str_replace(
    "'SELESAI' => [],",
    "'SELESAI' => ['BERJALAN'],",
    $phase8cMutationSource,
    $phase8cRuleCount
);
$phase8cMutationPath = tempnam(__DIR__, '.rtl_mutation_');
$phase8cMutationProbePath = tempnam(__DIR__, '.rtl_mutation_probe_');
$phase8cMutationCode = 1;
$phase8cMutationOutput = '';
$phase8cOriginalPassed = !RtlController::transitionIsAllowed('SELESAI', 'BERJALAN');
if ($phase8cMutationPath !== false && $phase8cMutationProbePath !== false
    && $phase8cClassCount === 1 && $phase8cRuleCount === 1) {
    file_put_contents($phase8cMutationPath, $phase8cMutationSource);
    file_put_contents(
        $phase8cMutationProbePath,
        "<?php\nrequire " . var_export($phase8cMutationPath, true) . ";\n"
            . "if (\\App\\Controllers\\RtlControllerMutation::transitionIsAllowed('SELESAI', 'BERJALAN')) { echo '[GAGAL] Status SELESAI dapat mundur pada mutasi.' . PHP_EOL; exit(1); }\n"
            . "echo '[LOLOS] Status SELESAI tetap final.' . PHP_EOL;\n"
    );
    $phase8cMutationCommand = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($phase8cMutationProbePath) . ' 2>&1';
    exec($phase8cMutationCommand, $phase8cMutationLines, $phase8cMutationCode);
    $phase8cMutationOutput = implode(PHP_EOL, $phase8cMutationLines);
}
foreach ([$phase8cMutationPath, $phase8cMutationProbePath] as $temporaryPath) {
    if (is_string($temporaryPath) && is_file($temporaryPath)) {
        unlink($temporaryPath);
    }
}
echo 'Bukti mutasi RTL salinan sementara: ' . $phase8cMutationOutput . ' (exit code ' . $phase8cMutationCode . '); implementasi asli: '
    . ($phase8cOriginalPassed ? '[LOLOS] Status SELESAI final.' : '[GAGAL] Status SELESAI final.') . PHP_EOL;
check(
    'Mutasi yang membuka transisi mundur benar-benar merah dan implementasi asli tetap hijau',
    $phase8cClassCount === 1
        && $phase8cRuleCount === 1
        && $phase8cMutationCode === 1
        && str_contains($phase8cMutationOutput, '[GAGAL]')
        && $phase8cOriginalPassed
);
