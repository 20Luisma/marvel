<?php

declare(strict_types=1);

$pageTitle = 'Clean Marvel Album — Álbumes';
$additionalStyles = ['/assets/css/albums.css'];
$activeTopAction = 'home';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../albums/hero.php';
require_once __DIR__ . '/../albums/list.php';

$scripts = ['/assets/js/albums.js'];
require_once __DIR__ . '/../layouts/footer.php';
