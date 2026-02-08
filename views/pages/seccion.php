<?php
declare(strict_types=1);

use App\Infrastructure\Http\AuthGuards;

AuthGuards::requireAuth();
AuthGuards::requireAdmin();

$pageTitle = 'Clean Marvel Album — Secret Room';
$additionalStyles = ['/assets/css/seccion.css'];
$activeTopAction = 'secret';

$sections = [
    [
        'title' => 'README',
        'tag' => 'Documentación',
        'description' => 'Lee el README vivo con arquitectura, comandos y tareas recomendadas del proyecto.',
        'href' => '/readme',
        'cta' => 'Leer README',
    ],

    [
        'title' => 'SonarCloud',
        'tag' => 'Calidad del código',
        'description' => 'Explora bugs, vulnerabilities, code smells y duplicación de código directamente desde SonarCloud.',
        'href' => '/sonar',
        'cta' => 'Abrir SonarCloud',
    ],
    [
        'title' => 'Sentry',
        'tag' => 'Observabilidad',
        'description' => 'Revive eventos, stack traces y lanza errores de prueba para validar cómo responde el tablero.',
        'href' => '/sentry',
        'cta' => 'Entrar a Sentry',
    ],
    [
        'title' => 'Seguridad',
        'tag' => 'Security Headers',
        'description' => 'Análisis automático de seguridad, cabeceras y configuración del servidor con SecurityHeaders.com y Mozilla Observatory.',
        'href' => '/seguridad',
        'cta' => 'Ver seguridad',
    ],
    [
        'title' => 'Heatmap',
        'tag' => 'Analítica',
        'description' => 'Explora los clics de cada página y descubre dónde los héroes hacen más clics dentro de la app.',
        'href' => '/secret-heatmap',
        'cta' => 'Ver heatmap',
    ],
        [
            'title' => 'GitHub PRs',
            'tag' => 'Repositorio',
            'description' => 'Analiza la actividad de pull requests, revisiones y comentarios sobre el repositorio Clean Marvel.',
            'href' => '/panel-github?lazy=1',
            'cta' => 'Revisar PRs',
        ],
        [
            'title' => 'Repo Marvel',
            'tag' => 'Repositorio',
            'description' => 'Explora carpetas y archivos del repositorio desde Clean Marvel Album.',
            'href' => '/repo-marvel',
            'cta' => 'Ver repo',
        ],
        [
            'title' => 'Performance Marvel',
            'tag' => 'Rendimiento',
            'description' => 'Mide velocidad, metrics clave y cuellos de botella con PageSpeed Insights.',
            'href' => '/performance',
            'cta' => 'Ver rendimiento',
        ],
    [
        'title' => 'Accesibilidad',
        'tag' => 'WAVE API',
        'description' => 'Ejecuta la API de accesibilidad de WebAIM contra las páginas clave del proyecto y revisa errores/contrast en un panel dedicado.',
        'href' => '/accessibility',
        'cta' => 'Abrir panel',
    ],
    [
        'title' => 'Snyk Code Audit',
        'tag' => 'Seguridad de Código',
        'description' => 'Análisis de vulnerabilidades en dependencias y código usando Snyk.',
        'href' => '/snyk',
        'cta' => 'Ver vulnerabilidades',
    ],
    [
        'title' => 'Token & Cost',
        'tag' => 'Monitoreo de IA',
        'description' => 'Panel completo de consumo de IA: tokens, llamadas, coste, latencia y eficiencia del Marvel Agent.',
        'href' => '/secret-ai-metrics',
        'cta' => 'Ver métricas',
    ],
    [
        'title' => 'MARVEL TECH',
        'tag' => 'MARVEL TECH',
        'description' => 'Visión completa de seguridad, arquitectura y buenas prácticas del Clean Marvel Album.',
        'href' => '/marveltech',
        'cta' => 'Abrir sección',
    ],
];

require_once __DIR__ . '/../layouts/header.php';
?>

<header class="app-hero app-hero--tech seccion-hero">
  <div class="app-hero__inner">
    <div class="space-y-3 max-w-3xl">
      <h1 class="app-hero__title text-4xl sm:text-5xl">Secret Room</h1>
      <p class="text-lg text-gray-300 max-w-2xl leading-snug sm:text-xl">
        Secciones avanzadas del proyecto.
      </p>
      <p class="app-hero__meta text-base text-slate-300">
        Este panel centraliza los recursos técnicos y los conecta con la experiencia principal.
      </p>
    </div>
    <div class="flex w-full flex-wrap items-center gap-4 md:flex-nowrap md:gap-6">
      <?php require_once __DIR__ . '/../partials/top-actions.php'; ?>
    </div>
  </div>
</header>

<main id="main-content" tabindex="-1" role="main" class="site-main seccion-main">
  <div class="max-w-6xl mx-auto py-10 px-4 space-y-8">
    <section class="seccion-panel tech-panel space-y-6">
      <div class="space-y-3">
        <div class="seccion-panel__header">
          <h2 class="text-4xl text-white seccion-panel__title">Panel Central Marvel</h2>
          <a href="/agentia" class="btn-marvel-agent">🧠 Marvel Agent</a>
        </div>
      </div>

      <div class="seccion-grid">
        <?php foreach ($sections as $section): ?>
          <article class="seccion-card">
            <p class="seccion-card__tag"><?= htmlspecialchars($section['tag'], ENT_QUOTES, 'UTF-8') ?></p>
            <h3 class="seccion-card__title"><?= htmlspecialchars($section['title'], ENT_QUOTES, 'UTF-8') ?></h3>
            <p class="seccion-card__description"><?= htmlspecialchars($section['description'], ENT_QUOTES, 'UTF-8') ?></p>
            <div class="seccion-card__actions">
              <a class="btn app-hero__cta app-hero__cta-equal app-hero__cta--github seccion-card__link"
                 href="<?= htmlspecialchars($section['href'], ENT_QUOTES, 'UTF-8') ?>">
                <?= htmlspecialchars($section['cta'], ENT_QUOTES, 'UTF-8') ?>
              </a>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    </section>
  </div>
</main>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
