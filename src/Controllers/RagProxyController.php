<?php

declare(strict_types=1);

namespace Src\Controllers;

use App\Shared\Infrastructure\Http\HttpClientInterface;
use Src\Controllers\Http\Request;

final class RagProxyController
{
    private const DEFAULT_TIMEOUT = 20;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $ragServiceUrl,
        private readonly ?string $internalToken
    ) {
    }

    public function forwardHeroesComparison(): void
    {
        $payload = Request::jsonBody();

        $headers = [];
        if (is_string($this->internalToken) && $this->internalToken !== '') {
            $headers['X-Internal-Token'] = $this->internalToken;
        }

        try {
            $response = $this->httpClient->postJson(
                $this->ragServiceUrl,
                $payload,
                $headers,
                timeoutSeconds: self::DEFAULT_TIMEOUT,
                retries: 1
            );
        } catch (\Throwable $exception) {
            http_response_code(502);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'error' => 'Error al contactar el microservicio RAG.',
                'message' => $exception->getMessage(),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return;
        }

        http_response_code($response->statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo $response->body;
    }
}
