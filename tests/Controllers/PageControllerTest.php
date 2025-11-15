<?php

declare(strict_types=1);

namespace Tests\Controllers;

use PHPUnit\Framework\TestCase;
use Src\Controllers\PageController;

final class PageControllerTest extends TestCase
{
    private PageController $controller;

    protected function setUp(): void
    {
        $this->controller = new PageController();
        http_response_code(200);
        $_SERVER['HTTP_ACCEPT'] = '';
    }

    public function testRenderIfHtmlRouteRejectsNonGet(): void
    {
        $result = $this->controller->renderIfHtmlRoute('POST', '/albums');

        self::assertFalse($result);
    }

    public function testRenderIfHtmlRouteRendersRootWithoutHtmlAccept(): void
    {
        ob_start();
        $result = $this->controller->renderIfHtmlRoute('GET', '/');
        $contents = (string) ob_get_clean();

        self::assertTrue($result);
        self::assertStringContainsString('<!DOCTYPE html>', $contents);
    }

    public function testRenderIfHtmlRouteRequiresHtmlAcceptForOtherPaths(): void
    {
        $_SERVER['HTTP_ACCEPT'] = 'application/json';
        $result = $this->controller->renderIfHtmlRoute('GET', '/heroes');
        self::assertFalse($result, 'Should not render when HTML is not requested.');

        $_SERVER['HTTP_ACCEPT'] = 'text/html,application/xhtml+xml';
        ob_start();
        $rendered = $this->controller->renderIfHtmlRoute('GET', '/comic');
        $contents = (string) ob_get_clean();

        self::assertTrue($rendered);
        self::assertStringContainsString('btn btn-primary', $contents);
    }

    public function testRenderNotFoundOutputsCustomPage(): void
    {
        ob_start();
        $this->controller->renderNotFound();
        $contents = (string) ob_get_clean();

        self::assertSame(404, http_response_code());
        self::assertStringContainsString('La ruta solicitada no existe', $contents);
        self::assertStringContainsString('Volver al inicio', $contents);
    }
}
