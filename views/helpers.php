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
        $container = $GLOBALS['__clean_marvel_container'] ?? null;
        $csrfManager = is_array($container) ? ($container['security']['csrf'] ?? null) : null;

        if ($csrfManager instanceof \App\Security\Http\CsrfTokenManager) {
            $token = $csrfManager->generate();

            return '<input type="hidden" name="_token" value="' . e($token) . '">';
        }

        return '';
    }
}
