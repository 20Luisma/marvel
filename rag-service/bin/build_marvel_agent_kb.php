<?php

declare(strict_types=1);

/**
 * Generate Marvel Agent KB JSON from the master markdown.
 * Usage: php bin/build_marvel_agent_kb.php
 */

$serviceRoot = dirname(__DIR__);
$repoRoot = dirname($serviceRoot);
$sourceFile = $repoRoot . '/docs/marvel-agent/marvel-agent-memory.md';
$outputFile = $serviceRoot . '/storage/marvel_agent_kb.json';

if (!is_file($sourceFile)) {
    fwrite(STDERR, "No se encontró el archivo de memoria: {$sourceFile}\n");
    exit(1);
}

$content = file_get_contents($sourceFile);
if ($content === false) {
    fwrite(STDERR, "No se pudo leer el archivo: {$sourceFile}\n");
    exit(1);
}

$lines = preg_split('/\R/', $content) ?: [];

$sections = [];
$currentTitle = null;
$currentLines = [];
$counter = 0;

$flushSection = static function () use (&$sections, &$currentTitle, &$currentLines, &$counter): void {
    if ($currentTitle === null) {
        return;
    }

    $text = trim(implode("\n", $currentLines));
    $sections[] = [
        'id' => 'section-' . (++$counter),
        'title' => $currentTitle,
        'text' => $text,
    ];

    $currentTitle = null;
    $currentLines = [];
};

foreach ($lines as $line) {
    if (preg_match('/^(#{2,3})\s+(.*)$/', $line, $matches) === 1) {
        $flushSection();
        $currentTitle = trim($matches[2]);
        $currentLines = [];
        continue;
    }

    if ($currentTitle !== null) {
        $currentLines[] = $line;
    }
}

$flushSection();

if ($sections === []) {
    fwrite(STDERR, "No se encontraron secciones en el markdown.\n");
    exit(1);
}

$directory = dirname($outputFile);
if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
    fwrite(STDERR, "No se pudo crear el directorio: {$directory}\n");
    exit(1);
}

$json = json_encode($sections, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if ($json === false) {
    fwrite(STDERR, "No se pudo codificar el JSON.\n");
    exit(1);
}

if (file_put_contents($outputFile, $json) === false) {
    fwrite(STDERR, "No se pudo escribir el archivo: {$outputFile}\n");
    exit(1);
}

echo "KB generada en {$outputFile}\n";
