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
        return '$2y$12$I9Z9uy.ksfLKelJO/Ov8.unFdMtI0ZyehDNVu3x3ULC5PeWGxG4My';
    }

    public function getInternalApiKey(): ?string
    {
        $key = getenv('INTERNAL_API_KEY') ?: null;

        return $key !== null && $key !== '' ? $key : null;
    }
}
