<?php

declare(strict_types=1);

namespace Tests\Shared\Infrastructure;

use App\Shared\Infrastructure\Http\HttpResponse;
use PHPUnit\Framework\TestCase;

final class HttpResponseTest extends TestCase
{
    public function testIsSuccessfulReturnsTrueFor2xx(): void
    {
        $response = new HttpResponse(201, '{"ok":true}');
        self::assertTrue($response->isSuccessful());
    }

    public function testIsSuccessfulReturnsFalseForNon2xx(): void
    {
        $response = new HttpResponse(404, 'not found');
        self::assertFalse($response->isSuccessful());
    }
}
