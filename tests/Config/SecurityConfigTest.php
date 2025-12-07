<?php

declare(strict_types=1);

namespace Tests\Config;

use App\Config\SecurityConfig;
use PHPUnit\Framework\TestCase;

final class SecurityConfigTest extends TestCase
{
    private SecurityConfig $config;

    protected function setUp(): void
    {
        $this->config = new SecurityConfig();
    }

    public function test_get_admin_email_returns_default_when_env_not_set(): void
    {
        // Clear environment variable if set
        putenv('ADMIN_EMAIL');
        
        $email = $this->config->getAdminEmail();
        
        $this->assertSame('seguridadmarvel@gmail.com', $email);
    }

    public function test_get_admin_email_returns_env_value_when_set(): void
    {
        $originalEnv = getenv('ADMIN_EMAIL');
        
        try {
            putenv('ADMIN_EMAIL=custom@example.com');
            
            $email = $this->config->getAdminEmail();
            
            $this->assertSame('custom@example.com', $email);
        } finally {
            // Restore original value
            if ($originalEnv !== false) {
                putenv('ADMIN_EMAIL=' . $originalEnv);
            } else {
                putenv('ADMIN_EMAIL');
            }
        }
    }

    public function test_get_admin_password_hash_returns_hash_from_env_when_set(): void
    {
        $originalEnv = getenv('ADMIN_PASSWORD_HASH');
        
        try {
            $expectedHash = '$2y$12$testhashmockedhashvalue';
            putenv('ADMIN_PASSWORD_HASH=' . $expectedHash);
            
            $hash = $this->config->getAdminPasswordHash();
            
            $this->assertSame($expectedHash, $hash);
        } finally {
            // Restore original value
            if ($originalEnv !== false) {
                putenv('ADMIN_PASSWORD_HASH=' . $originalEnv);
            } else {
                putenv('ADMIN_PASSWORD_HASH');
            }
        }
    }

    public function test_get_admin_password_hash_returns_fallback_hash_when_env_not_set(): void
    {
        $originalEnv = getenv('ADMIN_PASSWORD_HASH');
        
        try {
            putenv('ADMIN_PASSWORD_HASH');
            unset($_ENV['ADMIN_PASSWORD_HASH']);
            
            $hash = $this->config->getAdminPasswordHash();
            
            // Should return a valid bcrypt hash
            $this->assertStringStartsWith('$2y$', $hash);
            $this->assertTrue(password_verify('seguridadmarvel2025', $hash));
        } finally {
            // Restore original value
            if ($originalEnv !== false) {
                putenv('ADMIN_PASSWORD_HASH=' . $originalEnv);
            }
        }
    }

    public function test_get_internal_api_key_returns_null_when_env_not_set(): void
    {
        $originalEnv = getenv('INTERNAL_API_KEY');
        
        try {
            putenv('INTERNAL_API_KEY');
            
            $key = $this->config->getInternalApiKey();
            
            $this->assertNull($key);
        } finally {
            // Restore original value
            if ($originalEnv !== false) {
                putenv('INTERNAL_API_KEY=' . $originalEnv);
            }
        }
    }

    public function test_get_internal_api_key_returns_value_when_env_set(): void
    {
        $originalEnv = getenv('INTERNAL_API_KEY');
        
        try {
            putenv('INTERNAL_API_KEY=my-secret-api-key');
            
            $key = $this->config->getInternalApiKey();
            
            $this->assertSame('my-secret-api-key', $key);
        } finally {
            // Restore original value
            if ($originalEnv !== false) {
                putenv('INTERNAL_API_KEY=' . $originalEnv);
            } else {
                putenv('INTERNAL_API_KEY');
            }
        }
    }
}
