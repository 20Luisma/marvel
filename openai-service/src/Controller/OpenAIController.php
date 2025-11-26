<?php

declare(strict_types=1);

namespace Creawebes\OpenAI\Controller;

use Creawebes\OpenAI\Application\UseCase\GenerateContent;
use Throwable;

final class OpenAIController
{
    public function __construct(
        private readonly GenerateContent $generateContent
    ) {
    }

    /**
     * Maneja POST /v1/chat.
     */
    public function chat(): void
    {
        $raw = file_get_contents('php://input') ?: '';
        $data = json_decode($raw, true) ?? [];

        $messages = $data['messages'] ?? [];
        if (!is_array($messages)) {
            $messages = [];
        }

        try {
            $story = $this->generateContent->handle($messages);

            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'ok' => true,
                'content' => $story,
            ], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $exception) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'ok' => false,
                'error' => $exception->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
        }
    }
}
