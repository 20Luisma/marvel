<?php

declare(strict_types=1);

namespace Creawebes\Rag\Application;

use Creawebes\Rag\Application\Contracts\KnowledgeBaseInterface;
use Creawebes\Rag\Application\Contracts\LlmClientInterface;
use Creawebes\Rag\Application\Contracts\RetrieverInterface;
use InvalidArgumentException;

final class HeroRagService
{
    private const DEFAULT_QUESTION = 'Compara sus atributos y resume el resultado';

    public function __construct(
        private readonly KnowledgeBaseInterface $knowledgeBase,
        private readonly RetrieverInterface $retriever,
        private readonly LlmClientInterface $llmClient
    ) {
    }

    /**
     * @param array<int, string> $heroIds
     * @return array{answer: string, contexts: array<int, array{heroId: string, nombre: string, contenido: string, score: float}>, heroIds: array<int, string>}
     */
    public function compare(array $heroIds, ?string $question = null): array
    {
        $question = trim((string) ($question ?? self::DEFAULT_QUESTION));
        if ($question === '') {
            $question = self::DEFAULT_QUESTION;
        }

        $count = count($heroIds);
        if ($count !== 2) {
            throw new InvalidArgumentException('Selecciona 2 héroes primero.');
        }

        $contexts = $this->retriever->retrieve($heroIds, $question);
        if (count($contexts) < 2) {
            throw new InvalidArgumentException('Selecciona 2 héroes primero.');
        }

        $prompt = $this->buildPrompt($contexts, $question);
        $answer = $this->llmClient->ask($prompt);

        return [
            'answer' => $answer,
            'contexts' => $contexts,
            'heroIds' => array_column($contexts, 'heroId'),
        ];
    }

    /**
     * @param array<int, array{heroId: string, nombre: string, contenido: string}> $contexts
     */
    private function buildPrompt(array $contexts, string $question): string
    {
        $heroSummaries = array_map(
            static fn (array $context): string => sprintf(
                "- %s (ID: %s): %s",
                $context['nombre'] !== '' ? $context['nombre'] : 'Héroe sin nombre',
                $context['heroId'],
                $context['contenido'] !== '' ? $context['contenido'] : 'Sin descripción disponible'
            ),
            $contexts
        );

        $joined = implode("\n", $heroSummaries);

        return sprintf(
            <<<PROMPT
Tienes la siguiente información detallada de héroes. Usa esos contextos para responder a la pregunta: "%s".
%s

Instrucciones:
- Mantén un tono narrativo claro, directo y apto para audio. No utilices tablas, íconos, estrellas ni emojis.
- Explica primero las diferencias entre ambos héroes mencionando atributos o capacidades relevantes que aparecen en el contexto.
- En un segundo párrafo describe cómo se complementan en combate o misión, usando al menos dos criterios por cada héroe según los datos disponibles.
- Apóyate exclusivamente en los contextos proporcionados y no inventes nuevos atributos ni cambies valoraciones.
PROMPT,
            $question,
            $joined
        );
    }
}
