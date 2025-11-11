<?php

declare(strict_types=1);

namespace Tests\Heroes\Domain;

use App\Heroes\Domain\Entity\Hero;
use App\Heroes\Domain\Event\HeroCreated;
use PHPUnit\Framework\TestCase;

final class HeroCreatedEventTest extends TestCase
{
    public function testForHeroBuildsEventWithHeroData(): void
    {
        $hero = Hero::create('hero-1', 'album-1', 'Iron Man', 'Armadura', 'https://example.com/iron.jpg');
        $event = HeroCreated::forHero($hero, 'Avengers');

        self::assertSame('hero.created', HeroCreated::eventName());
        self::assertSame('album-1', $event->albumId());
        self::assertSame('Avengers', $event->albumName());

        $payload = $event->toPrimitives();
        self::assertSame('hero-1', $payload['heroId']);
        self::assertSame('Iron Man', $payload['name']);
    }

    public function testFromPrimitivesRestoresEvent(): void
    {
        $occurredOn = new \DateTimeImmutable('-1 minute');
        $event = HeroCreated::fromPrimitives(
            'hero-2',
            [
                'albumId' => 'album-5',
                'albumName' => 'Guardians',
                'name' => 'Rocket',
                'slug' => 'rocket',
                'image' => 'https://example.com/rocket.jpg',
            ],
            'evt-123',
            $occurredOn
        );

        self::assertSame('rocket', $event->slug());
        self::assertSame('https://example.com/rocket.jpg', $event->image());
    }
}
