<?php

declare(strict_types=1);

namespace App\AI;

use App\AI\OpenAIComicGenerator;
use App\AI\ComicGeneratorInterface;
use RuntimeException;

final class ComicGeneratorFactory
{
    public static function create(string $provider, ?string $serviceUrl): ComicGeneratorInterface
    {
        return match (strtolower($provider)) {
            'openai' => new OpenAIComicGenerator($serviceUrl ?: ''),
            default => throw new RuntimeException(sprintf('Proveedor de IA <%s> no soportado.', $provider)),
        };
    }
}

