<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\ApplicationTrackingLimiter;
use App\Core\Auth;
use App\Core\ComplaintWorkflow;
use App\Core\Csrf;
use App\Core\Db;
use App\Core\IdGen;
use App\Core\IpQuotaHash;
use App\Core\QuotaCleaner;
use App\Core\TrackingCode;
use App\Core\View;
use Throwable;

final class ComplaintsController
{
    private const PUBLIC_ERROR = 'Kode lacak tidak valid atau tidak ditemukan.';

    public static function form(): void
    {
        self::renderForm();
    }

    public static function submit(): void
    {
        $user = Auth::user();
        $type = self::postString('jenis');
        $body = self::postString('isi');
        $contact = self::postString('kontak');
        $honeypot = self::postString('website');

        if ($honeypot !== '') {
            self::renderForm('Pengiriman tidak dapat diproses.', 422, $type, $body, $contact);
            return;
        }
        if (
            !in_array($type, ComplaintWorkflow::TYPES, true)
            || $body === ''
            || !mb_check_encoding($body, 'UTF-8')
            || mb_strlen($body, 'UTF-8') > 4000
            || ($contact !== '' && (!mb_check_encoding($contact, 'UTF-8') || mb_strlen($contact, 'UTF-8') > 120))
        ) {
            self::renderForm('Jenis, isi, atau kontak tidak valid.', 422, $type, $body, $contact);
            return;
        }

        $database = Auth::databaseName();
        $ipAddress = Auth::clientIp();
        if ($ipAddress === '') {
            self::renderForm('Pengajuan tidak dapat diproses dari koneksi ini. Silakan coba lagi.', 503);
            return;
        }
        $ipHash = IpQuotaHash::forAddress($ipAddress);
        QuotaCleaner::maybeClean($database);
        $pdo = Db::pdo($database);
        $result = ApplicationTrackingLimiter::withIpLock(
            $ipAddress,
            $database,
            static function () use ($pdo, $database, $ipHash, $user, $type, $body, $contact): ?array {
                $pdo->beginTransaction();
                try {
                    Db::run(
                        'INSERT IGNORE INTO complaint_ip_quotas (ip_hash, window_started_at, submission_count) VALUES (?, NOW(), 0)',
                        [$ipHash],
                        $database
                    );
                    $quota = Db::one(
                        'SELECT submission_count, window_started_at <= DATE_SUB(NOW(), INTERVAL 1 HOUR) AS expired FROM complaint_ip_quotas WHERE ip_hash = ? FOR UPDATE',
                        [$ipHash],
                        $database
                    );
                    if ($quota === null) {
                        throw new \RuntimeException('Catatan kuota pengaduan tidak dapat dibaca.');
                    }
                    $count = (int) $quota['submission_count'];
                    if ((int) $quota['expired'] === 1) {
                        $count = 0;
                        Db::run(
                            'UPDATE complaint_ip_quotas SET window_started_at = NOW(), submission_count = 0 WHERE ip_hash = ?',
                            [$ipHash],
                            $database
                        );
                    }
                    if ($count >= 5) {
                        $pdo->rollBack();
                        return null;
                    }
                    Db::run(
                        'UPDATE complaint_ip_quotas SET submission_count = submission_count + 1 WHERE ip_hash = ?',
                        [$ipHash],
                        $database
                    );

                    $ticketNo = IdGen::next('CMP', $database);
                    $trackToken = TrackingCode::generate();
                    Db::run(
                        'INSERT INTO complaints (ticket_no, track_token, jenis, isi, kontak, user_id, status) VALUES (?, ?, ?, ?, ?, ?, ?)',
                        [
                            $ticketNo,
                            $trackToken,
                            $type,
                            $body,
                            $contact !== '' ? $contact : null,
                            $user === null ? null : (int) $user['id'],
                            ComplaintWorkflow::INITIAL_STATUS,
                        ],
                        $database
                    );
                    $complaintId = (int) Db::one(
                        'SELECT id FROM complaints WHERE ticket_no = ?',
                        [$ticketNo],
                        $database
                    )['id'];
                    Db::run(
                        'INSERT INTO complaint_history (complaint_id, event_type, old_status, new_status, changed_by) VALUES (?, ?, NULL, ?, ?)',
                        [$complaintId, 'STATUS', ComplaintWorkflow::INITIAL_STATUS, $user === null ? null : (int) $user['id']],
                        $database
                    );
                    Auth::audit(
                        $user === null ? null : (int) $user['id'],
                        (string) ($user['role_code'] ?? 'GUEST'),
                        'complaint_submitted',
                        'complaints',
                        $complaintId,
                        'Aduan baru diterima',
                        $database
                    );
                    $pdo->commit();
                    return ['ticket_no' => $ticketNo, 'track_token' => $trackToken];
                } catch (Throwable $exception) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    throw $exception;
                }
            }
        );

        if ($result === null) {
            self::renderForm('Batas pengiriman dari jaringan ini tercapai. Silakan coba lagi setelah satu jam.', 429);
            return;
        }
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $_SESSION['flash_complaint_ticket'] = $result['ticket_no'];
        $_SESSION['flash_complaint_token'] = $result['track_token'];
        if (!headers_sent()) {
            header('Location: /kahayan/diterima', true, 303);
        }
        http_response_code(303);
    }

    public static function submitted(): void
    {
        self::setPrivateHeaders();
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $ticketNo = (string) ($_SESSION['flash_complaint_ticket'] ?? '');
        $trackToken = (string) ($_SESSION['flash_complaint_token'] ?? '');
        unset($_SESSION['flash_complaint_ticket'], $_SESSION['flash_complaint_token']);
        View::render('pages/complaint_submitted', [
            'title' => 'KAHAYAN AMANAH diterima',
            'ticketNo' => $ticketNo,
            'trackToken' => $trackToken,
        ]);
    }

    public static function trackingForm(): void
    {
        self::setPrivateHeaders();
        View::render('pages/complaint_tracking_form', [
            'title' => 'Lacak KAHAYAN AMANAH',
            'result' => null,
            'error' => '',
        ]);
    }

    public static function track(): void
    {
        self::setPrivateHeaders();
        $database = Auth::databaseName();
        $ipAddress = Auth::clientIp();
        if ($ipAddress !== '' && ApplicationTrackingLimiter::isLockedOut($ipAddress, $database)) {
            self::trackingFailure(429);
            return;
        }

        $tokenInput = $_POST['track_token'] ?? '';
        $token = is_string($tokenInput) ? strtoupper(trim($tokenInput)) : '';
        if (!TrackingCode::isValid($token)) {
            self::recordTrackingFailure($ipAddress, $database);
            self::trackingFailure(self::trackingFailureStatus($ipAddress, $database));
            return;
        }

        $complaint = Db::one(
            'SELECT ticket_no, status, response_note FROM complaints WHERE track_token = ?',
            [$token],
            $database
        );
        if ($complaint === null) {
            self::recordTrackingFailure($ipAddress, $database);
            self::trackingFailure(self::trackingFailureStatus($ipAddress, $database));
            return;
        }

        View::render('pages/complaint_tracking_result', [
            'title' => 'Status KAHAYAN AMANAH',
            'complaint' => $complaint,
            'statusLabels' => ComplaintWorkflow::STATUS_LABELS,
            'statusSteps' => ComplaintWorkflow::STATUS_STEPS,
        ]);
    }

    public static function adminIndex(): void
    {
        $status = strtoupper(trim(is_string($_GET['status'] ?? null) ? $_GET['status'] : ''));
        $type = strtoupper(trim(is_string($_GET['jenis'] ?? null) ? $_GET['jenis'] : ''));
        $conditions = [];
        $parameters = [];
        if (in_array($status, ComplaintWorkflow::STATUSES, true)) {
            $conditions[] = 'status = ?';
            $parameters[] = $status;
        } else {
            $status = '';
        }
        if (in_array($type, ComplaintWorkflow::TYPES, true)) {
            $conditions[] = 'jenis = ?';
            $parameters[] = $type;
        } else {
            $type = '';
        }
        $where = $conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions);
        $complaints = Db::all(
            'SELECT id, ticket_no, jenis, status, created_at FROM complaints' . $where . ' ORDER BY created_at DESC, id DESC',
            $parameters
        );
        View::render('pages/admin_complaints', [
            'title' => 'Antrean KAHAYAN AMANAH',
            'complaints' => $complaints,
            'selectedStatus' => $status,
            'selectedType' => $type,
            'statuses' => ComplaintWorkflow::STATUS_LABELS,
            'types' => ComplaintWorkflow::TYPES,
        ]);
    }

    public static function portalDetail(array $params = []): void
    {
        self::setPrivateHeaders();
        $id = self::positiveId($params['id'] ?? null);
        $user = Auth::user();
        $complaint = $id === null || $user === null ? null : Db::one(
            'SELECT ticket_no, status, response_note FROM complaints WHERE id = ? AND user_id = ?',
            [$id, (int) $user['id']]
        );
        if ($complaint === null) {
            self::notFound();
            return;
        }
        View::render('pages/complaint_tracking_result', [
            'title' => 'Status KAHAYAN AMANAH',
            'complaint' => $complaint,
            'statusLabels' => ComplaintWorkflow::STATUS_LABELS,
            'statusSteps' => ComplaintWorkflow::STATUS_STEPS,
        ]);
    }

    public static function adminDetail(array $params = []): void
    {
        $id = self::positiveId($params['id'] ?? null);
        $complaint = $id === null ? null : Db::one(
            'SELECT c.id, c.ticket_no, c.jenis, c.isi, c.kontak, c.user_id, u.full_name AS reporter_name, u.identifier AS reporter_identifier, c.status, c.response_note, c.reject_reason, c.created_at, c.updated_at FROM complaints c LEFT JOIN users u ON u.id = c.user_id WHERE c.id = ?',
            [$id]
        );
        if ($complaint === null) {
            self::notFound();
            return;
        }
        $history = Db::all(
            'SELECT h.event_type, h.old_status, h.new_status, h.created_at, u.full_name AS actor_name FROM complaint_history h LEFT JOIN users u ON u.id = h.changed_by WHERE h.complaint_id = ? ORDER BY h.id',
            [$id]
        );
        View::render('pages/admin_complaint_detail', [
            'title' => 'Detail KAHAYAN AMANAH',
            'complaint' => $complaint,
            'history' => $history,
            'statuses' => ComplaintWorkflow::STATUS_LABELS,
            'nextStatuses' => ComplaintWorkflow::TRANSITIONS[$complaint['status']] ?? [],
            'message' => '',
        ]);
    }

    public static function updateStatus(array $params = []): void
    {
        $id = self::positiveId($params['id'] ?? null);
        $newStatusInput = $_POST['status'] ?? '';
        $newStatus = is_string($newStatusInput) ? strtoupper(trim($newStatusInput)) : '';
        $reason = self::postString('reject_reason');
        if ($id === null || !in_array($newStatus, ComplaintWorkflow::STATUSES, true)) {
            self::notFound();
            return;
        }
        if ($newStatus === 'DITOLAK' && (!mb_check_encoding($reason, 'UTF-8') || trim($reason) === '' || mb_strlen($reason, 'UTF-8') > 1000)) {
            self::renderAdminDetail($id, 'Alasan penolakan wajib diisi dan maksimal 1.000 karakter.', 422);
            return;
        }

        $database = Auth::databaseName();
        $pdo = Db::pdo($database);
        $pdo->beginTransaction();
        try {
            $complaint = Db::one('SELECT id, status FROM complaints WHERE id = ? FOR UPDATE', [$id], $database);
            if ($complaint === null) {
                $pdo->rollBack();
                self::notFound();
                return;
            }
            $oldStatus = (string) $complaint['status'];
            if (!ComplaintWorkflow::canTransition($oldStatus, $newStatus)) {
                $pdo->rollBack();
                self::renderAdminDetail($id, 'Perpindahan status tidak diizinkan.', 422);
                return;
            }
            Db::run(
                'UPDATE complaints SET status = ?, reject_reason = ? WHERE id = ?',
                [$newStatus, $newStatus === 'DITOLAK' ? trim($reason) : null, $id],
                $database
            );
            $actorId = (int) (Auth::user()['id'] ?? 0);
            Db::run(
                'INSERT INTO complaint_history (complaint_id, event_type, old_status, new_status, changed_by) VALUES (?, ?, ?, ?, ?)',
                [$id, 'STATUS', $oldStatus, $newStatus, $actorId],
                $database
            );
            Auth::audit($actorId, 'ADMIN', 'complaint_status_changed', 'complaints', $id, 'Status diubah menjadi ' . $newStatus, $database);
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
        self::redirectToDetail($id);
    }

    public static function updateResponse(array $params = []): void
    {
        $id = self::positiveId($params['id'] ?? null);
        $responseNoteInput = $_POST['response_note'] ?? '';
        $responseNote = is_string($responseNoteInput) ? trim($responseNoteInput) : '';
        if ($id === null || $responseNote === '' || !mb_check_encoding($responseNote, 'UTF-8') || mb_strlen($responseNote, 'UTF-8') > 1000) {
            if ($id === null) {
                self::notFound();
                return;
            }
            self::renderAdminDetail($id, 'Tanggapan wajib diisi dan maksimal 1.000 karakter.', 422);
            return;
        }

        $database = Auth::databaseName();
        $pdo = Db::pdo($database);
        $pdo->beginTransaction();
        try {
            if (Db::one('SELECT id FROM complaints WHERE id = ? FOR UPDATE', [$id], $database) === null) {
                $pdo->rollBack();
                self::notFound();
                return;
            }
            Db::run('UPDATE complaints SET response_note = ? WHERE id = ?', [$responseNote, $id], $database);
            $actorId = (int) (Auth::user()['id'] ?? 0);
            Db::run(
                'INSERT INTO complaint_history (complaint_id, event_type, changed_by) VALUES (?, ?, ?)',
                [$id, 'TANGGAPAN', $actorId],
                $database
            );
            Auth::audit($actorId, 'ADMIN', 'complaint_response_updated', 'complaints', $id, 'Tanggapan publik diperbarui', $database);
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
        self::redirectToDetail($id);
    }

    private static function renderForm(string $error = '', int $status = 200, string $type = '', string $body = '', string $contact = ''): void
    {
        http_response_code($status);
        View::render('pages/complaint_form', [
            'title' => 'KAHAYAN AMANAH',
            'error' => $error,
            'selectedType' => $type,
            'body' => $body,
            'contact' => $contact,
        ]);
    }

    private static function renderAdminDetail(int $id, string $message, int $status): void
    {
        $complaint = Db::one(
            'SELECT c.id, c.ticket_no, c.jenis, c.isi, c.kontak, c.user_id, u.full_name AS reporter_name, u.identifier AS reporter_identifier, c.status, c.response_note, c.reject_reason, c.created_at, c.updated_at FROM complaints c LEFT JOIN users u ON u.id = c.user_id WHERE c.id = ?',
            [$id]
        );
        if ($complaint === null) {
            self::notFound();
            return;
        }
        $history = Db::all(
            'SELECT h.event_type, h.old_status, h.new_status, h.created_at, u.full_name AS actor_name FROM complaint_history h LEFT JOIN users u ON u.id = h.changed_by WHERE h.complaint_id = ? ORDER BY h.id',
            [$id]
        );
        http_response_code($status);
        View::render('pages/admin_complaint_detail', [
            'title' => 'Detail KAHAYAN AMANAH',
            'complaint' => $complaint,
            'history' => $history,
            'statuses' => ComplaintWorkflow::STATUS_LABELS,
            'nextStatuses' => ComplaintWorkflow::TRANSITIONS[$complaint['status']] ?? [],
            'message' => $message,
        ]);
    }

    private static function recordTrackingFailure(string $ipAddress, string $database): void
    {
        if ($ipAddress !== '') {
            ApplicationTrackingLimiter::recordFailure($ipAddress, $database);
        }
    }

    private static function trackingFailureStatus(string $ipAddress, string $database): int
    {
        return $ipAddress !== '' && ApplicationTrackingLimiter::isLockedOut($ipAddress, $database) ? 429 : 404;
    }

    private static function trackingFailure(int $status): void
    {
        http_response_code($status);
        View::render('pages/complaint_tracking_form', [
            'title' => 'Lacak KAHAYAN AMANAH',
            'result' => null,
            'error' => self::PUBLIC_ERROR,
        ]);
    }

    private static function setPrivateHeaders(): void
    {
        if (!headers_sent()) {
            header('Cache-Control: no-store');
            header('Referrer-Policy: no-referrer');
        }
    }

    private static function postString(string $key): string
    {
        $value = $_POST[$key] ?? '';
        return is_string($value) ? trim($value) : '';
    }

    private static function positiveId(mixed $id): ?int
    {
        if (!is_string($id) && !is_int($id)) {
            return null;
        }
        $value = (string) $id;
        return preg_match('/^[1-9][0-9]*$/D', $value) === 1 ? (int) $value : null;
    }

    private static function redirectToDetail(int $id): void
    {
        if (!headers_sent()) {
            header('Location: /admin/aduan/' . $id, true, 303);
        }
        http_response_code(303);
    }

    private static function notFound(): void
    {
        http_response_code(404);
        View::render('pages/error', ['title' => '404', 'message' => 'Aduan tidak ditemukan.']);
    }
}
