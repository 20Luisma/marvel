<section id="tests-box" class="card section-lined rounded-2xl p-6 shadow-xl space-y-4">
  <div class="flex items-center justify-between gap-3">
    <div class="flex items-center gap-3">
      <h2 class="text-3xl text-white">🧪 Tests</h2>
      <span id="test-status-chip" class="hidden inline-flex items-center gap-2 rounded-full px-3 py-1.5 text-[0.7rem] font-black uppercase tracking-[0.18em] leading-none border border-slate-500/40 bg-slate-800/80 text-slate-200 shadow-sm transition-colors duration-150">—</span>
    </div>
    <button id="tests-toggle" type="button" class="inline-flex items-center gap-2 rounded-full px-3 py-1.5 text-[0.7rem] font-black uppercase tracking-[0.18em] leading-none border border-slate-500/40 bg-slate-800/80 text-slate-200 shadow-sm transition-colors duration-150 focus:outline-none focus:ring-2 focus:ring-emerald-300/30" aria-expanded="true" aria-controls="tests-box" onkeydown="if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); this.click(); }">
      <span class="tests-toggle-label">Ocultar tests</span>
      <svg class="tests-toggle-icon h-3.5 w-3.5 opacity-90 transition-transform duration-150 transform rotate-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
        <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 10.939l3.71-3.71a.75.75 0 111.06 1.061l-4.24 4.243a.75.75 0 01-1.06 0L5.21 8.27a.75.75 0 01.02-1.06z" clip-rule="evenodd"/>
      </svg>
    </button>
  </div>
  <div id="tests-body" class="space-y-4">
    <p class="text-sm text-slate-300 leading-relaxed">
      Ejecuta la batería de PHPUnit y revisa los resultados en tiempo real sin salir del panel.
    </p>
    <button id="run-tests-btn" class="btn btn-secondary w-full">
      ▶ Ejecutar tests
    </button>
    <p id="test-runner-message" class="hidden text-sm font-semibold"></p>
    <div id="tests-summary" class="hidden space-y-4">
      <div id="test-summary-grid" class="grid grid-cols-2 gap-3 text-sm"></div>
      <div id="test-status-breakdown" class="flex flex-wrap gap-2 text-xs"></div>
      <div class="space-y-2 relative">
        <h3 class="text-xs font-semibold tracking-[0.28em] uppercase text-slate-300 relative z-10">Resultados por test</h3>
        <div id="tests-tbody" class="relative z-10 pr-1 sm:pr-2 pt-2 pb-8 space-y-4"></div>
      </div>
    </div>
  </div>
</section>
