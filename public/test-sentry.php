<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/bootstrap.php';

// Lanzamos una excepción SIN capturar para que la recoja el handler global
throw new \Exception('Probando Sentry desde Clean Marvel Album (backend, uncaught)');
