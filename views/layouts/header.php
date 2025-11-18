<?php

declare(strict_types=1);

$pageTitle = $pageTitle ?? 'Clean Marvel Album';
$bodyClass = $bodyClass ?? 'text-gray-200 min-h-screen bg-[#0b0d17]';
$additionalStyles = $additionalStyles ?? [];
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
  <link href="https://fonts.googleapis.com/css2?family=Bangers&family=Roboto:wght@400;700&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="/assets/css/main.css"/>
  <?php foreach ($additionalStyles as $style): ?>
    <link rel="stylesheet" href="<?= htmlspecialchars($style, ENT_QUOTES, 'UTF-8') ?>"/>
  <?php endforeach; ?>
</head>
<body class="<?= htmlspecialchars($bodyClass, ENT_QUOTES, 'UTF-8') ?>">
  <a class="skip-link" href="#main-content">Saltar al contenido principal</a>
