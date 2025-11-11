<section class="card section-lined rounded-2xl p-6 shadow-xl">
  <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
    <h2 class="text-3xl text-white">🔔 Actividad</h2>
    <div class="flex items-center gap-2">
      <button id="album-activity-prev" class="btn btn-secondary text-xs">⟵ Anterior</button>
      <button id="album-activity-next" class="btn btn-secondary text-xs">Siguiente ⟶</button>
      <button id="clear-album-activity" class="btn btn-danger text-xs">Limpiar</button>
    </div>
  </div>

  <div id="album-activity-empty" class="bg-slate-900/70 border border-slate-700 rounded-xl p-4 text-sm text-gray-300 italic">
    No hay actividad registrada.
  </div>

  <div id="album-activity-view" class="hidden bg-slate-900/80 border border-slate-700 rounded-xl p-4 space-y-2">
    <div class="flex items-center justify-between">
      <span id="album-activity-tag" class="inline-flex items-center px-2 py-1 rounded-md text-[0.65rem] font-black uppercase tracking-[0.18em] border">—</span>
      <div class="flex items-center gap-2 text-xs text-gray-400">
        <time id="album-activity-date" class="font-mono text-amber-300/80">—</time>
        <span id="album-activity-counter">0/0</span>
      </div>
    </div>
    <p id="album-activity-title" class="text-sm text-gray-100 leading-tight">—</p>
  </div>
</section>
