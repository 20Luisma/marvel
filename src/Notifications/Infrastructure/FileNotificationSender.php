<?php

declare(strict_types=1);

namespace App\Notifications\Infrastructure;

use App\Notifications\Domain\Service\NotificationSender;
use DateTimeImmutable;

final class FileNotificationSender implements NotificationSender
{
    public function __construct(private readonly string $filePath)
    {
        $this->ensureStorage();
    }

    public function send(string $message): void
    {
        $timestamp = (new DateTimeImmutable())->format(DATE_ATOM);
        $line = sprintf('[%s] %s%s', $timestamp, $message, PHP_EOL);
        file_put_contents($this->filePath, $line, FILE_APPEND | LOCK_EX);
    }

    private function ensureStorage(): void
    {
        $directory = dirname($this->filePath);

        if ($directory !== '' && !is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        if (!is_file($this->filePath)) {
            touch($this->filePath);
        }
    }
}
