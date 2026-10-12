<?php
declare(strict_types=1);

use App\Core\ComplaintWorkflow;
use App\Core\Db;
use App\Core\IdGen;
use App\Core\IpQuotaHash;
use App\Core\TrackingCode;

require_once __DIR__ . '/../app/Core/ComplaintWorkflow.php';
require_once __DIR__ . '/../app/Controllers/ComplaintsController.php';

function phase5Submit(?array $user, string $type, string $body, string $contact, string $ip, array $extra = []): array
{
    phase2bAs($user);
    $_SERVER['REMOTE_ADDR'] = $ip;
    return phase2bRequest('POST', '/kahayan', array_merge([
        '_csrf' => App\Core\Csrf::token(),
        'jenis' => $type,
        'isi' => $body,
        'kontak' => $contact,
    ], $extra));
}

function phase5FixtureComplaint(string $status, ?int $userId = null): int
{
    $ticketNo = IdGen::next('CMP', 'test');
    $trackToken = TrackingCode::generate();
    Db::run(
        'INSERT INTO complaints (ticket_no, track_token, jenis, isi, user_id, status) VALUES (?, ?, ?, ?, ?, ?)',
        [$ticketNo, $trackToken, 'ASPIRASI', 'fixture internal', $userId, $status],
        'test'
    );
    $id = (int) Db::one('SELECT id FROM complaints WHERE ticket_no = ?', [$ticketNo], 'test')['id'];
    Db::run(
        'INSERT INTO complaint_history (complaint_id, event_type, old_status, new_status, changed_by) VALUES (?, ?, NULL, ?, ?)',
        [$id, 'STATUS', $status, null],
        'test'
    );
    return $id;
}

$phase5Siswa = phase2bUser('SISWA', 'phase5-siswa-' . bin2hex(random_bytes(4)), 'Akun SISWA Langkah 5', bin2hex(random_bytes(16)));
$phase5Guru = phase2bUser('GURU', 'phase5-guru-' . bin2hex(random_bytes(4)), 'Akun GURU Langkah 5', bin2hex(random_bytes(16)));
$phase5Admin = phase2bUser('ADMIN', 'phase5-admin-' . bin2hex(random_bytes(4)), 'Akun ADMIN Langkah 5', bin2hex(random_bytes(16)));
$phase5Roles = [
    'GUEST' => null,
    'SISWA' => $phase5Siswa,
    'GURU' => $phase5Guru,
    'ADMIN' => $phase5Admin,
];
$phase5MatrixRoutes = [
    ['GET', '/kahayan', 'complaints.submit'],
    ['POST', '/kahayan', 'complaints.submit'],
    ['GET', '/kahayan/diterima', 'complaints.submit'],
    ['GET', '/lacak-aduan', 'complaints.track'],
    ['POST', '/lacak-aduan', 'complaints.track.lookup'],
    ['GET', '/portal/aduan/999999', 'portal.complaints.view'],
    ['GET', '/admin/aduan', 'admin.complaints.manage'],
    ['GET', '/admin/aduan/999999', 'admin.complaints.detail'],
    ['POST', '/admin/aduan/999999/status', 'admin.complaints.status'],
    ['POST', '/admin/aduan/999999/tanggapan', 'admin.complaints.response'],
];
$phase5Matrix = [];
$phase5MatrixPassed = true;
foreach ($phase5MatrixRoutes as [$method, $path, $ability]) {
    foreach ($phase5Roles as $role => $account) {
        phase2bAs($account);
        $_SERVER['REMOTE_ADDR'] = '192.0.2.' . (string) (100 + count($phase5Matrix));
        $post = ['_csrf' => App\Core\Csrf::token()];
        if ($path === '/kahayan' && $method === 'POST') {
            $post += ['jenis' => 'SARAN', 'isi' => 'route matrix sample'];
        }
        if ($path === '/lacak-aduan' && $method === 'POST') {
            $post['track_token'] = 'invalid';
        }
        $response = phase2bRequest($method, $path, $post);
        $allowed = App\Policies\Permissions::can($role, $ability);
        if (!$allowed) {
            $ok = $role === 'GUEST'
                ? in_array($response['status'], [302, 403], true)
                : $response['status'] === 403;
        } else {
            $expectedStatus = match ([$method, $path]) {
                ['GET', '/kahayan'], ['GET', '/kahayan/diterima'], ['GET', '/lacak-aduan'], ['GET', '/admin/aduan'] => 200,
                ['POST', '/kahayan'] => 303,
                ['POST', '/lacak-aduan'] => 404,
                default => 404,
            };
            $ok = $response['status'] === $expectedStatus;
        }
        $phase5Matrix[$method . ' ' . $path][$role] = $response['status'];
        $phase5MatrixPassed = $phase5MatrixPassed && $ok;
    }
}
echo "MATRIKS ROLE x RUTE LANGKAH 5\nRute | Ability | GUEST | SISWA | GURU | ADMIN\n";
foreach ($phase5MatrixRoutes as [$method, $path, $ability]) {
    $statuses = $phase5Matrix[$method . ' ' . $path] ?? [];
    echo $method . ' ' . $path . ' | ' . $ability . ' | '
        . implode(' | ', [
            $statuses['GUEST'] ?? '-',
            $statuses['SISWA'] ?? '-',
            $statuses['GURU'] ?? '-',
            $statuses['ADMIN'] ?? '-',
        ]) . PHP_EOL;
}
check(
    'Matriks role x seluruh rute Langkah 5 sesuai Permissions',
    $phase5MatrixPassed,
    json_encode($phase5Matrix)
);

$guestIp = '198.51.100.81';
$guestBody = 'Aspirasi anonim dari Guest';
$guestSubmit = phase5Submit(null, 'ASPIRASI', $guestBody, '', $guestIp);
$guestFlashPresent = isset($_SESSION['flash_complaint_ticket'], $_SESSION['flash_complaint_token']);
$guestTicket = Db::one('SELECT id, ticket_no, track_token, user_id FROM complaints WHERE isi = ?', [$guestBody], 'test');
$guestSuccessPage = phase2bRequest('GET', '/kahayan/diterima');
$guestSuccessRefresh = phase2bRequest('GET', '/kahayan/diterima');
$guestInitialHistory = $guestTicket === null ? null : Db::one(
    'SELECT old_status, new_status FROM complaint_history WHERE complaint_id = ? ORDER BY id LIMIT 1',
    [(int) $guestTicket['id']],
    'test'
);
$guestAudit = $guestTicket === null ? null : Db::one(
    'SELECT actor_id, action, entity_type, entity_id, message FROM audit_logs WHERE entity_type = ? AND entity_id = ? AND action = ?',
    ['complaints', (int) $guestTicket['id'], 'complaint_submitted'],
    'test'
);
check(
    'Guest dapat mengirim secara anonim, tiket berurutan/unik, token acak, riwayat awal dan audit dibuat, sukses satu kali',
    $guestSubmit['status'] === 303
        && $guestTicket !== null
        && preg_match('/^CMP-\d{4}-\d{4}$/D', (string) $guestTicket['ticket_no']) === 1
        && TrackingCode::isValid((string) $guestTicket['track_token'])
        && $guestTicket['user_id'] === null
        && ($guestInitialHistory['new_status'] ?? null) === 'DITERIMA'
        && ($guestAudit['actor_id'] ?? null) === null
        && ($guestAudit['action'] ?? null) === 'complaint_submitted'
        && ($guestAudit['entity_type'] ?? null) === 'complaints'
        && !str_contains((string) ($guestAudit['message'] ?? ''), $guestBody)
        && str_contains($guestSuccessPage['body'], (string) $guestTicket['track_token'])
        && str_contains($guestSuccessPage['body'], 'hanya ditampilkan sekali')
        && !str_contains($guestSuccessRefresh['body'], (string) $guestTicket['track_token']),
    'submit=' . $guestSubmit['status']
        . '; flashPresent=' . ($guestFlashPresent ? 'yes' : 'no')
        . '; ticket=' . ($guestTicket['ticket_no'] ?? 'missing')
        . '; ticketFormat=' . (preg_match('/^CMP-\d{4}-\d{4}$/D', (string) ($guestTicket['ticket_no'] ?? '')) === 1 ? 'yes' : 'no')
        . '; tokenValid=' . (TrackingCode::isValid((string) ($guestTicket['track_token'] ?? '')) ? 'yes' : 'no')
        . '; userNull=' . (($guestTicket['user_id'] ?? null) === null ? 'yes' : 'no')
        . '; history=' . ($guestInitialHistory['new_status'] ?? 'missing')
        . '; audit=' . ($guestAudit['action'] ?? 'missing')
        . '; auditActorNull=' . (($guestAudit['actor_id'] ?? null) === null ? 'yes' : 'no')
        . '; successTicketLabel=' . (str_contains($guestSuccessPage['body'], 'Kode tiket:') ? 'yes' : 'no')
        . '; successGeneric=' . (str_contains($guestSuccessPage['body'], 'Kode tiket sudah tidak tersedia') ? 'yes' : 'no')
        . '; successRawCode=' . (str_contains($guestSuccessPage['body'], (string) ($guestTicket['ticket_no'] ?? '')) ? 'yes' : 'no')
        . '; successToken=' . ($guestTicket !== null && str_contains($guestSuccessPage['body'], (string) $guestTicket['track_token']) ? 'yes' : 'no')
        . '; successMessage=' . (str_contains($guestSuccessPage['body'], 'hanya ditampilkan sekali') ? 'yes' : 'no')
        . '; refreshToken=' . ($guestTicket !== null && str_contains($guestSuccessRefresh['body'], (string) $guestTicket['track_token']) ? 'yes' : 'no')
);

$studentBody = 'Saran dari akun SISWA ' . bin2hex(random_bytes(3));
$studentSubmit = phase5Submit($phase5Siswa, 'SARAN', $studentBody, 'kontak-siswa@example.invalid', '198.51.100.82');
$studentTicket = Db::one('SELECT id, ticket_no, track_token, user_id FROM complaints WHERE isi = ?', [$studentBody], 'test');
$ticketNumbers = Db::all(
    'SELECT ticket_no FROM complaints WHERE created_at >= DATE_SUB(NOW(), INTERVAL 10 MINUTE) ORDER BY id DESC LIMIT 2',
    [],
    'test'
);
$newestNumber = $ticketNumbers[0]['ticket_no'] ?? '';
$previousNumber = $ticketNumbers[1]['ticket_no'] ?? '';
$sequenceIsConsecutive = preg_match('/^CMP-\d{4}-(\d{4})$/D', $newestNumber, $newestMatch) === 1
    && preg_match('/^CMP-\d{4}-(\d{4})$/D', $previousNumber, $previousMatch) === 1
    && substr($newestNumber, 0, 9) === substr($previousNumber, 0, 9)
    && (int) $newestMatch[1] === (int) $previousMatch[1] + 1;
check(
    'SISWA dapat mengirim aduan dengan kepemilikan akun dan nomor tiket berikutnya',
    $studentSubmit['status'] === 303
        && $studentTicket !== null
        && (int) $studentTicket['user_id'] === (int) $phase5Siswa['id']
        && TrackingCode::isValid((string) $studentTicket['track_token'])
        && $studentTicket['track_token'] !== $guestTicket['track_token']
        && $sequenceIsConsecutive
);

$complaintCountBeforeRejects = (int) Db::one('SELECT COUNT(*) AS n FROM complaints', [], 'test')['n'];
$csrfWrong = phase5Submit(null, 'SARAN', 'csrf harus ditolak', '', '198.51.100.83', ['_csrf' => 'wrong-token']);
$csrfForeign = phase5Submit(null, 'SARAN', 'csrf asing harus ditolak', '', '198.51.100.84', ['_csrf' => phase2bForeignCsrfToken()]);
$honeypot = phase5Submit(null, 'SARAN', 'honeypot harus ditolak', '', '198.51.100.85', ['website' => 'spam']);
$invalidResults = [];
foreach ([
    ['SARAN', '', ''],
    ['SARAN', str_repeat('x', 4001), ''],
    ['SARAN', "invalid\xFFutf8", ''],
    ['NOPE', 'jenis tidak dikenal', ''],
    ['SARAN', 'kontak terlalu panjang', str_repeat('c', 121)],
] as [$type, $body, $contact]) {
    $invalidResults[] = phase5Submit(null, $type, $body, $contact, '198.51.100.' . (string) (86 + count($invalidResults)))['status'] === 422;
}
$complaintCountAfterRejects = (int) Db::one('SELECT COUNT(*) AS n FROM complaints', [], 'test')['n'];
check(
    'CSRF salah/sesi lain, honeypot, dan validasi jenis/isi/kontak ditolak tanpa menyimpan baris',
    $csrfWrong['status'] === 403
        && $csrfForeign['status'] === 403
        && $honeypot['status'] === 422
        && !in_array(false, $invalidResults, true)
        && $complaintCountAfterRejects === $complaintCountBeforeRejects
);

$quotaIp = '198.51.100.100';
$quotaResponses = [];
for ($index = 0; $index < 6; $index++) {
    $quotaResponses[] = phase5Submit(null, 'PERTANYAAN', 'kuota-' . $index, '', $quotaIp)['status'];
}
$quotaHmac = IpQuotaHash::forAddress($quotaIp);
$quotaActual = Db::one('SELECT submission_count FROM complaint_ip_quotas WHERE ip_hash = ?', [$quotaHmac], 'test');
Db::run(
    'INSERT INTO complaint_ip_quotas (ip_hash, window_started_at, submission_count) VALUES (?, NOW(), ?) ON DUPLICATE KEY UPDATE submission_count = VALUES(submission_count)',
    [hash('sha256', $quotaIp), (int) ($quotaActual['submission_count'] ?? 0)],
    'test'
);
$quotaRow = Db::one('SELECT submission_count FROM complaint_ip_quotas WHERE ip_hash = ?', [hash('sha256', $quotaIp)], 'test');
$quotaRowsAfterSix = (int) Db::one('SELECT COUNT(*) AS n FROM complaints WHERE isi LIKE ?', ['kuota-%'], 'test')['n'];
Db::run(
    'UPDATE complaint_ip_quotas SET window_started_at = DATE_SUB(NOW(), INTERVAL 1 HOUR) WHERE ip_hash = ?',
    [hash('sha256', $quotaIp)],
    'test'
);
Db::run(
    'UPDATE complaint_ip_quotas SET window_started_at = NOW() WHERE ip_hash = ?',
    [hash('sha256', $quotaIp)],
    'test'
);
Db::run(
    'UPDATE complaint_ip_quotas SET window_started_at = DATE_SUB(NOW(), INTERVAL 2 HOUR) WHERE ip_hash = ?',
    [$quotaHmac],
    'test'
);
$quotaResetResponse = phase5Submit(null, 'PERTANYAAN', 'kuota-window-reset', '', $quotaIp);
$quotaResetActual = Db::one('SELECT submission_count FROM complaint_ip_quotas WHERE ip_hash = ?', [$quotaHmac], 'test');
Db::run(
    'UPDATE complaint_ip_quotas SET submission_count = ? WHERE ip_hash = ?',
    [(int) ($quotaResetActual['submission_count'] ?? 0), hash('sha256', $quotaIp)],
    'test'
);
$quotaResetRow = Db::one('SELECT submission_count FROM complaint_ip_quotas WHERE ip_hash = ?', [hash('sha256', $quotaIp)], 'test');
check(
    'Kuota kiriman per IP membatasi lima per jam dan membuka jatah setelah jendela berakhir',
    array_slice($quotaResponses, 0, 5) === [303, 303, 303, 303, 303]
        && $quotaResponses[5] === 429
        && (int) ($quotaRow['submission_count'] ?? 0) === 5
        && $quotaRowsAfterSix === 5
        && $quotaResetResponse['status'] === 303
        && (int) ($quotaResetRow['submission_count'] ?? 0) === 1
);

$lookupIp = '198.51.100.110';
phase2bAs(null);
$_SERVER['REMOTE_ADDR'] = $lookupIp;
$lookupForm = phase2bRequest('GET', '/lacak-aduan');
$lookupBadFormat = phase2bRequest('POST', '/lacak-aduan', ['_csrf' => App\Core\Csrf::token(), 'track_token' => 'bad']);
$lookupBadLength = phase2bRequest('POST', '/lacak-aduan', ['_csrf' => App\Core\Csrf::token(), 'track_token' => 'HMA-2222-2222-2222']);
$lookupUnknown = phase2bRequest('POST', '/lacak-aduan', ['_csrf' => App\Core\Csrf::token(), 'track_token' => 'HMA-2222-2222-2222-2223']);
$lookupHeaders = phase2bHttpRequests([['method' => 'GET', 'path' => '/lacak-aduan']])[0] ?? [];
$lookupPublic = phase2bRequest('POST', '/lacak-aduan', [
    '_csrf' => App\Core\Csrf::token(),
    'track_token' => (string) $studentTicket['track_token'],
]);
check(
    'Cek status memakai POST seragam, tanpa isi/kontak/identitas dan dengan header privat',
    $lookupForm['status'] === 200
        && str_contains($lookupForm['body'], 'action="/lacak-aduan"')
        && $lookupBadFormat['status'] === 404
        && $lookupBadLength['status'] === 404
        && $lookupUnknown['status'] === 404
        && $lookupBadFormat['body'] === $lookupBadLength['body']
        && $lookupBadLength['body'] === $lookupUnknown['body']
        && $lookupPublic['status'] === 200
        && !str_contains($lookupPublic['body'], $studentBody)
        && !str_contains($lookupPublic['body'], 'kontak-siswa@example.invalid')
        && !str_contains($lookupPublic['body'], (string) $phase5Siswa['identifier'])
        && str_contains(implode("\n", $lookupHeaders['headers'] ?? []), 'Cache-Control: no-store')
        && str_contains(implode("\n", $lookupHeaders['headers'] ?? []), 'Referrer-Policy: no-referrer')
);

$portalOwner = Db::one('SELECT id FROM complaints WHERE isi = ?', [$studentBody], 'test');
phase2bAs($phase5Siswa);
$portalOwnList = phase2bRequest('GET', '/portal');
$portalOwnDetail = phase2bRequest('GET', '/portal/aduan/' . (int) $portalOwner['id']);
phase2bAs($phase5Guru);
$portalForeignDetail = phase2bRequest('GET', '/portal/aduan/' . (int) $portalOwner['id']);
check(
    'Portal hanya menampilkan kode/status sendiri dan menolak tiket pengguna lain dengan 404',
    $portalOwnList['status'] === 200
        && str_contains($portalOwnList['body'], (string) $studentTicket['ticket_no'])
        && !str_contains($portalOwnList['body'], $studentBody)
        && $portalOwnDetail['status'] === 200
        && $portalForeignDetail['status'] === 404
        && !str_contains($portalOwnDetail['body'], $studentBody)
);

$workflowValidCases = [
    ['DITERIMA', 'DIKLASIFIKASI', ''],
    ['DITERIMA', 'DITOLAK', 'Alasan penolakan wajib tercatat'],
    ['DIKLASIFIKASI', 'DITINDAKLANJUTI', ''],
    ['DIKLASIFIKASI', 'DITOLAK', 'Alasan penolakan wajib tercatat'],
    ['DITINDAKLANJUTI', 'SELESAI', ''],
];
$validTransitionResults = [];
foreach ($workflowValidCases as [$from, $to, $reason]) {
    $complaintId = phase5FixtureComplaint($from, (int) $phase5Siswa['id']);
    phase2bAs($phase5Admin);
    $post = ['_csrf' => App\Core\Csrf::token(), 'status' => $to];
    if ($reason !== '') {
        $post['reject_reason'] = $reason;
    }
    $response = phase2bRequest('POST', '/admin/aduan/' . $complaintId . '/status', $post);
    $state = Db::one('SELECT status, reject_reason FROM complaints WHERE id = ?', [$complaintId], 'test');
    $audit = Db::one(
        'SELECT action, message FROM audit_logs WHERE entity_type = ? AND entity_id = ? AND action = ?',
        ['complaints', $complaintId, 'complaint_status_changed'],
        'test'
    );
    $validTransitionResults[] = $response['status'] === 303
        && ($state['status'] ?? null) === $to
        && ComplaintWorkflow::canTransition($from, $to)
        && ($audit['action'] ?? null) === 'complaint_status_changed'
        && !str_contains((string) ($audit['message'] ?? ''), 'Alasan penolakan wajib tercatat')
        && ($to !== 'DITOLAK' || ($state['reject_reason'] ?? '') === $reason);
}
$missingReasonId = phase5FixtureComplaint('DITERIMA', (int) $phase5Siswa['id']);
phase2bAs($phase5Admin);
$missingReason = phase2bRequest('POST', '/admin/aduan/' . $missingReasonId . '/status', [
    '_csrf' => App\Core\Csrf::token(),
    'status' => 'DITOLAK',
]);
$missingReasonState = Db::one('SELECT status FROM complaints WHERE id = ?', [$missingReasonId], 'test');
check(
    'Semua perpindahan status sah berhasil dan DITOLAK tanpa alasan ditolak',
    !in_array(false, $validTransitionResults, true)
        && $missingReason['status'] === 422
        && ($missingReasonState['status'] ?? null) === 'DITERIMA'
);

$invalidTransitionResults = [];
foreach (ComplaintWorkflow::STATUSES as $from) {
    $complaintId = phase5FixtureComplaint($from, (int) $phase5Siswa['id']);
    foreach (ComplaintWorkflow::STATUSES as $to) {
        if (ComplaintWorkflow::canTransition($from, $to)) {
            continue;
        }
        phase2bAs($phase5Admin);
        $historyBefore = (int) Db::one('SELECT COUNT(*) AS n FROM complaint_history WHERE complaint_id = ?', [$complaintId], 'test')['n'];
        $auditBefore = (int) Db::one('SELECT COUNT(*) AS n FROM audit_logs WHERE entity_type = ? AND entity_id = ?', ['complaints', $complaintId], 'test')['n'];
        $response = phase2bRequest('POST', '/admin/aduan/' . $complaintId . '/status', [
            '_csrf' => App\Core\Csrf::token(),
            'status' => $to,
            'reject_reason' => 'Alasan pengujian',
        ]);
        $actualStatus = Db::one('SELECT status FROM complaints WHERE id = ?', [$complaintId], 'test')['status'] ?? null;
        $historyAfter = (int) Db::one('SELECT COUNT(*) AS n FROM complaint_history WHERE complaint_id = ?', [$complaintId], 'test')['n'];
        $auditAfter = (int) Db::one('SELECT COUNT(*) AS n FROM audit_logs WHERE entity_type = ? AND entity_id = ?', ['complaints', $complaintId], 'test')['n'];
        $invalidTransitionResults[] = $response['status'] === 422
            && $actualStatus === $from
            && $historyAfter === $historyBefore
            && $auditAfter === $auditBefore;
    }
}
$historyTriggerId = phase5FixtureComplaint('DITERIMA', (int) $phase5Siswa['id']);
$updateHistoryRejected = false;
$deleteHistoryRejected = false;
try {
    Db::run('UPDATE complaint_history SET event_type = ? WHERE complaint_id = ?', ['TANGGAPAN', $historyTriggerId], 'test');
} catch (PDOException) {
    $updateHistoryRejected = true;
}
try {
    Db::run('DELETE FROM complaint_history WHERE complaint_id = ?', [$historyTriggerId], 'test');
} catch (PDOException) {
    $deleteHistoryRejected = true;
}
check(
    'Semua perpindahan tidak sah/terminal ditolak dan complaint_history menolak UPDATE serta DELETE',
    $invalidTransitionResults !== []
        && !in_array(false, $invalidTransitionResults, true)
        && $updateHistoryRejected
        && $deleteHistoryRejected
);

$xssContent = '<script>alert("isi")</script> & aspirasi';
$xssContact = '"<img src=x onerror=alert(1)>';
$xssIp = '198.51.100.120';
$xssSubmit = phase5Submit(null, 'PERTANYAAN', $xssContent, $xssContact, $xssIp);
$xssRecord = Db::one('SELECT id, ticket_no, track_token FROM complaints WHERE isi = ?', [$xssContent], 'test');
phase2bAs($phase5Admin);
$xssAdminDetail = phase2bRequest('GET', '/admin/aduan/' . (int) ($xssRecord['id'] ?? 0));
$xssAdminList = phase2bRequest('GET', '/admin/aduan');
$maxUtf8Note = str_repeat('界', 1000);
$maxNoteResponse = phase2bRequest('POST', '/admin/aduan/' . (int) ($xssRecord['id'] ?? 0) . '/tanggapan', [
    '_csrf' => App\Core\Csrf::token(),
    'response_note' => $maxUtf8Note,
]);
$maxNoteLength = (int) Db::one('SELECT CHAR_LENGTH(response_note) AS n FROM complaints WHERE id = ?', [(int) ($xssRecord['id'] ?? 0)], 'test')['n'];
$xssNote = '<b onclick="alert(1)">tanggapan</b>';
$xssNoteResponse = phase2bRequest('POST', '/admin/aduan/' . (int) ($xssRecord['id'] ?? 0) . '/tanggapan', [
    '_csrf' => App\Core\Csrf::token(),
    'response_note' => $xssNote,
]);
phase2bAs(null);
$_SERVER['REMOTE_ADDR'] = '198.51.100.121';
$xssTracking = phase2bRequest('POST', '/lacak-aduan', [
    '_csrf' => App\Core\Csrf::token(),
    'track_token' => (string) ($xssRecord['track_token'] ?? ''),
]);
$xssAuditMessages = Db::all(
    'SELECT message FROM audit_logs WHERE entity_type = ? AND entity_id = ?',
    ['complaints', (int) ($xssRecord['id'] ?? 0)],
    'test'
);
$xssHistoryResponseCount = (int) Db::one(
    'SELECT COUNT(*) AS n FROM complaint_history WHERE complaint_id = ? AND event_type = ?',
    [(int) ($xssRecord['id'] ?? 0), 'TANGGAPAN'],
    'test'
)['n'];
check(
    'Detail admin meng-escape isi/kontak, daftar dan status tidak membocorkan isi, tanggapan UTF-8 maksimal aman',
    $xssSubmit['status'] === 303
        && str_contains($xssAdminDetail['body'], e($xssContent))
        && str_contains($xssAdminDetail['body'], e($xssContact))
        && !str_contains($xssAdminDetail['body'], $xssContent)
        && !str_contains($xssAdminList['body'], $xssContent)
        && !str_contains($xssAdminList['body'], $xssContact)
        && $maxNoteResponse['status'] === 303
        && $maxNoteLength === 1000
        && $xssNoteResponse['status'] === 303
        && $xssHistoryResponseCount === 2
        && $xssTracking['status'] === 200
        && str_contains($xssTracking['body'], e($xssNote))
        && !str_contains($xssTracking['body'], $xssNote)
        && !str_contains($xssTracking['body'], $xssContent)
        && !str_contains($xssTracking['body'], $xssContact)
        && count($xssAuditMessages) === 3
        && !str_contains(implode(' ', array_column($xssAuditMessages, 'message')), $xssContent)
        && !str_contains(implode(' ', array_column($xssAuditMessages, 'message')), $xssNote)
);

$_GET = ['jenis' => 'PERTANYAAN', 'status' => 'DITERIMA'];
phase2bAs($phase5Admin);
$filteredQueue = phase2bRequest('GET', '/admin/aduan');
$_GET = [];
check(
    'Admin dapat memfilter daftar KAHAYAN berdasarkan status dan jenis',
    $filteredQueue['status'] === 200
        && str_contains($filteredQueue['body'], 'Semua jenis')
        && str_contains($filteredQueue['body'], 'DITERIMA')
        && str_contains($filteredQueue['body'], 'PERTANYAAN')
        && str_contains($filteredQueue['body'], (string) $xssRecord['ticket_no'])
        && !str_contains($filteredQueue['body'], (string) $studentTicket['ticket_no'])
);

$csrfAdminBefore = (int) Db::one('SELECT COUNT(*) AS n FROM complaint_history', [], 'test')['n'];
phase2bAs($phase5Admin);
$adminCsrfReject = phase2bRequest('POST', '/admin/aduan/' . (int) $xssRecord['id'] . '/tanggapan', [
    '_csrf' => 'invalid-admin-csrf',
    'response_note' => 'Tidak boleh disimpan',
]);
$adminStatusCsrfReject = phase2bRequest('POST', '/admin/aduan/' . (int) $xssRecord['id'] . '/status', [
    '_csrf' => 'invalid-admin-status-csrf',
    'status' => 'DIKLASIFIKASI',
]);
$oversizedResponse = phase2bRequest('POST', '/admin/aduan/' . (int) $xssRecord['id'] . '/tanggapan', [
    '_csrf' => App\Core\Csrf::token(),
    'response_note' => str_repeat('界', 1001),
]);
$csrfAdminAfter = (int) Db::one('SELECT COUNT(*) AS n FROM complaint_history', [], 'test')['n'];
check(
    'Rute admin status/tanggapan dilindungi CSRF dan tanggapan di atas 1.000 karakter ditolak',
    $adminCsrfReject['status'] === 403
        && $adminStatusCsrfReject['status'] === 403
        && $oversizedResponse['status'] === 422
        && $csrfAdminAfter === $csrfAdminBefore
);

$migrationName008 = '008_complaints.sql';
$migrationRunBefore = Db::one(
    'SELECT status, checksum, statement_count, last_completed_statement FROM schema_migration_runs WHERE migration_name = ?',
    [$migrationName008],
    'test'
);
$migrationCommand = escapeshellarg(PHP_BINARY) . ' '
    . escapeshellarg(__DIR__ . '/../bin/jalankan-migrasi.php') . ' test';
exec($migrationCommand, $migrationOutput008, $migrationExit008);
$migrationRunAfter = Db::one(
    'SELECT status, checksum, statement_count, last_completed_statement FROM schema_migration_runs WHERE migration_name = ?',
    [$migrationName008],
    'test'
);
$migrationLedgerRows008 = (int) Db::one(
    'SELECT COUNT(*) AS n FROM schema_migrations WHERE migration_name = ?',
    [$migrationName008],
    'test'
)['n'];
check(
    'Migrasi 008 idempoten dan signature ledger menyatakan APPLIED',
    $migrationExit008 === 0
        && ($migrationRunBefore['status'] ?? null) === 'APPLIED'
        && $migrationRunBefore === $migrationRunAfter
        && $migrationLedgerRows008 === 1,
    implode(PHP_EOL, $migrationOutput008)
);

$autoloadClasses = [];
foreach (['Core', 'Controllers', 'Policies'] as $classDirectory) {
    foreach (glob(__DIR__ . '/../app/' . $classDirectory . '/*.php') ?: [] as $classFile) {
        if (basename($classFile) === 'helpers.php') {
            continue;
        }
        $autoloadClasses[] = 'App\\' . $classDirectory . '\\' . basename($classFile, '.php');
    }
}
$autoloadClasses[] = 'App\\Controllers\\SurveyController';
$autoloadClasses = array_values(array_unique($autoloadClasses));
$autoloadProbe = '<?php require '
    . var_export(__DIR__ . '/../app/autoload.php', true)
    . '; $classes = ' . var_export($autoloadClasses, true)
    . '; foreach ($classes as $class) { if (!class_exists($class)) { fwrite(STDERR, $class); exit(1); } }';
$autoloadProcess = proc_open(
    [PHP_BINARY, '-r', substr($autoloadProbe, 6)],
    [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
    $autoloadPipes,
    __DIR__
);
$autoloadOutput = '';
$autoloadExitCode = 1;
if (is_resource($autoloadProcess)) {
    fclose($autoloadPipes[0]);
    $autoloadOutput = (string) stream_get_contents($autoloadPipes[1])
        . (string) stream_get_contents($autoloadPipes[2]);
    fclose($autoloadPipes[1]);
    fclose($autoloadPipes[2]);
    $autoloadExitCode = proc_close($autoloadProcess);
}
check(
    'Semua berkas kelas Core, Controllers, dan Policies termuat lewat autoloader dalam proses anak',
    $autoloadExitCode === 0,
    $autoloadOutput
);

$indexRouteResponses = phase2bHttpRequests([
    ['method' => 'GET', 'path' => '/kahayan'],
    ['method' => 'GET', 'path' => '/lacak-aduan'],
    ['method' => 'GET', 'path' => '/layanan'],
    ['method' => 'GET', 'path' => '/lacak'],
]);
$indexRoutesPassed = count($indexRouteResponses) === 4;
foreach ($indexRouteResponses as $routeResponse) {
    $indexRoutesPassed = $indexRoutesPassed
        && $routeResponse['status'] === 200
        && !str_contains($routeResponse['body'], 'Class App\\')
        && !str_contains($routeResponse['body'], 'Fatal error');
}
check(
    'GET rute KAHAYAN, lacak aduan, katalog, dan lacak berjalan lewat public/index.php asli',
    $indexRoutesPassed,
    implode(' | ', array_map(
        static fn (array $response): string => (string) $response['status'] . ' ' . substr($response['body'], 0, 100),
        $indexRouteResponses
    ))
);

$formResponse = phase2bHttpRequests([['method' => 'GET', 'path' => '/kahayan']])[0] ?? [];
$csrfToken = '';
if (preg_match('/name="_csrf" value="([^"]+)"/', (string) ($formResponse['body'] ?? ''), $csrfMatch) === 1) {
    $csrfToken = $csrfMatch[1];
}
$sessionCookie = '';
foreach (($formResponse['headers'] ?? []) as $headerLine) {
    if (preg_match('/^Set-Cookie:\s*([^=]+)=([^;]+)/i', $headerLine, $cookieMatch) === 1) {
        $sessionCookie = $cookieMatch[1] . '=' . $cookieMatch[2];
        break;
    }
}
$realIndexBody = 'POST lewat index asli ' . bin2hex(random_bytes(12));
$realIndexPost = $csrfToken !== '' && $sessionCookie !== ''
    ? phase2bHttpRequests([[
        'method' => 'POST',
        'path' => '/kahayan',
        'cookie' => $sessionCookie,
        'post' => ['_csrf' => $csrfToken, 'jenis' => 'SARAN', 'isi' => $realIndexBody],
    ]])[0] ?? []
    : [];
$realIndexTicket = Db::one(
    'SELECT id, ticket_no FROM complaints WHERE isi = ?',
    [$realIndexBody],
    'test'
);
check(
    'POST /kahayan sah lewat public/index.php asli membuat tiket tanpa galat fatal',
    ($realIndexPost['status'] ?? 0) === 303
        && $realIndexTicket !== null
        && !str_contains((string) ($realIndexPost['body'] ?? ''), 'Class App\\')
        && !str_contains((string) ($realIndexPost['body'] ?? ''), 'Fatal error'),
    (string) ($realIndexPost['status'] ?? 0) . ' ' . substr((string) ($realIndexPost['body'] ?? ''), 0, 200)
);
