<?php

declare(strict_types=1);

namespace Creawebes\Rag\Tests\Application\Clients;

use Creawebes\Rag\Application\Clients\OpenAiHttpClient;
use Creawebes\Rag\Application\Contracts\HttpTransportInterface;
use Creawebes\Rag\Application\Contracts\StructuredLoggerInterface;
use Creawebes\Rag\Application\Resilience\CircuitBreaker;
use Creawebes\Rag\Application\Resilience\CircuitBreakerOpenException;
use Creawebes\Rag\Application\Resilience\CircuitBreakerStateStoreInterface;
use PHPUnit\Framework\TestCase;

final class OpenAiHttpClientCircuitBreakerTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $serverBackup = [];
    /** @var array<string, mixed> */
    private array $envBackup = [];
    private string $tokensLogPath = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->serverBackup = $_SERVER;
        $this->envBackup = $_ENV;

        $this->tokensLogPath = sys_get_temp_dir() . '/rag-tokens-' . bin2hex(random_bytes(8)) . '.log';
        @unlink($this->tokensLogPath);

        putenv('AI_TOKENS_LOG_PATH=' . $this->tokensLogPath);
        $_ENV['AI_TOKENS_LOG_PATH'] = $this->tokensLogPath;

        putenv('APP_DEBUG=0');
        $_ENV['APP_DEBUG'] = '0';
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->serverBackup;
        $_ENV = $this->envBackup;

        putenv('AI_TOKENS_LOG_PATH');
        putenv('APP_DEBUG');

        @unlink($this->tokensLogPath);
        parent::tearDown();
    }

    public function testShortCircuitsWhenBreakerIsOpenAndTtlNotExpired(): void
    {
        $store = new InMemoryBreakerStateStore([
            'state' => 'open',
            'failure_count' => 3,
            'opened_at' => time(),
            'half_open_calls' => 0,
        ]);
        $logger = new CapturingStructuredLogger();
        $breaker = new CircuitBreaker($store, $logger, failureThreshold: 3, openTtlSeconds: 30, halfOpenMaxCalls: 1);

        $transport = new CountingFakeTransport();
        $client = new OpenAiHttpClient(
            openAiEndpoint: 'http://fake.local/v1/chat',
            feature: 'test',
            transport: $transport,
            circuitBreaker: $breaker,
            logger: $logger,
        );

        $this->expectException(CircuitBreakerOpenException::class);
        $client->ask('hola');

        $this->assertSame(0, $transport->calls);
        $this->assertTrue($logger->hasEvent('llm.circuit.short_circuit'));
    }

    public function testTransitionsOpenToHalfOpenToClosedOnSuccess(): void
    {
        $store = new InMemoryBreakerStateStore([
            'state' => 'open',
            'failure_count' => 3,
            'opened_at' => time() - 31,
            'half_open_calls' => 0,
        ]);
        $logger = new CapturingStructuredLogger();
        $breaker = new CircuitBreaker($store, $logger, failureThreshold: 3, openTtlSeconds: 30, halfOpenMaxCalls: 1);

        $transport = new CountingFakeTransport([
            'response' => json_encode([
                'choices' => [
                    ['message' => ['content' => 'ok']],
                ],
                'usage' => [
                    'prompt_tokens' => 1,
                    'completion_tokens' => 1,
                    'total_tokens' => 2,
                ],
                'model' => 'gpt-4o-mini',
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'http_code' => 200,
            'error' => '',
        ]);

        $client = new OpenAiHttpClient(
            openAiEndpoint: 'http://fake.local/v1/chat',
            feature: 'test',
            transport: $transport,
            circuitBreaker: $breaker,
            logger: $logger,
        );

        $result = $client->ask('hola');

        $this->assertSame('ok', $result);
        $this->assertSame(1, $transport->calls);

        $state = $store->load();
        $this->assertSame('closed', $state['state']);
        $this->assertSame(0, $state['failure_count']);

        $this->assertTrue($logger->hasEvent('llm.circuit.half_open'));
        $this->assertTrue($logger->hasEvent('llm.request'));
    }
}

final class CountingFakeTransport implements HttpTransportInterface
{
    public int $calls = 0;

    /** @var array{response: string|false, http_code: int, error: string} */
    private array $result;

    /**
     * @param array{response: string|false, http_code: int, error: string} $result
     */
    public function __construct(array $result = ['response' => false, 'http_code' => 0, 'error' => 'no-transport'])
    {
        $this->result = $result;
    }

    public function post(string $url, array $headers, string $body, int $connectTimeoutSeconds, int $timeoutSeconds): array
    {
        $this->calls++;
        return $this->result;
    }
}

final class InMemoryBreakerStateStore implements CircuitBreakerStateStoreInterface
{
    /** @param array{state: string, failure_count: int, opened_at: int, half_open_calls: int} $state */
    public function __construct(private array $state)
    {
    }

    public function load(): array
    {
        return $this->state;
    }

    public function save(array $state): void
    {
        $this->state = $state;
    }
}

final class CapturingStructuredLogger implements StructuredLoggerInterface
{
    /** @var array<int, array{event: string, fields: array<string, mixed>}> */
    public array $entries = [];

    public function log(string $event, array $fields = []): void
    {
        $this->entries[] = ['event' => $event, 'fields' => $fields];
    }

    public function hasEvent(string $event): bool
    {
        foreach ($this->entries as $entry) {
            if ($entry['event'] === $event) {
                return true;
            }
        }

        return false;
    }
}

