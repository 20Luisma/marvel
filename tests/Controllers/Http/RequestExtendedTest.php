<?php

declare(strict_types=1);

namespace Tests\Controllers\Http;

use PHPUnit\Framework\TestCase;
use Src\Controllers\Http\Request;

final class RequestExtendedTest extends TestCase
{
    protected function tearDown(): void
    {
        // Reset any overrides
        Request::withJsonBody('{}');
        Request::jsonBody(); // Consume the override
    }

    public function test_wants_html_returns_true_when_accept_header_contains_text_html(): void
    {
        $original = $_SERVER['HTTP_ACCEPT'] ?? '';
        
        try {
            $_SERVER['HTTP_ACCEPT'] = 'text/html,application/xhtml+xml,application/xml;q=0.9';
            
            $result = Request::wantsHtml();
            
            $this->assertTrue($result);
        } finally {
            $_SERVER['HTTP_ACCEPT'] = $original;
        }
    }

    public function test_wants_html_returns_false_when_accept_header_is_json(): void
    {
        $original = $_SERVER['HTTP_ACCEPT'] ?? '';
        
        try {
            $_SERVER['HTTP_ACCEPT'] = 'application/json';
            
            $result = Request::wantsHtml();
            
            $this->assertFalse($result);
        } finally {
            $_SERVER['HTTP_ACCEPT'] = $original;
        }
    }

    public function test_wants_html_returns_false_when_accept_header_is_empty(): void
    {
        $original = $_SERVER['HTTP_ACCEPT'] ?? '';
        
        try {
            $_SERVER['HTTP_ACCEPT'] = '';
            
            $result = Request::wantsHtml();
            
            $this->assertFalse($result);
        } finally {
            $_SERVER['HTTP_ACCEPT'] = $original;
        }
    }

    public function test_wants_html_returns_false_when_accept_header_is_missing(): void
    {
        $original = $_SERVER['HTTP_ACCEPT'] ?? null;
        
        try {
            unset($_SERVER['HTTP_ACCEPT']);
            
            $result = Request::wantsHtml();
            
            $this->assertFalse($result);
        } finally {
            if ($original !== null) {
                $_SERVER['HTTP_ACCEPT'] = $original;
            }
        }
    }

    public function test_json_body_parses_valid_json_from_override(): void
    {
        $expectedData = ['key' => 'value', 'number' => 123];
        Request::withJsonBody(json_encode($expectedData));
        
        $result = Request::jsonBody();
        
        $this->assertSame($expectedData, $result);
    }

    public function test_json_body_returns_error_for_invalid_json(): void
    {
        Request::withJsonBody('{ invalid json }');
        
        $result = Request::jsonBody();
        
        // In CLI mode, it returns an error array instead of exiting
        $this->assertArrayHasKey('estado', $result);
        $this->assertSame('error', $result['estado']);
    }
}
