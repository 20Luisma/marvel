<?php

declare(strict_types=1);

namespace Tests\Api;

use App\Services\GithubClient;
use PHPUnit\Framework\TestCase;

final class GithubRepoBrowserTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($GLOBALS['__github_client_factory']);
        $_GET = [];
        $_SERVER = [];
        parent::tearDown();
    }

    public function testReturnsSuccessPayload(): void
    {
        $this->enableTestFlag();
        $_SERVER['HTTP_ACCEPT'] = 'application/json';
        $_GET['path'] = '';
        http_response_code(200);

        $fake = new class {
            public function listRepositoryContents(string $path): array
            {
                return [
                    'ok' => true,
                    'decoded' => [
                        ['type' => 'dir', 'name' => 'src', 'path' => 'src'],
                        ['type' => 'file', 'name' => 'README.md', 'path' => 'README.md', 'size' => 1],
                    ],
                    'error' => null,
                ];
            }
        };
        $GLOBALS['__github_client_factory'] = static fn () => $fake;

        $output = $this->includeScript();
        $payload = json_decode($output, true);

        self::assertNotSame('', $output);
        self::assertNotNull($payload, $output);
        self::assertIsArray($payload);
        self::assertSame('exito', $payload['estado']);
        self::assertSame('', $payload['path']);
        self::assertCount(2, $payload['items']);
        self::assertSame(200, http_response_code());
    }

    public function testReturnsErrorPayloadWhenClientFails(): void
    {
        $this->enableTestFlag();
        $_SERVER['HTTP_ACCEPT'] = 'application/json';
        $_GET['path'] = '';
        http_response_code(200);

        $fake = new class {
            public function listRepositoryContents(string $path): array
            {
                return [
                    'ok' => false,
                    'decoded' => ['message' => 'fail'],
                    'error' => null,
                ];
            }
        };
        $GLOBALS['__github_client_factory'] = static fn () => $fake;

        $output = $this->includeScript();
        $payload = json_decode($output, true);

        self::assertNotSame('', $output);
        self::assertNotNull($payload, $output);
        self::assertIsArray($payload);
        self::assertSame('error', $payload['estado']);
        self::assertSame('fail', $payload['mensaje']);
        self::assertSame(502, http_response_code());
    }

    public function testReturnsJson500OnException(): void
    {
        $this->enableTestFlag();
        $_SERVER['HTTP_ACCEPT'] = 'application/json';
        $_GET['path'] = '';
        http_response_code(200);

        $fake = new class {
            public function listRepositoryContents(string $path): array
            {
                throw new \RuntimeException('boom');
            }
        };
        $GLOBALS['__github_client_factory'] = static fn () => $fake;

        $output = $this->includeScript();
        $payload = json_decode($output, true);

        self::assertNotSame('', $output);
        self::assertNotNull($payload, $output);
        self::assertIsArray($payload);
        self::assertSame('error', $payload['estado']);
        self::assertSame('boom', $payload['mensaje']);
        self::assertSame(500, http_response_code());
    }

    private function includeScript(): string
    {
        $level = ob_get_level();
        ob_start();
        require dirname(__DIR__, 2) . '/public/api/github-repo-browser.php';
        $output = (string) ob_get_clean();
        while (ob_get_level() > $level) {
            ob_end_clean();
        }
        return $output;
    }

    private function enableTestFlag(): void
    {
        if (!defined('GITHUB_REPO_BROWSER_TEST')) {
            define('GITHUB_REPO_BROWSER_TEST', true);
        }
    }
}
