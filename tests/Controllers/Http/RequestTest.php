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

        self::assertSame([
            'estado' => 'error',
            'message' => 'JSON inválido',
        ], Request::jsonBody());
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

    public function testWantsHtmlReturnsFalseWhenOnlyJson(): void
    {
        $_SERVER['HTTP_ACCEPT'] = 'application/json';

        self::assertFalse(Request::wantsHtml());
    }

    public function testJsonBodyHandlesNestedArrays(): void
    {
        $data = [
            'level1' => [
                'level2' => [
                    'level3' => 'value',
                ],
            ],
        ];
        
        Request::withJsonBody(json_encode($data, JSON_THROW_ON_ERROR));

        self::assertSame($data, Request::jsonBody());
    }

    public function testJsonBodyHandlesSpecialCharacters(): void
    {
        $data = ['message' => 'Hola ñ áéíóú'];
        
        Request::withJsonBody(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        self::assertSame($data, Request::jsonBody());
    }

    public function testJsonBodyHandlesMalformedJson(): void
    {
        Request::withJsonBody('{"incomplete":');

        $result = Request::jsonBody();
        
        self::assertSame('error', $result['estado'] ?? null);
    }

    public function testJsonBodyClearsOverrideAfterUse(): void
    {
        $first = ['call' => '1'];
        $second = ['call' => '2'];
        
        Request::withJsonBody(json_encode($first, JSON_THROW_ON_ERROR));
        $result1 = Request::jsonBody();
        
        Request::withJsonBody(json_encode($second, JSON_THROW_ON_ERROR));
        $result2 = Request::jsonBody();

        self::assertSame($first, $result1);
        self::assertSame($second, $result2);
    }
}

