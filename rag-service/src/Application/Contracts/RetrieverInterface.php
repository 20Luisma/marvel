<?php

declare(strict_types=1);

namespace Creawebes\Rag\Application\Contracts;

interface RetrieverInterface
{
    /**
     * @param array<int, string> $heroIds
     * @return array<int, array{heroId: string, nombre: string, contenido: string, score: float}>
     */
    public function retrieve(array $heroIds, string $question, int $limit = 5): array;
}
