<section class="card section-lined rounded-2xl p-6 shadow-xl">
  <div class="flex items-center gap-3 mb-3">
    <h2 class="text-3xl text-white">🔄 Datos Demo</h2>
  </div>

  <div class="bg-slate-900/70 border border-slate-700 rounded-xl p-4 space-y-4">
    <p class="text-sm text-gray-300">
      Restaura los datos de demostración originales del proyecto (6 álbumes y 36 héroes).
    </p>
    
    <div class="flex items-center gap-2 text-xs text-gray-400">
      <span class="inline-flex items-center px-2 py-1 rounded-md bg-slate-700 border border-slate-600">
        📚 6 álbumes
      </span>
      <span class="inline-flex items-center px-2 py-1 rounded-md bg-slate-700 border border-slate-600">
        🦸 36 héroes
      </span>
    </div>

    <button id="reset-demo-btn" type="button" 
      class="w-full btn btn-secondary h-11 text-sm font-semibold flex items-center justify-center gap-2 hover:bg-amber-600/20 hover:text-amber-400 hover:border-amber-600/40 transition-colors">
      🔄 Restaurar datos de demo
    </button>
    
    <!-- Mensaje de resultado -->
    <div id="reset-demo-message" class="hidden rounded-lg p-3 text-sm font-medium"></div>
    
    <p class="text-xs text-gray-500 italic">
      ⚠️ Esta acción eliminará todos los datos actuales.
    </p>
  </div>
</section>
