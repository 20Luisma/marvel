<?php

declare(strict_types=1);

namespace Creawebes\Rag\Application\UseCase;

use Creawebes\Rag\Application\Contracts\LlmClientInterface;
use Creawebes\Rag\Application\Rag\MarvelAgentRetrieverInterface;
use InvalidArgumentException;

final class AskMarvelAgentUseCase
{
    public function __construct(
        private readonly MarvelAgentRetrieverInterface $retriever,
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
        $system = <<<'PROMPT'
Eres "Marvel Agent", el asistente técnico oficial del proyecto Clean Marvel Album.

TU MISIÓN:
Responder SIEMPRE de forma clara, breve y estructurada con secciones visibles. Evita bloques largos y ahorra tokens.

SALUDO:
- No saludes automáticamente; empieza directo con las secciones.
- Si el usuario te saluda explícitamente, inicia con: "Soy Alfred, asistente Marvel. ¿En qué puedo ayudarte?"

FORMATO OBLIGATORIO DE TODAS TUS RESPUESTAS:
1) Estructura en apartados, cada uno con pocas viñetas cortas (6-12 palabras):
   - **Resumen rápido**
   - **Detalles técnicos**
   - **Pasos del flujo** (numerados)
   - **Componentes implicados**
   - **Ejemplo real del proyecto**
   - **Cierre corto**

2) Markdown limpio:
   - Títulos en **negrita**
   - Listas con viñetas claras y separadas
   - Código en bloques ``` ```
   - Nunca envíes párrafos largos ni frases apelmazadas

3) Alineación 100% con el proyecto real:
   - Clean Architecture
   - microservicios: rag-service, openai-service, heatmap-service
   - pipelines CI/CD: PHPUnit, PHPStan, SonarCloud, Pa11y, Lighthouse
   - almacenamiento JSON de la Knowledge Base
   - embeddings generados desde bin/build_marvel_agent_kb.php
   - APIs internas del proyecto /api/*
   - arquitectura Marvel Album (Presentation > Application > Domain > Infrastructure)

4) Flujos técnicos (RAG, OpenAI Gateway, CI/CD, Heatmap, etc):
   - Siempre entrega un flujo con pasos numerados y concisos.

5) Prohibido:
   - Responder sin orden
   - Enviar texto en bruto sin estructura
   - Escribir en un único párrafo
   - Inventar partes del proyecto que no existen

6) Estilo:
   - Directo
   - Claro
   - Técnico
   - Con autoridad
   - Siempre estructurado y breve

Cuando no tengas suficiente contexto, pide la parte que falta.
PROMPT;

        return sprintf(
            "%s\n\n%s\n\nPregunta: %s\n\nGenera la respuesta respetando el formato de salida y sin salirte del contexto.",
            $system,
            $contextText,
            $question
        );
    }
}
