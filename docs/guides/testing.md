# Guía de testing

La calidad del proyecto se mantiene mediante PHPUnit, PHPStan y Composer scripts definidos en `composer.json`.

## Suites disponibles

- `vendor/bin/phpunit --colors=always`: ejecuta toda la suite de tests (`tests/Albums`, `tests/Heroes`, `tests/Notifications`, `tests/Shared`, `tests/Unit`).  
- `composer test`: alias configurado en `composer.json` (normalmente ejecuta `phpunit`).  
- `composer test:cov`: genera el reporte de cobertura (`build/coverage.xml`), utilizado por SonarCloud.  
- `vendor/bin/phpstan analyse --memory-limit=512M`: análisis estático con nivel 6 (`phpstan.neon`), excluyendo `src/Dev`.

## Workflow recomendado

1. Instala dependencias (`composer install`).  
2. Ejecuta `vendor/bin/phpstan analyse` para atrapar errores antes de correr pruebas.  
3. Corre `composer test` o `vendor/bin/phpunit --colors=always`.  
4. Si se requiere, genera cobertura con `composer test:cov` y sube `build/coverage.xml` como artefacto (no se sube `vendor/`).

## Recursos adicionales

- `tests/bootstrap.php` configura el autoload de PHPUnit; actualízalo solo si cambias `autoload` en `composer.json`.  
- El runner HTTP `/dev/tests/run` (controller `DevController`) permite ejecutar PHPUnit desde la interfaz (ideal para QA rápida dentro del panel de álbumes).  
- Conserva los logs de fallos (`storage/logs/*` o `coverage.xml`) como evidencia de pruebas fallidas en PRs.
