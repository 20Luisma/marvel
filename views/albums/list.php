<main id="main-content" tabindex="-1" role="main" class="site-main">
  <div class="max-w-7xl mx-auto py-8 px-4 space-y-8">
    <div id="focus-edit-backdrop" class="focus-edit-backdrop hidden"></div>
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
      <aside class="lg:col-span-1 self-start space-y-6">
        <?php require_once __DIR__ . '/../partials/create-album-form.php'; ?>
        <?php require_once __DIR__ . '/../partials/activity-box.php'; ?>
      </aside>

      <section class="lg:col-span-2 space-y-8">
        <section class="card section-lined rounded-2xl p-6 shadow-xl">
          <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div class="flex items-center gap-3">
              <h2 class="text-3xl text-white">Mis Álbumes</h2>
              <span id="albums-counter" class="inline-flex items-center px-2 py-1 rounded-lg text-xs font-bold bg-slate-700 text-gray-100 border border-slate-600">
                0 álbumes
              </span>
            </div>
            <div class="flex flex-col sm:flex-row sm:items-end sm:gap-3 w-full sm:w-auto sm:ml-auto">
              <div class="sm:w-72">
                <label class="block text-xs text-gray-400 mb-1" for="filter-q">Buscar (por nombre)</label>
                <input id="filter-q" type="search" placeholder="Ej: vengadores, 2025…"
                  class="w-full px-3 py-2 rounded-lg bg-slate-700 border border-slate-600 focus:border-[var(--marvel)] focus:ring-0 focus:outline-none text-white"/>
              </div>
              <div class="sm:w-52">
                <label class="block text-xs text-gray-400 mb-1" for="filter-order">Ordenar</label>
                <select id="filter-order"
                  class="w-full px-3 py-2 rounded-lg bg-slate-700 border border-slate-600 focus:border-[var(--marvel)] focus:ring-0 focus:outline-none text-white">
                  <option value="recent">Recientes</option>
                  <option value="az">A → Z</option>
                  <option value="za">Z → A</option>
                </select>
              </div>
              <button id="refresh-albums" class="hidden">Refrescar</button>
            </div>
          </div>

          <div id="albums-grid" class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3"></div>
        </section>
      </section>
    </div>
  </div>

</main>
