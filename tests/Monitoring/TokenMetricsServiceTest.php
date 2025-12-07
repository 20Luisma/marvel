<?php

declare(strict_types=1);

namespace Tests\Monitoring;

use App\Monitoring\TokenMetricsService;
use PHPUnit\Framework\TestCase;

final class TokenMetricsServiceTest extends TestCase
{
    private string $logPath;
    private ?string $originalContents = null;

    protected function setUp(): void
    {
        $this->logPath = __DIR__ . '/../../storage/ai/tokens.log';
        if (is_file($this->logPath)) {
            $this->originalContents = file_get_contents($this->logPath) ?: '';
        }
        if (!is_dir(dirname($this->logPath))) {
            mkdir(dirname($this->logPath), 0775, true);
        }
        // Desactivar cálculos de coste para simplificar asserts.
        putenv('OPENAI_PRICE_PROMPT_PER_1K=0');
        putenv('OPENAI_PRICE_COMPLETION_PER_1K=0');
    }

    protected function tearDown(): void
    {
        if ($this->originalContents !== null) {
            file_put_contents($this->logPath, $this->originalContents);
        } elseif (is_file($this->logPath)) {
            @unlink($this->logPath);
        }
    }

    public function testReturnsEmptyMetricsWhenNoAllowedEntries(): void
    {
        @unlink($this->logPath);
        file_put_contents($this->logPath, json_encode(['feature' => 'other']) . PHP_EOL);

        $metrics = (new TokenMetricsService())->getMetrics();

        self::assertTrue($metrics['ok']);
        self::assertSame(0, $metrics['global']['total_calls']);
        self::assertSame([], $metrics['by_model']);
        self::assertSame([], $metrics['by_feature']);
    }

    public function testAggregatesMetricsByModelFeatureAndRecentCalls(): void
    {
        $today = date('Y-m-d') . 'T10:00:00Z';
        $yesterday = date('Y-m-d', strtotime('-1 day')) . 'T08:00:00Z';

        $entries = [
            [
                'ts' => $today,
                'feature' => 'comic_generator',
                'model' => 'gpt-4o',
                'total_tokens' => 100,
                'prompt_tokens' => 60,
                'completion_tokens' => 40,
                'latency_ms' => 200,
                'success' => true,
            ],
            [
                'ts' => $yesterday,
                'feature' => 'compare_heroes',
                'model' => 'gpt-4o-mini',
                'total_tokens' => 50,
                'prompt_tokens' => 30,
                'completion_tokens' => 20,
                'latency_ms' => 100,
                'success' => false,
                'error' => 'timeout',
            ],
        ];

        $lines = array_map(static fn (array $e): string => json_encode($e, JSON_UNESCAPED_UNICODE), $entries);
        file_put_contents($this->logPath, implode(PHP_EOL, $lines) . PHP_EOL);

        $metrics = (new TokenMetricsService())->getMetrics();

        self::assertTrue($metrics['ok']);
        self::assertSame(2, $metrics['global']['total_calls']);
        self::assertSame(150, $metrics['global']['total_tokens']);
        self::assertSame(90, $metrics['global']['total_prompt_tokens']);
        self::assertSame(60, $metrics['global']['total_completion_tokens']);
        self::assertSame(100, $metrics['global']['tokens_today']);
        self::assertSame(150, $metrics['global']['tokens_last_7_days']);
        self::assertSame(1, $metrics['global']['failed_calls']);

        $byModel = $this->indexBy($metrics['by_model'] ?? [], 'model');
        self::assertSame(1, $byModel['gpt-4o']['calls']);
        self::assertSame(100, $byModel['gpt-4o']['total_tokens']);
        self::assertSame(200.0, $byModel['gpt-4o']['avg_latency_ms']);

        $byFeature = $this->indexBy($metrics['by_feature'] ?? [], 'feature');
        self::assertSame(1, $byFeature['comic_generator']['calls']);
        self::assertSame(100, $byFeature['comic_generator']['total_tokens']);
        self::assertSame(50, $byFeature['compare_heroes']['total_tokens']);

        $recent = $metrics['recent_calls'] ?? [];
        self::assertCount(2, $recent);
        self::assertSame('comic_generator', $recent[0]['feature']);
        self::assertSame('compare_heroes', $recent[1]['feature']);
    }

    public function testAddsCostEstimatesWhenPricesConfigured(): void
    {
        putenv('OPENAI_PRICE_PROMPT_PER_1K=0.001');
        putenv('OPENAI_PRICE_COMPLETION_PER_1K=0.002');
        putenv('EUR_USD_RATE=1.0');

        $entries = [
            ['ts' => date('c'), 'feature' => 'marvel_agent', 'model' => 'gpt-4o', 'prompt_tokens' => 1000, 'completion_tokens' => 500, 'total_tokens' => 1500, 'latency_ms' => 50],
        ];
        $lines = array_map(static fn (array $e): string => json_encode($e, JSON_UNESCAPED_UNICODE), $entries);
        file_put_contents($this->logPath, implode(PHP_EOL, $lines) . PHP_EOL);

        $metrics = (new TokenMetricsService())->getMetrics();

        $estimated = $metrics['global']['estimated_cost_total'] ?? 0;
        self::assertGreaterThan(0, $estimated);
        self::assertEqualsWithDelta(0.001 * 1 + 0.002 * 0.5, $estimated, 0.0001);
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<string, array<string, mixed>>
     */
    private function indexBy(array $rows, string $key): array
    {
        $indexed = [];
        foreach ($rows as $row) {
            $indexed[$row[$key]] = $row;
        }
        return $indexed;
    }
}
