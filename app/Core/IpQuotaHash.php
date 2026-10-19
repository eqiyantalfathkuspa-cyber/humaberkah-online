<?php
declare(strict_types=1);

namespace App\Core;

use RuntimeException;

final class IpQuotaHash
{
    public static function forAddress(string $address): string
    {
        $environmentKey = getenv('HUMA_IP_HASH_KEY');
        $security = Config::get('security', []);
        $configuredKey = is_array($security) ? ($security['ip_hash_key'] ?? null) : null;
        $key = $environmentKey !== false
            ? $environmentKey
            : (Config::activeDatabaseKey() === 'test' ? null : $configuredKey);

        return self::withKey($address, $key);
    }

    public static function withKey(string $address, mixed $key): string
    {
        if (!is_string($key) || strlen($key) < 32) {
            throw new RuntimeException('Permintaan tidak dapat diproses.');
        }

        return hash_hmac('sha256', $address, $key);
    }
}