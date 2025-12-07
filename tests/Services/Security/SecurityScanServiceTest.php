<?php

declare(strict_types=1);

namespace App\Services\Security;

/**
 * Simple doubles to control built-in function calls inside SecurityScanService without touching real network.
 */
final class SecurityScanServiceTestDoubles
{
    public static array|false|null $headers = null;
    public static mixed $streamSocketClientReturn = null;
    public static array|null $streamContextParams = null;
    public static array|false|null $opensslParseResult = null;

    public static function reset(): void
    {
        self::$headers = null;
        self::$streamSocketClientReturn = null;
        self::$streamContextParams = null;
        self::$opensslParseResult = null;
    }
}

function get_headers(string $url, $format = false)
{
    if (SecurityScanServiceTestDoubles::$headers !== null) {
        return SecurityScanServiceTestDoubles::$headers;
    }

    return \get_headers($url, $format);
}

function stream_socket_client(
    string $hostname,
    &$errno,
    &$errstr,
    float $timeout = 0.0,
    int $flags = 0,
    $context = null
) {
    if (SecurityScanServiceTestDoubles::$streamSocketClientReturn !== null) {
        $errno = 0;
        $errstr = '';
        return SecurityScanServiceTestDoubles::$streamSocketClientReturn;
    }

    return \stream_socket_client($hostname, $errno, $errstr, $timeout, $flags, $context);
}

function stream_context_get_params($stream): array
{
    if (SecurityScanServiceTestDoubles::$streamContextParams !== null) {
        return SecurityScanServiceTestDoubles::$streamContextParams;
    }

    return \stream_context_get_params($stream);
}

function openssl_x509_parse($certificate, bool $shortnames = true)
{
    if (SecurityScanServiceTestDoubles::$opensslParseResult !== null) {
        return SecurityScanServiceTestDoubles::$opensslParseResult;
    }

    return \openssl_x509_parse($certificate, $shortnames);
}

function fclose($stream): bool
{
    return true;
}

namespace Tests\Services\Security;

use App\Services\Security\SecurityScanService;
use App\Services\Security\SecurityScanServiceTestDoubles;
use PHPUnit\Framework\TestCase;

final class SecurityScanServiceTest extends TestCase
{
    private string $cacheFile;
    private ?string $originalCache = null;

    protected function setUp(): void
    {
        SecurityScanServiceTestDoubles::reset();
        $this->cacheFile = dirname(__DIR__, 3) . '/storage/security/security.json';

        if (file_exists($this->cacheFile)) {
            $content = file_get_contents($this->cacheFile);
            $this->originalCache = $content === false ? null : $content;
        }

        if (file_exists($this->cacheFile)) {
            unlink($this->cacheFile);
        }
    }

    protected function tearDown(): void
    {
        SecurityScanServiceTestDoubles::reset();

        if ($this->originalCache !== null) {
            file_put_contents($this->cacheFile, $this->originalCache);
        } elseif (file_exists($this->cacheFile)) {
            unlink($this->cacheFile);
        }
    }

    public function testScanSecurityHeadersCalculatesGradeAndMissing(): void
    {
        SecurityScanServiceTestDoubles::$headers = [
            'Content-Security-Policy' => "default-src 'self'",
            'X-Frame-Options' => 'DENY',
            'X-Content-Type-Options' => 'nosniff',
            'Strict-Transport-Security' => 'max-age=63072000',
            'Referrer-Policy' => 'no-referrer',
        ];

        $service = new SecurityScanService('https://example.com');
        $result = $service->scanSecurityHeaders();

        self::assertSame('B', $result['grade']);
        self::assertArrayHasKey('CSP', $result['headers']);
        self::assertContains('Permissions-Policy', $result['missing']);
        self::assertSame(2, count($result['missing']));
        self::assertTrue($result['real']);
    }

    public function testScanSecurityHeadersHandlesConnectionFailure(): void
    {
        SecurityScanServiceTestDoubles::$headers = false;

        $service = new SecurityScanService('https://example.com');
        $result = $service->scanSecurityHeaders();

        self::assertSame('N/A', $result['grade']);
        self::assertSame('No se pudo conectar con el servidor', $result['error']);
    }

    public function testScanMozillaObservatoryRejectsInvalidHost(): void
    {
        $service = new SecurityScanService('notaurl');
        $result = $service->scanMozillaObservatory();

        self::assertSame('Host inválido para análisis SSL', $result['error']);
        self::assertSame(0, $result['score']);
        self::assertSame('N/A', $result['grade']);
    }

    public function testScanMozillaObservatoryCalculatesScoreAndGrade(): void
    {
        SecurityScanServiceTestDoubles::$streamSocketClientReturn = 'socket';
        SecurityScanServiceTestDoubles::$streamContextParams = [
            'options' => [
                'ssl' => [
                    'peer_certificate' => 'dummy-cert',
                ],
            ],
        ];
        SecurityScanServiceTestDoubles::$opensslParseResult = [
            'validTo_time_t' => time() + 10000,
            'validFrom_time_t' => time() - 10000,
            'issuer' => ['O' => 'CA'],
            'extensions' => ['subjectAltName' => 'DNS:example.com'],
            'signatureTypeSN' => 'sha256WithRSAEncryption',
        ];

        $service = new SecurityScanService('https://example.com');
        $result = $service->scanMozillaObservatory();

        self::assertSame(100, $result['score']);
        self::assertSame('A+', $result['grade']);
        self::assertSame(5, $result['tests_passed']);
        self::assertSame(0, $result['tests_failed']);
        self::assertSame(5, $result['tests_quantity']);
        self::assertTrue($result['real']);
    }

    public function testCacheIsSavedAndFreshnessDetected(): void
    {
        $service = new SecurityScanService('https://example.com');

        $payload = ['lastScan' => date('c'), 'securityHeaders' => ['grade' => 'A']];
        $service->saveCache($payload);

        self::assertFileExists($this->cacheFile);
        self::assertTrue($service->isCacheFresh());

        $loaded = $service->loadCache();
        self::assertIsArray($loaded);
        self::assertSame('A', $loaded['securityHeaders']['grade'] ?? null);
    }
}
