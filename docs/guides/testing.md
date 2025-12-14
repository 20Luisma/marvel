# Guía de testing

La calidad del proyecto se mantiene mediante PHPUnit, PHPStan y Composer scripts definidos en `composer.json`.

## Suites disponibles

- `vendor/bin/phpunit --colors=always`: ejecuta toda la suite de tests (`tests/Albums`, `tests/Heroes`, `tests/Notifications`, `tests/Shared`, `tests/Unit`).  
- `composer test`: alias configurado en `composer.json` (normalmente ejecuta `phpunit`).  
- `composer test:coverage`: genera el reporte de cobertura (`coverage.xml`), utilizado por SonarCloud.  
- `vendor/bin/phpstan analyse --memory-limit=512M`: análisis estático con nivel configurado en `phpstan.neon` (actualmente 7), excluyendo `src/Dev`.

## Entorno de pruebas (aislado)

- PHPUnit fuerza `APP_ENV=test` y `DB_DSN=sqlite::memory:` desde `tests/bootstrap.php` para no depender de `.env` ni de MySQL. Las credenciales DB quedan vacías y se usan repositorios JSON cuando falla PDO.
- Los avisos PHP se redirigen a un log temporal (`sys_get_temp_dir()/phpunit-clean-marvel.log`) y no se muestran en consola.
- Mocks/fakes sin red:
  - `__github_client_factory` + banderas `GITHUB_REPO_BROWSER_TEST` y `PANEL_GITHUB_TEST` permiten probar `/api/github-repo-browser.php` y `views/pages/panel-github.php` sin llamar a GitHub.
- Warnings esperados en consola: un mensaje de fallback PDO a JSON y un log de CSRF en tests de seguridad; no son fallos de producción.

## Workflow recomendado

1. Instala dependencias (`composer install`).  
2. Ejecuta `vendor/bin/phpstan analyse` para atrapar errores antes de correr pruebas.  
3. Corre `composer test` o `vendor/bin/phpunit --colors=always`.  
4. Si se requiere, genera cobertura con `composer test:coverage` y usa `coverage.xml` como artefacto (no se sube `vendor/`).

## Recursos adicionales

- `tests/bootstrap.php` configura el autoload de PHPUnit; actualízalo solo si cambias `autoload` en `composer.json`.  
- El runner HTTP `/dev/tests/run` (controller `DevController`) permite ejecutar PHPUnit desde la interfaz (ideal para QA rápida dentro del panel de álbumes).  
- Conserva los logs de fallos (`storage/logs/*` o `coverage.xml`) como evidencia de pruebas fallidas en PRs.
