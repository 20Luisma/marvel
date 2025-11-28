<?php

declare(strict_types=1);

namespace App\Security\Logging;

final class SecurityLogger
{
    private string $logFile;

    public function __construct(?string $logFile = null)
    {
        $this->logFile = $logFile ?? dirname(__DIR__, 3) . '/storage/logs/security.log';
    }

    /**
     * @param array<string, scalar|null> $context
     */
    public function logEvent(string $type, array $context): void
    {
        $directory = dirname($this->logFile);
        if (!is_dir($directory)) {
            @mkdir($directory, 0775, true);
        }

        $traceId = $context['trace_id'] ?? ($_SERVER['X_TRACE_ID'] ?? '-');
        $ip = $context['ip'] ?? ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $path = $context['path'] ?? 'unknown';

        $line = sprintf(
            '[%s] event=%s trace_id=%s ip=%s path=%s %s',
            date('Y-m-d H:i:s'),
            $type,
            is_string($traceId) ? $traceId : '-',
            is_string($ip) ? $ip : 'unknown',
            is_string($path) ? $path : 'unknown',
            $this->formatContext($context)
        );

        @file_put_contents($this->logFile, trim($line) . PHP_EOL, FILE_APPEND);
    }

    /**
     * @param array<string, scalar|null> $context
     */
    private function formatContext(array $context): string
    {
        $parts = [];
        foreach ($context as $key => $value) {
            if (in_array($key, ['trace_id', 'ip', 'path'], true)) {
                continue;
            }
            $parts[] = sprintf('%s=%s', $key, $value === null ? '-' : $value);
        }

        return implode(' ', $parts);
    }
}
