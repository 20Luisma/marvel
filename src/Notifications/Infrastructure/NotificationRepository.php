<?php

declare(strict_types=1);

namespace App\Notifications\Infrastructure;

final class NotificationRepository
{
    public function __construct(private readonly string $filePath)
    {
        $this->ensureStorage();
    }

    /**
     * @return array<int, array{date: string, message: string}>
     */
    public function lastNotifications(): array
    {
        if (!file_exists($this->filePath)) {
            return [];
        }

        $lines = file($this->filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if ($lines === false) {
            return [];
        }

        $lines = array_slice($lines, -20);
        $lines = array_reverse($lines);

        return array_map(static function (string $line): array {
            if (preg_match('/^\[(.+)\] (.+)$/', $line, $matches) === 1) {
                return ['date' => $matches[1], 'message' => $matches[2]];
            }

            return ['date' => '', 'message' => $line];
        }, $lines);
    }

    public function clear(): void
    {
        file_put_contents($this->filePath, '');
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
