<?php

declare(strict_types=1);

namespace Creawebes\Rag\Application\UseCase;

use Creawebes\Rag\Application\Contracts\LlmClientInterface;
use Creawebes\Rag\Application\Rag\MarvelAgentRetriever;
use InvalidArgumentException;

final class AskMarvelAgentUseCase
{
    public function __construct(
        private readonly MarvelAgentRetriever $retriever,
        private readonly LlmClientInterface $llmClient
    ) {
    }

    /**
     * @return array{answer: string, contexts: array<int, array{id: string, title: string}>}
     */
    public function ask(string $question): array
    {
        $normalizedQuestion = trim($question);
        if ($normalizedQuestion === '') {
            throw new InvalidArgumentException('La pregunta no puede estar vacía.');
        }

        $contexts = $this->retriever->retrieve($normalizedQuestion, 3);
        $contextText = $this->buildContextText($contexts);

        $prompt = $this->buildPrompt($normalizedQuestion, $contextText);
        $answer = $this->llmClient->ask($prompt);

        return [
            'answer' => $answer,
            'contexts' => array_map(
                static fn (array $ctx): array => [
                    'id' => $ctx['id'],
                    'title' => $ctx['title'],
                ],
                $contexts
            ),
        ];
    }

    /**
     * @param array<int, array{id: string, title: string, text: string}> $contexts
     */
    private function buildContextText(array $contexts): string
    {
        if ($contexts === []) {
            return 'Contexto: (vacío, no hay información en la KB)';
        }

        $chunks = [];
        foreach ($contexts as $ctx) {
            $chunks[] = $ctx['title'] . "\n" . $ctx['text'];
        }

        return "Contexto (extractos KB):\n---\n" . implode("\n---\n", $chunks);
    }

    private function buildPrompt(string $question, string $contextText): string
    {
        $system = 'Eres Marvel Agent, asistente técnico de Clean Marvel Album. Usa solo el contexto disponible y, si falta información, dilo de forma breve y clara. No inventes datos.';

        return sprintf(
            "%s\n\n%s\n\nPregunta: %s\n\nResponde de forma técnica y concisa, sin inventar datos fuera del contexto.",
            $system,
            $contextText,
            $question
        );
    }
}
