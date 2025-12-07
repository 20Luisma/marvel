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
        $validator = new ConfigValidator([
            'APP_ENV' => '',
            'OPENAI_SERVICE_URL' => 'http://localhost:3000/api/chat',
            'RAG_SERVICE_URL' => 'http://localhost:8000/api/rag',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('APP_ENV no puede estar vacío');

        $validator->validate();
    }

    public function testValidateThrowsExceptionWhenOpenAiUrlInvalid(): void
    {
        $validator = new ConfigValidator([
            'APP_ENV' => 'local',
            'OPENAI_SERVICE_URL' => 'not-a-valid-url',
            'RAG_SERVICE_URL' => 'http://localhost:8000/api/rag',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('debe ser una URL válida');

        $validator->validate();
    }

    public function testValidateThrowsExceptionWhenRagUrlEmpty(): void
    {
        $validator = new ConfigValidator([
            'APP_ENV' => 'local',
            'OPENAI_SERVICE_URL' => 'http://localhost:3000/api/chat',
            'RAG_SERVICE_URL' => '',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('es obligatorio y no puede estar vacío');

        $validator->validate();
    }

    public function testValidateThrowsExceptionWhenRagUrlInvalid(): void
    {
        $validator = new ConfigValidator([
            'APP_ENV' => 'local',
            'OPENAI_SERVICE_URL' => 'http://localhost:3000/api/chat',
            'RAG_SERVICE_URL' => 'invalid-url',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('debe ser una URL válida');

        $validator->validate();
    }

    public function testValidatePassesWithValidConfig(): void
    {
        $validator = new ConfigValidator([
            'APP_ENV' => 'local',
            'OPENAI_SERVICE_URL' => 'http://localhost:3000/api/chat',
            'RAG_SERVICE_URL' => 'http://localhost:8000/api/rag',
        ]);

        // Should not throw any exception
        $validator->validate();
        $this->assertTrue(true);
    }

    public function testValidateOptionalHeatmapUrlSkipsValidationWhenEmpty(): void
    {
        $validator = new ConfigValidator([
            'APP_ENV' => 'local',
            'OPENAI_SERVICE_URL' => 'http://localhost:3000/api/chat',
            'RAG_SERVICE_URL' => 'http://localhost:8000/api/rag',
            'HEATMAP_API_BASE_URL' => '',
        ]);

        $validator->validate();
        $this->assertTrue(true);
    }

    public function testValidateThrowsExceptionWhenHeatmapUrlIsInvalid(): void
    {
        $validator = new ConfigValidator([
            'APP_ENV' => 'local',
            'OPENAI_SERVICE_URL' => 'http://localhost:3000/api/chat',
            'RAG_SERVICE_URL' => 'http://localhost:8000/api/rag',
            'HEATMAP_API_BASE_URL' => 'invalid-heatmap-url',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('HEATMAP_API_BASE_URL debe ser una URL válida');

        $validator->validate();
    }

    public function testValidateHeatmapUrlPassesWhenValid(): void
    {
        $validator = new ConfigValidator([
            'APP_ENV' => 'local',
            'OPENAI_SERVICE_URL' => 'http://localhost:3000/api/chat',
            'RAG_SERVICE_URL' => 'http://localhost:8000/api/rag',
            'HEATMAP_API_BASE_URL' => 'http://heatmap.example.com/api',
        ]);

        $validator->validate();
        $this->assertTrue(true);
    }

    public function testValidateReadsFromGetenvWhenNotInArray(): void
    {
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
    }

    public function testValidateInProductionEnvironmentLogsErrors(): void
    {
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
    }

    public function testValidateInHostingEnvironmentIsConsideredProduction(): void
    {
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
    }
}
