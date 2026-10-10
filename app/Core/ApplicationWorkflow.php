<?php
declare(strict_types=1);

namespace App\Core;

final class ApplicationWorkflow
{
    public const INITIAL_STATUS = 'DIAJUKAN';
    public const TRANSITIONS = [
        'DIAJUKAN' => ['DIPROSES', 'DITOLAK'],
        'DIPROSES' => ['SELESAI', 'DITOLAK'],
        'SELESAI' => [],
        'DITOLAK' => [],
    ];
    public const STATUS_LABELS = [
        'DIAJUKAN' => 'Diajukan',
        'DIPROSES' => 'Diproses',
        'SELESAI' => 'Selesai',
        'DITOLAK' => 'Ditolak',
    ];
    public const STATUS_STEPS = [
        'DIAJUKAN' => 'Pengajuan permintaan',
        'DIPROSES' => 'Verifikasi dan proses layanan',
        'SELESAI' => 'Produk layanan',
        'DITOLAK' => 'Verifikasi kebutuhan',
    ];

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }
}
