<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Db;
use App\Core\View;
use App\Policies\Permissions;
use DateTimeImmutable;
use PDOException;

final class BeforeAfterController
{
    public static function index(): void
    {
        if (!self::authorized('admin.impact.view')) {
            return;
        }
        self::setPrivateHeaders();

        $services = Db::all('SELECT id, code, name FROM services ORDER BY code');
        $rtls = self::completedRtls();
        $filterRtls = self::allRtls();
        $rawServiceId = $_GET['service_id'] ?? '';
        $rawRtlId = $_GET['rtl_id'] ?? '';
        $serviceValue = is_string($rawServiceId) ? trim($rawServiceId) : '';
        $rtlValue = is_string($rawRtlId) ? trim($rawRtlId) : '';
        $serviceId = $serviceValue === '' ? null : self::positiveInteger($serviceValue);
        $rtlId = $rtlValue === '' ? null : self::positiveInteger($rtlValue);
        $filterError = !is_string($rawServiceId)
            || !is_string($rawRtlId)
            || ($serviceValue !== '' && $serviceId === null)
            || ($rtlValue !== '' && $rtlId === null);
        if ($serviceId !== null && !self::contains($services, $serviceId)) {
            $serviceId = null;
            $filterError = true;
        }
        if ($rtlId !== null && !self::contains($filterRtls, $rtlId)) {
            $rtlId = null;
            $filterError = true;
        }
        if ($filterError) {
            http_response_code(422);
        }

        View::render('pages/admin_before_after', [
            'title' => 'Catatan Before/After',
            'services' => $services,
            'rtls' => $rtls,
            'filterRtls' => $filterRtls,
            'records' => self::listRecords($serviceId, $rtlId),
            'correctableRecords' => self::listRecords(null, null),
            'selectedServiceId' => $serviceId,
            'selectedRtlId' => $rtlId,
            'filterError' => $filterError,
            'error' => '',
            'formValues' => [],
            'titleMaxLength' => 200,
            'narrativeMaxLength' => 2000,
            'sourceMaxLength' => 500,
        ]);
    }

    public static function create(): void
    {
        $actor = self::adminUser('admin.impact.create');
        if ($actor === null) {
            return;
        }
        self::setPrivateHeaders();

        $values = [
            'service_id' => self::postString('service_id'),
            'rtl_id' => self::postString('rtl_id'),
            'title' => self::postString('title', false),
            'before_text' => self::postString('before_text', false),
            'before_observed_on' => self::postString('before_observed_on'),
            'after_text' => self::postString('after_text', false),
            'after_observed_on' => self::postString('after_observed_on'),
            'source_note' => self::postString('source_note', false),
            'corrects_before_after_id' => self::postString('corrects_before_after_id'),
        ];
        $typesValid = true;
        foreach (array_keys($values) as $field) {
            $typesValid = $typesValid
                && (!array_key_exists($field, $_POST) || is_string($_POST[$field]));
        }

        $serviceId = self::positiveInteger($values['service_id']);
        $service = $serviceId === null
            ? null
            : Db::one('SELECT id FROM services WHERE id = ?', [$serviceId]);
        $rtlId = $values['rtl_id'] === '' ? null : self::positiveInteger($values['rtl_id']);
        $rtl = $rtlId === null
            ? null
            : Db::one(
                'SELECT r.id, e.service_id, latest.status AS current_status FROM madani_rtl AS r INNER JOIN madani_evaluations AS e ON e.id = r.evaluation_id INNER JOIN (SELECT rtl_id, MAX(id) AS latest_id FROM madani_rtl_status_log GROUP BY rtl_id) AS latest_log ON latest_log.rtl_id = r.id INNER JOIN madani_rtl_status_log AS latest ON latest.id = latest_log.latest_id WHERE r.id = ?',
                [$rtlId]
            );
        $correctsValue = $values['corrects_before_after_id'];
        $correctsId = $correctsValue === '' ? null : self::positiveInteger($correctsValue);
        $corrected = $correctsId === null
            ? null
            : Db::one('SELECT id, service_id FROM madani_before_after WHERE id = ?', [$correctsId]);

        $datesValid = self::datesAreValid(
            $values['before_observed_on'],
            $values['after_observed_on'],
            date('Y-m-d')
        );
        $rtlValid = $values['rtl_id'] === ''
            ? true
            : ($rtlId !== null
                && $rtl !== null
                && (int) $rtl['service_id'] === $serviceId
                && $rtl['current_status'] === 'SELESAI');
        $correctionValid = $correctsValue === ''
            ? true
            : ($correctsId !== null
                && $corrected !== null
                && (int) $corrected['service_id'] === $serviceId);
        $valid = $typesValid
            && $service !== null
            && $rtlValid
            && $datesValid
            && self::validText($values['title'], 200, true)
            && self::validText($values['before_text'], 2000, true)
            && self::validText($values['after_text'], 2000, true)
            && self::validText($values['source_note'], 500, true)
            && $correctionValid;
        if (!$valid) {
            self::renderIndexError(
                'Data before/after tidak valid. Periksa layanan, RTL, tanggal, isi teks, dan koreksi.',
                422,
                $values
            );
            return;
        }

        $database = Auth::databaseName();
        $pdo = Db::pdo($database);
        $pdo->beginTransaction();
        try {
            Db::run(
                'INSERT INTO madani_before_after (service_id, rtl_id, title, before_text, before_observed_on, after_text, after_observed_on, source_note, corrects_before_after_id, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $serviceId,
                    $rtlId,
                    trim($values['title']),
                    trim($values['before_text']),
                    $values['before_observed_on'],
                    trim($values['after_text']),
                    $values['after_observed_on'],
                    trim($values['source_note']),
                    $correctsId,
                    (int) $actor['id'],
                ],
                $database
            );
            $recordId = (int) $pdo->lastInsertId();
            self::audit(
                (int) $actor['id'],
                $recordId,
                $serviceId,
                $rtlId,
                $correctsId,
                $database
            );
            $pdo->commit();
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[HUMA] Before/after record transaction failed.');
            self::renderIndexError('Catatan before/after tidak dapat disimpan. Silakan coba lagi.', 500, $values);
            return;
        }

        if (!headers_sent()) {
            header('Location: /admin/dampak/' . $recordId, true, 303);
        }
        http_response_code(303);
    }

    public static function detail(array $params = []): void
    {
        if (!self::authorized('admin.impact.view')) {
            return;
        }
        self::setPrivateHeaders();
        $id = self::positiveInteger((string) ($params['id'] ?? ''));
        $record = $id === null ? null : self::findRecord($id);
        if ($record === null) {
            http_response_code(404);
            View::render('pages/error', ['title' => '404', 'message' => 'Catatan before/after tidak ditemukan.']);
            return;
        }
        View::render('pages/admin_before_after_detail', [
            'title' => 'Detail before/after',
            'record' => $record,
        ]);
    }

    public static function datesAreValid(string $beforeDate, string $afterDate, string $today): bool
    {
        $before = self::parseDate($beforeDate);
        $after = self::parseDate($afterDate);
        $serverDate = self::parseDate($today);
        return $before !== null
            && $after !== null
            && $serverDate !== null
            && $before <= $after
            && $after <= $serverDate;
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

    private static function renderIndexError(string $error, int $status, array $values): void
    {
        http_response_code($status);
        $services = Db::all('SELECT id, code, name FROM services ORDER BY code');
        View::render('pages/admin_before_after', [
            'title' => 'Catatan Before/After',
            'services' => $services,
            'rtls' => self::completedRtls(),
            'filterRtls' => self::allRtls(),
            'records' => self::listRecords(null, null),
            'correctableRecords' => self::listRecords(null, null),
            'selectedServiceId' => null,
            'selectedRtlId' => null,
            'filterError' => false,
            'error' => $error,
            'formValues' => $values,
            'titleMaxLength' => 200,
            'narrativeMaxLength' => 2000,
            'sourceMaxLength' => 500,
        ]);
    }

    private static function listRecords(?int $serviceId, ?int $rtlId): array
    {
        $sql = 'SELECT b.id, b.service_id, b.rtl_id, b.title, b.before_text, b.before_observed_on, b.after_text, b.after_observed_on, b.source_note, b.corrects_before_after_id, b.created_by, b.created_at, s.code AS service_code, s.name AS service_name, (SELECT GROUP_CONCAT(correction.id ORDER BY correction.id SEPARATOR \',\') FROM madani_before_after AS correction WHERE correction.corrects_before_after_id = b.id) AS corrected_by_ids FROM madani_before_after AS b INNER JOIN services AS s ON s.id = b.service_id';
        $conditions = [];
        $params = [];
        if ($serviceId !== null) {
            $conditions[] = 'b.service_id = ?';
            $params[] = $serviceId;
        }
        if ($rtlId !== null) {
            $conditions[] = 'b.rtl_id = ?';
            $params[] = $rtlId;
        }
        if ($conditions !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }
        return Db::all($sql . ' ORDER BY b.created_at DESC, b.id DESC', $params);
    }

    private static function completedRtls(): array
    {
        return Db::all(
            'SELECT r.id, e.service_id, e.period_start, e.period_end, e.corrects_evaluation_id, s.code AS service_code, latest.status AS current_status FROM madani_rtl AS r INNER JOIN madani_evaluations AS e ON e.id = r.evaluation_id INNER JOIN services AS s ON s.id = e.service_id INNER JOIN (SELECT rtl_id, MAX(id) AS latest_id FROM madani_rtl_status_log GROUP BY rtl_id) AS latest_log ON latest_log.rtl_id = r.id INNER JOIN madani_rtl_status_log AS latest ON latest.id = latest_log.latest_id WHERE latest.status = ? ORDER BY r.created_at DESC, r.id DESC',
            ['SELESAI']
        );
    }

    private static function allRtls(): array
    {
        return Db::all(
            'SELECT r.id, e.service_id, s.code AS service_code, e.period_start FROM madani_rtl AS r INNER JOIN madani_evaluations AS e ON e.id = r.evaluation_id INNER JOIN services AS s ON s.id = e.service_id ORDER BY r.created_at DESC, r.id DESC'
        );
    }

    private static function findRecord(int $id): ?array
    {
        return Db::one(
            'SELECT b.id, b.service_id, b.rtl_id, b.title, b.before_text, b.before_observed_on, b.after_text, b.after_observed_on, b.source_note, b.corrects_before_after_id, b.created_by, b.created_at, s.code AS service_code, s.name AS service_name, u.full_name AS author_name, (SELECT GROUP_CONCAT(correction.id ORDER BY correction.id SEPARATOR \',\') FROM madani_before_after AS correction WHERE correction.corrects_before_after_id = b.id) AS corrected_by_ids FROM madani_before_after AS b INNER JOIN services AS s ON s.id = b.service_id INNER JOIN users AS u ON u.id = b.created_by WHERE b.id = ?',
            [$id]
        );
    }

    private static function audit(
        int $actorId,
        int $recordId,
        int $serviceId,
        ?int $rtlId,
        ?int $correctsId,
        string $database
    ): void {
        Auth::audit(
            $actorId,
            'ADMIN',
            'before_after_created',
            'madani_before_after',
            $recordId,
            (string) json_encode([
                'service_id' => $serviceId,
                'rtl_id' => $rtlId,
                'corrects_before_after_id' => $correctsId,
            ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            $database
        );
    }

    private static function contains(array $rows, int $id): bool
    {
        foreach ($rows as $row) {
            if ((int) $row['id'] === $id) {
                return true;
            }
        }
        return false;
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
        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        return $id === false ? null : $id;
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
