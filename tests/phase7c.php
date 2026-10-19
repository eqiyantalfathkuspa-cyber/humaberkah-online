<?php
declare(strict_types=1);

use App\Core\ChecklistService;
use App\Core\Db;
use App\Policies\Permissions;

$phase7cStatusCases = [
    [[0, 0, 0], 'KOSONG'],
    [[0, 0, 1], 'PERLU_PERBAIKAN'],
    [[0, 1, 0], 'ADA_BELUM_VERIFIKASI'],
    [[1, 0, 0], 'VALID'],
    [[1, 0, 1], 'VALID'],
    [[0, 1, 1], 'ADA_BELUM_VERIFIKASI'],
    [[0, 1, 0], 'ADA_BELUM_VERIFIKASI'],
];
$phase7cStatusPassed = true;
foreach ($phase7cStatusCases as [$counts, $expected]) {
    $phase7cStatusPassed = $phase7cStatusPassed
        && ChecklistService::statusFromCounts($counts[0], $counts[1], $counts[2]) === $expected;
}
check('Prioritas status mencakup semua keadaan dan campuran eviden', $phase7cStatusPassed);

$phase7cInitialMatrix = ChecklistService::matrix();
$phase7cInitialServiceCount = (int) Db::one('SELECT COUNT(*) AS n FROM services', [], 'test')['n'];
$phase7cInitialCheckpointCount = (int) Db::one('SELECT COUNT(*) AS n FROM evidence_checkpoints', [], 'test')['n'];
$phase7cInitialEvidenceCount = (int) Db::one('SELECT COUNT(*) AS n FROM evidence_items', [], 'test')['n'];
$phase7cInactiveServiceCount = (int) Db::one(
    'SELECT COUNT(*) AS n FROM services WHERE is_active = 0',
    [],
    'test'
)['n'];
$phase7cDuplicateCells = count($phase7cInitialMatrix['services']) * count($phase7cInitialMatrix['checkpoints'])
    - $phase7cInitialMatrix['cell_count'];
$phase7cInitialEmpty = $phase7cInitialEvidenceCount === 0
    && $phase7cInitialMatrix['cell_count'] === $phase7cInitialServiceCount * $phase7cInitialCheckpointCount
    && $phase7cDuplicateCells === 0
    && $phase7cInitialMatrix['summary']['KOSONG'] === $phase7cInitialMatrix['cell_count']
    && array_sum($phase7cInitialMatrix['summary']) === $phase7cInitialMatrix['cell_count'];
echo 'AUDIT CHECKLIST FIXTURE: layanan=' . $phase7cInitialServiceCount
    . '; checkpoint=' . $phase7cInitialCheckpointCount
    . '; sel=' . $phase7cInitialMatrix['cell_count']
    . '; nonaktif=' . $phase7cInactiveServiceCount
    . '; asumsi_99=' . ($phase7cInitialMatrix['cell_count'] === 99 ? 'terpenuhi' : 'DECISION REQUIRED') . PHP_EOL;
check(
    'Fixture awal menghitung hasil CROSS JOIN; semua sel KOSONG dan tidak ada pasangan duplikat',
    $phase7cInitialEmpty,
    'evidence=' . $phase7cInitialEvidenceCount . '; duplicate=' . $phase7cDuplicateCells
);

$phase7cServices = Db::all('SELECT id, code, name, is_active FROM services ORDER BY code', [], 'test');
$phase7cCheckpoints = Db::all('SELECT id, code, name FROM evidence_checkpoints ORDER BY code', [], 'test');
$phase7cAdmin = phase2bUser(
    'ADMIN',
    'phase7c-admin-' . bin2hex(random_bytes(5)),
    'Admin Checklist Uji',
    bin2hex(random_bytes(16))
);
$phase7cSiswa = phase2bUser(
    'SISWA',
    'phase7c-siswa-' . bin2hex(random_bytes(5)),
    'Siswa Checklist Uji',
    bin2hex(random_bytes(16))
);
$phase7cGuru = phase2bUser(
    'GURU',
    'phase7c-guru-' . bin2hex(random_bytes(5)),
    'Guru Checklist Uji',
    bin2hex(random_bytes(16))
);
$phase7cRoleUsers = [
    'GUEST' => null,
    'SISWA' => $phase7cSiswa,
    'GURU' => $phase7cGuru,
    'ADMIN' => $phase7cAdmin,
];
$phase7cIdentities = [];
foreach ($phase7cRoleUsers as $role => $user) {
    $phase7cIdentities[$role] = phase2bHttpIdentity($user);
}
$phase7cServiceA = $phase7cServices[0] ?? null;
$phase7cServiceB = $phase7cServices[1] ?? null;
$phase7cCheckpointA = $phase7cCheckpoints[0] ?? null;
$phase7cCheckpointB = $phase7cCheckpoints[1] ?? null;
$phase7cEvidenceIds = [];
$phase7cOriginalService = null;
$phase7cFixtureSetupValid = is_array($phase7cServiceA)
    && is_array($phase7cServiceB)
    && is_array($phase7cCheckpointA)
    && is_array($phase7cCheckpointB)
    && $phase7cInitialEvidenceCount === 0;

if ($phase7cFixtureSetupValid) {
    $phase7cOriginalService = [
        'name' => (string) $phase7cServiceA['name'],
        'is_active' => (int) $phase7cServiceA['is_active'],
    ];
    Db::run(
        'UPDATE services SET name = ?, is_active = 0 WHERE id = ?',
        ['<script>alert("checklist")</script>', (int) $phase7cServiceA['id']],
        'test'
    );
    $phase7cFixtureEvidence = [
        [(int) $phase7cServiceA['id'], (int) $phase7cCheckpointA['id'], 'DIUNGGAH'],
        [(int) $phase7cServiceA['id'], (int) $phase7cCheckpointA['id'], 'PERLU_PERBAIKAN'],
        [(int) $phase7cServiceA['id'], (int) $phase7cCheckpointB['id'], 'PERLU_PERBAIKAN'],
        [(int) $phase7cServiceB['id'], (int) $phase7cCheckpointA['id'], 'TERVERIFIKASI'],
        [(int) $phase7cServiceB['id'], (int) $phase7cCheckpointA['id'], 'PERLU_PERBAIKAN'],
    ];
    foreach ($phase7cFixtureEvidence as [$serviceId, $checkpointId, $status]) {
        Db::run(
            'INSERT INTO evidence_items (service_id, checkpoint_id, status, stored_name, original_name, mime, size_bytes, sha256, uploaded_by, verified_by, verified_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $serviceId,
                $checkpointId,
                $status,
                bin2hex(random_bytes(32)) . '.png',
                'fixture-' . strtolower($status) . '.png',
                'image/png',
                1,
                hash('sha256', $status . bin2hex(random_bytes(8))),
                (int) $phase7cAdmin['id'],
                $status === 'TERVERIFIKASI' ? (int) $phase7cAdmin['id'] : null,
                $status === 'TERVERIFIKASI' ? date('Y-m-d H:i:s') : null,
            ],
            'test'
        );
        $phase7cEvidenceIds[] = (int) Db::pdo('test')->lastInsertId();
    }
}

try {
    $phase7cMatrix = ChecklistService::matrix();
    $phase7cCells = [];
    foreach ($phase7cMatrix['services'] as $service) {
        foreach ($service['cells'] as $cell) {
            $phase7cCells[$service['id'] . ':' . $cell['checkpoint_id']] = $cell;
        }
    }
    $phase7cCellA1 = $phase7cCells[(int) $phase7cServiceA['id'] . ':' . (int) $phase7cCheckpointA['id']] ?? null;
    $phase7cCellA2 = $phase7cCells[(int) $phase7cServiceA['id'] . ':' . (int) $phase7cCheckpointB['id']] ?? null;
    $phase7cCellB1 = $phase7cCells[(int) $phase7cServiceB['id'] . ':' . (int) $phase7cCheckpointA['id']] ?? null;
    $phase7cCellB2 = $phase7cCells[(int) $phase7cServiceB['id'] . ':' . (int) $phase7cCheckpointB['id']] ?? null;
    check(
        'Eviden fixture menghitung status/jumlah per pasangan tanpa memengaruhi sel lain',
        $phase7cFixtureSetupValid
            && $phase7cCellA1 !== null
            && $phase7cCellA1['status'] === 'ADA_BELUM_VERIFIKASI'
            && $phase7cCellA1['evidence_count'] === 2
            && $phase7cCellA2 !== null
            && $phase7cCellA2['status'] === 'PERLU_PERBAIKAN'
            && $phase7cCellA2['evidence_count'] === 1
            && $phase7cCellB1 !== null
            && $phase7cCellB1['status'] === 'VALID'
            && $phase7cCellB1['evidence_count'] === 2
            && $phase7cCellB2 !== null
            && $phase7cCellB2['status'] === 'KOSONG'
            && $phase7cCellB2['evidence_count'] === 0
            && array_sum($phase7cMatrix['summary']) === $phase7cInitialMatrix['cell_count']
            && $phase7cMatrix['summary']['VALID'] === 1
            && $phase7cMatrix['summary']['ADA_BELUM_VERIFIKASI'] === 1
            && $phase7cMatrix['summary']['PERLU_PERBAIKAN'] === 1
            && $phase7cMatrix['summary']['KOSONG'] === $phase7cInitialMatrix['cell_count'] - 3
    );

    $phase7cStaticService = file_get_contents(__DIR__ . '/../app/Core/ChecklistService.php');
    $phase7cStaticView = file_get_contents(__DIR__ . '/../app/Views/pages/admin_checklist.php');
    $phase7cNoHardcodedCounts = is_string($phase7cStaticService)
        && is_string($phase7cStaticView)
        && preg_match('/\b(?:9|11|99)\b/', $phase7cStaticService . $phase7cStaticView) !== 1;
    check('ChecklistService dan tampilan tidak menanam jumlah sel tetap', $phase7cNoHardcodedCounts);

    $phase7cMatrixRequests = [];
    foreach ($phase7cRoleUsers as $role => $user) {
        $phase7cMatrixRequests[] = [
            'method' => 'GET',
            'path' => '/admin/checklist-99',
            'cookie' => $phase7cIdentities[$role]['cookie'],
        ];
    }
    $phase7cMatrixResponses = phase2bHttpRequests($phase7cMatrixRequests);
    $phase7cMatrixAccessPassed = count($phase7cMatrixResponses) === 4;
    echo PHP_EOL . "MATRIKS ROLE x RUTE LANGKAH 7C\nRole | GET /admin/checklist-99 | Ability | Hasil | Harapan" . PHP_EOL;
    foreach (array_keys($phase7cRoleUsers) as $index => $role) {
        $responseStatus = (int) ($phase7cMatrixResponses[$index]['status'] ?? 0);
        $expectedStatus = $role === 'ADMIN' ? 200 : ($role === 'GUEST' ? 302 : 403);
        $ability = Permissions::can($role, 'admin.checklist.view');
        $phase7cMatrixAccessPassed = $phase7cMatrixAccessPassed
            && $responseStatus === $expectedStatus
            && $ability === ($role === 'ADMIN');
        echo $role . ' | GET /admin/checklist-99 | admin.checklist.view | '
            . $responseStatus . ' | ' . $expectedStatus . PHP_EOL;
    }
    $phase7cPostResponse = phase2bHttpRequests([[
        'method' => 'POST',
        'path' => '/admin/checklist-99',
        'cookie' => $phase7cIdentities['ADMIN']['cookie'],
        'post' => ['_csrf' => $phase7cIdentities['ADMIN']['csrf']],
    ]]);
    $phase7cMatrixAccessPassed = $phase7cMatrixAccessPassed
        && ($phase7cPostResponse[0]['status'] ?? 0) === 405;
    check(
        'Matriks checklist ADMIN-only dan tidak mempunyai rute POST',
        $phase7cMatrixAccessPassed,
        'POST status=' . (string) ($phase7cPostResponse[0]['status'] ?? 0)
    );

    $phase7cAdminPage = phase2bHttpRequests([[
        'method' => 'GET',
        'path' => '/admin/checklist-99',
        'cookie' => $phase7cIdentities['ADMIN']['cookie'],
    ]]);
    $phase7cAdminPageBody = (string) ($phase7cAdminPage[0]['body'] ?? '');
    $phase7cExpectedFilter = '/admin/eviden?service_id=' . (int) $phase7cServiceA['id']
        . '&amp;checkpoint_id=' . (int) $phase7cCheckpointA['id'];
    $phase7cFilterResponse = phase2bHttpRequests([[
        'method' => 'GET',
        'path' => '/admin/eviden?service_id=' . (int) $phase7cServiceA['id']
            . '&checkpoint_id=' . (int) $phase7cCheckpointA['id'],
        'cookie' => $phase7cIdentities['ADMIN']['cookie'],
    ]]);
    check(
        'Entry point asli merender matriks, meng-escape nama layanan, menandai nonaktif, dan menautkan filter eviden',
        ($phase7cAdminPage[0]['status'] ?? 0) === 200
            && str_contains($phase7cAdminPageBody, '&lt;script&gt;')
            && !str_contains($phase7cAdminPageBody, '<script>alert("checklist")</script>')
            && str_contains($phase7cAdminPageBody, '(nonaktif)')
            && str_contains($phase7cAdminPageBody, $phase7cExpectedFilter)
            && preg_match('/ADA_BELUM_VERIFIKASI\s*\(2\)/s', $phase7cAdminPageBody) === 1
            && preg_match('/PERLU_PERBAIKAN\s*\(1\)/s', $phase7cAdminPageBody) === 1
            && ($phase7cFilterResponse[0]['status'] ?? 0) === 200
            && str_contains($phase7cAdminPageBody, 'Instrumen kendali internal; bukan indikator resmi LKE/PMPZI.'),
        'status=' . (string) ($phase7cAdminPage[0]['status'] ?? 0)
            . '; escaped=' . (str_contains($phase7cAdminPageBody, '&lt;script&gt;') ? 'yes' : 'no')
            . '; inactive=' . (str_contains($phase7cAdminPageBody, '(nonaktif)') ? 'yes' : 'no')
            . '; link=' . (str_contains($phase7cAdminPageBody, $phase7cExpectedFilter) ? 'yes' : 'no')
            . '; uploaded=' . (preg_match('/ADA_BELUM_VERIFIKASI\s*\(2\)/s', $phase7cAdminPageBody) === 1 ? 'yes' : 'no')
            . '; repair=' . (preg_match('/PERLU_PERBAIKAN\s*\(1\)/s', $phase7cAdminPageBody) === 1 ? 'yes' : 'no')
            . '; filter status=' . (string) ($phase7cFilterResponse[0]['status'] ?? 0)
    );

    $phase7cMutantClassPath = tempnam(__DIR__, '.checklist_mutant_');
    $phase7cMutantProbePath = tempnam(__DIR__, '.checklist_probe_');
    $phase7cMutantPassed = false;
    $phase7cMutantOutput = [];
    $phase7cMutantExit = -1;
    try {
        $phase7cSource = file_get_contents(__DIR__ . '/../app/Core/ChecklistService.php');
        $phase7cCorrectPriority = <<<'PHP'
        if ($verified > 0) {
            return 'VALID';
        }
        if ($uploaded > 0) {
PHP;
        $phase7cMutatedPriority = <<<'PHP'
        if ($uploaded > 0) {
            return 'ADA_BELUM_VERIFIKASI';
        }
        if ($verified > 0) {
PHP;
        $phase7cMutatedClass = is_string($phase7cSource)
            ? str_replace(
                ['final class ChecklistService', $phase7cCorrectPriority],
                ['final class ChecklistServiceMutant', $phase7cMutatedPriority],
                $phase7cSource,
                $phase7cReplacementCount
            )
            : '';
        $phase7cMutationValid = is_string($phase7cSource)
            && $phase7cReplacementCount === 2
            && $phase7cMutantClassPath !== false
            && $phase7cMutantProbePath !== false
            && file_put_contents($phase7cMutantClassPath, $phase7cMutatedClass) !== false;
        if ($phase7cMutationValid) {
            $phase7cProbe = "<?php\nrequire " . var_export($phase7cMutantClassPath, true) . ";\n"
                . "if (\\App\\Core\\ChecklistServiceMutant::statusFromCounts(1, 1, 0) !== 'VALID') { "
                . "echo '[GAGAL] Uji prioritas VALID: mutasi ADA_BELUM_VERIFIKASI mengalahkan VALID.' . PHP_EOL; exit(1); }\n"
                . "echo '[LOLOS] Uji prioritas VALID sesuai harapan.' . PHP_EOL;\n";
            $phase7cMutationValid = file_put_contents($phase7cMutantProbePath, $phase7cProbe) !== false;
        }
        if ($phase7cMutationValid) {
            exec(
                escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($phase7cMutantProbePath) . ' 2>&1',
                $phase7cMutantOutput,
                $phase7cMutantExit
            );
            $phase7cMutantPassed = $phase7cMutantExit === 1
                && str_contains(implode(PHP_EOL, $phase7cMutantOutput), '[GAGAL] Uji prioritas VALID');
        }
    } finally {
        if (is_string($phase7cMutantClassPath) && is_file($phase7cMutantClassPath)) {
            unlink($phase7cMutantClassPath);
        }
        if (is_string($phase7cMutantProbePath) && is_file($phase7cMutantProbePath)) {
            unlink($phase7cMutantProbePath);
        }
    }
    $phase7cOriginalRecovered = ChecklistService::statusFromCounts(1, 1, 0) === 'VALID';
    echo 'Bukti mutasi salinan sementara: '
        . ($phase7cMutantOutput[0] ?? '[GAGAL] Salinan mutant tidak menghasilkan keluaran tes.')
        . ' (exit code ' . $phase7cMutantExit . '); setelah pemulihan berkas kerja: '
        . ($phase7cOriginalRecovered ? '[LOLOS] Prioritas asli tetap VALID.' : '[GAGAL] Prioritas asli berubah.') . PHP_EOL;
    check(
        'Mutasi prioritas pada salinan ChecklistService menghasilkan merah lalu implementasi asli tetap hijau',
        $phase7cMutantPassed && $phase7cOriginalRecovered,
        'replacement count=' . (string) ($phase7cReplacementCount ?? 'n/a')
            . '; exit=' . $phase7cMutantExit
            . '; output=' . implode(PHP_EOL, $phase7cMutantOutput)
    );
} finally {
    if ($phase7cEvidenceIds !== []) {
        $placeholders = implode(',', array_fill(0, count($phase7cEvidenceIds), '?'));
        Db::run(
            'DELETE FROM evidence_items WHERE id IN (' . $placeholders . ')',
            $phase7cEvidenceIds,
            'test'
        );
    }
    if (is_array($phase7cServiceA) && is_array($phase7cOriginalService)) {
        Db::run(
            'UPDATE services SET name = ?, is_active = ? WHERE id = ?',
            [
                $phase7cOriginalService['name'],
                $phase7cOriginalService['is_active'],
                (int) $phase7cServiceA['id'],
            ],
            'test'
        );
    }
}
