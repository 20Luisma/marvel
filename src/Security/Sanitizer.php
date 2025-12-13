<?php

declare(strict_types=1);

namespace App\Security;

final class Sanitizer
{
    public function sanitizeString(string $value): string
    {
        $clean = $value;
        // Elimina control chars invisibles.
        $clean = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $clean) ?? '';
        // Elimina scripts y PHP tags.
        $clean = preg_replace('#<script\b[^>]*>(.*?)</script>#is', '', $clean) ?? $clean;
        $clean = str_replace(['<?php', '<?', '?>'], '', $clean);
        // Elimina payloads JNDI básicos.
        $clean = str_ireplace('${jndi:ldap://', '', $clean);

        // Recorta longitud para evitar abusos.
        if (mb_strlen($clean) > 2000) {
            $clean = mb_substr($clean, 0, 2000);
        }

        // Whitelist aproximada: letras, números, puntuación, espacios, saltos de línea y símbolos (ej. emojis, ★).
        $clean = preg_replace('/[^\p{L}\p{N}\p{P}\p{Zs}\p{S}\r\n\t]/u', '', $clean) ?? $clean;

        return trim($clean);
    }
}
