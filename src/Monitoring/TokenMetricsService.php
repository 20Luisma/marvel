<?php

declare(strict_types=1);

namespace App\Monitoring;

/**
 * Agrega métricas de tokens para el panel /secret-ai-metrics.
 *
 * Lee un único archivo de log central: storage/ai/tokens.log
 * (RAG también escribe allí vía AI_TOKENS_LOG_PATH).
 *
 * Solo se contabilizan las features permitidas en ALLOWED_FEATURES y no se
 * toca el formato JSON esperado por el dashboard.
 */
class TokenMetricsService
{
    private const LOG_FILE = __DIR__ . '/../../storage/ai/tokens.log';
    /** @var string[] */
    private const ALLOWED_FEATURES = ['comic_generator', 'compare_heroes', 'marvel_agent'];

    /**
     * @return array{
     *   ok: bool,
     *   global: array<string, mixed>,
     *   by_model: array<int, array<string, mixed>>,
     *   by_feature: array<int, array<string, mixed>>,
     *   recent_calls: array<int, array<string, mixed>>
     * }
     */
    public function getMetrics(): array
    {
        $entries = $this->loadEventsFromFile(self::LOG_FILE);

        // Filtrar solo las features soportadas por el dashboard
        $entries = array_values(array_filter(
            $entries,
            static fn (array $entry): bool => in_array($entry['feature'] ?? null, self::ALLOWED_FEATURES, true)
        ));

        if (empty($entries)) {
            return $this->emptyMetrics();
        }

        return [
            'ok' => true,
            'global' => $this->calculateGlobal($entries),
            'by_model' => $this->groupByModel($entries),
            'by_feature' => $this->groupByFeature($entries),
            'recent_calls' => $this->getRecentCalls($entries, 10),
            'debug' => $this->getDebugInfo(),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $entries
     * @return array<string, mixed>
     */
    private function calculateGlobal(array $entries): array
    {
        $totalCalls = count($entries);
        $totalTokens = 0;
        $totalPromptTokens = 0;
        $totalCompletionTokens = 0;
        $totalLatency = 0;
        $failedCalls = 0;
        $tokensToday = 0;
        $tokensLast7Days = 0;

        $today = date('Y-m-d');
        $sevenDaysAgo = date('Y-m-d', strtotime('-7 days'));

        foreach ($entries as $entry) {
            $totalTokens += (int)($entry['total_tokens'] ?? 0);
            $totalPromptTokens += (int)($entry['prompt_tokens'] ?? 0);
            $totalCompletionTokens += (int)($entry['completion_tokens'] ?? 0);
            $totalLatency += (int)($entry['latency_ms'] ?? 0);

            if (!($entry['success'] ?? true)) {
                $failedCalls++;
            }

            $entryDate = substr($entry['ts'] ?? '', 0, 10);
            if ($entryDate === $today) {
                $tokensToday += (int)($entry['total_tokens'] ?? 0);
            }
            if ($entryDate >= $sevenDaysAgo) {
                $tokensLast7Days += (int)($entry['total_tokens'] ?? 0);
            }
        }

        $avgTokens = $totalCalls > 0 ? round($totalTokens / $totalCalls, 2) : 0;
        $avgLatency = $totalCalls > 0 ? round($totalLatency / $totalCalls, 2) : 0;

        $result = [
            'total_calls' => $totalCalls,
            'total_tokens' => $totalTokens,
            'total_prompt_tokens' => $totalPromptTokens,
            'total_completion_tokens' => $totalCompletionTokens,
            'tokens_today' => $tokensToday,
            'tokens_last_7_days' => $tokensLast7Days,
            'avg_tokens_per_call' => $avgTokens,
            'avg_latency_ms' => $avgLatency,
            'failed_calls' => $failedCalls,
        ];

        // Add cost estimates if prices are configured
        // Default to GPT-4o-mini prices if not set: $0.15/1M input, $0.60/1M output
        $promptPrice = (float)(getenv('OPENAI_PRICE_PROMPT_PER_1K') ?: 0.00015);
        $completionPrice = (float)(getenv('OPENAI_PRICE_COMPLETION_PER_1K') ?: 0.00060);

        if ($promptPrice > 0 || $completionPrice > 0) {
            $result['estimated_cost_total'] = $this->calculateCost($totalPromptTokens, $totalCompletionTokens, $promptPrice, $completionPrice);
            
            // Calculate today's cost
            $todayPrompt = 0;
            $todayCompletion = 0;
            foreach ($entries as $entry) {
                $entryDate = substr($entry['ts'] ?? '', 0, 10);
                if ($entryDate === $today) {
                    $todayPrompt += (int)($entry['prompt_tokens'] ?? 0);
                    $todayCompletion += (int)($entry['completion_tokens'] ?? 0);
                }
            }
            $result['estimated_cost_today'] = $this->calculateCost($todayPrompt, $todayCompletion, $promptPrice, $completionPrice);

            // Calculate last 7 days cost
            $last7Prompt = 0;
            $last7Completion = 0;
            foreach ($entries as $entry) {
                $entryDate = substr($entry['ts'] ?? '', 0, 10);
                if ($entryDate >= $sevenDaysAgo) {
                    $last7Prompt += (int)($entry['prompt_tokens'] ?? 0);
                    $last7Completion += (int)($entry['completion_tokens'] ?? 0);
                }
            }
            $result['estimated_cost_last_7_days'] = $this->calculateCost($last7Prompt, $last7Completion, $promptPrice, $completionPrice);

            // Calculate EUR costs
            $eurRate = (float)(getenv('EUR_USD_RATE') ?: 0.95);
            $result['estimated_cost_total_eur'] = round($result['estimated_cost_total'] * $eurRate, 4);
            $result['estimated_cost_today_eur'] = round($result['estimated_cost_today'] * $eurRate, 4);
            $result['estimated_cost_last_7_days_eur'] = round($result['estimated_cost_last_7_days'] * $eurRate, 4);
        }

        return $result;
    }

    private function calculateCost(int $promptTokens, int $completionTokens, float $promptPrice, float $completionPrice): float
    {
        $cost = ($promptTokens / 1000 * $promptPrice) + ($completionTokens / 1000 * $completionPrice);
        return round($cost, 4);
    }

    /**
     * @param array<int, array<string, mixed>> $entries
     * @return array<int, array<string, mixed>>
     */
    private function groupByModel(array $entries): array
    {
        $byModel = [];

        foreach ($entries as $entry) {
            $model = $entry['model'] ?? 'unknown';
            if (!isset($byModel[$model])) {
                $byModel[$model] = [
                    'model' => $model,
                    'calls' => 0,
                    'total_tokens' => 0,
                    'total_latency' => 0,
                ];
            }

            $byModel[$model]['calls']++;
            $byModel[$model]['total_tokens'] += (int)($entry['total_tokens'] ?? 0);
            $byModel[$model]['total_latency'] += (int)($entry['latency_ms'] ?? 0);
        }

        $result = [];
        foreach ($byModel as $data) {
            $avgTokens = round($data['total_tokens'] / $data['calls'], 2);
            $avgLatency = round($data['total_latency'] / $data['calls'], 2);

            $result[] = [
                'model' => $data['model'],
                'calls' => $data['calls'],
                'total_tokens' => $data['total_tokens'],
                'avg_tokens' => $avgTokens,
                'avg_latency_ms' => $avgLatency,
            ];
        }

        return $result;
    }

    /**
     * @param array<int, array<string, mixed>> $entries
     * @return array<int, array<string, mixed>>
     */
    private function groupByFeature(array $entries): array
    {
        $byFeature = [];

        foreach ($entries as $entry) {
            $feature = $entry['feature'] ?? 'unknown';
            if (!isset($byFeature[$feature])) {
                $byFeature[$feature] = [
                    'feature' => $feature,
                    'calls' => 0,
                    'total_tokens' => 0,
                ];
            }

            $byFeature[$feature]['calls']++;
            $byFeature[$feature]['total_tokens'] += (int)($entry['total_tokens'] ?? 0);
        }

        $result = [];
        foreach ($byFeature as $data) {
            $avgTokens = round($data['total_tokens'] / $data['calls'], 2);

            $result[] = [
                'feature' => $data['feature'],
                'calls' => $data['calls'],
                'total_tokens' => $data['total_tokens'],
                'avg_tokens' => $avgTokens,
            ];
        }

        return $result;
    }

    /**
     * @param string $path
     * @return array<int, array<string, mixed>>
     */
    private function loadEventsFromFile(string $path): array
    {
        $events = [];

        if (!file_exists($path)) {
            return $events;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return $events;
        }

        foreach ($lines as $line) {
            $data = json_decode($line, true);
            if (is_array($data)) {
                $events[] = $data;
            }
        }

        return $events;
    }

    /**
     * @param array<int, array<string, mixed>> $entries
     * @return array<int, array<string, mixed>>
     */
    private function getRecentCalls(array $entries, int $limit = 10): array
    {
        // Mezclar app + RAG y ordenar por fecha descendente (más reciente primero)
        usort($entries, static fn (array $a, array $b) => strcmp($b['ts'] ?? '', $a['ts'] ?? ''));

        $recent = array_slice($entries, 0, $limit);
        $result = [];

        foreach ($recent as $entry) {
            $result[] = [
                'ts' => $entry['ts'] ?? '',
                'feature' => $entry['feature'] ?? 'unknown',
                'model' => $entry['model'] ?? 'unknown',
                'total_tokens' => (int)($entry['total_tokens'] ?? 0),
                'latency_ms' => (int)($entry['latency_ms'] ?? 0),
                'success' => $entry['success'] ?? true,
                'error' => $entry['error'] ?? null,
            ];
        }

        return $result;
    }

    /**
     * @return array{
     *   ok: bool,
     *   global: array<string, mixed>,
     *   by_model: array<int, mixed>,
     *   by_feature: array<int, mixed>,
     *   recent_calls: array<int, mixed>,
     *   debug?: array<string, mixed>
     * }
     */
    private function emptyMetrics(): array
    {
        return [
            'ok' => true,
            'global' => [
                'total_calls' => 0,
                'total_tokens' => 0,
                'total_prompt_tokens' => 0,
                'total_completion_tokens' => 0,
                'tokens_today' => 0,
                'tokens_last_7_days' => 0,
                'avg_tokens_per_call' => 0,
                'avg_latency_ms' => 0,
                'failed_calls' => 0,
            ],
            'by_model' => [],
            'by_feature' => [],
            'recent_calls' => [],
            'debug' => $this->getDebugInfo(),
        ];
    }

    private function getDebugInfo(): array
    {
        return [
            'log_path_resolution' => [
                'central_path' => self::LOG_FILE,
                'central_exists' => file_exists(self::LOG_FILE),
            ]
        ];
    }
}
