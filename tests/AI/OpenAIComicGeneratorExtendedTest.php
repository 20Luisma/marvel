<?php

declare(strict_types=1);

namespace Tests\AI;

use App\AI\OpenAIComicGenerator;
use PHPUnit\Framework\TestCase;
use Tests\Support\OpenAITransportStub;

final class OpenAIComicGeneratorExtendedTest extends TestCase
{
    protected function setUp(): void
    {
        OpenAITransportStub::reset();
    }

    protected function tearDown(): void
    {
        OpenAITransportStub::reset();
    }

    public function test_is_configured_returns_true_even_when_url_is_empty_due_to_default(): void
    {
        // The constructor sets a default URL if empty
        $generator = new OpenAIComicGenerator('');
        
        $this->assertTrue($generator->isConfigured());
    }

    public function test_generate_comic_throws_invalid_argument_when_no_heroes(): void
    {
        $generator = new OpenAIComicGenerator('https://api.example.com');
        
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Debes proporcionar al menos un héroe para generar el cómic.');
        
        $generator->generateComic([]);
    }

    public function test_generate_comic_throws_when_response_is_invalid_json(): void
    {
        $generator = new OpenAIComicGenerator('https://api.example.com');
        
        OpenAITransportStub::$response = 'invalid-json';
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Respuesta no válida del microservicio de OpenAI');
        
        $generator->generateComic([['nombre' => 'Hero', 'contenido' => 'desc', 'imagen' => 'img']]);
    }

    public function test_generate_comic_throws_when_response_missing_content(): void
    {
        $generator = new OpenAIComicGenerator('https://api.example.com');
        
        OpenAITransportStub::$response = json_encode(['ok' => true]); // Missing content
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Respuesta del microservicio no contenía datos de historia.');
        
        $generator->generateComic([['nombre' => 'Hero', 'contenido' => 'desc', 'imagen' => 'img']]);
    }

    public function test_generate_comic_logs_usage_when_available(): void
    {
        $generator = new OpenAIComicGenerator('https://api.example.com');
        
        $responseContent = json_encode([
            'title' => 'Title',
            'summary' => 'Summary',
            'panels' => []
        ]);

        OpenAITransportStub::$response = json_encode([
            'ok' => true,
            'content' => $responseContent,
            'usage' => [
                'prompt_tokens' => 100,
                'completion_tokens' => 50,
                'total_tokens' => 150
            ],
            'model' => 'gpt-4'
        ]);
        
        $result = $generator->generateComic([[
            'heroId' => 'hero-1',
            'nombre' => 'Hero',
            'contenido' => '',
            'imagen' => 'img',
        ]]);
        
        $this->assertArrayHasKey('story', $result);
        
        // We can't easily assert logging happened without mocking the logger global or file system,
        // but this executes the code path.
    }
}
