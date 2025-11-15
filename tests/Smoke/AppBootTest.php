<?php

declare(strict_types=1);

namespace Tests\Smoke;

use PHPUnit\Framework\TestCase;

final class AppBootTest extends TestCase
{
    public function test_app_boots_successfully(): void
    {
        self::assertTrue(true);
    }
}
