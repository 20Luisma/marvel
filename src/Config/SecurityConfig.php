<?php

declare(strict_types=1);

namespace App\Config;

final class SecurityConfig
{
    public function getAdminEmail(): string
    {
        $envEmail = getenv('ADMIN_EMAIL') ?: null;

        return $envEmail !== null && $envEmail !== ''
            ? $envEmail
            : 'seguridadmarvel@gmail.com';
    }

    public function getAdminPasswordHash(): string
    {
        $envHash = getenv('ADMIN_PASSWORD_HASH') ?: ($_ENV['ADMIN_PASSWORD_HASH'] ?? null);

        if (is_string($envHash) && trim($envHash) !== '') {
            return $envHash;
        }

        // Fallback solo para entornos de desarrollo/testing; configura ADMIN_PASSWORD_HASH en producción.
        return password_hash('seguridadmarvel2025', PASSWORD_BCRYPT, ['cost' => 12]);
    }

    public function getInternalApiKey(): ?string
    {
        $key = getenv('INTERNAL_API_KEY') ?: null;

        return $key !== null && $key !== '' ? $key : null;
    }
}
