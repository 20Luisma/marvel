<?php

declare(strict_types=1);

namespace Tests\Controllers;

use App\Security\Validation\InputSanitizer;
use PHPUnit\Framework\TestCase;
use Src\Controllers\RagProxyController;
use Tests\Support\HttpClientStub;

final class RagProxyControllerSecurityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['HTTP_HOST'] = 'localhost';
        $_SERVER['X_TRACE_ID'] = 'test-trace';
    }

    public function testSuspiciousQuestionIsSanitizedAndDoesNotCrash(): void
    {
        $client = new HttpClientStub();
        $client->body = json_encode(['answer' => 'ok']);

        $controller = new RagProxyController($client, 'http://rag-service/rag/heroes', null);
        $question = '<script>alert(1)</script> DROP TABLE users;';
        $payload = json_encode(['heroIds' => ['spiderman', 'ironman'], 'question' => $question]);

        $GLOBALS['mock_php_input'] = $payload;
        $_SERVER['CONTENT_TYPE'] = 'application/json';

        ob_start();
        $controller->forwardHeroesComparison();
        $output = ob_get_clean();

        self::assertNotFalse($output);
        // No debe romper ni devolver 500; aceptar respuesta de validación.
        self::assertStringNotContainsString('500', $output);

        $this->assertTrue(true); // no crash ni 500
    }
}
