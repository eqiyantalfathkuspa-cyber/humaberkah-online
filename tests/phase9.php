<?php
declare(strict_types=1);

use App\Core\Db;
use App\Core\IpQuotaHash;
use App\Core\QuotaCleaner;

function phase9StartServer(string $key): ?array
{
    $probe = @stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);
    if ($probe === false) {
        return null;
    }
    $address = stream_socket_get_name($probe, false);
    fclose($probe);
    if (!is_string($address)) {
        return null;
    }

    $environment = getenv();
    if (!is_array($environment)) {
        return null;
    }
    $environment['APP_ENV'] = 'test';
    $environment['HUMA_IP_HASH_KEY'] = $key;
    $process = proc_open(
        [PHP_BINARY, '-S', $address, __DIR__ . '/../public/index.php'],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        dirname(__DIR__),
        $environment
    );
    if (!is_resource($process)) {
        return null;
    }
    fclose($pipes[0]);

    return ['address' => $address, 'process' => $process, 'pipes' => $pipes];
}

function phase9StopServer(array $server): void
{
    proc_terminate($server['process']);
    fclose($server['pipes'][1]);
    fclose($server['pipes'][2]);
    proc_close($server['process']);
}

function phase9Http(array $server, string $method, string $path, string $body = '', ?string $cookie = null): array
{
    $socket = false;
    for ($attempt = 0; $attempt < 20 && $socket === false; $attempt++) {
        $socket = @stream_socket_client('tcp://' . $server['address'], $errorCode, $errorMessage, 1);
        if ($socket === false) {
            usleep(25000);
        }
    }
    if ($socket === false) {
        return ['status' => 0, 'headers' => [], 'body' => ''];
    }
    stream_set_timeout($socket, 5);
    $headers = ['Host: ' . $server['address'], 'Connection: close'];
    if ($cookie !== null) {
        $headers[] = 'Cookie: ' . $cookie;
    }
    if ($method === 'POST') {
        $headers[] = 'Content-Type: application/x-www-form-urlencoded';
    }
    fwrite(
        $socket,
        $method . ' ' . $path . " HTTP/1.1\r\n"
            . implode("\r\n", $headers) . "\r\n"
            . 'Content-Length: ' . strlen($body) . "\r\n\r\n"
            . $body
    );
    $response = (string) stream_get_contents($socket);
    fclose($socket);
    [$headerText, $responseBody] = array_pad(explode("\r\n\r\n", $response, 2), 2, '');
    $headerLines = explode("\r\n", $headerText);
    preg_match('/^HTTP\/\S+\s+(\d+)/', (string) ($headerLines[0] ?? ''), $statusMatch);

    return [
        'status' => (int) ($statusMatch[1] ?? 0),
        'headers' => $headerLines,
        'body' => $responseBody,
    ];
}

function phase9PostForm(array $server, string $path, array $fields): array
{
    $form = phase9Http($server, 'GET', $path);
    $csrf = '';
    $cookie = null;
    if (preg_match('/name="_csrf" value="([^"]+)"/', $form['body'], $match) === 1) {
        $csrf = $match[1];
    }
    foreach ($form['headers'] as $header) {
        if (preg_match('/^Set-Cookie:\s*([^=]+)=([^;]+)/i', $header, $match) === 1) {
            $cookie = $match[1] . '=' . $match[2];
            break;
        }
    }
    if ($csrf === '') {
        return ['status' => 0, 'headers' => [], 'body' => 'CSRF form token unavailable'];
    }

    return phase9Http($server, 'POST', $path, http_build_query(['_csrf' => $csrf] + $fields), $cookie);
}

function phase9Counts(array $tables): array
{
    $counts = [];
    foreach ($tables as $table) {
        $counts[$table] = (int) Db::one('SELECT COUNT(*) AS n FROM ' . $table, [], 'test')['n'];
    }
    return $counts;
}

function phase9BadKeyResponses(array $server, int $serviceId): array
{
    return [
        phase9PostForm($server, '/kahayan', [
            'jenis' => 'PERTANYAAN',
            'isi' => 'phase9 invalid-key complaint',
            'kontak' => '',
            'website' => '',
        ]),
        phase9PostForm($server, '/survei', [
            'survey_type' => 'tracked',
            'tracking_code' => 'invalid-test-code',
            'speed_rating' => '4',
            'clarity_rating' => '4',
            'courtesy_rating' => '4',
            'suggestion' => '',
            'website' => '',
        ]),
        phase9PostForm($server, '/survei', [
            'survey_type' => 'information',
            'service_id' => (string) $serviceId,
            'speed_rating' => '4',
            'clarity_rating' => '4',
            'courtesy_rating' => '4',
            'suggestion' => '',
            'website' => '',
        ]),
    ];
}

$phase9KeyA = str_repeat('a', 32);
$phase9KeyB = str_repeat('b', 32);
$phase9Address = '198.51.100.214';
$phase9HashA = IpQuotaHash::withKey($phase9Address, $phase9KeyA);
$phase9HashB = IpQuotaHash::withKey($phase9Address, $phase9KeyB);
check(
    'HMAC kuota stabil untuk IP dan kunci yang sama serta berubah saat kunci berubah',
    $phase9HashA === IpQuotaHash::withKey($phase9Address, $phase9KeyA)
        && $phase9HashA !== $phase9HashB
);
check(
    'HMAC kuota tidak sama dengan SHA-256 polos dari IP',
    $phase9HashA !== hash('sha256', $phase9Address)
);

$phase9MissingRejected = false;
$phase9ShortRejected = false;
foreach ([null, str_repeat('x', 31)] as $invalidKey) {
    try {
        IpQuotaHash::withKey($phase9Address, $invalidKey);
    } catch (RuntimeException $exception) {
        $genericMessage = $exception->getMessage();
        if ($invalidKey === null) {
            $phase9MissingRejected = $genericMessage === 'Permintaan tidak dapat diproses.';
        } else {
            $phase9ShortRejected = $genericMessage === 'Permintaan tidak dapat diproses.';
        }
    }
}
check('Kunci HMAC hilang atau kurang dari 32 byte ditolak dengan pesan generik', $phase9MissingRejected && $phase9ShortRejected);

$phase9TestKey = (string) getenv('HUMA_IP_HASH_KEY');
$phase9ValidServer = phase9StartServer($phase9TestKey);
$phase9ValidBody = 'phase9-valid-' . bin2hex(random_bytes(8));
$phase9ValidResponse = ['status' => 0, 'body' => 'server unavailable'];
if ($phase9ValidServer !== null) {
    try {
        $phase9ValidResponse = phase9PostForm($phase9ValidServer, '/kahayan', [
            'jenis' => 'PERTANYAAN',
            'isi' => $phase9ValidBody,
            'kontak' => '',
            'website' => '',
        ]);
    } finally {
        phase9StopServer($phase9ValidServer);
    }
}
$phase9ValidIp = '127.0.0.1';
$phase9ValidHash = IpQuotaHash::forAddress($phase9ValidIp);
$phase9ValidComplaint = Db::one(
    'SELECT id, ticket_no FROM complaints WHERE isi = ?',
    [$phase9ValidBody],
    'test'
);
$phase9ValidQuota = Db::one(
    'SELECT submission_count FROM complaint_ip_quotas WHERE ip_hash = ?',
    [$phase9ValidHash],
    'test'
);
check(
    'POST /kahayan melalui public/index.php menghasilkan tiket dan kuota HMAC',
    $phase9ValidResponse['status'] === 303
        && $phase9ValidComplaint !== null
        && preg_match('/^CMP-/', (string) ($phase9ValidComplaint['ticket_no'] ?? '')) === 1
        && (int) ($phase9ValidQuota['submission_count'] ?? 0) >= 1
);

$phase9Service = Db::one("SELECT id FROM services WHERE mode = 'INFORMASI' AND is_active = 1 ORDER BY id LIMIT 1", [], 'test');
$phase9ServiceId = (int) ($phase9Service['id'] ?? 0);
$phase9ObservedTables = [
    'complaints', 'complaint_history', 'complaint_ip_quotas',
    'surveys', 'survey_ip_quotas', 'information_surveys', 'information_survey_ip_quotas', 'audit_logs',
];
$phase9InvalidKeyGroupsPassed = $phase9ServiceId > 0;
foreach (['', 'short-key-fixture'] as $invalidKey) {
    $before = phase9Counts($phase9ObservedTables);
    $server = phase9StartServer($invalidKey);
    $responses = [];
    if ($server !== null) {
        try {
            $responses = phase9BadKeyResponses($server, $phase9ServiceId);
        } finally {
            phase9StopServer($server);
        }
    }
    $after = phase9Counts($phase9ObservedTables);
    $genericResponses = count($responses) === 3;
    foreach ($responses as $response) {
        $genericResponses = $genericResponses
            && $response['status'] === 500
            && str_contains($response['body'], 'Terjadi kesalahan pada server.')
            && ($invalidKey === '' || !str_contains($response['body'], $invalidKey))
            && !str_contains($response['body'], 'config/config.php')
            && !str_contains($response['body'], dirname(__DIR__));
    }
    $phase9InvalidKeyGroupsPassed = $phase9InvalidKeyGroupsPassed
        && $genericResponses
        && $before === $after;
}
check(
    'Form publik gagal tertutup untuk kunci hilang/pendek tanpa kuota, data, audit, rahasia, atau jalur berkas di respons',
    $phase9InvalidKeyGroupsPassed
);

$phase9CleanerPrefix = 'phase9-clean-' . bin2hex(random_bytes(6));
$phase9ExpiredHashes = [];
$phase9ActiveHashes = [];
$phase9CleanerServiceId = (int) Db::one('SELECT id FROM services ORDER BY id LIMIT 1', [], 'test')['id'];
foreach (['complaint_ip_quotas', 'survey_ip_quotas', 'information_survey_ip_quotas'] as $index => $table) {
    $expiredHash = IpQuotaHash::forAddress($phase9CleanerPrefix . '-expired-' . $index);
    $activeHash = IpQuotaHash::forAddress($phase9CleanerPrefix . '-active-' . $index);
    $phase9ExpiredHashes[$table] = $expiredHash;
    $phase9ActiveHashes[$table] = $activeHash;
    if ($table === 'information_survey_ip_quotas') {
        Db::run(
            'INSERT INTO information_survey_ip_quotas (service_id, ip_hash, window_started_at, submission_count) VALUES (?, ?, DATE_SUB(NOW(), INTERVAL 2 HOUR), 2), (?, ?, NOW(), 1)',
            [$phase9CleanerServiceId, $expiredHash, $phase9CleanerServiceId, $activeHash],
            'test'
        );
    } else {
        Db::run(
            'INSERT INTO ' . $table . ' (ip_hash, window_started_at, submission_count) VALUES (?, DATE_SUB(NOW(), INTERVAL 2 HOUR), 2), (?, NOW(), 1)',
            [$expiredHash, $activeHash],
            'test'
        );
    }
}
$phase9NonQuotaBefore = phase9Counts(['complaints', 'complaint_history', 'surveys', 'information_surveys', 'audit_logs']);
$phase9Deleted = QuotaCleaner::cleanExpired('test');
$phase9ExpiredRemoved = true;
$phase9ActiveRetained = true;
foreach ($phase9ExpiredHashes as $table => $hash) {
    if ($table === 'information_survey_ip_quotas') {
        $phase9ExpiredExists = Db::one('SELECT ip_hash FROM information_survey_ip_quotas WHERE service_id = ? AND ip_hash = ?', [$phase9CleanerServiceId, $hash], 'test') !== null;
        $phase9ActiveExists = Db::one('SELECT ip_hash FROM information_survey_ip_quotas WHERE service_id = ? AND ip_hash = ?', [$phase9CleanerServiceId, $phase9ActiveHashes[$table]], 'test') !== null;
    } else {
        $phase9ExpiredExists = Db::one('SELECT ip_hash FROM ' . $table . ' WHERE ip_hash = ?', [$hash], 'test') !== null;
        $phase9ActiveExists = Db::one('SELECT ip_hash FROM ' . $table . ' WHERE ip_hash = ?', [$phase9ActiveHashes[$table]], 'test') !== null;
    }
    $phase9ExpiredRemoved = $phase9ExpiredRemoved && !$phase9ExpiredExists && (int) ($phase9Deleted[$table] ?? 0) >= 1;
    $phase9ActiveRetained = $phase9ActiveRetained && $phase9ActiveExists;
}
check('Pembersihan menghapus kuota kedaluwarsa dan mempertahankan semua jendela aktif', $phase9ExpiredRemoved && $phase9ActiveRetained);
check(
    'Pembersihan tidak mengubah aduan, riwayat, survei, atau audit_logs',
    $phase9NonQuotaBefore === phase9Counts(['complaints', 'complaint_history', 'surveys', 'information_surveys', 'audit_logs'])
);

$phase9GateHash = IpQuotaHash::forAddress($phase9CleanerPrefix . '-gate');
Db::run(
    'INSERT INTO complaint_ip_quotas (ip_hash, window_started_at, submission_count) VALUES (?, DATE_SUB(NOW(), INTERVAL 2 HOUR), 1)',
    [$phase9GateHash],
    'test'
);
$phase9SkippedCleanup = QuotaCleaner::maybeClean('test', 2);
$phase9ExpiredStillPresent = Db::one('SELECT ip_hash FROM complaint_ip_quotas WHERE ip_hash = ?', [$phase9GateHash], 'test') !== null;
$phase9ForcedCleanup = QuotaCleaner::maybeClean('test', 1);
$phase9ExpiredNowGone = Db::one('SELECT ip_hash FROM complaint_ip_quotas WHERE ip_hash = ?', [$phase9GateHash], 'test') === null;
check(
    'Sampling membatasi pembersihan dan pemanggilan non-terpilih tidak menghapus baris',
    !$phase9SkippedCleanup && $phase9ExpiredStillPresent && $phase9ForcedCleanup && $phase9ExpiredNowGone
);

$phase9CliIp = '198.51.100.215';
$phase9CliHash = IpQuotaHash::forAddress($phase9CliIp);
Db::run(
    'INSERT INTO complaint_ip_quotas (ip_hash, window_started_at, submission_count) VALUES (?, DATE_SUB(NOW(), INTERVAL 2 HOUR), 1)',
    [$phase9CliHash],
    'test'
);
$phase9CliProcess = proc_open(
    [PHP_BINARY, __DIR__ . '/../bin/bersihkan-kuota.php'],
    [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
    $phase9CliPipes,
    dirname(__DIR__),
    getenv()
);
$phase9CliOutput = '';
$phase9CliExit = 1;
if (is_resource($phase9CliProcess)) {
    fclose($phase9CliPipes[0]);
    $phase9CliOutput = (string) stream_get_contents($phase9CliPipes[1]) . (string) stream_get_contents($phase9CliPipes[2]);
    fclose($phase9CliPipes[1]);
    fclose($phase9CliPipes[2]);
    $phase9CliExit = proc_close($phase9CliProcess);
}
check(
    'CLI pembersih keluar 0, melaporkan jumlah baris, dan tidak mencetak IP atau hash',
    $phase9CliExit === 0
        && str_contains($phase9CliOutput, 'Baris kuota kedaluwarsa dihapus: 1')
        && str_contains($phase9CliOutput, 'complaint_ip_quotas: 1')
        && !str_contains($phase9CliOutput, $phase9CliIp)
        && !str_contains($phase9CliOutput, $phase9CliHash)
);

$phase9SourcePath = __DIR__ . '/../app/Core/IpQuotaHash.php';
$phase9OriginalSource = (string) file_get_contents($phase9SourcePath);
$phase9SourceHash = hash('sha256', $phase9OriginalSource);
$phase9MutationNeedle = "return hash_hmac('sha256', \$address, \$key);";
$phase9MutatedSource = str_replace(
    $phase9MutationNeedle,
    "return hash('sha256', \$address);",
    $phase9OriginalSource,
    $phase9MutationCount
);
$phase9MutatedSource = str_replace('final class IpQuotaHash', 'final class IpQuotaHashMutation', $phase9MutatedSource, $phase9MutationClassCount);
$phase9MutationFile = tempnam(sys_get_temp_dir(), 'huma-ip-quota-mutant-');
$phase9ProbeFile = tempnam(sys_get_temp_dir(), 'huma-ip-quota-probe-');
$phase9MutationOutput = '';
$phase9MutationExit = 0;
if (
    is_string($phase9MutationFile)
    && is_string($phase9ProbeFile)
    && $phase9MutationCount === 1
    && $phase9MutationClassCount === 1
) {
    file_put_contents($phase9MutationFile, $phase9MutatedSource);
    file_put_contents(
        $phase9ProbeFile,
        "<?php\n"
            . 'require ' . var_export(__DIR__ . '/../app/autoload.php', true) . ";\n"
            . 'require ' . var_export($phase9MutationFile, true) . ";\n"
            . "\$address = '198.51.100.216';\n"
            . "\$actual = \\App\\Core\\IpQuotaHashMutation::withKey(\$address, str_repeat('m', 32));\n"
            . "if (\$actual !== hash_hmac('sha256', \$address, str_repeat('m', 32))) { echo '[GAGAL_MUTANT] assertion HMAC gagal'; exit(1); }\n"
            . "echo 'ASSERTION_PASSED'; exit(0);\n"
    );
    $phase9MutationProcess = proc_open(
        [PHP_BINARY, $phase9ProbeFile],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $phase9MutationPipes,
        dirname(__DIR__)
    );
    if (is_resource($phase9MutationProcess)) {
        fclose($phase9MutationPipes[0]);
        $phase9MutationOutput = trim((string) stream_get_contents($phase9MutationPipes[1]) . (string) stream_get_contents($phase9MutationPipes[2]));
        fclose($phase9MutationPipes[1]);
        fclose($phase9MutationPipes[2]);
        $phase9MutationExit = proc_close($phase9MutationProcess);
    }
}
foreach ([$phase9MutationFile, $phase9ProbeFile] as $phase9TemporaryFile) {
    if (is_string($phase9TemporaryFile) && is_file($phase9TemporaryFile)) {
        unlink($phase9TemporaryFile);
    }
}
echo 'MUTATION_PROBE exit=' . $phase9MutationExit . ' output=' . $phase9MutationOutput . PHP_EOL;
check(
    'Mutasi SHA-256 polos menggagalkan assertion HMAC pada proses anak dan sumber asli tetap pulih',
    $phase9MutationCount === 1
        && $phase9MutationClassCount === 1
        && $phase9MutationExit === 1
        && str_contains($phase9MutationOutput, '[GAGAL_MUTANT]')
        && hash_file('sha256', $phase9SourcePath) === $phase9SourceHash
);