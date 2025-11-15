<?php

declare(strict_types=1);

$pageTitle = 'Clean Marvel Album — Create Your Comic';
$additionalStyles = ['/assets/css/comic.css', '/assets/css/microservice-communication.css', '/assets/css/comic-fixes.css'];
$activeTopAction = 'comic';
require __DIR__ . '/../layouts/header.php';
?>

<!-- HERO / HEADER -->
<header class="app-hero">
  <div class="app-hero__inner">
    <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
      <div class="space-y-3 max-w-3xl">
        <h1 class="app-hero__title text-4xl sm:text-5xl">Clean Architecture with Marvel</h1>
        <p class="text-lg text-gray-300 max-w-2xl leading-snug sm:text-xl">
          Crea tu cómic de forma divertida con IA.
        </p>
        <p class="app-hero__meta text-base text-slate-300">
          Selecciona héroes increíbles y arma historias inolvidables con un flujo limpio.
        </p>
      </div>
    </div>
    <div class="flex w-full flex-wrap items-center gap-4 md:flex-nowrap md:gap-6">
      <?php require __DIR__ . '/../partials/top-actions.php'; ?>
    </div>
  </div>
</header>

<main class="site-main">
  <div class="max-w-7xl mx-auto py-8 px-4 space-y-8">
    <div class="flex flex-col lg:flex-row lg:items-start gap-8">
      <!-- ASIDE: Crear cómic + actividad -->
      <aside class="lg:w-1/3 space-y-6">
        <section id="comic-creation-card" class="card section-lined rounded-2xl p-6 shadow-xl space-y-5 relative">
          <div id="comic-creation-content" class="space-y-5">
            <div class="space-y-2">
              <h2 class="text-3xl text-white">Crear tu cómic</h2>
              <p class="text-sm text-gray-300/80">Selecciona tus héroes favoritos, deja que la IA construya la historia y revisa el resultado al instante.</p>
            </div>

            <form id="comic-form" class="space-y-4">
              <input type="hidden" id="selected-heroes-input" name="heroIds" value="[]">

              <div class="rounded-xl border border-slate-700/70 bg-slate-900/65 px-4 py-4 space-y-3">
                <div class="flex items-center justify-between gap-3">
                  <p class="text-xs uppercase tracking-[0.28em] text-gray-400">Héroes seleccionados</p>
                  <span id="selected-heroes-count" class="text-xs font-semibold text-amber-300/80">0</span>
                </div>
                <p id="selected-heroes-empty" class="text-sm text-gray-400 bg-slate-900/40 border border-dashed border-slate-700 rounded-xl px-4 py-3 text-center">Aún no seleccionas héroes.</p>
                <div id="selected-heroes-list" class="flex flex-wrap gap-2"></div>
              </div>

              <div class="rounded-xl border border-slate-700/70 bg-slate-900/65 px-4 py-4 space-y-4">
                <div class="flex justify-center items-center gap-4 comic-buttons-container">
                  <button type="submit" id="comic-generate" class="btn btn-primary">
                    <span>Generar cómic</span>
                  </button>
                  <button type="button" id="comic-compare-rag" class="btn btn-rag">🧠 Comparar héroes (RAG)</button>
                  <button type="button" id="comic-cancel" class="btn btn-secondary">Cancelar</button>
                </div>
                <p id="hero-warning" class="rag-result-box hidden" role="alert"></p>
                <div id="rag-result-box" class="rag-result-box hidden" role="status" aria-live="polite"></div>
              </div>

              <p id="comic-message" class="text-sm hidden msg-hidden"></p>
            </form>
          </div>

          <!-- Panel comunicación microservicios (cómic) -->
          <div id="microservice-comm-panel" class="msc-panel msc-hidden">
            <div class="msc-header">
              <span class="msc-title w-full text-center msc-title-canal">Canal Microservicios</span>
            </div>
            <p class="msc-subtitle">Web principal ↔ Microservicios Open IA</p>
            <ul class="msc-steps">
              <li class="msc-step" data-step="send">▶ Enviando datos…</li>
              <li class="msc-step" data-step="process">⚙ Procesando en Microservicios Open IA…</li>
              <li class="msc-step" data-step="return">⬅ Devolviendo a la Web principal…</li>
            </ul>
            <p id="msc-status-text" class="msc-status"></p>
            <button id="msc-retry" class="msc-retry msc-hidden" type="button">Reintentar</button>
          </div>

          <!-- Panel comunicación RAG (modificado) -->
          <div id="rag-comm-panel" class="msc-panel msc-hidden">
            <div class="msc-header">
              <!-- quitamos el circulito/badge -->
              <span class="msc-title w-full text-center msc-title-canal">Sistema RAG</span>
            </div>
            <p class="msc-subtitle">Web principal ↔ Microservicios Open IA</p>
            <ul class="msc-steps">
              <li class="msc-step" data-step="send">▶ Enviando héroes seleccionados…</li>
              <li class="msc-step" data-step="process">🤖 Consultando la base RAG…</li>
              <li class="msc-step" data-step="relay">📡 Comunicando con OpenAI…</li>
              <li class="msc-step" data-step="return">⬅ Respondiendo a la Web principal…</li>
            </ul>
            <p id="rag-msc-status-text" class="msc-status"></p>
            <button id="rag-msc-retry" class="msc-retry msc-hidden" type="button">Reintentar</button>
          </div>
        </section>

        <section class="card section-lined rounded-2xl p-6 shadow-xl space-y-4">
          <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-3xl text-white">🔔 Actividad</h2>
            <div class="flex items-center gap-2">
              <button id="comic-activity-prev" class="btn btn-secondary text-xs">⟵ Anterior</button>
              <button id="comic-activity-next" class="btn btn-secondary text-xs">Siguiente ⟶</button>
              <button id="comic-activity-clear" class="btn btn-danger text-xs">Limpiar</button>
            </div>
          </div>

          <div id="comic-activity-empty" class="bg-slate-900/70 border border-slate-700 rounded-xl p-4 text-sm text-gray-300 italic">
            Todavía no registramos actividad. Selecciona héroes o genera tu cómic para comenzar.
          </div>

          <div id="comic-activity-view" class="hidden bg-slate-900/80 border border-slate-700 rounded-xl p-4 space-y-2">
            <div class="flex items-center justify-between">
              <span id="comic-activity-tag" class="inline-flex items-center px-2 py-1 rounded-md text-[0.65rem] font-black uppercase tracking-[0.18em] border">—</span>
              <div class="flex items-center gap-2 text-xs text-gray-400">
                <time id="comic-activity-date" class="font-mono text-amber-300/80">—</time>
                <span id="comic-activity-counter">0/0</span>
              </div>
            </div>
            <p id="comic-activity-title" class="text-sm text-gray-100 leading-tight">—</p>
          </div>
        </section>
      </aside>

      <!-- MAIN: Comic generation -->
      <section class="lg:w-2/3 flex flex-col gap-6">
        <section id="hero-selection-section" class="card section-lined rounded-2xl p-6 shadow-xl space-y-6">
          <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div class="space-y-1">
              <h2 class="text-3xl text-white">Héroes disponibles</h2>
            </div>
            <div class="flex flex-col sm:flex-row sm:items-center gap-3">
              <label class="flex items-center gap-3 bg-slate-800/60 border border-slate-700 rounded-lg px-3 py-2 shadow-inner">
                <span class="text-xs uppercase tracking-[0.25em] text-gray-400">Buscar</span>
                <input id="comic-hero-search" type="search" placeholder="Ej: Spider, Wakanda, Doom..." class="bg-transparent border-0 focus:ring-0 focus:outline-none text-sm text-gray-100 placeholder:text-slate-500 min-w-[12rem]" />
              </label>
              <span id="comic-hero-count" class="inline-flex items-center justify-center rounded-lg bg-slate-800/70 border border-slate-700 px-3 py-1 text-xs font-semibold text-gray-200">0 héroes</span>
            </div>
          </div>

          <div id="comic-heroes-grid" class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3"></div>
          <p id="comic-heroes-empty" class="hidden text-sm text-gray-400 italic text-center py-10">No encontramos héroes que coincidan con tu búsqueda.</p>
        </section>

        <section id="comic-slideshow-section" class="hidden relative card section-lined rounded-2xl p-6 shadow-xl space-y-4">
          <button id="close-comic-result" type="button" class="absolute top-4 right-4 text-gray-400 hover:text-white z-20">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
          </button>
          <h2 id="generated-comic-title" class="text-4xl text-white text-center"></h2>
          <div id="hero-slideshow" class="relative w-full max-w-2xl mx-auto">
            <div id="slideshow-container" class="relative h-80 overflow-hidden rounded-lg">
            </div>
            <button id="slideshow-prev" type="button" class="absolute top-0 left-0 z-10 flex items-center justify-center h-full px-4 cursor-pointer group focus:outline-none">
              <span class="inline-flex items-center justify-center w-10 h-10 rounded-full bg-white/30 group-hover:bg-white/50 group-focus:ring-4 group-focus:ring-white">
                <svg class="w-4 h-4 text-white" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 6 10">
                  <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 1 1 5l4 4" />
                </svg>
              </span>
            </button>
            <button id="slideshow-next" type="button" class="absolute top-0 right-0 z-10 flex items-center justify-center h-full px-4 cursor-pointer group focus:outline-none">
              <span class="inline-flex items-center justify-center w-10 h-10 rounded-full bg-white/30 group-hover:bg-white/50 group-focus:ring-4 group-focus:ring-white">
                <svg class="w-4 h-4 text-white" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 6 10">
                  <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 9 4-4-4-4" />
                </svg>
              </span>
            </button>
          </div>
        </section>
        <section id="comic-story-section" class="hidden card section-lined rounded-2xl p-6 shadow-xl space-y-6">
          <div class="space-y-3">
            <h3 class="text-3xl text-white">Historia generada</h3>
            <div class="space-y-2">
              <p id="comic-output-story-summary" class="text-base text-gray-300 leading-relaxed"></p>
            </div>
            <div id="comic-output-panels" class="space-y-4"></div>
            <p id="comic-output-panels-empty" class="text-sm text-gray-400">Las viñetas generadas se mostrarán en este espacio.</p>
            <div class="flex flex-col">
              <button id="btn-audio-comic" class="btn btn-secondary" style="margin-top:0.5rem;">🔊 Escuchar cómic</button>
              <audio id="audio-player-comic" controls style="display:none; margin-top:0.5rem; width:100%;"></audio>
            </div>
          </div>
        </section>
        <section id="rag-result-section" class="hidden relative card section-lined rounded-2xl p-6 shadow-xl space-y-5">
          <button id="close-rag-result" type="button" class="absolute top-4 right-4 text-gray-400 hover:text-white z-20">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
          </button>
          <div class="flex flex-col gap-4">
            <div class="flex flex-col gap-2 text-center">
              <h3 class="text-3xl text-white">Comparación de héroes</h3>
              <p class="text-sm text-gray-300/80">Resumen generado por RAG según tu selección actual.</p>
            </div>
            <div id="rag-result-heroes" class="rag-heroes-preview"></div>
            <div id="rag-result-text" class="rag-result-text"></div>
            <div class="flex flex-col">
              <button id="btn-audio-rag" class="btn btn-secondary" style="margin-top:0.5rem;">🔊 Escuchar comparación</button>
              <audio id="audio-player-rag" controls style="display:none; margin-top:0.5rem; width:100%;"></audio>
            </div>
          </div>
        </section>
      </section>
    </div>
  </div>
</main>

<?php
$scripts = ['/assets/js/microservice-communication.js', '/assets/js/comic.js'];
require __DIR__ . '/../layouts/footer.php';
