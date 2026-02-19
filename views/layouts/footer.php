<?php

declare(strict_types=1);

$scripts = $scripts ?? [];
$globalHeatmapScript = '/assets/js/heatmap-tracker.js';
$scripts = array_merge([$globalHeatmapScript], $scripts);
?>

  <!-- FOOTER -->
  <footer class="site-footer" role="contentinfo">
    <div style="display: flex; flex-direction: column; align-items: center; gap: 5px;">
      <small>© creawebes 2025-2026 · Clean Marvel Album</small>
      <small style="opacity: 0.7; font-size: 0.75rem;">Todo el contenido y activos son propiedad de © 2026 MARVEL</small>
      <small style="opacity: 0.7; font-size: 0.75rem;">Proyecto con fines puramente académicos y educativos.</small>
    </div>
  </footer>

<?php
$projectRoot = dirname(__DIR__, 2);
$publicPath = $projectRoot . '/public';

foreach ($scripts as $script):
    $versionSuffix = '';
    $normalizedScript = is_string($script) ? $script : '';

    if ($normalizedScript !== '') {
        $candidatePath = $publicPath . $normalizedScript;
        if (!is_file($candidatePath)) {
            $candidatePath = $projectRoot . $normalizedScript;
        }
        if (is_file($candidatePath)) {
            $versionSuffix = (str_contains($normalizedScript, '?') ? '&' : '?') . 'v=' . filemtime($candidatePath);
        }
    }
?>
  <?php $cspNonce = $_SERVER['CSP_NONCE'] ?? null; ?>
  <script type="module" src="<?= htmlspecialchars($normalizedScript . $versionSuffix, ENT_QUOTES, 'UTF-8') ?>"<?= $cspNonce ? ' nonce="' . htmlspecialchars($cspNonce, ENT_QUOTES, 'UTF-8') . '"' : '' ?>></script>
<?php endforeach; ?>
</body>
</html>
