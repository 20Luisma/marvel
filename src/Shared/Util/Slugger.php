<?php

declare(strict_types=1);

namespace App\Shared\Util;

final class Slugger
{
    public static function slugify(string $value): string
    {
        $value = trim($value);
        $value = strtolower($value);
        $value = preg_replace('~[^a-z0-9]+~', '-', $value) ?? '';
        $value = trim($value, '-');

        return $value !== '' ? $value : bin2hex(random_bytes(8));
    }
}
