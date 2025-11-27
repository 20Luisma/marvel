<?php

declare(strict_types=1);

namespace App\Security\Auth;

final class AuthService
{
    private const ADMIN_EMAIL = 'seguridadmarvel@gmail.com';
    private const ADMIN_ID = 'marvel-admin';
    private const ADMIN_ROLE = 'admin';
    private const PASSWORD_HASH = '$2y$12$I9Z9uy.ksfLKelJO/Ov8.unFdMtI0ZyehDNVu3x3ULC5PeWGxG4My'; // hash de "seguridadmarvel2025"
    private const SESSION_TTL_SECONDS = 1800; // 30 minutos

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
            'last_activity' => time(),
        ];

        return true;
    }

    public function logout(): void
    {
        $this->ensureSession();

        unset($_SESSION['auth'], $_SESSION['intended_path'], $_SESSION['redirect_to']);
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

        if (($user['user_id'] ?? null) !== self::ADMIN_ID || ($user['role'] ?? null) !== self::ADMIN_ROLE) {
            return false;
        }

        $lastActivity = isset($user['last_activity']) ? (int)$user['last_activity'] : 0;
        if ($lastActivity > 0 && (time() - $lastActivity) > self::SESSION_TTL_SECONDS) {
            $this->logout();
            return false;
        }

        $_SESSION['auth']['last_activity'] = time();

        return true;
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
