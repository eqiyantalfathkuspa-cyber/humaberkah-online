<?php
declare(strict_types=1);

namespace App\Core;

final class IdGen
{
    public static function next(string $prefix, string $database = 'test'): string
    {
        $pdo = Db::pdo($database);
        $year = (int) date('Y');
        $name = $prefix . '-' . $year;
        $isOwnTransaction = !$pdo->inTransaction();

        if ($isOwnTransaction) {
            $pdo->beginTransaction();
        }

        try {
            Db::run(
                'INSERT INTO id_counters (name, current_value) VALUES (?, 0) ON DUPLICATE KEY UPDATE name = name',
                [$name],
                $database
            );

            $row = Db::one('SELECT current_value FROM id_counters WHERE name = ? FOR UPDATE', [$name], $database);
            $next = ((int) $row['current_value']) + 1;
            Db::run('UPDATE id_counters SET current_value = ? WHERE name = ?', [$next, $name], $database);

            if ($isOwnTransaction) {
                $pdo->commit();
            }

            return sprintf('%s-%04d', $name, $next);
        } catch (\Throwable $e) {
            if ($isOwnTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}
