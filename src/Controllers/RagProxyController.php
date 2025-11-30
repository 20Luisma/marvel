<?php

declare(strict_types=1);

namespace Src\Controllers;

use App\Shared\Infrastructure\Http\HttpClientInterface;
use App\Shared\Infrastructure\Security\InternalRequestSigner;

final class RagProxyController
{
    private const DEFAULT_TIMEOUT = 20;
    private const DEFAULT_RETRIES = 2;

    private readonly ?InternalRequestSigner $signer;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $ragServiceUrl,
        ?string $internalToken
    ) {
        $caller = is_string($_SERVER['HTTP_HOST'] ?? null) ? trim((string) $_SERVER['HTTP_HOST']) : 'clean-marvel-app';
        $this->signer = is_string($internalToken) && $internalToken !== ''
            ? new InternalRequestSigner($internalToken, $caller !== '' ? $caller : 'clean-marvel-app')
            : null;
    }

    public function forwardHeroesComparison(): void
    {
        $logFile = __DIR__ . '/../../storage/logs/debug_rag_proxy.log';
        
        try {
            // 1. Leer body usando el lector centralizado
            $rawBody = \Src\Http\RequestBodyReader::getRawBody();

            if ($rawBody === '') {
                // LOG de depuración claro
                error_log('[RAG_DEBUG] Raw Body está vacío en RagProxyController');
                file_put_contents($logFile, date('c') . " [RAG_DEBUG] Raw Body está vacío en RagProxyController\n", FILE_APPEND);

                throw new \RuntimeException('El cuerpo de la petición está vacío');
            }

            // Log de depuración (longitud)
            error_log('[RAG_DEBUG] Raw Body length: ' . strlen($rawBody));
            file_put_contents($logFile, date('c') . " [RAG_DEBUG] Raw Body length: " . strlen($rawBody) . "\n", FILE_APPEND);

            // 2. Decodificar JSON
            $payload = json_decode($rawBody, true);
            if (!is_array($payload)) {
                throw new \RuntimeException('El cuerpo no es un JSON válido');
            }

            // Log headers
            $headers = function_exists('getallheaders') ? getallheaders() : [];
            file_put_contents($logFile, date('c') . " [RAG_DEBUG] Headers: " . json_encode($headers) . "\n", FILE_APPEND);

            // 3. Extraer y normalizar datos (sin validación estricta)
            $question = $payload['question'] ?? '';
            $heroIdsRaw = $payload['heroIds'] ?? [];
            
            if (!is_array($heroIdsRaw)) {
                 // Si no es array, intentamos convertirlo o fallamos suavemente
                 $heroIdsRaw = [];
            }

            $heroIds = [];
            foreach ($heroIdsRaw as $id) {
                $heroIds[] = (string) $id;
            }

            // Reconstruir payload limpio
            $cleanPayload = [
                'question' => (string) $question,
                'heroIds' => $heroIds
            ];

            $encodedPayload = json_encode($cleanPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            
            // 4. Preparar headers internos
            $requestHeaders = $this->signer !== null
                ? $this->signer->sign('POST', $this->ragServiceUrl, $encodedPayload)
                : [];

            file_put_contents($logFile, date('c') . " [RAG_DEBUG] Forwarding to: " . $this->ragServiceUrl . "\n", FILE_APPEND);

            // 5. Enviar al microservicio
            $response = $this->httpClient->postJson(
                $this->ragServiceUrl,
                $encodedPayload,
                $requestHeaders,
                timeoutSeconds: self::DEFAULT_TIMEOUT,
                retries: self::DEFAULT_RETRIES
            );

            file_put_contents($logFile, date('c') . " [RAG_DEBUG] Upstream Response: " . $response->statusCode . " Body: " . $response->body . "\n", FILE_APPEND);

            // 6. Devolver respuesta tal cual
            http_response_code($response->statusCode);
            header('Content-Type: application/json; charset=utf-8');
            echo $response->body;

        } catch (\Throwable $e) {
            file_put_contents($logFile, date('c') . " [RAG_DEBUG] EXCEPTION: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n", FILE_APPEND);
            
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'estado' => 'error',
                'message' => 'Error interno en el proxy RAG: ' . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }
    }
}
