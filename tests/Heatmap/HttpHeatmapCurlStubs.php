<?php

declare(strict_types=1);

namespace App\Heatmap\Infrastructure;

final class HttpHeatmapApiClientTestDoubles
{
    public static bool $forceCurlInitFailure = false;
    public static mixed $curlExecReturn = '{}';
    public static int $curlInfoStatus = 200;
    public static string $curlError = '';
    public static array $recorded = [];

    public static function reset(): void
    {
        self::$forceCurlInitFailure = false;
        self::$curlExecReturn = '{}';
        self::$curlInfoStatus = 200;
        self::$curlError = '';
        self::$recorded = [];
    }
}

function curl_init(?string $url = null)
{
    HttpHeatmapApiClientTestDoubles::$recorded['init'][] = $url;

    if (HttpHeatmapApiClientTestDoubles::$forceCurlInitFailure) {
        return false;
    }

    return 'curl-resource';
}

function curl_setopt($ch, int $option, mixed $value): bool
{
    HttpHeatmapApiClientTestDoubles::$recorded['set'][] = [$option, $value];
    return true;
}

function curl_exec($ch)
{
    return HttpHeatmapApiClientTestDoubles::$curlExecReturn;
}

function curl_getinfo($ch, int $option = 0): int
{
    if ($option === \CURLINFO_RESPONSE_CODE) {
        return HttpHeatmapApiClientTestDoubles::$curlInfoStatus;
    }

    return 0;
}

function curl_error($ch): string
{
    return HttpHeatmapApiClientTestDoubles::$curlError;
}

function curl_close($ch): void
{
    HttpHeatmapApiClientTestDoubles::$recorded['closed'] = true;
}
