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
            $input = file_get_contents('php://input');
            self::$cachedRaw = $input !== false ? $input : '';
        }
        return self::$cachedRaw;
    }

    /**
     * Devuelve el body decodificado como array asociativo.
     * Lanza RuntimeException si el body no es un JSON válido.
     */
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
