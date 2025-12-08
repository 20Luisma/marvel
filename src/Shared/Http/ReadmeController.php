<?php

declare(strict_types=1);

namespace App\Shared\Http;

use App\Shared\Markdown\MarkdownRenderer;

final class ReadmeController
{
    public function __construct(private readonly string $projectRoot)
    {
    }

    public function __invoke(): void
    {
        $readmePath = $this->projectRoot . '/README.md';

        if (!is_file($readmePath)) {
            http_response_code(404);
            echo '<p>README.md no encontrado</p>';
            return;
        }

        $markdown = file_get_contents($readmePath);
        if ($markdown === false) {
            http_response_code(500);
            echo '<p>No se pudo leer el README.md</p>';
            return;
        }

        $html = MarkdownRenderer::render($markdown);

        header('Content-Type: text/html; charset=utf-8');
        echo $html;
    }
}
