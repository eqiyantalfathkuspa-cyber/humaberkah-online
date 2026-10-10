<?php
declare(strict_types=1);

namespace App\Core;

final class TrackingCode
{
    private const ALPHABET = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';

    public static function generate(): string
    {
        $bytes = random_bytes(16);
        $characters = '';
        for ($index = 0; $index < 16; $index++) {
            $characters .= self::ALPHABET[ord($bytes[$index]) & 31];
        }

        return 'HMA-' . implode('-', str_split($characters, 4));
    }

    public static function isValid(string $code): bool
    {
        return preg_match('/^HMA-[2-9A-HJ-NP-Z]{4}(?:-[2-9A-HJ-NP-Z]{4}){3}$/D', $code) === 1;
    }
}
