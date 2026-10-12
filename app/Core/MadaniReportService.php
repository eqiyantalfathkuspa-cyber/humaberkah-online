<?php
declare(strict_types=1);

namespace App\Core;

use DateTimeImmutable;
use InvalidArgumentException;

final class MadaniReportService
{
    private const EVIDENCE_STATUSES = [
        'DIUNGGAH' => 'Diunggah',
        'TERVERIFIKASI' => 'Terverifikasi',
        'PERLU_PERBAIKAN' => 'Perlu perbaikan',
    ];

    private const CHECKLIST_STATUSES = [
        'VALID' => 'Valid',
        'ADA_BELUM_VERIFIKASI' => 'Ada, belum diverifikasi',
        'PERLU_PERBAIKAN' => 'Perlu perbaikan',
        'KOSONG' => 'Kosong',
    ];

    /**
     * @return array{period_start:?string,period_end:?string,service_id:?int}
     */
    public static function validateFilters(array $input): array
    {
        $start = self::optionalString($input['period_start'] ?? '');
        $end = self::optionalString($input['period_end'] ?? '');
        if (($start === '') !== ($end === '')) {
            throw new InvalidArgumentException('Periode tidak valid.');
        }

        $periodStart = null;
        $periodEnd = null;
        if ($start !== '') {
            $startDate = self::parseDate($start);
            $endDate = self::parseDate($end);
            if ($endDate < $startDate || (int) $startDate->diff($endDate)->format('%a') + 1 > 366) {
                throw new InvalidArgumentException('Periode tidak valid.');
            }
            $periodStart = $startDate->format('Y-m-d');
            $periodEnd = $endDate->format('Y-m-d');
        }

        $serviceValue = self::optionalString($input['service_id'] ?? '');
        $serviceId = null;
        if ($serviceValue !== '') {
            $serviceId = self::positiveInteger($serviceValue);
            if ($serviceId === null || Db::one('SELECT id FROM services WHERE id = ?', [$serviceId]) === null) {
                throw new InvalidArgumentException('Filter tidak valid.');
            }
        }

        return [
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'service_id' => $serviceId,
        ];
    }

    /**
     * @param array{period_start:?string,period_end:?string,service_id:?int} $filters
     * @return array<string,mixed>
     */
    public static function report(array $filters): array
    {
        $serviceWhere = $filters['service_id'] === null ? '' : ' WHERE s.id = ?';
        $serviceParams = $filters['service_id'] === null ? [] : [$filters['service_id']];
        $services = Db::all(
            'SELECT s.id, s.code, s.name,
                    COALESCE(ev.total, 0) AS evaluation_count,
                    COALESCE(ba.total, 0) AS before_after_count
             FROM services AS s
             LEFT JOIN (
                 SELECT service_id, COUNT(*) AS total
                 FROM madani_evaluations
                 WHERE (? IS NULL OR (period_start <= ? AND period_end >= ?))
                 GROUP BY service_id
             ) AS ev ON ev.service_id = s.id
             LEFT JOIN (
                 SELECT service_id, COUNT(*) AS total
                 FROM madani_before_after
                 WHERE (? IS NULL OR after_observed_on BETWEEN ? AND ?)
                 GROUP BY service_id
             ) AS ba ON ba.service_id = s.id'
                . $serviceWhere . '
             ORDER BY s.code',
            [
                $filters['period_end'],
                $filters['period_end'],
                $filters['period_start'],
                $filters['period_start'],
                $filters['period_start'],
                $filters['period_end'],
                ...$serviceParams,
            ]
        );

        $rtlParams = [];
        $rtlWhere = self::createdAtWhere('r.created_at', $filters, $rtlParams);
        if ($filters['service_id'] !== null) {
            $rtlWhere .= ' AND e.service_id = ?';
            $rtlParams[] = $filters['service_id'];
        }
        $rtlRows = Db::all(
            'SELECT latest.status, COUNT(*) AS total,
                    SUM(CASE WHEN latest.status <> \'SELESAI\' AND r.due_date < ? THEN 1 ELSE 0 END) AS overdue
             FROM madani_rtl AS r
             JOIN madani_evaluations AS e ON e.id = r.evaluation_id
             JOIN madani_rtl_status_log AS latest
               ON latest.rtl_id = r.id
              AND latest.id = (SELECT MAX(newest.id) FROM madani_rtl_status_log AS newest WHERE newest.rtl_id = r.id)'
                . $rtlWhere . '
             GROUP BY latest.status',
            [date('Y-m-d'), ...$rtlParams]
        );
        $rtlStatuses = array_fill_keys(['DIRENCANAKAN', 'BERJALAN', 'SELESAI'], 0);
        $rtlOverdue = 0;
        foreach ($rtlRows as $row) {
            $rtlStatuses[(string) $row['status']] = (int) $row['total'];
            $rtlOverdue += (int) $row['overdue'];
        }

        $evidenceParams = [];
        $evidenceWhere = '';
        if ($filters['period_start'] !== null) {
            $evidenceWhere = ' WHERE created_at >= ? AND created_at < ?';
            $evidenceParams = self::periodBounds($filters);
        }
        if ($filters['service_id'] !== null) {
            $evidenceWhere .= ($evidenceWhere === '' ? ' WHERE ' : ' AND ') . 'service_id = ?';
            $evidenceParams[] = $filters['service_id'];
        }
        $evidenceCounts = self::groupedCounts(
            'SELECT status, COUNT(*) AS total FROM evidence_items' . $evidenceWhere . ' GROUP BY status',
            $evidenceParams,
            self::EVIDENCE_STATUSES
        );

        $cellWhere = $filters['service_id'] === null ? '' : ' WHERE s.id = ?';
        $checklistRows = Db::all(
            'SELECT cell_status, COUNT(*) AS total
             FROM (
                 SELECT CASE
                            WHEN COALESCE(SUM(e.status = \'TERVERIFIKASI\'), 0) > 0 THEN \'VALID\'
                            WHEN COALESCE(SUM(e.status = \'DIUNGGAH\'), 0) > 0 THEN \'ADA_BELUM_VERIFIKASI\'
                            WHEN COALESCE(SUM(e.status = \'PERLU_PERBAIKAN\'), 0) > 0 THEN \'PERLU_PERBAIKAN\'
                            ELSE \'KOSONG\'
                        END AS cell_status
                 FROM services AS s
                 CROSS JOIN evidence_checkpoints AS c
                 LEFT JOIN evidence_items AS e
                   ON e.service_id = s.id AND e.checkpoint_id = c.id
                 ' . $cellWhere . '
                 GROUP BY s.id, c.id
             ) AS cells
             GROUP BY cell_status',
            $serviceParams
        );
        $checklistCounts = array_fill_keys(array_keys(self::CHECKLIST_STATUSES), 0);
        foreach ($checklistRows as $row) {
            $checklistCounts[(string) $row['cell_status']] = (int) $row['total'];
        }

        $applicationParams = [];
        $applicationWhere = self::createdAtWhere('a.created_at', $filters, $applicationParams);
        if ($filters['service_id'] !== null) {
            $applicationWhere .= ' AND a.service_id = ?';
            $applicationParams[] = $filters['service_id'];
        }
        $applicationCounts = self::groupedCounts(
            'SELECT a.status, COUNT(*) AS total FROM applications AS a' . $applicationWhere . ' GROUP BY a.status',
            $applicationParams,
            ApplicationWorkflow::STATUS_LABELS
        );

        $complaintParams = [];
        $complaintWhere = self::createdAtWhere('created_at', $filters, $complaintParams);
        $complaintCounts = self::groupedCounts(
            'SELECT status, COUNT(*) AS total FROM complaints' . $complaintWhere . ' GROUP BY status',
            $complaintParams,
            ComplaintWorkflow::STATUS_LABELS
        );

        return [
            'filters' => $filters,
            'services' => $services,
            'rtl_statuses' => $rtlStatuses,
            'rtl_overdue' => $rtlOverdue,
            'evidence_statuses' => $evidenceCounts,
            'checklist_statuses' => $checklistCounts,
            'application_statuses' => $applicationCounts,
            'complaint_statuses' => $complaintCounts,
            'evidence_labels' => self::EVIDENCE_STATUSES,
            'checklist_labels' => self::CHECKLIST_STATUSES,
            'application_labels' => ApplicationWorkflow::STATUS_LABELS,
            'complaint_labels' => ComplaintWorkflow::STATUS_LABELS,
        ];
    }

    /**
     * @param array<string,mixed> $report
     */
    public static function csv(array $report): string
    {
        $stream = fopen('php://temp', 'w+');
        if ($stream === false) {
            throw new \RuntimeException('CSV sementara tidak dapat dibuat.');
        }
        fputcsv($stream, ['metrik', 'kode_layanan', 'nama_layanan', 'status', 'jumlah', 'tanggal_mulai', 'tanggal_akhir']);

        foreach ($report['services'] as $service) {
            self::writeCsvRow($stream, 'evaluasi_per_layanan', $service['code'], $service['name'], '', $service['evaluation_count'], $report['filters']);
            self::writeCsvRow($stream, 'before_after_per_layanan', $service['code'], $service['name'], '', $service['before_after_count'], $report['filters']);
        }
        foreach ($report['rtl_statuses'] as $status => $total) {
            self::writeCsvRow($stream, 'rtl_per_status', '', '', $status, $total, $report['filters']);
        }
        self::writeCsvRow($stream, 'rtl_lewat_tenggat', '', '', '', $report['rtl_overdue'], $report['filters']);
        self::writeStatusRows($stream, 'eviden_per_status', $report['evidence_statuses'], $report['filters']);
        self::writeStatusRows($stream, 'checklist_per_status', $report['checklist_statuses'], $report['filters']);
        self::writeStatusRows($stream, 'pengajuan_per_status', $report['application_statuses'], $report['filters']);
        self::writeStatusRows($stream, 'aduan_per_status', $report['complaint_statuses'], $report['filters']);
        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);
        if (!is_string($csv)) {
            throw new \RuntimeException('CSV sementara tidak dapat dibaca.');
        }
        return $csv;
    }

    /**
     * @param array<string,int> $counts
     * @param array{period_start:?string,period_end:?string,service_id:?int} $filters
     */
    private static function writeStatusRows($stream, string $metric, array $counts, array $filters): void
    {
        foreach ($counts as $status => $total) {
            self::writeCsvRow($stream, $metric, '', '', $status, $total, $filters);
        }
    }

    /**
     * @param array{period_start:?string,period_end:?string,service_id:?int} $filters
     */
    private static function writeCsvRow($stream, string $metric, string $code, string $name, string $status, int|string $total, array $filters): void
    {
        fputcsv($stream, array_map(static fn (string $cell): string => self::csvCell($cell), [
            $metric,
            $code,
            $name,
            $status,
            (string) $total,
            $filters['period_start'] ?? '',
            $filters['period_end'] ?? '',
        ]));
    }

    private static function csvCell(string $value): string
    {
        return preg_match('/^[=+\-@]/', $value) === 1 ? "'" . $value : $value;
    }

    /**
     * @param array<string,string> $labels
     * @return array<string,int>
     */
    private static function groupedCounts(string $sql, array $params, array $labels): array
    {
        $counts = array_fill_keys(array_keys($labels), 0);
        foreach (Db::all($sql, $params) as $row) {
            $counts[(string) $row['status']] = (int) $row['total'];
        }
        return $counts;
    }

    /**
     * @param array{period_start:?string,period_end:?string,service_id:?int} $filters
     * @param array<int,mixed> $params
     * @return string
     */
    private static function createdAtWhere(string $column, array $filters, array &$params): string
    {
        if ($filters['period_start'] === null) {
            return $filters['service_id'] === null ? '' : ' WHERE 1 = 1';
        }
        $params = self::periodBounds($filters);
        return ' WHERE ' . $column . ' >= ? AND ' . $column . ' < ?';
    }

    /**
     * @param array{period_start:?string,period_end:?string,service_id:?int} $filters
     * @return array{0:string,1:string}
     */
    private static function periodBounds(array $filters): array
    {
        $endExclusive = (new DateTimeImmutable((string) $filters['period_end']))
            ->modify('+1 day')
            ->format('Y-m-d 00:00:00');
        return [
            (string) $filters['period_start'] . ' 00:00:00',
            $endExclusive,
        ];
    }

    private static function parseDate(string $value): DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = DateTimeImmutable::getLastErrors();
        if (
            $date === false
            || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
            || $date->format('Y-m-d') !== $value
        ) {
            throw new InvalidArgumentException('Periode tidak valid.');
        }
        return $date;
    }

    private static function positiveInteger(string $value): ?int
    {
        if ($value === '' || preg_match('/^[1-9][0-9]*$/', $value) !== 1) {
            return null;
        }
        $max = (string) PHP_INT_MAX;
        if (strlen($value) > strlen($max) || (strlen($value) === strlen($max) && strcmp($value, $max) > 0)) {
            return null;
        }
        return (int) $value;
    }

    private static function optionalString(mixed $value): string
    {
        if (!is_string($value)) {
            throw new InvalidArgumentException('Filter tidak valid.');
        }
        return trim($value);
    }
}
