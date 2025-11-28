<?php

declare(strict_types=1);

namespace App\Security\Session;

use App\Security\Logging\SecurityLogger;

final class SessionReplayMonitor
{
    private const TOKEN_KEY = 'security_replay_token';
    private const SID_KEY = 'security_replay_sid';
    private const UA_KEY = 'security_replay_user_agent';

    public function __construct(private readonly ?SecurityLogger $logger = null)
    {
    }

    public function initReplayToken(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $_SESSION[self::TOKEN_KEY] = bin2hex(random_bytes(32));
        $_SESSION[self::SID_KEY] = session_id();
        $_SESSION[self::UA_KEY] = $this->userAgent();

        $this->log('session_replay_token_issued', ['token_set' => '1']);
    }

    public function getToken(): ?string
    {
        if (session_status() === PHP_SESSION_NONE) {
            return null;
        }

        $token = $_SESSION[self::TOKEN_KEY] ?? null;
        return is_string($token) && $token !== '' ? $token : null;
    }

    public function detectReplayAttack(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            return;
        }

        $storedSid = $_SESSION[self::SID_KEY] ?? null;
        $storedUa = $_SESSION[self::UA_KEY] ?? null;

        if (is_string($storedSid) && $storedSid !== '' && $storedSid !== session_id()) {
            $this->log('session_replay_suspected', ['cause' => 'session_id_changed']);
        }

        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if (strtoupper((string) $method) === 'POST' && is_string($storedUa) && $storedUa !== '') {
            $currentUa = $this->userAgent();
            if ($currentUa !== '' && $currentUa !== $storedUa) {
                $this->log('session_replay_suspected', ['cause' => 'user_agent_mismatch']);
            }
        }
    }

    private function userAgent(): string
    {
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $ua = is_string($ua) ? $ua : '';
        $ua = preg_replace('/[\x00-\x1F\x7F]/', '', $ua) ?? '';
        return substr($ua, 0, 200);
    }

    /**
     * @param array<string, scalar|null> $context
     */
    private function log(string $event, array $context = []): void
    {
        if ($this->logger === null) {
            return;
        }

        $this->logger->logEvent($event, $context + [
            'trace_id' => $_SERVER['X_TRACE_ID'] ?? null,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $this->userAgent(),
            'path' => $_SERVER['REQUEST_URI'] ?? 'unknown',
            'timestamp' => time(),
        ]);
    }
}
