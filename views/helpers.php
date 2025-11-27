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
