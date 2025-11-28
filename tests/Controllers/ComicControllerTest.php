<?php

declare(strict_types=1);

namespace Tests\Controllers;

use App\AI\OpenAIComicGenerator;
use App\Heroes\Application\UseCase\FindHeroUseCase;
use App\Heroes\Domain\Entity\Hero;
use PHPUnit\Framework\TestCase;
use Src\Controllers\ComicController;
use Src\Controllers\Http\Request;
use Tests\Doubles\InMemoryHeroRepository;
use Tests\Support\OpenAITransportStub;

final class ComicControllerTest extends TestCase
{
    private InMemoryHeroRepository $heroRepository;
    private OpenAIComicGenerator $generator;
    private ComicController $controller;

    protected function setUp(): void
    {
        $this->heroRepository = new InMemoryHeroRepository();
        $this->generator = new OpenAIComicGenerator('http://fake-service');
        $this->controller = new ComicController($this->generator, new FindHeroUseCase($this->heroRepository));
        OpenAITransportStub::reset();
        http_response_code(200);
    }

    public function testGenerateFailsWhenHeroIdsMissing(): void
    {
        Request::withJsonBody(json_encode(['heroIds' => []]));

        $payload = $this->captureJson(fn () => $this->controller->generate());

        self::assertSame('error', $payload['estado']);
        self::assertSame(422, http_response_code());
    }

    public function testGenerateReturns404WhenNoHeroesResolved(): void
    {
        Request::withJsonBody(json_encode(['heroIds' => ['missing-hero']]));

        $payload = $this->captureJson(fn () => $this->controller->generate());

        self::assertSame('error', $payload['estado']);
        self::assertSame(404, http_response_code());
        self::assertSame('No se encontraron héroes válidos para generar el cómic.', $payload['message']);
    }

    public function testGenerateReturnsStoryWhenHeroesExist(): void
    {
        $hero = Hero::create('hero-1', 'album-1', 'Thor', 'Trueno', 'https://example.com/thor.jpg');
        $this->heroRepository->save($hero);
        Request::withJsonBody(json_encode(['heroIds' => ['hero-1']]));

        OpenAITransportStub::$response = json_encode([
            'choices' => [
                [
                    'message' => [
                        'content' => json_encode([
                            'title' => 'Historia simulada',
                            'summary' => 'Resumen simulado',
                            'panels' => [],
                        ], JSON_UNESCAPED_UNICODE),
                    ],
                ],
            ],
        ]);

        $payload = $this->captureJson(fn () => $this->controller->generate());

        self::assertSame('éxito', $payload['estado']);
        self::assertSame(201, http_response_code());
        self::assertSame('Historia simulada', $payload['datos']['story']['title']);
    }

    /**
     * @return array<string, mixed>
     */
    private function captureJson(callable $callable): array
    {
        ob_start();
        $callable();
        $contents = (string) ob_get_clean();

        return json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
    }
}
