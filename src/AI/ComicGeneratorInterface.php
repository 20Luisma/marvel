<?php

declare(strict_types=1);

namespace App\AI;

/**
 * Contrato para generadores de cómics basados en LLM.
 *
 * Permite desacoplar la lógica de negocio del proveedor concreto de IA.
 * Implementaciones posibles: OpenAI, Claude, Gemini, Llama, etc.
 */
interface ComicGeneratorInterface
{
    /**
     * Indica si el generador está correctamente configurado y listo para usar.
     */
    public function isConfigured(): bool;

    /**
     * Genera un cómic a partir de un conjunto de héroes.
     *
     * @param array<int, array{heroId: string, nombre: string, contenido: string, imagen: string}> $heroes
     * @return array{
     *   story: array{title: string, summary: string, panels: array<int, array{title: string, description: string, caption: string}>}
     * }
     */
    public function generateComic(array $heroes): array;
}
