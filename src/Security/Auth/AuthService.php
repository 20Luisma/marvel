<?php

declare(strict_types=1);

namespace App\Security\Auth;

final class AuthService
{
    private const ADMIN_EMAIL = 'marvel@gmail.com';
    private const ADMIN_ID = 'marvel-admin';
    private const ADMIN_ROLE = 'admin';
    private const PASSWORD_HASH = '$2y$12$A.080AUUXuXqTrK/AkUjZ.ivvYUceZB3Zn.TLKYNmiNj96hlAK8tC'; // hash de "marvel2025"

    public function login(string $email, string $password): bool
    {
        $normalizedEmail = strtolower(trim($email));

        if ($normalizedEmail !== self::ADMIN_EMAIL) {
            $this->logout();
            return false;
        }

        if (!password_verify($password, self::PASSWORD_HASH)) {
            $this->logout();
            return false;
        }

        $this->ensureSession();
        session_regenerate_id(true);

        $_SESSION['auth'] = [
            'user_id' => self::ADMIN_ID,
            'role' => self::ADMIN_ROLE,
            'email' => self::ADMIN_EMAIL,
        ];

        return true;
    }

    public function logout(): void
    {
        $this->ensureSession();

        unset($_SESSION['auth'], $_SESSION['intended_path']);
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $params['path'] ?? '/',
                'domain' => $params['domain'] ?? '',
                'secure' => (bool)($params['secure'] ?? false),
                'httponly' => true,
                'samesite' => $params['samesite'] ?? 'Lax',
            ]);
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    public function isAuthenticated(): bool
    {
        $this->ensureSession();

        if (!isset($_SESSION['auth']) || !is_array($_SESSION['auth'])) {
            return false;
        }

        $user = $_SESSION['auth'];

        return ($user['user_id'] ?? null) === self::ADMIN_ID
            && ($user['role'] ?? null) === self::ADMIN_ROLE;
    }

    public function requireAdmin(): bool
    {
        return $this->isAuthenticated();
    }

    private function ensureSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
}
