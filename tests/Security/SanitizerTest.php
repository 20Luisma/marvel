<?php

declare(strict_types=1);

namespace Tests\Security;

use App\Security\Validation\InputSanitizer;
use PHPUnit\Framework\TestCase;

final class SanitizerTest extends TestCase
{
    public function testRemovesScriptAndPhpTags(): void
    {
        $sanitizer = new InputSanitizer();
        $result = $sanitizer->sanitizeString('<script>alert(1)</script><?php echo "x"; ?>Hello');

        self::assertStringNotContainsString('<script>', $result);
        self::assertStringContainsString('Hello', $result);
    }

    public function testTruncatesLongStrings(): void
    {
        $sanitizer = new InputSanitizer();
        $long = str_repeat('a', 2500);
        $result = $sanitizer->sanitizeString($long);

        self::assertLessThanOrEqual(2000, mb_strlen($result));
    }
}
