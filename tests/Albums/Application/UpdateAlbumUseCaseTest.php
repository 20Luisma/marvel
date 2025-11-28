<?php

declare(strict_types=1);

namespace Tests\Albums\Application;

use App\Albums\Application\DTO\UpdateAlbumRequest;
use App\Albums\Application\UseCase\UpdateAlbumUseCase;
use App\Albums\Domain\Entity\Album;
use App\Albums\Domain\Event\AlbumUpdated;
use App\Albums\Domain\Repository\AlbumRepository;
use App\Shared\Domain\Bus\DomainEvent;
use App\Shared\Domain\Bus\EventBus;
use App\Shared\Domain\Event\DomainEventHandler;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class UpdateAlbumUseCaseTest extends TestCase
{
    // @todo Inject a controllable clock into Album to assert timestamp changes deterministically.
    public function testItRenamesAlbumAndPublishesEvent(): void
    {
        $album = Album::create('album-1', 'Marvel');
        $repository = new InMemoryAlbumRepositoryStub($album);
        $eventBus = new RecordingEventBus();

        $useCase = new UpdateAlbumUseCase($repository, $eventBus);

        $response = $useCase->execute(new UpdateAlbumRequest('album-1', 'Marvel Studios', null, false));

        self::assertSame('Marvel Studios', $repository->stored()->nombre());
        self::assertSame('Marvel Studios', $response->toArray()['nombre']);
        self::assertSame(1, $repository->saveCalls());
        self::assertCount(1, $eventBus->events);
        self::assertInstanceOf(AlbumUpdated::class, $eventBus->events[0]);
        self::assertSame('album-1', $eventBus->events[0]->aggregateId());
        self::assertSame('Marvel Studios', $eventBus->events[0]->nombre());
    }

    public function testItRemovesCoverWhenEmptyStringProvided(): void
    {
        $album = Album::create('album-2', 'Guardians', 'https://example.com/cover.jpg');
        $repository = new InMemoryAlbumRepositoryStub($album);
        $eventBus = new RecordingEventBus();

        $useCase = new UpdateAlbumUseCase($repository, $eventBus);

        $response = $useCase->execute(new UpdateAlbumRequest('album-2', null, '   ', true));

        self::assertNull($repository->stored()->coverImage());
        self::assertNull($response->toArray()['coverImage']);
        self::assertCount(1, $eventBus->events);
        self::assertInstanceOf(AlbumUpdated::class, $eventBus->events[0]);
        self::assertNull($eventBus->events[0]->coverImage());
    }

    public function testItDoesNotPersistOrPublishWhenNoChanges(): void
    {
        $album = Album::create('album-3', 'Avengers', 'https://example.com/cover.jpg');
        $repository = new InMemoryAlbumRepositoryStub($album);
        $eventBus = new RecordingEventBus();

        $useCase = new UpdateAlbumUseCase($repository, $eventBus);

        $response = $useCase->execute(new UpdateAlbumRequest('album-3', 'Avengers', null, false));

        self::assertSame('Avengers', $response->toArray()['nombre']);
        self::assertSame(0, $repository->saveCalls());
        self::assertSame([], $eventBus->events);
    }

    public function testItFailsWhenAlbumDoesNotExist(): void
    {
        $repository = new InMemoryAlbumRepositoryStub(null);
        $eventBus = new RecordingEventBus();
        $useCase = new UpdateAlbumUseCase($repository, $eventBus);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Álbum no encontrado.');

        $useCase->execute(new UpdateAlbumRequest('missing', 'Name', null, false));
    }
}

/**
 * @internal
 */
final class InMemoryAlbumRepositoryStub implements AlbumRepository
{
    private ?Album $album;
    private int $saveCalls = 0;

    public function __construct(?Album $album)
    {
        $this->album = $album;
    }

    public function save(Album $album): void
    {
        $this->album = $album;
        $this->saveCalls++;
    }

    /**
     * @return array<int, Album>
     */
    public function all(): array
    {
        return $this->album !== null ? [$this->album] : [];
    }

    public function find(string $albumId): ?Album
    {
        if ($this->album !== null && $this->album->albumId() === $albumId) {
            return $this->album;
        }

        return null;
    }

    public function delete(string $albumId): void
    {
        if ($this->album !== null && $this->album->albumId() === $albumId) {
            $this->album = null;
        }
    }

    public function stored(): ?Album
    {
        return $this->album;
    }

    public function saveCalls(): int
    {
        return $this->saveCalls;
    }
}

/**
 * @internal
 */
final class RecordingEventBus implements EventBus
{
    /**
     * @var array<int, DomainEvent>
     */
    public array $events = [];

    public function subscribe(DomainEventHandler $handler): void
    {
        // No-op for tests
    }

    public function publish(array $events): void
    {
        $this->events = array_merge($this->events, $events);
    }
}
