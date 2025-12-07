<?php

declare(strict_types=1);

namespace Tests\Notifications\Infrastructure;

use App\Notifications\Infrastructure\FileNotificationSender;
use PHPUnit\Framework\TestCase;

final class FileNotificationSenderTest extends TestCase
{
    private string $testFilePath;

    protected function setUp(): void
    {
        $this->testFilePath = sys_get_temp_dir() . '/notifications_test_' . uniqid() . '/notifications.log';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->testFilePath)) {
            unlink($this->testFilePath);
            rmdir(dirname($this->testFilePath));
        }
    }

    public function test_constructor_creates_directory_if_not_exists(): void
    {
        new FileNotificationSender($this->testFilePath);

        $this->assertDirectoryExists(dirname($this->testFilePath));
    }

    public function test_constructor_creates_file_if_not_exists(): void
    {
        new FileNotificationSender($this->testFilePath);

        $this->assertFileExists($this->testFilePath);
    }

    public function test_send_appends_message_to_file(): void
    {
        $sender = new FileNotificationSender($this->testFilePath);

        $sender->send('Test notification message');

        $contents = file_get_contents($this->testFilePath);
        $this->assertStringContainsString('Test notification message', $contents);
    }

    public function test_send_includes_timestamp(): void
    {
        $sender = new FileNotificationSender($this->testFilePath);

        $sender->send('Test message');

        $contents = file_get_contents($this->testFilePath);
        // Timestamp format: [2025-01-01T12:00:00+00:00]
        $this->assertMatchesRegularExpression('/\[\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $contents);
    }

    public function test_send_appends_multiple_messages(): void
    {
        $sender = new FileNotificationSender($this->testFilePath);

        $sender->send('First message');
        $sender->send('Second message');

        $contents = file_get_contents($this->testFilePath);
        $this->assertStringContainsString('First message', $contents);
        $this->assertStringContainsString('Second message', $contents);
    }

    public function test_constructor_handles_existing_directory(): void
    {
        // Create directory first
        mkdir(dirname($this->testFilePath), 0777, true);

        $sender = new FileNotificationSender($this->testFilePath);

        $this->assertFileExists($this->testFilePath);
    }

    public function test_constructor_handles_existing_file(): void
    {
        // Create directory and file first
        mkdir(dirname($this->testFilePath), 0777, true);
        file_put_contents($this->testFilePath, 'Existing content');

        $sender = new FileNotificationSender($this->testFilePath);
        $sender->send('New message');

        $contents = file_get_contents($this->testFilePath);
        $this->assertStringContainsString('Existing content', $contents);
        $this->assertStringContainsString('New message', $contents);
    }
}
