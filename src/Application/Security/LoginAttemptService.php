<?php

declare(strict_types=1);

namespace App\Application\Security;

use App\Security\Logging\SecurityLogger;

final class LoginAttemptService
{
    private const STORAGE_FILE = '/storage/security/login_attempts.json';
    private const MAX_ATTEMPTS = 5;
    private const WINDOW_SECONDS = 900; // 15 minutos
    private const BLOCK_SECONDS = 900;  // 15 minutos

    private string $filePath;

    public function __construct(private readonly ?SecurityLogger $logger = null)
    {
        $root = dirname(__DIR__, 3); // proyecto raíz
        $this->filePath = $root . self::STORAGE_FILE;
        $dir = dirname($this->filePath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
    }

    public function isBlocked(string $email, string $ip): bool
    {
        $this->ensureSession();
        $this->cleanOld($email, $ip);

        $data = $this->read();
        $key = $this->key($email, $ip);
        $entry = $data[$key] ?? null;

        if (!is_array($entry)) {
            return false;
        }

        $blockedUntil = $entry['blocked_until'] ?? 0;

        return time() < (int) $blockedUntil;
    }

    public function registerFailedAttempt(string $email, string $ip): void
    {
        $this->ensureSession();
        $this->cleanOld($email, $ip);

        $data = $this->read();
        $key = $this->key($email, $ip);

        $entry = $data[$key] ?? ['attempts' => [], 'blocked_until' => 0];
        $attempts = $entry['attempts'] ?? [];
        $attempts[] = time();

        $entry['attempts'] = $attempts;

        if (count($attempts) > self::MAX_ATTEMPTS) {
            $entry['blocked_until'] = time() + self::BLOCK_SECONDS;
            $this->log('login_blocked', $email, $ip, [
                'retry_after_minutes' => $this->getBlockMinutesRemaining($email, $ip),
            ]);
        } else {
            $remaining = self::MAX_ATTEMPTS - count($attempts);
            $this->log('login_failed', $email, $ip, ['remaining_attempts' => $remaining]);
        }

        $data[$key] = $entry;
        $this->write($data);
    }

    public function clearAttempts(string $email, string $ip): void
    {
        $data = $this->read();
        $key = $this->key($email, $ip);
        unset($data[$key]);
        $this->write($data);
        $this->log('login_success', $email, $ip);
    }

    public function getRemainingAttempts(string $email, string $ip): int
    {
        $this->cleanOld($email, $ip);
        $data = $this->read();
        $key = $this->key($email, $ip);
        $entry = $data[$key] ?? null;

        $count = is_array($entry) ? count($entry['attempts'] ?? []) : 0;

        return max(0, self::MAX_ATTEMPTS - $count);
    }

    public function getBlockMinutesRemaining(string $email, string $ip): int
    {
        $data = $this->read();
        $key = $this->key($email, $ip);
        $entry = $data[$key] ?? null;
        if (!is_array($entry)) {
            return 0;
        }

        $blockedUntil = (int) ($entry['blocked_until'] ?? 0);
        $remaining = $blockedUntil - time();

        return $remaining > 0 ? (int) ceil($remaining / 60) : 0;
    }

    private function key(string $email, string $ip): string
    {
        return hash('sha256', strtolower(trim($email)) . '|' . $ip);
    }

    /**
     * @return array<string, array{attempts: array<int>, blocked_until: int}>
     */
    private function read(): array
    {
        if (!is_file($this->filePath)) {
            return [];
        }

        $raw = file_get_contents($this->filePath);
        if ($raw === false) {
            return [];
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string, array{attempts: array<int>, blocked_until: int}> $data
     */
    private function write(array $data): void
    {
        file_put_contents(
            $this->filePath,
            json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            LOCK_EX
        );
    }

    private function cleanOld(string $email, string $ip): void
    {
        $data = $this->read();
        $key = $this->key($email, $ip);
        $entry = $data[$key] ?? null;

        if (!is_array($entry)) {
            return;
        }

        $cutoff = time() - self::WINDOW_SECONDS;
        $attempts = array_filter(
            $entry['attempts'] ?? [],
            static fn($ts): bool => (int) $ts >= $cutoff
        );
        $entry['attempts'] = array_values($attempts);

        if (count($attempts) <= self::MAX_ATTEMPTS) {
            $entry['blocked_until'] = 0;
        }

        $data[$key] = $entry;
        $this->write($data);
    }

    /**
     * @param array<string, scalar|null> $context
     */
    private function log(string $event, string $email, string $ip, array $context = []): void
    {
        if ($this->logger === null) {
            return;
        }

        $this->logger->logEvent($event, $context + [
            'email' => $email,
            'ip' => $ip,
        ]);
    }

    private function ensureSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
}
