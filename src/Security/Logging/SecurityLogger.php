<?php

declare(strict_types=1);

namespace App\Security\Logging;

use App\Security\LogSanitizer;

final class SecurityLogger
{
    private string $logFile;

    public function __construct(?string $logFile = null)
    {
        $this->logFile = $logFile ?? dirname(__DIR__, 3) . '/storage/logs/security.log';
    }

    public function logFile(): string
    {
        return $this->logFile;
    }


    /**
     * @param array<string, mixed> $context
     */
    public function logEvent(string $type, array $context): void
    {
        $sanitizedContext = LogSanitizer::sanitizeContext($context);

        $directory = dirname($this->logFile);
        if (!is_dir($directory)) {
            @mkdir($directory, 0775, true);
        }

        $traceId = $sanitizedContext['trace_id'] ?? ($_SERVER['X_TRACE_ID'] ?? '-');
        $ip = $sanitizedContext['ip'] ?? ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $path = $sanitizedContext['path'] ?? 'unknown';

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
     * @param array<string, mixed> $context
     */
    private function formatContext(array $context): string
    {
        $parts = [];
        foreach ($context as $key => $value) {
            if (in_array($key, ['trace_id', 'ip', 'path'], true)) {
                continue;
            }
            if (is_array($value)) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
            $parts[] = sprintf('%s=%s', $key, $value === null ? '-' : $value);
        }

        return implode(' ', $parts);
    }
}
