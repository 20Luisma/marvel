<?php

declare(strict_types=1);

namespace Creawebes\Rag\Controllers;

use Creawebes\Rag\Application\HeroRagService;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class RagController
{
    public function __construct(private readonly HeroRagService $ragService)
    {
    }

    public function compareHeroes(): void
    {
        $body = file_get_contents('php://input') ?: '';
        $payload = json_decode($body, true) ?? [];

        $question = isset($payload['question']) ? (string) $payload['question'] : null;
        $heroIds = $payload['heroIds'] ?? [];

        if (!is_array($heroIds)) {
            $this->sendError('El campo heroIds debe ser un arreglo.', 422);
            return;
        }

        try {
            $result = $this->ragService->compare($heroIds, $question);
        } catch (InvalidArgumentException $exception) {
            $this->sendError($exception->getMessage(), 422);
            return;
        } catch (RuntimeException $exception) {
            $this->sendError($exception->getMessage(), 502);
            return;
        } catch (Throwable $exception) {
            $this->sendError('No se pudo procesar la comparación: ' . $exception->getMessage(), 500);
            return;
        }

        $this->sendJson($result);
    }

    private function sendJson(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function sendError(string $message, int $status = 400): void
    {
        $this->sendJson(['error' => $message], $status);
    }
}
