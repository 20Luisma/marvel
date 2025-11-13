<?php

namespace App\Services;

use DateTimeImmutable;
use DateTimeZone;

@ini_set('max_execution_time', '650');
@ini_set('default_socket_timeout', '650');
@set_time_limit(650);

final class GithubClient
{
    public const OWNER = '20Luisma';
    public const REPO  = 'marvel';

    private const BASE_URL   = 'https://api.github.com';
    private const PULLS_PATH = '/repos/%s/%s/pulls?state=all&per_page=100';
    private const COMMITS_PATH = '/repos/%s/%s/pulls/%d/commits?per_page=100';
    private const REVIEWS_PATH = '/repos/%s/%s/pulls/%d/reviews?per_page=100';

    private string $apiKey;
    private string $rootPath;

    public function __construct(string $rootPath)
    {
        $this->rootPath = rtrim($rootPath, '/\\');
        $envFile        = $this->rootPath . '/.env';

        self::ensureEnv($envFile);
        $this->apiKey = (string) self::envv('GITHUB_API_KEY', '');
    }

    public static function ensureEnv(string $envFile): void
    {
        static $loaded = false;

        if ($loaded || !is_file($envFile)) {
            return;
        }

        foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            if ($line === '' || str_starts_with(trim($line), '#')) {
                continue;
            }

            [$key, $value] = array_map('trim', explode('=', $line, 2) + [1 => '']);
            if ($key !== '' && getenv($key) === false) {
                putenv($key . '=' . $value);
                $_ENV[$key] = $value;
            }
        }

        $loaded = true;
    }

    public static function envv(string $key, ?string $default = null): ?string
    {
        $value = getenv($key);
        if ($value === false || $value === '') {
            return $default;
        }

        return $value;
    }

    /**
     * @return array{status:int,data?:array<int,array<string,mixed>>,error?:string,detail?:string,body?:mixed}
     */
    public function fetchActivity(string $from, string $to): array
    {
        if ($this->apiKey === '') {
            return [
                'error'  => 'Falta GITHUB_API_KEY en .env',
                'status' => 500,
            ];
        }

        $range = $this->buildDateRange($from, $to);
        if ($range === null) {
            return [
                'error'  => 'Rango de fechas inválido.',
                'status' => 400,
            ];
        }

        [$fromDate, $toDate] = $range;

        $url      = sprintf(self::BASE_URL . self::PULLS_PATH, self::OWNER, self::REPO);
        $response = $this->requestGithub($url);

        if (!$response['ok'] || !is_array($response['decoded'])) {
            return $this->errorPayloadFromResponse(
                $response,
                'No se pudo obtener la actividad de PRs desde GitHub.'
            );
        }

        $entries = [];
        foreach ($response['decoded'] as $pr) {
            if (!is_array($pr)) {
                continue;
            }

            $createdAtRaw = $pr['created_at'] ?? null;
            if (!is_string($createdAtRaw)) {
                continue;
            }

            try {
                $createdAt = new DateTimeImmutable($createdAtRaw);
            } catch (\Throwable $e) {
                continue;
            }

            if ($createdAt < $fromDate || $createdAt > $toDate) {
                continue;
            }

            $number = (int) ($pr['number'] ?? 0);

            // 🔢 Commits y reviews por PR
            $commitCount           = $this->countCommitsForPr($number);
            [$reviewCount, $reviewers] = $this->summarizeReviewsForPr($number);

            $title  = sprintf('#%d — %s', $number, $pr['title'] ?? 'Pull Request');
            $author = $pr['user']['login'] ?? 'desconocido';
            $state  = strtoupper((string) ($pr['state'] ?? ''));

            $subtitle = sprintf(
                'Autor: %s · Estado: %s · Creado: %s',
                $author,
                $state !== '' ? $state : 'N/D',
                $createdAtRaw
            );

            $metaLine = sprintf(
                'Commits: %d · Reviews: %d%s',
                $commitCount,
                $reviewCount,
                $reviewCount > 0 && $reviewers !== []
                    ? ' · Reviewers: ' . implode(', ', $reviewers)
                    : ''
            );

            $entries[] = [
                'title'    => $title,
                'subtitle' => $subtitle,
                'meta'     => $metaLine,
                'details'  => [
                    'url'          => $pr['html_url'] ?? '',
                    'created_at'   => $createdAtRaw,
                    'updated_at'   => $pr['updated_at'] ?? null,
                    'merged_at'    => $pr['merged_at'] ?? null,
                    'labels'       => $this->extractLabels($pr),
                    'commit_count' => $commitCount,
                    'review_count' => $reviewCount,
                    'reviewers'    => $reviewers,
                ],
            ];
        }

        return [
            'status' => 200,
            'data'   => array_values($entries),
        ];
    }

    /**
     * @return array{0:DateTimeImmutable,1:DateTimeImmutable}|null
     */
    private function buildDateRange(string $from, string $to): ?array
    {
        try {
            $fromDate = new DateTimeImmutable($from . ' 00:00:00', new DateTimeZone('UTC'));
            $toDate   = new DateTimeImmutable($to . ' 23:59:59', new DateTimeZone('UTC'));
        } catch (\Throwable $e) {
            return null;
        }

        if ($fromDate > $toDate) {
            return null;
        }

        return [$fromDate, $toDate];
    }

    /**
     * @return array<int,string>
     */
    private function extractLabels(array $pr): array
    {
        $labels = [];
        if (!isset($pr['labels']) || !is_array($pr['labels'])) {
            return $labels;
        }

        foreach ($pr['labels'] as $label) {
            if (is_array($label) && isset($label['name'])) {
                $labels[] = (string) $label['name'];
            }
        }

        return $labels;
    }

    /**
     * Cuenta commits usando /pulls/{number}/commits
     */
    private function countCommitsForPr(int $prNumber): int
    {
        if ($prNumber <= 0) {
            return 0;
        }

        $url = sprintf(
            self::BASE_URL . self::COMMITS_PATH,
            self::OWNER,
            self::REPO,
            $prNumber
        );

        $response = $this->requestGithub($url);
        if (!$response['ok'] || !is_array($response['decoded'])) {
            return 0;
        }

        return count($response['decoded']);
    }

    /**
     * Resume reviews: total + lista de reviewers únicos
     *
     * @return array{0:int,1:array<int,string>}
     */
    private function summarizeReviewsForPr(int $prNumber): array
    {
        if ($prNumber <= 0) {
            return [0, []];
        }

        $url = sprintf(
            self::BASE_URL . self::REVIEWS_PATH,
            self::OWNER,
            self::REPO,
            $prNumber
        );

        $response = $this->requestGithub($url);
        if (!$response['ok'] || !is_array($response['decoded'])) {
            return [0, []];
        }

        $reviewCount = 0;
        $reviewers   = [];

        foreach ($response['decoded'] as $review) {
            if (!is_array($review)) {
                continue;
            }

            $reviewCount++;

            $login = $review['user']['login'] ?? null;
            if (is_string($login) && $login !== '') {
                $reviewers[] = $login;
            }
        }

        $reviewers = array_values(array_unique($reviewers));

        return [$reviewCount, $reviewers];
    }

    /**
     * @return array{ok:bool,status:int,body:string,decoded:mixed,error?:string}
     */
    private function requestGithub(string $url): array
    {
        $ch = curl_init($url);

        $headers = [
            'accept: application/vnd.github+json',
            'user-agent: clean-marvel-app',
        ];

        if ($this->apiKey !== '') {
            $headers[] = 'Authorization: token ' . $this->apiKey;
        }

        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_HTTPHEADER     => $headers,
        ];

        curl_setopt_array($ch, $options);

        $body   = curl_exec($ch);
        $error  = curl_error($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE) ?: 0;

        curl_close($ch);

        if ($body === false) {
            return [
                'ok'      => false,
                'status'  => 0,
                'body'    => '',
                'decoded' => null,
                'error'   => $error,
            ];
        }

        $decoded = json_decode($body, true);

        return [
            'ok'      => $status >= 200 && $status < 300,
            'status'  => $status,
            'body'    => $body,
            'decoded' => $decoded,
            'error'   => $error ?: null,
        ];
    }

    /**
     * @param array{status:int,body:string,decoded:mixed,error?:string} $response
     * @return array{status:int,error:string,detail?:string,body?:mixed}
     */
    private function errorPayloadFromResponse(array $response, string $message): array
    {
        $payload = [
            'error'  => $message,
            'status' => $response['status'] ?: 502,
        ];

        if (!empty($response['error'])) {
            $payload['detail'] = $response['error'];
        }

        if ($response['decoded'] !== null) {
            $payload['body'] = $response['decoded'];
        } elseif ($response['body'] !== '') {
            $payload['body'] = $response['body'];
        }

        return $payload;
    }
}
