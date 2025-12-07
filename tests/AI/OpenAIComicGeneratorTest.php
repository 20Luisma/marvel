<?php

declare(strict_types=1);

namespace Tests\AI;

use App\AI\OpenAIComicGenerator;
use PHPUnit\Framework\TestCase;
use Tests\Support\OpenAITransportStub;

class OpenAIComicGeneratorTest extends TestCase
{
    protected function setUp(): void
    {
        OpenAITransportStub::reset();
    }

    protected function tearDown(): void
    {
        OpenAITransportStub::reset();
    }

    public function testGenerateComicHappyPath(): void
    {
        $generator = new OpenAIComicGenerator('https://api.openai.com');
        
        $heroes = [
            ['heroId' => '1', 'nombre' => 'Hulk', 'contenido' => 'Smash', 'imagen' => 'img.jpg']
        ];

        OpenAITransportStub::$response = json_encode([
            'ok' => true,
            'content' => json_encode([
                'title' => 'Hulk Smash',
                'summary' => 'Hulk smashes things',
                'panels' => [
                    ['title' => 'P1', 'description' => 'D1', 'caption' => 'C1']
                ]
            ]),
            'usage' => [
                'prompt_tokens' => 10,
                'completion_tokens' => 20,
                'total_tokens' => 30
            ]
        ]);

        $result = $generator->generateComic($heroes);

        $this->assertArrayHasKey('story', $result);
        $this->assertEquals('Hulk Smash', $result['story']['title']);
        $this->assertCount(1, $result['story']['panels']);
    }
    
    public function testGenerateComicError(): void
    {
        $generator = new OpenAIComicGenerator('https://api.openai.com');
        $heroes = [['heroId' => '1', 'nombre' => 'Hulk', 'contenido' => '', 'imagen' => '']];
        
        OpenAITransportStub::$response = json_encode(['error' => 'Service unavailable']);
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Microservicio OpenAI no disponible: Service unavailable');
        
        $generator->generateComic($heroes);
    }
}
