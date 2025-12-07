<?php

declare(strict_types=1);

namespace Tests\Shared\Http;

use App\Shared\Http\JsonResponse;
use PHPUnit\Framework\TestCase;

final class JsonResponseTest extends TestCase
{
    public function test_success_returns_correct_structure(): void
    {
        $data = ['key' => 'value'];
        
        $result = JsonResponse::success($data, 200);
        
        $this->assertSame('éxito', $result['estado']);
        $this->assertSame($data, $result['datos']);
    }

    public function test_success_with_empty_data(): void
    {
        $result = JsonResponse::success([], 200);
        
        $this->assertSame('éxito', $result['estado']);
        $this->assertSame([], $result['datos']);
    }

    public function test_error_returns_correct_structure(): void
    {
        $message = 'Something went wrong';
        
        $result = JsonResponse::error($message, 400);
        
        $this->assertSame('error', $result['estado']);
        $this->assertSame($message, $result['message']);
    }

    public function test_error_with_different_status_code(): void
    {
        $result = JsonResponse::error('Not found', 404);
        
        $this->assertSame('error', $result['estado']);
        $this->assertSame('Not found', $result['message']);
    }

    public function test_last_payload_returns_previous_payload(): void
    {
        $data = ['test' => 'data'];
        JsonResponse::success($data, 200);
        
        $lastPayload = JsonResponse::lastPayload();
        
        $this->assertIsArray($lastPayload);
        $this->assertSame('éxito', $lastPayload['estado']);
        $this->assertSame($data, $lastPayload['datos']);
    }

    public function test_last_payload_updates_after_each_call(): void
    {
        JsonResponse::success(['first' => 'call'], 200);
        $firstPayload = JsonResponse::lastPayload();
        
        JsonResponse::error('Second call', 400);
        $secondPayload = JsonResponse::lastPayload();
        
        $this->assertSame('éxito', $firstPayload['estado']);
        $this->assertSame('error', $secondPayload['estado']);
    }

    public function test_success_with_null_data_returns_empty_array(): void
    {
        // When data is null internally, it should be converted to empty array
        $result = JsonResponse::success(null);
        
        $this->assertSame('éxito', $result['estado']);
        $this->assertSame([], $result['datos']);
    }

    public function test_success_with_complex_nested_data(): void
    {
        $data = [
            'heroes' => [
                ['id' => 1, 'name' => 'Spider-Man'],
                ['id' => 2, 'name' => 'Iron Man'],
            ],
            'total' => 2,
            'nested' => ['deep' => ['value' => true]],
        ];
        
        $result = JsonResponse::success($data);
        
        $this->assertSame($data, $result['datos']);
    }

    public function test_error_does_not_include_datos_key(): void
    {
        $result = JsonResponse::error('Error message', 500);
        
        $this->assertArrayNotHasKey('datos', $result);
        $this->assertArrayHasKey('message', $result);
    }
}
