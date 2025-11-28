<?php

declare(strict_types=1);

namespace App\Security\Http;

use App\Security\Auth\AuthService;

final class AuthMiddleware
{
    /**
     * @var string[]
     */
    private array $protectedPaths = [
        '/seccion',
        '/secret-heatmap',
        '/secret/heatmap',
        '/secret/sonar',
        '/secret/sentry',
        '/secret',
        '/secret/',
        '/panel-github',
        '/panel-repo-marvel',
        '/repo-marvel',
        '/panel-accessibility',
        '/accessibility',
        '/panel-performance',
        '/performance',
        '/sonar',
        '/sentry',
        '/admin',
        '/admin/',
        '/admin/seed-all',
        '/agentia',
    ];

    /**
     * Prefijos que requieren rol admin (ej. /secret/*).
     *
     * @var string[]
     */
    private array $protectedPrefixes = ['/secret/'];

    public function __construct(private readonly AuthService $authService)
    {
    }

    public function checkAdminRoute(string $path): bool
    {
        if (!$this->isProtected($path)) {
            return true;
        }

        if (!$this->authService->requireAuth()) {
            $_SESSION['redirect_to'] = $_SERVER['REQUEST_URI'] ?? $path;
            header('Location: /login', true, 302);
            return false;
        }

        if ($this->authService->requireAdmin()) {
            return true;
        }

        http_response_code(403);
        echo 'Acceso restringido.';

        return false;
    }

    private function isProtected(string $path): bool
    {
        if (in_array($path, $this->protectedPaths, true)) {
            return true;
        }

        foreach ($this->protectedPrefixes as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
