<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\ApplicationWorkflow;
use App\Core\ApplicationTrackingLimiter;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Db;
use App\Core\TrackingCode;
use App\Core\View;
use App\Policies\Permissions;
use PDOException;
use RuntimeException;
use Throwable;

final class ApplicationController
{
    private const MAX_REQUEST_LENGTH = 4000;
    private const MAX_APPLICATIONS_PER_HOUR = 5;
    private const MAX_DOCUMENTS_PER_APPLICATION = 3;
    private const MAX_DOCUMENT_SIZE_BYTES = 5 * 1024 * 1024;
    private const TRACKING_NOT_FOUND_MESSAGE = 'Kode lacak tidak ditemukan atau belum dapat ditampilkan.';
    private const TRACKING_RATE_LIMIT_MESSAGE = 'Pemeriksaan kode lacak dibatasi sementara. Silakan coba lagi nanti.';
    private const DOCUMENT_SERVICES = ['TU-01', 'TU-02', 'KUR-02', 'SAR-01', 'HUM-01'];

    public static function requestExceedsPostMaxSize(): bool
    {
        if ($_POST !== [] || $_FILES !== []) {
            return false;
        }
        $contentLength = $_SERVER['CONTENT_LENGTH'] ?? null;
        $postMaxSize = self::iniSizeBytes((string) ini_get('post_max_size'));
        return is_string($contentLength)
            && ctype_digit($contentLength)
            && $postMaxSize > 0
            && (int) $contentLength > $postMaxSize;
    }

    public static function form(array $params = []): void
    {
        $service = self::serviceByCode((string) ($params['code'] ?? ''));
        if ($service === null || (int) $service['is_active'] !== 1) {
            self::notFound('Layanan tidak ditemukan.');
            return;
        }
        if ($service['mode'] !== 'FORMULIR') {
            self::invalidApplication($service);
            return;
        }

        View::render('pages/application_form', [
            'title' => 'Ajukan layanan',
            'service' => $service,
            'error' => '',
            'requestText' => '',
        ]);
    }

    public static function submit(array $params = []): void
    {
        $serviceCode = strtoupper(trim((string) ($params['code'] ?? '')));
        $service = self::serviceByCode($serviceCode);
        if ($service === null || (int) $service['is_active'] !== 1) {
            self::notFound('Layanan tidak ditemukan.');
            return;
        }
        if ($service['mode'] !== 'FORMULIR') {
            self::invalidApplication($service);
            return;
        }

        if (self::requestExceedsPostMaxSize()) {
            self::renderFormError($service, 'Berkas terlalu besar.', 413, '');
            return;
        }

        $user = Auth::user();
        $requestText = $_POST['request_text'] ?? null;
        if (
            $user === null
            || !Permissions::can((string) ($user['role_code'] ?? ''), 'applications.submit')
            || !is_string($requestText)
            || !mb_check_encoding($requestText, 'UTF-8')
            || trim($requestText) === ''
            || mb_strlen($requestText, 'UTF-8') > self::MAX_REQUEST_LENGTH
        ) {
            self::renderFormError($service, 'Isi permintaan wajib diisi dan maksimal 4.000 karakter UTF-8.', 422, is_string($requestText) ? $requestText : '');
            return;
        }

        try {
            $documents = self::validatedDocuments($serviceCode);
        } catch (ApplicationDocumentValidationException $exception) {
            self::renderFormError($service, $exception->getMessage(), 422, trim($requestText));
            return;
        }

        $database = Auth::databaseName();
        $pdo = Db::pdo($database);
        $applicationId = 0;
        $trackingCode = '';
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $trackingCode = TrackingCode::generate();
            $storedFiles = [];
            $pdo->beginTransaction();
            try {
                Db::one(
                    'SELECT id FROM users WHERE id = ? FOR UPDATE',
                    [(int) $user['id']],
                    $database
                );
                $recentApplications = (int) (Db::one(
                    'SELECT COUNT(*) AS total FROM applications WHERE applicant_user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)',
                    [(int) $user['id']],
                    $database
                )['total'] ?? 0);
                if ($recentApplications >= self::MAX_APPLICATIONS_PER_HOUR) {
                    $pdo->rollBack();
                    self::renderFormError($service, 'Batas pengajuan per jam tercapai. Silakan coba lagi nanti.', 429, trim($requestText));
                    return;
                }

                Db::run(
                    'INSERT INTO applications (service_id, applicant_user_id, tracking_code, request_text, status) VALUES (?, ?, ?, ?, ?)',
                    [(int) $service['id'], (int) $user['id'], $trackingCode, trim($requestText), ApplicationWorkflow::INITIAL_STATUS],
                    $database
                );
                $applicationId = (int) $pdo->lastInsertId();
                Db::run(
                    'INSERT INTO application_history (application_id, old_status, new_status, changed_by) VALUES (?, NULL, ?, ?)',
                    [$applicationId, ApplicationWorkflow::INITIAL_STATUS, (int) $user['id']],
                    $database
                );
                Auth::audit(
                    (int) $user['id'],
                    strtoupper((string) $user['role_code']),
                    'application_submitted',
                    'applications',
                    $applicationId,
                    (string) json_encode(
                        ['old' => null, 'new' => ['status' => ApplicationWorkflow::INITIAL_STATUS]],
                        JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
                    ),
                    $database
                );
                foreach ($documents as $document) {
                    $storedName = bin2hex(random_bytes(32)) . $document['extension'];
                    $storedPath = self::privateStorageDirectory() . DIRECTORY_SEPARATOR . $storedName;
                    if (!move_uploaded_file($document['tmp_name'], $storedPath)) {
                        throw new ApplicationDocumentStorageException('Berkas tidak dapat disimpan.');
                    }
                    $storedFiles[] = $storedPath;
                    Db::run(
                        'INSERT INTO application_documents (application_id, nama_asli, nama_acak, mime_terdeteksi, ukuran, sha256) VALUES (?, ?, ?, ?, ?, ?)',
                        [
                            $applicationId,
                            $document['original_name'],
                            $storedName,
                            $document['mime'],
                            $document['size'],
                            $document['sha256'],
                        ],
                        $database
                    );
                    $documentId = (int) $pdo->lastInsertId();
                    Auth::audit(
                        (int) $user['id'],
                        strtoupper((string) $user['role_code']),
                        'application_document_uploaded',
                        'application_documents',
                        $documentId,
                        json_encode(
                            ['application_id' => $applicationId, 'mime' => $document['mime'], 'size' => $document['size']],
                            JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
                        ),
                        $database
                    );
                }
                $pdo->commit();
                break;
            } catch (PDOException $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                self::removeStoredFiles($storedFiles);
                if (($exception->errorInfo[1] ?? null) === 1062 && $attempt < 4) {
                    continue;
                }
                if ($documents !== []) {
                    error_log('Pengajuan dengan lampiran gagal disimpan: ' . $exception->getMessage());
                    self::renderFormError($service, 'Pengajuan gagal disimpan; lampiran belum disimpan. Silakan coba lagi.', 500, trim($requestText));
                    return;
                }
                throw $exception;
            } catch (ApplicationDocumentStorageException $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                self::removeStoredFiles($storedFiles);
                self::renderFormError($service, 'Lampiran gagal disimpan; pengajuan belum dibuat.', 500, trim($requestText));
                return;
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                self::removeStoredFiles($storedFiles);
                throw $exception;
            }
        }

        if ($applicationId === 0) {
            throw new RuntimeException('Gagal membuat kode lacak unik setelah beberapa percobaan.');
        }

        $_SESSION['flash_tracking_code'] = $trackingCode;
        self::setPrivatePageHeaders();
        if (!headers_sent()) {
            header('Location: /pengajuan/diterima', true, 303);
        }
        http_response_code(303);
    }

    public static function submissionComplete(): void
    {
        self::setPrivatePageHeaders();
        $trackingCode = (string) ($_SESSION['flash_tracking_code'] ?? '');
        unset($_SESSION['flash_tracking_code']);
        View::render('pages/application_submitted', [
            'title' => 'Pengajuan diterima',
            'trackingCode' => $trackingCode,
        ]);
    }

    public static function trackingForm(): void
    {
        self::setPrivatePageHeaders();
        View::render('pages/application_tracking_form', [
            'title' => 'Cek status pengajuan',
            'error' => '',
            'trackingCode' => '',
        ]);
    }

    public static function track(): void
    {
        self::setPrivatePageHeaders();
        $database = Auth::databaseName();
        $ip = Auth::clientIp();
        if (ApplicationTrackingLimiter::isLockedOut($ip, $database)) {
            self::trackingRateLimited();
            return;
        }

        $trackingCodeInput = $_POST['tracking_code'] ?? '';
        $trackingCode = is_string($trackingCodeInput) ? strtoupper(trim($trackingCodeInput)) : '';
        if (!TrackingCode::isValid($trackingCode)) {
            $locked = ApplicationTrackingLimiter::recordFailure($ip, $database);
            if ($locked) {
                self::trackingRateLimited();
                return;
            }
            self::trackingNotFound();
            return;
        }

        $application = Db::one(
            'SELECT status FROM applications WHERE tracking_code = ?',
            [$trackingCode],
            $database
        );
        if ($application === null) {
            $locked = ApplicationTrackingLimiter::recordFailure($ip, $database);
            if ($locked) {
                self::trackingRateLimited();
                return;
            }
            self::trackingNotFound();
            return;
        }

        View::render('pages/application_tracking', [
            'title' => 'Cek status pengajuan',
            'status' => ApplicationWorkflow::STATUS_LABELS[$application['status']] ?? $application['status'],
            'stage' => ApplicationWorkflow::STATUS_STEPS[$application['status']] ?? 'Tahap layanan',
        ]);
    }

    public static function adminIndex(): void
    {
        $statusInput = $_GET['status'] ?? '';
        $status = is_string($statusInput) ? strtoupper(trim($statusInput)) : '';
        $filterStatus = isset(ApplicationWorkflow::STATUS_LABELS[$status]) ? $status : null;
        $applications = Db::all(
            'SELECT a.id, a.tracking_code, a.status, a.created_at, s.code AS service_code, s.name AS service_name, u.full_name AS applicant_name FROM applications a INNER JOIN services s ON s.id = a.service_id INNER JOIN users u ON u.id = a.applicant_user_id'
                . ($filterStatus === null ? '' : ' WHERE a.status = ?')
                . ' ORDER BY a.created_at DESC, a.id DESC',
            $filterStatus === null ? [] : [$filterStatus]
        );
        View::render('pages/admin_applications', [
            'title' => 'Antrean pengajuan',
            'applications' => $applications,
            'statusLabels' => ApplicationWorkflow::STATUS_LABELS,
        ]);
    }

    public static function adminDetail(array $params = []): void
    {
        $applicationId = self::applicationId($params);
        if ($applicationId === null) {
            self::notFound('Pengajuan tidak ditemukan.');
            return;
        }
        $application = self::findApplication($applicationId);
        if ($application === null) {
            self::notFound('Pengajuan tidak ditemukan.');
            return;
        }
        $history = Db::all(
            'SELECT h.old_status, h.new_status, h.created_at, u.full_name AS changed_by_name FROM application_history h LEFT JOIN users u ON u.id = h.changed_by WHERE h.application_id = ? ORDER BY h.id',
            [$applicationId]
        );
        $documents = Db::all(
            'SELECT id, mime_terdeteksi, ukuran, created_at FROM application_documents WHERE application_id = ? ORDER BY id',
            [$applicationId]
        );
        View::render('pages/admin_application_detail', [
            'title' => 'Detail pengajuan',
            'application' => $application,
            'history' => $history,
            'documents' => $documents,
            'statusLabels' => ApplicationWorkflow::STATUS_LABELS,
            'nextStatuses' => ApplicationWorkflow::TRANSITIONS[$application['status']] ?? [],
            'message' => '',
        ]);
    }

    public static function updateStatus(array $params = []): void
    {
        $applicationId = self::applicationId($params);
        $statusInput = $_POST['status'] ?? '';
        $newStatus = is_string($statusInput) ? strtoupper(trim($statusInput)) : '';
        if ($applicationId === null || !isset(ApplicationWorkflow::STATUS_LABELS[$newStatus])) {
            self::notFound('Pengajuan atau status tidak valid.');
            return;
        }

        $database = Auth::databaseName();
        $pdo = Db::pdo($database);
        $pdo->beginTransaction();
        try {
            $application = Db::one(
                'SELECT id, status FROM applications WHERE id = ? FOR UPDATE',
                [$applicationId],
                $database
            );
            if ($application === null) {
                $pdo->rollBack();
                self::notFound('Pengajuan tidak ditemukan.');
                return;
            }
            $oldStatus = (string) $application['status'];
            if (!ApplicationWorkflow::canTransition($oldStatus, $newStatus)) {
                $pdo->rollBack();
                self::renderAdminDetailError($applicationId, 'Transisi status tidak diizinkan.', 422);
                return;
            }

            $actor = Auth::user();
            Db::run(
                'UPDATE applications SET status = ? WHERE id = ?',
                [$newStatus, $applicationId],
                $database
            );
            Db::run(
                'INSERT INTO application_history (application_id, old_status, new_status, changed_by) VALUES (?, ?, ?, ?)',
                [$applicationId, $oldStatus, $newStatus, (int) ($actor['id'] ?? 0)],
                $database
            );
            Auth::audit(
                (int) ($actor['id'] ?? 0),
                'ADMIN',
                'application_status_changed',
                'applications',
                $applicationId,
                (string) json_encode(
                    ['old' => ['status' => $oldStatus], 'new' => ['status' => $newStatus]],
                    JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
                ),
                $database
            );
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }

        self::renderAdminDetail($applicationId, 'Status berhasil diperbarui.');
    }

    public static function downloadDocument(array $params = []): void
    {
        $applicationId = self::applicationId($params);
        $documentId = (string) ($params['docId'] ?? '');
        if ($applicationId === null || preg_match('/^[1-9][0-9]*$/D', $documentId) !== 1) {
            self::notFound('Lampiran tidak ditemukan.');
            return;
        }

        $document = Db::one(
            'SELECT id, nama_acak, mime_terdeteksi, ukuran FROM application_documents WHERE id = ? AND application_id = ?',
            [(int) $documentId, $applicationId]
        );
        if (
            $document === null
            || !in_array($document['mime_terdeteksi'], ['image/jpeg', 'image/png'], true)
            || preg_match('/^[a-f0-9]{64}\.(?:jpg|png)$/D', (string) $document['nama_acak']) !== 1
        ) {
            self::notFound('Lampiran tidak ditemukan.');
            return;
        }

        $directory = self::privateStorageDirectory();
        $path = $directory . DIRECTORY_SEPARATOR . $document['nama_acak'];
        if (!is_file($path) || is_link($path) || realpath(dirname($path)) !== realpath($directory)) {
            self::notFound('Lampiran tidak ditemukan.');
            return;
        }

        $actor = Auth::user();
        Auth::audit(
            (int) ($actor['id'] ?? 0),
            'ADMIN',
            'application_document_downloaded',
            'application_documents',
            (int) $document['id'],
            json_encode(
                ['application_id' => $applicationId, 'mime' => $document['mime_terdeteksi'], 'size' => (int) $document['ukuran']],
                JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
            )
        );
        if (!headers_sent()) {
            header('Content-Type: ' . $document['mime_terdeteksi']);
            header('Content-Disposition: attachment; filename="lampiran-' . (int) $document['id'] . (str_ends_with((string) $document['nama_acak'], '.png') ? '.png' : '.jpg') . '"');
            header('X-Content-Type-Options: nosniff');
            header('Cache-Control: no-store');
        }
        readfile($path);
    }

    private static function serviceByCode(string $code): ?array
    {
        return Db::one(
            'SELECT id, code, name, mode, is_active FROM services WHERE code = ?',
            [strtoupper(trim($code))]
        );
    }

    private static function findApplication(int $id): ?array
    {
        return Db::one(
            'SELECT a.id, a.service_id, a.applicant_user_id, a.tracking_code, a.request_text, a.status, a.created_at, a.updated_at, s.code AS service_code, s.name AS service_name, u.identifier AS applicant_identifier, u.full_name AS applicant_name FROM applications a INNER JOIN services s ON s.id = a.service_id INNER JOIN users u ON u.id = a.applicant_user_id WHERE a.id = ?',
            [$id]
        );
    }

    private static function applicationId(array $params): ?int
    {
        $value = (string) ($params['id'] ?? '');
        return preg_match('/^[1-9][0-9]*$/D', $value) === 1 ? (int) $value : null;
    }

    private static function renderAdminDetail(int $id, string $message): void
    {
        $application = self::findApplication($id);
        if ($application === null) {
            self::notFound('Pengajuan tidak ditemukan.');
            return;
        }
        $history = Db::all(
            'SELECT h.old_status, h.new_status, h.created_at, u.full_name AS changed_by_name FROM application_history h LEFT JOIN users u ON u.id = h.changed_by WHERE h.application_id = ? ORDER BY h.id',
            [$id]
        );
        $documents = Db::all(
            'SELECT id, mime_terdeteksi, ukuran, created_at FROM application_documents WHERE application_id = ? ORDER BY id',
            [$id]
        );
        View::render('pages/admin_application_detail', [
            'title' => 'Detail pengajuan',
            'application' => $application,
            'history' => $history,
            'documents' => $documents,
            'statusLabels' => ApplicationWorkflow::STATUS_LABELS,
            'nextStatuses' => ApplicationWorkflow::TRANSITIONS[$application['status']] ?? [],
            'message' => $message,
        ]);
    }

    private static function renderAdminDetailError(int $id, string $message, int $status): void
    {
        http_response_code($status);
        self::renderAdminDetail($id, $message);
    }

    private static function renderFormError(array $service, string $message, int $status, string $requestText): void
    {
        http_response_code($status);
        View::render('pages/application_form', [
            'title' => 'Ajukan layanan',
            'service' => $service,
            'error' => $message,
            'requestText' => $requestText,
        ]);
    }

    private static function validatedDocuments(string $serviceCode): array
    {
        $files = $_FILES['attachments'] ?? null;
        if ($files === null) {
            return [];
        }
        if (!in_array($serviceCode, self::DOCUMENT_SERVICES, true)) {
            throw new ApplicationDocumentValidationException('Lampiran tidak diizinkan untuk layanan ini.');
        }
        if (!is_array($files)) {
            throw new ApplicationDocumentValidationException('Format lampiran tidak valid.');
        }
        foreach (['name', 'tmp_name', 'error', 'size'] as $field) {
            if (!isset($files[$field]) || !is_array($files[$field])) {
                throw new ApplicationDocumentValidationException('Format lampiran tidak valid.');
            }
        }
        $count = count($files['name']);
        if (
            count($files['tmp_name']) !== $count
            || count($files['error']) !== $count
            || count($files['size']) !== $count
        ) {
            throw new ApplicationDocumentValidationException('Format lampiran tidak valid.');
        }

        $selected = [];
        for ($index = 0; $index < $count; $index++) {
            $error = $files['error'][$index];
            if (!is_int($error)) {
                throw new ApplicationDocumentValidationException('Format lampiran tidak valid.');
            }
            if ($error === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
                throw new ApplicationDocumentValidationException('Berkas terlalu besar.');
            }
            if ($error !== UPLOAD_ERR_OK || !is_string($files['tmp_name'][$index]) || !is_uploaded_file($files['tmp_name'][$index])) {
                throw new ApplicationDocumentValidationException('Unggahan berkas tidak berhasil.');
            }
            $selected[] = $index;
        }
        if (count($selected) > self::MAX_DOCUMENTS_PER_APPLICATION) {
            throw new ApplicationDocumentValidationException('Maksimal 3 berkas per pengajuan.');
        }

        $documents = [];
        foreach ($selected as $index) {
            $tmpName = $files['tmp_name'][$index];
            $size = filesize($tmpName);
            if ($size === false || $size > self::MAX_DOCUMENT_SIZE_BYTES) {
                throw new ApplicationDocumentValidationException('Berkas terlalu besar.');
            }
            if ($size < 1) {
                throw new ApplicationDocumentValidationException('Berkas gambar kosong atau tidak valid.');
            }

            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($tmpName);
            $image = @getimagesize($tmpName);
            $validImage = is_array($image)
                && (($mime === 'image/jpeg' && ($image[2] ?? null) === IMAGETYPE_JPEG)
                    || ($mime === 'image/png' && ($image[2] ?? null) === IMAGETYPE_PNG))
                && ($image['mime'] ?? null) === $mime;
            if (!$validImage) {
                throw new ApplicationDocumentValidationException('Hanya berkas JPG atau PNG yang valid.');
            }

            $originalName = self::cleanOriginalName($files['name'][$index] ?? null);
            $extension = $mime === 'image/png' ? '.png' : '.jpg';
            $sha256 = hash_file('sha256', $tmpName);
            if ($sha256 === false) {
                throw new ApplicationDocumentValidationException('Berkas gambar tidak dapat dibaca.');
            }
            $documents[] = [
                'tmp_name' => $tmpName,
                'original_name' => $originalName,
                'mime' => $mime,
                'size' => $size,
                'sha256' => $sha256,
                'extension' => $extension,
            ];
        }
        return $documents;
    }

    private static function cleanOriginalName(mixed $name): ?string
    {
        if (!is_string($name) || $name === '') {
            return null;
        }
        $name = str_replace('\\', '/', $name);
        $name = basename($name);
        $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name) ?? '';
        $name = trim($name);
        return $name === '' ? null : mb_substr($name, 0, 255, 'UTF-8');
    }

    private static function privateStorageDirectory(): string
    {
        $projectRoot = dirname(__DIR__, 2);
        $storageRoot = $projectRoot . DIRECTORY_SEPARATOR . 'storage';
        $privateDirectory = $storageRoot . DIRECTORY_SEPARATOR . 'private';
        if (is_link($storageRoot) || is_link($privateDirectory)) {
            throw new ApplicationDocumentStorageException('Direktori penyimpanan privat tidak aman.');
        }
        if (!is_dir($storageRoot) && !mkdir($storageRoot, 0700) && !is_dir($storageRoot)) {
            throw new ApplicationDocumentStorageException('Direktori penyimpanan privat tidak dapat dibuat.');
        }
        if (!is_dir($privateDirectory) && !mkdir($privateDirectory, 0700) && !is_dir($privateDirectory)) {
            throw new ApplicationDocumentStorageException('Direktori penyimpanan privat tidak dapat dibuat.');
        }
        $resolvedDirectory = realpath($privateDirectory);
        $resolvedRoot = realpath($projectRoot);
        if ($resolvedDirectory === false || $resolvedRoot === false || $resolvedDirectory !== $resolvedRoot . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'private') {
            throw new ApplicationDocumentStorageException('Direktori penyimpanan privat tidak valid.');
        }
        return $resolvedDirectory;
    }

    private static function removeStoredFiles(array $paths): void
    {
        foreach ($paths as $path) {
            if (is_file($path) && !unlink($path)) {
                throw new RuntimeException('Berkas lampiran gagal dibersihkan setelah transaksi dibatalkan.');
            }
        }
    }

    private static function iniSizeBytes(string $value): int
    {
        $value = trim($value);
        if ($value === '' || $value === '0') {
            return 0;
        }
        if (preg_match('/^([0-9]+)\s*([KMG])?$/i', $value, $matches) !== 1) {
            return 0;
        }
        $factor = match (strtoupper($matches[2] ?? '')) {
            'K' => 1024,
            'M' => 1024 ** 2,
            'G' => 1024 ** 3,
            default => 1,
        };
        return (int) $matches[1] * $factor;
    }

    private static function invalidApplication(array $service): void
    {
        http_response_code(422);
        View::render('pages/error', [
            'title' => 'Pengajuan tidak tersedia',
            'message' => ($service['code'] ?? '') === 'KES-01'
                ? 'Layanan ini hanya menyediakan informasi. Isi konseling dan data pribadi sensitif tidak disimpan di sistem.'
                : 'Pengajuan hanya tersedia untuk layanan bermode FORMULIR.',
        ]);
    }

    private static function trackingNotFound(): void
    {
        http_response_code(404);
        View::render('pages/error', [
            'title' => 'Cek status pengajuan',
            'message' => self::TRACKING_NOT_FOUND_MESSAGE,
        ]);
    }

    private static function trackingRateLimited(): void
    {
        http_response_code(429);
        View::render('pages/error', [
            'title' => 'Cek status pengajuan',
            'message' => self::TRACKING_RATE_LIMIT_MESSAGE,
        ]);
    }

    private static function setPrivatePageHeaders(): void
    {
        if (!headers_sent()) {
            header('Cache-Control: no-store');
            header('Referrer-Policy: no-referrer');
        }
    }

    private static function notFound(string $message): void
    {
        http_response_code(404);
        View::render('pages/error', ['title' => '404', 'message' => $message]);
    }
}

final class ApplicationDocumentValidationException extends RuntimeException
{
}

final class ApplicationDocumentStorageException extends RuntimeException
{
}
