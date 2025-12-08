<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Dev\Test\PhpUnitTestRunner;
use App\Shared\Http\JsonResponse;

final class DevController
{
    public function __construct(private readonly PhpUnitTestRunner $testRunner)
    {
    }

    public function runTests(): void
    {
        $result = $this->testRunner->run();

        if (($result['status'] ?? null) === 'skipped') {
            JsonResponse::error($result['message'] ?? 'La ejecución de tests está deshabilitada.', 403);
            return;
        }

        if (($result['status'] ?? null) === 'error') {
            JsonResponse::error($result['message'] ?? 'Error al ejecutar la suite de tests.', 500);
            return;
        }

        JsonResponse::success($result);
    }
}
