<?php

declare(strict_types=1);

namespace App\Security\Http;

use App\Security\Csrf\CsrfService;
use App\Security\Logging\SecurityLogger;

class CsrfMiddleware
{
    /** @var array<int, string> */
    private array $protectedPostRoutes = [
        '/login',
        '/logout',
        '/agentia',
        '/panel-heatmap',
        '/panel-github',
        '/panel-repo-marvel',
        '/panel-accesibility',
        '/panel-performance',
        '/panel-sonar',
        '/panel-marvel',
        '/api/rag/heroes',
    ];

    public function __construct(private readonly ?SecurityLogger $logger = null)
    {
    }

    public function handle(string $path): bool
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? '';
        
        // CSRF solo aplica a métodos mutantes
        if (!in_array($method, ['POST', 'PUT', 'DELETE'], true)) {
            return true;
        }

        // Saltarse CSRF para peticiones API legítimas (App Móvil o Inter-servicio)
        if (!empty($_SERVER['HTTP_X_MOBILE_KEY']) || !empty($_SERVER['HTTP_X_INTERNAL_SIGNER'])) {
            return true;
        }

        // Si no está en la lista de protegidas, no aplicamos CSRF
        if (!in_array($path, $this->protectedPostRoutes, true)) {
            return true;
        }

        $token = $_POST['csrf_token']

            ?? ($_POST['_token'] ?? null)
            ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);

        if (!CsrfService::validateToken(is_string($token) ? $token : null)) {
            $this->logFailure($path, $token);
            http_response_code(403);

            \App\Shared\Http\JsonResponse::error(
                'Token CSRF inválido o ausente. Por favor, recarga la página para restaurar la sesión de seguridad.',
                403
            );
            $this->terminate();
            return false;
        }


        return true;
    }



    protected function terminate(): void
    {
        http_response_code(403);
        if (defined('PHPUNIT_RUNNING')) {
            throw new \RuntimeException('CSRF terminated');
        }
        exit;  // @codeCoverageIgnore
    }

    private function logFailure(string $path, mixed $token): void
    {
        $tokenState = is_string($token) && trim((string) $token) !== '' ? 'present' : 'missing';
        if ($this->logger instanceof SecurityLogger) {
            $this->logger->logEvent('csrf_failed', [
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'path' => $path,
                'token_state' => $tokenState,
            ]);
        } else {
            error_log(sprintf(
                "event=csrf_failed ip=%s path=%s token_state=%s",
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                $path,
                $tokenState
            ));
        }
    }
}
