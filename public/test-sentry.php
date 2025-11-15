<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/bootstrap.php';

// Lanzamos una excepción SIN capturar para que la recoja el handler global
throw new \Exception('Probando Sentry desde Clean Marvel Album (backend, uncaught)');
