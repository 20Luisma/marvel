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
        $missingKeyPayload = $this->guardMissingApiKey();
        if ($missingKeyPayload !== null) {
            return $missingKeyPayload;
        }

        $range = $this->buildDateRange($from, $to);
        return $range === null
            ? $this->invalidRangePayload()
            : $this->buildActivityPayload($range[0], $range[1]);
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

    private function guardMissingApiKey(): ?array
    {
        if ($this->apiKey !== '') {
            return null;
        }

        return [
            'error'  => 'Falta GITHUB_API_KEY en .env',
            'status' => 500,
        ];
    }

    private function invalidRangePayload(): array
    {
        return [
            'error'  => 'Rango de fechas inválido.',
            'status' => 400,
        ];
    }

    private function buildActivityPayload(DateTimeImmutable $fromDate, DateTimeImmutable $toDate): array
    {
        $url      = sprintf(self::BASE_URL . self::PULLS_PATH, self::OWNER, self::REPO);
        $response = $this->requestGithub($url);

        if (!$this->isSuccessfulListResponse($response)) {
            return $this->errorPayloadFromResponse(
                $response,
                'No se pudo obtener la actividad de PRs desde GitHub.'
            );
        }

        $entries = $this->buildEntries($response['decoded'], $fromDate, $toDate);

        return [
            'status' => 200,
            'data'   => array_values($entries),
        ];
    }

    /**
     * @param array{ok:bool,status:int,body:string,decoded:mixed,error?:string} $response
     */
    private function isSuccessfulListResponse(array $response): bool
    {
        return $response['ok'] && is_array($response['decoded']);
    }

    /**
     * @param array<int,mixed> $pullRequests
     * @return array<int,array<string,mixed>>
     */
    private function buildEntries(array $pullRequests, DateTimeImmutable $fromDate, DateTimeImmutable $toDate): array
    {
        $entries = [];
        foreach ($pullRequests as $pr) {
            if (!is_array($pr)) {
                continue;
            }

            $entry = $this->createEntryFromPullRequest($pr, $fromDate, $toDate);
            if ($entry !== null) {
                $entries[] = $entry;
            }
        }

        return $entries;
    }

    /**
     * @return array<string,mixed>|null
     */
    private function createEntryFromPullRequest(array $pr, DateTimeImmutable $fromDate, DateTimeImmutable $toDate): ?array
    {
        $createdAt = $this->parseCreatedAt($pr);
        if ($createdAt === null || $createdAt['date'] < $fromDate || $createdAt['date'] > $toDate) {
            return null;
        }

        $number = (int) ($pr['number'] ?? 0);
        $commitCount = $this->countCommitsForPr($number);
        [$reviewCount, $reviewers] = $this->summarizeReviewsForPr($number);

        $title  = sprintf('#%d — %s', $number, $pr['title'] ?? 'Pull Request');
        $author = $pr['user']['login'] ?? 'desconocido';
        $state  = strtoupper((string) ($pr['state'] ?? ''));

        $subtitle = sprintf(
            'Autor: %s · Estado: %s · Creado: %s',
            $author,
            $state !== '' ? $state : 'N/D',
            $createdAt['raw']
        );

        return [
            'title'    => $title,
            'subtitle' => $subtitle,
            'meta'     => $this->buildMetaLine($commitCount, $reviewCount, $reviewers),
            'details'  => $this->buildDetails($pr, $createdAt['raw'], $commitCount, $reviewCount, $reviewers),
        ];
    }

    /**
     * @return array{raw:string,date:DateTimeImmutable}|null
     */
    private function parseCreatedAt(array $pr): ?array
    {
        $createdAtRaw = $pr['created_at'] ?? null;
        if (!is_string($createdAtRaw)) {
            return null;
        }

        try {
            $createdAt = new DateTimeImmutable($createdAtRaw);
        } catch (\Throwable $e) {
            return null;
        }

        return [
            'raw'  => $createdAtRaw,
            'date' => $createdAt,
        ];
    }

    /**
     * @param array<int,string> $reviewers
     */
    private function buildMetaLine(int $commitCount, int $reviewCount, array $reviewers): string
    {
        $metaParts = [
            'Commits: ' . $commitCount,
            'Reviews: ' . $reviewCount,
        ];

        if ($reviewCount > 0 && $reviewers !== []) {
            $metaParts[] = 'Reviewers: ' . implode(', ', $reviewers);
        }

        return implode(' · ', $metaParts);
    }

    /**
     * @param array<int,string> $reviewers
     * @return array<string,mixed>
     */
    private function buildDetails(
        array $pr,
        string $createdAtRaw,
        int $commitCount,
        int $reviewCount,
        array $reviewers
    ): array {
        return [
            'url'          => $pr['html_url'] ?? '',
            'created_at'   => $createdAtRaw,
            'updated_at'   => $pr['updated_at'] ?? null,
            'merged_at'    => $pr['merged_at'] ?? null,
            'labels'       => $this->extractLabels($pr),
            'commit_count' => $commitCount,
            'review_count' => $reviewCount,
            'reviewers'    => $reviewers,
        ];
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

    public function listRepositoryContents(string $path = ''): array
    {
        $trimmed = trim((string) $path, '/');
        $segments = $trimmed === '' ? [] : array_filter(explode('/', $trimmed), static fn ($segment) => $segment !== '');
        $encodedPath = '';
        if (!empty($segments)) {
            $encodedPath = '/' . implode('/', array_map(static fn ($segment) => rawurlencode($segment), $segments));
        }

        $url = sprintf(
            '%s/repos/%s/%s/contents%s',
            self::BASE_URL,
            self::OWNER,
            self::REPO,
            $encodedPath
        );

        return $this->requestGithub($url);
    }

    /**
     * @return array{ok:bool,status:int,body:string,decoded:mixed,error?:string}
     */
    private function requestGithub(string $url): array
    {
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

        $attempt = 0;
        $maxAttempts = 2;
        $lastError = null;
        $lastStatus = 0;
        $lastBody = '';

        while ($attempt < $maxAttempts) {
            $ch = curl_init($url);
            if ($ch === false) {
                $lastError = 'No se pudo iniciar cURL.';
                $attempt++;
                usleep(150_000);
                continue;
            }

            curl_setopt_array($ch, $options);

            $body   = curl_exec($ch);
            $error  = curl_error($ch);
            $status = curl_getinfo($ch, CURLINFO_HTTP_CODE) ?: 0;

            curl_close($ch);

            if ($body !== false && $status >= 200 && $status < 300) {
                $decoded = json_decode($body, true);

                return [
                    'ok'      => true,
                    'status'  => $status,
                    'body'    => $body,
                    'decoded' => $decoded,
                    'error'   => $error ?: null,
                ];
            }

            $lastError = $error ?: ($status ? 'HTTP ' . $status : 'Error desconocido');
            $lastStatus = $status;
            $lastBody = $body === false ? '' : (string) $body;
            $attempt++;
            usleep(150_000);
        }

        return [
            'ok'      => false,
            'status'  => $lastStatus,
            'body'    => $lastBody,
            'decoded' => null,
            'error'   => $lastError,
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
