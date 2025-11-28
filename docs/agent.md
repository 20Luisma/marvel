# Agente de IA para Clean Marvel Album

Este documento describe cómo debe operar el agente (Codex) dentro del repositorio. Complementa `AGENTS.md` en la raíz y el README.

## Propósito
- Mantener documentación, contexto y examples al día.
- Apoyar desarrollo de features sin romper contratos de dominio ni flujos de CI.
- Sincronizar app principal con microservicios (`openai-service`, `rag-service`) y paneles (GitHub, SonarCloud, Sentry, accesibilidad, performance, heatmap, repo).

## Contexto clave del proyecto
- **Arquitectura Limpia en PHP 8.2+**: Presentación → Aplicación → Dominio; infraestructura desacoplada (repos JSON/DB, EventBus).
- **Persistencia**: JSON en local (`APP_ENV=local`), PDO MySQL en hosting con fallback a JSON.
- **Microservicios IA**: `openai-service` (`POST /v1/chat`, 8081, fallback JSON) y `rag-service` (`POST /rag/heroes`, 8082, incluye flujo agent con embeddings/KB). Scripts en `rag-service/bin/*`.
- **Heatmap service**: Python/Flask en contenedor (GCP), proxy en `/api/heatmap/*` con `HEATMAP_API_TOKEN`; guía en `docs/microservicioheatmap/README.md`.
- **Servicios externos**: WAVE API (`/api/accessibility-marvel.php`), ElevenLabs TTS (`/api/tts-elevenlabs.php`), PageSpeed, GitHub PRs, Sentry, SonarCloud.
- **Almacenamiento**: `storage/` para datos/logs; semillas en `App\Dev\Seed`.
- **URLs**: `App\Config\ServiceUrlProvider` y `config/services.php` resuelven endpoints por entorno (`local`, `hosting`).

## Qué puede hacer el agente
- Actualizar documentación (`README.md`, `docs/*.md`, `AGENTS.md`) y comentarios desactualizados.
- Añadir/ajustar tests en `tests/` (respetando `*Test.php`) y ajustar bootstrap solo si cambia el autoload.
- Mejorar wiring en `src/bootstrap.php` cuando se agreguen handlers/eventos o nuevos servicios.
- Revisar calidad: PHPUnit, PHPStan, Composer validate. No revertir cambios de usuarios.

## Qué no debe hacer
- Romper contratos de dominio, interfaces o eventos.
- Modificar flujos CI/CD salvo correcciones de texto/comentarios.
- Borrar lógica de aplicación o alterar payloads sin actualizar la documentación de API.

## Comandos frecuentes (raíz)
- Instalar dependencias: `composer install`
- Servir app principal: `composer serve` o `php -S localhost:8080 -t public`
- Servir microservicios: `php -S localhost:8081 -t public` (openai-service), `php -S localhost:8082 -t public` (rag-service)
- Pruebas: `vendor/bin/phpunit --colors=always`
- Análisis estático: `vendor/bin/phpstan analyse --memory-limit=512M`
- Cobertura: `composer test:cov`
- Security check: `bash bin/security-check.sh` (PHPUnit de seguridad + PHPStan de seguridad + composer audit); workflow `security-check.yml` en PR/push a main.

## Paneles y rutas útiles
- GitHub PRs: `/panel-github`
- Accesibilidad (WAVE): `/accessibility`
- Performance (PSI): `/performance`
- Sentry: `/sentry`
- SonarCloud: `/sonar`
- Heatmap: `/secret-heatmap`
- Repo browser: `/repo-marvel`
- Cómic + RAG + TTS: `/comic`

## Buenas prácticas rápidas
- Mantener idempotencia de handlers de eventos y registros en `src/bootstrap.php`.
- No leer/escribir `storage/` desde Presentación: usar repositorios/servicios.
- Documentar cualquier cambio de payload en `docs/API_REFERENCE.md` y flujos en `docs/ARCHITECTURE.md`.
- Usar tokens de ejemplo o placeholders en la documentación, nunca credenciales reales.
