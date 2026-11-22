<?php

declare(strict_types=1);

namespace Tests\Heatmap\Infrastructure;

use App\Heatmap\Infrastructure\HttpHeatmapApiClient;
use App\Heatmap\Infrastructure\ReplicatedHeatmapApiClient;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * El identificador de nodo (gcp/aws) se resuelve por posición en la configuración,
 * no por IPs escritas en el código.
 */
final class ReplicatedHeatmapApiClientNodeKeyTest extends TestCase
{
    private function client(string ...$urls): ReplicatedHeatmapApiClient
    {
        return new ReplicatedHeatmapApiClient(
            ...array_map(static fn (string $url): HttpHeatmapApiClient => new HttpHeatmapApiClient($url), $urls)
        );
    }

    private function resolve(ReplicatedHeatmapApiClient $client, string $url, ?int $idx = null): string
    {
        $method = new ReflectionMethod($client, 'resolveNodeKey');

        return (string) $method->invoke($client, $url, $idx);
    }

    public function testFirstConfiguredNodeIsGcpAndSecondIsAws(): void
    {
        $client = $this->client('https://primary.example.com', 'https://secondary.example.com');

        self::assertSame('gcp', $this->resolve($client, 'https://primary.example.com'));
        self::assertSame('aws', $this->resolve($client, 'https://secondary.example.com'));
    }

    public function testExplicitIndexTakesPrecedenceOverUrlLookup(): void
    {
        $client = $this->client('https://primary.example.com', 'https://secondary.example.com');

        self::assertSame('gcp', $this->resolve($client, 'https://secondary.example.com', 0));
        self::assertSame('aws', $this->resolve($client, 'https://primary.example.com', 1));
    }

    public function testUnknownUrlWithoutIndexFallsBackToGcp(): void
    {
        $client = $this->client('https://primary.example.com');

        self::assertSame('gcp', $this->resolve($client, 'https://not-configured.example.com'));
    }

    public function testDoesNotDependOnLiteralIpAddresses(): void
    {
        $client = $this->client('http://203.0.113.10:8080', 'http://203.0.113.20:8080');

        self::assertSame('gcp', $this->resolve($client, 'http://203.0.113.10:8080'));
        self::assertSame('aws', $this->resolve($client, 'http://203.0.113.20:8080'));
    }
}
