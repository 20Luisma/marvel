<?php

declare(strict_types=1);

namespace Creawebes\OpenAI\Http;

use Creawebes\OpenAI\Controller\OpenAIController;
use Creawebes\OpenAI\Application\UseCase\GenerateContent;
use Creawebes\OpenAI\Infrastructure\Client\OpenAiClient;

class Router
{
    private ?string $lastAuthError = null;

    public function handle(string $method, string $uri): void
    {
        $start = microtime(true);
        $normalizedMethod = strtoupper($method);
        
        // Detectar y eliminar el prefijo de la carpeta si existe (Base Path)
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $basePath = dirname($scriptName);
        if ($basePath === '/') $basePath = '';
        
        $path = parse_url($uri, PHP_URL_PATH) ?? '/';
        
        // Si el path empieza por el basePath (como en el hosting), lo quitamos
        if ($basePath !== '' && str_starts_with($path, $basePath)) {
            $path = substr($path, strlen($basePath));
        }
        
        // Si después de quitar el basePath seguimos en la carpeta public, la quitamos también
        if (str_starts_with($path, '/public')) {
            $path = substr($path, 7);
        }
        
        if ($path === '') $path = '/';

        PrometheusMetrics::incrementRequests();

        if (!$this->applyCors()) {
            $this->denyCorsRequest($normalizedMethod);
            $this->logRequest($start, $path, http_response_code() ?: 403, 'origin-not-allowed');
            return;
        }

        if ($normalizedMethod === 'OPTIONS') {
            http_response_code(204);
            $this->logRequest($start, $path, 204);
            return;
        }

        if ($normalizedMethod === 'GET' && $path === '/health') {
            http_response_code(200);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(
                [
                    'status' => 'ok',
                    'service' => 'openai-service',
                    'time' => date('c'),
                ],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );
            $this->logRequest($start, $path, 200);
            return;
        }

        if ($normalizedMethod === 'GET' && $path === '/metrics') {
            PrometheusMetrics::respond('openai-service');
            $this->logRequest($start, $path, 200);
            return;
        }

        if ($path === '/metrics') {
            http_response_code(405);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Method not allowed'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $this->logRequest($start, $path, 405, 'method-not-allowed');
            return;
        }

        if ($normalizedMethod === 'POST' && $path === '/v1/chat') {
            if (!$this->authorizeInternalSignature($normalizedMethod, $path)) {
                http_response_code(401);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['error' => 'Unauthorized request'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $this->logRequest($start, $path, 401, $this->lastAuthError ?? 'signature');
                return;
            }

            $controller = new OpenAIController(new GenerateContent(new OpenAiClient()));
            $controller->chat();
            $status = http_response_code() ?: 200;
            if ($status >= 500) {
                PrometheusMetrics::incrementErrors();
            }
            $this->logRequest($start, $path, $status);
            return;
        }

        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Not Found']);
        $this->logRequest($start, $path, 404, 'not-found');
    }

    private function applyCors(): bool
    {
        $origins = $this->allowedOrigins();
        header('Vary: Origin');

        $requestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
        $origin = is_string($requestOrigin) ? trim($requestOrigin) : '';

        if ($origin !== '' && !in_array($origin, $origins, true)) {
            return false;
        }

        $selectedOrigin = $origin !== '' ? $origin : ($origins[0] ?? '');

        if ($selectedOrigin !== '') {
            header('Access-Control-Allow-Origin: ' . $selectedOrigin);
        }

        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Internal-Signature, X-Internal-Timestamp, X-Internal-Caller');
        header('Access-Control-Max-Age: 86400');

        return true;
    }

    /**
     * @return array<int, string>
     */
    private function allowedOrigins(): array
    {
        $allowed = $_ENV['ALLOWED_ORIGINS'] ?? getenv('ALLOWED_ORIGINS') ?: null;

        if (is_string($allowed) && trim($allowed) !== '') {
            $origins = array_filter(array_map('trim', explode(',', $allowed)));
            if ($origins !== []) {
                return array_values($origins);
            }
        }

        // Fallback dinámico: Permitir el dominio actual y localhost
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
        
        return [
            'http://localhost:8080',
            $protocol . '://' . $host,
        ];
    }

    private function denyCorsRequest(string $method): void
    {
        if ($method === 'OPTIONS') {
            http_response_code(403);
            return;
        }

        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Origin not allowed'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function authorizeInternalSignature(string $method, string $path): bool
    {
        $bypass = $_ENV['STAGING_INSECURE_BYPASS'] ?? getenv('STAGING_INSECURE_BYPASS') ?? 'false';
        if ($bypass === 'true') {
            return true;
        }

        $this->lastAuthError = null;
        $sharedKey = $_ENV['INTERNAL_API_KEY'] ?? getenv('INTERNAL_API_KEY') ?: '';
        $normalizedKey = is_string($sharedKey) ? trim($sharedKey) : '';
        if ($normalizedKey === '') {
            // HMAC Strict Mode (fail-closed): si está activado y no hay clave, rechazar
            $strictMode = $_ENV['HMAC_STRICT_MODE'] ?? getenv('HMAC_STRICT_MODE') ?: 'false';
            if ($strictMode === 'true') {
                $this->lastAuthError = 'hmac-strict-no-key';
                return false;
            }
            return true;
        }

        $signature = $_SERVER['HTTP_X_INTERNAL_SIGNATURE'] ?? '';
        $timestampHeader = $_SERVER['HTTP_X_INTERNAL_TIMESTAMP'] ?? '';
        $caller = $_SERVER['HTTP_X_INTERNAL_CALLER'] ?? '';
        $timestamp = is_numeric($timestampHeader) ? (int) $timestampHeader : 0;

        if (!is_string($signature) || trim($signature) === '' || $timestamp <= 0) {
            $this->lastAuthError = 'missing-signature';
            return false;
        }

        $canonical = strtoupper($method) . "\n" . $path . "\n" . $timestamp . "\n" . hash('sha256', $this->rawInput());
        $expected = hash_hmac('sha256', $canonical, $normalizedKey);

        if (!hash_equals($expected, trim((string) $signature))) {
            $this->lastAuthError = 'signature-mismatch';
            return false;
        }

        if (abs(time() - $timestamp) > 300) {
            $this->lastAuthError = 'timestamp-out-of-range';
            return false;
        }

        $allowedCallers = $this->allowedCallers();
        $normalizedCaller = $this->normalizeHost((string) $caller);
        if ($allowedCallers !== [] && $normalizedCaller !== '' && !in_array($normalizedCaller, $allowedCallers, true)) {
            $this->lastAuthError = 'caller-not-allowed';
            return false;
        }

        return true;
    }

    /**
     * @return array<int, string>
     */
    private function allowedCallers(): array
    {
        $configured = $_ENV['ALLOWED_INTERNAL_CALLERS'] ?? getenv('ALLOWED_INTERNAL_CALLERS') ?: null;
        if (is_string($configured) && trim($configured) !== '') {
            $entries = array_filter(array_map([$this, 'normalizeHost'], explode(',', $configured)));
            if ($entries !== []) {
                return array_values($entries);
            }
        }

        $entries = array_filter(array_map(function (string $origin): string {
            $host = parse_url($origin, PHP_URL_HOST);
            return $this->normalizeHost($host ?: $origin);
        }, $this->allowedOrigins()));

        $entries[] = $this->normalizeHost('rag-service.contenido.creawebes.com');
        $entries[] = $this->normalizeHost('localhost:8082');

        return array_values(array_unique(array_filter($entries)));
    }

    private function normalizeHost(?string $host): string
    {
        $value = strtolower(trim((string) $host));
        if ($value === '') {
            return '';
        }

        if (str_contains($value, '://')) {
            $parsed = parse_url($value, PHP_URL_HOST);
            if (is_string($parsed) && $parsed !== '') {
                $value = $parsed;
            }
        }

        // Strip path if any
        $value = explode('/', $value)[0];

        // Strip port if any (e.g. localhost:8082 -> localhost)
        return explode(':', $value)[0];
    }

    private function rawInput(): string
    {
        $raw = $_SERVER['__RAW_INPUT__'] ?? null;
        if (is_string($raw)) {
            return $raw;
        }

        $content = file_get_contents('php://input');
        return $content === false ? '' : (string) $content;
    }

    private function logRequest(float $start, string $path, int $statusCode, ?string $error = null): void
    {
        $logFile = __DIR__ . '/../storage/logs/requests.log';
        $directory = dirname($logFile);
        if (!is_dir($directory)) {
            @mkdir($directory, 0775, true);
        }

        $entry = [
            'timestamp' => date('c'),
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
            'path' => $path,
            'status' => $statusCode,
            'duration_ms' => (int) round((microtime(true) - $start) * 1000),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            'caller' => $_SERVER['HTTP_X_INTERNAL_CALLER'] ?? null,
        ];

        if ($error !== null) {
            $entry['error'] = $error;
        }

        $encoded = json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($encoded !== false) {
            @file_put_contents($logFile, $encoded . PHP_EOL, FILE_APPEND);
        }
    }
}
