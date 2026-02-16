<?php

declare(strict_types=1);

$pageTitle = 'MARVEL TECH – Clean Marvel Album';
$additionalStyles = ['/assets/css/marveltech.css']; 
$activeTopAction = 'marveltech';
require_once __DIR__ . '/../layouts/header.php';
?>

<!-- HERO / HEADER -->
<header class="app-hero app-hero--tech">
  <div class="app-hero__inner">
    <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
      <div class="space-y-3 max-w-3xl">
        <h1 class="app-hero__title text-4xl sm:text-5xl">Clean Architecture with Marvel</h1>
        <p class="text-lg text-gray-300 max-w-2xl leading-snug sm:text-xl">
          Panel visual con los 3 pilares del proyecto.
        </p>
        <p class="app-hero__meta text-base text-slate-300">
          Visión completa de seguridad, arquitectura y buenas prácticas de Clean Marvel Album.
        </p>
      </div>
    </div>
    <div class="flex w-full flex-wrap items-center gap-4 md:flex-nowrap md:gap-6">
      <?php require_once __DIR__ . '/../partials/top-actions.php'; ?>
    </div>
  </div>
</header>

<main id="main-content" tabindex="-1" role="main" class="site-main">
  <div class="max-w-7xl mx-auto py-8 px-4 space-y-8">
    <section class="card section-lined rounded-2xl p-6 shadow-xl tech-panel">
      <header class="space-y-2 mb-6">
        <h2 class="sonar-hero-title text-4xl text-white">MARVEL TECH</h2>
      </header>

      <!-- CARRUSEL DE IMÁGENES -->
      <div class="marvel-tech-carousel">
        <button class="carousel-arrow carousel-prev" type="button" aria-label="Imagen anterior">‹</button>

        <div class="carousel-track">
          <div class="carousel-slide is-active">
            <img src="/assets/images/marvel-tech/1.png" alt="10 puntos de seguridad de Clean Marvel Album">
          </div>
          <div class="carousel-slide">
            <img src="/assets/images/marvel-tech/2.png" alt="Flujo arquitectónico de Clean Marvel Album">
          </div>
          <div class="carousel-slide">
            <img src="/assets/images/marvel-tech/3.png" alt="Best Practices Album de Clean Marvel Album">
          </div>
        </div>

        <button class="carousel-arrow carousel-next" type="button" aria-label="Imagen siguiente">›</button>
      </div>

      <!-- Indicadores de navegación -->
      <div class="carousel-indicators">
        <span class="carousel-indicator is-active" data-index="0" aria-label="Ir a imagen 1"></span>
        <span class="carousel-indicator" data-index="1" aria-label="Ir a imagen 2"></span>
        <span class="carousel-indicator" data-index="2" aria-label="Ir a imagen 3"></span>
      </div>
    </section>
  </div>
</main>

<?php
$scripts = ['/assets/js/marveltech-carousel.js'];
require_once __DIR__ . '/../layouts/footer.php';
