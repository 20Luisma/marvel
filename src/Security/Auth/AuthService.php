<?php

declare(strict_types=1);

namespace App\Security\Auth;

final class AuthService
{
    private const ADMIN_EMAIL = 'seguridadmarvel@gmail.com';
    private const ADMIN_ID = 'marvel-admin';
    private const ADMIN_ROLE = 'admin';
    private const PASSWORD_HASH = '$2y$12$I9Z9uy.ksfLKelJO/Ov8.unFdMtI0ZyehDNVu3x3ULC5PeWGxG4My'; // hash de "seguridadmarvel2025"
    private const SESSION_TTL_SECONDS = 1800; // 30 minutos inactividad
    private const SESSION_MAX_LIFETIME = 28800; // 8 horas

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
        // Regeneramos la sesión tras autenticación para evitar session fixation.
        session_regenerate_id(true);
        $_SESSION['session_created_at'] = time();

        $_SESSION['user_id'] = self::ADMIN_ID;
        $_SESSION['user_email'] = self::ADMIN_EMAIL;
        $_SESSION['user_role'] = self::ADMIN_ROLE;
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

        unset(
            $_SESSION['auth'],
            $_SESSION['intended_path'],
            $_SESSION['redirect_to'],
            $_SESSION['user_id'],
            $_SESSION['user_email'],
            $_SESSION['user_role']
        );
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

        $userId = $user['user_id'] ?? null;
        $role = $user['role'] ?? null;

        if (!is_string($userId) || trim($userId) === '' || !is_string($role) || trim($role) === '') {
            return false;
        }

        $lastActivity = isset($user['last_activity']) ? (int)$user['last_activity'] : 0;
        if ($lastActivity > 0 && (time() - $lastActivity) > self::SESSION_TTL_SECONDS) {
            $this->logout();
            return false;
        }

        $createdAt = isset($_SESSION['session_created_at']) ? (int) $_SESSION['session_created_at'] : 0;
        if ($createdAt > 0 && (time() - $createdAt) > self::SESSION_MAX_LIFETIME) {
            $this->logout();
            return false;
        }

        $_SESSION['auth']['last_activity'] = time();

        return true;
    }

    public function isAdmin(): bool
    {
        if (!$this->isAuthenticated()) {
            return false;
        }

        $user = $_SESSION['auth'] ?? [];

        return ($user['user_id'] ?? null) === self::ADMIN_ID
            && ($user['role'] ?? null) === self::ADMIN_ROLE;
    }

    public function requireAuth(): bool
    {
        return $this->isAuthenticated();
    }

    public function requireAdmin(): bool
    {
        return $this->isAdmin();
    }

    private function ensureSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
}
