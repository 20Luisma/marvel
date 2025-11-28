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
        <p class="text-xs uppercase tracking-[0.28em] text-gray-400">Documentación</p>
        <h2 class="text-3xl text-white">README del proyecto</h2>
      </header>

      <article class="readme-content readme-content--page rounded-2xl space-y-6 leading-relaxed text-slate-100">
        <section class="space-y-2">
          <h2 class="text-3xl text-white">📘 Documentación</h2>
          <p class="text-lg text-gray-300">README del Proyecto</p>
          <p>
            Clean Marvel Album es una experiencia educativa desarrollada en PHP 8.2 que demuestra cómo se ve una Arquitectura Limpia aplicada a un proyecto real.
            Toda la aplicación está organizada en capas para mantener orden, claridad y facilidad de evolución. Además, el proyecto incluye un pipeline completo
            de CI/CD con tests, análisis de calidad, accesibilidad y despliegue automático desde GitHub.
          </p>
        </section>

        <section class="space-y-3">
          <h3 class="text-2xl text-white">🦸‍♂️ ¿Qué es Clean Marvel Album?</h3>
          <p>
            Es una plataforma didáctica que combina desarrollo backend y microservicios de inteligencia artificial.
            El objetivo es que cualquier persona que esté aprendiendo Arquitectura Limpia pueda ver en acción cómo se separan responsabilidades
            y cómo se comunican los distintos módulos del sistema.
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
            <li>Probar la generación de historias con IA (OpenAI) y comparar héroes con el microservicio RAG educativo.</li>
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
            El microservicio RAG está construido con fines educativos: reproduce el patrón <em>retrieval + generación</em> usando una base JSON in-memory y prompts controlados,
            de modo que puedas inspeccionar cada paso del flujo sin necesidad de un vector DB o infraestructura adicional.
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

        <!-- CALIDAD Y TESTS -->
        <section class="space-y-3">
          <h3 class="text-2xl text-white">🧪 Calidad y tipos de tests</h3>
          <p>
            El proyecto incluye pruebas automáticas (PHPUnit), análisis estático (PHPStan), auditorías de accesibilidad (WAVE + Pa11y), rendimiento (PageSpeed + Lighthouse) y tests E2E (Playwright).
            La idea es tener una visión completa de la salud del sistema tanto en local como en la integración continua.
          </p>
          <ul class="list-disc list-inside space-y-2 text-gray-200">
            <li>Suites unitarias para entidades y servicios puros.</li>
            <li>Pruebas de aplicación con repositorios en memoria (sin tocar disco ni HTTP externo).</li>
            <li>Dobles de prueba en <code>tests/Fakes</code> y <code>tests/Doubles</code> para mantener determinismo.</li>
            <li>Generación de <code>coverage.xml</code> con <code>composer test:cov</code> y uso de ese reporte en SonarCloud.</li>
          </ul>
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

        <!-- CIERRE -->
        <section class="space-y-3">
          <h3 class="text-2xl text-white">🚀 ¿Cómo continuar?</h3>
          <p>
            Explora la carpeta <code>docs/</code> para conocer más sobre la arquitectura, endpoints y roadmap.
            Revisa también los microservicios para entender cómo se integran con el backend principal.
            Todas las vistas comparten la misma cabecera y barra de acciones para que puedas moverte fácil entre álbumes, héroes,
            cómics, documentación, paneles de calidad y la sección oficial.
          </p>
        </section>
      </article>
    </section>
  </div>
</main>

<?php
$scripts = [];
require_once __DIR__ . '/../layouts/footer.php';
