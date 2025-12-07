<?php

declare(strict_types=1);

namespace Src\Http;

class RequestBodyReader
{
    private static ?string $cachedRaw = null;

    /**
     * Devuelve SIEMPRE el mismo raw body en esta petición.
     * Lee php://input solo una vez.
     */
    public static function getRawBody(): string
    {
        if (self::$cachedRaw === null) {
            $raw = $_SERVER['MARVEL_RAW_BODY'] ?? null;

            if (!is_string($raw)) {
                $input = file_get_contents('php://input');
                $raw = $input !== false ? $input : '';
                $_SERVER['MARVEL_RAW_BODY'] = $raw;
            }

            self::$cachedRaw = $raw;

            if ((int) ($_ENV['DEBUG_RAW_BODY'] ?? 0) === 1) {
                $log = __DIR__ . '/../../storage/logs/raw_body_debug.log';
                $prefix = substr($raw, 0, 200);
                @file_put_contents(
                    $log,
                    sprintf("[%s] len=%d preview=%s\n", date('c'), strlen($raw), $prefix),
                    FILE_APPEND
                );
            }
        }

        return self::$cachedRaw;
    }

    /**
     * Devuelve el body decodificado como array asociativo.
     * Lanza RuntimeException si el body no es un JSON válido.
     */
    /** @return array<string, mixed> */
    public static function getJsonArray(): array
    {
        $raw = self::getRawBody();
        
        if (trim($raw) === '') {
            throw new \RuntimeException('El cuerpo de la petición está vacío');
        }

        $decoded = json_decode($raw, true);

        if (!is_array($decoded)) {
            throw new \RuntimeException('El cuerpo no es un JSON válido');
        }

        return $decoded;
    }
}
