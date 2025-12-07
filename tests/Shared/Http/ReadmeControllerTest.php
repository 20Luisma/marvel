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

    public function testItHandlesEmptyMarkdown(): void
    {
        file_put_contents($this->tempDir . '/README.md', '');

        $controller = new ReadmeController($this->tempDir);

        ob_start();
        $controller();
        $output = ob_get_clean();

        self::assertIsString($output);
    }

    public function testItHandlesMarkdownWithOnlyHeadings(): void
    {
        $markdown = "# Heading 1\n## Heading 2\n### Heading 3";
        file_put_contents($this->tempDir . '/README.md', $markdown);

        $controller = new ReadmeController($this->tempDir);

        ob_start();
        $controller();
        $output = ob_get_clean();

        self::assertStringContainsString('<h1>Heading 1</h1>', $output);
        self::assertStringContainsString('<h2>Heading 2</h2>', $output);
        self::assertStringContainsString('<h3>Heading 3</h3>', $output);
    }

    public function testItHandlesMarkdownWithLinks(): void
    {
        $markdown = "[Click here](https://example.com)";
        file_put_contents($this->tempDir . '/README.md', $markdown);

        $controller = new ReadmeController($this->tempDir);

        ob_start();
        $controller();
        $output = ob_get_clean();

        self::assertStringContainsString('href="https://example.com"', $output);
        self::assertStringContainsString('Click here', $output);
    }

    public function testItHandlesMarkdownWithUnicode(): void
    {
        $markdown = "# Título con ñ\n\nContenido con áéíóú y 中文";
        file_put_contents($this->tempDir . '/README.md', $markdown);

        $controller = new ReadmeController($this->tempDir);

        ob_start();
        $controller();
        $output = ob_get_clean();

        self::assertStringContainsString('Título con ñ', $output);
        self::assertStringContainsString('áéíóú', $output);
    }
}

