<?php

declare(strict_types=1);

namespace Tests\Controllers;

use App\Notifications\Application\ClearNotificationsUseCase;
use App\Notifications\Application\ListNotificationsUseCase;
use App\Notifications\Infrastructure\NotificationRepository;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use App\Controllers\NotificationController;

final class NotificationControllerTest extends TestCase
{
    private string $filePath;
    private NotificationRepository $repository;
    private NotificationController $controller;

    protected function setUp(): void
    {
        $this->filePath = tempnam(sys_get_temp_dir(), 'notifications-') ?: sys_get_temp_dir() . '/notifications-' . uniqid('', true);
        $this->repository = new NotificationRepository($this->filePath);
        $this->controller = new NotificationController(
            new ListNotificationsUseCase($this->repository),
            new ClearNotificationsUseCase($this->repository)
        );
        http_response_code(200);
    }

    protected function tearDown(): void
    {
        if (is_file($this->filePath)) {
            @unlink($this->filePath);
        }
    }

    public function testIndexReturnsLatestNotifications(): void
    {
        file_put_contents($this->filePath, "[2024-01-01] Primer mensaje\nOtro renglón\n");

        $payload = $this->captureJson(fn () => $this->controller->index());

        self::assertSame('éxito', $payload['estado']);
        self::assertSame('Otro renglón', $payload['datos'][0]['message']);
    }

    public function testClearRemovesNotifications(): void
    {
        file_put_contents($this->filePath, "[2024-01-01] Persistente\n");

        $payload = $this->captureJson(fn () => $this->controller->clear());

        self::assertSame('éxito', $payload['estado']);
        self::assertSame('', file_get_contents($this->filePath));
    }

    public function testClearReturnsErrorWhenRepositoryFails(): void
    {
        chmod($this->filePath, 0444);
        set_error_handler(static function (int $severity, string $message): void {
            throw new RuntimeException($message);
        });

        try {
            $payload = $this->captureJson(fn () => $this->controller->clear());
        } finally {
            restore_error_handler();
            chmod($this->filePath, 0644);
        }

        self::assertSame('error', $payload['estado']);
        self::assertSame('No se pudieron limpiar las notificaciones.', $payload['message']);
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
