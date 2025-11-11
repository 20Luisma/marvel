<?php

declare(strict_types=1);

$pageTitle = 'Clean Marvel Album — README';
$additionalStyles = ['/assets/css/readme.css'];
require __DIR__ . '/../layouts/header.php';
?>

<!-- HERO / HEADER -->
<header class="app-hero">
  <div class="app-hero__inner">
    <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
      <div class="space-y-3 max-w-3xl">
        <h1 class="app-hero__title text-4xl sm:text-5xl">Clean Architecture with Marvel</h1>
        <p class="text-lg text-gray-300 max-w-2xl leading-snug sm:text-xl">
          Documentación viva y guías técnicas del proyecto.
        </p>
      </div>
    </div>
    <div class="flex w-full flex-wrap items-center gap-4 md:flex-nowrap md:gap-6">
      <p class="app-hero__meta flex-1 min-w-[14rem]">Consulta el README completo con arquitectura, comandos y flujos.</p>
      <?php require __DIR__ . '/../partials/top-actions.php'; ?>
    </div>
  </div>
</header>

<main class="site-main">
  <div class="max-w-5xl mx-auto py-8 px-4 space-y-8">
    <section class="card section-lined rounded-2xl p-6 shadow-xl">
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
            Toda la aplicación está organizada en capas para mantener orden, claridad y facilidad de evolución.
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
            para adaptadores, logs y persistencia.
          </p>
        </section>

        <section class="space-y-3">
          <h3 class="text-2xl text-white">🧩 Lo que puedes hacer</h3>
          <ul class="list-disc list-inside space-y-2 text-gray-200">
            <li>Gestionar álbumes, héroes y cómics desde una interfaz clara y uniforme.</li>
            <li>Probar la generación de historias con IA (OpenAI) y comparar héroes con el microservicio RAG educativo.</li>
            <li>Supervisar la actividad de la aplicación mediante logs y registros en tiempo real.</li>
            <li>Lanzar pruebas o “seeds” para validar comportamientos críticos del dominio.</li>
          </ul>
        </section>

        <section class="space-y-3">
          <h3 class="text-2xl text-white">🏗️ Arquitectura resumida</h3>
          <p>La estructura del proyecto sigue el principio de independencia entre capas:</p>
          <ul class="list-disc list-inside space-y-2 text-gray-200">
            <li><strong>Presentación:</strong> en <code>public/</code> y <code>views/</code>.</li>
            <li><strong>Aplicación:</strong> casos de uso en <code>src/*/Application</code>.</li>
            <li><strong>Dominio:</strong> entidades y contratos en <code>src/*/Domain</code>.</li>
            <li><strong>Infraestructura:</strong> adaptadores y persistencia en <code>src/*/Infrastructure</code>.</li>
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

        <section class="space-y-3">
          <h3 class="text-2xl text-white">🧪 Calidad</h3>
          <p>
            El proyecto incluye pruebas automáticas (PHPUnit) y auditorías de actividad que permiten detectar errores antes de desplegar.
            Cada acción —desde crear un álbum hasta comparar héroes— queda registrada para analizar el comportamiento del sistema en entornos reales.
          </p>
        </section>

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

        <section class="space-y-3">
          <h3 class="text-2xl text-white">📺 Contenido oficial (YouTube + n8n)</h3>
          <p>
            La sección “Oficial Marvel” está pensada para recibir contenido que venga de las fuentes oficiales (por ejemplo el canal de YouTube).
            Ese contenido se podrá traer mediante n8n o un scraper y guardarlo para mostrarlo dentro de la app con el mismo diseño.
            La arquitectura ya está preparada para consumir ese contenido externo sin mezclarlo con el dominio principal.
          </p>
        </section>

        <section class="space-y-3">
          <h3 class="text-2xl text-white">⚙️ Comandos útiles</h3>
          <div class="grid gap-4 md:grid-cols-2">
            <div class="rounded-xl border border-slate-700/80 bg-slate-900/70 p-4">
              <p class="text-xs uppercase tracking-[0.24em] text-gray-400 mb-2">Desarrollo</p>
              <ul class="space-y-2 text-sm text-gray-200">
                <li><code>composer install</code> — Instala las dependencias.</li>
                <li><code>composer serve</code> — Levanta la app en <strong>localhost:8080</strong>.</li>
              </ul>
            </div>
            <div class="rounded-xl border border-slate-700/80 bg-slate-900/70 p-4">
              <p class="text-xs uppercase tracking-[0.24em] text-gray-400 mb-2">Calidad</p>
              <ul class="space-y-2 text-sm text-gray-200">
                <li><code>vendor/bin/phpunit</code> — Ejecuta las pruebas automáticas.</li>
                <li><code>vendor/bin/phpstan analyse</code> — Analiza la calidad del código.</li>
              </ul>
            </div>
          </div>
        </section>

        <section class="space-y-3">
          <h3 class="text-2xl text-white">🚀 ¿Cómo continuar?</h3>
          <p>
            Explora la carpeta <code>docs/</code> para conocer más sobre la arquitectura, endpoints y roadmap.
            Revisa también los microservicios para entender cómo se integran con el backend principal.
            Todas las vistas comparten la misma cabecera y barra de acciones para que puedas moverte fácil entre álbumes, héroes,
            cómics, documentación y la futura página oficial.
          </p>
        </section>
      </article>
    </section>
  </div>
</main>

<?php
$scripts = [];
require __DIR__ . '/../layouts/footer.php';
