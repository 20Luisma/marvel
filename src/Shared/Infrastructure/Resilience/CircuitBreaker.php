<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Resilience;

/**
 * Circuit Breaker implementation for resilience in external service calls.
 * 
 * States:
 * - CLOSED: Normal operation, requests pass through
 * - OPEN: Circuit tripped, requests fail fast without calling service
 * - HALF_OPEN: Testing if service recovered, limited requests allowed
 * 
 * @see docs/architecture/ADR-009-circuit-breaker-app.md
 */
final class CircuitBreaker
{
    private const STATE_CLOSED = 'closed';
    private const STATE_OPEN = 'open';
    private const STATE_HALF_OPEN = 'half_open';

    private readonly string $stateFile;
    private readonly int $failureThreshold;
    private readonly int $openTtlSeconds;
    private readonly int $halfOpenMaxCalls;

    /**
     * @param string $name Unique identifier for this circuit breaker instance
     * @param int $failureThreshold Number of failures before opening circuit
     * @param int $openTtlSeconds How long to wait before testing recovery
     * @param int $halfOpenMaxCalls Max calls allowed in half-open state
     * @param string|null $storagePath Where to store state (defaults to storage/circuit-breaker/)
     */
    public function __construct(
        private readonly string $name,
        int $failureThreshold = 3,
        int $openTtlSeconds = 30,
        int $halfOpenMaxCalls = 1,
        ?string $storagePath = null
    ) {
        $this->failureThreshold = max(1, $failureThreshold);
        $this->openTtlSeconds = max(1, $openTtlSeconds);
        $this->halfOpenMaxCalls = max(1, $halfOpenMaxCalls);

        $basePath = $storagePath ?? dirname(__DIR__, 4) . '/storage/circuit-breaker';
        if (!is_dir($basePath)) {
            @mkdir($basePath, 0775, true);
        }

        $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $name);
        $this->stateFile = $basePath . '/' . $safeName . '.json';
    }

    /**
     * Execute a callable with circuit breaker protection.
     * 
     * @template T
     * @param callable(): T $operation The operation to execute
     * @param callable(): T|null $fallback Optional fallback when circuit is open
     * @return T
     * @throws CircuitBreakerOpenException When circuit is open and no fallback provided
     */
    public function execute(callable $operation, ?callable $fallback = null): mixed
    {
        if (!$this->allowRequest()) {
            if ($fallback !== null) {
                return $fallback();
            }
            throw new CircuitBreakerOpenException(
                "Circuit breaker '{$this->name}' is open. Service unavailable."
            );
        }

        try {
            $result = $operation();
            $this->recordSuccess();
            return $result;
        } catch (\Throwable $exception) {
            $this->recordFailure();
            throw $exception;
        }
    }

    /**
     * Check if a request should be allowed through.
     */
    public function allowRequest(): bool
    {
        $state = $this->loadState();
        $now = time();

        if ($state['state'] === self::STATE_CLOSED) {
            return true;
        }

        if ($state['state'] === self::STATE_OPEN) {
            $elapsed = $now - $state['opened_at'];
            if ($elapsed < $this->openTtlSeconds) {
                return false; // Still in cooldown
            }

            // Transition to half-open
            $this->saveState([
                'state' => self::STATE_HALF_OPEN,
                'failure_count' => $state['failure_count'],
                'opened_at' => $state['opened_at'],
                'half_open_calls' => 0,
            ]);
            return true;
        }

        // HALF_OPEN state
        if ($state['half_open_calls'] >= $this->halfOpenMaxCalls) {
            return false;
        }

        $state['half_open_calls']++;
        $this->saveState($state);
        return true;
    }

    /**
     * Record a successful operation.
     */
    public function recordSuccess(): void
    {
        $state = $this->loadState();

        if ($state['state'] === self::STATE_HALF_OPEN || $state['state'] === self::STATE_OPEN) {
            // Recovery confirmed, close the circuit
            $this->saveState([
                'state' => self::STATE_CLOSED,
                'failure_count' => 0,
                'opened_at' => 0,
                'half_open_calls' => 0,
            ]);
            return;
        }

        // Reset failure count on success
        if ($state['failure_count'] > 0) {
            $state['failure_count'] = 0;
            $this->saveState($state);
        }
    }

    /**
     * Record a failed operation.
     */
    public function recordFailure(): void
    {
        $state = $this->loadState();
        $now = time();

        if ($state['state'] === self::STATE_HALF_OPEN) {
            // Recovery attempt failed, reopen circuit
            $this->openCircuit($state['failure_count'] + 1, $now);
            return;
        }

        $failureCount = $state['failure_count'] + 1;

        if ($failureCount >= $this->failureThreshold) {
            $this->openCircuit($failureCount, $now);
            return;
        }

        $state['failure_count'] = $failureCount;
        $this->saveState($state);
    }

    /**
     * Get current circuit state.
     * 
     * @return array{state: string, failure_count: int, opened_at: int, half_open_calls: int}
     */
    public function getState(): array
    {
        return $this->loadState();
    }

    /**
     * Force reset the circuit to closed state.
     */
    public function reset(): void
    {
        $this->saveState([
            'state' => self::STATE_CLOSED,
            'failure_count' => 0,
            'opened_at' => 0,
            'half_open_calls' => 0,
        ]);
    }

    /**
     * Check if circuit is currently open.
     */
    public function isOpen(): bool
    {
        $state = $this->loadState();
        return $state['state'] === self::STATE_OPEN;
    }

    private function openCircuit(int $failureCount, int $now): void
    {
        $this->saveState([
            'state' => self::STATE_OPEN,
            'failure_count' => $failureCount,
            'opened_at' => $now,
            'half_open_calls' => 0,
        ]);
    }

    /**
     * @return array{state: string, failure_count: int, opened_at: int, half_open_calls: int}
     */
    private function loadState(): array
    {
        $default = [
            'state' => self::STATE_CLOSED,
            'failure_count' => 0,
            'opened_at' => 0,
            'half_open_calls' => 0,
        ];

        if (!file_exists($this->stateFile)) {
            return $default;
        }

        $handle = @fopen($this->stateFile, 'r');
        if ($handle === false) {
            return $default;
        }

        flock($handle, LOCK_SH);
        $content = stream_get_contents($handle);
        flock($handle, LOCK_UN);
        fclose($handle);

        if (!is_string($content) || $content === '') {
            return $default;
        }

        $decoded = json_decode($content, true);
        if (!is_array($decoded)) {
            return $default;
        }

        return [
            'state' => (string) ($decoded['state'] ?? self::STATE_CLOSED),
            'failure_count' => (int) ($decoded['failure_count'] ?? 0),
            'opened_at' => (int) ($decoded['opened_at'] ?? 0),
            'half_open_calls' => (int) ($decoded['half_open_calls'] ?? 0),
        ];
    }

    /**
     * @param array{state: string, failure_count: int, opened_at: int, half_open_calls: int} $state
     */
    private function saveState(array $state): void
    {
        $encoded = json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if ($encoded === false) {
            return;
        }

        $handle = @fopen($this->stateFile, 'c');
        if ($handle === false) {
            return;
        }

        flock($handle, LOCK_EX);
        ftruncate($handle, 0);
        fwrite($handle, $encoded);
        fflush($handle);
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}
