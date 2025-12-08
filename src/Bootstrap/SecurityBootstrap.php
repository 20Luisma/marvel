<?php

declare(strict_types=1);

namespace App\Bootstrap;

use App\Application\Security\IpBlockerService;
use App\Application\Security\LoginAttemptService;
use App\Security\Auth\AuthService;
use App\Security\Http\ApiFirewall;
use App\Security\Http\AuthMiddleware;
use App\Security\Http\CsrfTokenManager;
use App\Security\Http\RateLimitMiddleware;
use App\Security\Logging\SecurityLogger;
use App\Security\RateLimit\RateLimiter;
use App\Security\Session\SessionReplayMonitor;

final class SecurityBootstrap
{
    /**
     * @return array<string, mixed>
     */
    public static function initialize(string $appEnvironment): array
    {
        $csrfTokenManager = new CsrfTokenManager($appEnvironment);
        $securityLogger = new SecurityLogger();
        $replayMonitor = new SessionReplayMonitor($securityLogger);
        $authService = new AuthService(config: null, logger: $securityLogger, replayMonitor: $replayMonitor);
        $authService->enforceSessionSecurity();
        $replayMonitor->detectReplayAttack();
        self::initializeAntiReplay($securityLogger);

        $envInternalKey = $_ENV['INTERNAL_API_KEY'] ?? getenv('INTERNAL_API_KEY');
        $internalApiKey = is_string($envInternalKey) ? trim($envInternalKey) : '';

        $rateLimitEnabledRaw = $_ENV['RATE_LIMIT_ENABLED'] ?? getenv('RATE_LIMIT_ENABLED');
        if (!is_string($rateLimitEnabledRaw) || $rateLimitEnabledRaw === '') {
            $rateLimitEnabledRaw = 'true';
        }
        $rateLimitEnabled = $rateLimitEnabledRaw !== 'false';

        $defaultMaxRaw = $_ENV['RATE_LIMIT_DEFAULT_MAX_REQUESTS'] ?? getenv('RATE_LIMIT_DEFAULT_MAX_REQUESTS');
        if (!is_numeric($defaultMaxRaw)) {
            $defaultMaxRaw = 60;
        }
        $defaultMax = (int) $defaultMaxRaw;

        $defaultWindowRaw = $_ENV['RATE_LIMIT_DEFAULT_WINDOW_SECONDS'] ?? getenv('RATE_LIMIT_DEFAULT_WINDOW_SECONDS');
        if (!is_numeric($defaultWindowRaw)) {
            $defaultWindowRaw = 60;
        }
        $defaultWindow = (int) $defaultWindowRaw;
        $routeLimits = [
            '/login' => ['max' => 10, 'window' => 60],
            '/api/rag/heroes' => ['max' => 20, 'window' => 60],
            '/agentia' => ['max' => 20, 'window' => 60],
        ];

        $rateLimiter = new RateLimiter(
            enabled: $rateLimitEnabled,
            defaultMaxRequests: $defaultMax > 0 ? $defaultMax : 60,
            defaultWindowSeconds: $defaultWindow > 0 ? $defaultWindow : 60,
            routeLimits: $routeLimits
        );

        $loginAttemptService = new LoginAttemptService($securityLogger);
        $ipBlockerService = new IpBlockerService($loginAttemptService, $securityLogger);

        return [
            'security' => [
                'auth' => $authService,
                'csrf' => $csrfTokenManager,
                'middleware' => new AuthMiddleware($authService),
                'internal_api_key' => $internalApiKey !== '' ? $internalApiKey : null,
                'rateLimiter' => $rateLimiter,
                'rateLimitMiddleware' => new RateLimitMiddleware($rateLimiter, $routeLimits, $securityLogger),
                'apiFirewall' => new ApiFirewall($securityLogger),
                'logger' => $securityLogger,
                'ipBlocker' => $ipBlockerService,
                'loginAttemptService' => $loginAttemptService,
                'replayMonitor' => $replayMonitor,
            ],
        ];
    }

    private static function initializeAntiReplay(SecurityLogger $logger): void
    {
        $traceIdRaw = $_SERVER['X_TRACE_ID'] ?? null;
        $traceId = is_string($traceIdRaw) ? $traceIdRaw : '';
        $ip = is_string($_SERVER['REMOTE_ADDR'] ?? null) ? (string) $_SERVER['REMOTE_ADDR'] : 'unknown';
        $uaRaw = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $ua = is_string($uaRaw) ? preg_replace('/[\\x00-\\x1F\\x7F]/', '', $uaRaw) : '';
        $ua = is_string($ua) ? substr($ua, 0, 200) : '';
        $path = $_SERVER['REQUEST_URI'] ?? 'unknown';

        $securityLogPath = self::securityLogPath($logger);
        if (!is_dir(dirname($securityLogPath))) {
            @mkdir(dirname($securityLogPath), 0775, true);
        }

        if (empty($_SESSION['session_replay_token'])) {
            $_SESSION['session_replay_token'] = bin2hex(random_bytes(32));
            error_log(
                "[" . date('Y-m-d H:i:s') . "] event=session_replay_token_issued trace_id={$traceId} ip={$ip} path={$path} user_agent={$ua} timestamp=" . time() . "\n",
                3,
                $securityLogPath
            );
        }

        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            $header = $_SERVER['HTTP_X_SESSION_REPLAY'] ?? null;
            $expected = $_SESSION['session_replay_token'] ?? null;

            if (!$header) {
                error_log(
                    "[" . date('Y-m-d H:i:s') . "] event=session_replay_missing_soft trace_id={$traceId} ip={$ip} path={$path} user_agent={$ua} timestamp=" . time() . "\n",
                    3,
                    $securityLogPath
                );
            } elseif (!is_string($expected) || $header !== $expected) {
                error_log(
                    "[" . date('Y-m-d H:i:s') . "] event=session_replay_mismatch_soft trace_id={$traceId} ip={$ip} path={$path} user_agent={$ua} timestamp=" . time() . "\n",
                    3,
                    $securityLogPath
                );
            } else {
                error_log(
                    "[" . date('Y-m-d H:i:s') . "] event=session_replay_valid_soft trace_id={$traceId} ip={$ip} path={$path} user_agent={$ua} timestamp=" . time() . "\n",
                    3,
                    $securityLogPath
                );
            }
        }
    }

    private static function securityLogPath(SecurityLogger $logger): string
    {
        $extractPath = static fn(SecurityLogger $l): string => (function (): string {
            return $this->logFile;
        })->call($l);

        return $extractPath($logger);
    }
}
