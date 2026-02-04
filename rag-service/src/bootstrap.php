<?php

declare(strict_types=1);

use Creawebes\Rag\Application\HeroRagService;
use Creawebes\Rag\Application\HeroRetriever;
use Creawebes\Rag\Application\Clients\OpenAiHttpClient;
use Creawebes\Rag\Application\Resilience\CircuitBreaker;
use Creawebes\Rag\Application\Similarity\CosineSimilarity;
use Creawebes\Rag\Controllers\RagController;
use Creawebes\Rag\Application\UseCase\AskMarvelAgentUseCase;
use Creawebes\Rag\Application\Rag\MarvelAgentRetriever;
use Creawebes\Rag\Application\Rag\MarvelAgentRetrieverInterface;
use Creawebes\Rag\Application\HeroKnowledgeSyncService;
use Creawebes\Rag\Infrastructure\Knowledge\MarvelAgentKnowledgeBase;
use Creawebes\Rag\Infrastructure\EmbeddingStore;
use Creawebes\Rag\Infrastructure\HeroJsonKnowledgeBase;
use Creawebes\Rag\Infrastructure\Observability\JsonFileRagTelemetry;
use Creawebes\Rag\Infrastructure\Observability\JsonFileStructuredLogger;
use Creawebes\Rag\Infrastructure\Observability\ServerTraceIdProvider;
use Creawebes\Rag\Infrastructure\Resilience\JsonFileCircuitBreakerStateStore;
use Creawebes\Rag\Infrastructure\VectorHeroRetriever;
use Creawebes\Rag\Application\Clients\OpenAiEmbeddingClient;
use Creawebes\Rag\Infrastructure\Retrieval\VectorMarvelAgentRetriever;

if (!defined('APP_START_TIME')) {
    define('APP_START_TIME', time());
}

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

        if ($host !== '' && (str_contains($host, '.creawebes.com'))) {
            return 'hosting';
        }

        // Si estamos en CI (GitHub Actions) o local
        return 'local';
    };

    $environment = $resolveEnvironment();

    // Log de diagnóstico para el microservicio OpenAI
    $logOpenAiError = function(string $message) use ($rootPath) {
        $logFile = $rootPath . '/storage/logs/openai_error.log';
        @mkdir(dirname($logFile), 0775, true);
        @file_put_contents($logFile, date('c') . " - " . $message . PHP_EOL, FILE_APPEND);
    };

    $knowledgeBase = new HeroJsonKnowledgeBase($rootPath . '/storage/knowledge/heroes.json');
    $similarity = new CosineSimilarity();
    $traceIdProvider = new ServerTraceIdProvider();
    $ragLogFile = $_ENV['RAG_LOG_FILE'] ?? getenv('RAG_LOG_FILE') ?: ($rootPath . '/storage/logs/rag.log');
    $telemetry = new JsonFileRagTelemetry($traceIdProvider, (string) $ragLogFile);
    $structuredLogger = new JsonFileStructuredLogger($traceIdProvider, (string) $ragLogFile);

    $cbStateFile = $_ENV['CB_STATE_FILE'] ?? getenv('CB_STATE_FILE') ?: ($rootPath . '/storage/ai/circuit_breaker.json');
    $cbFailureThreshold = (int) ($_ENV['CB_FAILURE_THRESHOLD'] ?? getenv('CB_FAILURE_THRESHOLD') ?: 3);
    $cbOpenTtlSeconds = (int) ($_ENV['CB_OPEN_TTL_SECONDS'] ?? getenv('CB_OPEN_TTL_SECONDS') ?: 30);
    $cbHalfOpenMaxCalls = (int) ($_ENV['CB_HALF_OPEN_MAX_CALLS'] ?? getenv('CB_HALF_OPEN_MAX_CALLS') ?: 1);
    $cbFailureThreshold = max(1, $cbFailureThreshold);
    $cbOpenTtlSeconds = max(1, $cbOpenTtlSeconds);
    $cbHalfOpenMaxCalls = max(1, $cbHalfOpenMaxCalls);

    $circuitBreaker = new CircuitBreaker(
        new JsonFileCircuitBreakerStateStore((string) $cbStateFile),
        $structuredLogger,
        failureThreshold: $cbFailureThreshold,
        openTtlSeconds: $cbOpenTtlSeconds,
        halfOpenMaxCalls: $cbHalfOpenMaxCalls,
    );

    $lexicalRetriever = new HeroRetriever($knowledgeBase, $similarity, $telemetry);

    $embeddingStore = new EmbeddingStore($rootPath . '/storage/embeddings/heroes.json');
    $useEmbeddings = filter_var($_ENV['RAG_USE_EMBEDDINGS'] ?? getenv('RAG_USE_EMBEDDINGS'), FILTER_VALIDATE_BOOL) === true;
    $autoRefresh = filter_var($_ENV['RAG_EMBEDDINGS_AUTOREFRESH'] ?? getenv('RAG_EMBEDDINGS_AUTOREFRESH'), FILTER_VALIDATE_BOOL) === true;

    $retriever = $useEmbeddings
        ? new VectorHeroRetriever(
            $knowledgeBase,
            $embeddingStore,
            new OpenAiEmbeddingClient(),
            $lexicalRetriever,
            $similarity,
            useEmbeddings: $useEmbeddings,
            autoRefreshEmbeddings: $autoRefresh,
            telemetry: $telemetry,
        )
        : $lexicalRetriever;

    $openAiEndpoint = $_ENV['OPENAI_SERVICE_URL'] ?? getenv('OPENAI_SERVICE_URL') ?: null;
    if (!is_string($openAiEndpoint) || trim($openAiEndpoint) === '') {
        if ($environment === 'hosting') {
            $host = $_SERVER['HTTP_HOST'] ?? '';
            if (str_contains($host, 'staging')) {
                $openAiEndpoint = 'https://openai-staging.contenido.creawebes.com/v1/chat';
            } else {
                $openAiEndpoint = 'https://openai-service.contenido.creawebes.com/v1/chat';
            }
        } else {
            $openAiEndpoint = 'http://localhost:8081/v1/chat';
        }
    }

    $llmClientForCompare = new OpenAiHttpClient(
        openAiEndpoint: $openAiEndpoint,
        feature: 'compare_heroes',
        circuitBreaker: $circuitBreaker,
        logger: $structuredLogger,
    );
    $llmClientForAgent = new OpenAiHttpClient(
        openAiEndpoint: $openAiEndpoint,
        feature: 'marvel_agent',
        circuitBreaker: $circuitBreaker,
        logger: $structuredLogger,
    );
    
    $ragService = new HeroRagService($knowledgeBase, $retriever, $llmClientForCompare);
    $agentKb = new MarvelAgentKnowledgeBase($rootPath . '/storage/marvel_agent_kb.json');
    $agentLexicalRetriever = new MarvelAgentRetriever($agentKb, $telemetry);
    $agentEmbeddingStore = new EmbeddingStore($rootPath . '/storage/marvel_agent_embeddings.json');
    $agentUseEmbeddings = filter_var($_ENV['AGENT_USE_EMBEDDINGS'] ?? getenv('AGENT_USE_EMBEDDINGS'), FILTER_VALIDATE_BOOL) === true;
    $agentAutoRefresh = filter_var($_ENV['AGENT_EMBEDDINGS_AUTOREFRESH'] ?? getenv('AGENT_EMBEDDINGS_AUTOREFRESH'), FILTER_VALIDATE_BOOL) === true;

    $pineconeMode = filter_var($_ENV['PINECONE_MODE'] ?? getenv('PINECONE_MODE'), FILTER_VALIDATE_BOOL) === true;

    if ($pineconeMode) {
        $agentRetriever = new \Creawebes\Rag\Infrastructure\Retrieval\PineconeMarvelAgentRetriever(
            new OpenAiEmbeddingClient(),
            $agentLexicalRetriever,
            telemetry: $telemetry
        );
    } else {
        $agentRetriever = $agentUseEmbeddings
            ? new VectorMarvelAgentRetriever(
                $agentKb,
                $agentEmbeddingStore,
                new OpenAiEmbeddingClient(),
                $agentLexicalRetriever,
                $similarity,
                useEmbeddings: $agentUseEmbeddings,
                autoRefreshEmbeddings: $agentAutoRefresh,
                telemetry: $telemetry,
            )
            : $agentLexicalRetriever;
    }


    $agentUseCase = new AskMarvelAgentUseCase($agentRetriever, $llmClientForAgent);

    $ragUpsertLog = $rootPath . '/storage/logs/rag_upsert.log';
    $knowledgeSync = new HeroKnowledgeSyncService(
        $knowledgeBase,
        $embeddingStore,
        $useEmbeddings ? new OpenAiEmbeddingClient() : null,
        $useEmbeddings,
        $ragUpsertLog
    );

    return [
        'ragController' => new RagController($ragService, $knowledgeSync),
        'askMarvelAgentUseCase' => $agentUseCase,
    ];
})();
