#!/usr/bin/env php
<?php

declare(strict_types=1);

$rootPath = dirname(__DIR__);
$publicPath = $rootPath . '/public';
$outputFile = $publicPath . '/assets/bundle-size.json';

if (!is_dir($publicPath)) {
    fwrite(STDERR, "Directorio public/ no encontrado.\n");
    exit(1);
}

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($publicPath, FilesystemIterator::SKIP_DOTS)
);

$jsTotal = 0;
$cssTotal = 0;
$jsCount = 0;
$cssCount = 0;
$files = [];

foreach ($iterator as $file) {
    if (!$file->isFile()) {
        continue;
    }

    $ext = strtolower($file->getExtension());
    if (!in_array($ext, ['js', 'css'], true)) {
        continue;
    }

    $bytes = (int) $file->getSize();
    $relativePath = 'public/' . ltrim(str_replace($publicPath, '', $file->getPathname()), DIRECTORY_SEPARATOR);

    if ($ext === 'js') {
        $jsTotal += $bytes;
        $jsCount++;
    } else {
        $cssTotal += $bytes;
        $cssCount++;
    }

    $files[] = [
        'path' => $relativePath,
        'bytes' => $bytes,
        'human' => humanSize($bytes),
        'type' => $ext,
    ];
}

usort($files, static fn(array $a, array $b): int => $b['bytes'] <=> $a['bytes']);
$top = array_slice($files, 0, 5);

$payload = [
    'generatedAt' => gmdate('c'),
    'totals' => [
        'js' => [
            'bytes' => $jsTotal,
            'human' => humanSize($jsTotal),
            'count' => $jsCount,
        ],
        'css' => [
            'bytes' => $cssTotal,
            'human' => humanSize($cssTotal),
            'count' => $cssCount,
        ],
    ],
    'top' => $top,
];

if (!is_dir(dirname($outputFile))) {
    mkdir(dirname($outputFile), 0775, true);
}

file_put_contents(
    $outputFile,
    json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
);

echo "Bundle size generado en {$outputFile}\n";

function humanSize(int $bytes): string
{
    if ($bytes < 1024) {
        return $bytes . ' B';
    }

    $units = ['KB', 'MB', 'GB'];
    $value = $bytes / 1024;
    $unitIndex = 0;

    while ($value >= 1024 && $unitIndex < count($units) - 1) {
        $value /= 1024;
        $unitIndex++;
    }

    return sprintf('%.1f %s', $value, $units[$unitIndex]);
}
