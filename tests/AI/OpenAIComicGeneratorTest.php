<?php

declare(strict_types=1);

namespace Tests\AI;

use App\AI\OpenAIComicGenerator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\OpenAITransportStub;

final class OpenAIComicGeneratorTest extends TestCase
{
    protected function setUp(): void
    {
        OpenAITransportStub::reset();
    }

    public function testGenerateComicFailsWhenHeroesListIsEmpty(): void
    {
        $generator = new OpenAIComicGenerator('http://fake-service');

        $this->expectException(InvalidArgumentException::class);
        $generator->generateComic([]);
    }

    public function testGenerateComicReturnsStructuredStory(): void
    {
        OpenAITransportStub::$response = json_encode([
            'choices' => [
                [
                    'message' => [
                        'content' => json_encode([
                            'title' => 'Batalla Epica',
                            'summary' => 'Los heroes se unen.',
                            'panels' => [
                                ['title' => 'Inicio', 'description' => 'Todo comienza', 'caption' => 'Vamos'],
                            ],
                        ], JSON_UNESCAPED_UNICODE),
                    ],
                ],
            ],
        ]);

        $generator = new OpenAIComicGenerator('http://fake-service');
        $story = $generator->generateComic([
            ['heroId' => 'hero-1', 'nombre' => 'Iron Man', 'contenido' => '', 'imagen' => 'img'],
        ]);

        self::assertSame('Batalla Epica', $story['story']['title']);
        self::assertCount(1, $story['story']['panels']);
    }

    public function testGenerateComicHandlesNonArrayPanels(): void
    {
        OpenAITransportStub::$response = json_encode([
            'choices' => [
                [
                    'message' => [
                        'content' => json_encode([
                            'title' => 'Titulo',
                            'summary' => 'Resumen',
                            'panels' => 'invalid',
                        ], JSON_UNESCAPED_UNICODE),
                    ],
                ],
            ],
        ]);

        $generator = new OpenAIComicGenerator('http://fake-service');
        $story = $generator->generateComic([
            ['heroId' => 'hero-1', 'nombre' => 'Iron Man', 'contenido' => 'algo', 'imagen' => 'img'],
        ]);

        self::assertSame('Titulo', $story['story']['title']);
        self::assertSame([], $story['story']['panels']);
    }

    public function testGenerateComicBubblesUpServiceErrors(): void
    {
        OpenAITransportStub::$response = json_encode(['error' => 'Service down']);
        $generator = new OpenAIComicGenerator('http://fake-service');

        $this->expectException(RuntimeException::class);
        $generator->generateComic([
            ['heroId' => 'hero-1', 'nombre' => 'Iron Man', 'contenido' => '...', 'imagen' => 'img'],
        ]);
    }
}
