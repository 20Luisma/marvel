<?php

declare(strict_types=1);

namespace Tests\Contracts;

use App\Config\ServiceUrlProvider;
use PHPUnit\Framework\TestCase;

final class OpenAiServiceContractTest extends TestCase
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
        [$openAiBaseUrl] = $this->resolveOpenAiUrls();

        $response = $this->httpRequest('GET', rtrim($openAiBaseUrl, '/') . '/health');

        self::assertSame(200, $response['status'], 'Expected openai-service /health 200, got ' . $response['status'] . '. Body: ' . $this->snippet($response['body']));

        $json = $this->decodeJson($response['body'], 'openai-service /health');
        self::assertSame('ok', $json['status'] ?? null, 'Expected openai-service /health JSON with status=ok. Body: ' . $this->snippet($response['body']));
    }

    public function testChatEndpointRespondsWithExpectedKeys(): void
    {
        [, $chatUrl] = $this->resolveOpenAiUrls();

        $payload = [
            'messages' => [
                ['role' => 'user', 'content' => 'Ping (contract test).'],
            ],
        ];

        $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        self::assertNotFalse($encoded, 'Failed to encode JSON payload for openai chat.');

        $headers = [
            'Content-Type: application/json; charset=utf-8',
        ];
        $headers = array_merge($headers, $this->internalSignatureHeaders('POST', $chatUrl, (string) $encoded, defaultCaller: 'localhost:8082'));

        $response = $this->httpRequest('POST', $chatUrl, $headers, (string) $encoded);

        self::assertSame(200, $response['status'], 'Expected openai-service chat 200, got ' . $response['status'] . '. Body: ' . $this->snippet($response['body']));

        $json = $this->decodeJson($response['body'], 'openai-service chat');
        self::assertArrayHasKey('ok', $json, 'Expected key "ok" in openai-service response. Body: ' . $this->snippet($response['body']));
        self::assertIsString($json['content'] ?? null, 'Expected key "content" (string) in openai-service response. Body: ' . $this->snippet($response['body']));
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function resolveOpenAiUrls(): array
    {
        $provider = $this->serviceUrlProvider();
        $environment = $provider->resolveEnvironment();

        $chatUrl = $provider->getOpenAiChatUrl($environment);
        $baseUrl = $provider->getOpenAiBaseUrl($environment);

        if ($baseUrl === '') {
            $baseUrl = $this->deriveBaseUrl($chatUrl);
        }

        self::assertNotSame('', $baseUrl, 'Could not resolve OpenAI base URL. Set OPENAI_SERVICE_URL or configure config/services.php.');
        self::assertNotSame('', $chatUrl, 'Could not resolve OpenAI chat URL. Set OPENAI_SERVICE_URL or configure config/services.php.');

        return [$baseUrl, $chatUrl];
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

