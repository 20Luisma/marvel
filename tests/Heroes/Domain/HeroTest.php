<?php

declare(strict_types=1);

namespace Tests\Heroes\Domain;

use App\Heroes\Domain\Entity\Hero;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class HeroTest extends TestCase
{
    public function testItCreatesHeroWithSlug(): void
    {
        $hero = Hero::create('hero-1', 'album-1', 'Spider Man', 'Friendly neighborhood hero', 'https://example.com/spider.jpg');

        self::assertSame('hero-1', $hero->heroId());
        self::assertSame('spider-man', $hero->slug());
        self::assertSame('Spider Man', $hero->nombre());
        self::assertSame('https://example.com/spider.jpg', $hero->imagen());
    }

    public function testItFailsWithEmptyName(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Hero::create('hero-2', 'album-1', '', 'Content', 'https://example.com/image.jpg');
    }

    public function testItFailsWithEmptyImage(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Hero::create('hero-3', 'album-1', 'Iron Man', 'Genius billionaire', '');
    }
}
