<?php

declare(strict_types=1);

namespace Tests\Application;

use App\Albums\Application\DTO\CreateAlbumRequest;
use App\Albums\Application\UseCase\CreateAlbumUseCase;
use App\Albums\Application\UseCase\ListAlbumsUseCase;
use App\Heroes\Application\UseCase\CreateHeroUseCase;
use App\Heroes\Application\UseCase\ListHeroesUseCase;
use App\Notifications\Application\AlbumUpdatedNotificationHandler;
use App\Notifications\Application\HeroCreatedNotificationHandler;
use App\Notifications\Infrastructure\FileNotificationSender;
use App\Notifications\Infrastructure\NotificationRepository;
use App\Shared\Infrastructure\Bus\InMemoryEventBus;
use App\Shared\Http\JsonResponse;
use App\Controllers\HeroController;
use App\Controllers\Http\Request;
use PHPUnit\Framework\TestCase;
use Tests\Doubles\InMemoryAlbumRepository;
use Tests\Doubles\InMemoryHeroRepository;

final class CreateHeroUseCaseHttpTest extends TestCase
{
    private InMemoryAlbumRepository $albumRepository;
    private InMemoryHeroRepository $heroRepository;
    private InMemoryEventBus $eventBus;
    private FileNotificationSender $notificationSender;
    private NotificationRepository $notificationRepository;
    private CreateAlbumUseCase $createAlbumUseCase;
    private ListAlbumsUseCase $listAlbumsUseCase;
    private CreateHeroUseCase $createHeroUseCase;
    private ListHeroesUseCase $listHeroesUseCase;
    private AlbumUpdatedNotificationHandler $albumUpdatedNotificationHandler;
    private HeroCreatedNotificationHandler $heroCreatedNotificationHandler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->albumRepository = new InMemoryAlbumRepository();
        $this->heroRepository = new InMemoryHeroRepository();
        $this->eventBus = new InMemoryEventBus();
        $this->notificationSender = new FileNotificationSender(dirname(__DIR__, 2) . '/storage/test_notifications.log');
        $this->notificationRepository = new NotificationRepository(dirname(__DIR__, 2) . '/storage/test_notifications.log');

        $this->createAlbumUseCase = new CreateAlbumUseCase($this->albumRepository);
        $this->listAlbumsUseCase = new ListAlbumsUseCase($this->albumRepository);
        $this->createHeroUseCase = new CreateHeroUseCase($this->heroRepository, $this->albumRepository, $this->eventBus);
        $this->listHeroesUseCase = new ListHeroesUseCase($this->heroRepository);
        $this->albumUpdatedNotificationHandler = new AlbumUpdatedNotificationHandler($this->notificationSender);
        $this->heroCreatedNotificationHandler = new HeroCreatedNotificationHandler($this->notificationSender);

        $this->eventBus->subscribe($this->albumUpdatedNotificationHandler);
        $this->eventBus->subscribe($this->heroCreatedNotificationHandler);
    }

    protected function tearDown(): void
    {
        $logPath = dirname(__DIR__, 2) . '/storage/test_notifications.log';
        if (is_file($logPath)) {
            unlink($logPath);
        }

        parent::tearDown();
    }

    public function testCreateHeroSuccessfully(): void
    {
        $albumId = $this->createAlbum('Test Album');
        Request::withJsonBody(json_encode(['nombre' => 'Test Hero', 'imagen' => 'test.jpg', 'contenido' => 'Test content']));

        $controller = $this->heroController();
        $response = $this->capturePayload(fn () => $controller->store($albumId));

        $this->assertEquals('éxito', $response['estado']);
        $this->assertEquals('Test Hero', $response['datos']['nombre']);
    }

    public function testCreateHeroFailsWithMissingData(): void
    {
        $albumId = $this->createAlbum('Test Album');
        Request::withJsonBody(json_encode(['nombre' => 'Test Hero'])); // Missing imagen

        $controller = $this->heroController();
        $response = $this->capturePayload(fn () => $controller->store($albumId));

        $this->assertEquals('error', $response['estado']);
        $this->assertEquals('Los campos nombre e imagen son obligatorios.', $response['message']);
    }

    private function createAlbum(string $name): string
    {
        $response = $this->createAlbumUseCase->execute(new CreateAlbumRequest($name, null));

        return $response->toArray()['albumId'];
    }

    private function heroController(): HeroController
    {
        return new HeroController(
            $this->listHeroesUseCase,
            $this->createHeroUseCase,
            new \App\Heroes\Application\UseCase\UpdateHeroUseCase($this->heroRepository),
            new \App\Heroes\Application\UseCase\DeleteHeroUseCase($this->heroRepository),
            new \App\Heroes\Application\UseCase\FindHeroUseCase($this->heroRepository),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function capturePayload(callable $callable): array
    {
        ob_start();
        $result = $callable();
        $contents = (string) ob_get_clean();
        $payload = JsonResponse::lastPayload();

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
