<?php

declare(strict_types=1);

namespace Tests\Security;

use App\Security\LogSanitizer;
use PHPUnit\Framework\TestCase;

final class LogSanitizerTest extends TestCase
{
    public function testRedactsSensitiveStrings(): void
    {
        $context = [
            'password' => 'SuperSecreta123',
            'token' => 'abcd1234wxyz5678',
            'nested' => [
                'authorization' => 'Bearer verylongtokenvalue123456',
            ],
            'non_sensitive' => 'ok',
        ];

        $sanitized = LogSanitizer::sanitizeContext($context);

        self::assertIsArray($sanitized);
        self::assertArrayHasKey('password', $sanitized);
        self::assertArrayHasKey('token', $sanitized);
        self::assertStringNotContainsString('SuperSecreta123', (string) $sanitized['password']);
        self::assertStringContainsString('redacted', (string) $sanitized['password']);
        self::assertStringNotContainsString('abcd1234wxyz5678', (string) $sanitized['token']);
        self::assertStringContainsString('redacted', (string) $sanitized['token']);

        $nested = $sanitized['nested'] ?? [];
        self::assertIsArray($nested);
        self::assertStringContainsString('redacted', (string) ($nested['authorization'] ?? ''));
        self::assertSame('ok', $sanitized['non_sensitive']);
    }
}
