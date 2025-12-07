<?php

declare(strict_types=1);

namespace App\Security\Http;

if (!function_exists('App\Security\Http\http_response_code')) {
    function http_response_code(int $code = 0): int|bool {
        if ($code !== 0) {
            $GLOBALS['response_code'] = $code;
        }
        return $GLOBALS['response_code'] ?? 200;
    }
}

if (!function_exists('App\Security\Http\header')) {
    function header(string $header, bool $replace = true, int $response_code = 0): void {
        $GLOBALS['headers'][] = $header;
    }
}

namespace Tests\Security\Http;

use App\Security\Http\ApiFirewall;
use App\Security\Logging\SecurityLogger;
use PHPUnit\Framework\TestCase;

class ApiFirewallTest extends TestCase
{
    private $logger;
    private $firewall;

    private $logFile;

    protected function setUp(): void
    {
        $this->logFile = __DIR__ . '/../../storage/logs/test_security.log';
        if (file_exists($this->logFile)) {
            unlink($this->logFile);
        }
        
        $this->logger = new SecurityLogger($this->logFile);
        $this->firewall = new ApiFirewall($this->logger);
        
        $GLOBALS['headers'] = [];
        $GLOBALS['response_code'] = 200;
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['CONTENT_TYPE'] = 'application/json';
        $_SERVER['CONTENT_LENGTH'] = 100;
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['headers']);
        unset($GLOBALS['response_code']);
        if (file_exists($this->logFile)) {
            unlink($this->logFile);
        }
    }

    public function testHandleAllowed(): void
    {
        $_SERVER['MARVEL_RAW_BODY'] = json_encode(['key' => 'value']);
        
        $result = $this->firewall->handle('POST', '/api/test');
        
        $this->assertTrue($result);
    }

    public function testHandleJsonInvalid(): void
    {
        $_SERVER['MARVEL_RAW_BODY'] = '{invalid json';
        
        ob_start();
        $result = $this->firewall->handle('POST', '/api/test');
        $output = ob_get_clean();
        
        $this->assertFalse($result);
        $this->assertEquals(400, $GLOBALS['response_code']);
        $this->assertStringContainsString('Payload inválido', $output);
    }

    public function testHandleAttackPattern(): void
    {
        $_SERVER['MARVEL_RAW_BODY'] = json_encode(['key' => 'drop table users']);
        
        ob_start();
        $result = $this->firewall->handle('POST', '/api/test');
        $output = ob_get_clean();
        
        $this->assertFalse($result);
        $this->assertEquals(400, $GLOBALS['response_code']);
    }

    public function testHandleAllowlist(): void
    {
        $_SERVER['MARVEL_RAW_BODY'] = '{invalid'; // Should be ignored
        
        $result = $this->firewall->handle('POST', '/login');
        
        $this->assertTrue($result);
    }
}
