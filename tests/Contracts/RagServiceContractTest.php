<?php

declare(strict_types=1);

namespace Tests\Contracts;

use App\Config\ServiceUrlProvider;
use PHPUnit\Framework\TestCase;

final class RagServiceContractTest extends TestCase
{
    private const CONNECT_TIMEOUT_SECONDS = 2;
    private const TIMEOUT_SECONDS = 3;

    protected function setUp(): void
    {
        parent::setUp();

        if (getenv('RUN_CONTRACT_TESTS') !== '1') {
            self::markTestSkipped('Contract tests disabled. Set RUN_CONTRACT_TESTS=1 to enable.');
        }
    }

    public function testHealthEndpointRespondsOk(): void
    {
        [$ragBaseUrl] = $this->resolveRagUrls();

        $response = $this->httpRequest('GET', rtrim($ragBaseUrl, '/') . '/health');

        self::assertSame(200, $response['status'], 'Expected rag-service /health 200, got ' . $response['status'] . '. Body: ' . $this->snippet($response['body']));

        $json = $this->decodeJson($response['body'], 'rag-service /health');
        self::assertSame('ok', $json['status'] ?? null, 'Expected rag-service /health JSON with status=ok. Body: ' . $this->snippet($response['body']));
    }

    public function testHeroesComparisonViaAppProxyOrDirectEndpoint(): void
    {
        [$ragBaseUrl, $ragHeroesUrl] = $this->resolveRagUrls();
        $provider = $this->serviceUrlProvider();
        $environment = $provider->resolveEnvironment();
        $appBaseUrl = (string) (getenv('CONTRACT_APP_BASE_URL') ?: '');
        if ($appBaseUrl === '') {
            $appBaseUrl = (string) (getenv('APP_URL') ?: '');
        }
        if ($appBaseUrl === '') {
            $appBaseUrl = $provider->getAppBaseUrl($environment);
        }

        $targetUrl = $appBaseUrl !== ''
            ? rtrim($appBaseUrl, '/') . '/api/rag/heroes'
            : $ragHeroesUrl;

        $heroId1 = (string) (getenv('CONTRACT_HERO_ID_1') ?: 'a1a1a1a1-0001-4f00-9000-000000000001');
        $heroId2 = (string) (getenv('CONTRACT_HERO_ID_2') ?: 'a1a1a1a1-0002-4f00-9000-000000000002');

        $payload = [
            'question' => 'Comparación rápida (contract test).',
            'heroIds' => [$heroId1, $heroId2],
        ];

        $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        self::assertNotFalse($encoded, 'Failed to encode JSON payload for rag comparison.');

        $headers = [
            'Content-Type: application/json; charset=utf-8',
        ];

        if ($targetUrl === $ragHeroesUrl) {
            $headers = array_merge($headers, $this->internalSignatureHeaders('POST', $ragHeroesUrl, (string) $encoded, defaultCaller: 'localhost:8080'));
        }

        $response = $this->httpRequest('POST', $targetUrl, $headers, (string) $encoded);

        self::assertSame(
            200,
            $response['status'],
            'Expected rag heroes comparison 200 (target: ' . $targetUrl . '), got ' . $response['status'] . '. ' .
            'If your KB uses different hero IDs, set CONTRACT_HERO_ID_1/2. Body: ' . $this->snippet($response['body'])
        );

        $json = $this->decodeJson($response['body'], 'rag heroes comparison');
        self::assertIsString($json['answer'] ?? null, 'Expected key "answer" (string) in rag response. Body: ' . $this->snippet($response['body']));
        self::assertIsArray($json['contexts'] ?? null, 'Expected key "contexts" (array) in rag response. Body: ' . $this->snippet($response['body']));
        self::assertIsArray($json['heroIds'] ?? null, 'Expected key "heroIds" (array) in rag response. Body: ' . $this->snippet($response['body']));
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function resolveRagUrls(): array
    {
        $provider = $this->serviceUrlProvider();
        $environment = $provider->resolveEnvironment();

        $heroesUrl = $provider->getRagHeroesUrl($environment);
        $baseUrl = $provider->getRagBaseUrl($environment);

        if ($baseUrl === '') {
            $baseUrl = $this->deriveBaseUrl($heroesUrl);
        }

        self::assertNotSame('', $baseUrl, 'Could not resolve RAG base URL. Set RAG_BASE_URL or configure config/services.php.');
        self::assertNotSame('', $heroesUrl, 'Could not resolve RAG heroes URL. Set RAG_SERVICE_URL or configure config/services.php.');

        return [$baseUrl, $heroesUrl];
    }

    private function serviceUrlProvider(): ServiceUrlProvider
    {
        /** @var array<string, mixed> $config */
        $config = require __DIR__ . '/../../config/services.php';

        return new ServiceUrlProvider($config);
    }

    /**
     * @return array{status: int, body: string, headers: array<string, string>}
     */
    private function httpRequest(string $method, string $url, array $headers = [], ?string $body = null): array
    {
        if (!function_exists('curl_init')) {
            self::fail('cURL extension is required for contract tests.');
        }

        $responseHeaders = [];
        $ch = curl_init($url);
        if ($ch === false) {
            self::fail('curl_init failed for URL: ' . $url);
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, self::CONNECT_TIMEOUT_SECONDS);
        curl_setopt($ch, CURLOPT_TIMEOUT, self::TIMEOUT_SECONDS);
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, static function ($curl, string $line) use (&$responseHeaders): int {
            $trimmed = trim($line);
            if ($trimmed === '' || !str_contains($trimmed, ':')) {
                return strlen($line);
            }

            [$name, $value] = explode(':', $trimmed, 2);
            $responseHeaders[strtolower(trim($name))] = trim($value);
            return strlen($line);
        });

        if ($headers !== []) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = (string) curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            self::fail('HTTP request failed: ' . $method . ' ' . $url . ' (curl_error=' . $error . ')');
        }

        return [
            'status' => $status,
            'body' => (string) $raw,
            'headers' => $responseHeaders,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJson(string $body, string $context): array
    {
        $decoded = json_decode($body, true);
        self::assertIsArray($decoded, 'Expected JSON object for ' . $context . '. Body: ' . $this->snippet($body));

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    /**
     * @return array<int, string>
     */
    private function internalSignatureHeaders(string $method, string $url, string $rawBody, string $defaultCaller): array
    {
        $key = (string) (getenv('INTERNAL_API_KEY') ?: '');
        $key = trim($key);
        if ($key === '') {
            return [];
        }

        $path = parse_url($url, PHP_URL_PATH);
        $path = is_string($path) && $path !== '' ? $path : '/';
        $timestamp = time();
        $canonical = strtoupper($method) . "\n" . $path . "\n" . $timestamp . "\n" . hash('sha256', $rawBody);
        $signature = hash_hmac('sha256', $canonical, $key);

        $caller = (string) (getenv('CONTRACT_CALLER') ?: $defaultCaller);
        $caller = trim($caller) !== '' ? trim($caller) : $defaultCaller;

        return [
            'X-Internal-Signature: ' . $signature,
            'X-Internal-Timestamp: ' . $timestamp,
            'X-Internal-Caller: ' . $caller,
        ];
    }

    private function deriveBaseUrl(string $url): string
    {
        $scheme = parse_url($url, PHP_URL_SCHEME);
        $host = parse_url($url, PHP_URL_HOST);
        $port = parse_url($url, PHP_URL_PORT);

        if (!is_string($scheme) || $scheme === '' || !is_string($host) || $host === '') {
            return '';
        }

        $base = $scheme . '://' . $host;
        if (is_int($port) && $port > 0) {
            $base .= ':' . $port;
        }

        return $base;
    }

    private function snippet(string $body): string
    {
        $trimmed = trim($body);
        if (strlen($trimmed) <= 250) {
            return $trimmed;
        }

        return substr($trimmed, 0, 250) . '...';
    }
}

