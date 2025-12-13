<?php

declare(strict_types=1);

namespace App\Notifications\Infrastructure;

final class NotificationRepository
{
    private const DEFAULT_FILENAME = 'notifications.log';

    private string $filePath;

    public function __construct(string $filePath)
    {
        $this->filePath = $this->resolveFilePath($filePath);
        $this->ensureStorage();
    }

    /**
     * @return array<int, array{date: string, message: string}>
     */
    public function lastNotifications(): array
    {
        if (!is_file($this->filePath) || !is_readable($this->filePath)) {
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
        $this->ensureStorage();
        file_put_contents($this->filePath, '');
    }

    private function resolveFilePath(string $filePath): string
    {
        if (is_dir($filePath)) {
            $directory = rtrim($filePath, DIRECTORY_SEPARATOR);
            return $directory . DIRECTORY_SEPARATOR . self::DEFAULT_FILENAME;
        }

        return $filePath;
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
