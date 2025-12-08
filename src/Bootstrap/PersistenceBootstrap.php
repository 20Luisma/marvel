<?php

declare(strict_types=1);

namespace App\Bootstrap;

use App\Albums\Infrastructure\Persistence\DbAlbumRepository;
use App\Albums\Infrastructure\Persistence\FileAlbumRepository;
use App\Heroes\Infrastructure\Persistence\DbHeroRepository;
use App\Heroes\Infrastructure\Persistence\FileHeroRepository;
use App\Bootstrap\Shared\DriverResolver;
use App\Shared\Infrastructure\Persistence\PdoConnectionFactory;
use Throwable;

final class PersistenceBootstrap
{
    /**
        * @return array{albumRepository: object, heroRepository: object, pdo: object|null}
        */
    public static function initialize(string $appEnv): array
    {
        $rootPath = dirname(__DIR__, 2);

        $albumDriver = DriverResolver::resolve('ALBUMS_DRIVER', $appEnv);
        $heroDriver = DriverResolver::resolve('HEROES_DRIVER', $appEnv);
        $activityDriver = DriverResolver::resolve('ACTIVITY_DRIVER', $appEnv);

        if (defined('PHPUNIT_RUNNING')) {
            $albumDriver = $heroDriver = $activityDriver = 'file';
        }

        $useDatabase = in_array('db', [$albumDriver, $heroDriver, $activityDriver], true);

        $pdo = null;

        if ($useDatabase) {
            try {
                $pdo = PdoConnectionFactory::fromEnvironment();
            } catch (Throwable $e) {
                $envForLogs = $_ENV['APP_ENV'] ?? getenv('APP_ENV') ?: '';
                if (PHP_SAPI !== 'cli' && !defined('PHPUNIT_RUNNING') && $envForLogs !== 'test') {
                    error_log('Fallo al abrir conexión PDO, se usará JSON: ' . $e->getMessage());
                }
                $pdo = null;
                if ($albumDriver === 'db') {
                    $albumDriver = 'file';
                }
                if ($heroDriver === 'db') {
                    $heroDriver = 'file';
                }
                if ($activityDriver === 'db') {
                    $activityDriver = 'file';
                }
            }
        }

        $storagePath = $rootPath . '/storage';

        $albumRepository = ($albumDriver === 'db' && $pdo !== null)
            ? new DbAlbumRepository($pdo)
            : new FileAlbumRepository($storagePath . '/albums.json');

        $heroRepository = ($heroDriver === 'db' && $pdo !== null)
            ? new DbHeroRepository($pdo)
            : new FileHeroRepository($storagePath . '/heroes.json');

        return [
            'albumRepository' => $albumRepository,
            'heroRepository' => $heroRepository,
            'pdo' => $pdo,
        ];
    }
}
