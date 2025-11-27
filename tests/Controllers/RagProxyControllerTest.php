<?php

declare(strict_types=1);

namespace Tests\Controllers;

use App\Shared\Infrastructure\Http\HttpResponse;
use PHPUnit\Framework\TestCase;
use Src\Controllers\RagProxyController;
use Tests\Support\HttpClientStub;

final class RagProxyControllerTest extends TestCase
{
    public function testForwardsPayloadWithInternalToken(): void
    {
        $client = new HttpClientStub();
        $client->body = json_encode(['answer' => 'ok']);

        $controller = new RagProxyController($client, 'http://rag-service/rag/heroes', 'secret-token');
        $GLOBALS['mock_php_input'] = json_encode(['heroIds' => ['a', 'b']]);

        ob_start();
        $controller->forwardHeroesComparison();
        ob_end_clean();

        self::assertCount(1, $client->requests);
        self::assertSame('secret-token', $client->requests[0]['headers']['X-Internal-Token'] ?? null);
    }
}
