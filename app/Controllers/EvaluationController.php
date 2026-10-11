<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Db;
use App\Core\View;
use App\Policies\Permissions;
use DateTimeImmutable;
use PDOException;

final class EvaluationController
{
    public static function index(): void
    {
        self::setPrivateHeaders();
        if (!self::authorized('admin.evaluations.view')) {
            return;
        }

        $services = Db::all('SELECT id, code, name FROM services ORDER BY code');
        $serviceId = self::queryId('service_id');
        $filterError = false;
        if ($serviceId !== null && !self::containsService($services, $serviceId)) {
            $serviceId = null;
            $filterError = true;
            http_response_code(422);
        }

        View::render('pages/admin_evaluations', [
            'title' => 'Evaluasi layanan',
            'services' => $services,
            'evaluations' => self::listEvaluations($serviceId),
            'previousEvaluations' => self::listEvaluations(null),
            'selectedServiceId' => $serviceId,
            'filterError' => $filterError,
            'error' => '',
            'formValues' => [],
        ]);
    }

    public static function create(): void
    {
        self::setPrivateHeaders();
        $actor = self::adminUser('admin.evaluations.create');
        if ($actor === null) {
            return;
        }

        $values = [
            'service_id' => self::postString('service_id'),
            'period_start' => self::postString('period_start'),
            'period_end' => self::postString('period_end'),
            'summary' => self::postString('summary', false),
            'findings' => self::postString('findings', false),
            'conclusion' => self::postString('conclusion', false),
            'corrects_evaluation_id' => self::postString('corrects_evaluation_id'),
        ];
        $typesValid = true;
        foreach (array_keys($values) as $field) {
            $typesValid = $typesValid
                && (!array_key_exists($field, $_POST) || is_string($_POST[$field]));
        }
        $serviceId = self::positiveInteger($values['service_id']);
        $service = $serviceId === null
            ? null
            : Db::one('SELECT id, code, name FROM services WHERE id = ?', [$serviceId]);
        $correctsId = $values['corrects_evaluation_id'] === ''
            ? null
            : self::positiveInteger($values['corrects_evaluation_id']);
        $corrected = $correctsId === null
            ? null
            : Db::one(
                'SELECT id, service_id FROM madani_evaluations WHERE id = ?',
                [$correctsId]
            );

        $valid = $typesValid
            && $service !== null
            && self::periodIsValid($values['period_start'], $values['period_end'])
            && self::validText($values['summary'], 2000, true)
            && self::validText($values['findings'], 2000, false)
            && self::validText($values['conclusion'], 1000, false)
            && ($values['corrects_evaluation_id'] === '' || $correctsId !== null)
            && ($correctsId === null || (
                $corrected !== null && (int) $corrected['service_id'] === $serviceId
            ));

        if (!$valid) {
            self::renderIndexError('Data evaluasi tidak valid. Periksa layanan, tanggal, isi teks, dan catatan yang dikoreksi.', 422, $values);
            return;
        }

        $database = Auth::databaseName();
        $pdo = Db::pdo($database);
        $pdo->beginTransaction();
        try {
            Db::run(
                'INSERT INTO madani_evaluations (service_id, period_start, period_end, summary, findings, conclusion, corrects_evaluation_id, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $serviceId,
                    $values['period_start'],
                    $values['period_end'],
                    trim($values['summary']),
                    self::nullableText($values['findings']),
                    self::nullableText($values['conclusion']),
                    $correctsId,
                    (int) $actor['id'],
                ],
                $database
            );
            $evaluationId = (int) $pdo->lastInsertId();
            self::audit(
                (int) $actor['id'],
                $evaluationId,
                [
                    'service_id' => $serviceId,
                    'period_start' => $values['period_start'],
                    'period_end' => $values['period_end'],
                    'corrects_evaluation_id' => $correctsId,
                ],
                $database
            );
            $pdo->commit();
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[HUMA] Evaluation record transaction failed.');
            self::renderIndexError('Evaluasi tidak dapat disimpan. Silakan coba lagi.', 500, $values);
            return;
        }

        if (!headers_sent()) {
            header('Location: /admin/evaluasi/' . $evaluationId, true, 303);
        }
        http_response_code(303);
    }

    public static function detail(array $params = []): void
    {
        self::setPrivateHeaders();
        if (!self::authorized('admin.evaluations.view')) {
            return;
        }
        $id = self::positiveInteger((string) ($params['id'] ?? ''));
        $evaluation = $id === null ? null : Db::one(
            'SELECT e.id, e.service_id, e.period_start, e.period_end, e.summary, e.findings, e.conclusion, e.corrects_evaluation_id, e.created_by, e.created_at, s.code AS service_code, s.name AS service_name, author.full_name AS author_name, (SELECT GROUP_CONCAT(correction.id ORDER BY correction.id SEPARATOR \',\') FROM madani_evaluations AS correction WHERE correction.corrects_evaluation_id = e.id) AS corrected_by_ids FROM madani_evaluations AS e INNER JOIN services AS s ON s.id = e.service_id INNER JOIN users AS author ON author.id = e.created_by WHERE e.id = ?',
            [$id]
        );
        if ($evaluation === null) {
            http_response_code(404);
            View::render('pages/error', ['title' => '404', 'message' => 'Evaluasi tidak ditemukan.']);
            return;
        }

        View::render('pages/admin_evaluation_detail', [
            'title' => 'Detail evaluasi',
            'evaluation' => $evaluation,
        ]);
    }

    public static function periodIsValid(string $startDate, string $endDate): bool
    {
        $start = self::parseDate($startDate);
        $end = self::parseDate($endDate);
        if ($start === null || $end === null || $start > $end) {
            return false;
        }

        return (int) $start->diff($end)->format('%a') <= 366;
    }

    private static function parseDate(string $value): ?DateTimeImmutable
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value) !== 1) {
            return null;
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = DateTimeImmutable::getLastErrors();
        if (
            $date === false
            || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
            || $date->format('Y-m-d') !== $value
        ) {
            return null;
        }
        return $date;
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
        $services = Db::all('SELECT id, code, name FROM services ORDER BY code');
        View::render('pages/admin_evaluations', [
            'title' => 'Evaluasi layanan',
            'services' => $services,
            'evaluations' => self::listEvaluations(null),
            'previousEvaluations' => self::listEvaluations(null),
            'selectedServiceId' => self::positiveInteger($values['service_id'] ?? ''),
            'filterError' => false,
            'error' => $error,
            'formValues' => $values,
        ]);
    }

    private static function listEvaluations(?int $serviceId): array
    {
        $sql = 'SELECT e.id, e.service_id, e.period_start, e.period_end, e.summary, e.findings, e.conclusion, e.corrects_evaluation_id, e.created_by, e.created_at, s.code AS service_code, s.name AS service_name, author.full_name AS author_name, (SELECT GROUP_CONCAT(correction.id ORDER BY correction.id SEPARATOR \',\') FROM madani_evaluations AS correction WHERE correction.corrects_evaluation_id = e.id) AS corrected_by_ids FROM madani_evaluations AS e INNER JOIN services AS s ON s.id = e.service_id INNER JOIN users AS author ON author.id = e.created_by';
        $params = [];
        if ($serviceId !== null) {
            $sql .= ' WHERE e.service_id = ?';
            $params[] = $serviceId;
        }
        return Db::all($sql . ' ORDER BY e.created_at DESC, e.id DESC', $params);
    }

    private static function queryId(string $key): ?int
    {
        if (!array_key_exists($key, $_GET)) {
            return null;
        }
        if (!is_string($_GET[$key])) {
            return 0;
        }
        $value = trim($_GET[$key]);
        return $value === '' ? null : (self::positiveInteger($value) ?? 0);
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

    private static function containsService(array $services, int $serviceId): bool
    {
        foreach ($services as $service) {
            if ((int) $service['id'] === $serviceId) {
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

    private static function audit(int $actorId, int $evaluationId, array $metadata, string $database): void
    {
        Auth::audit(
            $actorId,
            'ADMIN',
            'evaluation_created',
            'madani_evaluations',
            $evaluationId,
            (string) json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            $database
        );
    }

    private static function setPrivateHeaders(): void
    {
        if (!headers_sent()) {
            header('Cache-Control: no-store');
            header('Referrer-Policy: no-referrer');
        }
    }
}
