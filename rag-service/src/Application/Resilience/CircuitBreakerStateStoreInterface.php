<?php

declare(strict_types=1);

namespace Creawebes\Rag\Application\Resilience;

interface CircuitBreakerStateStoreInterface
{
    /**
     * @return array{state: string, failure_count: int, opened_at: int, half_open_calls: int}
     */
    public function load(): array;

    /**
     * @param array{state: string, failure_count: int, opened_at: int, half_open_calls: int} $state
     */
    public function save(array $state): void;
}

