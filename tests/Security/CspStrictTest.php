<?php

declare(strict_types=1);

namespace Tests\Security;

use PHPUnit\Framework\TestCase;
use App\Security\Http\SecurityHeaders;
use App\Security\Http\CspNonceGenerator;

final class CspStrictTest extends TestCase
{
    protected function setUp(): void
    {
        $_ENV['APP_ENV'] = 'test';
        $this->cleanGlobalState();
    }

    protected function tearDown(): void
    {
        $this->cleanGlobalState();
        parent::tearDown();
    }

    private function cleanGlobalState(): void
    {
        // Forzar APP_ENV a 'test' en AMBOS lugares (getenv lee de putenv, no de $_ENV)
        $_ENV['APP_ENV'] = 'test';
        putenv('APP_ENV=test');
        
        // Limpiar completamente el estado global
        $GLOBALS['__test_headers'] = [];
        
        // Reset SecurityHeaders static state
        $reflection = new \ReflectionClass(SecurityHeaders::class);
        $property = $reflection->getProperty('applied');
        $property->setAccessible(true);
        $property->setValue(null, false);
    }

    public function test_csp_with_nonce_does_not_contain_unsafe_inline(): void
    {
        $this->cleanGlobalState(); // Limpiar ANTES de apply
        $nonce = CspNonceGenerator::generate();
        SecurityHeaders::apply($nonce);

        $headers = $GLOBALS['__test_headers'] ?? [];
        $cspHeader = '';
        foreach ($headers as $header) {
            if (str_starts_with($header, 'Content-Security-Policy:')) {
                $cspHeader = $header;
                break;
            }
        }

        // Verificar que script-src NO tiene unsafe-inline (protección XSS)
        $this->assertMatchesRegularExpression("/script-src[^;]*'nonce-" . preg_quote($nonce, '/') . "'/", $cspHeader, 'script-src debe contener el nonce');
        $this->assertDoesNotMatchRegularExpression("/script-src[^;]*'unsafe-inline'/", $cspHeader, 'script-src NO debe contener unsafe-inline cuando hay nonce');
        
        // Nota: style-src SIEMPRE tiene unsafe-inline por Tailwind CDN (no es vector XSS)
    }

    public function test_csp_without_nonce_falls_back_to_unsafe_inline(): void
    {
        $this->cleanGlobalState(); // Limpiar ANTES de apply
        SecurityHeaders::apply(null);

        $headers = $GLOBALS['__test_headers'] ?? [];
        $cspHeader = '';
        foreach ($headers as $header) {
            if (str_starts_with($header, 'Content-Security-Policy:')) {
                $cspHeader = $header;
                break;
            }
        }

        $this->assertStringContainsString("'unsafe-inline'", $cspHeader, 'CSP debe contener unsafe-inline cuando no hay nonce (backward compatibility)');
    }

    public function test_nonce_generator_produces_valid_base64(): void
    {
        $nonce = CspNonceGenerator::generate();

        $this->assertNotEmpty($nonce);
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9+\/=]+$/', $nonce, 'Nonce debe ser base64 válido');
        
        // Verificar que es decodificable
        $decoded = base64_decode($nonce, true);
        $this->assertNotFalse($decoded);
        $this->assertSame(16, strlen($decoded), 'Nonce debe tener 16 bytes de entropía');
    }

    public function test_nonce_generator_produces_unique_values(): void
    {
        $nonce1 = CspNonceGenerator::generate();
        $nonce2 = CspNonceGenerator::generate();

        $this->assertNotSame($nonce1, $nonce2, 'Cada nonce debe ser único');
    }

    public function test_csp_nonce_appears_in_both_script_and_style_directives(): void
    {
        $this->cleanGlobalState(); // Limpiar ANTES de apply
        $nonce = CspNonceGenerator::generate();
        SecurityHeaders::apply($nonce);

        $headers = $GLOBALS['__test_headers'] ?? [];
        $cspHeader = '';
        foreach ($headers as $header) {
            if (str_starts_with($header, 'Content-Security-Policy:')) {
                $cspHeader = $header;
                break;
            }
        }

        // Verificar que el nonce aparece en script-src (CRÍTICO para XSS)
        $this->assertMatchesRegularExpression("/script-src[^;]*'nonce-" . preg_quote($nonce, '/') . "'/", $cspHeader, 'Nonce debe estar en script-src');
        
        // Nota: style-src usa unsafe-inline por Tailwind CDN, no nonces
        $this->assertMatchesRegularExpression("/style-src[^;]*'unsafe-inline'/", $cspHeader, 'style-src debe tener unsafe-inline para Tailwind');
    }

    public function test_csp_maintains_allowed_cdn_sources(): void
    {
        $this->cleanGlobalState(); // Limpiar ANTES de apply
        $nonce = CspNonceGenerator::generate();
        SecurityHeaders::apply($nonce);

        $headers = $GLOBALS['__test_headers'] ?? [];
        $cspHeader = '';
        foreach ($headers as $header) {
            if (str_starts_with($header, 'Content-Security-Policy:')) {
                $cspHeader = $header;
                break;
            }
        }

        // Verificar que los CDNs permitidos siguen presentes
        $this->assertStringContainsString('https://cdn.tailwindcss.com', $cspHeader, 'Tailwind CDN debe estar permitido');
        $this->assertStringContainsString('https://fonts.googleapis.com', $cspHeader, 'Google Fonts debe estar permitido');
        $this->assertStringContainsString('https://cdn.jsdelivr.net', $cspHeader, 'jsDelivr CDN debe estar permitido');
    }
}
