<?php

declare(strict_types=1);

namespace App\Security\RateLimit;

use RuntimeException;

final class RateLimiter
{
    private string $storageDir;

    /**
     * @param array<string, array{max: int, window: int}> $routeLimits
     */
    public function __construct(
        private readonly bool $enabled = true,
        private readonly int $defaultMaxRequests = 60,
        private readonly int $defaultWindowSeconds = 60,
        private readonly array $routeLimits = []
    ) {
        $this->storageDir = dirname(__DIR__, 3) . '/storage/rate_limit';
        if (!is_dir($this->storageDir) && !@mkdir($this->storageDir, 0775, true) && !is_dir($this->storageDir)) {
            throw new RuntimeException('No se pudo crear el directorio de rate limit.');
        }
    }

    public function hit(string $ip, string $path): RateLimitResult
    {
        $ipKey = $ip !== '' ? $ip : 'unknown';
        $limit = $this->limitFor($path);

        if (!$this->enabled) {
            return new RateLimitResult(false, $limit['max'], $limit['max'], time() + $limit['window']);
        }

        $key = $this->buildKey($ipKey, $path);
        $file = $this->storageDir . '/' . $key . '.json';

        $state = $this->readState($file);
        $now = time();

        if ($state === null || $now >= ($state['reset_at'] ?? 0)) {
            $state = [
                'count' => 0,
                'reset_at' => $now + $limit['window'],
            ];
        }

        $state['count']++;
        $isLimited = $state['count'] > $limit['max'];
        $remaining = max(0, $limit['max'] - $state['count']);

        $this->writeState($file, $state);

        return new RateLimitResult(
            $isLimited,
            $remaining,
            $limit['max'],
            (int) $state['reset_at']
        );
    }

    /**
     * @return array{max: int, window: int}
     */
    private function limitFor(string $path): array
    {
        $limit = $this->routeLimits[$path] ?? null;
        if (is_array($limit)) {
            return [
                'max' => max(1, (int) ($limit['max'] ?? $this->defaultMaxRequests)),
                'window' => max(1, (int) ($limit['window'] ?? $this->defaultWindowSeconds)),
            ];
        }

        return [
            'max' => $this->defaultMaxRequests,
            'window' => $this->defaultWindowSeconds,
        ];
    }

    private function buildKey(string $ip, string $path): string
    {
        return hash('sha256', $ip . '|' . $path);
    }

    /**
     * @return array{count: int, reset_at: int}|null
     */
    private function readState(string $file): ?array
    {
        if (!is_file($file)) {
            return null;
        }

        $raw = file_get_contents($file);
        if ($raw === false) {
            return null;
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return null;
        }

        return [
            'count' => (int) ($decoded['count'] ?? 0),
            'reset_at' => (int) ($decoded['reset_at'] ?? 0),
        ];
    }

    /**
     * @param array{count: int, reset_at: int} $state
     */
    private function writeState(string $file, array $state): void
    {
        file_put_contents(
            $file,
            json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            LOCK_EX
        );
    }
}
