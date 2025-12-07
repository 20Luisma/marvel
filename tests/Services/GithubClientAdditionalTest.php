<?php

declare(strict_types=1);

namespace Tests\Services;

use App\Services\GithubClient;
use App\Shared\Infrastructure\Http\HttpClientInterface;
use App\Shared\Infrastructure\Http\HttpResponse;
use PHPUnit\Framework\TestCase;

/**
 * Additional tests for GithubClient to improve coverage
 */
class GithubClientAdditionalTest extends TestCase
{
    private string $rootPath;
    private string $originalApiKey;

    protected function setUp(): void
    {
        $this->rootPath = dirname(__DIR__, 2);
        $this->originalApiKey = getenv('GITHUB_API_KEY') ?: '';
        putenv('GITHUB_API_KEY=test_api_key_123');
    }

    protected function tearDown(): void
    {
        if ($this->originalApiKey !== '') {
            putenv("GITHUB_API_KEY={$this->originalApiKey}");
        } else {
            putenv('GITHUB_API_KEY');
        }
    }

    public function testListRepositoryContentsSuccess(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        
        $httpClient->method('get')->willReturn(
            new HttpResponse(200, json_encode([
                ['name' => 'src', 'type' => 'dir'],
                ['name' => 'README.md', 'type' => 'file'],
            ]))
        );

        $client = new GithubClient($this->rootPath, $httpClient);
        $result = $client->listRepositoryContents();

        $this->assertTrue($result['ok']);
        $this->assertSame(200, $result['status']);
        $this->assertIsArray($result['decoded']);
    }

    public function testListRepositoryContentsWithPath(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        
        $httpClient->expects($this->once())
            ->method('get')
            ->with($this->stringContains('/contents/src'))
            ->willReturn(new HttpResponse(200, json_encode([
                ['name' => 'Controllers', 'type' => 'dir'],
            ])));

        $client = new GithubClient($this->rootPath, $httpClient);
        $result = $client->listRepositoryContents('src');

        $this->assertTrue($result['ok']);
    }

    public function testListRepositoryContentsWithNestedPath(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        
        $httpClient->expects($this->once())
            ->method('get')
            ->with($this->stringContains('/contents/src/Controllers'))
            ->willReturn(new HttpResponse(200, '[]'));

        $client = new GithubClient($this->rootPath, $httpClient);
        $result = $client->listRepositoryContents('src/Controllers');

        $this->assertTrue($result['ok']);
    }

    public function testListRepositoryContentsWithSpecialCharacters(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        
        $httpClient->method('get')->willReturn(new HttpResponse(200, '[]'));

        $client = new GithubClient($this->rootPath, $httpClient);
        $result = $client->listRepositoryContents('path with spaces/special%chars');

        $this->assertTrue($result['ok']);
    }

    public function testFetchActivityWithApiError(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        
        $httpClient->method('get')->willReturn(
            new HttpResponse(403, json_encode(['message' => 'API rate limit exceeded']))
        );

        $client = new GithubClient($this->rootPath, $httpClient);
        $result = $client->fetchActivity('2023-01-01', '2023-12-31');

        $this->assertArrayHasKey('error', $result);
        $this->assertSame(403, $result['status']);
    }

    public function testFetchActivityWithNetworkError(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        
        $httpClient->method('get')->willThrowException(
            new \RuntimeException('Connection refused')
        );

        $client = new GithubClient($this->rootPath, $httpClient);
        $result = $client->fetchActivity('2023-01-01', '2023-12-31');

        $this->assertArrayHasKey('error', $result);
    }

    public function testFetchActivityWithEmptyPullRequestsList(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        
        $httpClient->method('get')->willReturn(
            new HttpResponse(200, '[]')
        );

        $client = new GithubClient($this->rootPath, $httpClient);
        $result = $client->fetchActivity('2023-01-01', '2023-01-02');

        $this->assertSame(200, $result['status']);
        $this->assertEmpty($result['data']);
    }

    public function testFetchActivityFiltersPRsOutsideDateRange(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        
        $prResponse = json_encode([
            [
                'number' => 1,
                'title' => 'Old PR',
                'state' => 'closed',
                'user' => ['login' => 'tester'],
                'created_at' => '2022-01-01T12:00:00Z',
                'html_url' => 'http://github.com/pr/1',
            ],
            [
                'number' => 2,
                'title' => 'In Range PR',
                'state' => 'open',
                'user' => ['login' => 'tester'],
                'created_at' => '2023-06-15T12:00:00Z',
                'html_url' => 'http://github.com/pr/2',
            ],
        ]);

        $httpClient->method('get')->willReturnCallback(function ($url) use ($prResponse) {
            if (str_contains($url, '/pulls?')) {
                return new HttpResponse(200, $prResponse);
            }
            if (str_contains($url, '/commits')) {
                return new HttpResponse(200, '[]');
            }
            if (str_contains($url, '/reviews')) {
                return new HttpResponse(200, '[]');
            }
            return new HttpResponse(404, '{}');
        });

        $client = new GithubClient($this->rootPath, $httpClient);
        $result = $client->fetchActivity('2023-01-01', '2023-12-31');

        $this->assertSame(200, $result['status']);
        $this->assertCount(1, $result['data']);
        $this->assertStringContainsString('In Range PR', $result['data'][0]['title']);
    }

    public function testFetchActivityWithMalformedPRData(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        
        $prResponse = json_encode([
            'not_an_array_item',
            null,
            [
                'number' => 1,
                'title' => 'Valid PR',
                'state' => 'open',
                'user' => ['login' => 'tester'],
                'created_at' => '2023-06-15T12:00:00Z',
                'html_url' => 'http://github.com/pr/1',
            ],
        ]);

        $httpClient->method('get')->willReturnCallback(function ($url) use ($prResponse) {
            if (str_contains($url, '/pulls?')) {
                return new HttpResponse(200, $prResponse);
            }
            return new HttpResponse(200, '[]');
        });

        $client = new GithubClient($this->rootPath, $httpClient);
        $result = $client->fetchActivity('2023-01-01', '2023-12-31');

        $this->assertSame(200, $result['status']);
        $this->assertCount(1, $result['data']);
    }

    public function testFetchActivityWithInvalidCreatedAtDate(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        
        $prResponse = json_encode([
            [
                'number' => 1,
                'title' => 'Invalid Date PR',
                'state' => 'open',
                'user' => ['login' => 'tester'],
                'created_at' => 'not-a-valid-date',
                'html_url' => 'http://github.com/pr/1',
            ],
        ]);

        $httpClient->method('get')->willReturn(new HttpResponse(200, $prResponse));

        $client = new GithubClient($this->rootPath, $httpClient);
        $result = $client->fetchActivity('2023-01-01', '2023-12-31');

        $this->assertSame(200, $result['status']);
        $this->assertEmpty($result['data']);
    }

    public function testFetchActivityWithMissingCreatedAt(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        
        $prResponse = json_encode([
            [
                'number' => 1,
                'title' => 'No Date PR',
                'state' => 'open',
                'user' => ['login' => 'tester'],
                'html_url' => 'http://github.com/pr/1',
            ],
        ]);

        $httpClient->method('get')->willReturn(new HttpResponse(200, $prResponse));

        $client = new GithubClient($this->rootPath, $httpClient);
        $result = $client->fetchActivity('2023-01-01', '2023-12-31');

        $this->assertSame(200, $result['status']);
        $this->assertEmpty($result['data']);
    }

    public function testFetchActivityWithPRWithoutLabels(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        
        $prResponse = json_encode([
            [
                'number' => 1,
                'title' => 'No Labels PR',
                'state' => 'open',
                'user' => ['login' => 'tester'],
                'created_at' => '2023-06-15T12:00:00Z',
                'html_url' => 'http://github.com/pr/1',
            ],
        ]);

        $httpClient->method('get')->willReturnCallback(function ($url) use ($prResponse) {
            if (str_contains($url, '/pulls?')) {
                return new HttpResponse(200, $prResponse);
            }
            return new HttpResponse(200, '[]');
        });

        $client = new GithubClient($this->rootPath, $httpClient);
        $result = $client->fetchActivity('2023-01-01', '2023-12-31');

        $this->assertSame(200, $result['status']);
        $this->assertEmpty($result['data'][0]['details']['labels']);
    }

    public function testFetchActivityWithInvalidLabelFormat(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        
        $prResponse = json_encode([
            [
                'number' => 1,
                'title' => 'Invalid Labels',
                'state' => 'open',
                'user' => ['login' => 'tester'],
                'created_at' => '2023-06-15T12:00:00Z',
                'html_url' => 'http://github.com/pr/1',
                'labels' => [
                    'string_label',
                    ['name' => 'valid_label'],
                    ['no_name_key' => 'invalid'],
                ],
            ],
        ]);

        $httpClient->method('get')->willReturnCallback(function ($url) use ($prResponse) {
            if (str_contains($url, '/pulls?')) {
                return new HttpResponse(200, $prResponse);
            }
            return new HttpResponse(200, '[]');
        });

        $client = new GithubClient($this->rootPath, $httpClient);
        $result = $client->fetchActivity('2023-01-01', '2023-12-31');

        $this->assertSame(200, $result['status']);
        $this->assertContains('valid_label', $result['data'][0]['details']['labels']);
        $this->assertCount(1, $result['data'][0]['details']['labels']);
    }

    public function testFetchActivityWithCommitsAndReviewsError(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        
        $prResponse = json_encode([
            [
                'number' => 1,
                'title' => 'Test PR',
                'state' => 'open',
                'user' => ['login' => 'tester'],
                'created_at' => '2023-06-15T12:00:00Z',
                'html_url' => 'http://github.com/pr/1',
            ],
        ]);

        $httpClient->method('get')->willReturnCallback(function ($url) use ($prResponse) {
            if (str_contains($url, '/pulls?')) {
                return new HttpResponse(200, $prResponse);
            }
            if (str_contains($url, '/commits')) {
                return new HttpResponse(500, 'Server Error');
            }
            if (str_contains($url, '/reviews')) {
                return new HttpResponse(403, 'Forbidden');
            }
            return new HttpResponse(404, '{}');
        });

        $client = new GithubClient($this->rootPath, $httpClient);
        $result = $client->fetchActivity('2023-01-01', '2023-12-31');

        $this->assertSame(200, $result['status']);
        $this->assertSame(0, $result['data'][0]['details']['commit_count']);
        $this->assertSame(0, $result['data'][0]['details']['review_count']);
    }

    public function testFetchActivityWithZeroPRNumber(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        
        $prResponse = json_encode([
            [
                'number' => 0,
                'title' => 'Zero Number PR',
                'state' => 'open',
                'user' => ['login' => 'tester'],
                'created_at' => '2023-06-15T12:00:00Z',
                'html_url' => 'http://github.com/pr/0',
            ],
        ]);

        $httpClient->method('get')->willReturnCallback(function ($url) use ($prResponse) {
            if (str_contains($url, '/pulls?')) {
                return new HttpResponse(200, $prResponse);
            }
            return new HttpResponse(200, '[]');
        });

        $client = new GithubClient($this->rootPath, $httpClient);
        $result = $client->fetchActivity('2023-01-01', '2023-12-31');

        $this->assertSame(200, $result['status']);
        $this->assertSame(0, $result['data'][0]['details']['commit_count']);
    }

    public function testFetchActivityWithReviewersWithEmptyLogin(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        
        $prResponse = json_encode([
            [
                'number' => 1,
                'title' => 'Test PR',
                'state' => 'open',
                'user' => ['login' => 'tester'],
                'created_at' => '2023-06-15T12:00:00Z',
                'html_url' => 'http://github.com/pr/1',
            ],
        ]);

        $reviewsResponse = json_encode([
            ['user' => ['login' => 'reviewer1']],
            ['user' => ['login' => '']],
            ['user' => null],
            ['user' => ['login' => 'reviewer1']],
        ]);

        $httpClient->method('get')->willReturnCallback(function ($url) use ($prResponse, $reviewsResponse) {
            if (str_contains($url, '/pulls?')) {
                return new HttpResponse(200, $prResponse);
            }
            if (str_contains($url, '/commits')) {
                return new HttpResponse(200, '[]');
            }
            if (str_contains($url, '/reviews')) {
                return new HttpResponse(200, $reviewsResponse);
            }
            return new HttpResponse(404, '{}');
        });

        $client = new GithubClient($this->rootPath, $httpClient);
        $result = $client->fetchActivity('2023-01-01', '2023-12-31');

        $this->assertSame(200, $result['status']);
        $this->assertSame(4, $result['data'][0]['details']['review_count']);
        $this->assertContains('reviewer1', $result['data'][0]['details']['reviewers']);
        $this->assertCount(1, $result['data'][0]['details']['reviewers']);
    }

    public function testFetchActivityMetaLineWithReviewers(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        
        $prResponse = json_encode([
            [
                'number' => 1,
                'title' => 'Test PR',
                'state' => 'open',
                'user' => ['login' => 'author'],
                'created_at' => '2023-06-15T12:00:00Z',
                'html_url' => 'http://github.com/pr/1',
            ],
        ]);

        $httpClient->method('get')->willReturnCallback(function ($url) use ($prResponse) {
            if (str_contains($url, '/pulls?')) {
                return new HttpResponse(200, $prResponse);
            }
            if (str_contains($url, '/commits')) {
                return new HttpResponse(200, '[{"sha":"abc"}]');
            }
            if (str_contains($url, '/reviews')) {
                return new HttpResponse(200, '[{"user":{"login":"reviewer1"}}]');
            }
            return new HttpResponse(404, '{}');
        });

        $client = new GithubClient($this->rootPath, $httpClient);
        $result = $client->fetchActivity('2023-01-01', '2023-12-31');

        $this->assertStringContainsString('Commits: 1', $result['data'][0]['meta']);
        $this->assertStringContainsString('Reviews: 1', $result['data'][0]['meta']);
        $this->assertStringContainsString('Reviewers: reviewer1', $result['data'][0]['meta']);
    }

    public function testFetchActivityWithMissingUserLogin(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        
        $prResponse = json_encode([
            [
                'number' => 1,
                'title' => 'No Author PR',
                'state' => 'closed',
                'created_at' => '2023-06-15T12:00:00Z',
                'html_url' => 'http://github.com/pr/1',
            ],
        ]);

        $httpClient->method('get')->willReturnCallback(function ($url) use ($prResponse) {
            if (str_contains($url, '/pulls?')) {
                return new HttpResponse(200, $prResponse);
            }
            return new HttpResponse(200, '[]');
        });

        $client = new GithubClient($this->rootPath, $httpClient);
        $result = $client->fetchActivity('2023-01-01', '2023-12-31');

        $this->assertStringContainsString('desconocido', $result['data'][0]['subtitle']);
    }

    public function testEnvvReturnsValueWhenSet(): void
    {
        $uniqueKey = 'TEST_VAR_' . uniqid();
        putenv("$uniqueKey=test_value");

        $result = GithubClient::envv($uniqueKey, 'default');

        $this->assertSame('test_value', $result);

        putenv($uniqueKey);
    }

    public function testEnvvReturnsDefaultWhenEmpty(): void
    {
        $uniqueKey = 'TEST_EMPTY_VAR_' . uniqid();
        putenv("$uniqueKey=");

        $result = GithubClient::envv($uniqueKey, 'default');

        $this->assertSame('default', $result);

        putenv($uniqueKey);
    }

    public function testListRepositoryContentsWithEmptyPath(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        
        $httpClient->expects($this->once())
            ->method('get')
            ->with($this->callback(function ($url) {
                return str_ends_with($url, '/contents');
            }))
            ->willReturn(new HttpResponse(200, '[]'));

        $client = new GithubClient($this->rootPath, $httpClient);
        $result = $client->listRepositoryContents('');

        $this->assertTrue($result['ok']);
    }

    public function testListRepositoryContentsWithTrailingSlash(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        
        $httpClient->method('get')->willReturn(new HttpResponse(200, '[]'));

        $client = new GithubClient($this->rootPath, $httpClient);
        $result = $client->listRepositoryContents('/src/');

        $this->assertTrue($result['ok']);
    }
}
