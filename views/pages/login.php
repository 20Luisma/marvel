<?php

declare(strict_types=1);

require_once __DIR__ . '/../layouts/header.php';
?>

<style>
  .login-card--blue {
    background: linear-gradient(135deg, #0b1224 0%, #0f1f3c 100%);
    border: 1px solid #1e3a8a;
    box-shadow: 0 15px 35px rgba(30, 58, 138, 0.25);
  }
  .login-eyebrow {
    color: #60a5fa;
  }
  .login-button--blue {
    background: linear-gradient(90deg, #2563eb 0%, #1d4ed8 100%);
    color: #fff;
    border: none;
    transition: transform 120ms ease, box-shadow 120ms ease;
  }
  .login-button--blue:hover {
    transform: translateY(-1px);
    box-shadow: 0 10px 20px rgba(37, 99, 235, 0.25);
  }
  .login-note {
    color: #93c5fd;
  }
  .site-footer {
    border-top-color: #2563eb;
  }
</style>

<main id="main-content" tabindex="-1" role="main" class="site-main">
  <div class="max-w-xl mx-auto py-16 px-4">
    <section class="login-card--blue rounded-2xl p-8 space-y-6">
      <header class="space-y-2">
        <p class="text-sm font-semibold uppercase tracking-[0.2em] login-eyebrow">Acceso Seguro</p>
        <h1 class="text-4xl font-bold text-white">Secret Room</h1>
        <p class="text-base text-gray-200">Protección dedicada para paneles internos. Usa las credenciales de seguridad.</p>
      </header>

      <?php if (!empty($error)): ?>
        <div class="rounded-lg border border-red-500/50 bg-red-500/10 px-4 py-3 text-sm text-red-100" role="alert" aria-live="assertive">
          <?= e((string) $error) ?>
        </div>
      <?php endif; ?>

      <form method="post" action="/login" class="space-y-5">
        <?= csrf_field() ?>
        <div class="space-y-2">
          <label class="block text-sm text-gray-200" for="email">Correo</label>
          <input
            id="email"
            name="email"
            type="email"
            required
            autocomplete="username"
            class="w-full rounded-lg border border-slate-700 bg-slate-800 px-4 py-3 text-white focus:border-sky-500 focus:ring-0"
            placeholder="seguridadmarvel@gmail.com"
            value="<?= e((string) ($_POST['email'] ?? '')) ?>"
          >
        </div>

        <div class="space-y-2">
          <label class="block text-sm text-gray-200" for="password">Contraseña</label>
          <input
            id="password"
            name="password"
            type="password"
            required
            autocomplete="current-password"
            class="w-full rounded-lg border border-slate-700 bg-slate-800 px-4 py-3 text-white focus:border-sky-500 focus:ring-0"
            placeholder="seguridadmarvel2025"
          >
        </div>

        <button type="submit" class="login-button--blue w-full py-3 rounded-lg font-semibold">Entrar</button>
        <p class="text-xs text-center login-note">Usuario único: seguridadmarvel@gmail.com · Contraseña: seguridadmarvel2025</p>
      </form>

      <?php if (!empty($isAuthenticated)): ?>
        <div class="space-y-3 border-t border-slate-700 pt-4">
          <p class="text-sm text-gray-200">Ya iniciaste sesión. Puedes cerrar para cambiar de cuenta.</p>
          <form method="post" action="/logout" class="flex">
            <?= csrf_field() ?>
            <button type="submit" class="w-full py-3 rounded-lg font-semibold bg-slate-700 text-white hover:bg-slate-600 transition">Cerrar sesión</button>
          </form>
        </div>
      <?php endif; ?>
    </section>
  </div>
</main>

<?php
$scripts = [];
require_once __DIR__ . '/../layouts/footer.php';
