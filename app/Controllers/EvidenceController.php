<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Db;
use App\Core\View;
use App\Policies\Permissions;
use RuntimeException;
use Throwable;

final class EvidenceController
{
    private const MAX_FILES = 3;
    private const MAX_FILE_BYTES = 5 * 1024 * 1024;

    public static function index(): void
    {
        self::setPrivateHeaders();
        if (!self::authorized('admin.evidence.view')) {
            return;
        }

        $services = Db::all('SELECT id, code, name FROM services ORDER BY code');
        $checkpoints = Db::all('SELECT id, code, name FROM evidence_checkpoints ORDER BY code');
        $status = self::queryString('status');
        $serviceId = self::queryId('service_id');
        $checkpointId = self::queryId('checkpoint_id');
        $error = '';

        if (
            ($status !== '' && !in_array($status, ['DIUNGGAH', 'TERVERIFIKASI', 'PERLU_PERBAIKAN'], true))
            || ($serviceId !== null && !self::containsId($services, $serviceId))
            || ($checkpointId !== null && !self::containsId($checkpoints, $checkpointId))
        ) {
            http_response_code(422);
            $error = 'Filter eviden tidak valid.';
            $status = '';
            $serviceId = null;
            $checkpointId = null;
        }

        $where = [];
        $params = [];
        if ($serviceId !== null) {
            $where[] = 'e.service_id = ?';
            $params[] = $serviceId;
        }
        if ($checkpointId !== null) {
            $where[] = 'e.checkpoint_id = ?';
            $params[] = $checkpointId;
        }
        if ($status !== '') {
            $where[] = 'e.status = ?';
            $params[] = $status;
        }
        $sql = 'SELECT e.id, e.service_id, e.checkpoint_id, e.status, e.original_name, e.mime, e.size_bytes, e.sha256, e.uploaded_by, e.verified_by, e.verified_at, e.review_reason, e.created_at, s.code AS service_code, s.name AS service_name, c.code AS checkpoint_code, c.name AS checkpoint_name, uploader.full_name AS uploader_name, verifier.full_name AS verifier_name FROM evidence_items e INNER JOIN services s ON s.id = e.service_id INNER JOIN evidence_checkpoints c ON c.id = e.checkpoint_id INNER JOIN users uploader ON uploader.id = e.uploaded_by LEFT JOIN users verifier ON verifier.id = e.verified_by';
        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY e.id DESC';

        View::render('pages/admin_evidence', [
            'title' => 'Eviden',
            'items' => Db::all($sql, $params),
            'services' => $services,
            'checkpoints' => $checkpoints,
            'selectedServiceId' => $serviceId,
            'selectedCheckpointId' => $checkpointId,
            'selectedStatus' => $status,
            'message' => (string) ($_SESSION['evidence_flash'] ?? ''),
            'error' => $error,
        ]);
        unset($_SESSION['evidence_flash']);
    }

    public static function upload(): void
    {
        self::setPrivateHeaders();
        $actor = self::adminUser('admin.evidence.upload');
        if ($actor === null) {
            return;
        }

        $serviceId = self::postId('service_id');
        $checkpointId = self::postId('checkpoint_id');
        $service = $serviceId === null
            ? null
            : Db::one('SELECT id FROM services WHERE id = ?', [$serviceId]);
        $checkpoint = $checkpointId === null
            ? null
            : Db::one('SELECT id, code, name FROM evidence_checkpoints WHERE id = ?', [$checkpointId]);
        if ($service === null || $checkpoint === null) {
            self::renderUploadError('Layanan atau checkpoint tidak valid.', 422);
            return;
        }

        try {
            $files = self::validatedFiles();
        } catch (EvidenceValidationException $exception) {
            self::renderUploadError($exception->getMessage(), 422);
            return;
        }

        $database = Auth::databaseName();
        $pdo = Db::pdo($database);
        $storedPaths = [];
        $evidenceIds = [];
        $pdo->beginTransaction();
        try {
            $directory = self::privateStorageDirectory();
            foreach ($files as $file) {
                $storedName = bin2hex(random_bytes(32)) . $file['extension'];
                $storedPath = $directory . DIRECTORY_SEPARATOR . $storedName;
                if (!move_uploaded_file($file['tmp_name'], $storedPath)) {
                    throw new EvidenceStorageException();
                }
                $storedPaths[] = $storedPath;
                Db::run(
                    'INSERT INTO evidence_items (service_id, checkpoint_id, stored_name, original_name, mime, size_bytes, sha256, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                    [
                        $serviceId,
                        $checkpointId,
                        $storedName,
                        $file['original_name'],
                        $file['mime'],
                        $file['size'],
                        $file['sha256'],
                        (int) $actor['id'],
                    ],
                    $database
                );
                $evidenceId = (int) $pdo->lastInsertId();
                $evidenceIds[] = $evidenceId;
                self::audit(
                    (int) $actor['id'],
                    'evidence_uploaded',
                    $evidenceId,
                    [
                        'service_id' => $serviceId,
                        'checkpoint_code' => $checkpoint['code'],
                        'status' => 'DIUNGGAH',
                        'mime' => $file['mime'],
                        'size_bytes' => $file['size'],
                        'sha256' => $file['sha256'],
                    ],
                    $database
                );
            }
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            self::removeStoredFiles($storedPaths);
            if ($exception instanceof EvidenceStorageException) {
                error_log('[HUMA] Evidence upload storage operation failed.');
                self::renderUploadError('Eviden tidak dapat disimpan. Silakan coba lagi.', 500);
                return;
            }
            throw $exception;
        }

        $_SESSION['evidence_flash'] = count($evidenceIds) . ' eviden berhasil diunggah.';
        if (!headers_sent()) {
            header('Location: /admin/eviden', true, 303);
        }
    }

    public static function detail(array $params = []): void
    {
        self::setPrivateHeaders();
        if (!self::authorized('admin.evidence.view')) {
            return;
        }
        $id = self::parameterId($params, 'id');
        $item = $id === null ? null : self::findEvidence($id);
        if ($item === null) {
            self::notFound();
            return;
        }
        View::render('pages/admin_evidence_detail', [
            'title' => 'Detail eviden',
            'item' => $item,
            'sameVerifier' => $item['verified_by'] !== null
                && (int) $item['uploaded_by'] === (int) $item['verified_by'],
        ]);
    }

    public static function download(array $params = []): void
    {
        self::setPrivateHeaders();
        $actor = self::adminUser('admin.evidence.download');
        if ($actor === null) {
            return;
        }
        $id = self::parameterId($params, 'id');
        $item = $id === null
            ? null
            : Db::one(
                'SELECT id, service_id, checkpoint_id, stored_name, mime, size_bytes, sha256 FROM evidence_items WHERE id = ?',
                [$id]
            );
        if ($item === null) {
            self::notFound();
            return;
        }

        $directory = self::privateStorageDirectory();
        $storedName = (string) $item['stored_name'];
        $path = $directory . DIRECTORY_SEPARATOR . $storedName;
        $resolvedDirectory = realpath($directory);
        $resolvedFile = realpath($path);
        if (
            preg_match('/^[a-f0-9]{64}\.(?:jpg|png)$/D', $storedName) !== 1
            || !in_array($item['mime'], ['image/jpeg', 'image/png'], true)
            || !is_file($path)
            || is_link($path)
            || $resolvedDirectory === false
            || $resolvedFile === false
            || dirname($resolvedFile) !== $resolvedDirectory
        ) {
            if (!is_file($path)) {
                error_log('[HUMA] Evidence download file is unavailable.');
            }
            self::notFound();
            return;
        }

        self::audit(
            (int) $actor['id'],
            'evidence_downloaded',
            (int) $item['id'],
            [
                'service_id' => (int) $item['service_id'],
                'checkpoint_id' => (int) $item['checkpoint_id'],
                'mime' => (string) $item['mime'],
                'size_bytes' => (int) $item['size_bytes'],
                'sha256' => (string) $item['sha256'],
            ]
        );
        if (!headers_sent()) {
            header('Content-Type: ' . $item['mime']);
            header('Content-Disposition: attachment; filename="eviden-' . (int) $item['id'] . (str_ends_with($storedName, '.png') ? '.png' : '.jpg') . '"');
            header('X-Content-Type-Options: nosniff');
            header('Cache-Control: no-store');
            header('Content-Length: ' . (string) filesize($path));
        }
        readfile($path);
    }

    public static function verify(array $params = []): void
    {
        self::setPrivateHeaders();
        $actor = self::adminUser('admin.evidence.verify');
        if ($actor === null) {
            return;
        }
        $id = self::parameterId($params, 'id');
        $decision = self::postString('decision');
        $reason = self::postString('review_reason');
        if (
            $id === null
            || !in_array($decision, ['TERVERIFIKASI', 'PERLU_PERBAIKAN'], true)
            || ($decision === 'PERLU_PERBAIKAN'
                && ($reason === '' || !mb_check_encoding($reason, 'UTF-8') || mb_strlen($reason, 'UTF-8') > 500))
        ) {
            self::notFound();
            return;
        }

        $database = Auth::databaseName();
        $pdo = Db::pdo($database);
        $pdo->beginTransaction();
        try {
            $item = Db::one(
                'SELECT id, service_id, checkpoint_id, status, mime, size_bytes, sha256 FROM evidence_items WHERE id = ? FOR UPDATE',
                [$id],
                $database
            );
            if ($item === null) {
                $pdo->rollBack();
                self::notFound();
                return;
            }
            if ($item['status'] !== 'DIUNGGAH') {
                $pdo->rollBack();
                http_response_code(409);
                self::renderError('Eviden sudah diputuskan dan tidak dapat diubah.');
                return;
            }
            $storedReason = $decision === 'PERLU_PERBAIKAN' ? $reason : null;
            $updated = Db::run(
                'UPDATE evidence_items SET status = ?, verified_by = ?, verified_at = NOW(), review_reason = ? WHERE id = ? AND status = ?',
                [$decision, (int) $actor['id'], $storedReason, $id, 'DIUNGGAH'],
                $database
            );
            if ($updated !== 1) {
                $pdo->rollBack();
                http_response_code(409);
                self::renderError('Eviden sudah diputuskan dan tidak dapat diubah.');
                return;
            }
            self::audit(
                (int) $actor['id'],
                'evidence_reviewed',
                $id,
                [
                    'service_id' => (int) $item['service_id'],
                    'checkpoint_id' => (int) $item['checkpoint_id'],
                    'old_status' => 'DIUNGGAH',
                    'new_status' => $decision,
                    'mime' => (string) $item['mime'],
                    'size_bytes' => (int) $item['size_bytes'],
                    'sha256' => (string) $item['sha256'],
                ],
                $database
            );
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }

        $_SESSION['evidence_flash'] = 'Keputusan eviden berhasil disimpan.';
        if (!headers_sent()) {
            header('Location: /admin/eviden/' . $id, true, 303);
        }
    }

    private static function validatedFiles(): array
    {
        $files = $_FILES['attachments'] ?? null;
        if (!is_array($files)) {
            throw new EvidenceValidationException('Pilih minimal satu berkas JPG atau PNG.');
        }
        foreach (['name', 'tmp_name', 'error', 'size'] as $field) {
            if (!isset($files[$field]) || !is_array($files[$field])) {
                throw new EvidenceValidationException('Format unggahan tidak valid.');
            }
        }
        $count = count($files['name']);
        if (
            $count < 1
            || $count > self::MAX_FILES
            || count($files['tmp_name']) !== $count
            || count($files['error']) !== $count
            || count($files['size']) !== $count
        ) {
            throw new EvidenceValidationException('Unggah satu sampai tiga berkas JPG atau PNG.');
        }

        $validated = [];
        foreach ($files['name'] as $index => $name) {
            $error = $files['error'][$index] ?? null;
            $tmpName = $files['tmp_name'][$index] ?? null;
            if ($error !== UPLOAD_ERR_OK || !is_string($tmpName) || !is_uploaded_file($tmpName)) {
                throw new EvidenceValidationException('Unggahan berkas tidak berhasil.');
            }
            $size = filesize($tmpName);
            if ($size === false || $size < 1 || $size > self::MAX_FILE_BYTES) {
                throw new EvidenceValidationException('Ukuran berkas harus lebih dari 0 dan maksimal 5 MiB.');
            }
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($tmpName);
            $image = @getimagesize($tmpName);
            $validImage = is_array($image)
                && (($mime === 'image/jpeg' && ($image[2] ?? null) === IMAGETYPE_JPEG)
                    || ($mime === 'image/png' && ($image[2] ?? null) === IMAGETYPE_PNG))
                && ($image['mime'] ?? null) === $mime;
            if (!$validImage) {
                throw new EvidenceValidationException('Hanya berkas JPG atau PNG yang valid.');
            }
            $sha256 = hash_file('sha256', $tmpName);
            if ($sha256 === false) {
                throw new EvidenceValidationException('Berkas tidak dapat dibaca.');
            }
            $validated[] = [
                'tmp_name' => $tmpName,
                'original_name' => self::cleanOriginalName($name),
                'mime' => $mime,
                'size' => $size,
                'sha256' => $sha256,
                'extension' => $mime === 'image/png' ? '.png' : '.jpg',
            ];
        }
        return $validated;
    }

    private static function cleanOriginalName(mixed $name): ?string
    {
        if (!is_string($name) || $name === '') {
            return null;
        }
        $name = basename(str_replace('\\', '/', $name));
        $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name) ?? '';
        $name = trim($name);
        return $name === '' || !mb_check_encoding($name, 'UTF-8')
            ? null
            : mb_substr($name, 0, 255, 'UTF-8');
    }

    private static function privateStorageDirectory(): string
    {
        $projectRoot = dirname(__DIR__, 2);
        $storageRoot = $projectRoot . DIRECTORY_SEPARATOR . 'storage';
        $privateRoot = $storageRoot . DIRECTORY_SEPARATOR . 'private';
        $fixtureToken = getenv('HUMA_TEST_FIXTURE_TOKEN');
        $testEnvironment = (getenv('APP_ENV') ?: ($_SERVER['APP_ENV'] ?? '')) === 'test';
        if ($testEnvironment && (!is_string($fixtureToken) || preg_match('/^[a-f0-9]{64}$/D', $fixtureToken) !== 1)) {
            throw new EvidenceStorageException();
        }
        $directoryName = $testEnvironment ? 'evidence-fixture-' . $fixtureToken : 'evidence';
        $directory = $privateRoot . DIRECTORY_SEPARATOR . $directoryName;
        foreach ([$storageRoot, $privateRoot, $directory] as $candidate) {
            if (is_link($candidate)) {
                throw new EvidenceStorageException();
            }
            if (!is_dir($candidate) && !mkdir($candidate, 0700) && !is_dir($candidate)) {
                throw new EvidenceStorageException();
            }
        }
        $resolvedRoot = realpath($privateRoot);
        $resolvedDirectory = realpath($directory);
        if (
            $resolvedRoot === false
            || $resolvedDirectory !== $resolvedRoot . DIRECTORY_SEPARATOR . $directoryName
        ) {
            throw new EvidenceStorageException();
        }
        return $resolvedDirectory;
    }

    private static function removeStoredFiles(array $paths): void
    {
        foreach ($paths as $path) {
            if (is_file($path) && !unlink($path)) {
                error_log('[HUMA] Evidence file cleanup failed after transaction rollback.');
            }
        }
    }

    private static function findEvidence(int $id): ?array
    {
        return Db::one(
            'SELECT e.id, e.service_id, e.checkpoint_id, e.status, e.original_name, e.mime, e.size_bytes, e.sha256, e.uploaded_by, e.verified_by, e.verified_at, e.review_reason, e.created_at, s.code AS service_code, s.name AS service_name, c.code AS checkpoint_code, c.name AS checkpoint_name, uploader.full_name AS uploader_name, verifier.full_name AS verifier_name FROM evidence_items e INNER JOIN services s ON s.id = e.service_id INNER JOIN evidence_checkpoints c ON c.id = e.checkpoint_id INNER JOIN users uploader ON uploader.id = e.uploaded_by LEFT JOIN users verifier ON verifier.id = e.verified_by WHERE e.id = ?',
            [$id]
        );
    }

    private static function audit(int $actorId, string $action, int $id, array $metadata, ?string $database = null): void
    {
        Auth::audit(
            $actorId,
            'ADMIN',
            $action,
            'evidence_items',
            $id,
            (string) json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            $database
        );
    }

    private static function adminUser(string $ability): ?array
    {
        $actor = Auth::user();
        if ($actor === null || !Permissions::can((string) ($actor['role_code'] ?? ''), $ability)) {
            http_response_code($actor === null ? 302 : 403);
            if ($actor === null && !headers_sent()) {
                header('Location: /login', true, 302);
            } else {
                self::renderError('Anda tidak berhak mengakses halaman ini.');
            }
            return null;
        }
        return $actor;
    }

    private static function authorized(string $ability): bool
    {
        return self::adminUser($ability) !== null;
    }

    private static function renderUploadError(string $message, int $status): void
    {
        self::setPrivateHeaders();
        http_response_code($status);
        $services = Db::all('SELECT id, code, name FROM services ORDER BY code');
        $checkpoints = Db::all('SELECT id, code, name FROM evidence_checkpoints ORDER BY code');
        View::render('pages/admin_evidence', [
            'title' => 'Eviden',
            'items' => self::listItems(),
            'services' => $services,
            'checkpoints' => $checkpoints,
            'selectedServiceId' => null,
            'selectedCheckpointId' => null,
            'selectedStatus' => '',
            'message' => '',
            'error' => $message,
        ]);
    }

    private static function listItems(): array
    {
        return Db::all(
            'SELECT e.id, e.service_id, e.checkpoint_id, e.status, e.original_name, e.mime, e.size_bytes, e.sha256, e.uploaded_by, e.verified_by, e.verified_at, e.review_reason, e.created_at, s.code AS service_code, s.name AS service_name, c.code AS checkpoint_code, c.name AS checkpoint_name, uploader.full_name AS uploader_name, verifier.full_name AS verifier_name FROM evidence_items e INNER JOIN services s ON s.id = e.service_id INNER JOIN evidence_checkpoints c ON c.id = e.checkpoint_id INNER JOIN users uploader ON uploader.id = e.uploaded_by LEFT JOIN users verifier ON verifier.id = e.verified_by ORDER BY e.id DESC'
        );
    }

    private static function queryString(string $key): string
    {
        return isset($_GET[$key]) && is_string($_GET[$key]) ? trim($_GET[$key]) : '';
    }

    private static function queryId(string $key): ?int
    {
        $value = self::queryString($key);
        return $value === '' ? null : (self::positiveInteger($value) ?? 0);
    }

    private static function postId(string $key): ?int
    {
        $value = $_POST[$key] ?? null;
        return is_string($value) ? self::positiveInteger($value) : null;
    }

    private static function parameterId(array $params, string $key): ?int
    {
        $value = $params[$key] ?? '';
        return is_string($value) ? self::positiveInteger($value) : null;
    }

    private static function positiveInteger(string $value): ?int
    {
        if (preg_match('/^[1-9][0-9]*$/D', $value) !== 1) {
            return null;
        }
        $maximum = (string) PHP_INT_MAX;
        if (
            strlen($value) > strlen($maximum)
            || (strlen($value) === strlen($maximum) && strcmp($value, $maximum) > 0)
        ) {
            return null;
        }
        return (int) $value;
    }

    private static function postString(string $key): string
    {
        return isset($_POST[$key]) && is_string($_POST[$key]) ? $_POST[$key] : '';
    }

    private static function containsId(array $rows, int $id): bool
    {
        foreach ($rows as $row) {
            if ((int) $row['id'] === $id) {
                return true;
            }
        }
        return false;
    }

    private static function notFound(): void
    {
        http_response_code(404);
        self::renderError('Eviden tidak ditemukan.');
    }

    private static function renderError(string $message): void
    {
        View::render('pages/error', ['title' => 'Eviden', 'message' => $message]);
    }

    private static function setPrivateHeaders(): void
    {
        if (!headers_sent()) {
            header('Cache-Control: no-store');
            header('Referrer-Policy: no-referrer');
        }
    }
}

final class EvidenceValidationException extends RuntimeException
{
}

final class EvidenceStorageException extends RuntimeException
{
}
