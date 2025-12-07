<?php

declare(strict_types=1);

namespace Src\Controllers;

use App\Shared\Infrastructure\Http\HttpClientInterface;
use App\Shared\Infrastructure\Security\InternalRequestSigner;
use Src\Http\RequestBodyReader;

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
            // BEGIN ZONAR FIX DEFINITIVO - Leer JSON puro directamente
            $rawBody = $_SERVER['MARVEL_RAW_BODY'] ?? RequestBodyReader::getRawBody();
            $rawBody = is_string($rawBody) ? $rawBody : '';
            
            file_put_contents($logFile, date('c') . " [RAG] Raw body length: " . strlen($rawBody) . "\n", FILE_APPEND);
            
            if ($rawBody === '') {
                file_put_contents($logFile, date('c') . " [RAG] ERROR: Body vacío\n", FILE_APPEND);
                http_response_code(400);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'estado' => 'error',
                    'message' => 'El cuerpo de la petición está vacío'
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                return;
            }
            
            $payload = json_decode($rawBody, true);
            
            if (!is_array($payload)) {
                file_put_contents($logFile, date('c') . " [RAG] ERROR: JSON inválido\n", FILE_APPEND);
                http_response_code(400);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'estado' => 'error',
                    'message' => 'El cuerpo no es un JSON válido'
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                return;
            }
            
            file_put_contents($logFile, date('c') . " [RAG] Payload recibido correctamente\n", FILE_APPEND);
            // END ZONAR FIX DEFINITIVO


            if ($payload === []) {
                file_put_contents($logFile, date('c') . " [RAG] ERROR: Payload vacío\n", FILE_APPEND);
                http_response_code(400);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'estado' => 'error',
                    'message' => 'El cuerpo de la petición está vacío'
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                return;
            }

            $question = $payload['question'] ?? '';
            $heroIdsRaw = $payload['heroIds'] ?? [];
            
            if (!is_array($heroIdsRaw)) {
                 $heroIdsRaw = [];
            }

            $heroIds = [];
            foreach ($heroIdsRaw as $id) {
                $heroIds[] = (string) $id;
            }

            $cleanPayload = [
                'question' => (string) $question,
                'heroIds' => $heroIds
            ];

            $encodedPayload = json_encode($cleanPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($encodedPayload === false) {
                throw new \RuntimeException('No se pudo serializar el payload para RAG.');
            }
            
            file_put_contents($logFile, date('c') . " [RAG] Payload: $encodedPayload\n", FILE_APPEND);

            $requestHeaders = $this->signer !== null
                ? $this->signer->sign('POST', $this->ragServiceUrl, $encodedPayload)
                : [];

            $response = $this->httpClient->postJson(
                $this->ragServiceUrl,
                $encodedPayload,
                $requestHeaders,
                timeoutSeconds: self::DEFAULT_TIMEOUT,
                retries: self::DEFAULT_RETRIES
            );

            file_put_contents($logFile, date('c') . " [RAG] Respuesta: " . $response->statusCode . "\n", FILE_APPEND);

            http_response_code($response->statusCode);
            header('Content-Type: application/json; charset=utf-8');
            echo $response->body;

        } catch (\Throwable $e) {
            file_put_contents($logFile, date('c') . " [RAG] EXCEPTION: " . $e->getMessage() . "\n", FILE_APPEND);
            
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'estado' => 'error',
                'message' => 'Error interno en el proxy RAG: ' . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }
    }
}
