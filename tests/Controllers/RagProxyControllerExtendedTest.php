<?php

declare(strict_types=1);

namespace Tests\Controllers;

use PHPUnit\Framework\TestCase;
use App\Controllers\RagProxyController;
use App\Controllers\Http\Request;
use Tests\Support\HttpClientStub;

final class RagProxyControllerExtendedTest extends TestCase
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

    public function test_forward_returns_error_when_body_is_empty(): void
    {
        $_SERVER['MARVEL_RAW_BODY'] = '';
        $_POST = [];

        ob_start();
        $this->controller->forwardHeroesComparison();
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        
        $this->assertSame('error', $response['estado']);
        $this->assertSame('El cuerpo de la petición está vacío', $response['message']);
    }

    public function test_forward_returns_error_when_service_fails(): void
    {
        http_response_code(200); // Reset status
        $_SERVER['MARVEL_RAW_BODY'] = json_encode(['heroIds' => ['1', '2']]);
        
        // Simulate service failure
        $this->client->statusCode = 500;
        $this->client->body = 'Internal Server Error';

        ob_start();
        $this->controller->forwardHeroesComparison();
        $output = ob_get_clean();
        
        // The controller outputs the body directly when status is 500 from service
        $this->assertSame('Internal Server Error', $output);
    }

    protected function tearDown(): void
    {
        unset($_SERVER['MARVEL_RAW_BODY'], $_POST['heroIds']);
    }
}
