<?php

declare(strict_types=1);

namespace Creawebes\Rag\Application\Contracts;

interface EmbeddingClientInterface
{
    /**
     * @return array<float>
     */
    public function embedText(string $text): array;

    /**
     * @param array<int, string> $texts
     * @return array<int, array<float>>
     */
    public function embedDocuments(array $texts): array;
}
