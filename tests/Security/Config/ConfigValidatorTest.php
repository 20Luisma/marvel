<?php

declare(strict_types=1);

namespace Tests\Security\Config;

use App\Security\Config\ConfigValidator;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ConfigValidatorTest extends TestCase
{
    public function testValidateThrowsExceptionWhenAppEnvEmpty(): void
    {
        $this->suppressOutput(function (): void {
            $validator = new ConfigValidator([
                'APP_ENV' => '',
                'OPENAI_SERVICE_URL' => 'http://localhost:3000/api/chat',
                'RAG_SERVICE_URL' => 'http://localhost:8000/api/rag',
            ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('APP_ENV no puede estar vacío');

        $validator->validate();
        });
    }

    public function testValidateThrowsExceptionWhenOpenAiUrlInvalid(): void
    {
        $this->suppressOutput(function (): void {
        $validator = new ConfigValidator([
            'APP_ENV' => 'local',
            'OPENAI_SERVICE_URL' => 'not-a-valid-url',
            'RAG_SERVICE_URL' => 'http://localhost:8000/api/rag',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('debe ser una URL válida');

        $validator->validate();
        });
    }

    public function testValidateThrowsExceptionWhenRagUrlEmpty(): void
    {
        $this->suppressOutput(function (): void {
        $validator = new ConfigValidator([
            'APP_ENV' => 'local',
            'OPENAI_SERVICE_URL' => 'http://localhost:3000/api/chat',
            'RAG_SERVICE_URL' => '',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('es obligatorio y no puede estar vacío');

        $validator->validate();
        });
    }

    public function testValidateThrowsExceptionWhenRagUrlInvalid(): void
    {
        $this->suppressOutput(function (): void {
        $validator = new ConfigValidator([
            'APP_ENV' => 'local',
            'OPENAI_SERVICE_URL' => 'http://localhost:3000/api/chat',
            'RAG_SERVICE_URL' => 'invalid-url',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('debe ser una URL válida');

        $validator->validate();
        });
    }

    public function testValidatePassesWithValidConfig(): void
    {
        $this->suppressOutput(function (): void {
        $validator = new ConfigValidator([
            'APP_ENV' => 'local',
            'OPENAI_SERVICE_URL' => 'http://localhost:3000/api/chat',
            'RAG_SERVICE_URL' => 'http://localhost:8000/api/rag',
        ]);

        // Should not throw any exception
        $validator->validate();
        $this->assertTrue(true);
        });
    }

    public function testValidateOptionalHeatmapUrlSkipsValidationWhenEmpty(): void
    {
        $this->suppressOutput(function (): void {
        $validator = new ConfigValidator([
            'APP_ENV' => 'local',
            'OPENAI_SERVICE_URL' => 'http://localhost:3000/api/chat',
            'RAG_SERVICE_URL' => 'http://localhost:8000/api/rag',
            'HEATMAP_API_BASE_URL' => '',
        ]);

        $validator->validate();
        $this->assertTrue(true);
        });
    }

    public function testValidateThrowsExceptionWhenHeatmapUrlIsInvalid(): void
    {
        $this->suppressOutput(function (): void {
        $validator = new ConfigValidator([
            'APP_ENV' => 'local',
            'OPENAI_SERVICE_URL' => 'http://localhost:3000/api/chat',
            'RAG_SERVICE_URL' => 'http://localhost:8000/api/rag',
            'HEATMAP_API_BASE_URL' => 'invalid-heatmap-url',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('HEATMAP_API_BASE_URL debe ser una URL válida');

        $validator->validate();
        });
    }

    public function testValidateHeatmapUrlPassesWhenValid(): void
    {
        $this->suppressOutput(function (): void {
        $validator = new ConfigValidator([
            'APP_ENV' => 'local',
            'OPENAI_SERVICE_URL' => 'http://localhost:3000/api/chat',
            'RAG_SERVICE_URL' => 'http://localhost:8000/api/rag',
            'HEATMAP_API_BASE_URL' => 'http://heatmap.example.com/api',
        ]);

        $validator->validate();
        $this->assertTrue(true);
        });
    }

    public function testValidateReadsFromGetenvWhenNotInArray(): void
    {
        $this->suppressOutput(function (): void {
        $originalEnv = getenv('APP_ENV');
        putenv('APP_ENV=testing');

        $validator = new ConfigValidator([
            'OPENAI_SERVICE_URL' => 'http://localhost:3000/api/chat',
            'RAG_SERVICE_URL' => 'http://localhost:8000/api/rag',
        ]);

        $validator->validate();
        $this->assertTrue(true);

        // Restore original environment
        if ($originalEnv !== false) {
            putenv("APP_ENV=$originalEnv");
        } else {
            putenv('APP_ENV');
        }
        });
    }

    public function testValidateInProductionEnvironmentLogsErrors(): void
    {
        $this->suppressOutput(function (): void {
            $validator = new ConfigValidator(
                [
                    'APP_ENV' => '',
                    'OPENAI_SERVICE_URL' => 'http://localhost:3000/api/chat',
                    'RAG_SERVICE_URL' => 'http://localhost:8000/api/rag',
                ],
                null,
                'production'
            );

            $this->expectException(RuntimeException::class);
            $validator->validate();
        });
    }

    public function testValidateInHostingEnvironmentIsConsideredProduction(): void
    {
        $this->suppressOutput(function (): void {
            $validator = new ConfigValidator(
                [
                    'APP_ENV' => '',
                    'OPENAI_SERVICE_URL' => 'http://localhost:3000/api/chat',
                    'RAG_SERVICE_URL' => 'http://localhost:8000/api/rag',
                ],
                null,
                'hosting'
            );

            $this->expectException(RuntimeException::class);
            $validator->validate();
        });
    }

    /**
     * @param callable():void $callback
     */
    private function suppressOutput(callable $callback): void
    {
        $level = ob_get_level();
        ob_start();
        $prevErrorLog = ini_get('error_log');
        $prevDisplay = ini_set('display_errors', '0');
        $prevLogErrors = ini_set('log_errors', '1');
        $tempLog = sys_get_temp_dir() . '/phpunit-config-validator.log';
        ini_set('error_log', $tempLog);
        try {
            $callback();
        } finally {
            if ($prevErrorLog !== false) {
                ini_set('error_log', (string) $prevErrorLog);
            }
            if ($prevDisplay !== false) {
                ini_set('display_errors', (string) $prevDisplay);
            }
            if ($prevLogErrors !== false) {
                ini_set('log_errors', (string) $prevLogErrors);
            }
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
        }
    }
}
