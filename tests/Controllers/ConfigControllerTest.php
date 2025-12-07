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
        $config = require_once dirname(__DIR__, 2) . '/config/services.php';
        $resolvedConfig = is_array($config) ? $config : ($GLOBALS['__clean_marvel_service_config'] ?? []);
        $controller = new ConfigController(new ServiceUrlProvider($resolvedConfig));

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
        $result = $callable();
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
}
