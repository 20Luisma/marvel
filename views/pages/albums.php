<?php

declare(strict_types=1);

$pageTitle = 'Clean Marvel Album — Álbumes';
$additionalStyles = ['/assets/css/albums.css', '/assets/css/readme.css'];
$activeTopAction = 'home';

require __DIR__ . '/../layouts/header.php';
require __DIR__ . '/../albums/hero.php';
require __DIR__ . '/../albums/list.php';

$scripts = ['/assets/js/albums.js', '/assets/js/readme.js'];
require __DIR__ . '/../layouts/footer.php';
