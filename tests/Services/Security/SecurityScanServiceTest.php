<?php

declare(strict_types=1);

namespace App\Services\Security;

// Mocks for global functions in the same namespace as the class under test
function get_headers(string $url, bool $associative = false): array|false
{
    if ($url === 'https://fail.com') {
        return false;
    }
    
    return [
        0 => 'HTTP/1.1 200 OK',
        'Content-Type' => 'text/html',
        'Content-Security-Policy' => 'default-src \'self\'',
        'X-Frame-Options' => 'DENY',
        'Strict-Transport-Security' => 'max-age=31536000',
    ];
}

function stream_socket_client(
    string $remote_socket,
    &$errno,
    &$errstr,
    ?float $timeout = null,
    int $flags = STREAM_CLIENT_CONNECT,
    $context = null
) {
    if (str_contains($remote_socket, 'fail.com')) {
        return false;
    }
    
    // Return a dummy resource
    return fopen('php://memory', 'r+');
}

function stream_context_get_params($stream): array
{
    // Return dummy SSL context params
    return [
        'options' => [
            'ssl' => [
                'peer_certificate' => 'dummy_cert_resource'
            ]
        ]
    ];
}

function openssl_x509_parse($cert)
{
    if ($cert === 'dummy_cert_resource') {
        return [
            'validTo_time_t' => time() + 3600,
            'validFrom_time_t' => time() - 3600,
            'issuer' => ['O' => 'Trusted CA'],
            'extensions' => ['subjectAltName' => 'DNS:example.com'],
            'signatureTypeSN' => 'sha256WithRSAEncryption',
        ];
    }
    return false;
}

namespace Tests\Services\Security;

use App\Services\Security\SecurityScanService;
use PHPUnit\Framework\TestCase;

class SecurityScanServiceTest extends TestCase
{
    private string $cacheFile;

    protected function setUp(): void
    {
        $this->cacheFile = __DIR__ . '/../../../storage/security/security.json';
        if (file_exists($this->cacheFile)) {
            unlink($this->cacheFile);
        }
    }

    protected function tearDown(): void
    {
        if (file_exists($this->cacheFile)) {
            unlink($this->cacheFile);
        }
    }

    public function testScanSecurityHeadersHappyPath(): void
    {
        $service = new SecurityScanService('https://example.com');
        $result = $service->scanSecurityHeaders();

        $this->assertArrayHasKey('grade', $result);
        $this->assertNotEquals('N/A', $result['grade']);
        $this->assertArrayHasKey('headers', $result);
        $this->assertArrayHasKey('CSP', $result['headers']);
        $this->assertArrayHasKey('HSTS', $result['headers']);
        $this->assertTrue($result['real']);
    }

    public function testScanSecurityHeadersError(): void
    {
        $service = new SecurityScanService('https://fail.com');
        $result = $service->scanSecurityHeaders();

        $this->assertArrayHasKey('error', $result);
        $this->assertEquals('N/A', $result['grade']);
    }

    public function testScanMozillaObservatoryHappyPath(): void
    {
        $service = new SecurityScanService('https://example.com');
        $result = $service->scanMozillaObservatory();

        $this->assertArrayHasKey('grade', $result);
        $this->assertArrayHasKey('score', $result);
        $this->assertEquals(100, $result['score']); // All tests passed in mock
        $this->assertEquals('A+', $result['grade']);
        $this->assertEquals(5, $result['tests_passed']);
    }

    public function testScanMozillaObservatoryError(): void
    {
        $service = new SecurityScanService('https://fail.com');
        $result = $service->scanMozillaObservatory();

        $this->assertArrayHasKey('error', $result);
        $this->assertEquals(0, $result['score']);
    }

    public function testCacheOperations(): void
    {
        $service = new SecurityScanService('https://example.com');
        
        $this->assertFalse($service->isCacheFresh());
        
        $data = ['lastScan' => date('c'), 'test' => 'data'];
        $service->saveCache($data);
        
        $this->assertTrue($service->isCacheFresh());
        
        $loaded = $service->loadCache();
        $this->assertEquals($data, $loaded);
    }
}
