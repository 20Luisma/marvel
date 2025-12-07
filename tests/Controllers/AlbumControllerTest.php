<?php

declare(strict_types=1);

namespace Tests\Controllers;

use Src\Controllers\AlbumController;
use Src\Controllers\Http\Request;
use App\Albums\Application\UseCase\ListAlbumsUseCase;
use App\Albums\Application\UseCase\CreateAlbumUseCase;
use App\Albums\Application\UseCase\UpdateAlbumUseCase;
use App\Albums\Application\UseCase\DeleteAlbumUseCase;
use App\Albums\Application\UseCase\FindAlbumUseCase;
use App\Albums\Domain\Repository\AlbumRepository;
use App\Albums\Domain\Entity\Album;
use App\Heroes\Domain\Repository\HeroRepository;
use App\Shared\Domain\Bus\EventBus;
use PHPUnit\Framework\TestCase;

class AlbumControllerTest extends TestCase
{
    private $repository;
    private $eventBus;
    private $heroRepository;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(AlbumRepository::class);
        $this->eventBus = $this->createMock(EventBus::class);
        $this->heroRepository = $this->createMock(HeroRepository::class);
    }

    public function testIndex(): void
    {
        $this->repository->method('all')->willReturn([]);
        
        $listUseCase = new ListAlbumsUseCase($this->repository);
        
        $controller = new AlbumController(
            $listUseCase,
            new CreateAlbumUseCase($this->repository),
            new UpdateAlbumUseCase($this->repository, $this->eventBus),
            new DeleteAlbumUseCase($this->repository, $this->heroRepository),
            new FindAlbumUseCase($this->repository)
        );

        $payload = $this->capturePayload(fn () => $controller->index());

        $this->assertEquals('éxito', $payload['estado']);
    }

    public function testStore(): void
    {
        $this->repository->expects($this->once())->method('save');
        
        $createUseCase = new CreateAlbumUseCase($this->repository);

        $controller = new AlbumController(
            new ListAlbumsUseCase($this->repository),
            $createUseCase,
            new UpdateAlbumUseCase($this->repository, $this->eventBus),
            new DeleteAlbumUseCase($this->repository, $this->heroRepository),
            new FindAlbumUseCase($this->repository)
        );

        Request::withJsonBody(json_encode(['nombre' => 'New Album']));

        $payload = $this->capturePayload(fn () => $controller->store());

        $this->assertEquals('éxito', $payload['estado']);
    }
    
    public function testStoreInvalid(): void
    {
        $controller = new AlbumController(
            new ListAlbumsUseCase($this->repository),
            new CreateAlbumUseCase($this->repository),
            new UpdateAlbumUseCase($this->repository, $this->eventBus),
            new DeleteAlbumUseCase($this->repository, $this->heroRepository),
            new FindAlbumUseCase($this->repository)
        );

        Request::withJsonBody(json_encode(['nombre' => ''])); // Empty name

        $payload = $this->capturePayload(fn () => $controller->store());

        $this->assertEquals('error', $payload['estado']);
    }

    /**
     * @return array<string, mixed>
     */
    private function capturePayload(callable $callable): array
    {
        ob_start();
        $result = $callable();
        $contents = (string) ob_get_clean();

        $payload = \App\Shared\Http\JsonResponse::lastPayload();

        if (is_array($result)) {
            return $result;
        }

        if ($payload !== null) {
            return $payload;
        }

        if ($contents !== '') {
            return json_decode($contents, true) ?? [];
        }

        return [];
    }
}
