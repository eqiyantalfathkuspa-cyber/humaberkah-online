<?php
declare(strict_types=1);

namespace App\Core;

use Throwable;

final class QuotaCleaner
{
    public static function cleanExpired(string $database = 'default'): array
    {
        $pdo = Db::pdo($database);
        $deleted = [
            'complaint_ip_quotas' => 0,
            'survey_ip_quotas' => 0,
            'information_survey_ip_quotas' => 0,
        ];

        $pdo->beginTransaction();
        try {
            $deleted['complaint_ip_quotas'] = Db::run(
                'DELETE FROM complaint_ip_quotas WHERE window_started_at <= DATE_SUB(NOW(), INTERVAL 1 HOUR)',
                [],
                $database
            );
            $deleted['survey_ip_quotas'] = Db::run(
                'DELETE FROM survey_ip_quotas WHERE window_started_at <= DATE_SUB(NOW(), INTERVAL 1 HOUR)',
                [],
                $database
            );
            $deleted['information_survey_ip_quotas'] = Db::run(
                'DELETE FROM information_survey_ip_quotas WHERE window_started_at <= DATE_SUB(NOW(), INTERVAL 1 HOUR)',
                [],
                $database
            );
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }

        return $deleted;
    }

    public static function maybeClean(string $database = 'default', ?int $sample = null): bool
    {
        $sample ??= random_int(1, 1000);
        if ($sample !== 1) {
            return false;
        }

        try {
            self::cleanExpired($database);
            return true;
        } catch (Throwable) {
            return false;
        }
    }
}