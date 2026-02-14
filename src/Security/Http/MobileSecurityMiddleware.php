<?php

declare(strict_types=1);

namespace App\Security\Http;

use App\Shared\Http\JsonResponse;

final class MobileSecurityMiddleware
{
    private const MOBILE_KEY_HEADER = 'X-Mobile-Key';

    public function __construct(
        private readonly ?string $expectedKey,
        private readonly string $environment = 'production'
    ) {
    }

    public function handle(string $method, string $path): bool
    {
        if (!$this->isSensitivePath($path)) {
            return true;
        }

        if ($this->expectedKey === null || $this->expectedKey === '') {
            // Fail-closed en producción si la clave no está configurada
            if ($this->environment === 'production') {
                JsonResponse::error('Configuración de seguridad incompleta: MOBILE_KEY ausente en producción.', 500);
                return false;
            }
            return true;
        }

        $serverHeaderKey = 'HTTP_' . strtoupper(str_replace('-', '_', self::MOBILE_KEY_HEADER));
        $providedKey = $_SERVER[$serverHeaderKey] ?? '';

        if (!hash_equals($this->expectedKey, $providedKey)) {
            JsonResponse::error('Acceso denegado: Mobile Key inválida o ausente.', 403);
            return false;
        }

        return true;
    }


    private function isSensitivePath(string $path): bool
    {
        $sensitivePatterns = [
            '#^/comics/generate$#',
            '#^/api/rag/.*$#',
            '#^/api/tts$#',
            '#^/admin/.*$#'
        ];

        foreach ($sensitivePatterns as $pattern) {
            if (preg_match($pattern, $path) === 1) {
                return true;
            }
        }

        return false;
    }
}
