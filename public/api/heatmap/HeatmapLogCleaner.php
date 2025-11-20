<?php

declare(strict_types=1);

/**
 * HeatmapLogCleaner mantiene los logs mensuales y los borra automáticamente cada cierto tiempo.
 * Por defecto retiene 6 meses y ejecuta la limpieza no más de una vez cada 24 horas.
 * Puedes ajustar estos valores pasando los parámetros al constructor.
 */
final class HeatmapLogCleaner
{
    private const LEGACY_LOG = 'clicks.log';
    private const LOG_FORMAT = 'clicks_%s.jsonl';
    private const LAST_CLEANUP = 'last_cleanup.json';

    public function __construct(
        private readonly string $storagePath,
        private readonly int $retentionMonths = 6,
        private readonly int $cleanupIntervalSeconds = 86400
    ) {
    }

    public function ensureStorage(): void
    {
        if (!is_dir($this->storagePath) && !mkdir($this->storagePath, 0775, true) && !is_dir($this->storagePath)) {
            throw new RuntimeException('No se puede crear el directorio de heatmap.');
        }
    }

    public function prepare(): void
    {
        $this->ensureStorage();
        $this->migrateLegacyLog();
    }

    public function monthlyLogPath(int $timestamp): string
    {
        return $this->storagePath . '/' . sprintf(self::LOG_FORMAT, date('Y-m', $timestamp));
    }

    public function getLogFiles(): array
    {
        $files = glob($this->storagePath . '/clicks_*.jsonl') ?: [];
        $legacyPath = $this->storagePath . '/' . self::LEGACY_LOG;
        if (is_file($legacyPath)) {
            $files[] = $legacyPath;
        }
        sort($files);

        return array_values($files);
    }

    public function maybeCleanup(int $now): void
    {
        $this->prepare();
        if (!$this->shouldRunCleanup($now)) {
            return;
        }

        $this->cleanupOldLogs($now);
        $this->recordCleanupTimestamp($now);
    }

    private function migrateLegacyLog(): void
    {
        $legacyPath = $this->storagePath . '/' . self::LEGACY_LOG;
        if (!is_file($legacyPath)) {
            return;
        }

        $modTime = filemtime($legacyPath) ?: time();
        $targetPath = $this->monthlyLogPath($modTime);

        if (!is_file($targetPath)) {
            rename($legacyPath, $targetPath);
            return;
        }

        $content = file_get_contents($legacyPath);
        if ($content !== false) {
            @file_put_contents($targetPath, $content, FILE_APPEND | LOCK_EX);
        }
        @unlink($legacyPath);
    }

    private function shouldRunCleanup(int $now): bool
    {
        $last = $this->readLastCleanupTimestamp();
        if ($last === null) {
            return true;
        }

        return ($now - $last) >= $this->cleanupIntervalSeconds;
    }

    private function readLastCleanupTimestamp(): ?int
    {
        $path = $this->storagePath . '/' . self::LAST_CLEANUP;
        if (!is_file($path)) {
            return null;
        }

        $json = json_decode(file_get_contents($path) ?: '', true);
        if (!is_array($json) || !isset($json['timestamp'])) {
            return null;
        }

        return is_numeric($json['timestamp']) ? (int) $json['timestamp'] : null;
    }

    private function recordCleanupTimestamp(int $timestamp): void
    {
        $path = $this->storagePath . '/' . self::LAST_CLEANUP;
        file_put_contents($path, json_encode(['timestamp' => $timestamp], JSON_UNESCAPED_UNICODE), LOCK_EX);
    }

    private function cleanupOldLogs(int $now): void
    {
        $threshold = (new DateTimeImmutable())->setTimestamp($now)->modify(sprintf('-%d months', $this->retentionMonths))->modify('first day of this month');
        foreach ($this->collectLogMetadata() as $file) {
            if ($this->isThresholdExceeded($file['year'], $file['month'], $threshold)) {
                @unlink($file['path']);
            }
        }
    }

    /**
     * @return array<int,array{path:string,year:int,month:int}>
     */
    private function collectLogMetadata(): array
    {
        $metadata = [];
        foreach ($this->getLogFiles() as $path) {
            $base = basename($path);
            if (preg_match('/^clicks_(\d{4})-(\d{2})\.jsonl$/', $base, $matches) === 1) {
                $metadata[] = [
                    'path' => $path,
                    'year' => (int) $matches[1],
                    'month' => (int) $matches[2],
                ];
            }
        }

        return $metadata;
    }

    private function isThresholdExceeded(int $year, int $month, DateTimeImmutable $threshold): bool
    {
        $fileDate = DateTimeImmutable::createFromFormat('!Y-m', sprintf('%04d-%02d', $year, $month));
        if ($fileDate === false) {
            return false;
        }

        return $fileDate < $threshold;
    }
}
