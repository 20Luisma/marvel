<?php

declare(strict_types=1);

namespace App\Security\Http;

/**
 * Generador de nonces para Content Security Policy.
 * Cada nonce debe ser único por request y usarse tanto en headers CSP
 * como en atributos nonce de <script> y <style>.
 */
final class CspNonceGenerator
{
    /**
     * Genera un nonce criptográficamente seguro.
     * 
     * @return string Base64 del nonce (apto para CSP y HTML)
     */
    public static function generate(): string
    {
        return base64_encode(random_bytes(16));
    }
}
