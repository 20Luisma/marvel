<?php

declare(strict_types=1);

namespace Tests\Activities\Application;

use App\Activities\Application\DTO\RecordActivityRequest;
use App\Activities\Application\UseCase\RecordActivityUseCase;
use App\Activities\Domain\ActivityScope;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Tests\Doubles\SpyActivityLogRepository;

final class RecordActivityUseCaseExceptionsTest extends TestCase
{
    public function testItDoesNotAppendWhenScopeIsInvalid(): void
    {
        $repository = new SpyActivityLogRepository();
        $useCase = new RecordActivityUseCase($repository);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Scope de actividad no soportado');

        try {
            $useCase->execute(new RecordActivityRequest('not-valid', null, 'Created', 'Algo'));
        } finally {
            self::assertSame(0, $repository->appendCalls);
        }
    }

    public function testItDoesNotAppendWhenHeroesScopeHasMissingContext(): void
    {
        $repository = new SpyActivityLogRepository();
        $useCase = new RecordActivityUseCase($repository);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('El contexto es obligatorio para el scope de héroes.');

        try {
            $useCase->execute(new RecordActivityRequest(ActivityScope::HEROES, null, 'Created', 'Nuevo héroe'));
        } finally {
            self::assertSame(0, $repository->appendCalls);
        }
    }

    public function testItDoesNotAppendWhenActionIsEmpty(): void
    {
        $repository = new SpyActivityLogRepository();
        $useCase = new RecordActivityUseCase($repository);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('La acción de actividad no puede estar vacía.');

        try {
            $useCase->execute(new RecordActivityRequest(ActivityScope::ALBUMS, null, '   ', 'Título'));
        } finally {
            self::assertSame(0, $repository->appendCalls);
        }
    }

    public function testItDoesNotAppendWhenTitleIsEmpty(): void
    {
        $repository = new SpyActivityLogRepository();
        $useCase = new RecordActivityUseCase($repository);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('El título de actividad no puede estar vacío.');

        try {
            $useCase->execute(new RecordActivityRequest(ActivityScope::ALBUMS, null, 'Updated', ''));
        } finally {
            self::assertSame(0, $repository->appendCalls);
        }
    }
}
