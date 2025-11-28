<?php

declare(strict_types=1);

if (!function_exists('e')) {
    /**
     * Escape HTML output consistently to prevenir XSS en vistas.
 */
    function e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('csrf_field')) {
    /**
     * Renderiza un campo hidden con el token CSRF actual.
     */
    function csrf_field(): string
    {
        $token = null;
        $container = $GLOBALS['__clean_marvel_container'] ?? null;
        $csrfManager = is_array($container) ? ($container['security']['csrf'] ?? null) : null;

        if ($csrfManager instanceof \App\Security\Http\CsrfTokenManager) {
            $token = $csrfManager->generate();
        } else {
            $token = \App\Security\Csrf\CsrfService::generateToken();
        }

        $escaped = e((string) $token);

        // Incluimos ambos nombres para compatibilidad con middleware nuevo y controladores existentes.
        return sprintf(
            '<input type="hidden" name="csrf_token" value="%1$s"><input type="hidden" name="_token" value="%1$s">',
            $escaped
        );
    }
}
