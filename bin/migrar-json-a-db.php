#!/usr/bin/env php
<?php

declare(strict_types=1);

use App\Activities\Domain\ActivityScope;
use App\Activities\Infrastructure\Persistence\DbActivityLogRepository;
use App\Activities\Infrastructure\Persistence\FileActivityLogRepository;
use App\Albums\Infrastructure\Persistence\DbAlbumRepository;
use App\Albums\Infrastructure\Persistence\FileAlbumRepository;
use App\Heroes\Infrastructure\Persistence\DbHeroRepository;
use App\Heroes\Infrastructure\Persistence\FileHeroRepository;
use App\Shared\Infrastructure\Persistence\PdoConnectionFactory;
use PDO;
use Throwable;

$rootPath = dirname(__DIR__);

// === Autoload ===
require $rootPath . '/vendor/autoload.php';

// === Cargar .env manualmente (por si no lo hace bootstrap) ===
loadEnv($rootPath . '/.env');

$isCli = (PHP_SAPI === 'cli');

// Mostrar errores en hosting mientras migramos
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

if (!$isCli) {
    header('Content-Type: text/plain; charset=utf-8');
}

// Helper para escribir salida según contexto
function out(string $message, bool $isCli = false): void
{
    $message = rtrim($message, "\n") . "\n";

    if ($isCli) {
        echo $message;
    } else {
        echo $message;
    }
}

try {
    $pdo = PdoConnectionFactory::fromEnvironment();
} catch (Throwable $e) {
    out('❌ No se pudo conectar a la base de datos: ' . $e->getMessage(), $isCli);
    exit(1);
}

// Repositorios FILE (JSON)
$fileAlbumRepo    = new FileAlbumRepository($rootPath . '/storage/albums.json');
$fileHeroRepo     = new FileHeroRepository($rootPath . '/storage/heroes.json');
$fileActivityRepo = new FileActivityLogRepository($rootPath . '/storage/actividad');

// Repositorios DB
$dbAlbumRepo    = new DbAlbumRepository($pdo);
$dbHeroRepo     = new DbHeroRepository($pdo);
$dbActivityRepo = new DbActivityLogRepository($pdo);

$counters = [
    'albums'     => 0,
    'heroes'     => 0,
    'activities' => 0,
];

// === Migrar álbumes ===
foreach ($fileAlbumRepo->all() as $album) {
    $dbAlbumRepo->save($album);
    $counters['albums']++;
}

// === Migrar héroes ===
foreach ($fileHeroRepo->all() as $hero) {
    $dbHeroRepo->save($hero);
    $counters['heroes']++;
}

// Para actividad de héroes necesitamos todos los IDs de héroes
$contextIds = array_map(
    static fn($hero) => $hero->heroId(),
    $fileHeroRepo->all()
);

// === Migrar actividad (álbumes, cómic, héroes) ===
migrateActivityScope(ActivityScope::ALBUMS, null, $fileActivityRepo, $dbActivityRepo, $pdo, $counters);
migrateActivityScope(ActivityScope::COMIC,  null, $fileActivityRepo, $dbActivityRepo, $pdo, $counters);

foreach ($contextIds as $contextId) {
    migrateActivityScope(ActivityScope::HEROES, $contextId, $fileActivityRepo, $dbActivityRepo, $pdo, $counters);
}

// === Resumen ===
out("✅ Migración completada:", $isCli);
out("  - Álbumes:    {$counters['albums']}", $isCli);
out("  - Héroes:     {$counters['heroes']}", $isCli);
out("  - Actividad:  {$counters['activities']}", $isCli);

if (!$isCli) {
    out("\nYa puedes recargar la página /albums en el hosting. 😉", $isCli);
}

/**
 * @param string $scope
 * @param string|null $contextId
 */
function migrateActivityScope(
    string $scope,
    ?string $contextId,
    FileActivityLogRepository $fileRepo,
    DbActivityLogRepository $dbRepo,
    PDO $pdo,
    array &$counters
): void {
    $entries = $fileRepo->all($scope, $contextId);

    foreach ($entries as $entry) {
        if (!activityExists(
            $pdo,
            $scope,
            $contextId,
            $entry->action(),
            $entry->title(),
            $entry->occurredAt()->format('Y-m-d H:i:s.u')
        )) {
            $dbRepo->append($entry);
            $counters['activities']++;
        }
    }
}

function activityExists(
    PDO $pdo,
    string $scope,
    ?string $contextId,
    string $action,
    string $title,
    string $occurredAt
): bool {
    $sql = 'SELECT 1 FROM activity_logs 
            WHERE scope = :scope 
              AND action = :action 
              AND title = :title 
              AND occurred_at = :occurred_at';

    $params = [
        'scope'       => $scope,
        'action'      => $action,
        'title'       => $title,
        'occurred_at' => $occurredAt,
    ];

    if ($contextId !== null) {
        $sql .= ' AND context_id = :context_id';
        $params['context_id'] = $contextId;
    } else {
        $sql .= ' AND context_id IS NULL';
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return (bool) $stmt->fetchColumn();
}

/**
 * Carga un .env plano KEY=VALUE a $_ENV y getenv()
 */
function loadEnv(string $envPath): void
{
    if (!is_file($envPath)) {
        return;
    }

    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];

    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#')) {
            continue;
        }

        [$key, $value] = array_map('trim', explode('=', $line, 2) + [1 => '']);

        if ($key !== '') {
            $_ENV[$key] = $value;
            putenv($key . '=' . $value);
        }
    }
}
