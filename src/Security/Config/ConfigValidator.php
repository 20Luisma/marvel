<?php

declare(strict_types=1);

namespace App\Security\Config;

use App\Config\ServiceUrlProvider;
use RuntimeException;

final class ConfigValidator
{
    /**
     * @param array<string, mixed> $env
     */
    public function __construct(
        private readonly array $env,
        private readonly ?ServiceUrlProvider $serviceUrlProvider = null,
        private readonly ?string $environment = null,
    ) {
    }

    public function validate(): void
    {
        $errors = [];

        $appEnv = $this->readEnv('APP_ENV');
        if ($appEnv === '') {
            $errors[] = 'APP_ENV no puede estar vacío (usa local/hosting o un entorno válido).';
        }

        $openAiUrl = $this->readEnv('OPENAI_SERVICE_URL');
        if ($openAiUrl === '' && $this->serviceUrlProvider instanceof ServiceUrlProvider) {
            $openAiUrl = $this->serviceUrlProvider->getOpenAiChatUrl($this->environment);
        }
        $this->validateUrl($openAiUrl, 'OPENAI_SERVICE_URL o config/services.php[openai.chat_url]', $errors);

        $ragUrl = $this->readEnv('RAG_SERVICE_URL');
        if ($ragUrl === '' && $this->serviceUrlProvider instanceof ServiceUrlProvider) {
            $ragUrl = $this->serviceUrlProvider->getRagHeroesUrl($this->environment);
        }
        $this->validateUrl($ragUrl, 'RAG_SERVICE_URL o config/services.php[rag.heroes_url]', $errors);

        $heatmapBaseUrl = $this->readEnv('HEATMAP_API_BASE_URL');
        if ($heatmapBaseUrl !== '') {
            $this->validateUrl($heatmapBaseUrl, 'HEATMAP_API_BASE_URL', $errors, false);
        }

        if ($errors !== []) {
            $message = "Configuración inválida:\n - " . implode("\n - ", $errors);
            if ($this->isProduction()) {
                error_log($message);
            }
            throw new RuntimeException($message);
        }
    }

    private function isProduction(): bool
    {
        $env = strtolower($this->environment ?: $this->readEnv('APP_ENV'));

        return $env !== '' && $env !== 'local';
    }

    private function readEnv(string $key): string
    {
        if (array_key_exists($key, $this->env)) {
            $value = $this->env[$key];
        } else {
            $value = getenv($key) ?: '';
        }

        return trim((string) $value);
    }

    /**
     * @param list<string> $errors
     */
    private function validateUrl(?string $value, string $label, array &$errors, bool $required = true): void
    {
        $trimmed = trim((string) $value);

        if ($trimmed === '') {
            if ($required) {
                $errors[] = $label . ' es obligatorio y no puede estar vacío.';
            }
            return;
        }

        if (filter_var($trimmed, FILTER_VALIDATE_URL) === false) {
            $errors[] = $label . ' debe ser una URL válida.';
        }
    }
}
