<?php

declare(strict_types=1);

namespace App\Dev\Test;

use DateTimeImmutable;
use RuntimeException;
use SimpleXMLElement;

final class PhpUnitTestRunner
{
    public function __construct(
        private readonly string $projectRoot,
        private readonly bool $testsAllowed
    ) {
    }

    public static function fromEnvironment(string $projectRoot): self
    {
        return new self($projectRoot, self::detectLocalEnvironment());
    }

    /**
     * @return array{
     *     status: string,
     *     message?: string,
     *     exitCode?: int,
     *     startedAt?: string,
     *     finishedAt?: string,
     *     duration?: float,
     *     summary?: array<string, int|float>,
     *     statusCounts?: array<string, int>,
     *     tests?: array<int, array<string, mixed>>,
     *     output?: array{stdout: string, stderr: string}
     * }
     */
    public function run(): array
    {
        if (!$this->testsAllowed) {
            return [
                'status' => 'skipped',
                'message' => 'La ejecución de tests está deshabilitada en este entorno. Establece APP_ENV=local o APP_ALLOW_TESTS=1 para habilitarla.'
            ];
        }

        $phpunitBinary = $this->projectRoot . '/vendor/bin/phpunit';
        if (!file_exists($phpunitBinary)) {
            return [
                'status' => 'error',
                'message' => 'No se encontró el binario de PHPUnit. Ejecuta composer install.'
            ];
        }

        $logFile = tempnam(sys_get_temp_dir(), 'phpunit-junit-');
        if ($logFile === false) {
            return [
                'status' => 'error',
                'message' => 'No se pudo crear el archivo temporal para el log de PHPUnit.'
            ];
        }

        $startedAt = microtime(true);
        $command = sprintf(
            '%s %s --colors=never --log-junit %s',
            escapeshellarg(PHP_BINARY),
            escapeshellarg($phpunitBinary),
            escapeshellarg($logFile)
        );

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptors, $pipes, $this->projectRoot);
        if (!is_resource($process)) {
            @unlink($logFile);

            return [
                'status' => 'error',
                'message' => 'No se pudo iniciar el proceso de PHPUnit.'
            ];
        }

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]) ?: '';
        $stderr = stream_get_contents($pipes[2]) ?: '';
        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);
        $finishedAt = microtime(true);

        $result = [
            'status' => 'error',
            'message' => 'No fue posible leer el reporte de PHPUnit.',
            'exitCode' => $exitCode,
            'startedAt' => self::formatInstant($startedAt),
            'finishedAt' => self::formatInstant($finishedAt),
            'duration' => round($finishedAt - $startedAt, 4),
            'output' => [
                'stdout' => trim($stdout),
                'stderr' => trim($stderr),
            ],
        ];

        try {
            $parsed = $this->parseJunitReport($logFile);
        } catch (RuntimeException $exception) {
            @unlink($logFile);

            $result['status'] = 'error';
            $result['message'] = $exception->getMessage();

            return $result;
        }

        @unlink($logFile);

        $status = match (true) {
            $exitCode === 0 => 'passed',
            $parsed['summary']['errors'] > 0 => 'error',
            default => 'failed',
        };

        $summary = $parsed['summary'];
        if (isset($summary['time'])) {
            $summary['time'] = round((float) $summary['time'], 4);
        }

        $statusCounts = $parsed['statusCounts'];
        foreach (['passed', 'failed', 'error', 'skipped'] as $key) {
            $statusCounts[$key] = $statusCounts[$key] ?? 0;
        }

        $message = match ($status) {
            'passed' => 'Todos los tests se ejecutaron correctamente.',
            'failed' => 'La suite de tests finalizó con fallos.',
            'error' => 'La suite de tests finalizó con errores.',
            default => 'Resultado de la suite de tests no determinado.',
        };

        return [
            'status' => $status,
            'message' => $message,
            'exitCode' => $exitCode,
            'startedAt' => self::formatInstant($startedAt),
            'finishedAt' => self::formatInstant($finishedAt),
            'duration' => round($finishedAt - $startedAt, 4),
            'summary' => $summary,
            'statusCounts' => $statusCounts,
            'tests' => $parsed['tests'],
            'output' => [
                'stdout' => trim($stdout),
                'stderr' => trim($stderr),
            ],
        ];
    }

    /**
     * @return array{summary: array<string, int|float>, statusCounts: array<string, int>, tests: array<int, array<string, mixed>>}
     */
    private function parseJunitReport(string $logFile): array
    {
        if (!is_file($logFile)) {
            throw new RuntimeException('PHPUnit no generó el reporte JUnit esperado.');
        }

        $content = file_get_contents($logFile);
        if ($content === false) {
            throw new RuntimeException('No se pudo leer el reporte generado por PHPUnit.');
        }

        $xml = simplexml_load_string($content);
        if (!$xml instanceof SimpleXMLElement) {
            throw new RuntimeException('El reporte de PHPUnit tiene un formato inválido.');
        }

        $suite = $xml->testsuite[0] ?? $xml;
        if (!$suite instanceof SimpleXMLElement) {
            throw new RuntimeException('El reporte de PHPUnit no contiene información de tests.');
        }

        $summary = [
            'tests' => (int) ($suite['tests'] ?? 0),
            'assertions' => (int) ($suite['assertions'] ?? 0),
            'failures' => (int) ($suite['failures'] ?? 0),
            'errors' => (int) ($suite['errors'] ?? 0),
            'skipped' => (int) ($suite['skipped'] ?? 0),
            'time' => (float) ($suite['time'] ?? 0.0),
        ];

        $statusCounts = [
            'passed' => 0,
            'failed' => 0,
            'error' => 0,
            'skipped' => 0,
        ];

        $tests = [];
        foreach ($suite->xpath('.//testcase') as $testCase) {
            if (!$testCase instanceof SimpleXMLElement) {
                continue;
            }

            $attributes = $testCase->attributes();
            $className = (string) ($attributes['class'] ?? $attributes['classname'] ?? '');
            $name = (string) ($attributes['name'] ?? '');
            $time = round((float) ($attributes['time'] ?? 0.0), 4);

            $status = 'passed';
            $message = null;

            if (isset($testCase->failure)) {
                $status = 'failed';
                $message = trim((string) $testCase->failure);
            } elseif (isset($testCase->error)) {
                $status = 'error';
                $message = trim((string) $testCase->error);
            } elseif (isset($testCase->skipped)) {
                $status = 'skipped';
                $message = trim((string) $testCase->skipped);
            }

            if (!isset($statusCounts[$status])) {
                $statusCounts[$status] = 0;
            }
            $statusCounts[$status]++;

            $tests[] = [
                'class' => $className,
                'name' => $name,
                'status' => $status,
                'time' => $time,
                'message' => $message,
            ];
        }

        return [
            'summary' => $summary,
            'statusCounts' => $statusCounts,
            'tests' => $tests,
        ];
    }

    private static function formatInstant(float $microtime): string
    {
        $date = DateTimeImmutable::createFromFormat('U.u', sprintf('%.6F', $microtime));

        if ($date === false) {
            return date(DATE_ATOM, (int) $microtime);
        }

        return $date->format(DATE_ATOM);
    }

    private static function detectLocalEnvironment(): bool
    {
        $env = strtolower((string) ($_ENV['APP_ENV'] ?? getenv('APP_ENV') ?? ''));
        if ($env !== '') {
            if (in_array($env, ['local', 'development', 'dev', 'testing', 'test'], true)) {
                return true;
            }
            if (in_array($env, ['prod', 'production'], true)) {
                return false;
            }
        }

        $allowEnv = $_ENV['APP_ALLOW_TESTS'] ?? getenv('APP_ALLOW_TESTS');
        if ($allowEnv !== null && filter_var($allowEnv, FILTER_VALIDATE_BOOL)) {
            return true;
        }

        if (PHP_SAPI === 'cli' || PHP_SAPI === 'cli-server') {
            return true;
        }

        $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? null;
        if ($remoteAddr !== null && in_array($remoteAddr, ['127.0.0.1', '::1'], true)) {
            return true;
        }

        return false;
    }
}
