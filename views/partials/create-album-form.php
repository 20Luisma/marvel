<section class="card section-lined rounded-2xl p-6 shadow-xl">
  <h2 class="text-3xl text-white mb-4">Crear Álbum</h2>
  <form id="album-form" class="space-y-4">
    <input id="album-name" class="w-full px-4 py-3 rounded-lg bg-slate-700 border border-slate-600 focus:border-[var(--marvel)] focus:ring-0 focus:outline-none text-white" type="text" placeholder="Nombre del nuevo álbum" required>
    <div class="grid grid-cols-1 gap-4">
      <label class="space-y-2 text-sm font-semibold text-gray-300">
        <span>Portada (URL)</span>
        <input id="album-cover-url" class="w-full px-3 py-2 rounded-lg bg-slate-700 border border-slate-600 focus:border-[var(--marvel)] focus:ring-0 focus:outline-none text-white" type="url" placeholder="https://example.com/imagen.jpg">
      </label>
      <label class="space-y-2 text-sm font-semibold text-gray-300">
        <span>Subir portada</span>
        <input id="album-cover-file" class="w-full text-sm text-gray-200 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-[var(--marvel)] file:text-white hover:file:bg-red-700 cursor-pointer" type="file" accept="image/png,image/jpeg,image/webp">
      </label>
    </div>
    <button class="btn btn-primary w-full" type="submit">Crear Álbum</button>
  </form>
  <p id="album-message" class="text-sm mt-4 hidden msg-hidden"></p>
</section>
