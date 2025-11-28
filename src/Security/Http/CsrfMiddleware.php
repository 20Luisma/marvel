<?php

declare(strict_types=1);

namespace App\Security\Http;

use App\Security\Csrf\CsrfService;
use App\Security\Logging\SecurityLogger;

final class CsrfMiddleware
{
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

    public function handle(string $path): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            return;
        }

        if (!in_array($path, $this->protectedPostRoutes, true)) {
            return;
        }

        $token = $_POST['csrf_token']
            ?? ($_POST['_token'] ?? null)
            ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);

        if (!CsrfService::validateToken(is_string($token) ? $token : null)) {
            $this->logFailure($path, $token);

            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Invalid CSRF token'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }
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
