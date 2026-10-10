<?php
declare(strict_types=1);

namespace App\Core;

final class ComplaintWorkflow
{
    public const INITIAL_STATUS = 'DITERIMA';
    public const STATUSES = ['DITERIMA', 'DIKLASIFIKASI', 'DITINDAKLANJUTI', 'SELESAI', 'DITOLAK'];
    public const TYPES = ['PENGADUAN', 'ASPIRASI', 'SARAN', 'PERTANYAAN'];
    public const TRANSITIONS = [
        'DITERIMA' => ['DIKLASIFIKASI', 'DITOLAK'],
        'DIKLASIFIKASI' => ['DITINDAKLANJUTI', 'DITOLAK'],
        'DITINDAKLANJUTI' => ['SELESAI'],
        'SELESAI' => [],
        'DITOLAK' => [],
    ];
    public const STATUS_LABELS = [
        'DITERIMA' => 'Diterima',
        'DIKLASIFIKASI' => 'Diklasifikasi',
        'DITINDAKLANJUTI' => 'Ditindaklanjuti',
        'SELESAI' => 'Selesai',
        'DITOLAK' => 'Ditolak',
    ];
    public const STATUS_STEPS = [
        'DITERIMA' => 'Verifikasi laporan',
        'DIKLASIFIKASI' => 'Verifikasi laporan',
        'DITINDAKLANJUTI' => 'Proses penanganan',
        'SELESAI' => 'Produk tindak lanjut',
        'DITOLAK' => 'Verifikasi laporan',
    ];

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }
}
