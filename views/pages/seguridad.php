<?php
declare(strict_types=1);
use App\Infrastructure\Http\AuthGuards;

AuthGuards::requireAuth();
AuthGuards::requireAdmin();

$activeTopAction = 'security';
?>
<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Security Dashboard – Clean Marvel Album</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Bangers&family=Roboto:wght@400;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="/assets/css/main.css" />
  <style>
    .security-card {
      background: linear-gradient(135deg, rgba(15, 23, 42, 0.9), rgba(30, 41, 59, 0.8));
      border: 1px solid rgba(148, 163, 184, 0.2);
      border-radius: 12px;
      padding: 1.5rem;
      transition: all 0.3s ease;
    }

    .security-card:hover {
      border-color: rgba(148, 163, 184, 0.4);
      transform: translateY(-2px);
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
    }

    .security-card__header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 1.5rem;
      padding-bottom: 1rem;
      border-bottom: 1px solid rgba(148, 163, 184, 0.1);
    }

    .security-card__header h3 {
      font-size: 1.25rem;
      font-weight: 700;
      color: #fff;
    }

    .grade {
      font-size: 1.5rem;
      font-weight: 700;
      padding: 0.5rem 1rem;
      border-radius: 8px;
      min-width: 60px;
      text-align: center;
    }

    .grade-a {
      background: linear-gradient(135deg, #10b981, #059669);
      color: #fff;
    }

    .grade-b {
      background: linear-gradient(135deg, #f59e0b, #d97706);
      color: #fff;
    }

    .grade-f {
      background: linear-gradient(135deg, #ef4444, #dc2626);
      color: #fff;
    }

    .grade-na {
      background: linear-gradient(135deg, #64748b, #475569);
      color: #fff;
    }

    .security-card__body {
      display: flex;
      flex-direction: column;
      gap: 1rem;
    }

    .metric {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 0.75rem;
      background: rgba(15, 23, 42, 0.5);
      border-radius: 8px;
    }

    .metric .label {
      color: #cbd5e1;
      font-size: 0.95rem;
    }

    .metric .value {
      color: #fff;
      font-weight: 700;
      font-size: 1.1rem;
    }

    .metric-list {
      padding: 0.75rem;
      background: rgba(15, 23, 42, 0.5);
      border-radius: 8px;
    }

    .metric-list .label {
      color: #cbd5e1;
      font-size: 0.95rem;
      display: block;
      margin-bottom: 0.5rem;
    }

    .metric-list ul {
      list-style: none;
      padding: 0;
      margin: 0;
    }

    .metric-list li {
      color: #f87171;
      font-size: 0.9rem;
      padding: 0.25rem 0;
    }

    .skeleton-loader {
      animation: pulse 1.5s ease-in-out infinite;
    }

    .skeleton-line {
      height: 20px;
      background: linear-gradient(90deg, rgba(148, 163, 184, 0.1) 25%, rgba(148, 163, 184, 0.2) 50%, rgba(148, 163, 184, 0.1) 75%);
      border-radius: 4px;
      margin-bottom: 0.75rem;
    }

    .skeleton-line.short {
      width: 60%;
    }

    @keyframes pulse {
      0%, 100% {
        opacity: 1;
      }
      50% {
        opacity: 0.5;
      }
    }

    .error-message {
      padding: 1rem;
      background: rgba(239, 68, 68, 0.1);
      border: 1px solid rgba(239, 68, 68, 0.3);
      border-radius: 8px;
      color: #fca5a5;
      text-align: center;
    }

    .security-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
      gap: 1.5rem;
      margin-top: 2rem;
    }
  </style>
</head>

<body class="text-gray-200 min-h-screen bg-[#0b0d17]">

  <!-- HERO / HEADER -->
  <header class="app-hero app-hero--tech">
    <div class="app-hero__inner">
      <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
        <div class="space-y-3 max-w-3xl">
          <h1 class="app-hero__title text-4xl sm:text-5xl">Clean Architecture with Marvel</h1>
          <p class="text-lg text-gray-300 max-w-2xl leading-snug sm:text-xl">
            Análisis automático de seguridad del servidor Marvel Album.
          </p>
          <p class="app-hero__meta text-base text-slate-300">
            Monitoreamos headers de seguridad y configuración usando SecurityHeaders.com y Mozilla Observatory.
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
      <section class="space-y-10" aria-live="polite">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
          <div class="space-y-2">
            <h2 class="text-4xl text-white">Marvel Security Board</h2>
            <p class="text-slate-300 text-sm" id="last-scan">Cargando...</p>
          </div>
          <div class="flex flex-col items-center gap-4 text-center">
            <button id="rescan-btn" class="btn btn-primary inline-flex items-center gap-2 mx-auto">
              <span>Actualizar</span>
            </button>
          </div>
        </div>


        <!-- Contenedor principal con borde azul -->
        <div class="rounded-3xl border border-blue-500/40 bg-[#050814] px-6 py-8 shadow-xl shadow-black/40">
          <div class="security-grid">
            <div id="security-headers-card" class="security-card">
              <div class="skeleton-loader">
                <div class="skeleton-line"></div>
                <div class="skeleton-line"></div>
                <div class="skeleton-line short"></div>
              </div>
            </div>

            <div id="mozilla-card" class="security-card">
              <div class="skeleton-loader">
                <div class="skeleton-line"></div>
                <div class="skeleton-line"></div>
                <div class="skeleton-line short"></div>
              </div>
            </div>
          </div>

          <!-- Explicación de seguridad -->
          <div class="mt-8 pt-6 border-t border-slate-700/50">
            <h3 class="text-lg font-semibold text-white mb-3">¿Qué estamos analizando?</h3>
            <div class="space-y-4 text-sm text-slate-300 leading-relaxed">
              <div>
                <p class="text-cyan-400 font-semibold mb-1">🛡️ Headers de Seguridad HTTP</p>
                <p class="text-slate-400">
                  Analizamos los headers de seguridad del servidor (CSP, HSTS, X-Frame-Options, etc.) que protegen contra ataques XSS, clickjacking y otras vulnerabilidades web. 
                  Estos headers son la primera línea de defensa del navegador.
                </p>
              </div>
              <div>
                <p class="text-cyan-400 font-semibold mb-1">🔐 Certificado SSL/TLS</p>
                <p class="text-slate-400">
                  Verificamos la validez del certificado SSL, algoritmos de cifrado, fecha de expiración y configuración HTTPS. 
                  Un certificado válido garantiza que la comunicación entre el servidor y los usuarios está encriptada y es segura.
                </p>
              </div>
              <p class="text-xs text-slate-500 mt-4">
                Los datos se obtienen analizando directamente tu servidor en producción (<code class="text-cyan-400 bg-slate-900 px-1 rounded">https://iamasterbigschool.contenido.creawebes.com</code>). 
                Resultados actualizados cada 24 horas o manualmente con el botón "Actualizar".
              </p>
            </div>
          </div>
        </div>
      </section>
    </div>
  </main>

  <!-- FOOTER -->
  <footer class="site-footer">
    <small>© creawebes 2025 · Clean Marvel Album</small>
  </footer>

  <?php $cspNonce = $_SERVER['CSP_NONCE'] ?? null; ?>
  <script src="/assets/js/seguridad.js" defer<?= $cspNonce ? ' nonce="' . htmlspecialchars($cspNonce, ENT_QUOTES, 'UTF-8') . '"' : '' ?>></script>
</body>

</html>
