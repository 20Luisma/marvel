<?php

declare(strict_types=1);

namespace App\Security\Http;

use App\Security\RateLimit\RateLimitResult;
use Src\Controllers\Http\Request;

final class ApiFirewall
{
    private const MAX_BODY_BYTES = 1048576; // 1MB
    private const MAX_VALUE_LENGTH = 10000;

    /**
     * Rutas que no deben ser filtradas por el firewall (intro/login/seccion y estáticos).
     * @var array<int, string>
     */
    private array $allowlist = [
        '/intro',
        '/login',
        '/seccion',
        '/',
        '/api/rag/heroes', // Permitir endpoint RAG sin restricciones
    ];

    public function __construct(private readonly ?\App\Security\Logging\SecurityLogger $logger = null)
    {
    }

    public function handle(string $method, string $path): bool
    {
        // BEGIN ZONAR FIX 1.1 - Evitar consumir php://input para rutas en whitelist
        if ($this->shouldSkip($method, $path)) {
            return true; // Salir ANTES de leer el body
        }
        // END ZONAR FIX 1.1

        return $this->isRequestAllowed($method, $path);
    }

    private function isRequestAllowed(string $method, string $path): bool
    {
        $rawInput = $this->readRawBody();

        // Log de depuración para rutas API
        if (str_starts_with($path, '/api/')) {
            $this->logDebugInfo($method, $path, $rawInput);
        }

        if ($rawInput !== null) {
            $GLOBALS['__raw_input__'] = $rawInput; // permite reutilizar en Request::jsonBody
        }

        if (!$this->checkBodySize($rawInput)) {
            $this->deny($path, 'Payload excede tamaño máximo', 400);
            return false;
        }

        if ($rawInput === null || $rawInput === '') {
            return true;
        }

        $contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
        $isJson = str_contains($contentType, 'application/json');
        $isForm = str_contains($contentType, 'application/x-www-form-urlencoded') || str_contains($contentType, 'multipart/form-data');

        if ($isJson) {
            if (!$this->validateJsonPayload($rawInput, $path)) {
                return false;
            }
        } elseif ($isForm) {
            if (!$this->validateFormData($rawInput, $path)) {
                return false;
            }
        }

        if ($this->detectAttackPattern($rawInput)) {
            $this->deny($path, 'Patrón de ataque detectado', 400);
            return false;
        }

        return true;
    }

    private function shouldSkip(string $method, string $path): bool
    {
        if (in_array($path, $this->allowlist, true)) {
            return true;
        }

        // heurística: rutas con extensión se asumen estáticos.
        if (str_contains($path, '.')) {
            return true;
        }

        return false;
    }

    private function readRawBody(): ?string
    {
        // Producción/local: el front controller deja el body en MARVEL_RAW_BODY
        $rawFromServer = $_SERVER['MARVEL_RAW_BODY'] ?? null;
        if (is_string($rawFromServer) && $rawFromServer !== '') {
            return $rawFromServer;
        }

        // Tests/PSR-7: usamos el body real del ServerRequest si está disponible
        $rawFromGlobals = $GLOBALS['__raw_input__'] ?? null;
        if (is_string($rawFromGlobals) && $rawFromGlobals !== '') {
            return $rawFromGlobals;
        }

        $raw = \Src\Http\RequestBodyReader::getRawBody();
        return $raw === '' ? null : $raw;
    }

    private function checkBodySize(?string $raw): bool
    {
        $lengthHeader = $_SERVER['CONTENT_LENGTH'] ?? null;
        if (is_numeric($lengthHeader) && (int) $lengthHeader > self::MAX_BODY_BYTES) {
            return false;
        }

        if ($raw !== null && strlen($raw) > self::MAX_BODY_BYTES) {
            return false;
        }

        return true;
    }

    private function validateJsonPayload(string $raw, string $path): bool
    {
        $decoded = json_decode($raw, true);
        if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
            $this->deny($path, 'JSON inválido', 400);
            return false;
        }

        if (is_array($decoded)) {
            if ($this->hasDuplicateKeys($raw)) {
                $this->deny($path, 'Claves duplicadas detectadas', 400);
                return false;
            }

            if (!$this->validateValues($decoded)) {
                $this->deny($path, 'Payload inválido', 400);
                return false;
            }
        }

        return true;
    }

    private function validateFormData(string $raw, string $path): bool
    {
        // form-data llega en superglobales, usamos raw solo para patrones.
        foreach ($_POST as $value) {
            if (is_string($value) && strlen($value) > self::MAX_VALUE_LENGTH) {
                $this->deny($path, 'Campo demasiado largo', 400);
                return false;
            }
        }

        return true;
    }

    private function hasDuplicateKeys(string $raw): bool
    {
        $matches = [];
        preg_match_all('/"([^"\\\\]*(?:\\\\.[^"\\\\]*)*)"\\s*:/', $raw, $matches);
        if (!isset($matches[1]) || $matches[1] === []) {
            return false;
        }

        $keys = array_map(static function ($key): string {
            return stripslashes((string) $key);
        }, $matches[1]);

        return count($keys) !== count(array_unique($keys));
    }

    /**
     * @param array<string|int, mixed> $data
     */
    private function validateValues(array $data): bool
    {
        foreach ($data as $value) {
            if (is_string($value)) {
                if (strlen($value) > self::MAX_VALUE_LENGTH) {
                    return false;
                }
                continue;
            }

            if (is_int($value) || is_float($value) || is_bool($value)) {
                continue;
            }

            if (is_array($value)) {
                if (!$this->validateValues($value)) {
                    return false;
                }
                continue;
            }

            // null u otros tipos no permitidos.
            return false;
        }

        return true;
    }

    private function detectAttackPattern(string $raw): bool
    {
        $haystack = strtolower($raw);
        $patterns = [
            '<script',
            'drop table',
            'union select',
            '${jndi:ldap://',
            '<?php',
        ];

        foreach ($patterns as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function deny(string $path, string $message, int $statusCode): void
    {
        $this->logEvent($path, $statusCode, $message);
        http_response_code($statusCode);

        if (str_starts_with($path, '/api/') || !Request::wantsHtml()) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['estado' => 'error', 'message' => 'Payload inválido'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return;
        }

        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><html><body><h1>400</h1><p>Petición inválida.</p></body></html>';
    }

    private function logEvent(string $path, int $status, string $reason): void
    {
        if ($this->logger) {
            $this->logger->logEvent('payload_suspicious', [
                'trace_id' => $_SERVER['X_TRACE_ID'] ?? null,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'path' => $path,
                'status' => $status,
                'reason' => $reason,
            ]);
            return;
        }

        $logFile = dirname(__DIR__, 3) . '/storage/logs/security.log';
        $directory = dirname($logFile);
        if (!is_dir($directory)) {
            @mkdir($directory, 0775, true);
        }

        $traceId = $_SERVER['X_TRACE_ID'] ?? '-';
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $line = sprintf(
            '[%s] event=payload_suspicious trace_id=%s ip=%s path=%s status=%d reason=%s',
            date('Y-m-d H:i:s'),
            is_string($traceId) ? $traceId : '-',
            is_string($ip) ? $ip : 'unknown',
            $path,
            $status,
            $reason
        );

        @file_put_contents($logFile, $line . PHP_EOL, FILE_APPEND);
    }

    private function logDebugInfo(string $method, string $path, ?string $rawInput): void
    {
        $logFile = dirname(__DIR__, 3) . '/storage/logs/debug_rag_proxy.log';
        $contentType = $_SERVER['CONTENT_TYPE'] ?? 'N/A';
        $contentLength = $_SERVER['CONTENT_LENGTH'] ?? 'N/A';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'N/A';
        $bodyLength = $rawInput !== null ? strlen($rawInput) : 0;

        $logEntry = sprintf(
            "%s [FIREWALL_DEBUG] %s %s | Content-Type: %s | Content-Length: %s | Body Length: %d | UA: %s\n",
            date('c'),
            $method,
            $path,
            $contentType,
            $contentLength,
            $bodyLength,
            substr($userAgent, 0, 100)
        );

        @file_put_contents($logFile, $logEntry, FILE_APPEND);
    }
}
