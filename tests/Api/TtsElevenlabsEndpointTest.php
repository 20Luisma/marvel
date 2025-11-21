<?php

declare(strict_types=1);

namespace Tests\Api;

use PHPUnit\Framework\TestCase;

final class TtsElevenlabsEndpointTest extends TestCase
{
    private string $projectRoot;

    protected function setUp(): void
    {
        $this->projectRoot = dirname(__DIR__, 2);
    }

    // Rechaza métodos que no sean POST.
    public function testRejectsNonPostMethod(): void
    {
        $result = $this->runScript('public/api/tts-elevenlabs.php', [
            'REQUEST_METHOD' => 'GET',
            'HTTP_ACCEPT' => 'application/json',
            'APP_ORIGIN' => '',
            'APP_URL' => '',
        ]);

        $payload = json_decode($result['stdout'], true);

        self::assertSame('error', $payload['status'] ?? null);
        self::assertStringContainsString('Método no permitido', $payload['message'] ?? '');
    }

    // Devuelve 400 cuando el payload no es JSON válido.
    public function testReturnsErrorWhenPayloadIsInvalidJson(): void
    {
        $result = $this->runScript('public/api/tts-elevenlabs.php', [
            'REQUEST_METHOD' => 'POST',
            'HTTP_ACCEPT' => 'application/json',
            'APP_ORIGIN' => '',
            'APP_URL' => '',
        ], '  ');

        $payload = json_decode($result['stdout'], true);

        self::assertSame('error', $payload['status'] ?? null);
        self::assertStringContainsString('JSON válido', $payload['message'] ?? '');
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
