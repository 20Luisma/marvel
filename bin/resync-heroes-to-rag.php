<?php

declare(strict_types=1);

// Sincroniza todos los héroes locales con el microservicio RAG mediante el endpoint interno de upsert.
// Uso: php bin/resync-heroes-to-rag.php

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

foreach ($heroes as $hero) {
    try {
        $ragSyncer->sync($hero);
        $synced++;
    } catch (\Throwable $exception) {
        fwrite(STDERR, "[warn] Falló sync para {$hero->heroId()}: {$exception->getMessage()}\n");
    }
}

fwrite(STDOUT, "[ok] Sincronización RAG completada. Heroes: {$synced}/{$total}\n");
