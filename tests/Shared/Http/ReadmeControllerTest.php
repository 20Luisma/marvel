<?php

declare(strict_types=1);

namespace Tests\Shared\Http;

use PHPUnit\Framework\TestCase;
use Src\Shared\Http\ReadmeController;

final class ReadmeControllerTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/readme-controller-' . uniqid('', true);
        mkdir($this->tempDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tempDir);
    }

    public function testItRendersMarkdownReadme(): void
    {
        $markdown = "# Titulo\n\nTexto **negrita** y *cursiva*.\n\n- Uno\n- Dos\n\n```php\necho 'hola';\n```";
        file_put_contents($this->tempDir . '/README.md', $markdown);

        $controller = new ReadmeController($this->tempDir);

        ob_start();
        $controller();
        $output = ob_get_clean();

        self::assertIsString($output);
        self::assertStringContainsString('<h1>Titulo</h1>', $output);
        self::assertStringContainsString('<strong>negrita</strong>', $output);
        self::assertStringContainsString('<em>cursiva</em>', $output);
        self::assertStringContainsString('<ul>', $output);
        self::assertStringContainsString('<li>Uno</li>', $output);
        self::assertStringContainsString('<pre><code>', $output);
    }

    public function testItReturns404WhenReadmeIsMissing(): void
    {
        $controller = new ReadmeController($this->tempDir);
        $previousStatus = http_response_code();

        ob_start();
        $controller();
        $output = ob_get_clean();
        $status = http_response_code();

        self::assertSame(404, $status);
        self::assertStringContainsString('README.md no encontrado', $output);

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
