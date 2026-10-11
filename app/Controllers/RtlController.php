<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Db;
use App\Core\View;
use App\Policies\Permissions;
use DateTimeImmutable;
use PDOException;

final class RtlController
{
    private const STATUSES = ['DIRENCANAKAN', 'BERJALAN', 'SELESAI'];

    public static function index(): void
    {
        self::setPrivateHeaders();
        if (!self::authorized('admin.rtl.view')) {
            return;
        }

        $evaluations = self::evaluations();
        $rawStatus = $_GET['status'] ?? '';
        $rawEvaluationId = $_GET['evaluation_id'] ?? '';
        $statusFilter = is_string($rawStatus) ? trim($rawStatus) : '';
        $evaluationValue = is_string($rawEvaluationId) ? trim($rawEvaluationId) : '';
        $evaluationId = $evaluationValue === '' ? null : self::positiveInteger($evaluationValue);
        $filterError = !is_string($rawStatus)
            || !is_string($rawEvaluationId)
            || ($evaluationValue !== '' && $evaluationId === null);
        if ($statusFilter !== '' && !in_array($statusFilter, self::STATUSES, true)) {
            $statusFilter = '';
            $filterError = true;
        }
        if ($evaluationId !== null && !self::containsEvaluation($evaluations, $evaluationId)) {
            $evaluationId = null;
            $filterError = true;
        }
        if ($filterError) {
            http_response_code(422);
        }

        View::render('pages/admin_rtl', [
            'title' => 'Rencana Tindak Lanjut',
            'evaluations' => $evaluations,
            'rtls' => self::listRtls($statusFilter, $evaluationId),
            'selectedStatus' => $statusFilter,
            'selectedEvaluationId' => $evaluationId,
            'filterError' => $filterError,
            'error' => '',
            'formValues' => [],
            'actionDescriptionMaxLength' => 2000,
            'responsibleNameMaxLength' => 150,
            'statusNoteMaxLength' => 500,
        ]);
    }

    public static function create(): void
    {
        self::setPrivateHeaders();
        $actor = self::adminUser('admin.rtl.create');
        if ($actor === null) {
            return;
        }

        $values = [
            'evaluation_id' => self::postString('evaluation_id'),
            'action_description' => self::postString('action_description', false),
            'responsible_name' => self::postString('responsible_name', false),
            'due_date' => self::postString('due_date'),
        ];
        $typesValid = true;
        foreach (array_keys($values) as $field) {
            $typesValid = $typesValid
                && (!array_key_exists($field, $_POST) || is_string($_POST[$field]));
        }
        $evaluationId = self::positiveInteger($values['evaluation_id']);
        $evaluation = $evaluationId === null
            ? null
            : Db::one('SELECT id FROM madani_evaluations WHERE id = ?', [$evaluationId]);
        $valid = $typesValid
            && $evaluation !== null
            && self::validDate($values['due_date'])
            && self::validText($values['action_description'], 2000, true)
            && self::validText($values['responsible_name'], 150, true);
        if (!$valid) {
            self::renderIndexError('Data RTL tidak valid. Periksa evaluasi, tenggat, uraian, dan penanggung jawab.', 422, $values);
            return;
        }

        $database = Auth::databaseName();
        $pdo = Db::pdo($database);
        $pdo->beginTransaction();
        try {
            Db::run(
                'INSERT INTO madani_rtl (evaluation_id, action_description, responsible_name, due_date, created_by) VALUES (?, ?, ?, ?, ?)',
                [
                    $evaluationId,
                    trim($values['action_description']),
                    trim($values['responsible_name']),
                    $values['due_date'],
                    (int) $actor['id'],
                ],
                $database
            );
            $rtlId = (int) $pdo->lastInsertId();
            Db::run(
                'INSERT INTO madani_rtl_status_log (rtl_id, status, note, changed_by) VALUES (?, ?, NULL, ?)',
                [$rtlId, 'DIRENCANAKAN', (int) $actor['id']],
                $database
            );
            self::audit(
                (int) $actor['id'],
                'rtl_created',
                $rtlId,
                $evaluationId,
                'DIRENCANAKAN',
                $database
            );
            $pdo->commit();
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[HUMA] RTL record transaction failed.');
            self::renderIndexError('RTL tidak dapat disimpan. Silakan coba lagi.', 500, $values);
            return;
        }

        if (!headers_sent()) {
            header('Location: /admin/rtl/' . $rtlId, true, 303);
        }
        http_response_code(303);
    }

    public static function detail(array $params = []): void
    {
        self::setPrivateHeaders();
        if (!self::authorized('admin.rtl.view')) {
            return;
        }
        $id = self::positiveInteger((string) ($params['id'] ?? ''));
        $rtl = $id === null ? null : self::findRtl($id);
        if ($rtl === null) {
            http_response_code(404);
            View::render('pages/error', ['title' => '404', 'message' => 'RTL tidak ditemukan.']);
            return;
        }

        $history = Db::all(
            'SELECT l.id, l.status, l.note, l.changed_by, l.created_at, u.full_name AS changed_by_name FROM madani_rtl_status_log AS l INNER JOIN users AS u ON u.id = l.changed_by WHERE l.rtl_id = ? ORDER BY l.id',
            [$id]
        );
        View::render('pages/admin_rtl_detail', [
            'title' => 'Detail RTL',
            'rtl' => $rtl,
            'history' => $history,
            'availableTransitions' => self::availableTransitions((string) $rtl['current_status']),
            'statusNoteMaxLength' => 500,
        ]);
    }

    public static function updateStatus(array $params = []): void
    {
        self::setPrivateHeaders();
        $actor = self::adminUser('admin.rtl.update_status');
        if ($actor === null) {
            return;
        }

        $id = self::positiveInteger((string) ($params['id'] ?? ''));
        if ($id === null) {
            self::statusNotFound();
            return;
        }
        $statusValue = $_POST['status'] ?? null;
        $noteValue = $_POST['note'] ?? '';
        $typesValid = is_string($statusValue) && is_string($noteValue);
        $targetStatus = is_string($statusValue) ? trim($statusValue) : '';
        $note = is_string($noteValue) ? $noteValue : '';
        if (!$typesValid || !self::validText($note, 500, false) || !in_array($targetStatus, self::STATUSES, true)) {
            self::renderStatusError($id, 'Perubahan status RTL tidak valid.', 422);
            return;
        }

        $database = Auth::databaseName();
        $pdo = Db::pdo($database);
        $pdo->beginTransaction();
        try {
            $rtl = Db::one('SELECT id, evaluation_id FROM madani_rtl WHERE id = ? FOR UPDATE', [$id], $database);
            if ($rtl === null) {
                $pdo->rollBack();
                self::statusNotFound();
                return;
            }
            $latest = Db::one(
                'SELECT status FROM madani_rtl_status_log WHERE rtl_id = ? ORDER BY id DESC LIMIT 1 FOR UPDATE',
                [$id],
                $database
            );
            $currentStatus = (string) ($latest['status'] ?? '');
            if (!self::transitionIsAllowed($currentStatus, $targetStatus)) {
                $pdo->rollBack();
                self::renderStatusError($id, 'Transisi status RTL tidak diizinkan.', 422);
                return;
            }

            Db::run(
                'INSERT INTO madani_rtl_status_log (rtl_id, status, note, changed_by) VALUES (?, ?, ?, ?)',
                [$id, $targetStatus, self::nullableText($note), (int) $actor['id']],
                $database
            );
            self::audit(
                (int) $actor['id'],
                'rtl_status_changed',
                $id,
                (int) $rtl['evaluation_id'],
                $targetStatus,
                $database
            );
            $pdo->commit();
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[HUMA] RTL status transaction failed.');
            self::renderStatusError($id, 'Status RTL tidak dapat diubah. Silakan coba lagi.', 500);
            return;
        }

        if (!headers_sent()) {
            header('Location: /admin/rtl/' . $id, true, 303);
        }
        http_response_code(303);
    }

    public static function availableTransitions(string $currentStatus): array
    {
        return match ($currentStatus) {
            'DIRENCANAKAN' => ['BERJALAN', 'SELESAI'],
            'BERJALAN' => ['SELESAI'],
            'SELESAI' => [],
            default => [],
        };
    }

    public static function transitionIsAllowed(string $currentStatus, string $targetStatus): bool
    {
        return in_array($targetStatus, self::availableTransitions($currentStatus), true);
    }

    private static function validDate(string $value): bool
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value) !== 1) {
            return false;
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = DateTimeImmutable::getLastErrors();
        return $date !== false
            && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))
            && $date->format('Y-m-d') === $value;
    }

    private static function validText(string $value, int $limit, bool $required): bool
    {
        return mb_check_encoding($value, 'UTF-8')
            && (!$required || trim($value) !== '')
            && mb_strlen($value, 'UTF-8') <= $limit;
    }

    private static function nullableText(string $value): ?string
    {
        return trim($value) === '' ? null : trim($value);
    }

    private static function renderIndexError(string $error, int $status, array $values): void
    {
        self::setPrivateHeaders();
        http_response_code($status);
        $evaluations = self::evaluations();
        View::render('pages/admin_rtl', [
            'title' => 'Rencana Tindak Lanjut',
            'evaluations' => $evaluations,
            'rtls' => self::listRtls('', null),
            'selectedStatus' => '',
            'selectedEvaluationId' => null,
            'filterError' => false,
            'error' => $error,
            'formValues' => $values,
            'actionDescriptionMaxLength' => 2000,
            'responsibleNameMaxLength' => 150,
            'statusNoteMaxLength' => 500,
        ]);
    }

    private static function renderStatusError(int $id, string $error, int $status): void
    {
        self::setPrivateHeaders();
        http_response_code($status);
        $rtl = self::findRtl($id);
        if ($rtl === null) {
            self::statusNotFound();
            return;
        }
        $history = Db::all(
            'SELECT l.id, l.status, l.note, l.changed_by, l.created_at, u.full_name AS changed_by_name FROM madani_rtl_status_log AS l INNER JOIN users AS u ON u.id = l.changed_by WHERE l.rtl_id = ? ORDER BY l.id',
            [$id]
        );
        View::render('pages/admin_rtl_detail', [
            'title' => 'Detail RTL',
            'rtl' => $rtl,
            'history' => $history,
            'availableTransitions' => self::availableTransitions((string) $rtl['current_status']),
            'statusNoteMaxLength' => 500,
            'error' => $error,
        ]);
    }

    private static function statusNotFound(): void
    {
        self::setPrivateHeaders();
        http_response_code(404);
        View::render('pages/error', ['title' => '404', 'message' => 'RTL tidak ditemukan.']);
    }

    private static function evaluations(): array
    {
        return Db::all(
            'SELECT e.id, e.period_start, e.period_end, e.corrects_evaluation_id, s.code AS service_code, s.name AS service_name FROM madani_evaluations AS e INNER JOIN services AS s ON s.id = e.service_id ORDER BY e.created_at DESC, e.id DESC'
        );
    }

    private static function listRtls(string $statusFilter, ?int $evaluationId): array
    {
        $sql = 'SELECT r.id, r.evaluation_id, r.action_description, r.responsible_name, r.due_date, r.created_at, e.period_start, e.period_end, e.corrects_evaluation_id, s.code AS service_code, s.name AS service_name, latest.status AS current_status, latest.id AS latest_status_log_id FROM madani_rtl AS r INNER JOIN madani_evaluations AS e ON e.id = r.evaluation_id INNER JOIN services AS s ON s.id = e.service_id INNER JOIN (SELECT rtl_id, MAX(id) AS latest_id FROM madani_rtl_status_log GROUP BY rtl_id) AS latest_log ON latest_log.rtl_id = r.id INNER JOIN madani_rtl_status_log AS latest ON latest.id = latest_log.latest_id';
        $conditions = [];
        $params = [];
        if ($statusFilter !== '') {
            $conditions[] = 'latest.status = ?';
            $params[] = $statusFilter;
        }
        if ($evaluationId !== null) {
            $conditions[] = 'r.evaluation_id = ?';
            $params[] = $evaluationId;
        }
        if ($conditions !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }
        return Db::all($sql . ' ORDER BY r.created_at DESC, r.id DESC', $params);
    }

    private static function findRtl(int $id): ?array
    {
        return Db::one(
            'SELECT r.id, r.evaluation_id, r.action_description, r.responsible_name, r.due_date, r.created_by, r.created_at, e.period_start, e.period_end, e.corrects_evaluation_id, e.summary AS evaluation_summary, s.code AS service_code, s.name AS service_name, current_log.status AS current_status FROM madani_rtl AS r INNER JOIN madani_evaluations AS e ON e.id = r.evaluation_id INNER JOIN services AS s ON s.id = e.service_id INNER JOIN (SELECT rtl_id, MAX(id) AS latest_id FROM madani_rtl_status_log GROUP BY rtl_id) AS latest_log ON latest_log.rtl_id = r.id INNER JOIN madani_rtl_status_log AS current_log ON current_log.id = latest_log.latest_id WHERE r.id = ?',
            [$id]
        );
    }

    private static function audit(
        int $actorId,
        string $action,
        int $rtlId,
        int $evaluationId,
        string $newStatus,
        string $database
    ): void {
        Auth::audit(
            $actorId,
            'ADMIN',
            $action,
            'madani_rtl',
            $rtlId,
            (string) json_encode([
                'evaluation_id' => $evaluationId,
                'status_new' => $newStatus,
            ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            $database
        );
    }

    private static function postString(string $key, bool $trim = true): string
    {
        $value = $_POST[$key] ?? '';
        if (!is_string($value)) {
            return '';
        }
        return $trim ? trim($value) : $value;
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

    private static function containsEvaluation(array $evaluations, int $evaluationId): bool
    {
        foreach ($evaluations as $evaluation) {
            if ((int) $evaluation['id'] === $evaluationId) {
                return true;
            }
        }
        return false;
    }

    private static function authorized(string $ability): bool
    {
        return self::adminUser($ability) !== null;
    }

    private static function adminUser(string $ability): ?array
    {
        $actor = Auth::user();
        if ($actor !== null && Permissions::can((string) ($actor['role_code'] ?? ''), $ability)) {
            return $actor;
        }
        http_response_code($actor === null ? 302 : 403);
        if ($actor === null && !headers_sent()) {
            header('Location: /login', true, 302);
        } else {
            View::render('pages/error', ['title' => '403', 'message' => 'Anda tidak berhak mengakses halaman ini.']);
        }
        return null;
    }

    private static function setPrivateHeaders(): void
    {
        if (!headers_sent()) {
            header('Cache-Control: no-store');
            header('Referrer-Policy: no-referrer');
        }
    }
}
