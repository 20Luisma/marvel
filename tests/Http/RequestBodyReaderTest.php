<?php

declare(strict_types=1);

namespace Tests\Http;

use PHPUnit\Framework\TestCase;
use App\Http\RequestBodyReader;

final class RequestBodyReaderTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_SERVER['MARVEL_RAW_BODY']);
        $ref = new \ReflectionClass(RequestBodyReader::class);
        $prop = $ref->getProperty('cachedRaw');
        $prop->setAccessible(true);
        $prop->setValue(null, null);
    }

    public function testRawBodyUsesServerValueAndCaches(): void
    {
        $this->resetCache();
        $_SERVER['MARVEL_RAW_BODY'] = 'first-body';
        self::assertSame('first-body', RequestBodyReader::getRawBody());

        $_SERVER['MARVEL_RAW_BODY'] = 'second-body';
        self::assertSame('first-body', RequestBodyReader::getRawBody(), 'cached value should remain');
    }

    public function testGetJsonArrayThrowsOnEmptyBody(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('El cuerpo de la petición está vacío');
        $_SERVER['MARVEL_RAW_BODY'] = '   ';
        RequestBodyReader::getJsonArray();
    }

    public function testGetJsonArrayThrowsOnInvalidJson(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('El cuerpo no es un JSON válido');
        $_SERVER['MARVEL_RAW_BODY'] = '{bad json}';
        RequestBodyReader::getJsonArray();
    }

    public function testGetJsonArrayReturnsDecodedPayload(): void
    {
        $this->resetCache();
        $_SERVER['MARVEL_RAW_BODY'] = '{"foo":123}';
        self::assertSame(['foo' => 123], RequestBodyReader::getJsonArray());
    }

    private function resetCache(): void
    {
        $ref = new \ReflectionClass(RequestBodyReader::class);
        $prop = $ref->getProperty('cachedRaw');
        $prop->setAccessible(true);
        $prop->setValue(null, null);
    }

    public function testGetRawBodyWithDebugEnabled(): void
    {
        $this->resetCache();
        $_ENV['DEBUG_RAW_BODY'] = 1;
        $_SERVER['MARVEL_RAW_BODY'] = 'debug test body';
        
        $result = RequestBodyReader::getRawBody();
        
        self::assertSame('debug test body', $result);
        
        unset($_ENV['DEBUG_RAW_BODY']);
    }

    public function testGetJsonArrayWithNestedData(): void
    {
        $this->resetCache();
        $data = ['level1' => ['level2' => ['key' => 'value']]];
        $_SERVER['MARVEL_RAW_BODY'] = json_encode($data);
        
        self::assertSame($data, RequestBodyReader::getJsonArray());
    }

    public function testGetRawBodyWithNonStringServerValue(): void
    {
        $this->resetCache();
        $_SERVER['MARVEL_RAW_BODY'] = 12345; // Non-string value
        
        // Should fall back to php://input or empty string
        $result = RequestBodyReader::getRawBody();
        
        self::assertIsString($result);
    }

    public function testGetRawBodyWithNullServerValue(): void
    {
        $this->resetCache();
        $_SERVER['MARVEL_RAW_BODY'] = null;
        
        $result = RequestBodyReader::getRawBody();
        
        self::assertIsString($result);
    }
}
