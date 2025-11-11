<?php

declare(strict_types=1);

namespace App\Config;

final class ServiceUrlProvider
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(private readonly array $config)
    {
    }

    public function resolveEnvironment(?string $host = null): string
    {
        $env = $this->readEnvironmentVariable();
        if ($env !== null) {
            return $env;
        }

        $host ??= $_SERVER['HTTP_HOST'] ?? '';
        $host = $this->normalizeHost($host);

        foreach ($this->config['environments'] ?? [] as $environment => $settings) {
            if ($this->hostBelongsToEnvironment($host, (array) $settings)) {
                return (string) $environment;
            }
        }

        if ($host === '' || str_contains($host, 'localhost')) {
            return 'local';
        }

        return (string) ($this->config['default_environment'] ?? 'local');
    }

    public function getAppBaseUrl(?string $environment = null): string
    {
        $env = $this->ensureEnvironment($environment);

        return (string) ($this->config['environments'][$env]['app']['base_url'] ?? '');
    }

    public function getAppHost(?string $environment = null): string
    {
        $env = $this->ensureEnvironment($environment);

        return (string) ($this->config['environments'][$env]['app']['host'] ?? '');
    }

    public function getOpenAiBaseUrl(?string $environment = null): string
    {
        $env = $this->ensureEnvironment($environment);

        return (string) ($this->config['environments'][$env]['openai']['base_url'] ?? '');
    }

    public function getOpenAiChatUrl(?string $environment = null): string
    {
        $env = $this->ensureEnvironment($environment);

        $base = $this->getOpenAiBaseUrl($env);
        $configured = (string) ($this->config['environments'][$env]['openai']['chat_url'] ?? '');

        if ($configured !== '') {
            return $configured;
        }

        return $this->appendPath($base, '/v1/chat');
    }

    public function getOpenAiHost(?string $environment = null): string
    {
        $env = $this->ensureEnvironment($environment);

        return (string) ($this->config['environments'][$env]['openai']['host'] ?? '');
    }

    public function getRagBaseUrl(?string $environment = null): string
    {
        $env = $this->ensureEnvironment($environment);

        return (string) ($this->config['environments'][$env]['rag']['base_url'] ?? '');
    }

    public function getRagHeroesUrl(?string $environment = null): string
    {
        $env = $this->ensureEnvironment($environment);

        $base = $this->getRagBaseUrl($env);
        $configured = (string) ($this->config['environments'][$env]['rag']['heroes_url'] ?? '');

        if ($configured !== '') {
            return $configured;
        }

        return $this->appendPath($base, '/rag/heroes');
    }

    public function getRagHost(?string $environment = null): string
    {
        $env = $this->ensureEnvironment($environment);

        return (string) ($this->config['environments'][$env]['rag']['host'] ?? '');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArrayForFrontend(?string $host = null): array
    {
        $environment = $this->resolveEnvironment($host);

        return [
            'environment' => [
                'mode' => $environment,
                'host' => $this->normalizeHost($host ?? ($_SERVER['HTTP_HOST'] ?? '')),
            ],
            'services' => [
                'app' => [
                    'host' => $this->getAppHost($environment),
                    'baseUrl' => $this->getAppBaseUrl($environment),
                ],
                'openai' => [
                    'host' => $this->getOpenAiHost($environment),
                    'baseUrl' => $this->getOpenAiBaseUrl($environment),
                    'chatUrl' => $this->getOpenAiChatUrl($environment),
                ],
                'rag' => [
                    'host' => $this->getRagHost($environment),
                    'baseUrl' => $this->getRagBaseUrl($environment),
                    'heroesUrl' => $this->getRagHeroesUrl($environment),
                ],
            ],
            'availableEnvironments' => $this->config['environments'] ?? [],
        ];
    }

    private function appendPath(string $baseUrl, string $path): string
    {
        if ($baseUrl === '') {
            return trim($path);
        }

        return rtrim($baseUrl, '/') . '/' . ltrim($path, '/');
    }

    private function ensureEnvironment(?string $environment): string
    {
        $env = $environment ?? $this->resolveEnvironment();
        if (isset($this->config['environments'][$env])) {
            return $env;
        }

        return (string) ($this->config['default_environment'] ?? 'local');
    }

    private function readEnvironmentVariable(): ?string
    {
        $candidates = [
            $_ENV['APP_ENV'] ?? null,
            getenv('APP_ENV') ?: null,
        ];

        foreach ($candidates as $candidate) {
            if (!is_string($candidate)) {
                continue;
            }

            $trimmed = trim($candidate);
            if ($trimmed === '' || strcasecmp($trimmed, 'auto') === 0) {
                continue;
            }

            foreach (array_keys($this->config['environments'] ?? []) as $environmentKey) {
                if (strcasecmp((string) $environmentKey, $trimmed) === 0) {
                    return (string) $environmentKey;
                }
            }
        }

        return null;
    }

    private function normalizeHost(?string $host): string
    {
        $value = strtolower(trim((string) $host));

        if ($value === '') {
            return '';
        }

        $value = explode('/', $value)[0];
        return $value;
    }

    private function hostsMatch(string $currentHost, string $configuredHost): bool
    {
        if ($currentHost === $configuredHost) {
            return true;
        }

        $currentParts = explode(':', $currentHost);
        $configuredParts = explode(':', $configuredHost);

        if ($currentParts[0] === $configuredParts[0]) {
            return true;
        }

        return false;
    }

    /**
     * @param array<string, mixed> $settings
     */
    private function hostBelongsToEnvironment(string $host, array $settings): bool
    {
        foreach (['app', 'openai', 'rag'] as $serviceKey) {
            $serviceHost = $this->normalizeHost((string) ($settings[$serviceKey]['host'] ?? ''));
            if ($serviceHost === '') {
                continue;
            }

            if ($this->hostsMatch($host, $serviceHost)) {
                return true;
            }
        }

        return false;
    }
}
