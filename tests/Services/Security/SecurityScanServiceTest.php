<?php

declare(strict_types=1);

namespace App\Services\Security;

// Mocks configuration
class MockConfig {
    public static $headers = [];
    public static $cert = [];
    public static $socketFail = false;
    public static $headersFail = false;
    
    public static function reset() {
        self::$headers = [
            0 => 'HTTP/1.1 200 OK',
            'Content-Type' => 'text/html',
            'Content-Security-Policy' => 'default-src \'self\'',
            'X-Frame-Options' => 'DENY',
            'Strict-Transport-Security' => 'max-age=31536000',
        ];
        self::$cert = [
            'validTo_time_t' => time() + 3600,
            'validFrom_time_t' => time() - 3600,
            'issuer' => ['O' => 'Trusted CA'],
            'extensions' => ['subjectAltName' => 'DNS:example.com'],
            'signatureTypeSN' => 'sha256WithRSAEncryption',
        ];
        self::$socketFail = false;
        self::$headersFail = false;
    }
}

// Mocks for global functions in the same namespace as the class under test
function get_headers(string $url, bool $associative = false): array|false
{
    if ($url === 'https://fail.com' || MockConfig::$headersFail) {
        return false;
    }
    
    return MockConfig::$headers;
}

function stream_socket_client(
    string $remote_socket,
    &$errno,
    &$errstr,
    ?float $timeout = null,
    int $flags = STREAM_CLIENT_CONNECT,
    $context = null
) {
    if (str_contains($remote_socket, 'fail.com') || MockConfig::$socketFail) {
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
        return MockConfig::$cert;
    }
    return false;
}

namespace Tests\Services\Security;

use App\Services\Security\SecurityScanService;
use App\Services\Security\MockConfig; // Import MockConfig from global namespace
use PHPUnit\Framework\TestCase;

class SecurityScanServiceTest extends TestCase
{
    private string $cacheFile;

    protected function setUp(): void
    {
        MockConfig::reset();
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

    public function testCacheExpired(): void
    {
        $service = new SecurityScanService('https://example.com');
        
        // Save cache with old date (25 hours ago)
        $oldDate = date('c', time() - 90000);
        $data = ['lastScan' => $oldDate, 'test' => 'data'];
        $service->saveCache($data);
        
        $this->assertFalse($service->isCacheFresh());
    }

    public function testDetectEnvironmentUrl(): void
    {
        // Default (local)
        $service = new SecurityScanService();
        // Reflection to check private property targetUrl
        $reflection = new \ReflectionClass($service);
        $property = $reflection->getProperty('targetUrl');
        $property->setAccessible(true);
        
        // Should be production URL in local env
        $this->assertSame('https://iamasterbigschool.contenido.creawebes.com', $property->getValue($service));
    }

    public function testCalculateSecurityGradeLow(): void
    {
        // Only one header present
        MockConfig::$headers = [
            0 => 'HTTP/1.1 200 OK',
            'Content-Type' => 'text/html',
            'X-Frame-Options' => 'DENY',
        ];
        
        $service = new SecurityScanService('https://example.com');
        $result = $service->scanSecurityHeaders();
        
        // 1 out of 7 headers ~ 14% -> F
        $this->assertEquals('F', $result['grade']);
    }

    public function testCalculateSecurityGradeMedium(): void
    {
        // 4 headers present ~ 57% -> C
        MockConfig::$headers = [
            0 => 'HTTP/1.1 200 OK',
            'Content-Type' => 'text/html',
            'X-Frame-Options' => 'DENY',
            'X-Content-Type-Options' => 'nosniff',
            'Strict-Transport-Security' => 'max-age=31536000',
            'Referrer-Policy' => 'no-referrer',
        ];
        
        $service = new SecurityScanService('https://example.com');
        $result = $service->scanSecurityHeaders();
        
        $this->assertEquals('C', $result['grade']);
    }

    public function testSSLTestsFailures(): void
    {
        // Expired cert, weak signature, untrusted issuer
        MockConfig::$cert = [
            'validTo_time_t' => time() - 3600, // Expired
            'validFrom_time_t' => time() - 7200,
            'issuer' => ['O' => 'Untrusted'], // Missing 'O' check logic? No, code checks isset($cert['issuer']['O'])
            // Let's remove 'O' to fail
            'extensions' => [], // Missing SAN
            'signatureTypeSN' => 'md5WithRSAEncryption', // Weak
        ];
        unset(MockConfig::$cert['issuer']['O']);
        
        $service = new SecurityScanService('https://example.com');
        $result = $service->scanMozillaObservatory();
        
        // 1 passed (validFrom), 4 failed
        $this->assertEquals(1, $result['tests_passed']);
        $this->assertEquals(4, $result['tests_failed']);
        $this->assertEquals('F', $result['grade']);
    }
}
