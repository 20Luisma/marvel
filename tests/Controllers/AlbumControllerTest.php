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

        ob_start();
        $controller->index();
        $output = ob_get_clean();

        $this->assertJson($output);
        $decoded = json_decode($output, true);
        $this->assertEquals('éxito', $decoded['estado']);
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

        ob_start();
        $controller->store();
        $output = ob_get_clean();

        $this->assertJson($output);
        $decoded = json_decode($output, true);
        $this->assertEquals(201, http_response_code());
        $this->assertEquals('éxito', $decoded['estado']);
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

        ob_start();
        $controller->store();
        $output = ob_get_clean();

        $this->assertJson($output);
        $decoded = json_decode($output, true);
        $this->assertEquals(400, http_response_code());
        $this->assertEquals('error', $decoded['estado']);
    }
}
