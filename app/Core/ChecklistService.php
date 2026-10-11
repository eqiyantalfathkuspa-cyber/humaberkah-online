<?php
declare(strict_types=1);

namespace App\Core;

final class ChecklistService
{
    /**
     * @return array{
     *   services: array<int, array{id:int,code:string,name:string,is_active:bool,cells:array<int,array<string,int|string>>}>,
     *   checkpoints: array<int,array{id:int,code:string,name:string}>,
     *   summary: array<string,int>,
     *   cell_count: int
     * }
     */
    public static function matrix(): array
    {
        $rows = Db::all(
            "SELECT s.id AS service_id, s.code AS service_code, s.name AS service_name, s.is_active,
                    c.id AS checkpoint_id, c.code AS checkpoint_code, c.name AS checkpoint_name,
                    COUNT(e.id) AS evidence_count,
                    COALESCE(SUM(CASE WHEN e.status = 'TERVERIFIKASI' THEN 1 ELSE 0 END), 0) AS verified_count,
                    COALESCE(SUM(CASE WHEN e.status = 'DIUNGGAH' THEN 1 ELSE 0 END), 0) AS uploaded_count,
                    COALESCE(SUM(CASE WHEN e.status = 'PERLU_PERBAIKAN' THEN 1 ELSE 0 END), 0) AS needs_repair_count
             FROM services AS s
             CROSS JOIN evidence_checkpoints AS c
             LEFT JOIN evidence_items AS e
               ON e.service_id = s.id AND e.checkpoint_id = c.id
             GROUP BY s.id, s.code, s.name, s.is_active, c.id, c.code, c.name
             ORDER BY s.code, c.code"
        );

        $services = [];
        $checkpoints = [];
        $summary = [
            'VALID' => 0,
            'ADA_BELUM_VERIFIKASI' => 0,
            'PERLU_PERBAIKAN' => 0,
            'KOSONG' => 0,
        ];

        foreach ($rows as $row) {
            $serviceId = (int) $row['service_id'];
            $checkpointId = (int) $row['checkpoint_id'];
            $checkpoints[$checkpointId] = [
                'id' => $checkpointId,
                'code' => (string) $row['checkpoint_code'],
                'name' => (string) $row['checkpoint_name'],
            ];

            if (!isset($services[$serviceId])) {
                $services[$serviceId] = [
                    'id' => $serviceId,
                    'code' => (string) $row['service_code'],
                    'name' => (string) $row['service_name'],
                    'is_active' => (bool) $row['is_active'],
                    'cells' => [],
                ];
            }

            $verifiedCount = (int) $row['verified_count'];
            $uploadedCount = (int) $row['uploaded_count'];
            $needsRepairCount = (int) $row['needs_repair_count'];
            $status = self::statusFromCounts($verifiedCount, $uploadedCount, $needsRepairCount);
            $services[$serviceId]['cells'][$checkpointId] = [
                'checkpoint_id' => $checkpointId,
                'status' => $status,
                'evidence_count' => (int) $row['evidence_count'],
                'verified_count' => $verifiedCount,
                'uploaded_count' => $uploadedCount,
                'needs_repair_count' => $needsRepairCount,
            ];
            $summary[$status]++;
        }

        return [
            'services' => array_values($services),
            'checkpoints' => array_values($checkpoints),
            'summary' => $summary,
            'cell_count' => count($rows),
        ];
    }

    public static function statusFromCounts(int $verified, int $uploaded, int $needsRepair): string
    {
        if ($verified > 0) {
            return 'VALID';
        }
        if ($uploaded > 0) {
            return 'ADA_BELUM_VERIFIKASI';
        }
        if ($needsRepair > 0) {
            return 'PERLU_PERBAIKAN';
        }
        return 'KOSONG';
    }
}
