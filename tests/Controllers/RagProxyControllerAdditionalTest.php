<?php

declare(strict_types=1);

namespace Tests\Controllers;

use PHPUnit\Framework\TestCase;
use Src\Controllers\RagProxyController;
use Tests\Support\HttpClientStub;

/**
 * Additional coverage tests for RagProxyController
 */
final class RagProxyControllerAdditionalTest extends TestCase
{
    private HttpClientStub $client;
    private RagProxyController $controller;

    protected function setUp(): void
    {
        $this->client = new HttpClientStub();
        $this->controller = new RagProxyController(
            $this->client,
            'http://rag-service/rag/heroes',
            'secret-token'
        );
    }

    protected function tearDown(): void
    {
        unset($_SERVER['MARVEL_RAW_BODY'], $_POST['heroIds']);
    }

    public function testForwardReturnsErrorWhenJsonIsInvalid(): void
    {
        $_SERVER['MARVEL_RAW_BODY'] = 'not valid json {';

        ob_start();
        $this->controller->forwardHeroesComparison();
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        
        $this->assertSame('error', $response['estado']);
        $this->assertStringContainsString('JSON válido', $response['message']);
    }

    public function testForwardReturnsErrorWhenPayloadIsEmptyArray(): void
    {
        $_SERVER['MARVEL_RAW_BODY'] = '{}';

        ob_start();
        $this->controller->forwardHeroesComparison();
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        
        $this->assertSame('error', $response['estado']);
        $this->assertStringContainsString('vacío', $response['message']);
    }

    public function testForwardSuccessWithValidPayload(): void
    {
        $this->client->body = json_encode(['answer' => 'Hero comparison result']);
        $_SERVER['MARVEL_RAW_BODY'] = json_encode([
            'question' => 'Who is stronger?',
            'heroIds' => ['hero-1', 'hero-2'],
        ]);

        ob_start();
        $this->controller->forwardHeroesComparison();
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        
        $this->assertSame('Hero comparison result', $response['answer']);
    }

    public function testForwardHandlesHeroIdsAsNonArray(): void
    {
        $this->client->body = json_encode(['answer' => 'OK']);
        $_SERVER['MARVEL_RAW_BODY'] = json_encode([
            'question' => 'Test',
            'heroIds' => 'not-an-array',
        ]);

        ob_start();
        $this->controller->forwardHeroesComparison();
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        
        $this->assertSame('OK', $response['answer']);
        
        // Verify the converted payload
        $this->assertCount(1, $this->client->requests);
        $sentPayload = json_decode($this->client->requests[0]['payload'], true);
        $this->assertSame([], $sentPayload['heroIds']);
    }

    public function testForwardConvertsHeroIdsToStrings(): void
    {
        $this->client->body = json_encode(['answer' => 'OK']);
        $_SERVER['MARVEL_RAW_BODY'] = json_encode([
            'question' => 'Test',
            'heroIds' => [1, 2, 3],
        ]);

        ob_start();
        $this->controller->forwardHeroesComparison();
        $output = ob_get_clean();
        
        $this->assertCount(1, $this->client->requests);
        $sentPayload = json_decode($this->client->requests[0]['payload'], true);
        
        $this->assertSame(['1', '2', '3'], $sentPayload['heroIds']);
    }

    public function testForwardWithoutInternalToken(): void
    {
        $controllerNoToken = new RagProxyController(
            $this->client,
            'http://rag-service/rag/heroes',
            null
        );

        $this->client->body = json_encode(['answer' => 'OK']);
        $_SERVER['MARVEL_RAW_BODY'] = json_encode([
            'question' => 'Test',
            'heroIds' => ['h1'],
        ]);

        ob_start();
        $controllerNoToken->forwardHeroesComparison();
        $output = ob_get_clean();
        
        $this->assertCount(1, $this->client->requests);
        $headers = $this->client->requests[0]['headers'] ?? [];
        
        // Without token, no signature headers
        $this->assertArrayNotHasKey('X-Internal-Signature', $headers);
    }

    public function testForwardWithEmptyInternalToken(): void
    {
        $controllerEmptyToken = new RagProxyController(
            $this->client,
            'http://rag-service/rag/heroes',
            ''
        );

        $this->client->body = json_encode(['answer' => 'OK']);
        $_SERVER['MARVEL_RAW_BODY'] = json_encode([
            'question' => 'Test',
            'heroIds' => ['h1'],
        ]);

        ob_start();
        $controllerEmptyToken->forwardHeroesComparison();
        $output = ob_get_clean();
        
        $this->assertCount(1, $this->client->requests);
        $headers = $this->client->requests[0]['headers'] ?? [];
        
        // With empty token, no signature headers
        $this->assertArrayNotHasKey('X-Internal-Signature', $headers);
    }

    public function testForwardHandlesMissingQuestion(): void
    {
        $this->client->body = json_encode(['answer' => 'OK']);
        $_SERVER['MARVEL_RAW_BODY'] = json_encode([
            'heroIds' => ['h1', 'h2'],
        ]);

        ob_start();
        $this->controller->forwardHeroesComparison();
        $output = ob_get_clean();
        
        $this->assertCount(1, $this->client->requests);
        $sentPayload = json_decode($this->client->requests[0]['payload'], true);
        
        $this->assertSame('', $sentPayload['question']);
    }

    public function testForwardHandlesMissingHeroIds(): void
    {
        $this->client->body = json_encode(['answer' => 'OK']);
        $_SERVER['MARVEL_RAW_BODY'] = json_encode([
            'question' => 'Who wins?',
        ]);

        ob_start();
        $this->controller->forwardHeroesComparison();
        $output = ob_get_clean();
        
        $this->assertCount(1, $this->client->requests);
        $sentPayload = json_decode($this->client->requests[0]['payload'], true);
        
        $this->assertSame([], $sentPayload['heroIds']);
    }
}
