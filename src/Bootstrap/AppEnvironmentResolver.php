<?php

declare(strict_types=1);

namespace App\Bootstrap;

use App\Config\ServiceUrlProvider;

final class AppEnvironmentResolver
{
    public static function resolve(?string $declaredEnv, ?ServiceUrlProvider $provider, ?string $host = null): string
    {
        $env = strtolower(trim((string) $declaredEnv));

        if ($env === '') {
            $env = 'auto';
        }

        if ($env === 'test') {
            return 'test';
        }

        if ($env !== 'auto') {
            return $env;
        }

        if ($provider instanceof ServiceUrlProvider) {
            return $provider->resolveEnvironment($host);
        }

        return 'local';
    }
}

