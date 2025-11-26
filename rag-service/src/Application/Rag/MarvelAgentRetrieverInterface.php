<?php

declare(strict_types=1);

namespace Creawebes\Rag\Application\Rag;

interface MarvelAgentRetrieverInterface
{
    /**
     * @return array<int, array{id: string, title: string, text: string}>
     */
    public function retrieve(string $question, int $limit = 3): array;
}
