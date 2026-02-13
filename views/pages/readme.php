<?php

declare(strict_types=1);

$pageTitle = 'Clean Marvel Album — README';
$additionalStyles = ['/assets/css/readme.css'];
$activeTopAction = 'readme';
require_once __DIR__ . '/../layouts/header.php';
?>

<!-- HERO / HEADER -->
<header class="app-hero app-hero--tech">
  <div class="app-hero__inner">
    <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
      <div class="space-y-3 max-w-3xl">
        <h1 class="app-hero__title text-4xl sm:text-5xl">Clean Architecture with Marvel</h1>
        <p class="text-lg text-gray-300 max-w-2xl leading-snug sm:text-xl">
          Documentación viva, CI/CD y guías técnicas del proyecto.
        </p>
        <p class="app-hero__meta text-base text-slate-300">
          README sincronizado con el repositorio: arquitectura, comandos, pipelines y flujos de despliegue.
        </p>
      </div>
    </div>
    <div class="flex w-full flex-wrap items-center gap-4 md:flex-nowrap md:gap-6">
      <?php require_once __DIR__ . '/../partials/top-actions.php'; ?>
    </div>
  </div>
</header>

<main id="main-content" tabindex="-1" role="main" class="site-main">
  <div class="max-w-5xl mx-auto py-8 px-4 space-y-8">
    <section class="card section-lined rounded-2xl p-6 shadow-xl tech-panel">
      <header class="space-y-2 mb-6">
        <h2 class="sonar-hero-title text-4xl text-white">README del proyecto</h2>
      </header>

      <article class="readme-content readme-content--page rounded-2xl space-y-6 leading-relaxed text-slate-100">
        <section class="space-y-2">
          <h2 class="text-3xl text-white">📘 Documentación</h2>
          <p class="text-lg text-gray-300">README del Proyecto</p>
          <p>
            Clean Marvel Album es un proyecto creado en paralelo a mi formación en el Máster de IA de Big School. Cada módulo del máster inspiró una parte del sistema:
            arquitectura limpia, seguridad, microservicios, RAG, automatización y buenas prácticas. A medida que avanzaba el curso, fui aplicando lo aprendido directamente
            en el código, convirtiendo este proyecto en un laboratorio real donde experimentar, equivocarme, mejorar y construir una aplicación profesional de principio a fin.
          </p>
          <p>
            El resultado es una plataforma completa en PHP 8.2 con Arquitectura Limpia, microservicios IA, métricas, paneles de calidad y un pipeline CI/CD totalmente
            automatizado. Más que un proyecto, es el reflejo del camino recorrido durante el máster.
          </p>
        </section>

        <section class="space-y-3">
          <h3 class="text-2xl text-white">🦸‍♂️ ¿Qué es Clean Marvel Album?</h3>
          <p>
            Es una plataforma que combina desarrollo backend y microservicios de inteligencia artificial aplicando Clean Architecture.
            He implementado separación de responsabilidades en capas bien definidas demostrando cómo se comunican los distintos módulos.
          </p>
          <p>
            Cada capa tiene su función: Presentación para la interfaz, Aplicación para los casos de uso, Dominio para las reglas del negocio e Infraestructura
            para adaptadores, logs y persistencia. El core del dominio no conoce HTTP, base de datos ni proveedores externos de IA.
          </p>
        </section>

        <section class="space-y-3">
          <h3 class="text-2xl text-white">🧩 Lo que puedes hacer</h3>
          <ul class="list-disc list-inside space-y-2 text-gray-200">
            <li>Gestionar álbumes, héroes y cómics desde una interfaz clara y uniforme.</li>
            <li>Probar la generación de historias con IA (OpenAI) y comparar héroes con el microservicio RAG.</li>
            <li>Supervisar la actividad de la aplicación mediante logs y registros en tiempo real.</li>
            <li>Lanzar pruebas o “seeds” para validar comportamientos críticos del dominio.</li>
            <li>Visualizar métricas de calidad, errores, accesibilidad, rendimiento y actividad del repositorio sin salir del dashboard.</li>
          </ul>
        </section>

        <section class="space-y-3">
          <h3 class="text-2xl text-white">💾 Persistencia de datos</h3>
          <p>En local (<code>APP_ENV=local</code>) todo se almacena en JSON: álbumes y héroes en <code>storage/albums.json</code> y <code>storage/heroes.json</code>, y actividad en <code>storage/actividad/</code>. En hosting (<code>APP_ENV=hosting</code>) se intenta abrir PDO con las credenciales de <code>.env</code> para usar MySQL (repositorios <code>Db*</code>); si la conexión falla se registra el error y la app sigue con JSON como fallback.</p>
          <p>Para llevar los datos de JSON a la BD hay un script CLI: <code>php bin/migrar-json-a-db.php</code> que inserta álbumes, héroes y actividad evitando duplicados. Esto permite desarrollar ligero en local y desplegar en hosting con BD real, sin perder resiliencia.</p>
        </section>

        <section class="space-y-3">
          <h3 class="text-2xl text-white">🏗️ Arquitectura resumida</h3>
          <p>La estructura del proyecto sigue el principio de independencia entre capas:</p>
          <ul class="list-disc list-inside space-y-2 text-gray-200">
            <li><strong>Presentación:</strong> en <code>public/</code>, controladores en <code>src/Controllers</code> y vistas en <code>views/</code>.</li>
            <li><strong>Aplicación:</strong> casos de uso y servicios de orquestación en <code>src/*/Application</code> (incluyendo AI y herramientas Dev).</li>
            <li><strong>Dominio:</strong> entidades, eventos y contratos de repositorio en <code>src/*/Domain</code>.</li>
            <li><strong>Infraestructura:</strong> adaptadores, EventBus y persistencia en <code>src/*/Infrastructure</code> y <code>storage/</code>.</li>
          </ul>
          <p>
            Los microservicios <strong>openai-service</strong> (puerto 8081) y <strong>rag-service</strong> (puerto 8082) se comunican con la app principal
            mediante endpoints definidos en <code>config/services.php</code>. Así, la misma arquitectura puede correr en local o en hosting sin cambios en el código.
          </p>
          <p class="text-sm text-gray-300">
            El microservicio RAG reproduce el patrón <em>retrieval + generación</em> usando una base JSON in-memory y prompts controlados,
            de modo que puedas inspeccionar cada paso del flujo sin necesidad de un vector DB o infraestructura adicional.
          </p>
        </section>

        <!-- POR QUÉ CLEAN ARCHITECTURE -->
        <section class="space-y-3">
          <h3 class="text-2xl text-white">🎯 ¿Por qué Clean Architecture?</h3>
          <p>
            Esta arquitectura se eligió por razones <strong>técnicas</strong> que garantizan la calidad y evolución del proyecto a largo plazo.
          </p>

          <div class="rounded-xl border border-slate-700/80 bg-slate-900/70 p-5">
            <p class="text-cyan-300 font-semibold mb-3">✅ Beneficios clave</p>
            <ul class="space-y-2 text-sm text-gray-200">
              <li><strong>Independencia de frameworks:</strong> El dominio no depende de librerías externas, facilitando la evolución tecnológica sin reescribir la lógica de negocio.</li>
              <li><strong>Testabilidad extrema:</strong> Cada capa se prueba aisladamente. El dominio tiene tests puros sin mocks complejos, los casos de uso se testean sin HTTP, y la infraestructura se valida con doubles.</li>
              <li><strong>Mantenibilidad a largo plazo:</strong> Los cambios en UI, base de datos o APIs externas no afectan las reglas de negocio. Un cambio en persistencia (JSON → MySQL) solo toca <code>Infrastructure</code>.</li>
              <li><strong>Escalabilidad gradual:</strong> Permite añadir microservicios, cache o nuevos contextos sin refactorizar el core. Los microservicios IA (OpenAI, RAG) se integraron como adaptadores sin tocar el dominio.</li>
            </ul>
          </div>

          <p class="text-sm text-gray-300 mt-3">
            La decisión arquitectónica completa está documentada en <code>docs/architecture/ADR-001-clean-architecture.md</code>.
          </p>
        </section>

        <!-- CI/CD Y PIPELINE DE CALIDAD -->
        <section class="space-y-3">
          <h3 class="text-2xl text-white">✅ CI/CD, calidad y despliegue</h3>
          <p>
            El repositorio incluye un pipeline de GitHub Actions (<code>.github/workflows/ci.yml</code>) que se ejecuta en cada <strong>push</strong> a
            <code>main</code>, <code>feature/*</code>, <code>hotfix/*</code>, <code>release/*</code> y en cada <strong>pull request</strong> hacia <code>main</code>.
            El objetivo es asegurar que nada llega a producción sin pasar por tests y chequeos de calidad.
          </p>
          <ul class="list-disc list-inside space-y-2 text-gray-200">
            <li><strong>Job build:</strong> PHP 8.4, instalación de dependencias, validación de <code>composer.json</code>, PHPUnit y PHPStan.</li>
            <li><strong>Job sonarcloud:</strong> vuelve a ejecutar tests con coverage y sube métricas de bugs, code smells, duplicación y cobertura a SonarCloud.</li>
            <li><strong>Job pa11y:</strong> lanza auditorías de accesibilidad WCAG 2.1 AA sobre rutas clave y guarda los resultados como artefactos JSON.</li>
            <li><strong>Job lighthouse:</strong> analiza rendimiento, accesibilidad, SEO y best practices mediante Lighthouse CI.</li>
            <li><strong>Job playwright:</strong> ejecuta tests E2E en Chromium sobre las páginas principales, con trazas, capturas y vídeos como artefactos.</li>
          </ul>
          <p>
            Opcionalmente, un workflow separado de <strong>deploy FTP</strong> puede subirse a producción sólo cuando el pipeline de calidad pasa en verde, subiendo los
            archivos al hosting via FTP/SFTP. El proyecto también está preparado para incluir un flujo de rollback sencillo, recuperando la versión anterior si algo falla
            tras un despliegue.
          </p>
        </section>

        <!-- OBSERVABILIDAD -->
        <section class="space-y-3">
          <h3 class="text-2xl text-white">🔭 Observabilidad</h3>
          <p><strong>SonarCloud:</strong> el endpoint interno <code>/api/sonar-metrics.php</code> consulta la API oficial con token y project key configurados en el <code>.env</code>. La página <code>/sonar</code> (vista <code>views/pages/sonar.php</code>) muestra bugs, code smells, cobertura y duplicación en tiempo real aprovechando el reporte de cobertura generado tanto en local como en el pipeline.</p>
          <p><strong>Sentry:</strong> <code>src/bootstrap.php</code> inicializa Sentry con <code>SENTRY_DSN</code> y el entorno activo para capturar errores. El endpoint <code>/api/sentry-metrics.php</code> lista eventos recientes y la vista <code>/sentry</code> permite verlos y lanzar errores de prueba desde la UI.</p>
          <p><strong>Accesibilidad (WAVE + Pa11y):</strong> <code>/api/accessibility-marvel.php</code> usa <code>WAVE_API_KEY</code> para invocar <code>https://wave.webaim.org/api/request</code> y resumir errores, alertas y contraste, mientras que Pa11y se ejecuta tanto en el pipeline como en scripts locales para mantener las páginas en WCAG 2.1 AA.</p>
          <p><strong>Performance (PageSpeed + Lighthouse):</strong> <code>/api/performance-marvel.php</code> consulta PageSpeed Insights para las rutas clave y la página <code>/performance</code> muestra KPIs y oportunidades. Lighthouse CI refuerza este análisis en el pipeline en cada push.</p>
        </section>

        <!-- HEATMAP -->
        <section class="space-y-3">
          <h3 class="text-2xl text-white">🌡️ Heatmap de interacción</h3>
          <p>El tracker global (<code>public/assets/js/heatmap-tracker.js</code>) arma un payload con la página actual, coordenadas normalizadas (incluyendo scroll) y viewport para que cada clic viaje al microservicio Python.</p>
          <p>Los endpoints <code>/api/heatmap/click.php</code>, <code>summary.php</code> y <code>pages.php</code> funcionan ahora como proxies autenticados: reenvían POST/GET al microservicio (<code>http://34.74.102.123:8080</code> por defecto) adjuntando el token <code>HEATMAP_API_TOKEN</code> y devuelven el JSON tal cual para que el viewer siga operando con la misma matriz 20×20.</p>
          <p>Configuración: en la VM Python exporta <code>HEATMAP_API_TOKEN</code> (p. ej. <code>dev-heatmap-token</code> en local) y en Marvel establece <code>HEATMAP_API_BASE_URL</code> + <code>HEATMAP_API_TOKEN</code> en el entorno del servidor.</p>
          <p>El microservicio se puede contenerizar: el README del heatmap incluye los comandos de <code>docker build</code>/<code>docker run</code> para levantarlo en local o en una VM con el token ya inyectado.</p>
          <p>Visita la sección <code>/secret-heatmap</code> en la Secret Room para ver el mapa en canvas, KPIs y dos gráficos Chart.js (zonas Top/Middle/Bottom y distribución vertical) con estilo Marvel, además de una leyenda cromática que explica cada color.</p>
          <p class="text-sm text-gray-300">
            Si quieres ver la documentación técnica completa del microservicio Heatmap (Python + Flask + Docker en Google Cloud),
            está disponible en el repositorio en <code>docs/microservicioheatmap/README.md</code>.
          </p>
        </section>

        <!-- PANELES ADICIONALES -->
        <section class="space-y-3">
          <h3 class="text-2xl text-white">✨ Paneles adicionales</h3>
          <ul class="space-y-3 text-gray-200">
            <li>
              <strong>Accesibilidad:</strong> tarjetas de errores/contrast y tabla de resultados alimentadas por <code>/api/accessibility-marvel.php</code>, con layout idéntico al resto de paneles y botón “Analizar accesibilidad”.
            </li>
            <li>
              <strong>Repo Marvel:</strong> breadcrumb + tabla desde <code>/api/github-repo-browser.php</code>, ideal para explorar carpetas y archivos del repo sin salir del dashboard.
            </li>
            <li>
              <strong>Performance Marvel:</strong> <code>public/assets/js/panel-performance.js</code> pinta KPIs coloridos y cuellos de botella, consumiendo <code>/api/performance-marvel.php</code> y PageSpeed Insights.
            </li>
          </ul>
        </section>

        <!-- SEGURIDAD -->
        <section class="space-y-3">
          <h3 class="text-2xl text-white">🔐 Seguridad aplicada (resumen)</h3>
          <ul class="list-disc list-inside space-y-2 text-gray-200">
            <li>Cabeceras de hardening activas (CSP básica, X-Frame-Options, nosniff, Referrer-Policy, Permissions-Policy, COOP/COEP/CORP) y cookies de sesión HttpOnly + SameSite=Lax.</li>
            <li>CSRF en POST críticos, rate-limit/login throttling, firewall de payloads y sanitización de entrada.</li>
            <li>Sesiones con TTL/lifetime, sellado IP/UA y anti-replay en modo observación; rutas sensibles protegidas por AuthMiddleware/guards.</li>
            <li>Logs de seguridad con trace_id y secretos gestionados vía <code>.env</code> (app y microservicios); verificación previa a despliegue con <code>bin/security-check.sh</code> + workflow <code>security-check.yml</code>.</li>
          </ul>
          <p class="text-sm text-gray-300">Detalle completo y roadmap (Máster vs Enterprise) en <code>docs/security.md</code>.</p>
        </section>

        <!-- CONTAINERIZACIÓN Y KUBERNETES -->
        <section class="space-y-3">
          <h3 class="text-2xl text-white">🐳 Containerización y Kubernetes</h3>
          <p>
            El proyecto está <strong>completamente preparado para contenedorización y orquestación</strong>. Todos los microservicios
            incluyen Dockerfiles y pueden desplegarse tanto en contenedores individuales como en un cluster de Kubernetes.
          </p>

          <div class="grid gap-4 md:grid-cols-2">
            <div class="rounded-xl border border-slate-700/80 bg-slate-900/70 p-4">
              <p class="text-xs uppercase tracking-[0.24em] text-gray-400 mb-2">🐳 Docker</p>
              <ul class="space-y-2 text-sm text-gray-200">
                <li>• <strong>3 Dockerfiles</strong>: App principal + OpenAI Service + RAG Service</li>
                <li>• <strong>docker-compose.yml</strong>: Stack completa con un comando</li>
                <li>• <strong>Variables de entorno</strong>: Configuración unificada con <code>.env</code></li>
                <li>• <strong>Multi-puerto</strong>: 8080 (app), 8081 (OpenAI), 8082 (RAG)</li>
              </ul>
            </div>
            <div class="rounded-xl border border-slate-700/80 bg-slate-900/70 p-4">
              <p class="text-xs uppercase tracking-[0.24em] text-gray-400 mb-2">☸️ Kubernetes</p>
              <ul class="space-y-2 text-sm text-gray-200">
                <li>• <strong>8 manifiestos YAML</strong> en directorio <code>k8s/</code></li>
                <li>• <strong>Deployments</strong>: 2 réplicas escalables por servicio</li>
                <li>• <strong>Ingress NGINX</strong>: Enrutamiento por path (/, /api/rag, /api/openai)</li>
                <li>• <strong>ConfigMaps + Secrets</strong>: Configuración separada por servicio</li>
              </ul>
            </div>
          </div>

          <div class="rounded-xl border border-cyan-700/50 bg-cyan-900/20 p-5 mt-3">
            <p class="text-cyan-300 font-semibold mb-3">📖 Documentación Kubernetes</p>
            <ul class="text-sm text-gray-200 space-y-2">
              <li>• <code>k8s/README.md</code> — Índice general y arquitectura desplegada</li>
              <li>• <code>k8s/DEPLOY_K8S.md</code> — Guía paso a paso de despliegue</li>
              <li>• <code>k8s/PRODUCTION_CONSIDERATIONS.md</code> — Mejoras para producción (Sealed Secrets, TLS, NetworkPolicies, etc.)</li>
              <li>• <code>k8s/SECURITY_HARDENING.md</code> — 10 capas de seguridad para K8s</li>
            </ul>
          </div>

          <div class="rounded-xl border border-slate-700/80 bg-slate-900/70 p-4 mt-3">
            <p class="text-xs uppercase tracking-widest text-gray-400 mb-3">🚀 Estrategias de Despliegue</p>
            <div class="overflow-x-auto">
              <table class="w-full text-sm text-gray-200">
                <thead>
                  <tr class="border-b border-slate-700">
                    <th class="text-left py-2 px-3 text-cyan-300">Entorno</th>
                    <th class="text-left py-2 px-3 text-cyan-300">Tecnología</th>
                    <th class="text-left py-2 px-3 text-cyan-300">Caso de uso</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-800">
                  <tr>
                    <td class="py-2 px-3 font-semibold">Local</td>
                    <td class="py-2 px-3 font-mono text-xs">php -S</td>
                    <td class="py-2 px-3">Desarrollo rápido</td>
                  </tr>
                  <tr>
                    <td class="py-2 px-3 font-semibold">Hosting</td>
                    <td class="py-2 px-3 font-mono text-xs">Apache/Nginx + FTP</td>
                    <td class="py-2 px-3">Producción simple</td>
                  </tr>
                  <tr>
                    <td class="py-2 px-3 font-semibold">Docker</td>
                    <td class="py-2 px-3 font-mono text-xs">docker-compose</td>
                    <td class="py-2 px-3">Entorno con dependencias</td>
                  </tr>
                  <tr>
                    <td class="py-2 px-3 font-semibold">Kubernetes</td>
                    <td class="py-2 px-3 font-mono text-xs">kubectl</td>
                    <td class="py-2 px-3">Producción escalable</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <p class="text-sm text-gray-300">
            Los manifiestos actuales están diseñados para desarrollo y demostración. La documentación incluye una
            <strong>hoja de ruta completa</strong> con mejoras para producción: Sealed Secrets, cert-manager + TLS automático,
            NetworkPolicies, Pod Security Admission, Image scanning, Runtime security con Falco, y observabilidad avanzada.
          </p>
        </section>

        <!-- REFACTOR ESTRUCTURAL v2.0 -->
        <section class="space-y-3">
          <h3 class="text-2xl text-white">🔧 Refactor Estructural v2.0 (Diciembre 2025 Enero 2026)</h3>
          <p>Consolidación de la arquitectura como implementación de Clean Architecture.</p>
          
          <div class="grid gap-3 md:grid-cols-2">
            <div class="rounded-xl border border-slate-700/80 bg-slate-900/70 p-4">
              <p class="text-xs uppercase tracking-widest text-gray-400 mb-2">Namespace y Autoload</p>
              <ul class="space-y-1 text-sm text-gray-200">
                <li>• Migración de <code>Src\</code> → <code>App\</code></li>
                <li>• PSR-4 estándar en <code>composer.json</code></li>
                <li>• 191 tests migrados a namespace <code>Tests\</code></li>
              </ul>
            </div>
            <div class="rounded-xl border border-slate-700/80 bg-slate-900/70 p-4">
              <p class="text-xs uppercase tracking-widest text-gray-400 mb-2">Mejoras Técnicas</p>
              <ul class="space-y-1 text-sm text-gray-200">
                <li>• <code>RequestBodyReader</code>: lectura única con caché</li>
                <li>• <code>ApiFirewall</code>: whitelist antes de leer body</li>
                <li>• Corrección del bug "body vacío" en endpoints POST</li>
              </ul>
            </div>
          </div>

          <div class="rounded-xl border border-cyan-700/50 bg-cyan-900/20 p-4 mt-3">
            <p class="text-cyan-300 font-semibold mb-2">🐛 Variables DEBUG (solo producción)</p>
            <p class="text-sm text-gray-300 mb-2">En <code>APP_ENV=local/dev</code> los logs están siempre activos. En producción, usa:</p>
            <ul class="text-sm text-gray-200 space-y-1">
              <li><code>DEBUG_API_FIREWALL=1</code> → Logs del firewall de payloads</li>
              <li><code>DEBUG_RAG_PROXY=1</code> → Logs del proxy RAG</li>
              <li><code>DEBUG_RAW_BODY=1</code> → Logs del lector de body HTTP</li>
            </ul>
          </div>
        </section>

        <!-- CALIDAD Y TESTS -->
        <section class="space-y-3">
          <h3 class="text-2xl text-white">🧪 Calidad y Testing Multinivel</h3>
          <p>
            El proyecto implementa una <strong>estrategia de testing multinivel</strong> con <strong>673 tests automatizados</strong> (659 PHPUnit + 14 E2E)
            que cubren desde la lógica de negocio hasta la experiencia de usuario final.
          </p>

          <div class="rounded-xl border border-slate-700/80 bg-slate-900/70 p-5 overflow-x-auto">
            <p class="text-xs uppercase tracking-widest text-gray-400 mb-4">Tipos de Tests Implementados</p>
            <table class="w-full text-sm text-gray-200">
              <thead>
                <tr class="border-b border-slate-700">
                  <th class="text-left py-2 px-3 text-cyan-300">Tipo</th>
                  <th class="text-left py-2 px-3 text-cyan-300">Cantidad</th>
                  <th class="text-left py-2 px-3 text-cyan-300">Herramienta</th>
                  <th class="text-left py-2 px-3 text-cyan-300">Cobertura</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-800">
                <tr>
                  <td class="py-2 px-3 font-semibold">Unitarios y Dominio</td>
                  <td class="py-2 px-3">~30 archivos</td>
                  <td class="py-2 px-3 font-mono text-xs">PHPUnit</td>
                  <td class="py-2 px-3 text-sm">Entidades, VOs, Eventos</td>
                </tr>
                <tr>
                  <td class="py-2 px-3 font-semibold">Casos de Uso</td>
                  <td class="py-2 px-3">~25 archivos</td>
                  <td class="py-2 px-3 font-mono text-xs">PHPUnit</td>
                  <td class="py-2 px-3 text-sm">Application layer</td>
                </tr>
                <tr>
                  <td class="py-2 px-3 font-semibold">Seguridad ⚔️</td>
                  <td class="py-2 px-3">22 archivos</td>
                  <td class="py-2 px-3 font-mono text-xs">PHPUnit</td>
                  <td class="py-2 px-3 text-sm">CSRF, Rate Limit, Sessions, Firewall</td>
                </tr>
                <tr>
                  <td class="py-2 px-3 font-semibold">Controladores</td>
                  <td class="py-2 px-3">21 archivos</td>
                  <td class="py-2 px-3 font-mono text-xs">PHPUnit</td>
                  <td class="py-2 px-3 text-sm">HTTP layer completa</td>
                </tr>
                <tr>
                  <td class="py-2 px-3 font-semibold">Infraestructura</td>
                  <td class="py-2 px-3">~20 archivos</td>
                  <td class="py-2 px-3 font-mono text-xs">PHPUnit</td>
                  <td class="py-2 px-3 text-sm">Repos, HTTP clients, Bus</td>
                </tr>
                <tr>
                  <td class="py-2 px-3 font-semibold">E2E 🎭</td>
                  <td class="py-2 px-3">5 archivos (6 tests)</td>
                  <td class="py-2 px-3 font-mono text-xs">Playwright</td>
                  <td class="py-2 px-3 text-sm">Flujos críticos de usuario</td>
                </tr>
                <tr>
                  <td class="py-2 px-3 font-semibold">Accesibilidad</td>
                  <td class="py-2 px-3">Pipeline CI</td>
                  <td class="py-2 px-3 font-mono text-xs">Pa11y</td>
                  <td class="py-2 px-3 text-sm">WCAG 2.1 AA (0 errores)</td>
                </tr>
                <tr>
                  <td class="py-2 px-3 font-semibold">Performance</td>
                  <td class="py-2 px-3">Pipeline CI</td>
                  <td class="py-2 px-3 font-mono text-xs">Lighthouse</td>
                  <td class="py-2 px-3 text-sm">Métricas de rendimiento</td>
                </tr>
              </tbody>
            </table>
          </div>

          <div class="grid gap-4 md:grid-cols-2 mt-4">
            <div class="rounded-xl border border-slate-700/80 bg-slate-900/70 p-4">
              <p class="text-xs uppercase tracking-[0.24em] text-gray-400 mb-2">Suite PHPUnit (659 tests)</p>
              <ul class="space-y-2 text-sm text-gray-200">
                <li><code>vendor/bin/phpunit --colors=always</code> — Ejecuta todos los tests.</li>
                <li><code>composer test:cov</code> — Genera cobertura (88.89% ✅ a 13 Feb 2026).</li>
                <li><code>vendor/bin/phpstan analyse</code> — Análisis estático nivel 6.</li>
                <li><code>vendor/bin/phpunit tests/Security</code> — Solo tests de seguridad.</li>
              </ul>
            </div>
            <div class="rounded-xl border border-slate-700/80 bg-slate-900/70 p-4">
              <p class="text-xs uppercase tracking-[0.24em] text-gray-400 mb-2">Tests E2E Playwright (14 archivos)</p>
              <ul class="space-y-2 text-sm text-gray-200">
                <li><code>npm run test:e2e</code> — Tests con navegador visible.</li>
                <li><code>npm run test:e2e:ui</code> — Modo UI interactivo (recomendado).</li>
                <li><code>npm run test:e2e:debug</code> — Debug paso a paso.</li>
                <li>✅ Home, Álbumes, Héroes, Cómics, Películas cubiertos.</li>
              </ul>
            </div>
          </div>

          <div class="rounded-xl border border-cyan-700/50 bg-cyan-900/20 p-4 mt-3">
            <p class="text-cyan-300 font-semibold mb-2">🎯 Tests E2E Cubiertos</p>
            <ul class="text-sm text-gray-200 space-y-1">
              <li>✅ <strong>Home</strong> (2 tests): Carga correcta + navegación principal</li>
              <li>✅ <strong>Álbumes</strong>: Renderizado, cards y formulario de creación</li>
              <li>✅ <strong>Héroes</strong>: Galería, listado y botón añadir héroe</li>
              <li>✅ <strong>Cómics</strong>: Formulario de generación con IA</li>
              <li>✅ <strong>Películas</strong>: Búsqueda y manejo de estados (con/sin datos)</li>
            </ul>
          </div>

          <p class="text-sm text-gray-300 mt-3">
            Ver <code>docs/guides/testing-complete.md</code> para documentación exhaustiva de cada tipo de test,
            comandos específicos y estrategia completa de testing.
          </p>
        </section>

        <!-- NARRACIÓN ELEVENLABS -->
        <section class="space-y-3">
          <h3 class="text-2xl text-white">🔊 Narración con ElevenLabs</h3>
          <p>
            Los resultados de texto (cómic y comparación RAG) incluyen botones para escuchar la historia usando el endpoint <code>/api/tts-elevenlabs.php</code>.
            Ese proxy toma el texto, inyecta <code>ELEVENLABS_API_KEY</code> y lo envía a <code>https://api.elevenlabs.io/v1/text-to-speech/{voiceId}</code> sin exponer tu credencial.
          </p>
          <ul class="list-disc list-inside space-y-2 text-gray-200">
            <li>Voz y modelo por defecto: <strong>Charlie</strong> (<code>EXAVITQu4vr4xnSDxMaL</code>) usando <code>eleven_multilingual_v2</code>.</li>
            <li>Configura las variables <code>ELEVENLABS_VOICE_ID</code>, <code>ELEVENLABS_MODEL_ID</code>, <code>ELEVENLABS_VOICE_STABILITY</code> y <code>ELEVENLABS_VOICE_SIMILARITY</code> en el <code>.env</code> para personalizar la narración.</li>
            <li>En hosting asegúrate de copiar el <code>.env</code>, habilitar cURL y permitir tráfico saliente HTTPS; el endpoint sólo acepta solicitudes <code>POST</code>.</li>
          </ul>
        </section>

        <!-- PANEL GITHUB -->
        <section class="space-y-3">
          <h3 class="text-2xl text-white">🐙 Panel GitHub en vivo</h3>
          <p>
            La vista <code>/panel-github</code> consume la clase <code>App\Services\GithubClient</code> para consultar la API oficial de GitHub y mostrar Pull Requests del repo
            <code>20Luisma/marvel</code>. Puedes filtrar por rango de fechas y revisar cuántos commits, reviews y reviewers únicos tuvo cada PR, junto con sus labels.
          </p>
          <ul class="list-disc list-inside space-y-2 text-gray-200">
            <li>Configura <code>GITHUB_API_KEY</code> en el <code>.env</code> con un token personal que tenga permisos de lectura.</li>
            <li>El dashboard normaliza fechas (YYYY-MM-DD), muestra errores claros cuando falta el token y enlaza cada PR directo en GitHub.</li>
            <li>Los estilos (<code>public/assets/css/panel-github.css</code>) y el top action dedicado mantienen el mismo look &amp; feel del resto del proyecto.</li>
          </ul>
        </section>

        <!-- CONTENIDO OFICIAL -->
        <section class="space-y-3">
          <h3 class="text-2xl text-white">📺 Contenido oficial (YouTube + n8n)</h3>
          <p>
            La sección “Oficial Marvel” está pensada para recibir contenido que venga de las fuentes oficiales (por ejemplo el canal de YouTube).
            Ese contenido se puede traer mediante n8n o un scraper y guardarlo para mostrarlo dentro de la app con el mismo diseño.
            La arquitectura ya está preparada para consumir ese contenido externo sin mezclarlo con el dominio principal.
          </p>
        </section>

        <!-- BUNDLE SIZE -->
        <section class="space-y-3">
          <h3 class="text-2xl text-white">📦 Bundle Size (JS/CSS)</h3>
          <p>
            El pipeline de CI (job <code>sonarcloud</code>) ejecuta <code>php bin/generate-bundle-size.php</code> y publica
            <code>public/assets/bundle-size.json</code>. La vista <code>/sonar</code> consume ese JSON para mostrar totales y el top 5 de archivos más pesados sin ejecutar comandos en hosting.
          </p>
          <ul class="list-disc list-inside space-y-2 text-gray-200">
            <li>Regenera el JSON en local con <code>php bin/generate-bundle-size.php</code> antes de subir cambios si no usas CI.</li>
            <li>El bloque de métricas de bundle en <code>/sonar</code> se actualiza con cada deploy que incluya el JSON.</li>
          </ul>
        </section>

        <!-- COMANDOS ÚTILES -->
        <section class="space-y-3">
          <h3 class="text-2xl text-white">⚙️ Comandos útiles</h3>
          <div class="grid gap-4 md:grid-cols-2">
            <div class="rounded-xl border border-slate-700/80 bg-slate-900/70 p-4">
              <p class="text-xs uppercase tracking-[0.24em] text-gray-400 mb-2">Desarrollo</p>
              <ul class="space-y-2 text-sm text-gray-200">
                <li><code>composer install</code> — Instala las dependencias.</li>
                <li><code>composer serve</code> — Levanta la app en <strong>localhost:8080</strong>.</li>
                <li><code>php -S localhost:8081 -t public</code> — Microservicio OpenAI en local.</li>
                <li><code>php -S localhost:8082 -t public</code> — Microservicio RAG en local.</li>
              </ul>
            </div>
            <div class="rounded-xl border border-slate-700/80 bg-slate-900/70 p-4">
              <p class="text-xs uppercase tracking-[0.24em] text-gray-400 mb-2">Calidad</p>
              <ul class="space-y-2 text-sm text-gray-200">
                <li><code>vendor/bin/phpunit</code> — Ejecuta las pruebas automáticas.</li>
                <li><code>composer test:cov</code> — Genera reporte de cobertura para SonarCloud.</li>
                <li><code>vendor/bin/phpstan analyse</code> — Analiza la calidad del código.</li>
                <li>Tasks de VS Code en <code>.vscode/tasks.json</code> para QA completo, Git y servidores en un clic.</li>
              </ul>
            </div>
          </div>
        </section>

        <!-- ARQUITECTURA BOOTSTRAP (COMPOSITION ROOT) -->
        <section class="space-y-3">
          <h3 class="text-2xl text-white">🧩 Arquitectura del Bootstrap (Composition Root)</h3>
          <p>
            El archivo <code>bootstrap.php</code> actúa como <strong>Composition Root</strong> del proyecto, pero con una arquitectura <strong>modular y escalable</strong> que separa responsabilidades en módulos especializados:
          </p>

          <div class="rounded-xl border border-slate-700/80 bg-slate-900/70 p-5 overflow-x-auto">
            <p class="text-xs uppercase tracking-widest text-gray-400 mb-4">Módulos Bootstrap</p>
            <table class="w-full text-sm text-gray-200">
              <thead>
                <tr class="border-b border-slate-700">
                  <th class="text-left py-2 px-3 text-cyan-300">Módulo</th>
                  <th class="text-left py-2 px-3 text-cyan-300">Responsabilidad</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-800">
                <tr>
                  <td class="py-2 px-3 font-mono text-cyan-400">EnvironmentBootstrap</td>
                  <td class="py-2 px-3">Carga de <code>.env</code>, inicialización de sesión y generación de Trace ID</td>
                </tr>
                <tr>
                  <td class="py-2 px-3 font-mono text-cyan-400">PersistenceBootstrap</td>
                  <td class="py-2 px-3">Configuración de repositorios (DB/JSON) con fallback automático</td>
                </tr>
                <tr>
                  <td class="py-2 px-3 font-mono text-cyan-400">SecurityBootstrap</td>
                  <td class="py-2 px-3">Auth, CSRF, Rate Limit, Firewall y Anti-Replay</td>
                </tr>
                <tr>
                  <td class="py-2 px-3 font-mono text-cyan-400">EventBootstrap</td>
                  <td class="py-2 px-3">EventBus y suscriptores de eventos de dominio</td>
                </tr>
                <tr>
                  <td class="py-2 px-3 font-mono text-cyan-400">ObservabilityBootstrap</td>
                  <td class="py-2 px-3">Sentry, métricas de tokens y trazabilidad</td>
                </tr>
                <tr>
                  <td class="py-2 px-3 font-mono text-cyan-400">AppBootstrap</td>
                  <td class="py-2 px-3">Orquestador principal que coordina todos los módulos</td>
                </tr>
              </tbody>
            </table>
          </div>

          <div class="rounded-xl border border-slate-700/80 bg-slate-900/70 p-4 mt-3">
            <p class="text-cyan-300 font-semibold mb-3">✅ Beneficios de la Modularización</p>
            <ul class="space-y-2 text-sm text-gray-200">
              <li><strong>Separación de responsabilidades:</strong> Cada módulo tiene una única razón de cambio.</li>
              <li><strong>Mantenibilidad:</strong> Fácil localizar y modificar configuración específica (seguridad, persistencia, etc.).</li>
              <li><strong>Testabilidad:</strong> Los módulos pueden probarse de forma aislada.</li>
              <li><strong>Escalabilidad:</strong> Permite añadir nuevos módulos (cache, queue, etc.) sin afectar los existentes.</li>
            </ul>
          </div>

          <p class="text-sm text-gray-300">
            Esta arquitectura combina claridad en el wiring con las mejores prácticas empresariales (modularización, SRP).
            El resultado es un sistema que mantiene la <strong>transparencia</strong> del ensamblado completo, pero con una <strong>estructura profesional</strong>
            basada en <strong>Clean Architecture</strong> con fallback resiliente JSON/BD, seguridad multicapa, microservicios y trazabilidad.
          </p>
        </section>

        <!-- ROUTER HTTP -->
        <section class="space-y-3">
          <h3 class="text-2xl text-white">🛤️ Router HTTP</h3>
          <p>
            El <code>Router</code> (<code>src/Shared/Http/Router.php</code>) es el <strong>punto de entrada principal</strong> de todas las peticiones HTTP.
            Implementa un diseño custom que demuestra los principios de un enrutador profesional sin depender de librerías externas.
          </p>

          <div class="grid gap-4 md:grid-cols-2">
            <div class="rounded-xl border border-slate-700/80 bg-slate-900/70 p-4">
              <p class="text-xs uppercase tracking-[0.24em] text-gray-400 mb-2">🔒 Pipeline de Seguridad</p>
              <p class="text-sm text-gray-300 mb-3">3 capas ejecutadas en orden estricto:</p>
              <ul class="space-y-2 text-sm text-gray-200">
                <li><strong>1. ApiFirewall</strong> → Bloquea patrones maliciosos (SQL injection, XSS, path traversal)</li>
                <li><strong>2. RateLimitMiddleware</strong> → Protege contra abusos y ataques DoS</li>
                <li><strong>3. AuthMiddleware</strong> → Verifica sesión en rutas <code>/admin/*</code></li>
              </ul>
            </div>
            <div class="rounded-xl border border-slate-700/80 bg-slate-900/70 p-4">
              <p class="text-xs uppercase tracking-[0.24em] text-gray-400 mb-2">📋 Sistema de Rutas</p>
              <p class="text-sm text-gray-300 mb-3">Declarativo con soporte dual:</p>
              <ul class="space-y-2 text-sm text-gray-200">
                <li><strong>Rutas estáticas</strong>: <code>/albums</code>, <code>/heroes</code>, <code>/login</code></li>
                <li><strong>Rutas dinámicas (regex)</strong>: <code>/heroes/{id}</code>, <code>/albums/{id}/heroes</code></li>
                <li><strong>Despacho por método</strong>: GET, POST, PUT, DELETE con <code>match</code> expression</li>
              </ul>
            </div>
          </div>

          <div class="rounded-xl border border-cyan-700/50 bg-cyan-900/20 p-5 mt-3">
            <p class="text-cyan-300 font-semibold mb-3">⚡ Características Clave</p>
            <ul class="text-sm text-gray-200 space-y-2">
              <li><strong>Inyección de dependencias:</strong> Recibe el contenedor como array asociativo desde <code>AppBootstrap</code></li>
              <li><strong>Lazy-loading:</strong> Controladores instanciados bajo demanda y cacheados durante la petición</li>
              <li><strong>Manejo de errores:</strong> Try-catch global con respuesta JSON genérica (sin leak de información sensible)</li>
              <li><strong>Separación HTML/JSON:</strong> Detecta <code>Accept: text/html</code> para renderizar vistas vs respuestas API</li>
            </ul>
          </div>

          <p class="text-sm text-gray-300">
            Esta implementación custom permite entender cómo funcionan los routers internamente, manteniendo un nivel profesional de seguridad y mantenibilidad. La arquitectura completa está documentada en
            <code>docs/architecture/ARCHITECTURE.md</code>.
          </p>
        </section>

        <!-- REFLEXIÓN FINAL -->
        <section class="space-y-3">
          <h3 class="text-2xl text-white">💭 Reflexión Final</h3>
          <div class="rounded-xl border border-slate-700/80 bg-slate-900/70 p-5">
            <p class="text-gray-200 italic text-lg leading-relaxed">
              "Este proyecto no pretende definir cómo debe hacerse arquitectura profesional, sino mostrar mi proceso de aprendizaje y experimentación aplicando conceptos del Máster."
            </p>
            <p class="text-gray-300 mt-4 leading-relaxed">
            El proyecto está diseñado como base reutilizable para crear nuevos sistemas, aplicando correctamente la estructura de un backend moderno sin frameworks.
          </p>
          <p class="text-cyan-300 mt-4 italic text-lg leading-relaxed">
            ⚡ "Como un centauro del universo Marvel, este proyecto fusiona la creatividad humana con la fuerza imparable de la IA: dos mitades, un héroe completo."
          </p>
          </div>
        </section>
      </article>
    </section>
  </div>
</main>

<?php
$scripts = [];
require_once __DIR__ . '/../layouts/footer.php';
