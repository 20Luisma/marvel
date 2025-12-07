<?php

declare(strict_types=1);

namespace Tests\Security;

use App\Security\Http\ApiFirewall;
use PHPUnit\Framework\TestCase;

final class ApiFirewallTest extends TestCase
{
    private string $securityLog;

    protected function setUp(): void
    {
        parent::setUp();
        $this->securityLog = dirname(__DIR__, 2) . '/storage/logs/security.log';
        if (is_file($this->securityLog)) {
            @unlink($this->securityLog);
        }
    }

    public function testBlocksDuplicateKeysJson(): void
    {
        $_SERVER['CONTENT_TYPE'] = 'application/json';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['X_TRACE_ID'] = 'test-trace-firewall';
        $GLOBALS['__raw_input__'] = '{"a":1,"a":2}';

        $firewall = new ApiFirewall();

        ob_start();
        $result = $firewall->handle('POST', '/api/test');
        $output = ob_get_clean();

        self::assertFalse($result);
        self::assertIsString($output);
        self::assertStringContainsString('"estado":"error"', $output);
        $this->assertLogExists();
    }

    public function testBlocksAttackPatternInHtml(): void
    {
        $_SERVER['CONTENT_TYPE'] = 'application/json';
        $_SERVER['HTTP_ACCEPT'] = 'text/html';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $GLOBALS['__raw_input__'] = '{"q":"<script>alert(1)</script>"}';

        $firewall = new ApiFirewall();
        ob_start();
        $result = $firewall->handle('POST', '/dashboard');
        $output = ob_get_clean();

        self::assertFalse($result);
        self::assertIsString($output);
        self::assertStringContainsString('Petición inválida', $output);
        $this->assertLogExists();
    }

    private function assertLogExists(): void
    {
        // Puede loguear vía logger o fallback; damos tiempo al FS.
        self::assertTrue(is_file($this->securityLog));
    }
}
