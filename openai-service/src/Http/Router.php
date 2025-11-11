<?php

declare(strict_types=1);

namespace Creawebes\OpenAI\Http;

use Creawebes\OpenAI\Controller\OpenAIController;
use Creawebes\OpenAI\Service\OpenAIChatService;

class Router
{
    public function handle(string $method, string $uri): void
    {
        $path = parse_url($uri, PHP_URL_PATH) ?? '/';
        if (!$this->applyCors()) {
            $this->denyCorsRequest(strtoupper($method));
            return;
        }

        if (strtoupper($method) === 'OPTIONS') {
            http_response_code(204);
            return;
        }

        if (strtoupper($method) === 'POST' && $path === '/v1/chat') {
            $controller = new OpenAIController(new OpenAIChatService());
            $controller->chat();
            return;
        }

        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Not Found']);
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

        header('Access-Control-Allow-Methods: POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
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

        return [
            'http://localhost:8080',
            'https://iamasterbigschool.contenido.creawebes.com',
            'https://openai-service.contenido.creawebes.com',
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
}
