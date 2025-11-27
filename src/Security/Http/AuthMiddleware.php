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
        '/secret-heatmap',
        '/panel-github',
        '/panel-repo-marvel',
        '/panel-accessibility',
        '/panel-performance',
        '/performance',
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

        $_SESSION['intended_path'] = $path;
        header('Location: /login', true, 302);
        return false;
    }

    private function isProtected(string $path): bool
    {
        return in_array($path, $this->protectedPaths, true);
    }
}
