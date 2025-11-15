<?php

declare(strict_types=1);

namespace Tests\Controllers;

use App\Config\ServiceUrlProvider;
use PHPUnit\Framework\TestCase;
use Src\Controllers\ConfigController;

final class ConfigControllerTest extends TestCase
{
    public function testServicesReturnsConfigurationForHost(): void
    {
        $_SERVER['HTTP_HOST'] = 'localhost:8080';
        $config = require dirname(__DIR__, 2) . '/config/services.php';
        $controller = new ConfigController(new ServiceUrlProvider($config));

        $payload = $this->captureJson(fn () => $controller->services());

        self::assertSame('éxito', $payload['estado']);
        self::assertSame('local', $payload['datos']['environment']['mode']);
        self::assertSame('http://localhost:8080', $payload['datos']['services']['app']['baseUrl']);
    }

    /**
     * @return array<string, mixed>
     */
    private function captureJson(callable $callable): array
    {
        ob_start();
        $callable();
        $contents = (string) ob_get_clean();

        return json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
    }
}
