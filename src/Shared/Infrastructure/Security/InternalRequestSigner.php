<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Security;

final class InternalRequestSigner
{
    private const DEFAULT_TOLERANCE = 300;

    public function __construct(
        private readonly string $sharedSecret,
        private readonly string $callerId = 'clean-marvel-app'
    ) {
    }

    /**
     * @param array<string, string> $extraHeaders
     * @return array<string, string>
     */
    public function sign(string $method, string $url, string $rawBody, ?int $timestamp = null, array $extraHeaders = []): array
    {
        $ts = $timestamp ?? time();
        $signature = $this->computeSignature($method, $url, $rawBody, $ts);

        return array_merge($extraHeaders, [
            'X-Internal-Signature' => $signature,
            'X-Internal-Timestamp' => (string) $ts,
            'X-Internal-Caller' => $this->callerId,
        ]);
    }

    public function computeSignature(string $method, string $url, string $rawBody, int $timestamp): string
    {
        $path = parse_url($url, PHP_URL_PATH) ?: '/';
        $canonical = strtoupper($method) . "\n" . $path . "\n" . $timestamp . "\n" . hash('sha256', $rawBody);

        return hash_hmac('sha256', $canonical, $this->sharedSecret);
    }

    public function isValid(string $method, string $path, string $rawBody, string $providedSignature, int $timestamp, int $toleranceSeconds = self::DEFAULT_TOLERANCE): bool
    {
        if ($providedSignature === '' || $timestamp <= 0) {
            return false;
        }

        if (abs(time() - $timestamp) > $toleranceSeconds) {
            return false;
        }

        $expected = $this->computeSignature($method, $path, $rawBody, $timestamp);

        return hash_equals($expected, $providedSignature);
    }
}
