<?php

declare(strict_types=1);

namespace Tests\Security\Validation;

use App\Security\Validation\JsonValidator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class JsonValidatorTest extends TestCase
{
    private JsonValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new JsonValidator();
    }

    public function test_validate_passes_with_valid_string_field(): void
    {
        $payload = ['nombre' => 'Test Album'];
        $schema = ['nombre' => ['type' => 'string', 'required' => true]];

        // Should not throw
        $this->validator->validate($payload, $schema);
        $this->assertTrue(true);
    }

    public function test_validate_throws_on_empty_payload_when_not_allowed(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('El payload es obligatorio.');

        $this->validator->validate([], ['nombre' => ['type' => 'string']], allowEmpty: false);
    }

    public function test_validate_passes_with_empty_payload_when_allowed(): void
    {
        // Should not throw
        $this->validator->validate([], ['nombre' => ['type' => 'string', 'required' => false]], allowEmpty: true);
        $this->assertTrue(true);
    }

    public function test_validate_throws_on_missing_required_field(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('El campo nombre es obligatorio.');

        $payload = ['other' => 'value'];
        $schema = ['nombre' => ['type' => 'string', 'required' => true]];

        $this->validator->validate($payload, $schema);
    }

    public function test_validate_passes_when_optional_field_is_missing(): void
    {
        $payload = ['required_field' => 'value'];
        $schema = [
            'required_field' => ['type' => 'string', 'required' => true],
            'optional_field' => ['type' => 'string', 'required' => false],
        ];

        // Should not throw
        $this->validator->validate($payload, $schema);
        $this->assertTrue(true);
    }

    public function test_validate_throws_on_invalid_type_for_string(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Tipo inválido para nombre.');

        $payload = ['nombre' => 123]; // int instead of string
        $schema = ['nombre' => ['type' => 'string', 'required' => true]];

        $this->validator->validate($payload, $schema);
    }

    public function test_validate_passes_with_valid_array_type(): void
    {
        $payload = ['items' => ['a', 'b', 'c']];
        $schema = ['items' => ['type' => 'array', 'required' => true]];

        // Should not throw
        $this->validator->validate($payload, $schema);
        $this->assertTrue(true);
    }

    public function test_validate_throws_on_invalid_array_type(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Tipo inválido para items.');

        $payload = ['items' => 'not-an-array'];
        $schema = ['items' => ['type' => 'array', 'required' => true]];

        $this->validator->validate($payload, $schema);
    }

    public function test_validate_passes_with_valid_int_type(): void
    {
        $payload = ['count' => 42];
        $schema = ['count' => ['type' => 'int', 'required' => true]];

        // Should not throw
        $this->validator->validate($payload, $schema);
        $this->assertTrue(true);
    }

    public function test_validate_throws_on_invalid_int_type(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Tipo inválido para count.');

        $payload = ['count' => 'not-an-int'];
        $schema = ['count' => ['type' => 'int', 'required' => true]];

        $this->validator->validate($payload, $schema);
    }

    public function test_validate_passes_with_valid_float_type(): void
    {
        $payload = ['price' => 19.99];
        $schema = ['price' => ['type' => 'float', 'required' => true]];

        // Should not throw
        $this->validator->validate($payload, $schema);
        $this->assertTrue(true);
    }

    public function test_validate_passes_with_int_as_float_type(): void
    {
        // integers should be accepted as floats
        $payload = ['price' => 20];
        $schema = ['price' => ['type' => 'float', 'required' => true]];

        // Should not throw
        $this->validator->validate($payload, $schema);
        $this->assertTrue(true);
    }

    public function test_validate_passes_with_valid_bool_type(): void
    {
        $payload = ['active' => true];
        $schema = ['active' => ['type' => 'bool', 'required' => true]];

        // Should not throw
        $this->validator->validate($payload, $schema);
        $this->assertTrue(true);
    }

    public function test_validate_throws_on_invalid_bool_type(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Tipo inválido para active.');

        $payload = ['active' => 'true']; // string instead of bool
        $schema = ['active' => ['type' => 'bool', 'required' => true]];

        $this->validator->validate($payload, $schema);
    }

    public function test_validate_throws_on_unknown_type(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Tipo inválido para field.');

        $payload = ['field' => 'value'];
        $schema = ['field' => ['type' => 'unknown_type', 'required' => true]];

        $this->validator->validate($payload, $schema);
    }
}
