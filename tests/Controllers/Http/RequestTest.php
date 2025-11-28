<?php

declare(strict_types=1);

namespace Tests\Controllers\Http;

use PHPUnit\Framework\TestCase;
use Src\Controllers\Http\Request;

final class RequestTest extends TestCase
{
    protected function tearDown(): void
    {
        Request::withJsonBody('');
        unset($_SERVER['HTTP_ACCEPT']);
        parent::tearDown();
    }

    public function testJsonBodyReturnsArrayWhenPayloadIsValid(): void
    {
        Request::withJsonBody(json_encode(['foo' => 'bar'], JSON_THROW_ON_ERROR));

        self::assertSame(['foo' => 'bar'], Request::jsonBody());
    }

    public function testJsonBodyReturnsEmptyArrayWhenPayloadIsEmpty(): void
    {
        Request::withJsonBody('   ');

        self::assertSame([], Request::jsonBody());
    }

    public function testWantsHtmlReturnsTrueWhenHeaderContainsHtml(): void
    {
        $_SERVER['HTTP_ACCEPT'] = 'application/json, text/html; charset=utf-8';

        self::assertTrue(Request::wantsHtml());
    }

    public function testWantsHtmlReturnsFalseWhenHeaderMissing(): void
    {
        unset($_SERVER['HTTP_ACCEPT']);

        self::assertFalse(Request::wantsHtml());
    }
}
