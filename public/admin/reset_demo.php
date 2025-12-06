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
use Dotenv\Dotenv;
use Throwable;

$rootPath = dirname(__DIR__, 2);

require_once $rootPath . '/vendor/autoload.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Cargar .env si está disponible (vía vlucas/phpdotenv o fallback manual)
if (class_exists(Dotenv::class)) {
    Dotenv::createImmutable($rootPath)->safeLoad();
} else {
    $envFile = $rootPath . '/.env';
    if (is_file($envFile)) {
        foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }
            [$k, $v] = array_map('trim', explode('=', $line, 2) + [1 => '']);
            if ($k !== '') {
                $_ENV[$k] = $v;
                $_SERVER[$k] = $v;
                putenv($k . '=' . $v);
            }
        }
    }
}

if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

$demoModeRaw = getenv('DEMO_MODE') !== false ? getenv('DEMO_MODE') : ($_ENV['DEMO_MODE'] ?? null);
$demoEnabled = filter_var($demoModeRaw, FILTER_VALIDATE_BOOLEAN);

if (!$demoEnabled) {
    http_response_code(403);
    exit('Forbidden');
}

try {
    $pdo = PdoConnectionFactory::fromEnvironment();
} catch (Throwable $e) {
    http_response_code(500);
    exit('No se pudo conectar a la base de datos: ' . $e->getMessage());
}

$tablePrefix = (string) ($GLOBALS['__clean_marvel_table_prefix'] ?? ($_ENV['DB_TABLE_PREFIX'] ?? getenv('DB_TABLE_PREFIX') ?: ''));
$tablePrefix = preg_replace('/[^A-Za-z0-9_]/', '', $tablePrefix ?? '') ?? '';

$tableName = static fn(string $base): string => $tablePrefix . $base;

try {
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    $pdo->exec(sprintf('TRUNCATE TABLE `%s`', $tableName('albums')));
    $pdo->exec(sprintf('TRUNCATE TABLE `%s`', $tableName('heroes')));
    $pdo->exec(sprintf('TRUNCATE TABLE `%s`', $tableName('activity_logs')));
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
} catch (Throwable $e) {
    http_response_code(500);
    exit('No se pudo vaciar la base de datos: ' . $e->getMessage());
}

$fileAlbumRepo = new FileAlbumRepository($rootPath . '/storage/albums.json');
$fileHeroRepo = new FileHeroRepository($rootPath . '/storage/heroes.json');
$fileActivityRepo = new FileActivityLogRepository($rootPath . '/storage/actividad');

$dbAlbumRepo = new DbAlbumRepository($pdo);
$dbHeroRepo = new DbHeroRepository($pdo);
$dbActivityRepo = new DbActivityLogRepository($pdo);

$albumCount = 0;
$heroCount = 0;
$activityCount = 0;

foreach ($fileAlbumRepo->all() as $album) {
    $dbAlbumRepo->save($album);
    $albumCount++;
}

$heroes = $fileHeroRepo->all();
foreach ($heroes as $hero) {
    $dbHeroRepo->save($hero);
    $heroCount++;
}

$heroContextIds = array_map(
    static fn($hero) => $hero->heroId(),
    $heroes
);

$appendActivities = static function (string $scope, ?string $contextId) use ($fileActivityRepo, $dbActivityRepo, &$activityCount): void {
    foreach ($fileActivityRepo->all($scope, $contextId) as $entry) {
        $dbActivityRepo->append($entry);
        $activityCount++;
    }
};

$appendActivities(ActivityScope::ALBUMS, null);
$appendActivities(ActivityScope::COMIC, null);

foreach ($heroContextIds as $contextId) {
    $appendActivities(ActivityScope::HEROES, $contextId);
}

$_SESSION['flash_message'] = sprintf(
    'Demo regenerada: %d álbumes, %d héroes, %d actividad.',
    $albumCount,
    $heroCount,
    $activityCount
);

header('Location: /albums');
exit;
