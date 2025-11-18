<?php

declare(strict_types=1);

$pageTitle = 'Marvel Oficial — Último video';
$cssPath = '/assets/css/marvel.css';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= htmlspecialchars($cssPath, ENT_QUOTES, 'UTF-8') ?>">
</head>
<body class="oficial-marvel-body">
  <a class="skip-link" href="#main-content">Saltar al contenido principal</a>
  <main class="oficial-marvel-wrapper" id="main-content" tabindex="-1" role="main">
    <header class="oficial-marvel-header">
      <p class="oficial-marvel-tag">Marvel Studios</p>
      <h1 class="oficial-marvel-title">Último video enviado desde n8n</h1>
      <p class="oficial-marvel-subtitle">Este módulo muestra en tiempo real el contenido recibido vía automatización.</p>
    </header>
    <section id="marvel-dynamic-section" class="oficial-marvel-card" aria-live="polite">
      <div id="marvel-loading" role="status" aria-live="polite" aria-atomic="true">Sincronizando con Marvel Entertainment…</div>
    </section>
  </main>

  <script src="/assets/js/oficial-marvel.js"></script>
</body>
</html>
