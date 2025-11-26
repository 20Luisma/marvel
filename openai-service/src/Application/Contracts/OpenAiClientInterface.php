<?php

declare(strict_types=1);

namespace Creawebes\OpenAI\Application\Contracts;

interface OpenAiClientInterface
{
    /**
     * @param array<int, array<string, mixed>> $messages
     * @return array<string, mixed>
     */
    public function chat(array $messages, ?string $model = null): array;
}
