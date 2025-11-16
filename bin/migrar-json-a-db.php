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

require dirname(__DIR__) . '/vendor/autoload.php';

$rootPath = dirname(__DIR__);

loadEnv($rootPath . '/.env');

try {
    $pdo = PdoConnectionFactory::fromEnvironment();
} catch (Throwable $e) {
    fwrite(STDERR, "❌ No se pudo conectar a la base de datos: {$e->getMessage()}\n");
    exit(1);
}

$fileAlbumRepo = new FileAlbumRepository($rootPath . '/storage/albums.json');
$fileHeroRepo = new FileHeroRepository($rootPath . '/storage/heroes.json');
$fileActivityRepo = new FileActivityLogRepository($rootPath . '/storage/actividad');

$dbAlbumRepo = new DbAlbumRepository($pdo);
$dbHeroRepo = new DbHeroRepository($pdo);
$dbActivityRepo = new DbActivityLogRepository($pdo);

$counters = [
    'albums' => 0,
    'heroes' => 0,
    'activities' => 0,
];

foreach ($fileAlbumRepo->all() as $album) {
    $dbAlbumRepo->save($album);
    $counters['albums']++;
}

foreach ($fileHeroRepo->all() as $hero) {
    $dbHeroRepo->save($hero);
    $counters['heroes']++;
}

$contextIds = array_map(static fn ($hero) => $hero->heroId(), $fileHeroRepo->all());

migrateActivityScope(ActivityScope::ALBUMS, null, $fileActivityRepo, $dbActivityRepo, $pdo, $counters);
migrateActivityScope(ActivityScope::COMIC, null, $fileActivityRepo, $dbActivityRepo, $pdo, $counters);
foreach ($contextIds as $contextId) {
    migrateActivityScope(ActivityScope::HEROES, $contextId, $fileActivityRepo, $dbActivityRepo, $pdo, $counters);
}

echo "✅ Migración completada:\n";
echo "  - Álbumes: {$counters['albums']}\n";
echo "  - Héroes: {$counters['heroes']}\n";
echo "  - Actividades: {$counters['activities']}\n";

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
        if (!activityExists($pdo, $scope, $contextId, $entry->action(), $entry->title(), $entry->occurredAt()->format('Y-m-d H:i:s.u'))) {
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
    $sql = 'SELECT 1 FROM activity_logs WHERE scope = :scope AND action = :action AND title = :title AND occurred_at = :occurred_at';
    $params = [
        'scope' => $scope,
        'action' => $action,
        'title' => $title,
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
 * @param string $envPath
 */
function loadEnv(string $envPath): void
{
    if (!is_file($envPath)) {
        return;
    }

    foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        if (str_starts_with($line, '#')) {
            continue;
        }

        [$key, $value] = array_map('trim', explode('=', $line, 2) + [1 => '']);
        if ($key !== '') {
            $_ENV[$key] = $value;
            putenv($key . '=' . $value);
        }
    }
}
