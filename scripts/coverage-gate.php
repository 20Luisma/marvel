<?php

declare(strict_types=1);

/**
 * Coverage gate for PHPUnit Clover coverage.xml.
 *
 * Computes overall statement coverage using <metrics statements=".." coveredstatements="..">.
 * Exits non-zero when coverage is below the threshold.
 *
 * Usage:
 *   php scripts/coverage-gate.php coverage.xml
 *
 * Config:
 *   COVERAGE_THRESHOLD (percent, default 75)
 */

function fail(string $message, int $code = 1): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit($code);
}

function parseThreshold(?string $value, float $default): float
{
    if ($value === null) {
        return $default;
    }

    $trimmed = trim($value);
    if ($trimmed === '') {
        return $default;
    }

    if (!is_numeric($trimmed)) {
        fail("COVERAGE_THRESHOLD must be numeric, got: {$trimmed}");
    }

    $threshold = (float) $trimmed;
    if ($threshold < 0 || $threshold > 100) {
        fail("COVERAGE_THRESHOLD must be between 0 and 100, got: {$trimmed}");
    }

    return $threshold;
}

try {
    $path = $argv[1] ?? '';
    if (!is_string($path) || trim($path) === '') {
        throw new RuntimeException('Missing coverage file path argument (expected coverage.xml).');
    }

    $path = trim($path);
    if (!is_file($path)) {
        throw new RuntimeException("Coverage file not found: {$path}");
    }

    $threshold = parseThreshold(getenv('COVERAGE_THRESHOLD') ?: null, 75.0);

    $dom = new DOMDocument();
    $dom->preserveWhiteSpace = false;
    $loaded = $dom->load($path, LIBXML_NONET);
    if ($loaded !== true) {
        throw new RuntimeException("Failed to parse XML: {$path}");
    }

    $xpath = new DOMXPath($dom);
    $projectMetrics = $xpath->query('/coverage/project/metrics');
    if ($projectMetrics === false) {
        throw new RuntimeException('Failed to query Clover metrics nodes.');
    }

    $metricsNode = $projectMetrics->item(0);
    if (!$metricsNode instanceof DOMElement) {
        $fallback = $xpath->query('//project/metrics');
        if ($fallback === false) {
            throw new RuntimeException('Failed to query Clover metrics nodes.');
        }
        $metricsNode = $fallback->item(0);
    }

    if (!$metricsNode instanceof DOMElement) {
        throw new RuntimeException('Project metrics node not found in Clover XML.');
    }

    $statements = (int) ($metricsNode->getAttribute('statements') ?: 0);
    if ($statements <= 0) {
        fail('Invalid Clover metrics: statements=0', 3);
    }

    $covered = (int) ($metricsNode->getAttribute('coveredstatements') ?: 0);

    $percent = $statements > 0 ? ($covered / $statements * 100.0) : 0.0;
    $percentRounded = number_format($percent, 2, '.', '');

    $summary = "Coverage gate (statements): {$covered}/{$statements} = {$percentRounded}% (threshold: {$threshold}%)";
    if ($percent + 1e-9 < $threshold) {
        fail("FAIL: {$summary}", 2);
    }

    echo "PASS: {$summary}" . PHP_EOL;
} catch (Throwable $e) {
    fail('Coverage gate error: ' . $e->getMessage(), 3);
}
