<?php

declare(strict_types=1);

namespace Tests\Controllers;

use PHPUnit\Framework\TestCase;
use Src\Controllers\AdminController;
use Tests\Doubles\SeedHeroesServiceStub;

final class AdminControllerTest extends TestCase
{
    private SeedHeroesServiceStub $seedService;
    private AdminController $controller;

    protected function setUp(): void
    {
        $this->seedService = new SeedHeroesServiceStub();
        $this->controller = new AdminController($this->seedService);
        http_response_code(200);
        $_GET = [];
    }

    public function testSeedAllRequiresDevKey(): void
    {
        $_GET['key'] = 'wrong';

        $payload = $this->captureJson(fn () => $this->controller->seedAll());

        self::assertSame('error', $payload['estado']);
        self::assertSame(0, $this->seedService->seedForceCalls);
    }

    public function testSeedAllReturnsCreatedCount(): void
    {
        $_GET['key'] = 'dev';
        $this->seedService->createdCount = 7;

        $payload = $this->captureJson(fn () => $this->controller->seedAll());

        self::assertSame('éxito', $payload['estado']);
        self::assertSame(['created' => 7], $payload['datos']);
        self::assertSame(1, $this->seedService->seedForceCalls);
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
