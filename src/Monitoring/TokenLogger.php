<?php

declare(strict_types=1);

namespace App\Monitoring;

use RuntimeException;

class TokenLogger
{
    private const LOG_FILE = __DIR__ . '/../../storage/ai/tokens.log';

    /**
     * @param array{
     *   feature: string,
     *   model: string,
     *   endpoint: string,
     *   prompt_tokens: int,
     *   completion_tokens: int,
     *   total_tokens: int,
     *   latency_ms: int,
     *   tools_used?: int,
     *   success: bool,
     *   error?: string|null,
     *   user_id?: string,
     *   context_size?: int
     * } $data
     */
    public static function log(array $data): void
    {
        $logDir = dirname(self::LOG_FILE);
        if (!is_dir($logDir)) {
            if (!mkdir($logDir, 0755, true) && !is_dir($logDir)) {
                throw new RuntimeException("No se pudo crear el directorio de logs: {$logDir}");
            }
        }

        $entry = [
            'ts' => date('c'),
            'feature' => $data['feature'] ?? 'unknown',
            'model' => $data['model'] ?? 'unknown',
            'endpoint' => $data['endpoint'] ?? 'unknown',
            'prompt_tokens' => $data['prompt_tokens'] ?? 0,
            'completion_tokens' => $data['completion_tokens'] ?? 0,
            'total_tokens' => $data['total_tokens'] ?? 0,
            'latency_ms' => $data['latency_ms'] ?? 0,
            'tools_used' => $data['tools_used'] ?? 0,
            'success' => $data['success'] ?? true,
            'error' => $data['error'] ?? null,
            'user_id' => $data['user_id'] ?? 'demo',
            'context_size' => $data['context_size'] ?? 0,
        ];

        $json = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return;
        }

        file_put_contents(self::LOG_FILE, $json . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}
