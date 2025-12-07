<?php

declare(strict_types=1);

namespace App\AI;

// Mocks for global functions
function curl_init(?string $url = null) { 
    return new \stdClass(); 
}

function curl_setopt($handle, int $option, $value): bool { 
    return true; 
}

function curl_exec($handle): string|bool {
    return $GLOBALS['openai_curl_exec'] ?? '{"ok":true}';
}

function curl_getinfo($handle, int $option = 0) {
    return $GLOBALS['openai_curl_code'] ?? 200;
}

function curl_close($handle): void {}

function curl_error($handle): string { 
    return $GLOBALS['openai_curl_error'] ?? ''; 
}

namespace Tests\AI;

use App\AI\OpenAIComicGenerator;
use PHPUnit\Framework\TestCase;

class OpenAIComicGeneratorTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($GLOBALS['openai_curl_exec']);
        unset($GLOBALS['openai_curl_code']);
        unset($GLOBALS['openai_curl_error']);
    }

    public function testGenerateComicHappyPath(): void
    {
        $generator = new OpenAIComicGenerator('https://api.openai.com');
        
        $heroes = [
            ['heroId' => '1', 'nombre' => 'Hulk', 'contenido' => 'Smash', 'imagen' => 'img.jpg']
        ];

        $GLOBALS['openai_curl_exec'] = json_encode([
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
        
        $GLOBALS['openai_curl_exec'] = json_encode(['error' => 'Service unavailable']);
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Microservicio OpenAI no disponible: Service unavailable');
        
        $generator->generateComic($heroes);
    }
}
