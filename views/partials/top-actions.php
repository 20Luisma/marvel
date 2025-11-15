<?php

declare(strict_types=1);

$activeTopAction = $activeTopAction ?? null;

$ctaBaseClasses = static fn (string $action) => trim(
    'btn app-hero__cta app-hero__cta-equal' . ($activeTopAction === $action ? ' is-active' : '')
);

$aria = static fn (string $action): string => $activeTopAction === $action ? ' aria-current="page"' : '';
?>
<div class="flex items-center gap-3 ml-auto">
  <a href="/albums" class="<?= $ctaBaseClasses('home') ?> app-hero__cta--flat"<?= $aria('home') ?>>Inicio</a>
  <a href="/comic" class="<?= $ctaBaseClasses('comic') ?> app-hero__cta--flat"<?= $aria('comic') ?>>Crear Cómic</a>
  <a href="/oficial-marvel" class="<?= $ctaBaseClasses('official') ?>"<?= $aria('official') ?>>Oficial Marvel</a>
  <a href="/sonar" class="<?= $ctaBaseClasses('sonar') ?> app-hero__cta--github"<?= $aria('sonar') ?>>SonarCloud</a>
  <a href="/panel-github" class="<?= $ctaBaseClasses('github') ?> app-hero__cta--github"<?= $aria('github') ?>>GitHub PRs</a>
  <a id="btn-readme" href="/readme" class="<?= $ctaBaseClasses('readme') ?> btn-readme app-hero__cta--github"<?= $aria('readme') ?>>
    <span>README</span>
  </a>
</div>
