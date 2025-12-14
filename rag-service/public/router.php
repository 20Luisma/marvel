<?php

declare(strict_types=1);

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$fullPath = __DIR__ . $path;

if ($path !== '/' && (is_file($fullPath) || is_dir($fullPath))) {
    return false;
}

require __DIR__ . '/index.php';

