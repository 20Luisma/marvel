<?php

declare(strict_types=1);

namespace Tests\Controllers;

use App\Dev\Test\PhpUnitTestRunner;
use PHPUnit\Framework\TestCase;
use App\Controllers\DevController;

final class DevControllerTest extends TestCase
{
    protected function setUp(): void
    {
        http_response_code(200);
    }

    public function testRunTestsReturns403WhenTestsAreSkipped(): void
    {
        $controller = new DevController(new PhpUnitTestRunner(__DIR__, false));

        $payload = $this->captureJson(fn () => $controller->runTests());

        self::assertSame('error', $payload['estado']);
    }

    public function testRunTestsReturns500WhenRunnerErrors(): void
    {
        $projectRoot = sys_get_temp_dir() . '/dev-runner-error-' . uniqid('', true);
        mkdir($projectRoot);
        $controller = new DevController(new PhpUnitTestRunner($projectRoot, true));

        $payload = $this->captureJson(fn () => $controller->runTests());

        self::assertSame('error', $payload['estado']);

        @rmdir($projectRoot);
    }

    public function testRunTestsReturnsSuccessPayloadWhenRunnerPasses(): void
    {
        $projectRoot = $this->createFakePhpUnitProject();
        $controller = new DevController(new PhpUnitTestRunner($projectRoot, true));

        $payload = $this->captureJson(fn () => $controller->runTests());

        self::assertSame('éxito', $payload['estado']);
        self::assertSame('passed', $payload['datos']['status']);

        $this->removeDirectory($projectRoot);
    }

    /**
     * @return array<string, mixed>
     */
    private function captureJson(callable $callable): array
    {
        ob_start();
        $result = $callable();
        $contents = (string) ob_get_clean();

        $payload = \App\Shared\Http\JsonResponse::lastPayload();

        if (is_array($result)) {
            return $result;
        }

        if ($payload !== null) {
            return $payload;
        }

        if ($contents !== '') {
            return json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        }

        return [];
    }

    private function createFakePhpUnitProject(): string
    {
        $root = sys_get_temp_dir() . '/dev-runner-success-' . uniqid('', true);
        $binaryDir = $root . '/vendor/bin';
        mkdir($binaryDir, 0777, true);
        $script = <<<PHP
<?php
\$logFile = null;
for (\$i = 1; \$i < \$argc; \$i++) {
    if (\$argv[\$i] === '--log-junit' && isset(\$argv[\$i + 1])) {
        \$logFile = \$argv[\$i + 1];
        break;
    }
}
if (\$logFile) {
    file_put_contents(\$logFile, '<?xml version="1.0"?><testsuite tests="1" assertions="1" failures="0" errors="0" skipped="0" time="0.01"><testcase classname="Fake" name="testPass" time="0.01"/></testsuite>');
}
echo "Fake suite ejecutada\n";
PHP;
        file_put_contents($binaryDir . '/phpunit', $script);
        chmod($binaryDir . '/phpunit', 0755);

        return $root;
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            if (is_file($path)) {
                @unlink($path);
            }
            return;
        }

        $items = scandir($path);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $this->removeDirectory($path . DIRECTORY_SEPARATOR . $item);
        }

        @rmdir($path);
    }
}
