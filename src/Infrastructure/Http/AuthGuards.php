<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

final class AuthGuards
{
    public static function requireAuth(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $user = self::sessionUser();
        if (empty($user['user_id'])) {
            header('Location: /login', true, 302);
            exit;
        }
    }

    public static function requireAdmin(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $user = self::sessionUser();
        $role = $user['user_role'] ?? 'user';

        if ($role !== 'admin') {
            http_response_code(403);
            echo 'Forbidden (admin only)';
            exit;
        }
    }

    /**
     * @return array{user_id?: string, user_email?: string, user_role?: string}
     */
    private static function sessionUser(): array
    {
        $auth = [];

        if (isset($_SESSION['auth']) && is_array($_SESSION['auth'])) {
            $auth = [
                'user_id' => $_SESSION['auth']['user_id'] ?? null,
                'user_email' => $_SESSION['auth']['email'] ?? null,
                'user_role' => $_SESSION['auth']['role'] ?? null,
            ];
        }

        return [
            'user_id' => $_SESSION['user_id'] ?? $auth['user_id'] ?? null,
            'user_email' => $_SESSION['user_email'] ?? $auth['user_email'] ?? null,
            'user_role' => $_SESSION['user_role'] ?? $auth['user_role'] ?? null,
        ];
    }
}
