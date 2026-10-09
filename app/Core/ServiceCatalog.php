<?php
declare(strict_types=1);

namespace App\Core;

final class ServiceCatalog
{
    public const MODES = ['FORMULIR', 'INFORMASI', 'TATAP_MUKA', 'MODUL_PENGADUAN'];
    public const MODE_LABELS = [
        'FORMULIR' => 'Ajukan lewat website',
        'INFORMASI' => 'Informasi langsung di website',
        'TATAP_MUKA' => 'Tatap muka di sekolah',
        'MODUL_PENGADUAN' => 'Modul pengaduan',
    ];
    public const INFORMATION_STEPS_NOTICE = 'Tidak berlaku, informasi dapat diakses langsung';

    public static function modeLabel(string $mode): string
    {
        return self::MODE_LABELS[$mode] ?? $mode;
    }
}
