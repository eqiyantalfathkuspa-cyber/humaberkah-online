<?php
declare(strict_types=1);

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function angka_atau_kosong(?int $value): string
{
    return $value === null ? 'BELUM ADA DATA' : (string) $value;
}
