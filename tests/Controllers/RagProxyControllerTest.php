<?php

declare(strict_types=1);

namespace Tests\Controllers;

use App\Shared\Infrastructure\Http\HttpResponse;
use App\Shared\Infrastructure\Security\InternalRequestSigner;
use PHPUnit\Framework\TestCase;
use App\Controllers\RagProxyController;
use App\Controllers\Http\Request;
use Tests\Support\HttpClientStub;

final class RagProxyControllerTest extends TestCase
{
    public function testForwardsPayloadWithHmacHeaders(): void
    {
        $client = new HttpClientStub();
        $client->body = json_encode(['answer' => 'ok']);

        $controller = new RagProxyController($client, 'http://rag-service/rag/heroes', 'secret-token');
        $_SERVER['MARVEL_RAW_BODY'] = json_encode(['heroIds' => ['a', 'b']]);

        ob_start();
        $controller->forwardHeroesComparison();
        ob_end_clean();
        unset($_SERVER['MARVEL_RAW_BODY']);

        self::assertCount(1, $client->requests);
        $headers = $client->requests[0]['headers'] ?? [];

        self::assertArrayHasKey('X-Internal-Signature', $headers);
        self::assertArrayHasKey('X-Internal-Timestamp', $headers);

        $timestamp = (int) $headers['X-Internal-Timestamp'];
        $signer = new InternalRequestSigner('secret-token');
        $expectedSignature = $signer->computeSignature('POST', '/rag/heroes', (string) $client->requests[0]['payload'], $timestamp);

        self::assertSame($expectedSignature, $headers['X-Internal-Signature']);
    }
}
