<?php

declare(strict_types=1);

use Creawebes\Rag\Application\HeroRagService;
use Creawebes\Rag\Application\HeroRetriever;
use Creawebes\Rag\Application\Clients\OpenAiHttpClient;
use Creawebes\Rag\Controllers\RagController;
use Creawebes\Rag\Application\UseCase\AskMarvelAgentUseCase;
use Creawebes\Rag\Application\Rag\MarvelAgentRetriever;
use Creawebes\Rag\Application\Rag\MarvelAgentRetrieverInterface;
use Creawebes\Rag\Infrastructure\Knowledge\MarvelAgentKnowledgeBase;
use Creawebes\Rag\Infrastructure\EmbeddingStore;
use Creawebes\Rag\Infrastructure\HeroJsonKnowledgeBase;
use Creawebes\Rag\Infrastructure\VectorHeroRetriever;
use Creawebes\Rag\Application\Clients\OpenAiEmbeddingClient;
use Creawebes\Rag\Infrastructure\Retrieval\VectorMarvelAgentRetriever;

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
    $lexicalRetriever = new HeroRetriever($knowledgeBase);

    $embeddingStore = new EmbeddingStore($rootPath . '/storage/embeddings/heroes.json');
    $useEmbeddings = filter_var($_ENV['RAG_USE_EMBEDDINGS'] ?? getenv('RAG_USE_EMBEDDINGS'), FILTER_VALIDATE_BOOL) === true;
    $autoRefresh = filter_var($_ENV['RAG_EMBEDDINGS_AUTOREFRESH'] ?? getenv('RAG_EMBEDDINGS_AUTOREFRESH'), FILTER_VALIDATE_BOOL) === true;

    $retriever = $useEmbeddings
        ? new VectorHeroRetriever(
            $knowledgeBase,
            $embeddingStore,
            new OpenAiEmbeddingClient(),
            $lexicalRetriever,
            useEmbeddings: $useEmbeddings,
            autoRefreshEmbeddings: $autoRefresh,
        )
        : $lexicalRetriever;

    $openAiEndpoint = $_ENV['OPENAI_SERVICE_URL'] ?? getenv('OPENAI_SERVICE_URL') ?: null;
    if (!is_string($openAiEndpoint) || trim($openAiEndpoint) === '') {
        $openAiEndpoint = $environment === 'hosting'
            ? 'https://openai-service.contenido.creawebes.com/v1/chat'
            : 'http://localhost:8081/v1/chat';
    }

    $llmClientForCompare = new OpenAiHttpClient($openAiEndpoint, 'compare_heroes');
    $llmClientForAgent = new OpenAiHttpClient($openAiEndpoint, 'marvel_agent');
    
    $ragService = new HeroRagService($knowledgeBase, $retriever, $llmClientForCompare);
    $agentKb = new MarvelAgentKnowledgeBase($rootPath . '/storage/marvel_agent_kb.json');
    $agentLexicalRetriever = new MarvelAgentRetriever($agentKb);
    $agentEmbeddingStore = new EmbeddingStore($rootPath . '/storage/marvel_agent_embeddings.json');
    $agentUseEmbeddings = filter_var($_ENV['AGENT_USE_EMBEDDINGS'] ?? getenv('AGENT_USE_EMBEDDINGS'), FILTER_VALIDATE_BOOL) === true;
    $agentAutoRefresh = filter_var($_ENV['AGENT_EMBEDDINGS_AUTOREFRESH'] ?? getenv('AGENT_EMBEDDINGS_AUTOREFRESH'), FILTER_VALIDATE_BOOL) === true;

    $agentRetriever = $agentUseEmbeddings
        ? new VectorMarvelAgentRetriever(
            $agentKb,
            $agentEmbeddingStore,
            new OpenAiEmbeddingClient(),
            $agentLexicalRetriever,
            useEmbeddings: $agentUseEmbeddings,
            autoRefreshEmbeddings: $agentAutoRefresh,
        )
        : $agentLexicalRetriever;

    $agentUseCase = new AskMarvelAgentUseCase($agentRetriever, $llmClientForAgent);

    return [
        'ragController' => new RagController($ragService),
        'askMarvelAgentUseCase' => $agentUseCase,
    ];
})();
