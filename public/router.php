<?php

declare(strict_types=1);

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$fullPath = __DIR__ . $path;

// Legacy API aliases must always flow through the central Router.
// This prevents bypassing middleware if someone re-adds physical PHP files.
$forceRouterPaths = [
    '/api/marvel-agent.php',
    '/api/github-activity.php',
];

if (in_array($path, $forceRouterPaths, true)) {
    require __DIR__ . '/index.php';
    return;
}

if ($path !== '/' && (is_file($fullPath) || is_dir($fullPath))) {
    return false;
}

require __DIR__ . '/index.php';
