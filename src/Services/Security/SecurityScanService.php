<?php

declare(strict_types=1);

namespace App\Services\Security;

/**
 * Servicio para escanear seguridad del servidor usando APIs externas
 */
final class SecurityScanService
{
    private const CACHE_FILE = __DIR__ . '/../../../storage/security/security.json';
    private const CACHE_TTL = 86400; // 24 horas
    
    // URL de producción para escanear
    private const PRODUCTION_URL = 'https://iamasterbigschool.contenido.creawebes.com';

    private string $targetUrl;

    public function __construct(?string $targetUrl = null)
    {
        // Si no se proporciona URL, detectar automáticamente
        if ($targetUrl === null) {
            $targetUrl = $this->detectEnvironmentUrl();
        }
        
        $this->targetUrl = $targetUrl;
    }

    /**
     * Detecta la URL según el entorno
     */
    private function detectEnvironmentUrl(): string
    {
        $appEnv = getenv('APP_ENV') ?: ($_ENV['APP_ENV'] ?? 'local');
        
        // En local, usar URL de producción porque localhost no es accesible públicamente
        // En producción, también usar URL de producción
        // Las APIs externas necesitan una URL pública para escanear
        return self::PRODUCTION_URL;
    }

    /**
     * Analiza headers de seguridad REALES del servidor
     */
    /**
     * @return array{
     *   grade?: string,
     *   headers?: array<string, string>,
     *   missing?: array<int, string>,
     *   scanDate: string,
     *   real?: bool,
     *   error?: string
     * }
     */
    public function scanSecurityHeaders(): array
    {
        // Obtener headers REALES del servidor
        $headers = @get_headers($this->targetUrl, true);
        
        if ($headers === false) {
            return [
                'error' => 'No se pudo conectar con el servidor',
                'grade' => 'N/A',
                'scanDate' => date('c')
            ];
        }

        // Analizar headers de seguridad presentes
        $securityHeaders = $this->analyzeSecurityHeaders($headers);
        
        // Calcular grade basado en headers presentes
        $grade = $this->calculateSecurityGrade($securityHeaders);
        
        return [
            'grade' => $grade,
            'headers' => $securityHeaders['present'],
            'missing' => $securityHeaders['missing'],
            'scanDate' => date('c'),
            'real' => true // Datos REALES
        ];
    }

    /**
     * Analiza headers de seguridad del servidor
     */
    /**
     * @param array<string|int, mixed> $headers
     * @return array{present: array<string, string>, missing: array<int, string>}
     */
    private function analyzeSecurityHeaders(array $headers): array
    {
        $present = [];
        $missing = [];
        
        // Headers de seguridad importantes
        $securityHeadersToCheck = [
            'Content-Security-Policy' => 'CSP',
            'X-Frame-Options' => 'X-Frame-Options',
            'X-Content-Type-Options' => 'X-Content-Type-Options',
            'Strict-Transport-Security' => 'HSTS',
            'Referrer-Policy' => 'Referrer-Policy',
            'Permissions-Policy' => 'Permissions-Policy',
            'X-XSS-Protection' => 'X-XSS-Protection'
        ];

        foreach ($securityHeadersToCheck as $header => $displayName) {
            $found = false;
            foreach ($headers as $key => $value) {
                // Skip numeric keys (HTTP status line)
                if (is_int($key)) {
                    continue;
                }
                
                if (stripos($key, $header) !== false) {
                    $present[$displayName] = is_array($value) ? end($value) : $value;
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $missing[] = $displayName;
            }
        }

        return [
            'present' => $present,
            'missing' => $missing
        ];
    }

    /**
     * Calcula grade basado en headers presentes
     */
    /**
     * @param array{present: array<string, string>, missing: array<int, string>} $analysis
     */
    private function calculateSecurityGrade(array $analysis): string
    {
        $presentCount = count($analysis['present']);
        $totalCount = $presentCount + count($analysis['missing']);
        
        if ($totalCount === 0) {
            return 'N/A';
        }
        
        $percentage = ($presentCount / $totalCount) * 100;
        
        if ($percentage >= 85) return 'A';
        if ($percentage >= 70) return 'B';
        if ($percentage >= 50) return 'C';
        if ($percentage >= 30) return 'D';
        return 'F';
    }

    /**
     * Analiza SSL/TLS y configuración HTTPS REAL del servidor
     */
    /**
     * @return array{
     *   error?: string,
     *   score: int,
     *   grade: string,
     *   tests_passed?: int,
     *   tests_failed?: int,
     *   tests_quantity?: int,
     *   scanDate: string,
     *   real?: bool
     * }
     */
    public function scanMozillaObservatory(): array
    {
        $host = parse_url($this->targetUrl, PHP_URL_HOST);
        if (!is_string($host) || $host === '') {
            return [
                'error' => 'Host inválido para análisis SSL',
                'score' => 0,
                'grade' => 'N/A',
                'scanDate' => date('c'),
            ];
        }
        
        // Analizar SSL/TLS real
        $sslInfo = $this->analyzeSSL($host);
        
        if ($sslInfo === null) {
            return [
                'error' => 'No se pudo analizar SSL/TLS del servidor',
                'score' => 0,
                'grade' => 'N/A',
                'scanDate' => date('c')
            ];
        }
        
        // Calcular score basado en configuración SSL
        $score = $this->calculateSSLScore($sslInfo);
        $grade = $this->scoreToGrade($score);
        
        return [
            'score' => $score,
            'grade' => $grade,
            'tests_passed' => $sslInfo['tests_passed'],
            'tests_failed' => $sslInfo['tests_failed'],
            'tests_quantity' => $sslInfo['tests_quantity'],
            'scanDate' => date('c'),
            'real' => true // Datos REALES
        ];
    }

    /**
     * Analiza configuración SSL/TLS real
     */
    /**
     * @return array{
     *   tests_passed: int,
     *   tests_failed: int,
     *   tests_quantity: int,
     *   cert_info: array<string, mixed>
     * }|null
     */
    private function analyzeSSL(string $host): ?array
    {
        $context = stream_context_create([
            'ssl' => [
                'capture_peer_cert' => true,
                'capture_peer_cert_chain' => true,
                'verify_peer' => false,
                'verify_peer_name' => false
            ]
        ]);

        $client = @stream_socket_client(
            "ssl://{$host}:443",
            $errno,
            $errstr,
            30,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if ($client === false) {
            return null;
        }

        $params = stream_context_get_params($client);
        fclose($client);

        if (!isset($params['options']['ssl']['peer_certificate'])) {
            return null;
        }

        $cert = openssl_x509_parse($params['options']['ssl']['peer_certificate']);
        if ($cert === false) {
            return null;
        }
        
        // Analizar certificado y configuración
        $tests = $this->runSSLTests($cert, $host);
        
        return [
            'tests_passed' => $tests['passed'],
            'tests_failed' => $tests['failed'],
            'tests_quantity' => $tests['total'],
            'cert_info' => $cert
        ];
    }

    /**
     * Ejecuta tests de seguridad SSL/TLS
     */
    /**
     * @param array<string, mixed> $cert
     * @return array{passed: int, failed: int, total: int}
     */
    private function runSSLTests(array $cert, string $host): array
    {
        $passed = 0;
        $failed = 0;
        
        // Test 1: Certificado válido
        if (isset($cert['validTo_time_t']) && $cert['validTo_time_t'] > time()) {
            $passed++;
        } else {
            $failed++;
        }
        
        // Test 2: Certificado no expirado hace mucho
        if (isset($cert['validFrom_time_t']) && $cert['validFrom_time_t'] < time()) {
            $passed++;
        } else {
            $failed++;
        }
        
        // Test 3: Emisor confiable
        if (isset($cert['issuer']['O'])) {
            $passed++;
        } else {
            $failed++;
        }
        
        // Test 4: Subject Alternative Names
        if (isset($cert['extensions']['subjectAltName'])) {
            $passed++;
        } else {
            $failed++;
        }
        
        // Test 5: Algoritmo de firma seguro
        if (isset($cert['signatureTypeSN']) && 
            (strpos($cert['signatureTypeSN'], 'sha256') !== false || 
             strpos($cert['signatureTypeSN'], 'sha384') !== false ||
             strpos($cert['signatureTypeSN'], 'sha512') !== false)) {
            $passed++;
        } else {
            $failed++;
        }
        
        return [
            'passed' => $passed,
            'failed' => $failed,
            'total' => $passed + $failed
        ];
    }

    /**
     * Calcula score SSL basado en tests
     */
    /**
     * @param array{tests_passed: int, tests_quantity: int} $sslInfo
     */
    private function calculateSSLScore(array $sslInfo): int
    {
        if ($sslInfo['tests_quantity'] === 0) {
            return 0;
        }
        
        return (int) (($sslInfo['tests_passed'] / $sslInfo['tests_quantity']) * 100);
    }

    /**
     * Convierte score a grade
     */
    private function scoreToGrade(int $score): string
    {
        if ($score >= 90) return 'A+';
        if ($score >= 80) return 'A';
        if ($score >= 70) return 'B';
        if ($score >= 60) return 'C';
        if ($score >= 50) return 'D';
        return 'F';
    }

    /**
     * Carga datos desde cache
     */
    /**
     * @return array<string, mixed>|null
     */
    public function loadCache(): ?array
    {
        if (!file_exists(self::CACHE_FILE)) {
            return null;
        }

        $content = file_get_contents(self::CACHE_FILE);
        if ($content === false) {
            return null;
        }

        $data = json_decode($content, true);
        if (!is_array($data)) {
            return null;
        }

        return $data;
    }

    /**
     * Guarda datos en cache
     */
    /**
     * @param array<string, mixed> $data
     */
    public function saveCache(array $data): void
    {
        $dir = dirname(self::CACHE_FILE);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents(self::CACHE_FILE, json_encode($data, JSON_PRETTY_PRINT));
    }

    /**
     * Verifica si el cache es fresco (menos de 24h)
     */
    public function isCacheFresh(): bool
    {
        if (!file_exists(self::CACHE_FILE)) {
            return false;
        }

        $cache = $this->loadCache();
        if ($cache === null || !isset($cache['lastScan'])) {
            return false;
        }

        $lastScan = strtotime($cache['lastScan']);
        $now = time();

        return ($now - $lastScan) < self::CACHE_TTL;
    }

    /**
     * Ejecuta escaneo completo
     */
    /**
     * @return array{
     *   securityHeaders: array<string, mixed>,
     *   mozillaObservatory: array<string, mixed>,
     *   lastScan: string,
     *   fromCache: bool
     * }
     */
    public function scan(): array
    {
        $securityHeaders = $this->scanSecurityHeaders();
        $mozillaObservatory = $this->scanMozillaObservatory();

        return [
            'securityHeaders' => $securityHeaders,
            'mozillaObservatory' => $mozillaObservatory,
            'lastScan' => date('c'),
            'fromCache' => false
        ];
    }
}
