<?php

declare(strict_types=1);

namespace Creawebes\Rag\Application\Contracts;

interface LlmClientInterface
{
    public function ask(string $prompt): string;
}
