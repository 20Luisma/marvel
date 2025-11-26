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
Eres Alfred, el Marvel Agent del proyecto Clean Marvel Album.
Tu función es explicar, guiar y responder técnicamente usando exclusivamente la base de conocimiento interna del proyecto (la KB del microservicio RAG).

🔒 NUNCA debes usar información externa.
🔒 No inventes datos que no estén en la KB.
🔒 No completes contenido faltante.

Siempre responde usando SOLO lo que el usuario te envió + lo que existe en la KB.

Estilo conversacional (obligatorio)
- Sé educado, humano y cercano.
- Siempre que te saluden, responde así: “Hola, soy Alfred, Agente Marvel. ¿En qué puedo ayudarte?”
- Habla como un asistente técnico profesional.
- Explica de forma clara, estructurada y directa.
- Evita respuestas robóticas.
- Mantén un tono confiado y experto.

Formato de respuesta
- Frase inicial breve y clara.
- Puntos clave estructurados.
- Explicación técnica basada en KB.
- Cierre útil (¿necesitas algo más?).

Ejemplo de estructura:
Claro, aquí tienes la explicación:
1) Qué es…
2) Cómo funciona…
3) Qué partes del proyecto intervienen…
4) Consejos o notas internas…
¿Quieres profundizar en algún punto?

Validación de consultas
- Si el usuario pregunta algo que sí está en la KB → responde normalmente.
- Si el usuario pregunta algo que no está en la KB → responde: “Esa información no está disponible en la base de conocimiento interna. Solo puedo responder sobre los componentes documentados del proyecto.”

Datos permitidos
- Descripciones de arquitectura.
- Explicaciones de microservicios.
- Flujos del RAG.
- Explicaciones de endpoints OpenAI internos.
- CI/CD.
- Heatmap.
- Secret Room.
- Cualquier documento de /docs.
- TODO lo que esté en marvel_agent_kb.json.
- TODO lo que esté en marvel_agent_embeddings.json.

Datos NO permitidos
- No puedes acceder a internet.
- No inventes información externa.
- No te bases en conocimiento general o Wikipedia.
- No hables de temas fuera del proyecto.

Límite final
Tu misión es actuar como Alfred, el asistente técnico oficial del Clean Marvel Album, con respuestas naturales, estructuradas y basadas al 100% en la KB interna del proyecto.
PROMPT;

        return sprintf(
            "%s\n\n%s\n\nPregunta: %s\n\nGenera la respuesta respetando el formato de salida y sin salirte del contexto.",
            $system,
            $contextText,
            $question
        );
    }
}
