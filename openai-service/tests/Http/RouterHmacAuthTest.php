<?php

declare(strict_types=1);

namespace Creawebes\OpenAI\Tests\Http;

use PHPUnit\Framework\TestCase;
use Creawebes\OpenAI\Http\Router;

/**
 * Tests for HMAC signature verification on POST /v1/chat.
 *
 * Validates that the Router correctly rejects requests with:
 * - Missing HMAC signatures
 * - Invalid/tampered HMAC signatures
 * - Expired timestamps (replay attack protection)
 * - Missing INTERNAL_API_KEY in strict mode (fail-closed)
 *
 * And accepts requests with valid signatures.
 */
final class RouterHmacAuthTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        $this->router = new Router();

        $_SERVER['SCRIPT_NAME'] = '/index.php';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        unset($_SERVER['HTTP_ORIGIN']);
        unset($_SERVER['HTTP_X_INTERNAL_SIGNATURE']);
        unset($_SERVER['HTTP_X_INTERNAL_TIMESTAMP']);
        unset($_SERVER['HTTP_X_INTERNAL_CALLER']);
        unset($_SERVER['__RAW_INPUT__']);
    }

    protected function tearDown(): void
    {
        // Clean up environment variables
        $this->clearEnv('INTERNAL_API_KEY');
        $this->clearEnv('HMAC_STRICT_MODE');
        $this->clearEnv('STAGING_INSECURE_BYPASS');
        $this->clearEnv('ALLOWED_INTERNAL_CALLERS');
    }

    public function testRejectsMissingSignature(): void
    {
        $this->setEnv('INTERNAL_API_KEY', 'test-shared-secret');

        // No signature headers set
        ob_start();
        $this->router->handle('POST', '/v1/chat');
        $output = ob_get_clean();

        $decoded = json_decode((string) $output, true);

        $this->assertIsArray($decoded);
        $this->assertSame('Unauthorized request', $decoded['error'] ?? null);
    }

    public function testRejectsInvalidSignature(): void
    {
        $this->setEnv('INTERNAL_API_KEY', 'test-shared-secret');

        $body = '{"messages":[]}';
        $_SERVER['__RAW_INPUT__'] = $body;
        $_SERVER['HTTP_X_INTERNAL_SIGNATURE'] = 'definitely-wrong-signature';
        $_SERVER['HTTP_X_INTERNAL_TIMESTAMP'] = (string) time();
        $_SERVER['HTTP_X_INTERNAL_CALLER'] = 'localhost';

        ob_start();
        $this->router->handle('POST', '/v1/chat');
        $output = ob_get_clean();

        $decoded = json_decode((string) $output, true);

        $this->assertIsArray($decoded);
        $this->assertSame('Unauthorized request', $decoded['error'] ?? null);
    }

    public function testRejectsExpiredTimestamp(): void
    {
        $secret = 'test-shared-secret';
        $this->setEnv('INTERNAL_API_KEY', $secret);

        $body = '{"messages":[]}';
        $expiredTimestamp = time() - 600; // 10 minutes ago (threshold is 300s)
        $bodyHash = hash('sha256', $body);
        $canonical = "POST\n/v1/chat\n{$expiredTimestamp}\n{$bodyHash}";
        $signature = hash_hmac('sha256', $canonical, $secret);

        $_SERVER['__RAW_INPUT__'] = $body;
        $_SERVER['HTTP_X_INTERNAL_SIGNATURE'] = $signature;
        $_SERVER['HTTP_X_INTERNAL_TIMESTAMP'] = (string) $expiredTimestamp;
        $_SERVER['HTTP_X_INTERNAL_CALLER'] = 'localhost';

        ob_start();
        $this->router->handle('POST', '/v1/chat');
        $output = ob_get_clean();

        $decoded = json_decode((string) $output, true);

        $this->assertIsArray($decoded);
        $this->assertSame('Unauthorized request', $decoded['error'] ?? null);
    }

    public function testRejectsWhenNoKeyInStrictMode(): void
    {
        $this->clearEnv('INTERNAL_API_KEY');
        $this->setEnv('HMAC_STRICT_MODE', 'true');

        ob_start();
        $this->router->handle('POST', '/v1/chat');
        $output = ob_get_clean();

        $decoded = json_decode((string) $output, true);

        $this->assertIsArray($decoded);
        $this->assertSame('Unauthorized request', $decoded['error'] ?? null);
    }

    public function testAcceptsValidSignature(): void
    {
        $secret = 'test-shared-secret';
        $this->setEnv('INTERNAL_API_KEY', $secret);
        $this->setEnv('OPENAI_API_KEY', 'fake-key-for-test');

        // We need to bypass the actual OpenAI call, so we use a staging bypass
        // to demonstrate the HMAC passes, then test the actual HMAC verify path.
        // Instead, let's just verify the signature passes validation
        // by generating a correct signature.
        $body = '{"messages":[{"role":"user","content":"test"}]}';
        $timestamp = time();
        $bodyHash = hash('sha256', $body);
        $canonical = "POST\n/v1/chat\n{$timestamp}\n{$bodyHash}";
        $signature = hash_hmac('sha256', $canonical, $secret);

        $_SERVER['__RAW_INPUT__'] = $body;
        $_SERVER['HTTP_X_INTERNAL_SIGNATURE'] = $signature;
        $_SERVER['HTTP_X_INTERNAL_TIMESTAMP'] = (string) $timestamp;
        $_SERVER['HTTP_X_INTERNAL_CALLER'] = 'localhost';

        ob_start();
        $this->router->handle('POST', '/v1/chat');
        $output = ob_get_clean();

        $decoded = json_decode((string) $output, true);

        $this->assertIsArray($decoded);
        // If HMAC passed, we should NOT get "Unauthorized request"
        // (we may get an OpenAI error since the key is fake, but NOT an auth error)
        $this->assertNotSame('Unauthorized request', $decoded['error'] ?? null);
    }

    public function testBypassAllowsRequestWithoutSignature(): void
    {
        $this->setEnv('STAGING_INSECURE_BYPASS', 'true');
        $this->setEnv('OPENAI_API_KEY', 'fake-key-for-test');

        $_SERVER['__RAW_INPUT__'] = '{"messages":[]}';

        ob_start();
        $this->router->handle('POST', '/v1/chat');
        $output = ob_get_clean();

        $decoded = json_decode((string) $output, true);

        $this->assertIsArray($decoded);
        // Should not be auth error — bypass worked
        $this->assertNotSame('Unauthorized request', $decoded['error'] ?? null);
    }

    private function setEnv(string $key, string $value): void
    {
        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
    }

    private function clearEnv(string $key): void
    {
        putenv($key);
        unset($_ENV[$key], $_SERVER[$key]);
    }
}
