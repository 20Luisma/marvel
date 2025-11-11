<?php

declare(strict_types=1);

namespace Tests\Dev\Test;

use App\Dev\Test\PhpUnitTestRunner;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class PhpUnitTestRunnerTest extends TestCase
{
    public function testRunReturnsSkippedWhenTestsDisabled(): void
    {
        $runner = new PhpUnitTestRunner(__DIR__, false);
        $result = $runner->run();

        self::assertSame('skipped', $result['status']);
        self::assertStringContainsString('deshabilitada', $result['message']);
    }

    public function testRunReturnsErrorWhenBinaryMissing(): void
    {
        $projectRoot = sys_get_temp_dir() . '/phpunit-runner-' . uniqid('', true);
        mkdir($projectRoot);

        $runner = new PhpUnitTestRunner($projectRoot, true);
        $result = $runner->run();

        self::assertSame('error', $result['status']);
        self::assertStringContainsString('No se encontró el binario', $result['message']);

        @rmdir($projectRoot);
    }

    public function testParseJunitReportExtractsSummary(): void
    {
        $logFile = tempnam(sys_get_temp_dir(), 'junit-');
        file_put_contents($logFile, <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<testsuite tests="2" assertions="3" failures="1" errors="0" skipped="0" time="0.123">
    <testcase classname="ExampleTest" name="testPass" time="0.01" />
    <testcase classname="ExampleTest" name="testFail" time="0.02">
        <failure>Assertion failed</failure>
    </testcase>
</testsuite>
XML);

        $runner = new PhpUnitTestRunner(__DIR__, true);
        $reflection = new ReflectionClass($runner);
        $method = $reflection->getMethod('parseJunitReport');
        $method->setAccessible(true);

        /** @var array{summary: array<string, mixed>, statusCounts: array<string, int>} $parsed */
        $parsed = $method->invoke($runner, $logFile);

        self::assertSame(2, $parsed['summary']['tests']);
        self::assertSame(1, $parsed['statusCounts']['failed']);

        @unlink($logFile);
    }
}
