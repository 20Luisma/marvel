<?php

declare(strict_types=1);

namespace Tests\Activities\Domain;

use App\Activities\Domain\ActivityScope;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ActivityScopeExceptionsTest extends TestCase
{
    public function testAssertValidRejectsEmptyScope(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Scope de actividad no soportado');

        ActivityScope::assertValid('   ');
    }

    public function testAssertValidRejectsUnsupportedScope(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Scope de actividad no soportado');

        ActivityScope::assertValid('payments');
    }

    public function testFileNameRejectsUnsupportedScope(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Scope de actividad no soportado');

        ActivityScope::fileName('not-valid');
    }

    public function testNormalizeContextRejectsNullContextForHeroesScope(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('El contexto es obligatorio para el scope de héroes.');

        ActivityScope::normalizeContext(ActivityScope::HEROES, null);
    }

    public function testNormalizeContextRejectsNonScalarContextForHeroesScope(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('El contexto es obligatorio para el scope de héroes.');

        ActivityScope::normalizeContext(ActivityScope::HEROES, ['hero-1']);
    }
}

