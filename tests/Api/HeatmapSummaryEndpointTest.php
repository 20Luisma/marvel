<?php

declare(strict_types=1);

namespace Tests\Api;

use PHPUnit\Framework\TestCase;

final class HeatmapSummaryEndpointTest extends TestCase
{
    private string $projectRoot;
    private string $storageDir;

    protected function setUp(): void
    {
        $this->projectRoot = dirname(__DIR__, 2);
        $this->storageDir = $this->projectRoot . '/storage/heatmap';
    }

    protected function tearDown(): void
    {
        $fixture = $this->storageDir . '/clicks_2099-01.jsonl';
        if (is_file($fixture)) {
            @unlink($fixture);
        }
    }

    // Rechaza métodos que no sean GET.
    public function testRejectsNonGetRequests(): void
    {
        $result = $this->runScript('public/api/heatmap/summary.php', [
            'REQUEST_METHOD' => 'POST',
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $payload = json_decode($result['stdout'], true);

        self::assertSame('error', $payload['status'] ?? null);
        self::assertStringContainsString('Solo se permiten GET', $payload['message'] ?? '');
    }

    // Calcula grid y totalClicks cuando hay eventos almacenados.
    public function testReturnsGridForStoredEvents(): void
    {
        if (!is_dir($this->storageDir)) {
            mkdir($this->storageDir, 0775, true);
        }

        $fixture = $this->storageDir . '/clicks_2099-01.jsonl';
        $events = [
            json_encode(['page' => '/', 'x' => 0.1, 'y' => 0.2], JSON_UNESCAPED_UNICODE),
            json_encode(['page' => '/', 'x' => 0.5, 'y' => 0.5], JSON_UNESCAPED_UNICODE),
        ];
        file_put_contents($fixture, implode(PHP_EOL, $events) . PHP_EOL, LOCK_EX);

        $result = $this->runScript('public/api/heatmap/summary.php', [
            'REQUEST_METHOD' => 'GET',
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $payload = json_decode($result['stdout'], true);

        self::assertSame('ok', $payload['status'] ?? null);
        self::assertSame('/', $payload['page'] ?? null);
        self::assertSame(20, $payload['rows'] ?? null);
        self::assertSame(20, $payload['cols'] ?? null);
        self::assertArrayHasKey('grid', $payload);
    }

    /**
     * @param array<string, string> $env
     * @return array{stdout:string, stderr:string, exitCode:int}
     */
    private function runScript(string $script, array $env = [], string $body = ''): array
    {
        $descriptor = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $processEnv = $this->buildEnv($env);
        $command = ['php', $script];

        $process = proc_open($command, $descriptor, $pipes, $this->projectRoot, $processEnv);
        if (!is_resource($process)) {
            $this->fail('No se pudo ejecutar el script: ' . $script);
        }

        fwrite($pipes[0], $body);
        fclose($pipes[0]);

        $stdout = stream_get_contents($pipes[1]) ?: '';
        fclose($pipes[1]);

        $stderr = stream_get_contents($pipes[2]) ?: '';
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        return [
            'stdout' => trim($stdout),
            'stderr' => trim($stderr),
            'exitCode' => $exitCode,
        ];
    }

    /**
     * @param array<string, string> $overrides
     * @return array<string, string>
     */
    private function buildEnv(array $overrides): array
    {
        $base = [];
        foreach (array_merge($_ENV, $_SERVER) as $key => $value) {
            if (is_string($key) && is_string($value)) {
                $base[$key] = $value;
            }
        }
        $base['PATH'] = getenv('PATH') ?: ($base['PATH'] ?? '');

        return array_merge($base, $overrides);
    }
}
