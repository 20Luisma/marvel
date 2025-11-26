<?php

declare(strict_types=1);

namespace Creawebes\Rag\Application\Contracts;

interface KnowledgeBaseInterface
{
    /**
     * @param array<int, string> $heroIds
     * @return array<int, array{heroId: string, nombre: string, contenido: string}>
     */
    public function findByIds(array $heroIds): array;

    /**
     * @return array<int, array{heroId: string, nombre: string, contenido: string}>
     */
    public function all(): array;
}
