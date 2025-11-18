<?php

declare(strict_types=1);

$pageTitle = 'Clean Marvel Album — Marvel Movies';
$additionalStyles = ['/assets/css/albums.css'];
$activeTopAction = 'movies';

require_once __DIR__ . '/../layouts/header.php';
?>

<header class="app-hero">
  <div class="app-hero__inner">
    <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
      <div class="space-y-3 max-w-3xl">
        <h1 class="app-hero__title text-4xl sm:text-5xl">Clean Architecture with Marvel</h1>
        <p class="text-lg text-gray-300 max-w-2xl leading-snug sm:text-xl">
          Explora el universo cinematográfico de Marvel.
        </p>
        <p class="app-hero__meta text-base text-slate-300">
          Espacio reservado para ampliar la experiencia con listados, filtros y trailers oficiales.
        </p>
      </div>
    </div>
    <div class="flex w-full flex-wrap items-center gap-4 md:flex-nowrap md:gap-6">
      <?php require_once __DIR__ . '/../partials/top-actions.php'; ?>
    </div>
  </div>
</header>

<main id="main-content" tabindex="-1" role="main" class="site-main">
  <div class="max-w-6xl mx-auto py-10 px-4">
    <section class="card section-lined rounded-2xl p-6 shadow-xl space-y-6">
      <header class="space-y-3">
        <h2 class="text-3xl text-white">Marvel Movies</h2>
      </header>

      <div id="movies-panel" class="rounded-2xl border border-slate-700/80 bg-slate-900/40 p-6 min-h-[240px]">
        <div class="flex flex-wrap items-center gap-3">
          <div
            id="movies-status"
            class="inline-flex items-center rounded-2xl border border-slate-800 bg-slate-950/70 px-4 py-2 text-sm font-semibold text-gray-200 shadow-sm animate-pulse"
            role="status"
            aria-live="polite"
            aria-atomic="true"
          >
            Cargando películas Marvel…
          </div>
          <div class="relative flex-1 min-w-[220px] max-w-3xl">
            <label class="sr-only" for="movies-search-input">Buscar película</label>
            <span class="pointer-events-none absolute left-3 top-1/2 flex -translate-y-1/2 text-gray-500">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M16 11a5 5 0 11-10 0 5 5 0 0110 0z" />
              </svg>
            </span>
            <input
              id="movies-search-input"
              type="search"
              aria-label="Buscar película"
              placeholder="Buscar película..."
              class="w-full rounded-2xl border border-slate-800 bg-slate-900/80 px-10 py-3 text-sm text-white placeholder:text-slate-500 focus:border-slate-800 focus:outline-none focus:ring-0"
            />
          </div>
        </div>
        <div id="movies-grid" class="mt-6 grid gap-4 md:grid-cols-2 lg:grid-cols-3"></div>
      </div>
      <section
        id="movie-detail"
        class="hidden relative rounded-2xl border border-rose-600/70 bg-slate-900/70 p-6 shadow-2xl space-y-6"
      >
        <div class="flex flex-col gap-6 md:flex-row">
          <figure class="w-full md:w-72 flex-shrink-0 overflow-hidden rounded-2xl border border-slate-800/70 bg-slate-950">
            <img
              id="movie-detail-poster"
              class="h-[360px] w-full object-cover"
              src="https://via.placeholder.com/500x750?text=Sin+poster"
              alt="Poster de la película"
              loading="lazy"
            />
          </figure>
          <div class="flex flex-1 flex-col gap-5">
            <div>
              <h3 id="movie-detail-title" class="text-3xl font-bold text-white">Título de la película</h3>
              <p id="movie-detail-meta" class="mt-2 text-sm uppercase tracking-wide text-gray-400">
                Año · ⭐ rating
              </p>
            </div>
            <p id="movie-detail-overview" class="flex-1 text-sm leading-relaxed text-gray-200">
              Sinopsis completa de la película.
            </p>
          </div>
        </div>
        <div class="flex justify-end">
          <button id="movie-detail-back" type="button" class="btn btn-primary">
            Volver a la cartelera
          </button>
        </div>
      </section>
    </section>
  </div>
</main>

<?php
$scripts = ['/assets/js/movies.js'];
require_once __DIR__ . '/../layouts/footer.php';
?>
