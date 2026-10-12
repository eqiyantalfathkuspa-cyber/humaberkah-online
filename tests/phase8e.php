<?php
declare(strict_types=1);

use App\Core\Db;
use App\Core\MadaniReportService;
use App\Policies\Permissions;

function phase8eUser(string $role, string $suffix): array
{
    $identifier = 'p8e-' . $suffix . '-' . bin2hex(random_bytes(4));
    $token = bin2hex(random_bytes(32));
    Db::run(
        'INSERT INTO users (role_id, identifier, full_name, password_hash, is_active, auth_token) VALUES ((SELECT id FROM roles WHERE code = ?), ?, ?, ?, 1, ?)',
        [$role, $identifier, 'Uji ' . $role, password_hash(bin2hex(random_bytes(20)), PASSWORD_DEFAULT), $token],
        'test'
    );
    $user = Db::one(
        'SELECT id FROM users WHERE identifier = ?',
        [$identifier],
        'test'
    );
    return [
        'id' => (int) $user['id'],
        'role' => $role,
        'identifier' => $identifier,
        'token' => $token,
    ];
}

function phase8eIdentity(?array $user): array
{
    $previousId = session_id();
    session_write_close();
    $id = session_create_id();
    session_id($id);
    session_start();
    $_SESSION = [];
    if ($user !== null) {
        $_SESSION['user'] = [
            'id' => $user['id'],
            'role' => $user['role'],
            'role_code' => $user['role'],
            'identifier' => $user['identifier'],
            'full_name' => 'Uji ' . $user['role'],
            'auth_token' => $user['token'],
        ];
        $_SESSION['login_time'] = time();
        $_SESSION['last_activity'] = time();
    }
    $csrf = App\Core\Csrf::token();
    session_write_close();
    session_id($previousId);
    session_start();
    return ['cookie' => session_name() . '=' . $id, 'csrf' => $csrf];
}

function phase8eHttpRequests(array $requests): array
{
    $server = stream_socket_server('tcp://127.0.0.1:0', $error, $message);
    if ($server === false) {
        return [[], 'Unable to reserve local test port.'];
    }
    $address = stream_socket_get_name($server, false);
    fclose($server);
    if (!is_string($address)) {
        return [[], 'Unable to resolve local test port.'];
    }

    $process = proc_open(
        [PHP_BINARY, '-S', $address, __DIR__ . '/../public/index.php'],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        __DIR__,
        array_replace(getenv(), [
            'APP_ENV' => 'test',
            'HUMA_TEST_DATABASE_NAME' => getenv('HUMA_TEST_DATABASE_NAME'),
            'HUMA_TEST_FIXTURE_TOKEN' => getenv('HUMA_TEST_FIXTURE_TOKEN'),
        ])
    );
    if (!is_resource($process)) {
        return [[], 'Unable to start public/index.php child process.'];
    }
    fclose($pipes[0]);
    $responses = [];
    try {
        foreach ($requests as $request) {
            $socket = false;
            for ($attempt = 0; $attempt < 30 && $socket === false; $attempt++) {
                $socket = @stream_socket_client('tcp://' . $address, $connectError, $connectMessage, 1);
                if ($socket === false) {
                    usleep(25000);
                }
            }
            if ($socket === false) {
                $responses[] = ['status' => 0, 'headers' => [], 'body' => ''];
                continue;
            }
            stream_set_timeout($socket, 8);
            fwrite(
                $socket,
                'GET ' . $request['path'] . " HTTP/1.1\r\n"
                    . 'Host: ' . $address . "\r\n"
                    . 'Cookie: ' . $request['cookie'] . "\r\n"
                    . "Connection: close\r\n\r\n"
            );
            $raw = (string) stream_get_contents($socket);
            fclose($socket);
            [$headerText, $body] = array_pad(explode("\r\n\r\n", $raw, 2), 2, '');
            $headers = explode("\r\n", $headerText);
            preg_match('/^HTTP\/\S+\s+(\d+)/', (string) ($headers[0] ?? ''), $status);
            $responses[] = [
                'status' => (int) ($status[1] ?? 0),
                'headers' => $headers,
                'body' => $body,
            ];
        }
    } finally {
        proc_terminate($process);
        $serverOutput = (string) stream_get_contents($pipes[1]);
        $serverError = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);
    }
    return [$responses, $serverOutput . $serverError];
}

function phase8eCsvMutationProbe(string $temporaryDirectory): array
{
    $sourcePath = __DIR__ . '/../app/Core/MadaniReportService.php';
    $source = file_get_contents($sourcePath);
    $original = "return preg_match('/^[=+\\-@]/', \$value) === 1 ? \"'\" . \$value : \$value;";
    $mutated = 'return $value;';
    if (!is_string($source) || !str_contains($source, $original)) {
        return [2, 'Mutation target was not found.'];
    }
    $mutantSource = str_replace($original, $mutated, $source);
    $mutantPath = $temporaryDirectory . DIRECTORY_SEPARATOR . 'MadaniReportService.php';
    $probePath = $temporaryDirectory . DIRECTORY_SEPARATOR . 'probe.php';
    if (file_put_contents($mutantPath, $mutantSource) === false) {
        return [2, 'Unable to write temporary mutant.'];
    }
    $probe = <<<'PHP'
<?php
declare(strict_types=1);
require __DIR__ . '/MadaniReportService.php';
$csv = \App\Core\MadaniReportService::csv([
    'services' => [['code' => 'MUT', 'name' => '=FORMULA', 'evaluation_count' => 0, 'before_after_count' => 0]],
    'rtl_statuses' => [],
    'rtl_overdue' => 0,
    'evidence_statuses' => [],
    'checklist_statuses' => [],
    'application_statuses' => [],
    'complaint_statuses' => [],
    'filters' => ['period_start' => null, 'period_end' => null],
]);
if (str_contains($csv, "'=FORMULA")) {
    echo "[LOLOS] Mutated exporter unexpectedly neutralized formula input.\n";
    exit(0);
}
echo "[GAGAL] CSV cell formula prefix was not neutralized.\n";
exit(1);
PHP;
    if (file_put_contents($probePath, $probe) === false) {
        return [2, 'Unable to write temporary mutation probe.'];
    }
    $process = proc_open(
        [PHP_BINARY, $probePath],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        $temporaryDirectory
    );
    if (!is_resource($process)) {
        return [2, 'Unable to start temporary mutation process.'];
    }
    fclose($pipes[0]);
    $output = (string) stream_get_contents($pipes[1]) . (string) stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    return [proc_close($process), $output];
}

$phase8eDate = '2001-05-01';
$phase8eOldDate = '1999-01-01';
$phase8eSuffix = bin2hex(random_bytes(8));
$phase8eAdmin = phase8eUser('ADMIN', $phase8eSuffix . '-admin');
$phase8eStudent = phase8eUser('SISWA', $phase8eSuffix . '-student');
$phase8eTeacher = phase8eUser('GURU', $phase8eSuffix . '-teacher');
$phase8eCategory = 'P8E-' . strtoupper(substr($phase8eSuffix, 0, 10));
Db::run('INSERT INTO service_categories (code, name) VALUES (?, ?)', [$phase8eCategory, 'Kategori laporan uji'], 'test');
$phase8eCategoryId = (int) Db::one('SELECT id FROM service_categories WHERE code = ?', [$phase8eCategory], 'test')['id'];

$phase8eServices = [];
$phase8eNames = [
    'alpha' => '<script>alert("madani-xss")</script>',
    'formula_equal' => '=SUM(1,1)',
    'formula_plus' => '+cmd',
    'formula_minus' => '-cmd',
    'formula_at' => '@cmd',
    'empty' => 'Layanan tanpa catatan',
    'other' => 'Layanan pembanding',
];
foreach (array_values($phase8eNames) as $index => $name) {
    $code = 'P8E-' . strtoupper(substr($phase8eSuffix, 0, 6)) . '-' . str_pad((string) $index, 2, '0', STR_PAD_LEFT);
    Db::run(
        'INSERT INTO services (code, name, category_id, mode, is_active) VALUES (?, ?, ?, ?, 1)',
        [$code, $name, $phase8eCategoryId, 'FORMULIR'],
        'test'
    );
    $key = array_keys($phase8eNames)[$index];
    $phase8eServices[$key] = [
        'id' => (int) Db::one('SELECT id FROM services WHERE code = ?', [$code], 'test')['id'],
        'code' => $code,
        'name' => $name,
    ];
}
$phase8eA = $phase8eServices['alpha'];
$phase8eB = $phase8eServices['other'];
$phase8eEmpty = $phase8eServices['empty'];
$phase8eNarrativeMarker = 'PRIVATE_MADANI_CONTENT_' . $phase8eSuffix;

foreach ([
    [$phase8eA['id'], $phase8eDate, $phase8eDate],
    [$phase8eA['id'], $phase8eDate, $phase8eDate],
    [$phase8eA['id'], $phase8eOldDate, $phase8eOldDate],
    [$phase8eB['id'], $phase8eDate, $phase8eDate],
] as $index => [$serviceId, $start, $end]) {
    Db::run(
        'INSERT INTO madani_evaluations (service_id, period_start, period_end, summary, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?)',
        [$serviceId, $start, $end, $phase8eNarrativeMarker . '-evaluation-' . $index, $phase8eAdmin['id'], $phase8eDate . ' 12:00:00'],
        'test'
    );
}
$phase8eEvaluationIds = Db::all(
    'SELECT id, service_id FROM madani_evaluations WHERE summary LIKE ? ORDER BY id',
    [$phase8eNarrativeMarker . '-evaluation-%'],
    'test'
);
$phase8eEvaluationByService = [];
foreach ($phase8eEvaluationIds as $row) {
    $phase8eEvaluationByService[(int) $row['service_id']][] = (int) $row['id'];
}

Db::run(
    'INSERT INTO madani_before_after (service_id, title, before_text, before_observed_on, after_text, after_observed_on, source_note, created_by, created_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
    [$phase8eA['id'], 'Title A', 'Before', $phase8eDate, 'After', $phase8eDate, 'Source', $phase8eAdmin['id'], $phase8eDate . ' 12:00:00'],
    'test'
);
Db::run(
    'INSERT INTO madani_before_after (service_id, title, before_text, before_observed_on, after_text, after_observed_on, source_note, created_by, created_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
    [$phase8eA['id'], 'Title old', 'Before', $phase8eOldDate, 'After', $phase8eOldDate, 'Source', $phase8eAdmin['id'], $phase8eOldDate . ' 12:00:00'],
    'test'
);

$phase8eRtlIds = [];
foreach ([
    ['DIRENCANAKAN', '2001-04-30'],
    ['DIRENCANAKAN', '2001-04-30'],
    ['BERJALAN', date('Y-m-d', strtotime('+10 days'))],
] as $index => [$status, $dueDate]) {
    Db::run(
        'INSERT INTO madani_rtl (evaluation_id, action_description, responsible_name, due_date, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?)',
        [$phase8eEvaluationByService[$phase8eA['id']][0], 'RTL ' . $index, 'Uji fiktif', $dueDate, $phase8eAdmin['id'], $phase8eDate . ' 12:00:00'],
        'test'
    );
    $rtlId = (int) Db::one('SELECT LAST_INSERT_ID() AS id', [], 'test')['id'];
    $phase8eRtlIds[] = $rtlId;
    Db::run(
        'INSERT INTO madani_rtl_status_log (rtl_id, status, changed_by, created_at) VALUES (?, ?, ?, ?)',
        [$rtlId, 'DIRENCANAKAN', $phase8eAdmin['id'], $phase8eDate . ' 12:00:00'],
        'test'
    );
    if ($index === 1) {
        Db::run(
            'INSERT INTO madani_rtl_status_log (rtl_id, status, changed_by, created_at) VALUES (?, ?, ?, ?)',
            [$rtlId, 'SELESAI', $phase8eAdmin['id'], $phase8eDate . ' 13:00:00'],
            'test'
        );
    } elseif ($index === 2) {
        Db::run(
            'INSERT INTO madani_rtl_status_log (rtl_id, status, changed_by, created_at) VALUES (?, ?, ?, ?)',
            [$rtlId, 'BERJALAN', $phase8eAdmin['id'], $phase8eDate . ' 13:00:00'],
            'test'
        );
    }
}

$phase8eCheckpoints = Db::all('SELECT id FROM evidence_checkpoints ORDER BY id LIMIT 3', [], 'test');
$phase8eEvidenceStatuses = ['TERVERIFIKASI', 'DIUNGGAH', 'PERLU_PERBAIKAN'];
foreach ($phase8eCheckpoints as $index => $checkpoint) {
    Db::run(
        'INSERT INTO evidence_items (service_id, checkpoint_id, status, stored_name, mime, size_bytes, sha256, uploaded_by, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [
            $phase8eA['id'],
            (int) $checkpoint['id'],
            $phase8eEvidenceStatuses[$index],
            'p8e-' . $phase8eSuffix . '-' . $index . '.png',
            'image/png',
            1,
            hash('sha256', $phase8eSuffix . $index),
            $phase8eAdmin['id'],
            $phase8eDate . ' 23:59:59',
        ],
        'test'
    );
}

foreach ([
    ['DIAJUKAN', $phase8eDate . ' 23:59:59'],
    ['SELESAI', $phase8eDate . ' 10:00:00'],
    ['DITOLAK', $phase8eOldDate . ' 10:00:00'],
] as $index => [$status, $createdAt]) {
    Db::run(
        'INSERT INTO applications (service_id, applicant_user_id, tracking_code, request_text, status, created_at) VALUES (?, ?, ?, ?, ?, ?)',
        [$phase8eA['id'], $phase8eStudent['id'], strtoupper(substr(hash('sha256', $phase8eSuffix . $index), 0, 23)), $phase8eNarrativeMarker . '-application-' . $index, $status, $createdAt],
        'test'
    );
}

foreach ([
    ['DITERIMA', $phase8eDate . ' 23:59:59'],
    ['SELESAI', $phase8eDate . ' 10:00:00'],
    ['DITOLAK', $phase8eOldDate . ' 10:00:00'],
] as $index => [$status, $createdAt]) {
    Db::run(
        'INSERT INTO complaints (ticket_no, track_token, jenis, isi, status, created_at) VALUES (?, ?, ?, ?, ?, ?)',
        [
            'P8E' . strtoupper(substr($phase8eSuffix, 0, 8)) . str_pad((string) $index, 3, '0', STR_PAD_LEFT),
            strtoupper(substr(hash('sha256', $phase8eSuffix . 'token' . $index), 0, 23)),
            'PENGADUAN',
            $phase8eNarrativeMarker . '-complaint-' . $index,
            $status,
            $createdAt,
        ],
        'test'
    );
}

$phase8eFilters = MadaniReportService::validateFilters([
    'period_start' => $phase8eDate,
    'period_end' => $phase8eDate,
    'service_id' => (string) $phase8eA['id'],
]);
$phase8eReport = MadaniReportService::report($phase8eFilters);
$phase8eExpectedEvaluationCount = (int) Db::one(
    'SELECT COUNT(*) AS total FROM madani_evaluations WHERE service_id = ? AND period_start <= ? AND period_end >= ?',
    [$phase8eA['id'], $phase8eDate, $phase8eDate],
    'test'
)['total'];
$phase8eExpectedBeforeAfterCount = (int) Db::one(
    'SELECT COUNT(*) AS total FROM madani_before_after WHERE service_id = ? AND after_observed_on BETWEEN ? AND ?',
    [$phase8eA['id'], $phase8eDate, $phase8eDate],
    'test'
)['total'];
$phase8eExpectedApplications = Db::all(
    'SELECT status, COUNT(*) AS total FROM applications WHERE service_id = ? AND created_at >= ? AND created_at < ? GROUP BY status',
    [$phase8eA['id'], $phase8eDate . ' 00:00:00', '2001-05-02 00:00:00'],
    'test'
);
$phase8eExpectedApplicationCounts = array_fill_keys(array_keys(App\Core\ApplicationWorkflow::STATUS_LABELS), 0);
foreach ($phase8eExpectedApplications as $row) {
    $phase8eExpectedApplicationCounts[$row['status']] = (int) $row['total'];
}
$phase8eExpectedComplaints = Db::all(
    'SELECT status, COUNT(*) AS total FROM complaints WHERE created_at >= ? AND created_at < ? GROUP BY status',
    [$phase8eDate . ' 00:00:00', '2001-05-02 00:00:00'],
    'test'
);
$phase8eExpectedComplaintCounts = array_fill_keys(array_keys(App\Core\ComplaintWorkflow::STATUS_LABELS), 0);
foreach ($phase8eExpectedComplaints as $row) {
    $phase8eExpectedComplaintCounts[$row['status']] = (int) $row['total'];
}
check(
    'Data laporan cocok hitungan manual per layanan dan batas tanggal inklusif',
    count($phase8eReport['services']) === 1
        && (int) $phase8eReport['services'][0]['evaluation_count'] === $phase8eExpectedEvaluationCount
        && $phase8eExpectedEvaluationCount === 2
        && (int) $phase8eReport['services'][0]['before_after_count'] === $phase8eExpectedBeforeAfterCount
        && $phase8eExpectedBeforeAfterCount === 1
        && $phase8eReport['application_statuses'] === $phase8eExpectedApplicationCounts
        && $phase8eReport['complaint_statuses'] === $phase8eExpectedComplaintCounts
);

$phase8eRtlManualRows = Db::all(
    'SELECT current_status.status, r.due_date
     FROM madani_rtl AS r
     INNER JOIN madani_evaluations AS ev ON ev.id = r.evaluation_id
     INNER JOIN madani_rtl_status_log AS current_status
       ON current_status.rtl_id = r.id
      AND current_status.id = (SELECT MAX(latest.id) FROM madani_rtl_status_log AS latest WHERE latest.rtl_id = r.id)
     WHERE ev.service_id = ? AND r.created_at >= ? AND r.created_at < ?',
    [$phase8eA['id'], $phase8eDate . ' 00:00:00', '2001-05-02 00:00:00'],
    'test'
);
$phase8eExpectedRtl = ['DIRENCANAKAN' => 0, 'BERJALAN' => 0, 'SELESAI' => 0];
$phase8eExpectedOverdue = 0;
foreach ($phase8eRtlManualRows as $row) {
    $phase8eExpectedRtl[$row['status']]++;
    if ($row['status'] !== 'SELESAI' && $row['due_date'] < date('Y-m-d')) {
        $phase8eExpectedOverdue++;
    }
}
check(
    'RTL menghitung status terbaru dan hanya RTL belum selesai yang lewat tenggat',
    $phase8eReport['rtl_statuses'] === $phase8eExpectedRtl
        && $phase8eReport['rtl_statuses'] === ['DIRENCANAKAN' => 1, 'BERJALAN' => 1, 'SELESAI' => 1]
        && $phase8eReport['rtl_overdue'] === $phase8eExpectedOverdue
        && $phase8eExpectedOverdue === 1,
    'laporan=' . json_encode([$phase8eReport['rtl_statuses'], $phase8eReport['rtl_overdue']])
        . '; hitung_manual=' . json_encode([$phase8eExpectedRtl, $phase8eExpectedOverdue])
);

$phase8eEvidenceManual = Db::all(
    'SELECT status, COUNT(*) AS total FROM evidence_items WHERE service_id = ? AND created_at >= ? AND created_at < ? GROUP BY status',
    [$phase8eA['id'], $phase8eDate . ' 00:00:00', '2001-05-02 00:00:00'],
    'test'
);
$phase8eExpectedEvidenceCounts = array_fill_keys(['DIUNGGAH', 'TERVERIFIKASI', 'PERLU_PERBAIKAN'], 0);
foreach ($phase8eEvidenceManual as $row) {
    $phase8eExpectedEvidenceCounts[$row['status']] = (int) $row['total'];
}
$phase8eExpectedCells = (int) Db::one('SELECT COUNT(*) AS total FROM evidence_checkpoints', [], 'test')['total'];
check(
    'Eviden dan checklist memakai status aktual serta checklist snapshot',
    $phase8eReport['evidence_statuses'] === $phase8eExpectedEvidenceCounts
        && $phase8eReport['evidence_statuses'] === ['DIUNGGAH' => 1, 'TERVERIFIKASI' => 1, 'PERLU_PERBAIKAN' => 1]
        && $phase8eReport['checklist_statuses']['VALID'] === 1
        && $phase8eReport['checklist_statuses']['ADA_BELUM_VERIFIKASI'] === 1
        && $phase8eReport['checklist_statuses']['PERLU_PERBAIKAN'] === 1
        && $phase8eReport['checklist_statuses']['KOSONG'] === $phase8eExpectedCells - 3
);

$phase8eEmptyReport = MadaniReportService::report(MadaniReportService::validateFilters([
    'period_start' => $phase8eOldDate,
    'period_end' => $phase8eOldDate,
    'service_id' => (string) $phase8eEmpty['id'],
]));
check(
    'Laporan tanpa observasi menunjukkan hitungan nol dan BELUM ADA DATA untuk metrik turunan',
    (int) $phase8eEmptyReport['services'][0]['evaluation_count'] === 0
        && (int) $phase8eEmptyReport['services'][0]['before_after_count'] === 0
        && $phase8eEmptyReport['rtl_overdue'] === 0
        && array_sum($phase8eEmptyReport['evidence_statuses']) === 0
        && array_sum($phase8eEmptyReport['application_statuses']) === 0
        && str_contains(file_get_contents(__DIR__ . '/../app/Views/pages/admin_madani.php'), 'BELUM ADA DATA')
);

$phase8eInvalidFilters = [
    ['period_start' => '2001-05-02', 'period_end' => '2001-05-01'],
    ['period_start' => '2001-02-30', 'period_end' => '2001-03-01'],
    ['period_start' => '2001-01-01', 'period_end' => '2002-01-02'],
    ['period_start' => '2001-05-01', 'period_end' => ''],
    ['service_id' => '99999999999999999999'],
    ['service_id' => '01'],
    ['service_id' => '999999999'],
];
$phase8eInvalidRejected = true;
foreach ($phase8eInvalidFilters as $invalidFilter) {
    try {
        MadaniReportService::validateFilters($invalidFilter);
        $phase8eInvalidRejected = false;
    } catch (InvalidArgumentException) {
    }
}
$phase8eValid366Days = true;
try {
    MadaniReportService::validateFilters(['period_start' => '2001-01-01', 'period_end' => '2002-01-01']);
} catch (InvalidArgumentException) {
    $phase8eValid366Days = false;
}
check(
    'Filter menolak tanggal terbalik/tidak valid/rentang lebih dari 366 hari dan layanan atau ID tidak valid',
    $phase8eInvalidRejected && $phase8eValid366Days
);

$phase8eCsv = MadaniReportService::csv(MadaniReportService::report(
    MadaniReportService::validateFilters([])
));
$phase8eForbiddenHeaders = ['summary', 'narasi', 'kontak', 'nama_berkas', 'identitas', 'before_text', 'after_text'];
$phase8eCsvSafe = str_contains($phase8eCsv, 'metrik,kode_layanan,nama_layanan,status,jumlah,tanggal_mulai,tanggal_akhir')
    && str_contains($phase8eCsv, "'=SUM(1,1)")
    && str_contains($phase8eCsv, "'+cmd")
    && str_contains($phase8eCsv, "'-cmd")
    && str_contains($phase8eCsv, "'@cmd")
    && !str_contains($phase8eCsv, $phase8eNarrativeMarker);
foreach ($phase8eForbiddenHeaders as $forbiddenHeader) {
    $phase8eCsvSafe = $phase8eCsvSafe && !str_contains(strtolower($phase8eCsv), $forbiddenHeader);
}
check('CSV memiliki header agregat, formula netral, dan tanpa kolom/isi sensitif', $phase8eCsvSafe);

$phase8eTemporaryDirectory = __DIR__ . DIRECTORY_SEPARATOR . '.phase8e-' . bin2hex(random_bytes(8));
mkdir($phase8eTemporaryDirectory);
[$phase8eMutationExit, $phase8eMutationOutput] = phase8eCsvMutationProbe($phase8eTemporaryDirectory);
echo '[MUTASI PROSES ANAK exit=' . $phase8eMutationExit . '] ' . trim($phase8eMutationOutput) . PHP_EOL;
@unlink($phase8eTemporaryDirectory . DIRECTORY_SEPARATOR . 'probe.php');
@unlink($phase8eTemporaryDirectory . DIRECTORY_SEPARATOR . 'MadaniReportService.php');
@rmdir($phase8eTemporaryDirectory);
check(
    'Bukti mutasi: penghapusan netralisasi formula gagal merah di proses anak, implementasi kerja tetap utuh',
    $phase8eMutationExit === 1 && str_contains($phase8eMutationOutput, '[GAGAL]')
);

$phase8eSources = [
    file_get_contents(__DIR__ . '/../app/Core/MadaniReportService.php'),
    file_get_contents(__DIR__ . '/../app/Views/pages/admin_madani.php'),
];
$phase8eNoFixedMetricCount = true;
foreach ($phase8eSources as $source) {
    $phase8eNoFixedMetricCount = $phase8eNoFixedMetricCount
        && preg_match('/(?:count|total)\s*=>\s*[1-9][0-9]*/i', (string) $source) !== 1
        && preg_match('/<strong>\s*[1-9][0-9]*\s*<\/strong>/', (string) $source) !== 1;
}
$phase8eServiceSource = (string) $phase8eSources[0];
$phase8eReadOnly = preg_match('/\b(?:INSERT|UPDATE|DELETE)\b/i', $phase8eServiceSource) !== 1;
$phase8eRouterSource = (string) file_get_contents(__DIR__ . '/../app/Core/Router.php');
$phase8ePostRoutes = explode("'POST' => [", $phase8eRouterSource, 2)[1] ?? '';
$phase8eNoPostRoute = !str_contains($phase8ePostRoutes, "'/admin/madani'")
    && !str_contains($phase8ePostRoutes, "'/admin/madani/ekspor'");
check(
    'Pencarian pola menolak angka metrik tetap, operasi tulis, dan rute POST MADANI',
    $phase8eNoFixedMetricCount && $phase8eReadOnly && $phase8eNoPostRoute
);

$phase8eRouteCases = [];
$phase8eIdentities = [
    'GUEST' => phase8eIdentity(null),
    'SISWA' => phase8eIdentity($phase8eStudent),
    'GURU' => phase8eIdentity($phase8eTeacher),
    'ADMIN' => phase8eIdentity($phase8eAdmin),
];
$phase8eSessionRoles = [];
$phase8ePreviousSessionId = session_id();
session_write_close();
foreach ($phase8eIdentities as $role => $identity) {
    $identitySessionId = substr($identity['cookie'], strpos($identity['cookie'], '=') + 1);
    session_id($identitySessionId);
    session_start();
    $resolvedUser = App\Core\Auth::user();
    $phase8eSessionRoles[$role] = $resolvedUser['role_code'] ?? null;
    session_write_close();
}
session_id($phase8ePreviousSessionId);
session_start();
foreach ($phase8eIdentities as $role => $identity) {
    foreach (['/admin/madani', '/admin/madani/ekspor'] as $path) {
        $expected = $role === 'ADMIN' ? 200 : ($role === 'GUEST' ? 302 : 403);
        $phase8eRouteCases[] = [
            'role' => $role,
            'path' => $path,
            'expected' => $expected,
            'cookie' => $identity['cookie'],
        ];
    }
}
$phase8eRouteCases[] = [
    'role' => 'ADMIN',
    'path' => '/admin/madani?period_start=' . rawurlencode($phase8eOldDate)
        . '&period_end=' . rawurlencode($phase8eOldDate)
        . '&service_id=' . rawurlencode((string) $phase8eEmpty['id']),
    'expected' => 200,
    'cookie' => $phase8eIdentities['ADMIN']['cookie'],
];
$phase8eRouteCases[] = [
    'role' => 'ADMIN',
    'path' => '/admin/madani?period_start=2001-05-02&period_end=2001-05-01',
    'expected' => 422,
    'cookie' => $phase8eIdentities['ADMIN']['cookie'],
];
[$phase8eHttpResponses, $phase8eHttpError] = phase8eHttpRequests(array_map(
    static fn (array $case): array => ['path' => $case['path'], 'cookie' => $case['cookie']],
    $phase8eRouteCases
));
$phase8eRouteTable = "| Role | GET /admin/madani | GET /admin/madani/ekspor |\n|---|---:|---:|\n";
$phase8eRouteMatrixPassed = count($phase8eHttpResponses) === count($phase8eRouteCases);
foreach (['GUEST', 'SISWA', 'GURU', 'ADMIN'] as $roleIndex => $role) {
    $index = $roleIndex * 2;
    $dashboardStatus = (int) ($phase8eHttpResponses[$index]['status'] ?? 0);
    $exportStatus = (int) ($phase8eHttpResponses[$index + 1]['status'] ?? 0);
    $phase8eRouteTable .= '| ' . $role . ' | ' . $dashboardStatus . ' | ' . $exportStatus . " |\n";
    $expected = $role === 'ADMIN' ? 200 : ($role === 'GUEST' ? 302 : 403);
    $phase8eRouteMatrixPassed = $phase8eRouteMatrixPassed
        && $dashboardStatus === $expected
        && $exportStatus === $expected;
}
echo $phase8eRouteTable;
$phase8eAdminDashboard = $phase8eHttpResponses[6] ?? [];
$phase8eAdminExport = $phase8eHttpResponses[7] ?? [];
$phase8eAdminEmptyDashboard = $phase8eHttpResponses[8] ?? [];
$phase8eAdminInvalidFilter = $phase8eHttpResponses[9] ?? [];
$phase8eHttpHasFatal = preg_match('/Fatal error|Uncaught (?:Error|Exception)|Parse error/i', $phase8eHttpError) === 1;
$phase8eCacheHeader = static function (array $response): bool {
    foreach ($response['headers'] ?? [] as $header) {
        if (strtolower($header) === 'cache-control: no-store') {
            return true;
        }
    }
    return false;
};
check(
    'Matriks role x rute MADANI sesuai Permissions dan Router pada entry point asli',
    $phase8eRouteMatrixPassed
        && !$phase8eHttpHasFatal
        && $phase8eSessionRoles === ['GUEST' => null, 'SISWA' => 'SISWA', 'GURU' => 'GURU', 'ADMIN' => 'ADMIN']
        && Permissions::routeRoles('admin.madani.view') === ['ADMIN']
        && Permissions::routeRoles('admin.madani.export') === ['ADMIN'],
    'status=' . $phase8eRouteTable . '; sesi=' . json_encode($phase8eSessionRoles)
);
check(
    'Entry point asli menampilkan dashboard HTML aman dan no-store untuk ADMIN',
    (int) ($phase8eAdminDashboard['status'] ?? 0) === 200
        && $phase8eCacheHeader($phase8eAdminDashboard)
        && str_contains($phase8eAdminDashboard['body'] ?? '', '&lt;script&gt;')
        && !str_contains($phase8eAdminDashboard['body'] ?? '', '<script>alert("madani-xss")</script>')
        && str_contains($phase8eAdminDashboard['body'] ?? '', 'Dashboard MADANI')
);
check(
    'Entry point asli menampilkan nol dan BELUM ADA DATA untuk filter tanpa observasi',
    (int) ($phase8eAdminEmptyDashboard['status'] ?? 0) === 200
        && str_contains($phase8eAdminEmptyDashboard['body'] ?? '', 'BELUM ADA DATA')
        && str_contains($phase8eAdminEmptyDashboard['body'] ?? '', '>0</a>')
        && !$phase8eHttpHasFatal
);
check(
    'Entry point asli menolak filter periode tidak valid tanpa menyajikan laporan tanpa filter',
    (int) ($phase8eAdminInvalidFilter['status'] ?? 0) === 422
        && $phase8eCacheHeader($phase8eAdminInvalidFilter)
        && str_contains($phase8eAdminInvalidFilter['body'] ?? '', 'Filter laporan tidak valid.')
        && !str_contains($phase8eAdminInvalidFilter['body'] ?? '', 'Evaluasi MADANI dan catatan before/after per layanan')
        && !$phase8eHttpHasFatal
);
check(
    'Entry point asli mengekspor CSV dengan header unduhan dan no-store untuk ADMIN',
    (int) ($phase8eAdminExport['status'] ?? 0) === 200
        && $phase8eCacheHeader($phase8eAdminExport)
        && in_array('Content-Type: text/csv; charset=UTF-8', $phase8eAdminExport['headers'] ?? [], true)
        && in_array('Content-Disposition: attachment; filename="madani-agregat.csv"', $phase8eAdminExport['headers'] ?? [], true)
);
