<?php

declare(strict_types=1);

namespace App\Heroes\Infrastructure\Rag;

use App\Config\ServiceUrlProvider;
use App\Heroes\Application\Rag\HeroRagSyncer;
use App\Heroes\Domain\Entity\Hero;
use App\Shared\Infrastructure\Http\HttpClientInterface;
use App\Shared\Infrastructure\Security\InternalRequestSigner;
use Throwable;

final class HeroRagSyncService implements HeroRagSyncer
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly ?ServiceUrlProvider $serviceUrlProvider,
        private readonly ?InternalRequestSigner $signer,
        private readonly string $environment,
        private readonly ?string $logFile = null
    ) {
    }

    public function sync(Hero $hero): void
    {
        if (strtolower($this->environment) === 'test') {
            return;
        }

        $endpoint = $this->resolveEndpoint();
        if ($endpoint === '') {
            $this->log('RAG sync omitido: endpoint no configurado.');
            return;
        }

        $payload = [
            'heroId' => $hero->heroId(),
            'nombre' => $hero->nombre(),
            'contenido' => $hero->contenido(),
        ];

        $rawBody = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($rawBody === false) {
            $this->log('RAG sync omitido: no se pudo serializar el payload.');
            return;
        }

        $headers = $this->signer !== null
            ? $this->signer->sign('POST', $endpoint, $rawBody)
            : [];

        // Propagar trace_id al microservicio RAG
        $traceId = $_SERVER['X_TRACE_ID'] ?? null;
        if (is_string($traceId) && $traceId !== '') {
            $headers['X-Trace-Id'] = $traceId;
        }

        try {
            $response = $this->httpClient->postJson($endpoint, $rawBody, $headers, timeoutSeconds: 12, retries: 1);
            if ($response->statusCode >= 400) {
                $this->log(sprintf('RAG sync error HTTP %d trace_id=%s: %s', $response->statusCode, $traceId ?? '-', $response->body));
            }
        } catch (Throwable $exception) {
            $this->log(sprintf('RAG sync falló trace_id=%s: %s', $traceId ?? '-', $exception->getMessage()));
        }
    }

    private function resolveEndpoint(): string
    {
        $baseUrl = $this->resolveBaseUrl();
        if ($baseUrl === '') {
            return '';
        }

        return rtrim($baseUrl, '/') . '/rag/heroes/upsert';
    }

    private function resolveBaseUrl(): string
    {
        $envBase = getenv('RAG_BASE_URL');
        if (is_string($envBase) && trim($envBase) !== '') {
            $trimmed = trim($envBase);
            $host = parse_url($trimmed, PHP_URL_HOST) ?: $trimmed;
            $isLocalEnv = in_array(strtolower($this->environment), ['local', 'auto'], true);
            $isLocalHost = in_array($host, ['localhost', '127.0.0.1', '::1'], true);
            // Si estamos en local y la URL apunta a hosting, ignorar override y usar provider.
            if (!$isLocalEnv || $isLocalHost) {
                return $trimmed;
            }
        }

        $envService = getenv('RAG_SERVICE_URL');
        if (is_string($envService) && trim($envService) !== '') {
            $trimmed = trim($envService);
            $parsed = parse_url($trimmed, PHP_URL_HOST);
            $host = $parsed ?: $trimmed;
            $isLocalEnv = in_array(strtolower($this->environment), ['local', 'auto'], true);
            $isLocalHost = in_array($host, ['localhost', '127.0.0.1', '::1'], true);
            if (!$isLocalEnv || $isLocalHost) {
                return $parsed ? preg_replace('#/rag/heroes$#', '', $trimmed) : $trimmed;
            }
        }

        if ($this->serviceUrlProvider instanceof ServiceUrlProvider) {
            $base = $this->serviceUrlProvider->getRagBaseUrl();
            $host = parse_url($base, PHP_URL_HOST) ?: $base;
            $isLocalEnv = in_array(strtolower($this->environment), ['local', 'auto'], true);
            $isLocalHost = in_array($host, ['localhost', '127.0.0.1', '::1'], true);
            if ($isLocalEnv && !$isLocalHost) {
                // Forzamos localhost para evitar que la variable de entorno apunte al hosting en local.
                return 'http://localhost:8082';
            }

            return $base;
        }

        return '';
    }

    private function log(string $message): void
    {
        if ($this->logFile !== null && $this->logFile !== '') {
            $directory = dirname($this->logFile);
            if (!is_dir($directory)) {
                @mkdir($directory, 0775, true);
            }
            @file_put_contents($this->logFile, date('c') . ' ' . $message . PHP_EOL, FILE_APPEND);
            return;
        }

        error_log($message);
    }
}
