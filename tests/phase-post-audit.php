<?php
declare(strict_types=1);

use App\Core\Csrf;
use App\Core\Db;
use App\Controllers\EvidenceController;

function postAuditIdentity(): array
{
    $identifier = 'post-audit-' . bin2hex(random_bytes(8));
    $authToken = bin2hex(random_bytes(32));
    Db::run(
        'INSERT INTO users (role_id, identifier, full_name, password_hash, is_active, auth_token) VALUES ((SELECT id FROM roles WHERE code = ?), ?, ?, ?, 1, ?)',
        ['ADMIN', $identifier, 'Admin Uji Audit', password_hash(bin2hex(random_bytes(24)), PASSWORD_DEFAULT), $authToken],
        'test'
    );
    $user = Db::one(
        'SELECT u.id, u.identifier, u.full_name, u.auth_token, r.code AS role_code FROM users AS u INNER JOIN roles AS r ON r.id = u.role_id WHERE u.identifier = ?',
        [$identifier],
        'test'
    );
    if ($user === null) {
        throw new RuntimeException('Akun admin fixture tidak dapat dibuat.');
    }

    $previousId = session_id();
    session_write_close();
    $sessionId = session_create_id();
    session_id($sessionId);
    session_start();
    $_SESSION = [
        'user' => [
            'id' => (int) $user['id'],
            'identifier' => (string) $user['identifier'],
            'full_name' => (string) $user['full_name'],
            'role' => (string) $user['role_code'],
            'role_code' => (string) $user['role_code'],
            'auth_token' => (string) $user['auth_token'],
        ],
    ];
    $csrf = Csrf::token();
    session_write_close();
    session_id($previousId);
    session_start();

    return ['cookie' => session_name() . '=' . $sessionId, 'csrf' => $csrf];
}

function postAuditHttpRequests(array $requests): array
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
            $method = $request['method'];
            $body = $method === 'POST' ? http_build_query($request['post'] ?? []) : '';
            $headers = ['Host: ' . $address, 'Connection: close'];
            if (isset($request['cookie'])) {
                $headers[] = 'Cookie: ' . $request['cookie'];
            }
            if ($method === 'POST') {
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
                $responses[] = ['status' => 0, 'headers' => []];
                continue;
            }
            stream_set_timeout($socket, 5);
            fwrite(
                $socket,
                $method . ' ' . $request['path'] . " HTTP/1.1\r\n"
                    . implode("\r\n", $headers) . "\r\n"
                    . 'Content-Length: ' . strlen($body) . "\r\n\r\n"
                    . $body
            );
            $rawResponse = (string) stream_get_contents($socket);
            fclose($socket);
            [$rawHeaders] = array_pad(explode("\r\n\r\n", $rawResponse, 2), 2, '');
            $headerLines = explode("\r\n", $rawHeaders);
            preg_match('/^HTTP\/\S+\s+(\d+)/', (string) ($headerLines[0] ?? ''), $statusMatch);
            $responses[] = [
                'status' => (int) ($statusMatch[1] ?? 0),
                'headers' => $headerLines,
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

$postAuditAdmin = postAuditIdentity();
$postAuditService = Db::one('SELECT id FROM services ORDER BY id LIMIT 1', [], 'test');
$postAuditCheckpoint = Db::one('SELECT id FROM evidence_checkpoints ORDER BY id LIMIT 1', [], 'test');
$postAuditServiceId = (int) ($postAuditService['id'] ?? 0);
$postAuditCheckpointId = (int) ($postAuditCheckpoint['id'] ?? 0);
$postAuditCsrf = ['_csrf' => $postAuditAdmin['csrf']];
$postAuditErrorResponses = postAuditHttpRequests([
    [
        'method' => 'POST',
        'path' => '/admin/eviden',
        'cookie' => $postAuditAdmin['cookie'],
        'post' => $postAuditCsrf + [
            'service_id' => (string) $postAuditServiceId,
            'checkpoint_id' => (string) $postAuditCheckpointId,
        ],
    ],
    ['method' => 'POST', 'path' => '/admin/evaluasi', 'cookie' => $postAuditAdmin['cookie'], 'post' => $postAuditCsrf],
    ['method' => 'POST', 'path' => '/admin/rtl', 'cookie' => $postAuditAdmin['cookie'], 'post' => $postAuditCsrf],
    ['method' => 'POST', 'path' => '/admin/dampak', 'cookie' => $postAuditAdmin['cookie'], 'post' => $postAuditCsrf],
    ['method' => 'GET', 'path' => '/admin/checklist-99', 'cookie' => $postAuditAdmin['cookie']],
]);
$postAuditErrorExpectedStatuses = [422, 422, 422, 422, 200];
$postAuditPrivateErrorsPassed = count($postAuditErrorResponses) === count($postAuditErrorExpectedStatuses);
$postAuditErrorDiagnostics = [];
foreach ($postAuditErrorExpectedStatuses as $index => $expectedStatus) {
    $response = $postAuditErrorResponses[$index] ?? ['status' => 0, 'headers' => []];
    $headers = implode("\n", $response['headers']);
    $hasNoStore = preg_match('/^Cache-Control:\s*no-store/im', $headers) === 1;
    $hasNoReferrer = preg_match('/^Referrer-Policy:\s*no-referrer/im', $headers) === 1;
    $postAuditErrorDiagnostics[] = $response['status'] . ':' . ($hasNoStore ? 'no-store' : 'cache-missing')
        . ':' . ($hasNoReferrer ? 'referrer' : 'referrer-missing');
    $postAuditPrivateErrorsPassed = $postAuditPrivateErrorsPassed
        && $response['status'] === $expectedStatus
        && $hasNoStore
        && $hasNoReferrer;
}
check(
    'Jalur error internal 7B–8D mengirim no-store dan Referrer-Policy',
    $postAuditPrivateErrorsPassed,
    implode(', ', $postAuditErrorDiagnostics)
);

$postAuditOverflow = '99999999999999999999';
$postAuditOverflowResponses = postAuditHttpRequests([
    ['method' => 'GET', 'path' => '/admin/eviden/' . $postAuditOverflow, 'cookie' => $postAuditAdmin['cookie']],
    ['method' => 'GET', 'path' => '/admin/eviden/' . $postAuditOverflow . '/berkas', 'cookie' => $postAuditAdmin['cookie']],
    [
        'method' => 'POST',
        'path' => '/admin/eviden/' . $postAuditOverflow . '/verifikasi',
        'cookie' => $postAuditAdmin['cookie'],
        'post' => $postAuditCsrf + ['decision' => 'TERVERIFIKASI'],
    ],
    ['method' => 'GET', 'path' => '/admin/evaluasi/' . $postAuditOverflow, 'cookie' => $postAuditAdmin['cookie']],
    ['method' => 'GET', 'path' => '/admin/rtl/' . $postAuditOverflow, 'cookie' => $postAuditAdmin['cookie']],
    [
        'method' => 'POST',
        'path' => '/admin/rtl/' . $postAuditOverflow . '/status',
        'cookie' => $postAuditAdmin['cookie'],
        'post' => $postAuditCsrf + ['status' => 'BERJALAN'],
    ],
    ['method' => 'GET', 'path' => '/admin/dampak/' . $postAuditOverflow, 'cookie' => $postAuditAdmin['cookie']],
    ['method' => 'GET', 'path' => '/admin/eviden?service_id=' . $postAuditOverflow, 'cookie' => $postAuditAdmin['cookie']],
    ['method' => 'GET', 'path' => '/admin/evaluasi?service_id=' . $postAuditOverflow, 'cookie' => $postAuditAdmin['cookie']],
    ['method' => 'GET', 'path' => '/admin/rtl?evaluation_id=' . $postAuditOverflow, 'cookie' => $postAuditAdmin['cookie']],
    ['method' => 'GET', 'path' => '/admin/dampak?service_id=' . $postAuditOverflow, 'cookie' => $postAuditAdmin['cookie']],
]);
$postAuditOverflowPassed = count($postAuditOverflowResponses) === 11;
foreach (array_slice($postAuditOverflowResponses, 0, 7) as $response) {
    $postAuditOverflowPassed = $postAuditOverflowPassed && $response['status'] === 404;
}
foreach (array_slice($postAuditOverflowResponses, 7) as $response) {
    $postAuditOverflowPassed = $postAuditOverflowPassed && $response['status'] === 422;
}
check('ID overflow ditolak pada detail, unduh, verifikasi, status, dan filter', $postAuditOverflowPassed);

$postAuditControllerSource = (string) file_get_contents(__DIR__ . '/../app/Controllers/EvidenceController.php');
$postAuditRangeGuard = '/strlen\(\$value\) > strlen\(\$maximum\)\R\s*\|\| \(strlen\(\$value\) === strlen\(\$maximum\) && strcmp\(\$value, \$maximum\) > 0\)/';
$postAuditMutatedSource = preg_replace(
    $postAuditRangeGuard,
    'false',
    $postAuditControllerSource,
    1,
    $postAuditRangeGuardCount
);
$postAuditMutatedSource = str_replace(
    'EvidenceValidationException',
    'EvidenceValidationExceptionMutationProbe',
    $postAuditMutatedSource
);
$postAuditMutatedSource = str_replace(
    'EvidenceStorageException',
    'EvidenceStorageExceptionMutationProbe',
    $postAuditMutatedSource
);
$postAuditMutatedSource = str_replace(
    'EvidenceController',
    'EvidenceControllerMutationProbe',
    $postAuditMutatedSource,
    $postAuditClassCount
);
$postAuditMutationController = tempnam(__DIR__, '.post_audit_controller_');
$postAuditMutationProbe = tempnam(__DIR__, '.post_audit_probe_');
$postAuditMutationOutput = '';
$postAuditMutationError = '';
$postAuditMutationExit = 1;
if (
    is_string($postAuditMutationController)
    && is_string($postAuditMutationProbe)
    && $postAuditRangeGuardCount === 1
    && $postAuditClassCount === 1
) {
    file_put_contents($postAuditMutationController, $postAuditMutatedSource);
    $postAuditMutationScript = "<?php\n"
        . "require " . var_export(__DIR__ . '/../app/autoload.php', true) . ";\n"
        . "require " . var_export($postAuditMutationController, true) . ";\n"
        . "\$parser = new ReflectionMethod(\\App\\Controllers\\EvidenceControllerMutationProbe::class, 'positiveInteger');\n"
        . "\$parser->setAccessible(true);\n"
        . "if (\$parser->invoke(null, '99999999999999999999') !== null) { echo '[GAGAL] overflow lolos pada mutant'; exit(1); }\n"
        . "echo '[LOLOS] mutant menolak overflow'; exit(0);\n";
    file_put_contents($postAuditMutationProbe, $postAuditMutationScript);
    $process = proc_open(
        [PHP_BINARY, $postAuditMutationProbe],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        __DIR__
    );
    if (is_resource($process)) {
        fclose($pipes[0]);
        $postAuditMutationOutput = trim((string) stream_get_contents($pipes[1]));
        $postAuditMutationError = trim((string) stream_get_contents($pipes[2]));
        fclose($pipes[1]);
        fclose($pipes[2]);
        $postAuditMutationExit = proc_close($process);
    }
}
foreach ([$postAuditMutationController, $postAuditMutationProbe] as $temporaryPath) {
    if (is_string($temporaryPath) && is_file($temporaryPath)) {
        unlink($temporaryPath);
    }
}
$postAuditOriginalParser = new ReflectionMethod(EvidenceController::class, 'positiveInteger');
$postAuditOriginalParser->setAccessible(true);
$postAuditOriginalRejectsOverflow = $postAuditOriginalParser->invoke(null, $postAuditOverflow) === null;
echo '[MUTASI PROSES ANAK exit=' . $postAuditMutationExit . '] ' . $postAuditMutationOutput . PHP_EOL;
echo '[PEMULIHAN] ' . ($postAuditOriginalRejectsOverflow ? '[LOLOS] parser asli menolak overflow' : '[GAGAL] parser asli menerima overflow') . PHP_EOL;
check(
    'Mutasi pemeriksaan rentang menghasilkan merah; parser asli pulih',
    $postAuditRangeGuardCount === 1
        && $postAuditClassCount === 1
        && $postAuditMutationExit === 1
        && str_contains($postAuditMutationOutput, '[GAGAL] overflow lolos pada mutant')
        && $postAuditMutationError === ''
        && $postAuditOriginalRejectsOverflow,
    'guard=' . $postAuditRangeGuardCount . '; class=' . $postAuditClassCount
        . '; exit=' . $postAuditMutationExit . '; stdout=' . $postAuditMutationOutput
        . '; stderr=' . $postAuditMutationError
);
