<?php
declare(strict_types=1);

namespace App\Core;

use RuntimeException;

final class ApplicationTrackingLimiter
{
    public static function isLockedOut(string $ipAddress, ?string $database = null): bool
    {
        $ipAddress = trim($ipAddress);
        if ($ipAddress === '') {
            return false;
        }

        $database ??= Auth::databaseName();
        $row = Db::one(
            'SELECT 1 FROM application_tracking_attempts WHERE ip_address = ? AND attempt_type = ? AND created_at >= DATE_SUB(NOW(), INTERVAL ? SECOND) LIMIT 1',
            [$ipAddress, 'LOCKED', self::windowSeconds()],
            $database
        );
        return $row !== null;
    }

    public static function recordFailure(string $ipAddress, ?string $database = null): bool
    {
        $ipAddress = trim($ipAddress);
        if ($ipAddress === '') {
            return false;
        }

        $database ??= Auth::databaseName();
        return self::withIpLock($ipAddress, $database, static function () use ($ipAddress, $database): bool {
            $pdo = Db::pdo($database);
            $pdo->beginTransaction();
            try {
                if (self::isLockedOut($ipAddress, $database)) {
                    $pdo->commit();
                    return true;
                }

                $windowSeconds = self::windowSeconds();
                $previousFailures = (int) (Db::one(
                    'SELECT COUNT(*) AS total FROM application_tracking_attempts WHERE ip_address = ? AND attempt_type = ? AND created_at >= DATE_SUB(NOW(), INTERVAL ? SECOND)',
                    [$ipAddress, 'FAILURE', $windowSeconds],
                    $database
                )['total'] ?? 0);
                Db::run(
                    'INSERT INTO application_tracking_attempts (ip_address, attempt_type) VALUES (?, ?)',
                    [$ipAddress, 'FAILURE'],
                    $database
                );

                $locked = $previousFailures + 1 >= self::attemptLimit();
                if ($locked) {
                    Db::run(
                        'INSERT INTO application_tracking_attempts (ip_address, attempt_type) VALUES (?, ?)',
                        [$ipAddress, 'LOCKED'],
                        $database
                    );
                }
                $pdo->commit();
                return $locked;
            } catch (\Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $exception;
            }
        });
    }

    public static function withIpLock(string $ipAddress, string $database, callable $operation): mixed
    {
        $ipAddress = trim($ipAddress);
        if ($ipAddress === '') {
            throw new RuntimeException('Alamat IP tidak tersedia untuk serialisasi.');
        }

        $pdo = Db::pdo($database);
        $lockName = 'huma-track:' . substr(hash('sha256', $database . "\0" . $ipAddress), 0, 32);
        $lock = $pdo->prepare('SELECT GET_LOCK(?, 10)');
        $lock->execute([$lockName]);
        if ((int) $lock->fetchColumn() !== 1) {
            throw new RuntimeException('Tidak dapat memperoleh lock pencatatan berbasis IP.');
        }

        try {
            return $operation();
        } finally {
            $release = $pdo->prepare('SELECT RELEASE_LOCK(?)');
            $release->execute([$lockName]);
            if ((int) $release->fetchColumn() !== 1) {
                throw new RuntimeException('Tidak dapat melepas lock pencatatan berbasis IP.');
            }
        }
    }

    private static function attemptLimit(): int
    {
        return self::securityLimit('ip_attempt_limit');
    }

    private static function windowSeconds(): int
    {
        return self::securityLimit('lockout_window_seconds');
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
