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
        '/panel-github',
        '/panel-repo-marvel',
        '/repo-marvel',
        '/panel-accessibility',
        '/accessibility',
        '/panel-performance',
        '/performance',
        '/sonar',
        '/sentry',
        '/agentia',
    ];

    public function __construct(private readonly AuthService $authService)
    {
    }

    public function checkAdminRoute(string $path): bool
    {
        if (!$this->isProtected($path)) {
            return true;
        }

        if ($this->authService->requireAdmin()) {
            return true;
        }

        $_SESSION['redirect_to'] = $_SERVER['REQUEST_URI'] ?? $path;
        header('Location: /login', true, 302);
        return false;
    }

    private function isProtected(string $path): bool
    {
        return in_array($path, $this->protectedPaths, true);
    }
}
