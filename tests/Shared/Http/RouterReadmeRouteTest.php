<?php

declare(strict_types=1);

namespace Tests\Shared\Http;

use PHPUnit\Framework\TestCase;
use Src\Shared\Http\ReadmeController;
use Src\Shared\Http\Router;

final class RouterReadmeRouteTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/router-readme-' . uniqid('', true);
        mkdir($this->tempDir, 0777, true);
        file_put_contents($this->tempDir . '/README.md', "# Router Test\n\nContenido.");
    }

    protected function tearDown(): void
    {
        unset($_SERVER['HTTP_ACCEPT']);
        $this->removeDir($this->tempDir);
        http_response_code(200);
    }

    public function testHandleGetReadmeDeliversHtmlResponse(): void
    {
        $container = [
            'readme.show' => fn () => new ReadmeController($this->tempDir),
            'useCases' => [],
        ];

        $router = new Router($container);
        $_SERVER['HTTP_ACCEPT'] = 'text/html';
        http_response_code(200);
        $previousStatus = http_response_code();

        ob_start();
        $router->handle('GET', '/readme/raw');
        $output = ob_get_clean();
        $status = http_response_code();

        self::assertIsString($output);
        self::assertStringContainsString('<h1>Router Test</h1>', $output);
        self::assertSame(200, $status ?: 200);

        http_response_code($previousStatus ?: 200);
    }

    private function removeDir(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }

        rmdir($path);
    }
}
