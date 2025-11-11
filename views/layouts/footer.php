<?php

declare(strict_types=1);

$scripts = $scripts ?? [];
?>

  <!-- FOOTER -->
  <footer class="site-footer">
    <small>© creawebes 2025 · Clean Marvel Album</small>
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
  <script type="module" src="<?= htmlspecialchars($normalizedScript . $versionSuffix, ENT_QUOTES, 'UTF-8') ?>"></script>
<?php endforeach; ?>
</body>
</html>
