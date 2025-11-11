<?php

declare(strict_types=1);

use Creawebes\Rag\Application\HeroRagService;
use Creawebes\Rag\Application\HeroRetriever;
use Creawebes\Rag\Controllers\RagController;
use Creawebes\Rag\Infrastructure\HeroJsonKnowledgeBase;

return (static function (): array {
    $rootPath = dirname(__DIR__);
    $envPath = $rootPath . DIRECTORY_SEPARATOR . '.env';

    if (is_file($envPath)) {
        foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            if (str_starts_with($line, '#')) {
                continue;
            }

            [$key, $value] = array_map('trim', explode('=', $line, 2) + [1 => '']);
            if ($key !== '') {
                $_ENV[$key] = $value;
                putenv($key . '=' . $value);
            }
        }
    }

    $resolveEnvironment = static function (): string {
        $candidate = $_ENV['APP_ENV'] ?? getenv('APP_ENV') ?: null;
        if (is_string($candidate)) {
            $trimmed = trim($candidate);
            if ($trimmed !== '' && strcasecmp($trimmed, 'auto') !== 0) {
                return strtolower($trimmed);
            }
        }

        $host = $_SERVER['HTTP_HOST'] ?? '';
        $host = strtolower(trim((string) $host));
        $host = $host !== '' ? explode(':', $host)[0] : '';

        $hostingHosts = [
            'iamasterbigschool.contenido.creawebes.com',
            'openai-service.contenido.creawebes.com',
            'rag-service.contenido.creawebes.com',
        ];

        if ($host !== '' && in_array($host, $hostingHosts, true)) {
            return 'hosting';
        }

        return 'local';
    };

    $environment = $resolveEnvironment();

    $knowledgeBase = new HeroJsonKnowledgeBase($rootPath . '/storage/knowledge/heroes.json');
    $retriever = new HeroRetriever($knowledgeBase);

    $openAiEndpoint = $_ENV['OPENAI_SERVICE_URL'] ?? getenv('OPENAI_SERVICE_URL') ?: null;
    if (!is_string($openAiEndpoint) || trim($openAiEndpoint) === '') {
        $openAiEndpoint = $environment === 'hosting'
            ? 'https://openai-service.contenido.creawebes.com/v1/chat'
            : 'http://localhost:8081/v1/chat';
    }

    $ragService = new HeroRagService($retriever, $openAiEndpoint);

    return [
        'ragController' => new RagController($ragService),
    ];
})();
