<?php

declare(strict_types=1);

namespace Src\Shared\Markdown;

final class MarkdownRenderer
{
    public static function render(string $markdown): string
    {
        $normalized = str_replace(["\r\n", "\r"], "\n", $markdown);
        $escaped = htmlspecialchars($normalized, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $escaped = preg_replace_callback(
            '/```([\s\S]*?)```/',
            static fn (array $matches): string => '<pre><code>' . $matches[1] . '</code></pre>',
            $escaped
        ) ?? $escaped;

        $escaped = preg_replace('/^### (.+)$/m', '<h3>$1</h3>', $escaped) ?? $escaped;
        $escaped = preg_replace('/^## (.+)$/m', '<h2>$1</h2>', $escaped) ?? $escaped;
        $escaped = preg_replace('/^# (.+)$/m', '<h1>$1</h1>', $escaped) ?? $escaped;

        $escaped = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $escaped) ?? $escaped;
        $escaped = preg_replace('/\*(.+?)\*/s', '<em>$1</em>', $escaped) ?? $escaped;

        $escaped = preg_replace('/\[(.*?)\]\((.*?)\)/', '<a href="$2" target="_blank" rel="noopener">$1</a>', $escaped) ?? $escaped;

        $escaped = preg_replace('/^> (.+)$/m', '<blockquote>$1</blockquote>', $escaped) ?? $escaped;

        $escaped = preg_replace('/^- (.+)$/m', '<li>$1</li>', $escaped) ?? $escaped;
        $escaped = preg_replace_callback(
            '/(?:^|\n)(<li>.*?(?:\n<li>.*?)*)(?=\n{2,}|\n<h[123]|$)/s',
            static fn (array $matches): string => "\n<ul>\n" . $matches[1] . "\n</ul>\n",
            $escaped
        ) ?? $escaped;

        $segments = preg_split('/\n{2,}/', trim($escaped)) ?: [];
        $html = '';

        foreach ($segments as $segment) {
            $trimmed = trim($segment);

            if ($trimmed === '') {
                continue;
            }

            if (preg_match('/^<(h[1-6]|pre|ul|blockquote)/', $trimmed) === 1) {
                $html .= $trimmed . "\n";
                continue;
            }

            $lines = preg_split('/\n/', $trimmed) ?: [];
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }

                if (preg_match('/^<(h[1-6]|pre|ul|li|blockquote)/', $line) === 1) {
                    $html .= $line . "\n";
                } else {
                    $html .= '<p>' . $line . '</p>' . "\n";
                }
            }
        }

        return $html;
    }
}
