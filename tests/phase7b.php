<?php
declare(strict_types=1);

use App\Core\Db;
use App\Policies\Permissions;

$phase7bMigration = '011_evidence_foundation.sql';
$phase7bMigrationBefore = Db::one(
    'SELECT status, checksum, statement_count, last_completed_statement FROM schema_migration_runs WHERE migration_name = ?',
    [$phase7bMigration],
    'test'
);
[$phase7bFirstCode, $phase7bFirstOutput] = runMigrationProcess(__DIR__ . '/../database/migrations');
[$phase7bSecondCode, $phase7bSecondOutput] = runMigrationProcess(__DIR__ . '/../database/migrations');
$phase7bMigrationAfter = Db::one(
    'SELECT status, checksum, statement_count, last_completed_statement FROM schema_migration_runs WHERE migration_name = ?',
    [$phase7bMigration],
    'test'
);
check(
    'Migrasi 011 idempoten dan signature migrasi 009/010 tetap terdaftar',
    $phase7bMigrationBefore !== null
        && $phase7bMigrationBefore === $phase7bMigrationAfter
        && (int) $phase7bMigrationAfter['statement_count'] === 3
        && (int) $phase7bMigrationAfter['last_completed_statement'] === 3
        && $phase7bFirstCode === 0
        && $phase7bSecondCode === 0
        && (int) Db::one(
            'SELECT COUNT(*) AS n FROM schema_migration_runs WHERE migration_name IN (?, ?) AND status = ?',
            ['009_surveys.sql', '010_information_surveys.sql', 'APPLIED'],
            'test'
        )['n'] === 2,
    $phase7bFirstOutput . $phase7bSecondOutput
);

$phase7bDriftDetected = false;
$phase7bDriftRestored = false;
Db::pdo('test')->exec('ALTER TABLE evidence_items MODIFY COLUMN size_bytes BIGINT UNSIGNED NOT NULL');
try {
    [$phase7bDriftCode, $phase7bDriftOutput] = runMigrationProcess(__DIR__ . '/../database/migrations');
    $phase7bDriftDetected = $phase7bDriftCode !== 0
        && str_contains($phase7bDriftOutput, 'skema aktual berbeda');
} finally {
    Db::pdo('test')->exec('ALTER TABLE evidence_items MODIFY COLUMN size_bytes INT UNSIGNED NOT NULL');
    [$phase7bRestoreCode, $phase7bRestoreOutput] = runMigrationProcess(__DIR__ . '/../database/migrations');
    $phase7bDriftRestored = $phase7bRestoreCode === 0;
}
check(
    'Signature 011 menolak drift kolom fixture lalu lulus setelah pemulihan',
    $phase7bDriftDetected && $phase7bDriftRestored,
    'drift=' . ($phase7bDriftDetected ? 'ditolak' : 'tidak ditolak')
        . '; restore=' . ($phase7bDriftRestored ? 'lulus' : 'gagal')
        . '; ' . $phase7bRestoreOutput
);

$phase7bExpectedCheckpoints = [
    'EV-01' => 'Identitas layanan',
    'EV-02' => 'Dokumen persyaratan',
    'EV-03' => 'Proses verifikasi awal',
    'EV-04' => 'Pelaksanaan SOP',
    'EV-05' => 'Hasil layanan',
    'EV-06' => 'Umpan balik pelanggan',
    'EV-07' => 'Evaluasi internal',
    'EV-08' => 'RTL / tindak lanjut',
    'EV-09' => 'Dampak layanan',
];
$phase7bActualCheckpoints = [];
foreach (Db::all('SELECT code, name FROM evidence_checkpoints ORDER BY code', [], 'test') as $checkpoint) {
    $phase7bActualCheckpoints[$checkpoint['code']] = $checkpoint['name'];
}
check(
    'Migrasi 011 seed tepat sembilan checkpoint sesuai label ERD',
    $phase7bActualCheckpoints === $phase7bExpectedCheckpoints
);

$phase7bForeignKeysEnforced = true;
foreach ([
    'INSERT INTO evidence_items (service_id, checkpoint_id, stored_name, mime, size_bytes, sha256, uploaded_by) VALUES (999999999, 1, ?, ?, 1, ?, 1)' => ['fk-service-' . bin2hex(random_bytes(16)) . '.png', 'image/png', hash('sha256', 'fk-service')],
    'INSERT INTO evidence_items (service_id, checkpoint_id, stored_name, mime, size_bytes, sha256, uploaded_by) VALUES (1, 999999999, ?, ?, 1, ?, 1)' => ['fk-checkpoint-' . bin2hex(random_bytes(16)) . '.png', 'image/png', hash('sha256', 'fk-checkpoint')],
    'INSERT INTO evidence_items (service_id, checkpoint_id, stored_name, mime, size_bytes, sha256, uploaded_by) VALUES (1, 1, ?, ?, 1, ?, 999999999)' => ['fk-user-' . bin2hex(random_bytes(16)) . '.png', 'image/png', hash('sha256', 'fk-user')],
] as $sql => $params) {
    try {
        Db::run($sql, $params, 'test');
        $phase7bForeignKeysEnforced = false;
    } catch (PDOException) {
    }
}
check('FK eviden menolak layanan, checkpoint, atau pengguna yang tidak ada', $phase7bForeignKeysEnforced);

$phase7bAdmin = phase2bUser(
    'ADMIN',
    'phase7b-admin-' . bin2hex(random_bytes(5)),
    'Admin Eviden Uji',
    bin2hex(random_bytes(16))
);
$phase7bAdmin2 = phase2bUser(
    'ADMIN',
    'phase7b-admin2-' . bin2hex(random_bytes(5)),
    'Admin Eviden Kedua',
    bin2hex(random_bytes(16))
);
$phase7bSiswa = phase2bUser(
    'SISWA',
    'phase7b-siswa-' . bin2hex(random_bytes(5)),
    'Siswa Eviden Uji',
    bin2hex(random_bytes(16))
);
$phase7bGuru = phase2bUser(
    'GURU',
    'phase7b-guru-' . bin2hex(random_bytes(5)),
    'Guru Eviden Uji',
    bin2hex(random_bytes(16))
);
$phase7bRoles = [
    'GUEST' => null,
    'SISWA' => $phase7bSiswa,
    'GURU' => $phase7bGuru,
    'ADMIN' => $phase7bAdmin,
];
$phase7bIdentities = [];
foreach ($phase7bRoles as $role => $user) {
    $phase7bIdentities[$role] = phase2bHttpIdentity($user);
}
$phase7bCheckpointId = (int) Db::one(
    'SELECT id FROM evidence_checkpoints WHERE code = ?',
    ['EV-01'],
    'test'
)['id'];
$phase7bServiceId = (int) Db::one(
    'SELECT id FROM services WHERE code = ?',
    ['TU-01'],
    'test'
)['id'];
$phase7bRouteCases = [
    ['GET', '/admin/eviden', 'admin.evidence.view'],
    ['POST', '/admin/eviden', 'admin.evidence.upload'],
    ['GET', '/admin/eviden/999999999', 'admin.evidence.view'],
    ['GET', '/admin/eviden/999999999/berkas', 'admin.evidence.download'],
    ['POST', '/admin/eviden/999999999/verifikasi', 'admin.evidence.verify'],
];
$phase7bRequests = [];
$phase7bExpectedStatuses = [];
foreach ($phase7bRouteCases as [$method, $path, $ability]) {
    foreach ($phase7bRoles as $role => $user) {
        $identity = $phase7bIdentities[$role];
        $payload = ['_csrf' => $identity['csrf']];
        if ($path === '/admin/eviden' && $method === 'POST') {
            $payload += ['service_id' => (string) $phase7bServiceId, 'checkpoint_id' => (string) $phase7bCheckpointId];
        } elseif ($method === 'POST') {
            $payload += ['decision' => 'TERVERIFIKASI'];
        }
        $phase7bRequests[] = [
            'method' => $method,
            'path' => $path,
            'cookie' => $identity['cookie'],
            'post' => $payload,
        ];
        $phase7bExpectedStatuses[] = [
            'role' => $role,
            'method' => $method,
            'path' => $path,
            'ability' => $ability,
            'allowed' => Permissions::can($role, $ability),
        ];
    }
}
$phase7bResponses = phase2bHttpRequests($phase7bRequests);
$phase7bMatrixPassed = count($phase7bResponses) === count($phase7bExpectedStatuses);
echo PHP_EOL . "MATRIKS ROLE x RUTE LANGKAH 7B\nRole | Rute | Ability | Hasil | Harapan" . PHP_EOL;
foreach ($phase7bExpectedStatuses as $index => $case) {
    $response = $phase7bResponses[$index] ?? ['status' => 0];
    $status = (int) $response['status'];
    $expectedStatus = match ([$case['role'], $case['method'], $case['path']]) {
        ['ADMIN', 'GET', '/admin/eviden'] => 200,
        ['ADMIN', 'POST', '/admin/eviden'] => 422,
        ['ADMIN', 'POST', '/admin/eviden/999999999/verifikasi'] => 404,
        ['ADMIN', 'GET', '/admin/eviden/999999999'],
        ['ADMIN', 'GET', '/admin/eviden/999999999/berkas'] => 404,
        default => $case['role'] === 'GUEST' ? [302, 403] : [403],
    };
    $validStatus = is_array($expectedStatus)
        ? in_array($status, $expectedStatus, true)
        : $status === $expectedStatus;
    $phase7bMatrixPassed = $phase7bMatrixPassed && $case['allowed'] === ($case['role'] === 'ADMIN') && $validStatus;
    echo $case['role'] . ' | ' . $case['method'] . ' ' . $case['path'] . ' | '
        . $case['ability'] . ' | ' . $status . ' | '
        . (is_array($expectedStatus) ? implode('/', $expectedStatus) : $expectedStatus) . PHP_EOL;
}
check('Matriks role x lima rute eviden sesuai Permissions.php', $phase7bMatrixPassed);

$phase7bCsrfRequests = [];
foreach (['/admin/eviden', '/admin/eviden/999999999/verifikasi'] as $path) {
    $phase7bCsrfRequests[] = [
        'method' => 'POST',
        'path' => $path,
        'cookie' => $phase7bIdentities['ADMIN']['cookie'],
        'post' => [
            '_csrf' => 'wrong-' . bin2hex(random_bytes(8)),
            'service_id' => (string) $phase7bServiceId,
            'checkpoint_id' => (string) $phase7bCheckpointId,
            'decision' => 'TERVERIFIKASI',
        ],
    ];
}
$phase7bCsrfResponses = phase2bHttpRequests($phase7bCsrfRequests);
check(
    'CSRF salah ditolak pada POST unggah dan verifikasi',
    count($phase7bCsrfResponses) === 2
        && $phase7bCsrfResponses[0]['status'] === 403
        && $phase7bCsrfResponses[1]['status'] === 403
);

$phase7bFixtureToken = getenv('HUMA_TEST_FIXTURE_TOKEN');
$phase7bStorageDirectory = __DIR__ . '/../storage/private/evidence-fixture-' . $phase7bFixtureToken;
$phase7bStorageDirectory = realpath(dirname($phase7bStorageDirectory)) === false
    ? __DIR__ . '/../storage/private/evidence-fixture-' . $phase7bFixtureToken
    : realpath(dirname($phase7bStorageDirectory)) . DIRECTORY_SEPARATOR . basename($phase7bStorageDirectory);
register_shutdown_function(static function () use ($phase7bStorageDirectory, $phase7bFixtureToken): void {
    if (!is_string($phase7bFixtureToken) || preg_match('/^[a-f0-9]{64}$/D', $phase7bFixtureToken) !== 1) {
        return;
    }
    $expectedName = 'evidence-fixture-' . $phase7bFixtureToken;
    if (basename($phase7bStorageDirectory) !== $expectedName || !is_dir($phase7bStorageDirectory) || is_link($phase7bStorageDirectory)) {
        return;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($phase7bStorageDirectory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $entry) {
        if ($entry->isDir() && !$entry->isLink()) {
            rmdir($entry->getPathname());
        } else {
            unlink($entry->getPathname());
        }
    }
    rmdir($phase7bStorageDirectory);
});

$phase7bPngGenerator = imagecreatetruecolor(1, 1);
ob_start();
imagepng($phase7bPngGenerator);
$phase7bPng = (string) ob_get_clean();
imagedestroy($phase7bPngGenerator);
$phase7bJpegGenerator = imagecreatetruecolor(1, 1);
ob_start();
imagejpeg($phase7bJpegGenerator, null, 80);
$phase7bJpeg = (string) ob_get_clean();
imagedestroy($phase7bJpegGenerator);

$phase7bUploadIdentity = phase2bHttpIdentity($phase7bAdmin);
$phase7bUploadPost = [
    '_csrf' => $phase7bUploadIdentity['csrf'],
    'service_id' => (string) $phase7bServiceId,
    'checkpoint_id' => (string) $phase7bCheckpointId,
];
$phase7bCountBeforeSingleUpload = (int) Db::one('SELECT COUNT(*) AS n FROM evidence_items', [], 'test')['n'];
$phase7bSingleUpload = phase2bHttpRequests([[
    'method' => 'POST',
    'path' => '/admin/eviden',
    'cookie' => $phase7bUploadIdentity['cookie'],
    'post' => $phase7bUploadPost,
    'files' => [['name' => 'single.png', 'type' => 'image/png', 'content' => $phase7bPng]],
]]);
$phase7bSingleItem = Db::one(
    'SELECT id, stored_name, size_bytes, sha256 FROM evidence_items WHERE original_name = ? AND uploaded_by = ? ORDER BY id DESC LIMIT 1',
    ['single.png', (int) $phase7bAdmin['id']],
    'test'
);
$phase7bCountAfterSingleUpload = (int) Db::one('SELECT COUNT(*) AS n FROM evidence_items', [], 'test')['n'];
check(
    'Entry point asli menyimpan tepat satu eviden dan satu berkas pada unggah tunggal',
    ($phase7bSingleUpload[0]['status'] ?? 0) === 303
        && $phase7bCountAfterSingleUpload === $phase7bCountBeforeSingleUpload + 1
        && $phase7bSingleItem !== null
        && is_file($phase7bStorageDirectory . DIRECTORY_SEPARATOR . $phase7bSingleItem['stored_name'])
        && (int) $phase7bSingleItem['size_bytes'] === strlen($phase7bPng)
        && $phase7bSingleItem['sha256'] === hash('sha256', $phase7bPng)
);
$phase7bUpload = phase2bHttpRequests([[
    'method' => 'POST',
    'path' => '/admin/eviden',
    'cookie' => $phase7bUploadIdentity['cookie'],
    'post' => $phase7bUploadPost,
    'files' => [
        ['name' => '../<script>alert(1).png', 'type' => 'image/png', 'content' => $phase7bPng],
        ['name' => '..\\..\\nul' . "\0" . 'traverse.jpg', 'type' => 'image/jpeg', 'content' => $phase7bJpeg],
        ['name' => 'third.png', 'type' => 'image/png', 'content' => $phase7bPng],
    ],
]]);
$phase7bUploadedItems = Db::all(
    'SELECT id, service_id, checkpoint_id, status, stored_name, original_name, mime, size_bytes, sha256, uploaded_by, verified_by, verified_at FROM evidence_items WHERE uploaded_by = ? ORDER BY id DESC LIMIT 3',
    [(int) $phase7bAdmin['id']],
    'test'
);
$phase7bUploadRowsValid = count($phase7bUploadedItems) === 3;
$phase7bUploadNames = [];
foreach ($phase7bUploadedItems as $item) {
    $phase7bUploadNames[] = $item['original_name'];
    $expectedContent = $item['mime'] === 'image/png' ? $phase7bPng : $phase7bJpeg;
    $storedPath = $phase7bStorageDirectory . DIRECTORY_SEPARATOR . $item['stored_name'];
    $phase7bUploadRowsValid = $phase7bUploadRowsValid
        && preg_match('/^[a-f0-9]{64}\.(?:jpg|png)$/D', $item['stored_name']) === 1
        && (int) $item['service_id'] === $phase7bServiceId
        && (int) $item['checkpoint_id'] === $phase7bCheckpointId
        && $item['status'] === 'DIUNGGAH'
        && (int) $item['uploaded_by'] === $phase7bAdmin['id']
        && $item['verified_by'] === null
        && (int) $item['size_bytes'] === strlen($expectedContent)
        && $item['sha256'] === hash('sha256', $expectedContent)
        && is_file($storedPath)
        && file_get_contents($storedPath) === $expectedContent;
}
$phase7bSingleUploadItems = array_values(array_filter(
    $phase7bUploadedItems,
    static fn (array $item): bool => $item['original_name'] === '<script>alert(1).png'
));
check(
    'Entry point asli menerima JPG/PNG sekaligus, menyimpan tiga eviden dan metadata/hash berkas cocok',
    ($phase7bUpload[0]['status'] ?? 0) === 303
        && $phase7bUploadRowsValid
        && count($phase7bSingleUploadItems) === 1,
    'status=' . (string) ($phase7bUpload[0]['status'] ?? 0)
        . '; rows=' . count($phase7bUploadedItems)
        . '; names=' . json_encode($phase7bUploadNames, JSON_UNESCAPED_SLASHES)
);

$phase7bEvidenIds = array_map(static fn (array $item): int => (int) $item['id'], $phase7bUploadedItems);
$phase7bFilterResponse = phase2bHttpRequests([[
    'method' => 'GET',
    'path' => '/admin/eviden?service_id=' . $phase7bServiceId
        . '&checkpoint_id=' . $phase7bCheckpointId . '&status=DIUNGGAH',
    'cookie' => $phase7bUploadIdentity['cookie'],
]]);
check(
    'Daftar eviden memakai filter tervalidasi dan meng-escape nama asli HTML',
    ($phase7bFilterResponse[0]['status'] ?? 0) === 200
        && str_contains($phase7bFilterResponse[0]['body'], '&lt;script&gt;')
        && !str_contains($phase7bFilterResponse[0]['body'], '<script>alert(1).png')
        && count($phase7bEvidenIds) === 3,
    'status=' . (string) ($phase7bFilterResponse[0]['status'] ?? 0)
        . '; escaped=' . (str_contains($phase7bFilterResponse[0]['body'] ?? '', '&lt;script&gt;') ? 'yes' : 'no')
        . '; raw=' . (str_contains($phase7bFilterResponse[0]['body'] ?? '', '<script>alert(1)</script>') ? 'yes' : 'no')
);
$phase7bInvalidFilter = phase2bHttpRequests([[
    'method' => 'GET',
    'path' => '/admin/eviden?service_id=999999999',
    'cookie' => $phase7bUploadIdentity['cookie'],
]]);
check('Filter layanan yang tidak ada ditolak', ($phase7bInvalidFilter[0]['status'] ?? 0) === 422);

$phase7bDetailId = (int) ($phase7bSingleUploadItems[0]['id'] ?? 0);
$phase7bDetailResponses = phase2bHttpRequests([[
    'method' => 'GET',
    'path' => '/admin/eviden/' . $phase7bDetailId,
    'cookie' => $phase7bUploadIdentity['cookie'],
]]);
$phase7bDetailValid = ($phase7bDetailResponses[0]['status'] ?? 0) === 200
    && str_contains($phase7bDetailResponses[0]['body'], 'SHA-256')
    && str_contains($phase7bDetailResponses[0]['body'], '&lt;script&gt;');
check(
    'Detail ADMIN menampilkan metadata dengan escape dan tanpa pratinjau inline',
    $phase7bDetailValid,
    'id=' . $phase7bDetailId . '; status=' . (string) ($phase7bDetailResponses[0]['status'] ?? 0)
        . '; escaped=' . (str_contains($phase7bDetailResponses[0]['body'] ?? '', '&lt;script&gt;') ? 'yes' : 'no')
);

$phase7bDownload = phase2bHttpRequests([[
    'method' => 'GET',
    'path' => '/admin/eviden/' . $phase7bDetailId . '/berkas',
    'cookie' => $phase7bUploadIdentity['cookie'],
]]);
$phase7bDownloadBodyMatches = false;
if (isset($phase7bUploadedItems[0], $phase7bDownload[0])) {
    $downloadExpected = $phase7bUploadedItems[0]['mime'] === 'image/png' ? $phase7bPng : $phase7bJpeg;
    $phase7bDownloadBodyMatches = $phase7bDownload[0]['body'] === $downloadExpected;
}
$phase7bDownloadHeaders = implode("\n", $phase7bDownload[0]['headers'] ?? []);
$phase7bMissingDownload = phase2bHttpRequests([[
    'method' => 'GET',
    'path' => '/admin/eviden/999999999/berkas',
    'cookie' => $phase7bUploadIdentity['cookie'],
]]);
check(
    'Unduh ADMIN mengirim isi tepat, attachment generik, nosniff/no-store, dan ID hilang 404 seragam',
    ($phase7bDownload[0]['status'] ?? 0) === 200
        && $phase7bDownloadBodyMatches
        && str_contains($phase7bDownloadHeaders, 'Content-Disposition: attachment; filename="eviden-')
        && stripos($phase7bDownloadHeaders, 'X-Content-Type-Options: nosniff') !== false
        && stripos($phase7bDownloadHeaders, 'Cache-Control: no-store') !== false
        && ($phase7bMissingDownload[0]['status'] ?? 0) === 404
        && str_contains($phase7bMissingDownload[0]['body'], 'Eviden tidak ditemukan.')
);

$phase7bBadFiles = [
    ['name' => 'forged.jpg', 'type' => 'image/jpeg', 'content' => 'bukan gambar'],
    ['name' => 'document.pdf', 'type' => 'application/pdf', 'content' => '%PDF-1.7 fake'],
    ['name' => 'empty.png', 'type' => 'image/png', 'content' => ''],
];
$phase7bBadRequests = [];
foreach ($phase7bBadFiles as $file) {
    $phase7bBadRequests[] = [
        'method' => 'POST',
        'path' => '/admin/eviden',
        'cookie' => $phase7bUploadIdentity['cookie'],
        'post' => $phase7bUploadPost,
        'files' => [$file],
    ];
}
$phase7bTooManyFiles = [];
for ($index = 0; $index < 4; $index++) {
    $phase7bTooManyFiles[] = ['name' => 'too-many-' . $index . '.png', 'type' => 'image/png', 'content' => $phase7bPng];
}
$phase7bBadRequests[] = [
    'method' => 'POST',
    'path' => '/admin/eviden',
    'cookie' => $phase7bUploadIdentity['cookie'],
    'post' => $phase7bUploadPost,
    'files' => $phase7bTooManyFiles,
];
$phase7bBadRequests[] = [
    'method' => 'POST',
    'path' => '/admin/eviden',
    'cookie' => $phase7bUploadIdentity['cookie'],
    'post' => array_merge($phase7bUploadPost, ['service_id' => '999999999']),
    'files' => [['name' => 'bad-service.png', 'type' => 'image/png', 'content' => $phase7bPng]],
];
$phase7bBadRequests[] = [
    'method' => 'POST',
    'path' => '/admin/eviden',
    'cookie' => $phase7bUploadIdentity['cookie'],
    'post' => array_merge($phase7bUploadPost, ['checkpoint_id' => '999999999']),
    'files' => [['name' => 'bad-checkpoint.png', 'type' => 'image/png', 'content' => $phase7bPng]],
];
$phase7bBadRequests[] = [
    'method' => 'POST',
    'path' => '/admin/eviden',
    'cookie' => $phase7bUploadIdentity['cookie'],
    'post' => array_merge($phase7bUploadPost, ['service_id' => 'not-a-number']),
    'files' => [['name' => 'bad-id.png', 'type' => 'image/png', 'content' => $phase7bPng]],
];
$phase7bBadRequests[] = [
    'method' => 'POST',
    'path' => '/admin/eviden',
    'cookie' => $phase7bUploadIdentity['cookie'],
    'post' => ['_csrf' => $phase7bUploadIdentity['csrf'], 'service_id' => (string) $phase7bServiceId, 'checkpoint_id' => (string) $phase7bCheckpointId],
];
$phase7bCountBeforeInvalid = (int) Db::one('SELECT COUNT(*) AS n FROM evidence_items', [], 'test')['n'];
$phase7bBadResponses = phase2bHttpRequests(
    $phase7bBadRequests,
    ['upload_max_filesize=8M', 'post_max_size=20M']
);
$phase7bCountAfterInvalid = (int) Db::one('SELECT COUNT(*) AS n FROM evidence_items', [], 'test')['n'];
check(
    'Berkas palsu, PDF, kosong, empat berkas, layanan/checkpoint salah, dan tanpa berkas ditolak tanpa baris',
    count($phase7bBadResponses) === count($phase7bBadRequests)
        && count(array_filter($phase7bBadResponses, static fn (array $response): bool => $response['status'] === 422)) === count($phase7bBadRequests)
        && $phase7bCountBeforeInvalid === $phase7bCountAfterInvalid
);

$phase7bPhpUploadError = phase2bHttpRequests([[
    'method' => 'POST',
    'path' => '/admin/eviden',
    'cookie' => $phase7bUploadIdentity['cookie'],
    'post' => $phase7bUploadPost,
    'files' => [['name' => 'php-upload-error.png', 'type' => 'image/png', 'content' => $phase7bPng . str_repeat('x', 2048)]],
]], ['upload_max_filesize=1K', 'post_max_size=20M']);
$phase7bAfterPhpUploadError = (int) Db::one('SELECT COUNT(*) AS n FROM evidence_items', [], 'test')['n'];
check(
    'Status galat upload PHP ditolak tanpa baris',
    ($phase7bPhpUploadError[0]['status'] ?? 0) === 422
        && $phase7bCountAfterInvalid === $phase7bAfterPhpUploadError
);

$phase7bLargeContent = $phase7bPng
    . random_bytes((5 * 1024 * 1024 + 1) - strlen($phase7bPng));
$phase7bOversizeCountBefore = (int) Db::one('SELECT COUNT(*) AS n FROM evidence_items', [], 'test')['n'];
$phase7bOversizeResponse = phase2bHttpRequests([[
    'method' => 'POST',
    'path' => '/admin/eviden',
    'cookie' => $phase7bUploadIdentity['cookie'],
    'post' => $phase7bUploadPost,
    'files' => [['name' => 'oversized.png', 'type' => 'image/png', 'content' => $phase7bLargeContent]],
]], ['upload_max_filesize=12M', 'post_max_size=20M']);
$phase7bOversizeCountAfter = (int) Db::one('SELECT COUNT(*) AS n FROM evidence_items', [], 'test')['n'];
check(
    'Berkas gambar valid yang lebih besar dari 5 MiB ditolak tanpa metadata',
    strlen($phase7bLargeContent) > 5 * 1024 * 1024
        && ($phase7bOversizeResponse[0]['status'] ?? 0) === 422
        && $phase7bOversizeCountBefore === $phase7bOversizeCountAfter
);

$phase7bRollbackTrigger = 'trg_phase7b_force_evidence_rollback_' . bin2hex(random_bytes(4));
$phase7bRollbackBefore = (int) Db::one('SELECT COUNT(*) AS n FROM evidence_items', [], 'test')['n'];
$phase7bFilesBeforeRollback = glob($phase7bStorageDirectory . DIRECTORY_SEPARATOR . '*') ?: [];
$phase7bFilesBeforeRollback = array_values(array_map('basename', $phase7bFilesBeforeRollback));
Db::pdo('test')->exec(
    'CREATE TRIGGER ' . $phase7bRollbackTrigger . ' BEFORE INSERT ON evidence_items FOR EACH ROW BEGIN IF NEW.original_name = \'force-rollback.png\' THEN SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT = \'fixture upload rollback\'; END IF; END'
);
try {
    $phase7bRollbackResponse = phase2bHttpRequests([[
        'method' => 'POST',
        'path' => '/admin/eviden',
        'cookie' => $phase7bUploadIdentity['cookie'],
        'post' => $phase7bUploadPost,
        'files' => [
            ['name' => 'valid-before-failure.png', 'type' => 'image/png', 'content' => $phase7bPng],
            ['name' => 'force-rollback.png', 'type' => 'image/png', 'content' => $phase7bPng],
            ['name' => 'valid-after-failure.png', 'type' => 'image/png', 'content' => $phase7bPng],
        ],
    ]]);
} finally {
    Db::pdo('test')->exec('DROP TRIGGER IF EXISTS ' . $phase7bRollbackTrigger);
}
$phase7bRollbackAfter = (int) Db::one('SELECT COUNT(*) AS n FROM evidence_items', [], 'test')['n'];
$phase7bRollbackFiles = glob($phase7bStorageDirectory . DIRECTORY_SEPARATOR . '*') ?: [];
$phase7bRollbackFiles = array_values(array_map('basename', $phase7bRollbackFiles));
check(
    'Kegagalan di tengah transaksi menghapus seluruh eviden dan file yang sudah dipindah',
    ($phase7bRollbackResponse[0]['status'] ?? 0) === 500
        && $phase7bRollbackBefore === $phase7bRollbackAfter
        && $phase7bFilesBeforeRollback === $phase7bRollbackFiles
        && count($phase7bUploadedItems) === 3,
    'file sebelum=' . count($phase7bFilesBeforeRollback) . '; sesudah=' . count($phase7bRollbackFiles)
);

$phase7bRepairReason = 'Perlu dokumen yang lebih jelas <script>alert(2)</script>';
$phase7bVerifyIdentity = phase2bHttpIdentity($phase7bAdmin2);
$phase7bVerifyResponse = phase2bHttpRequests([[
    'method' => 'POST',
    'path' => '/admin/eviden/' . $phase7bEvidenIds[0] . '/verifikasi',
    'cookie' => $phase7bVerifyIdentity['cookie'],
    'post' => ['_csrf' => $phase7bVerifyIdentity['csrf'], 'decision' => 'TERVERIFIKASI', 'review_reason' => ''],
]]);
$phase7bVerified = Db::one(
    'SELECT status, uploaded_by, verified_by, verified_at, review_reason FROM evidence_items WHERE id = ?',
    [$phase7bEvidenIds[0]],
    'test'
);
$phase7bSecondDecision = phase2bHttpRequests([[
    'method' => 'POST',
    'path' => '/admin/eviden/' . $phase7bEvidenIds[0] . '/verifikasi',
    'cookie' => $phase7bUploadIdentity['cookie'],
    'post' => ['_csrf' => $phase7bUploadIdentity['csrf'], 'decision' => 'PERLU_PERBAIKAN', 'review_reason' => $phase7bRepairReason],
]]);
$phase7bSelfVerifyResponse = phase2bHttpRequests([[
    'method' => 'POST',
    'path' => '/admin/eviden/' . $phase7bEvidenIds[1] . '/verifikasi',
    'cookie' => $phase7bUploadIdentity['cookie'],
    'post' => ['_csrf' => $phase7bUploadIdentity['csrf'], 'decision' => 'PERLU_PERBAIKAN', 'review_reason' => $phase7bRepairReason],
]]);
$phase7bSelfDetail = phase2bHttpRequests([[
    'method' => 'GET',
    'path' => '/admin/eviden/' . $phase7bEvidenIds[1],
    'cookie' => $phase7bUploadIdentity['cookie'],
]]);
$phase7bVerifyRows = Db::all(
    'SELECT id, status, uploaded_by, verified_by, verified_at, review_reason FROM evidence_items WHERE id IN (?, ?) ORDER BY id DESC',
    [$phase7bEvidenIds[0], $phase7bEvidenIds[1]],
    'test'
);
check(
    'Verifikasi final mengunci DIUNGGAH, menyimpan aktor/waktu, menerima alasan, dan menandai verifikasi sendiri',
    ($phase7bVerifyResponse[0]['status'] ?? 0) === 303
        && $phase7bVerified !== null
        && $phase7bVerified['status'] === 'TERVERIFIKASI'
        && (int) $phase7bVerified['uploaded_by'] !== (int) $phase7bVerified['verified_by']
        && $phase7bVerified['verified_at'] !== null
        && ($phase7bSecondDecision[0]['status'] ?? 0) === 409
        && ($phase7bSelfVerifyResponse[0]['status'] ?? 0) === 303
        && ($phase7bVerifyRows[1]['status'] ?? null) === 'PERLU_PERBAIKAN'
        && (int) ($phase7bVerifyRows[1]['uploaded_by'] ?? 0) === (int) ($phase7bVerifyRows[1]['verified_by'] ?? -1)
        && ($phase7bVerifyRows[1]['review_reason'] ?? null) === $phase7bRepairReason
        && str_contains($phase7bSelfDetail[0]['body'] ?? '', 'diverifikasi oleh pengunggah yang sama')
);

$phase7bInvalidReviewRequests = [];
foreach (['', str_repeat('x', 501), "\xC3\x28"] as $reason) {
    $phase7bInvalidReviewRequests[] = [
        'method' => 'POST',
        'path' => '/admin/eviden/' . $phase7bEvidenIds[2] . '/verifikasi',
        'cookie' => $phase7bUploadIdentity['cookie'],
        'post' => ['_csrf' => $phase7bUploadIdentity['csrf'], 'decision' => 'PERLU_PERBAIKAN', 'review_reason' => $reason],
    ];
}
$phase7bInvalidReviewRequests[] = [
    'method' => 'POST',
    'path' => '/admin/eviden/' . $phase7bEvidenIds[2] . '/verifikasi',
    'cookie' => $phase7bUploadIdentity['cookie'],
    'post' => ['_csrf' => $phase7bUploadIdentity['csrf'], 'decision' => 'INVALID', 'review_reason' => 'reason'],
];
$phase7bInvalidReviewResponses = phase2bHttpRequests($phase7bInvalidReviewRequests);
$phase7bInvalidReviewRow = Db::one(
    'SELECT status, verified_by, verified_at, review_reason FROM evidence_items WHERE id = ?',
    [$phase7bEvidenIds[2]],
    'test'
);
check(
    'Alasan wajib, batas 500, UTF-8, dan keputusan validasi ditegakkan',
    count($phase7bInvalidReviewResponses) === 4
        && count(array_filter($phase7bInvalidReviewResponses, static fn (array $response): bool => $response['status'] === 404)) === 4
        && $phase7bInvalidReviewRow['status'] === 'DIUNGGAH'
        && $phase7bInvalidReviewRow['verified_by'] === null
        && $phase7bInvalidReviewRow['verified_at'] === null
);

$phase7bAuditRows = Db::all(
    'SELECT action, entity_type, entity_id, message FROM audit_logs WHERE entity_type = ? AND entity_id IN (?, ?, ?) ORDER BY id',
    ['evidence_items', $phase7bEvidenIds[0], $phase7bEvidenIds[1], $phase7bEvidenIds[2]],
    'test'
);
$phase7bAuditPassed = $phase7bAuditRows !== [];
foreach ($phase7bAuditRows as $auditRow) {
    $phase7bAuditPassed = $phase7bAuditPassed
        && in_array($auditRow['action'], ['evidence_uploaded', 'evidence_downloaded', 'evidence_reviewed'], true)
        && $auditRow['entity_type'] === 'evidence_items'
        && !str_contains((string) $auditRow['message'], '<script>')
        && !str_contains((string) $auditRow['message'], $phase7bRepairReason)
        && !str_contains((string) $auditRow['message'], $phase7bPng);
}
check('Audit eviden hanya berisi metadata dan tidak mencatat nama asli, alasan, atau isi berkas', $phase7bAuditPassed);

$phase7bNonAdminDownload = phase2bHttpRequests([[
    'method' => 'GET',
    'path' => '/admin/eviden/' . $phase7bEvidenIds[0] . '/berkas',
    'cookie' => $phase7bIdentities['SISWA']['cookie'],
]]);
$phase7bMissingFile = $phase7bStorageDirectory . DIRECTORY_SEPARATOR . $phase7bUploadedItems[2]['stored_name'];
if (is_file($phase7bMissingFile)) {
    unlink($phase7bMissingFile);
}
$phase7bMissingPhysicalFile = phase2bHttpRequests([[
    'method' => 'GET',
    'path' => '/admin/eviden/' . $phase7bEvidenIds[2] . '/berkas',
    'cookie' => $phase7bUploadIdentity['cookie'],
]]);
check(
    'Role lain tidak dapat mengunduh; file fisik hilang memberi 404 tanpa jalur internal',
    in_array($phase7bNonAdminDownload[0]['status'] ?? 0, [302, 403], true)
        && ($phase7bMissingPhysicalFile[0]['status'] ?? 0) === 404
        && !str_contains($phase7bMissingPhysicalFile[0]['body'] ?? '', 'storage')
        && !str_contains($phase7bMissingPhysicalFile[0]['body'] ?? '', $phase7bStorageDirectory)
);

function phase7bConcurrentVerification(array $requests): array
{
    $servers = [];
    $sockets = [];
    foreach ($requests as $request) {
        $probe = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);
        if ($probe === false) {
            return [];
        }
        $address = stream_socket_get_name($probe, false);
        fclose($probe);
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
        $servers[] = ['address' => $address, 'process' => $process, 'pipes' => $pipes];
    }
    try {
        foreach ($servers as $index => $server) {
            for ($attempt = 0; $attempt < 40; $attempt++) {
                $socket = @stream_socket_client('tcp://' . $server['address'], $errorCode, $errorMessage, 0.25);
                if ($socket !== false) {
                    break;
                }
                usleep(25000);
            }
            if ($socket === false) {
                return [];
            }
            stream_set_timeout($socket, 8);
            $post = http_build_query($requests[$index]['post']);
            fwrite(
                $socket,
                "POST " . $requests[$index]['path'] . " HTTP/1.1\r\n"
                    . 'Host: ' . $server['address'] . "\r\n"
                    . 'Cookie: ' . $requests[$index]['cookie'] . "\r\n"
                    . "Content-Type: application/x-www-form-urlencoded\r\n"
                    . 'Content-Length: ' . strlen($post) . "\r\n"
                    . "Connection: close\r\n\r\n" . $post
            );
            $sockets[] = $socket;
        }
        usleep(300000);
        Db::pdo('test')->commit();
        $responses = [];
        foreach ($sockets as $socket) {
            $raw = (string) stream_get_contents($socket);
            preg_match('/^HTTP\/\S+\s+(\d+)/', $raw, $matches);
            $responses[] = (int) ($matches[1] ?? 0);
            fclose($socket);
        }
        return $responses;
    } finally {
        if (Db::pdo('test')->inTransaction()) {
            Db::pdo('test')->rollBack();
        }
        foreach ($sockets as $socket) {
            if (is_resource($socket)) {
                fclose($socket);
            }
        }
        foreach ($servers as $server) {
            proc_terminate($server['process']);
            foreach ($server['pipes'] as $pipe) {
                fclose($pipe);
            }
            proc_close($server['process']);
        }
    }
}

$phase7bConcurrentId = $phase7bEvidenIds[2];
$phase7bConcurrentAdmin1 = phase2bHttpIdentity($phase7bAdmin);
$phase7bConcurrentAdmin2 = phase2bHttpIdentity($phase7bAdmin2);
Db::pdo('test')->beginTransaction();
Db::one(
    'SELECT id FROM evidence_items WHERE id = ? FOR UPDATE',
    [$phase7bConcurrentId],
    'test'
);
$phase7bConcurrentResults = phase7bConcurrentVerification([
    [
        'cookie' => $phase7bConcurrentAdmin1['cookie'],
        'path' => '/admin/eviden/' . $phase7bConcurrentId . '/verifikasi',
        'post' => ['_csrf' => $phase7bConcurrentAdmin1['csrf'], 'decision' => 'TERVERIFIKASI'],
    ],
    [
        'cookie' => $phase7bConcurrentAdmin2['cookie'],
        'path' => '/admin/eviden/' . $phase7bConcurrentId . '/verifikasi',
        'post' => ['_csrf' => $phase7bConcurrentAdmin2['csrf'], 'decision' => 'TERVERIFIKASI'],
    ],
]);
$phase7bConcurrentFinal = Db::one(
    'SELECT status, verified_by FROM evidence_items WHERE id = ?',
    [$phase7bConcurrentId],
    'test'
);
check(
    'Dua permintaan verifikasi yang menunggu row lock menghasilkan tepat satu keputusan sukses',
    count($phase7bConcurrentResults) === 2
        && count(array_filter($phase7bConcurrentResults, static fn (int $status): bool => $status === 303)) === 1
        && count(array_filter($phase7bConcurrentResults, static fn (int $status): bool => $status === 409)) === 1
        && $phase7bConcurrentFinal['status'] === 'TERVERIFIKASI'
);

$phase7bProbePath = tempnam(__DIR__, '.evidence_autoload_probe_');
$phase7bProbeScript = <<<'PHP'
<?php
declare(strict_types=1);
$target = 'App\\Controllers\\EvidenceController';
$skipTarget = in_array('--missing', $argv, true);
if ($skipTarget) {
    spl_autoload_register(static function (string $class) use ($target): void {
        if ($class === $target) {
            return;
        }
        require __DIR__ . '/../app/autoload.php';
    });
} else {
    require __DIR__ . '/../app/autoload.php';
}
$loaded = class_exists($target);
echo ($loaded ? '[LOLOS] ' : '[GAGAL] ') . 'EvidenceController dapat dimuat' . PHP_EOL;
exit($loaded ? 0 : 1);
PHP;
file_put_contents($phase7bProbePath, $phase7bProbeScript);
try {
    exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($phase7bProbePath) . ' --missing 2>&1', $phase7bMissingOutput, $phase7bMissingExit);
    exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($phase7bProbePath) . ' 2>&1', $phase7bRestoredOutput, $phase7bRestoredExit);
} finally {
    if (is_file($phase7bProbePath)) {
        unlink($phase7bProbePath);
    }
}
check(
    'Autoload probe benar-benar merah saat kelas sengaja dilewati lalu hijau dengan autoloader aktual',
    $phase7bMissingExit === 1
        && str_contains(implode(PHP_EOL, $phase7bMissingOutput), '[GAGAL]')
        && $phase7bRestoredExit === 0
        && str_contains(implode(PHP_EOL, $phase7bRestoredOutput), '[LOLOS]'),
    'missing=' . implode(PHP_EOL, $phase7bMissingOutput)
        . '; restored=' . implode(PHP_EOL, $phase7bRestoredOutput)
);
echo 'Probe kelas tidak termuat: exit code ' . $phase7bMissingExit . ' (gagal sesuai harapan); '
    . 'probe autoloader dipulihkan: exit code ' . $phase7bRestoredExit . ' (lulus).' . PHP_EOL;
