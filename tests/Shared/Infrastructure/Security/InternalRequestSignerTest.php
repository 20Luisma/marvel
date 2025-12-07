<?php

declare(strict_types=1);

namespace Tests\Shared\Infrastructure\Security;

use App\Shared\Infrastructure\Security\InternalRequestSigner;
use PHPUnit\Framework\TestCase;

final class InternalRequestSignerTest extends TestCase
{
    private const SHARED_SECRET = 'test-secret-key';
    private InternalRequestSigner $signer;

    protected function setUp(): void
    {
        $this->signer = new InternalRequestSigner(self::SHARED_SECRET, 'test-caller');
    }

    public function test_sign_returns_required_headers(): void
    {
        $headers = $this->signer->sign('POST', '/api/test', '{"data":"value"}');

        $this->assertArrayHasKey('X-Internal-Signature', $headers);
        $this->assertArrayHasKey('X-Internal-Timestamp', $headers);
        $this->assertArrayHasKey('X-Internal-Caller', $headers);
        $this->assertSame('test-caller', $headers['X-Internal-Caller']);
    }

    public function test_sign_uses_provided_timestamp(): void
    {
        $timestamp = 1700000000;
        $headers = $this->signer->sign('GET', '/api/resource', '', $timestamp);

        $this->assertSame((string) $timestamp, $headers['X-Internal-Timestamp']);
    }

    public function test_sign_merges_extra_headers(): void
    {
        $headers = $this->signer->sign('POST', '/api/test', '', null, ['X-Custom' => 'value']);

        $this->assertArrayHasKey('X-Custom', $headers);
        $this->assertSame('value', $headers['X-Custom']);
    }

    public function test_compute_signature_returns_consistent_hash(): void
    {
        $timestamp = 1700000000;
        $sig1 = $this->signer->computeSignature('POST', '/api/test', 'body', $timestamp);
        $sig2 = $this->signer->computeSignature('POST', '/api/test', 'body', $timestamp);

        $this->assertSame($sig1, $sig2);
    }

    public function test_compute_signature_differs_for_different_methods(): void
    {
        $timestamp = 1700000000;
        $getSig = $this->signer->computeSignature('GET', '/api/test', '', $timestamp);
        $postSig = $this->signer->computeSignature('POST', '/api/test', '', $timestamp);

        $this->assertNotSame($getSig, $postSig);
    }

    public function test_compute_signature_differs_for_different_paths(): void
    {
        $timestamp = 1700000000;
        $sig1 = $this->signer->computeSignature('POST', '/api/one', '', $timestamp);
        $sig2 = $this->signer->computeSignature('POST', '/api/two', '', $timestamp);

        $this->assertNotSame($sig1, $sig2);
    }

    public function test_compute_signature_differs_for_different_bodies(): void
    {
        $timestamp = 1700000000;
        $sig1 = $this->signer->computeSignature('POST', '/api/test', 'body1', $timestamp);
        $sig2 = $this->signer->computeSignature('POST', '/api/test', 'body2', $timestamp);

        $this->assertNotSame($sig1, $sig2);
    }

    public function test_is_valid_returns_true_for_valid_signature(): void
    {
        $timestamp = time();
        $signature = $this->signer->computeSignature('POST', '/api/test', 'body', $timestamp);

        $result = $this->signer->isValid('POST', '/api/test', 'body', $signature, $timestamp);

        $this->assertTrue($result);
    }

    public function test_is_valid_returns_false_for_empty_signature(): void
    {
        $result = $this->signer->isValid('POST', '/api/test', '', '', time());

        $this->assertFalse($result);
    }

    public function test_is_valid_returns_false_for_invalid_timestamp(): void
    {
        $signature = $this->signer->computeSignature('POST', '/api/test', '', time());

        $result = $this->signer->isValid('POST', '/api/test', '', $signature, 0);

        $this->assertFalse($result);
    }

    public function test_is_valid_returns_false_for_expired_timestamp(): void
    {
        $oldTimestamp = time() - 600; // 10 minutes ago
        $signature = $this->signer->computeSignature('POST', '/api/test', '', $oldTimestamp);

        // Default tolerance is 300 seconds (5 minutes)
        $result = $this->signer->isValid('POST', '/api/test', '', $signature, $oldTimestamp);

        $this->assertFalse($result);
    }

    public function test_is_valid_returns_true_for_timestamp_within_tolerance(): void
    {
        $recentTimestamp = time() - 100; // 100 seconds ago (within 300 seconds tolerance)
        $signature = $this->signer->computeSignature('POST', '/api/test', '', $recentTimestamp);

        $result = $this->signer->isValid('POST', '/api/test', '', $signature, $recentTimestamp);

        $this->assertTrue($result);
    }

    public function test_is_valid_returns_false_for_wrong_signature(): void
    {
        $timestamp = time();

        $result = $this->signer->isValid('POST', '/api/test', '', 'wrong-signature', $timestamp);

        $this->assertFalse($result);
    }

    public function test_is_valid_uses_custom_tolerance(): void
    {
        $oldTimestamp = time() - 400; // 400 seconds ago
        $signature = $this->signer->computeSignature('POST', '/api/test', '', $oldTimestamp);

        // With 500 seconds tolerance, it should be valid
        $result = $this->signer->isValid('POST', '/api/test', '', $signature, $oldTimestamp, 500);

        $this->assertTrue($result);
    }
}
