<?php

declare(strict_types=1);

namespace Creawebes\Rag\Application\Resilience;

use Creawebes\Rag\Application\Contracts\StructuredLoggerInterface;

final class CircuitBreaker
{
    private const STATE_CLOSED = 'closed';
    private const STATE_OPEN = 'open';
    private const STATE_HALF_OPEN = 'half_open';

    private readonly CircuitBreakerStateStoreInterface $store;
    private readonly StructuredLoggerInterface $logger;
    private readonly int $failureThreshold;
    private readonly int $openTtlSeconds;
    private readonly int $halfOpenMaxCalls;
    private readonly ?\Closure $clock;

    public function __construct(
        CircuitBreakerStateStoreInterface $store,
        StructuredLoggerInterface $logger,
        int $failureThreshold = 3,
        int $openTtlSeconds = 30,
        int $halfOpenMaxCalls = 1,
        ?callable $clock = null,
    ) {
        $this->store = $store;
        $this->logger = $logger;
        $this->failureThreshold = $failureThreshold;
        $this->openTtlSeconds = $openTtlSeconds;
        $this->halfOpenMaxCalls = $halfOpenMaxCalls;
        $this->clock = $clock !== null ? \Closure::fromCallable($clock) : null;
    }

    public function beforeCall(): string
    {
        $state = $this->store->load();
        $now = $this->now();

        if ($state['state'] === self::STATE_OPEN) {
            $elapsed = $now - $state['opened_at'];
            if ($elapsed < $this->openTtlSeconds) {
                $this->logger->log('llm.circuit.short_circuit', [
                    'state' => self::STATE_OPEN,
                ]);
                throw new CircuitBreakerOpenException(self::STATE_OPEN);
            }

            $state = [
                'state' => self::STATE_HALF_OPEN,
                'failure_count' => $state['failure_count'],
                'opened_at' => $state['opened_at'],
                'half_open_calls' => 0,
            ];
            $this->store->save($state);

            $this->logger->log('llm.circuit.half_open', [
                'state' => self::STATE_HALF_OPEN,
            ]);
        }

        if ($state['state'] === self::STATE_HALF_OPEN) {
            if ($state['half_open_calls'] >= $this->halfOpenMaxCalls) {
                $this->logger->log('llm.circuit.short_circuit', [
                    'state' => self::STATE_HALF_OPEN,
                ]);
                throw new CircuitBreakerOpenException(self::STATE_HALF_OPEN);
            }

            $state['half_open_calls']++;
            $this->store->save($state);
        }

        return $state['state'];
    }

    public function onSuccess(): void
    {
        $state = $this->store->load();

        if ($state['state'] === self::STATE_HALF_OPEN || $state['state'] === self::STATE_OPEN) {
            $this->store->save([
                'state' => self::STATE_CLOSED,
                'failure_count' => 0,
                'opened_at' => 0,
                'half_open_calls' => 0,
            ]);
            return;
        }

        $this->store->save([
            'state' => self::STATE_CLOSED,
            'failure_count' => 0,
            'opened_at' => $state['opened_at'],
            'half_open_calls' => 0,
        ]);
    }

    public function onFailure(): void
    {
        $state = $this->store->load();
        $now = $this->now();

        if ($state['state'] === self::STATE_HALF_OPEN) {
            $this->open($state['failure_count'] + 1, $now);
            return;
        }

        if ($state['state'] === self::STATE_OPEN) {
            $this->open($state['failure_count'] + 1, $now);
            return;
        }

        $failureCount = $state['failure_count'] + 1;
        if ($failureCount >= $this->failureThreshold) {
            $this->open($failureCount, $now);
            return;
        }

        $this->store->save([
            'state' => self::STATE_CLOSED,
            'failure_count' => $failureCount,
            'opened_at' => $state['opened_at'],
            'half_open_calls' => 0,
        ]);
    }

    public function getState(): string
    {
        return $this->store->load()['state'];
    }

    private function open(int $failureCount, int $now): void
    {
        $this->store->save([
            'state' => self::STATE_OPEN,
            'failure_count' => $failureCount,
            'opened_at' => $now,
            'half_open_calls' => 0,
        ]);

        $this->logger->log('llm.circuit.opened', [
            'state' => self::STATE_OPEN,
        ]);
    }

    private function now(): int
    {
        if ($this->clock !== null) {
            return (int) ($this->clock)();
        }

        return time();
    }
}
