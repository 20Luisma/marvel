<?php

declare(strict_types=1);

namespace Creawebes\Rag\Infrastructure\Observability;

final class ServerTraceIdProvider
{
    private const SERVER_KEY = '__TRACE_ID__';

    public function getTraceId(): string
    {
        $existing = $_SERVER[self::SERVER_KEY] ?? null;
        if (is_string($existing) && $existing !== '') {
            return $existing;
        }

        $header = $_SERVER['HTTP_X_TRACE_ID'] ?? null;
        if (is_string($header)) {
            $candidate = trim($header);
            if ($candidate !== '' && preg_match('/^[A-Za-z0-9._-]{1,128}$/', $candidate) === 1) {
                $_SERVER[self::SERVER_KEY] = $candidate;
                return $candidate;
            }
        }

        try {
            $generated = bin2hex(random_bytes(16));
        } catch (\Throwable) {
            $generated = str_replace('.', '', uniqid('trace_', true));
        }

        $_SERVER[self::SERVER_KEY] = $generated;

        return $generated;
    }
}

