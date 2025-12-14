<?php

declare(strict_types=1);

namespace App\Config;

final class SecurityConfig
{
    public function getAdminEmail(): string
    {
        $envEmail = getenv('ADMIN_EMAIL') ?: null;

        return $envEmail !== null
            ? $envEmail
            : 'seguridadmarvel@gmail.com';
    }

    public function getAdminPasswordHash(): string
    {
        $envHash = getenv('ADMIN_PASSWORD_HASH') ?: ($_ENV['ADMIN_PASSWORD_HASH'] ?? null);

        if (is_string($envHash) && trim($envHash) !== '') {
            return $envHash;
        }

        // En producción, exigir configuración explícita
        $appEnv = getenv('APP_ENV') ?: ($_ENV['APP_ENV'] ?? 'local');
        if ($appEnv === 'prod' || $appEnv === 'production') {
            throw new \RuntimeException(
                'ADMIN_PASSWORD_HASH must be configured in production. ' .
                'Generate with: php -r "echo password_hash(\'your-password\', PASSWORD_BCRYPT, [\'cost\' => 12]);"'
            );
        }

        // Fallback SOLO para entornos local/test (nunca en producción)
        return password_hash('seguridadmarvel2025', PASSWORD_BCRYPT, ['cost' => 12]);
    }

    public function getInternalApiKey(): ?string
    {
        $key = getenv('INTERNAL_API_KEY') ?: null;

        return $key !== null ? $key : null;
    }
}
