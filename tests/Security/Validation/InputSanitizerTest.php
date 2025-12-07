<?php

declare(strict_types=1);

namespace Tests\Security\Validation;

use App\Security\Validation\InputSanitizer;
use PHPUnit\Framework\TestCase;

final class InputSanitizerTest extends TestCase
{
    private InputSanitizer $sanitizer;

    protected function setUp(): void
    {
        $this->sanitizer = new InputSanitizer();
    }

    public function test_sanitize_string_trims_whitespace(): void
    {
        $result = $this->sanitizer->sanitizeString('  hello  ');

        $this->assertSame('hello', $result);
    }

    public function test_sanitize_string_collapses_multiple_spaces(): void
    {
        $result = $this->sanitizer->sanitizeString('hello    world');

        $this->assertSame('hello world', $result);
    }

    public function test_sanitize_string_removes_html_tags(): void
    {
        $result = $this->sanitizer->sanitizeString('<p>Hello</p><script>alert(1)</script>');

        $this->assertSame('Helloalert(1)', $result);
    }

    public function test_sanitize_string_respects_max_length(): void
    {
        $longString = str_repeat('a', 2000);

        $result = $this->sanitizer->sanitizeString($longString, 100);

        $this->assertSame(100, mb_strlen($result));
    }

    public function test_sanitize_string_default_max_length_is_1000(): void
    {
        $longString = str_repeat('a', 2000);

        $result = $this->sanitizer->sanitizeString($longString);

        $this->assertSame(1000, mb_strlen($result));
    }

    public function test_sanitize_string_handles_empty_string(): void
    {
        $result = $this->sanitizer->sanitizeString('');

        $this->assertSame('', $result);
    }

    public function test_is_suspicious_returns_false_for_normal_input(): void
    {
        $result = $this->sanitizer->isSuspicious('Hello World');

        $this->assertFalse($result);
    }

    public function test_is_suspicious_detects_script_tag(): void
    {
        $result = $this->sanitizer->isSuspicious('<script>alert(1)</script>');

        $this->assertTrue($result);
    }

    public function test_is_suspicious_detects_onerror(): void
    {
        $result = $this->sanitizer->isSuspicious('<img onerror=alert(1)>');

        $this->assertTrue($result);
    }

    public function test_is_suspicious_detects_onload(): void
    {
        $result = $this->sanitizer->isSuspicious('<body onload=evil()>');

        $this->assertTrue($result);
    }

    public function test_is_suspicious_detects_sql_injection_drop_table(): void
    {
        $result = $this->sanitizer->isSuspicious("'; DROP TABLE users; --");

        $this->assertTrue($result);
    }

    public function test_is_suspicious_detects_sql_injection_union_select(): void
    {
        $result = $this->sanitizer->isSuspicious("1 UNION SELECT * FROM users");

        $this->assertTrue($result);
    }

    public function test_is_suspicious_detects_sql_injection_select_from(): void
    {
        $result = $this->sanitizer->isSuspicious("SELECT * FROM users");

        $this->assertTrue($result);
    }

    public function test_is_suspicious_detects_sql_comment_injection(): void
    {
        $result = $this->sanitizer->isSuspicious("admin\"'-- comment");

        $this->assertTrue($result);
    }

    public function test_is_suspicious_is_case_insensitive(): void
    {
        $result = $this->sanitizer->isSuspicious('<SCRIPT>ALERT(1)</SCRIPT>');

        $this->assertTrue($result);
    }

    public function test_is_suspicious_returns_false_for_partial_patterns(): void
    {
        // "script" alone is not suspicious, only "<script"
        $result = $this->sanitizer->isSuspicious('This is a script for testing');

        $this->assertFalse($result);
    }
}
