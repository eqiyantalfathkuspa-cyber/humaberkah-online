<?php
declare(strict_types=1);

namespace App\Core;

use App\Policies\Permissions;
use RuntimeException;

final class Auth
{
    private const LOGIN_FAILURE_MESSAGE = 'Identitas atau kata sandi salah, atau akun terkunci sementara. Periksa kembali data masuk Anda.';
    public const DEFAULT_SESSION_LIFETIME_SECONDS = 2 * 60 * 60;

    public static function databaseName(): string
    {
        return Config::activeDatabaseName();
    }

    public static function sessionLifetimeSeconds(): int
    {
        return self::DEFAULT_SESSION_LIFETIME_SECONDS;
    }

    public static function user(): ?array
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $user = $_SESSION['user'] ?? null;
        if (!is_array($user)) {
            return null;
        }

        $currentUser = Db::one(
            'SELECT u.is_active, u.auth_token, r.code AS role_code FROM users u INNER JOIN roles r ON r.id = u.role_id WHERE u.id = ?',
            [(int) ($user['id'] ?? 0)],
            self::databaseName()
        );
        if (
            $currentUser === null
            || (int) $currentUser['is_active'] !== 1
            || !isset($user['auth_token'], $currentUser['auth_token'])
            || !hash_equals((string) $currentUser['auth_token'], (string) $user['auth_token'])
            || strtoupper((string) $currentUser['role_code']) !== strtoupper((string) ($user['role_code'] ?? $user['role'] ?? ''))
        ) {
            self::logout();
            return null;
        }

        $lastActivity = isset($_SESSION['last_activity']) ? (int) $_SESSION['last_activity'] : time();
        if (time() - $lastActivity > self::sessionLifetimeSeconds()) {
            self::logout();
            return null;
        }

        $_SESSION['last_activity'] = time();
        return $user;
    }

    public static function login(array $user): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        if (PHP_SAPI !== 'cli' && headers_sent()) {
            throw new RuntimeException('Login dibatalkan: header sudah dikirim sebelum session_regenerate_id().');
        }

        session_regenerate_id(true);
        $_SESSION['user'] = $user;
        $_SESSION['last_activity'] = time();
        $_SESSION['login_time'] = time();

        $params = session_get_cookie_params();
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? '') === '443');
        setcookie(
            session_name(),
            session_id(),
            [
                'expires' => time() + self::sessionLifetimeSeconds(),
                'path' => $params['path'] ?: '/',
                'domain' => $params['domain'] ?? '',
                'secure' => $secure,
                'httponly' => true,
                'samesite' => 'Lax',
            ]
        );
    }

    public static function logout(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $user = $_SESSION['user'] ?? null;
        if (is_array($user)) {
            self::audit(
                (int) ($user['id'] ?? 0),
                strtoupper((string) ($user['role_code'] ?? $user['role'] ?? 'GUEST')),
                'logout',
                'session',
                (int) ($user['id'] ?? 0),
                'Logout berhasil',
                self::databaseName()
            );
        }

        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'] ?: '/',
                $params['domain'] ?? '',
                (bool) ($params['secure'] ?? false),
                true
            );
        }
        session_destroy();
    }

    public static function guard(array $roles, ?string $ability = null): bool
    {
        $user = self::user();
        if ($user === null) {
            return false;
        }

        $roleCode = strtoupper((string) ($user['role_code'] ?? $user['role'] ?? ''));
        $allowedByRole = in_array($roleCode, $roles, true);
        $allowedByAbility = $ability === null || Permissions::can($roleCode, $ability);

        if (!$allowedByRole || !$allowedByAbility) {
            self::audit((int) ($user['id'] ?? 0), $roleCode, 'access_denied', 'route', 0, $ability ?: 'unknown', self::databaseName());
            http_response_code(403);
            return false;
        }

        return true;
    }

    public static function processLogin(string $identifier, string $password, ?string $roleCode = null): array
    {
        $identifier = trim((string) $identifier);
        $password = (string) $password;
        $roleCode = $roleCode === null ? null : strtoupper(trim((string) $roleCode));
        $database = self::databaseName();
        $ip = self::clientIp();

        if ($identifier === '' || $password === '') {
            self::recordFailure($identifier, null, $ip, $database, 'Form login tidak lengkap');
            return ['ok' => false, 'message' => self::LOGIN_FAILURE_MESSAGE];
        }

        if (self::isLockedOut($identifier, $ip, $database)) {
            return ['ok' => false, 'message' => self::LOGIN_FAILURE_MESSAGE];
        }

        $user = self::findUserByIdentifier($identifier, $roleCode, $database);
        if ($user === null) {
            self::recordFailure($identifier, null, $ip, $database, 'Identitas tidak terdaftar');
            self::audit(0, 'GUEST', 'login_failed', 'users', 0, 'Identitas tidak terdaftar', $database);
            return ['ok' => false, 'message' => self::LOGIN_FAILURE_MESSAGE];
        }

        if ((int) ($user['is_active'] ?? 0) !== 1) {
            self::recordFailure($identifier, (int) $user['id'], $ip, $database, 'Akun nonaktif');
            self::audit((int) $user['id'], (string) ($user['role_code'] ?? 'GUEST'), 'login_failed', 'users', (int) $user['id'], 'Akun nonaktif', $database);
            return ['ok' => false, 'message' => self::LOGIN_FAILURE_MESSAGE];
        }

        if (!password_verify($password, (string) ($user['password_hash'] ?? ''))) {
            self::recordFailure($identifier, (int) $user['id'], $ip, $database);
            self::audit((int) $user['id'], (string) ($user['role_code'] ?? 'GUEST'), 'login_failed', 'users', (int) $user['id'], 'Kata sandi salah', $database);
            return ['ok' => false, 'message' => self::LOGIN_FAILURE_MESSAGE];
        }

        $authToken = bin2hex(random_bytes(32));
        self::recordAttempt($identifier, (int) $user['id'], 'SUCCESS', $ip, 'Login berhasil', $database);
        Db::run('UPDATE users SET last_login_at = NOW(), auth_token = ? WHERE id = ?', [$authToken, (int) $user['id']], $database);
        self::audit((int) $user['id'], (string) ($user['role_code'] ?? 'GUEST'), 'login_success', 'users', (int) $user['id'], 'Login berhasil', $database);

        try {
            self::login([
                'id' => (int) $user['id'],
                'role' => (string) ($user['role_code'] ?? 'GUEST'),
                'role_code' => (string) ($user['role_code'] ?? 'GUEST'),
                'identifier' => $identifier,
                'full_name' => (string) ($user['full_name'] ?? ''),
                'auth_token' => $authToken,
            ]);
        } catch (RuntimeException $exception) {
            self::audit((int) $user['id'], (string) ($user['role_code'] ?? 'GUEST'), 'login_failed', 'users', (int) $user['id'], 'Header sudah terkirim; login ditolak', $database);
            return ['ok' => false, 'message' => self::LOGIN_FAILURE_MESSAGE];
        }

        return ['ok' => true, 'user' => $_SESSION['user'] ?? null];
    }

    public static function findUserByIdentifier(string $identifier, ?string $roleCode = null, ?string $database = null): ?array
    {
        $database ??= self::databaseName();
        $sql = 'SELECT u.*, r.code AS role_code FROM users u INNER JOIN roles r ON r.id = u.role_id WHERE u.identifier = ?';
        $params = [$identifier];
        if ($roleCode !== null) {
            $roles = match (strtoupper($roleCode)) {
                'SISWA_GURU' => ['SISWA', 'GURU'],
                'ADMIN' => ['ADMIN'],
                'SISWA', 'GURU' => [strtoupper($roleCode)],
                default => [],
            };
            if ($roles === []) {
                return null;
            }
            $sql .= ' AND r.code IN (' . implode(', ', array_fill(0, count($roles), '?')) . ')';
            array_push($params, ...$roles);
        }
        $sql .= ' ORDER BY u.id DESC LIMIT 1';
        return Db::one($sql, $params, $database);
    }

    public static function isLockedOut(string $identifier, ?string $ipAddress = null, ?string $database = null): bool
    {
        $database ??= self::databaseName();
        $lockWindowSeconds = self::securityLimit('lockout_window_seconds');
        $where = '(identifier = :identifier AND user_id IS NOT NULL)';
        $params = ['seconds' => $lockWindowSeconds, 'identifier' => $identifier];
        if ($ipAddress !== null && trim($ipAddress) !== '') {
            $where .= ' OR (ip_address = :ipAddress AND user_id IS NULL)';
            $params['ipAddress'] = trim($ipAddress);
        }

        $row = Db::one(
            'SELECT 1 FROM login_attempts WHERE (' . $where . ') AND created_at >= DATE_SUB(NOW(), INTERVAL :seconds SECOND) AND attempt_type = "LOCKED" LIMIT 1',
            $params,
            $database
        );
        if ($row !== null) {
            return true;
        }

        return false;
    }

    public static function recordFailure(
        string $identifier,
        ?int $userId,
        ?string $ipAddress = null,
        ?string $database = null,
        string $note = 'Kata sandi salah'
    ): void
    {
        $database ??= self::databaseName();
        if (self::isLockedOut($identifier, $ipAddress, $database)) {
            return;
        }
        $accountLimit = self::securityLimit('login_attempt_limit');
        $ipLimit = self::securityLimit('ip_attempt_limit');
        $lockWindowSeconds = self::securityLimit('lockout_window_seconds');
        $existingAccountFailures = $userId === null ? 0 : (int) (Db::one(
            'SELECT COUNT(*) AS total FROM login_attempts WHERE user_id = :userId AND created_at >= DATE_SUB(NOW(), INTERVAL :seconds SECOND) AND attempt_type = "FAILURE"',
            ['userId' => $userId, 'seconds' => $lockWindowSeconds],
            $database
        )['total'] ?? 0);
        $normalizedIp = $ipAddress !== null && trim($ipAddress) !== '' ? trim($ipAddress) : null;

        self::recordAttempt($identifier, $userId, 'FAILURE', $normalizedIp, $note, $database);
        if ($userId !== null && $existingAccountFailures + 1 >= $accountLimit) {
            self::recordAttempt($identifier, $userId, 'LOCKED', $normalizedIp, 'Akun dikunci setelah ' . $accountLimit . ' kegagalan', $database);
            self::audit($userId, 'USER', 'account_locked', 'users', $userId, 'Akun dikunci karena ' . $accountLimit . ' kegagalan login', $database);
        }

        if ($normalizedIp !== null) {
            $existingIpFailures = (int) (Db::one(
                'SELECT COUNT(*) AS total FROM login_attempts WHERE ip_address = :ipAddress AND created_at >= DATE_SUB(NOW(), INTERVAL :seconds SECOND) AND attempt_type = "FAILURE"',
                ['ipAddress' => $normalizedIp, 'seconds' => $lockWindowSeconds],
                $database
            )['total'] ?? 0);
            if ($existingIpFailures >= $ipLimit) {
                self::recordAttempt('', null, 'LOCKED', $normalizedIp, 'IP dikunci setelah ' . $ipLimit . ' kegagalan', $database);
                self::audit(null, 'SYSTEM', 'ip_locked', 'ip_address', null, 'Alamat IP dikunci setelah ambang kegagalan login', $database);
            }
        }
    }

    public static function recordAttempt(string $identifier, ?int $userId, string $type, ?string $ipAddress, ?string $note = null, ?string $database = null): void
    {
        $database ??= self::databaseName();
        $ipAddress = $ipAddress !== null && trim($ipAddress) !== '' ? trim($ipAddress) : null;
        Db::run(
            'INSERT INTO login_attempts (user_id, identifier, ip_address, attempt_type, note) VALUES (?, ?, ?, ?, ?)',
            [$userId, $identifier, $ipAddress, $type, $note],
            $database
        );
    }

    public static function audit(?int $actorId, string $actorRole, string $action, ?string $entityType = null, ?int $entityId = null, ?string $message = null, ?string $database = null): void
    {
        $database ??= self::databaseName();
        Db::run(
            'INSERT INTO audit_logs (actor_id, actor_role, action, entity_type, entity_id, message) VALUES (?, ?, ?, ?, ?, ?)',
            [$actorId ?: null, $actorRole ?: 'GUEST', $action, $entityType, $entityId, $message],
            $database
        );
    }

    public static function clientIp(): string
    {
        $remote = $_SERVER['REMOTE_ADDR'] ?? '';
        if (is_string($remote) && trim($remote) !== '') {
            return trim($remote);
        }

        return '';
    }

    private static function securityLimit(string $key): int
    {
        $security = Config::get('security', []);
        $value = is_array($security) ? ($security[$key] ?? null) : null;
        if (!is_int($value) || $value < 1) {
            throw new RuntimeException('Konfigurasi security.' . $key . ' harus berupa bilangan bulat positif.');
        }

        return $value;
    }
}
