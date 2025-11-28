<?php

declare(strict_types=1);

namespace Tests\Monitoring;

use App\Monitoring\TraceIdGenerator;
use PHPUnit\Framework\TestCase;

final class TraceIdGeneratorTest extends TestCase
{
    public function testGeneratesUuidLikeValue(): void
    {
        $generator = new TraceIdGenerator();
        $id = $generator->generate();

        self::assertNotSame('', $id);
        self::assertMatchesRegularExpression('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/', $id);

        $other = $generator->generate();
        self::assertNotSame($id, $other);
    }
}
