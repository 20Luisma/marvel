<?php

declare(strict_types=1);

// Sincroniza todos los héroes locales con el microservicio RAG mediante el endpoint interno de upsert.
// Uso:
//   php bin/resync-heroes-to-rag.php
//   APP_ENV=hosting php bin/resync-heroes-to-rag.php
//   php bin/resync-heroes-to-rag.php --env=hosting
//   php bin/resync-heroes-to-rag.php --hosting

/** @var array<int, string> $argv */
$envArg = null;
foreach ($argv ?? [] as $arg) {
    if (!is_string($arg)) {
        continue;
    }
    if ($arg === '--hosting') {
        $envArg = 'hosting';
        continue;
    }
    if ($arg === '--local') {
        $envArg = 'local';
        continue;
    }
    if (str_starts_with($arg, '--env=')) {
        $envArg = trim(substr($arg, 6));
        continue;
    }
}

if (is_string($envArg) && $envArg !== '') {
    $_ENV['APP_ENV'] = $envArg;
    putenv('APP_ENV=' . $envArg);
}

$safeMode = filter_var($_ENV['SAFE_MODE'] ?? getenv('SAFE_MODE') ?: null, FILTER_VALIDATE_BOOL) === true;

$root = dirname(__DIR__);
require_once $root . '/vendor/autoload.php';

$container = require $root . '/src/bootstrap.php';

$heroRepository = $container['heroRepository'] ?? null;
$ragSyncer = $container['services']['ragSyncer'] ?? null;

if (!$heroRepository || !method_exists($heroRepository, 'all')) {
    fwrite(STDERR, "[error] No se pudo obtener heroRepository\n");
    exit(1);
}

if (!$ragSyncer) {
    fwrite(STDERR, "[error] No hay syncer RAG disponible (services.ragSyncer)\n");
    exit(1);
}

$heroes = $heroRepository->all();
$total = count($heroes);
$synced = 0;

if ($safeMode) {
    fwrite(STDOUT, "[dry-run] SAFE_MODE=1 activo, no se enviarán upserts al RAG. Heroes: {$total}\n");
    exit(0);
}

foreach ($heroes as $hero) {
    try {
        $ragSyncer->sync($hero);
        $synced++;
    } catch (\Throwable $exception) {
        fwrite(STDERR, "[warn] Falló sync para {$hero->heroId()}: {$exception->getMessage()}\n");
    }
}

fwrite(STDOUT, "[ok] Sincronización RAG completada. Heroes: {$synced}/{$total}\n");
