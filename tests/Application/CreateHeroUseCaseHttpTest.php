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
use PHPUnit\Framework\TestCase;
use Tests\Doubles\InMemoryAlbumRepository;
use Tests\Doubles\InMemoryHeroRepository;

class CreateHeroUseCaseHttpTest extends TestCase
{
    private array $container;

    protected function setUp(): void
    {
        parent::setUp();
        if (!defined('PHPUNIT_RUNNING')) {
            define('PHPUNIT_RUNNING', true);
        }

        if (!defined('SKIP_HTTP_BOOT')) {
            define('SKIP_HTTP_BOOT', true);
        }

        require_once dirname(__DIR__, 2) . '/public/index.php';

        $this->container = require_once dirname(__DIR__, 2) . '/src/bootstrap.php';
        $logPath = dirname(__DIR__, 2) . '/storage/test_notifications.log';
        if (is_file($logPath)) {
            unlink($logPath);
        }

        $albumRepository = new InMemoryAlbumRepository();
        $heroRepository = new InMemoryHeroRepository();
        $eventBus = new InMemoryEventBus();
        $notificationRepository = new NotificationRepository($logPath);
        $notificationSender = new FileNotificationSender($logPath);

        $eventBus->subscribe(new HeroCreatedNotificationHandler($notificationSender));
        $eventBus->subscribe(new AlbumUpdatedNotificationHandler($notificationSender));

        $this->container['albumRepository'] = $albumRepository;
        $this->container['heroRepository'] = $heroRepository;
        $this->container['notificationRepository'] = $notificationRepository;
        $this->container['eventBus'] = $eventBus;

        $this->container['useCases']['createAlbum'] = new CreateAlbumUseCase($albumRepository);
        $this->container['useCases']['listAlbums'] = new ListAlbumsUseCase($albumRepository);
        $this->container['useCases']['createHero'] = new CreateHeroUseCase($heroRepository, $albumRepository, $eventBus);
        $this->container['useCases']['listHeroes'] = new ListHeroesUseCase($heroRepository);
    }

    private function createAlbum(string $name): string
    {
        $useCase = $this->container['useCases']['createAlbum'];
        $response = $useCase->execute(new CreateAlbumRequest($name, null));
        $data = $response->toArray();

        return $data['albumId'];
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
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = "/albums/$albumId/heroes";
        $GLOBALS['mock_php_input'] = json_encode(['nombre' => 'Test Hero', 'imagen' => 'test.jpg', 'contenido' => 'Test content']);

        ob_start();
        \route('POST', "/albums/$albumId/heroes", $this->container);
        $output = ob_get_clean();
        $response = json_decode($output, true);

        $this->assertEquals(201, http_response_code());
        $this->assertEquals('éxito', $response['estado']);
        $this->assertEquals('Test Hero', $response['datos']['nombre']);
    }

    public function testCreateHeroFailsWithMissingData(): void
    {
        $albumId = $this->createAlbum('Test Album');
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = "/albums/$albumId/heroes";
        $GLOBALS['mock_php_input'] = json_encode(['nombre' => 'Test Hero']); // Missing imagen

        ob_start();
        \route('POST', "/albums/$albumId/heroes", $this->container);
        $output = ob_get_clean();
        $response = json_decode($output, true);

        $this->assertEquals(422, http_response_code());
        $this->assertEquals('error', $response['estado']);
        $this->assertEquals('Los campos nombre e imagen son obligatorios.', $response['message']);
    }
}
