<?php

declare(strict_types=1);

namespace Tests\Shared;

use PHPUnit\Framework\TestCase;
use App\Shared\Markdown\MarkdownRenderer;

final class MarkdownRendererTest extends TestCase
{
    public function testRenderBuildsListsHeadingsAndLinks(): void
    {
        $markdown = <<<MD
# Título Principal

## Sección

- Item **uno**
- Item *dos*

Visita [Clean Marvel](https://example.com).
MD;

        $html = MarkdownRenderer::render($markdown);

        self::assertStringContainsString('<h1>Título Principal</h1>', $html);
        self::assertStringContainsString('<h2>Sección</h2>', $html);
        self::assertStringContainsString('<ul>', $html);
        self::assertStringContainsString('<strong>uno</strong>', $html);
        self::assertStringContainsString('<em>dos</em>', $html);
        self::assertStringContainsString('<a href="https://example.com" target="_blank" rel="noopener">Clean Marvel</a>', $html);
    }

    public function testRenderEscapesHtmlAndKeepsCodeBlocks(): void
    {
        $markdown = <<<MD
<script>alert('xss');</script>

```php
echo \"Hola\";
```
MD;

        $html = MarkdownRenderer::render($markdown);

        self::assertStringContainsString('&lt;script&gt;alert(&#039;xss&#039;);&lt;/script&gt;', $html);
        self::assertStringContainsString('<pre><code>php', $html);
        self::assertStringContainsString('echo \\&quot;Hola\\&quot;;', $html);
    }
}
