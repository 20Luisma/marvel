<?php

declare(strict_types=1);

namespace App\Security\Auth;

use App\Config\SecurityConfig;
use App\Security\Logging\SecurityLogger;
use App\Security\Session\SessionReplayMonitor;

final class AuthService
{
    private const ADMIN_ID = 'marvel-admin';
    private const ADMIN_ROLE = 'admin';
    private const SESSION_TTL_SECONDS = 1800; // 30 minutos inactividad
    private const SESSION_MAX_LIFETIME = 28800; // 8 horas

    private SecurityConfig $config;
    private ?SecurityLogger $logger;
    private ?SessionReplayMonitor $replayMonitor;

    public function __construct(
        ?SecurityConfig $config = null,
        ?SecurityLogger $logger = null,
        ?SessionReplayMonitor $replayMonitor = null
    ) {
        $this->config = $config ?? new SecurityConfig();
        $this->logger = $logger;
        $this->replayMonitor = $replayMonitor;
    }

    public function login(string $email, string $password): bool
    {
        $normalizedEmail = strtolower(trim($email));
        $adminEmail = strtolower($this->config->getAdminEmail());
        $passwordHash = $this->config->getAdminPasswordHash();

        if ($normalizedEmail !== $adminEmail) {
            $this->logout();
            return false;
        }

        if (!password_verify($password, $passwordHash)) {
            $this->logout();
            return false;
        }

        $this->ensureSession();
        // Regeneramos la sesión tras autenticación para evitar session fixation.
        session_regenerate_id(true);
        $_SESSION['session_created_at'] = time();

        $_SESSION['user_id'] = self::ADMIN_ID;
        $_SESSION['user_email'] = $this->config->getAdminEmail();
        $_SESSION['user_role'] = self::ADMIN_ROLE;
        $_SESSION['session_ip_hash'] = $this->hashIp($this->clientIp());
        $_SESSION['session_ua_hash'] = $this->hashUserAgent($this->userAgent());
        if ($this->replayMonitor instanceof SessionReplayMonitor) {
            $this->replayMonitor->initReplayToken();
        }
        $_SESSION['auth'] = [
            'user_id' => self::ADMIN_ID,
            'role' => self::ADMIN_ROLE,
            'email' => $this->config->getAdminEmail(),
            'last_activity' => time(),
        ];

        // FASE 7.4 — Rotar token Anti-Replay después de login exitoso (soft mode)
        $_SESSION['session_replay_token'] = bin2hex(random_bytes(32));
        $traceId = $_SERVER['X_TRACE_ID'] ?? '-';
        $ip = $this->clientIp();
        $ua = $this->userAgent();
        $path = $_SERVER['REQUEST_URI'] ?? 'unknown';
        $securityLogPath = dirname(__DIR__, 3) . '/storage/logs/security.log';
        if (!is_dir(dirname($securityLogPath))) {
            @mkdir(dirname($securityLogPath), 0775, true);
        }
        error_log(
            "[" . date('Y-m-d H:i:s') . "] event=session_replay_rotated_soft trace_id={$traceId} ip={$ip} path={$path} user_agent={$ua} timestamp=" . time() . "\n",
            3,
            $securityLogPath
        );

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

        $now = time();
        $lastActivity = isset($user['last_activity']) ? (int)$user['last_activity'] : 0;
        if ($lastActivity > 0 && ($now - $lastActivity) > self::SESSION_TTL_SECONDS) {
            $this->logEvent('session_expired_ttl');
            $this->invalidateSession();
            return false;
        }

        $createdAt = isset($_SESSION['session_created_at']) ? (int) $_SESSION['session_created_at'] : 0;
        if ($createdAt > 0 && ($now - $createdAt) > self::SESSION_MAX_LIFETIME) {
            $this->logEvent('session_expired_lifetime');
            $this->invalidateSession();
            return false;
        }

        $ipHash = $_SESSION['session_ip_hash'] ?? null;
        $uaHash = $_SESSION['session_ua_hash'] ?? null;
        if (!is_string($ipHash) || $ipHash !== $this->hashIp($this->clientIp())
            || !is_string($uaHash) || $uaHash !== $this->hashUserAgent($this->userAgent())) {
            $this->logEvent('session_hijack_detected');
            $this->invalidateSession();
            return false;
        }

        $_SESSION['auth']['last_activity'] = $now;

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

    public function enforceSessionSecurity(): void
    {
        $this->ensureSession();

        if (!isset($_SESSION['auth']) || !is_array($_SESSION['auth'])) {
            return;
        }

        // isAuthenticated ya actualizará last_activity si es válida.
        $this->isAuthenticated();
    }

    private function ensureSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    private function invalidateSession(): void
    {
        $this->logout();
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        session_regenerate_id(true);
    }

    private function clientIp(): string
    {
        $remote = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        return is_string($remote) && $remote !== '' ? $remote : 'unknown';
    }

    private function userAgent(): string
    {
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $ua = is_string($ua) ? $ua : '';
        $ua = preg_replace('/[\x00-\x1F\x7F]/', '', $ua) ?? '';
        return substr($ua, 0, 200);
    }

    private function hashIp(string $ip): string
    {
        return hash('sha256', $ip);
    }

    private function hashUserAgent(string $ua): string
    {
        return hash('sha256', $ua);
    }

    private function logEvent(string $event): void
    {
        if ($this->logger === null) {
            return;
        }

        $this->logger->logEvent($event, [
            'trace_id' => $_SERVER['X_TRACE_ID'] ?? null,
            'ip' => $this->clientIp(),
            'user_agent' => $this->userAgent(),
            'path' => $_SERVER['REQUEST_URI'] ?? 'unknown',
            'timestamp' => time(),
        ]);
    }
}
