<?php
declare(strict_types=1);

namespace App\Heatmap\Infrastructure;

interface HeatmapApiClient
{
    /**
     * @param array<string,mixed> $payload
     * @return array{statusCode:int,body:string}
     */
    public function sendClick(array $payload): array;

    /**
     * @param array<string,string> $query
     * @return array{statusCode:int,body:string}
     */
    public function getSummary(array $query): array;

    /**
     * @return array{statusCode:int,body:string}
     */
    public function getPages(): array;
}
