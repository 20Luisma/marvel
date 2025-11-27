<?php

declare(strict_types=1);

require_once __DIR__ . '/../layouts/header.php';
?>

<main id="main-content" tabindex="-1" role="main" class="site-main">
  <div class="max-w-xl mx-auto py-16 px-4">
    <section class="bg-slate-900/70 border border-slate-800 rounded-2xl shadow-xl p-8 space-y-6">
      <header class="space-y-2">
        <p class="text-sm text-[var(--marvel)] font-semibold uppercase tracking-[0.2em]">Acceso</p>
        <h1 class="text-4xl font-bold text-white">Inicia sesión</h1>
        <p class="text-base text-gray-300">Usa las credenciales de administrador para entrar a las secciones protegidas.</p>
      </header>

      <?php if (!empty($error)): ?>
        <div class="rounded-lg border border-red-500/50 bg-red-500/10 px-4 py-3 text-sm text-red-100" role="alert" aria-live="assertive">
          <?= e((string) $error) ?>
        </div>
      <?php endif; ?>

      <form method="post" action="/login" class="space-y-5">
        <?= csrf_field() ?>
        <div class="space-y-2">
          <label class="block text-sm text-gray-300" for="email">Correo</label>
          <input
            id="email"
            name="email"
            type="email"
            required
            autocomplete="username"
            class="w-full rounded-lg border border-slate-700 bg-slate-800 px-4 py-3 text-white focus:border-[var(--marvel)] focus:ring-0"
            placeholder="marvel@gmail.com"
            value="<?= e((string) ($_POST['email'] ?? '')) ?>"
          >
        </div>

        <div class="space-y-2">
          <label class="block text-sm text-gray-300" for="password">Contraseña</label>
          <input
            id="password"
            name="password"
            type="password"
            required
            autocomplete="current-password"
            class="w-full rounded-lg border border-slate-700 bg-slate-800 px-4 py-3 text-white focus:border-[var(--marvel)] focus:ring-0"
            placeholder="marvel2025"
          >
        </div>

        <button type="submit" class="btn btn-primary w-full justify-center">Entrar</button>
        <p class="text-xs text-gray-400 text-center">Usuario único: marvel@gmail.com · Contraseña: marvel2025</p>
      </form>

      <?php if (!empty($isAuthenticated)): ?>
        <div class="space-y-3 border-t border-slate-800 pt-4">
          <p class="text-sm text-gray-300">Ya iniciaste sesión. Puedes salir para cambiar de cuenta.</p>
          <form method="post" action="/logout" class="flex">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-secondary w-full justify-center">Cerrar sesión</button>
          </form>
        </div>
      <?php endif; ?>
    </section>
  </div>
</main>

<?php
$scripts = [];
require_once __DIR__ . '/../layouts/footer.php';
