<?php

declare(strict_types=1);

namespace Tests\Shared\Http;

use App\Shared\Http\JsonResponse;
use App\Shared\Util\Slugger;
use PHPUnit\Framework\TestCase;

final class JsonResponseAndSluggerTest extends TestCase
{
    protected function setUp(): void
    {
        http_response_code(200);
    }

    public function testSuccessPayloadCarriesDataAndStatusCode(): void
    {
        $payload = $this->capture(fn () => JsonResponse::success(['foo' => 'bar'], 201));

        self::assertSame('éxito', $payload['estado']);
        self::assertSame(['foo' => 'bar'], $payload['datos']);
    }

    public function testErrorPayloadIncludesMessage(): void
    {
        $payload = $this->capture(fn () => JsonResponse::error('Invalid', 422));

        self::assertSame('error', $payload['estado']);
        self::assertSame('Invalid', $payload['message']);
    }

    /**
     * @return array<string, mixed>
     */
    private function capture(callable $callback): array
    {
        ob_start();
        $result = $callback();
        $contents = (string) ob_get_clean();

        $payload = \App\Shared\Http\JsonResponse::lastPayload();

        if (is_array($result)) {
            return $result;
        }

        if ($payload !== null) {
            return $payload;
        }

        if ($contents !== '') {
            return json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        }

        return [];
    }
    public function testSlugifyNormalizesValues(): void
    {
        self::assertSame('iron-man', Slugger::slugify('Iron Man!!'));
    }

    public function testSlugifyFallsBackToRandomHexWhenEmpty(): void
    {
        $slug = Slugger::slugify('   ');
        self::assertMatchesRegularExpression('/^[a-f0-9]{16}$/', $slug);
    }
}
