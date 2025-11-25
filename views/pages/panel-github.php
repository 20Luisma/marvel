<?php

declare(strict_types=1);

@ini_set('max_execution_time', '650');
@ini_set('default_socket_timeout', '650');
@set_time_limit(650);

$pageTitle = 'Clean Marvel Album — GitHub PRs';
$additionalStyles = ['/assets/css/panel-github.css'];
$activeTopAction = 'github';
$bodyClass = 'text-gray-200 min-h-screen bg-[#0b0d17] panel-github-page';

$root = dirname(__DIR__, 2);
require_once $root . '/app/Services/GithubClient.php';

/**
 * Normaliza una fecha proveniente del query string a YYYY-MM-DD.
 *
 * @param array<string, mixed>|null $errorRef
 */
function normalizeDateParamView(string $param, string $default, ?array &$errorRef): string
{
  $raw = $_GET[$param] ?? null;
  if ($raw === null || trim((string) $raw) === '') {
    return $default;
  }

  $normalized = normalizeDateView((string) $raw);
  if ($normalized === null) {
    $errorRef = [
      'error' => "Fecha '{$param}' inválida. Usa YYYY-MM-DD o DD/MM/AAAA.",
      'status' => 400,
      'detail' => 'Formato de fecha inválido en el panel.',
    ];
    return $default;
  }

  return $normalized;
}

/**
 * @return string|null
 */
function normalizeDateView(string $value): ?string
{
  $value = trim($value);
  if ($value === '') {
    return null;
  }

  $formats = ['Y-m-d', 'd/m/Y', 'd-m-Y'];
  foreach ($formats as $format) {
    $dt = DateTimeImmutable::createFromFormat('!' . $format, $value);
    if ($dt instanceof DateTimeImmutable) {
      return $dt->format('Y-m-d');
    }
  }

  $timestamp = strtotime($value);
  if ($timestamp !== false) {
    return gmdate('Y-m-d', $timestamp);
  }

  return null;
}

$lazyMode = isset($_GET['lazy']) && $_GET['lazy'] === '1';

$dateError = null;
$from = normalizeDateParamView('from', date('Y-m-d', strtotime('-14 days')), $dateError);
$to   = normalizeDateParamView('to', date('Y-m-d'), $dateError);

if ($lazyMode) {
  $data = ['lazy' => true];
} else {
  $client = new \App\Services\GithubClient($root);
  $data = $dateError !== null
    ? $dateError
    : $client->fetchActivity($from, $to);
}

/**
 * @param array<string, mixed>|array<int, mixed>|null $payload
 * @return array<int, array<string, mixed>>
 */
function extract_activity_entries($payload): array
{
  if (!is_array($payload)) {
    return [];
  }

  if (isset($payload['error'])) {
    return [];
  }

  if (isset($payload['data']) && is_array($payload['data'])) {
    return $payload['data'];
  }

  if (array_is_list($payload)) {
    return $payload;
  }

  return [];
}

$blocks = [];
$hasError = false;
if (is_array($data) && ($data['lazy'] ?? false) === true) {
  $lazyMode = true;
  $blocks = [];
  $hasError = false;
} else {
  $blocks = extract_activity_entries($data);
  $hasError = !is_array($data) || isset($data['error']);
}
$repoOwner = \App\Services\GithubClient::OWNER;
$repoName = \App\Services\GithubClient::REPO;

require_once __DIR__ . '/../layouts/header.php';
?>

<header class="app-hero app-hero--tech panel-github__hero">
  <div class="app-hero__inner">
    <div class="space-y-3 max-w-3xl">
      <div class="space-y-3">
        <h1 class="app-hero__title">Clean Architecture with Marvel</h1>
        <p class="text-lg text-gray-300 leading-snug max-w-2xl sm:text-xl">
          Visualiza la actividad reciente de Pull Requests del repositorio marvel.
        </p>
        <p class="app-hero__meta text-base text-slate-300">
          GitHub integrado para ver actividad de cada PR.
        </p>
      </div>
    </div>
    <div class="flex w-full flex-wrap items-center gap-4 md:flex-nowrap md:gap-6">
      <?php require_once __DIR__ . '/../partials/top-actions.php'; ?>
    </div>
  </div>
</header>

<main id="main-content" tabindex="-1" role="main" class="site-main panel-github">
  <div class="panel-github__wrap">
    <section class="panel-github__card section-lined space-y-4 tech-panel">
      <header class="space-y-1">
        <h2>Reporte de Pull Requests</h2>
        <p class="text-slate-300 text-base">
          Consulta PRs abiertos, mergeados o cerrados con estadísticas de commits, reviews y reviewers.
        </p>
      </header>

      <form class="panel-github__filters" method="get" action="/panel-github">
        <label>Desde
          <input type="date" name="from" value="<?= htmlspecialchars($from) ?>">
        </label>
        <label>Hasta
          <input type="date" name="to" value="<?= htmlspecialchars($to) ?>">
        </label>
        <button class="btn-primary" type="submit">Ver PRs</button>
      </form>

      <?php if ($lazyMode): ?>
        <?php // Sin placeholder inicial en modo lazy ?>
      <?php elseif ($hasError): ?>
        <div class="panel-github__message panel-github__message--error" role="alert" aria-live="assertive" aria-atomic="true">
          <strong>Sin datos del API.</strong>
          <p><?= htmlspecialchars($data['error'] ?? 'Error desconocido') ?></p>
          <?php if (isset($data['detail'])): ?>
            <p>Detalle: <?= htmlspecialchars((string) $data['detail']) ?></p>
          <?php endif; ?>
          <?php if (isset($data['status'])): ?>
            <p>HTTP: <?= htmlspecialchars((string) $data['status']) ?></p>
          <?php endif; ?>
          <?php if (isset($data['body'])): ?>
            <p>Payload: <?= htmlspecialchars(is_string($data['body']) ? $data['body'] : json_encode($data['body'])) ?></p>
          <?php endif; ?>
          <p>Revisa que <code>GITHUB_API_KEY</code> esté configurada con un token personal válido.</p>
        </div>
      <?php elseif (!$blocks): ?>
        <div class="panel-github__message panel-github__message--empty" role="status" aria-live="polite" aria-atomic="true">
          <strong>Sin actividad reciente.</strong>
          <p>No hay Pull Requests en este rango para el repositorio <code><?= htmlspecialchars($repoOwner) ?>/<?= htmlspecialchars($repoName) ?></code>.</p>
        </div>
      <?php else: ?>
        <div class="panel-github__row">
          <?php foreach ($blocks as $entry): ?>
            <?php
            $details = $entry['details'] ?? [];
            if (!is_array($details)) {
              $details = [];
            }

            $metaText = '';
            if (!empty($entry['meta'])) {
              $metaText = (string) $entry['meta'];
            } else {
              $metaParts = [];
              if (isset($details['commit_count'])) {
                $metaParts[] = 'Commits: ' . (string) $details['commit_count'];
              }
              if (isset($details['review_count'])) {
                $metaParts[] = 'Reviews: ' . (string) $details['review_count'];
              }
              if (!empty($details['reviewers']) && is_array($details['reviewers'])) {
                $metaParts[] = 'Reviewers: ' . implode(', ', $details['reviewers']);
              }
              $metaText = implode(' · ', array_filter($metaParts));
            }

            $dateLine = 'Creado: ' . htmlspecialchars((string) ($details['created_at'] ?? 'N/D'));
            if (!empty($details['updated_at'])) {
              $dateLine .= ' · Actualizado: ' . htmlspecialchars((string) $details['updated_at']);
            }
            if (!empty($details['merged_at'])) {
              $dateLine .= ' · Mergeado: ' . htmlspecialchars((string) $details['merged_at']);
            }
            ?>
            <article class="panel-github__pr">
              <h3><?= htmlspecialchars($entry['title'] ?? 'Actividad') ?></h3>
              <?php if (!empty($entry['subtitle'])): ?>
                <p class="panel-github__subtitle"><?= htmlspecialchars((string) $entry['subtitle']) ?></p>
              <?php endif; ?>

              <?php if ($metaText !== ''): ?>
                <p class="panel-github__meta"><?= htmlspecialchars($metaText) ?></p>
              <?php endif; ?>

              <div class="panel-github__actions">
                <span><?= $dateLine ?></span>
                <?php if (!empty($details['url'])): ?>
                  <a class="panel-github__link" href="<?= htmlspecialchars((string) $details['url']) ?>" target="_blank" rel="noopener">
                    Ver en GitHub →
                  </a>
                <?php endif; ?>
              </div>

              <?php if (!empty($details['labels']) && is_array($details['labels'])): ?>
                <div class="panel-github__labels">
                  <?php foreach ($details['labels'] as $label): ?>
                    <span class="panel-github__label"><?= htmlspecialchars((string) $label) ?></span>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </article>
          <?php endforeach; ?>
        </div>
        <p class="panel-github__footnote">
          Fuente: API REST de GitHub para <code><?= htmlspecialchars($repoOwner) ?>/<?= htmlspecialchars($repoName) ?></code>.
          Asegúrate de que el token personal tenga permisos de lectura sobre el repositorio.
        </p>
      <?php endif; ?>

      <div id="panel-github-loader" class="panel-github__loader hidden" role="status" aria-live="polite" aria-atomic="true">
        <span class="perf-loader" aria-hidden="true"><span></span><span></span><span></span></span>
        <span class="panel-github__loader-text">Consultando GitHub…</span>
      </div>
    </section>
  </div>
</main>

<!-- 🔥 JS INLINE SOLO PARA ESTE PANEL -->
<script>
  // Helpers
  const enableGithubButton = (button) => {
    if (!button) return;
    button.disabled = false;
    button.classList.remove('is-disabled');
    const fallbackLabel = button.dataset.originalLabel || 'Ver PRs';
    button.textContent = fallbackLabel;
  };

  const disableGithubButton = (button) => {
    if (!button) return;
    if (!button.dataset.originalLabel) {
      button.dataset.originalLabel = (button.textContent || 'Ver PRs').trim();
    }
    button.disabled = true;
    button.classList.add('is-disabled');
    button.textContent = 'Cargando…';
  };

  document.addEventListener('DOMContentLoaded', () => {
    console.log('🔥 JS inline del panel GitHub cargado');

    const filterForm = document.querySelector('.panel-github__filters');
    if (!filterForm) {
      console.warn('No se encontró .panel-github__filters');
      return;
    }

    const submitButton = filterForm.querySelector('button[type="submit"]');
    const dateInputs = filterForm.querySelectorAll('input[type="date"]');
    const loader = document.getElementById('panel-github-loader');
    const loaderText = loader ? loader.querySelector('.panel-github__loader-text') : null;

    filterForm.addEventListener('submit', (event) => {
      event.preventDefault();

      disableGithubButton(submitButton);

      if (loader) {
        if (loaderText) {
          loaderText.textContent = 'Consultando GitHub…';
        }
        loader.classList.remove('hidden');
        loader.style.display = 'flex';
        loader.scrollIntoView({
          behavior: 'smooth',
          block: 'end'
        });
      }

      window.requestAnimationFrame(() => {
        setTimeout(() => {
          filterForm.submit();
        }, 80);
      });
    });

    dateInputs.forEach((input) => {
      input.addEventListener('input', () => {
        if (submitButton && submitButton.disabled) {
          enableGithubButton(submitButton);
        }
      });
    });
  });
</script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
