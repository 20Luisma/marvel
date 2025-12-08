<?php

declare(strict_types=1);

namespace Tests\Views;

use App\Services\GithubClient;
use PHPUnit\Framework\TestCase;

final class PanelGithubViewTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($GLOBALS['__github_client_factory']);
        $_GET = [];
        $_SERVER = [];
        parent::tearDown();
    }

    public function testLazyModeRendersWithoutClient(): void
    {
        if (!defined('PANEL_GITHUB_TEST')) {
            define('PANEL_GITHUB_TEST', true);
        }
        $_GET['lazy'] = '1';
        $_SERVER['HTTP_ACCEPT'] = 'text/html';

        $html = $this->renderPanel();

        self::assertStringContainsString('Reporte de Pull Requests', $html);
    }

    public function testErrorStateRendersMessage(): void
    {
        if (!defined('PANEL_GITHUB_TEST')) {
            define('PANEL_GITHUB_TEST', true);
        }
        $_SERVER['HTTP_ACCEPT'] = 'text/html';

        $fake = new class {
            public function fetchActivity(string $from, string $to): array
            {
                return ['error' => 'api fail'];
            }
        };
        $GLOBALS['__github_client_factory'] = static fn () => $fake;

        $html = $this->renderPanel();

        self::assertStringContainsString('Sin datos del API.', $html);
        self::assertStringContainsString('api fail', $html);
    }

    private function renderPanel(): string
    {
        $level = ob_get_level();
        ob_start();
        require dirname(__DIR__, 2) . '/views/pages/panel-github.php';
        $html = (string) ob_get_clean();
        while (ob_get_level() > $level) {
            ob_end_clean();
        }
        return $html;
    }
}
