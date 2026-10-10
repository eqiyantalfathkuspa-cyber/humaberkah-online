<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\ApplicationWorkflow;
use App\Core\ComplaintWorkflow;
use App\Core\Db;
use App\Core\ServiceCatalog;
use App\Core\View;
use PDOException;
use Throwable;

final class AdminController
{
    private const ALLOWED_USER_ROLES = ['SISWA', 'GURU', 'ADMIN'];

    public static function dashboard(): void
    {
        $applicationCounts = self::countsByStatus('applications', array_keys(ApplicationWorkflow::STATUS_LABELS));
        $complaintCounts = self::countsByStatus('complaints', ComplaintWorkflow::STATUSES);
        $surveyMetrics = Db::one(
            'SELECT COUNT(*) AS respondent_count, AVG(speed_rating) AS speed_average, AVG(clarity_rating) AS clarity_average, AVG(courtesy_rating) AS courtesy_average FROM surveys'
        ) ?? [];
        View::render('pages/admin_dashboard', [
            'title' => 'Dashboard Admin',
            'applicationCounts' => $applicationCounts,
            'applicationStatusLabels' => ApplicationWorkflow::STATUS_LABELS,
            'complaintCounts' => $complaintCounts,
            'complaintStatusLabels' => ComplaintWorkflow::STATUS_LABELS,
            'surveyMetrics' => $surveyMetrics,
        ]);
    }

    public static function users(): void
    {
        self::renderUsers();
    }

    public static function createUser(array $params = []): void
    {
        $identifier = trim((string) ($_POST['identifier'] ?? ''));
        $fullName = trim((string) ($_POST['full_name'] ?? ''));
        $role = strtoupper(trim((string) ($_POST['role'] ?? '')));
        $password = (string) ($_POST['password'] ?? '');

        if ($identifier === '' || $fullName === '' || !in_array($role, self::ALLOWED_USER_ROLES, true) || mb_strlen($password, 'UTF-8') < 10) {
            self::auditManagementAction('create_user_rejected', 0, 'Pembuatan pengguna ditolak karena input tidak valid');
            self::renderUsers('Data pengguna tidak valid. Kata sandi harus minimal 10 karakter.', true, 422);
            return;
        }

        $database = Auth::databaseName();
        $pdo = Db::pdo($database);
        $pdo->beginTransaction();
        try {
            Db::run(
                'INSERT INTO users (role_id, identifier, full_name, password_hash, is_active) VALUES ((SELECT id FROM roles WHERE code = ?), ?, ?, ?, 1)',
                [$role, $identifier, $fullName, password_hash($password, PASSWORD_DEFAULT)],
                $database
            );
            $userId = (int) $pdo->lastInsertId();
            self::auditManagementAction('create_user', $userId, 'Pengguna dibuat', $database);
            $pdo->commit();
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($exception->getCode() !== '23000') {
                throw $exception;
            }
            self::auditManagementAction('create_user_rejected', 0, 'Pembuatan pengguna ditolak karena identifier sudah digunakan');
            self::renderUsers('Identifier sudah digunakan untuk role tersebut.', true, 422);
            return;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }

        self::renderUsers('Pengguna berhasil dibuat.');
    }

    public static function changeRole(array $params = []): void
    {
        $userId = self::userId($params);
        $role = strtoupper(trim((string) ($_POST['role'] ?? '')));
        if ($userId === null || !in_array($role, self::ALLOWED_USER_ROLES, true)) {
            self::auditManagementAction('change_user_role_rejected', $userId ?? 0, 'Perubahan role ditolak karena input tidak valid');
            self::renderUsers('Perubahan role tidak valid.', true, 422);
            return;
        }

        $actor = Auth::user();
        $database = Auth::databaseName();
        $pdo = Db::pdo($database);
        $pdo->beginTransaction();
        try {
            $activeAdminIds = self::activeAdminIdsForUpdate($database);
            if (
                $role !== 'ADMIN'
                && in_array($userId, $activeAdminIds, true)
                && count($activeAdminIds) <= 1
            ) {
                $pdo->rollBack();
                self::auditManagementAction('change_user_role_rejected', $userId, 'Perubahan role ditolak karena harus tersisa minimal satu admin aktif');
                self::renderUsers('Operasi ditolak karena harus tersisa minimal satu admin aktif.', true, 422);
                return;
            }
            if ((int) ($actor['id'] ?? 0) === $userId && $role !== 'ADMIN') {
                $pdo->rollBack();
                self::auditManagementAction('change_user_role_rejected', $userId, 'Admin tidak dapat menurunkan role dirinya sendiri');
                self::renderUsers('Admin tidak dapat menurunkan role dirinya sendiri.', true, 422);
                return;
            }

            $updated = Db::run(
                'UPDATE users SET role_id = (SELECT id FROM roles WHERE code = ?) WHERE id = ?',
                [$role, $userId],
                $database
            );
            if ($updated === 0 && Db::one('SELECT id FROM users WHERE id = ?', [$userId], $database) === null) {
                $pdo->rollBack();
                self::auditManagementAction('change_user_role_rejected', $userId, 'Perubahan role ditolak karena pengguna tidak ditemukan');
                self::renderUsers('Pengguna tidak ditemukan.', true, 404);
                return;
            }
            self::auditManagementAction('change_user_role', $userId, 'Role pengguna diubah menjadi ' . $role, $database);
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }

        self::renderUsers('Role pengguna berhasil diubah.');
    }

    public static function deactivateUser(array $params = []): void
    {
        $userId = self::userId($params);
        if ($userId === null) {
            self::auditManagementAction('deactivate_user_rejected', 0, 'Penonaktifan ditolak karena identifier pengguna tidak valid');
            self::renderUsers('Identifier pengguna tidak valid.', true, 422);
            return;
        }

        $actor = Auth::user();
        $database = Auth::databaseName();
        $pdo = Db::pdo($database);
        $pdo->beginTransaction();
        try {
            $activeAdminIds = self::activeAdminIdsForUpdate($database);
            if (in_array($userId, $activeAdminIds, true) && count($activeAdminIds) <= 1) {
                $pdo->rollBack();
                self::auditManagementAction('deactivate_user_rejected', $userId, 'Penonaktifan ditolak karena harus tersisa minimal satu admin aktif');
                self::renderUsers('Operasi ditolak karena harus tersisa minimal satu admin aktif.', true, 422);
                return;
            }
            if ((int) ($actor['id'] ?? 0) === $userId) {
                $pdo->rollBack();
                self::auditManagementAction('deactivate_user_rejected', $userId, 'Admin tidak dapat menonaktifkan dirinya sendiri');
                self::renderUsers('Admin tidak dapat menonaktifkan dirinya sendiri.', true, 422);
                return;
            }

            $updated = Db::run('UPDATE users SET is_active = 0 WHERE id = ?', [$userId], $database);
            if ($updated === 0 && Db::one('SELECT id FROM users WHERE id = ?', [$userId], $database) === null) {
                $pdo->rollBack();
                self::auditManagementAction('deactivate_user_rejected', $userId, 'Permintaan ditolak karena pengguna tidak ditemukan');
                self::renderUsers('Pengguna tidak ditemukan.', true, 404);
                return;
            }
            self::auditManagementAction('deactivate_user', $userId, 'Pengguna dinonaktifkan', $database);
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }

        self::renderUsers('Pengguna dinonaktifkan.');
    }

    public static function resetPassword(array $params = []): void
    {
        $userId = self::userId($params);
        $password = (string) ($_POST['password'] ?? '');
        if ($userId === null || mb_strlen($password, 'UTF-8') < 10) {
            self::auditManagementAction('reset_user_password_rejected', $userId ?? 0, 'Reset sandi ditolak karena input tidak valid');
            self::renderUsers('Identifier pengguna tidak valid atau sandi kurang dari 10 karakter.', true, 422);
            return;
        }

        $database = Auth::databaseName();
        $pdo = Db::pdo($database);
        $pdo->beginTransaction();
        try {
            $updated = Db::run(
                'UPDATE users SET password_hash = ?, auth_token = NULL WHERE id = ?',
                [password_hash($password, PASSWORD_DEFAULT), $userId],
                $database
            );
            if ($updated === 0 && Db::one('SELECT id FROM users WHERE id = ?', [$userId], $database) === null) {
                $pdo->rollBack();
                self::auditManagementAction('reset_user_password_rejected', $userId, 'Reset sandi ditolak karena pengguna tidak ditemukan');
                self::renderUsers('Pengguna tidak ditemukan.', true, 404);
                return;
            }
            self::auditManagementAction('reset_user_password', $userId, 'Sandi pengguna direset', $database);
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }

        self::renderUsers('Sandi pengguna berhasil direset.');
    }

    private static function activeAdminIdsForUpdate(string $database): array
    {
        $rows = Db::all(
            'SELECT u.id FROM users u INNER JOIN roles r ON r.id = u.role_id WHERE r.code = ? AND u.is_active = 1 FOR UPDATE',
            ['ADMIN'],
            $database
        );
        return array_map(static fn(array $row): int => (int) $row['id'], $rows);
    }

    private static function userId(array $params): ?int
    {
        $id = filter_var($params['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        return $id === false ? null : $id;
    }

    private static function auditManagementAction(string $action, int $entityId, string $message, ?string $database = null): void
    {
        $actor = Auth::user();
        Auth::audit(
            (int) ($actor['id'] ?? 0),
            strtoupper((string) ($actor['role_code'] ?? $actor['role'] ?? 'GUEST')),
            $action,
            'users',
            $entityId > 0 ? $entityId : null,
            $message,
            $database
        );
    }

    /**
     * @param array<int, string> $statuses
     * @return array<string, int>
     */
    private static function countsByStatus(string $table, array $statuses): array
    {
        if (!in_array($table, ['applications', 'complaints'], true)) {
            throw new \InvalidArgumentException('Tabel status dashboard tidak dikenal.');
        }
        $counts = array_fill_keys($statuses, 0);
        foreach (Db::all('SELECT status, COUNT(*) AS row_count FROM ' . $table . ' GROUP BY status') as $row) {
            $status = (string) $row['status'];
            if (array_key_exists($status, $counts)) {
                $counts[$status] = (int) $row['row_count'];
            }
        }
        return $counts;
    }

    private static function renderUsers(string $message = '', bool $error = false, int $status = 200): void
    {
        http_response_code($status);
        $users = Db::all(
            'SELECT u.id, u.identifier, u.full_name, u.is_active, r.code AS role_code, r.name AS role_name FROM users u INNER JOIN roles r ON r.id = u.role_id ORDER BY u.id DESC'
        );
        View::render('pages/admin_users', [
            'title' => 'Pengguna',
            'users' => $users,
            'message' => $message,
            'messageIsError' => $error,
        ]);
    }

    public static function audit(): void
    {
        $auditLogs = Db::all(
            'SELECT id, actor_id, actor_role, action, entity_type, entity_id, message, created_at FROM audit_logs ORDER BY id DESC LIMIT 200'
        );
        View::render('pages/admin_audit', ['title' => 'Audit Log', 'auditLogs' => $auditLogs]);
    }

    public static function services(): void
    {
        $services = Db::all(
            'SELECT s.id, s.code, s.name, s.mode, c.name AS category_name, ss.fee_text, ss.sla_text, ss.responsible_unit, ss.required_documents_text FROM services s INNER JOIN service_categories c ON c.id = s.category_id LEFT JOIN service_standards ss ON ss.service_id = s.id ORDER BY c.id, s.code'
        );
        foreach ($services as &$service) {
            $service['completed_standards'] = 0;
            foreach (['fee_text', 'sla_text', 'responsible_unit', 'required_documents_text'] as $field) {
                if (trim((string) ($service[$field] ?? '')) !== '') {
                    $service['completed_standards']++;
                }
            }
        }
        unset($service);

        View::render('pages/admin_services', [
            'title' => 'Kelola Layanan',
            'services' => $services,
            'modeLabels' => ServiceCatalog::MODE_LABELS,
        ]);
    }

    public static function editService(array $params = []): void
    {
        $service = self::serviceByCode((string) ($params['code'] ?? ''));
        if ($service === null) {
            self::serviceNotFound();
            return;
        }

        View::render('pages/admin_service_edit', [
            'title' => 'Ubah Layanan ' . $service['code'],
            'service' => $service,
            'modeLabels' => ServiceCatalog::MODE_LABELS,
            'message' => '',
            'isError' => false,
        ]);
    }

    public static function updateService(array $params = []): void
    {
        $serviceCode = strtoupper(trim((string) ($params['code'] ?? '')));
        $mode = self::postText('mode');
        $fields = [
            'fee_text' => ['fee_text', 500],
            'sla_text' => ['sla_text', 500],
            'responsible_unit' => ['responsible_unit', 500],
            'required_documents_text' => ['required_documents_text', 4000],
        ];
        $values = [];
        $invalid = $mode === null || !in_array($mode, ServiceCatalog::MODES, true);
        foreach ($fields as $column => [$inputName, $maximumLength]) {
            $value = self::postText($inputName);
            if ($value === null || mb_strlen($value, 'UTF-8') > $maximumLength) {
                $invalid = true;
            } else {
                $trimmed = trim($value);
                $values[$column] = $trimmed === '' ? null : $trimmed;
            }
        }

        $service = self::serviceByCode($serviceCode);
        if ($service === null) {
            self::serviceNotFound();
            return;
        }

        $modeAllowed = $serviceCode === 'HUM-02'
            ? $mode === 'MODUL_PENGADUAN'
            : $mode !== 'MODUL_PENGADUAN';
        if ($invalid || !$modeAllowed) {
            self::renderServiceEdit($serviceCode, 'Mode atau teks standar tidak valid.', true, 422);
            return;
        }

        $database = Auth::databaseName();
        $pdo = Db::pdo($database);
        $pdo->beginTransaction();
        try {
            $oldService = Db::one(
                'SELECT s.id, s.code, s.mode, ss.fee_text, ss.sla_text, ss.responsible_unit, ss.required_documents_text FROM services s LEFT JOIN service_standards ss ON ss.service_id = s.id WHERE s.code = ? FOR UPDATE',
                [$serviceCode],
                $database
            );
            if ($oldService === null) {
                $pdo->rollBack();
                self::serviceNotFound();
                return;
            }
            $oldValues = [
                'mode' => $oldService['mode'],
                'fee_text' => $oldService['fee_text'],
                'sla_text' => $oldService['sla_text'],
                'responsible_unit' => $oldService['responsible_unit'],
                'required_documents_text' => $oldService['required_documents_text'],
            ];
            Db::run('UPDATE services SET mode = ? WHERE id = ?', [$mode, (int) $oldService['id']], $database);
            Db::run(
                'INSERT INTO service_standards (service_id, fee_text, sla_text, responsible_unit, required_documents_text, updated_by) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE fee_text = VALUES(fee_text), sla_text = VALUES(sla_text), responsible_unit = VALUES(responsible_unit), required_documents_text = VALUES(required_documents_text), updated_by = VALUES(updated_by)',
                [(int) $oldService['id'], $values['fee_text'], $values['sla_text'], $values['responsible_unit'], $values['required_documents_text'], (int) (Auth::user()['id'] ?? 0)],
                $database
            );
            $actor = Auth::user();
            $newValues = ['mode' => $mode] + $values;
            Auth::audit(
                (int) ($actor['id'] ?? 0),
                strtoupper((string) ($actor['role_code'] ?? 'ADMIN')),
                'update_service',
                'services',
                (int) $oldService['id'],
                (string) json_encode(['old' => $oldValues, 'new' => $newValues], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                $database
            );
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }

        self::renderServiceEdit($serviceCode, 'Layanan berhasil diperbarui.', false, 200);
    }

    private static function serviceByCode(string $code): ?array
    {
        return Db::one(
            'SELECT s.id, s.code, s.name, s.mode, c.name AS category_name, ss.fee_text, ss.sla_text, ss.responsible_unit, ss.required_documents_text FROM services s INNER JOIN service_categories c ON c.id = s.category_id LEFT JOIN service_standards ss ON ss.service_id = s.id WHERE s.code = ?',
            [strtoupper(trim($code))]
        );
    }

    private static function postText(string $key): ?string
    {
        return isset($_POST[$key]) && is_string($_POST[$key]) ? $_POST[$key] : null;
    }

    private static function serviceNotFound(): void
    {
        http_response_code(404);
        View::render('pages/error', ['title' => '404', 'message' => 'Layanan tidak ditemukan.']);
    }

    private static function renderServiceEdit(string $code, string $message, bool $isError, int $status = 200): void
    {
        http_response_code($status);
        $service = self::serviceByCode($code);
        if ($service === null) {
            self::serviceNotFound();
            return;
        }
        View::render('pages/admin_service_edit', [
            'title' => 'Ubah Layanan ' . $service['code'],
            'service' => $service,
            'modeLabels' => ServiceCatalog::MODE_LABELS,
            'message' => $message,
            'isError' => $isError,
        ]);
    }
}
