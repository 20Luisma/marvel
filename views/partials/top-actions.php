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
  <a href="/seccion" class="<?= $ctaBaseClasses('secret') ?> app-hero__cta--github"<?= $aria('secret') ?>>Secret Room</a>
</div>
