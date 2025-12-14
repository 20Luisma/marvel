# Deploy (alcance Máster) — Clean Marvel Album

## Entornos
- **Local**: `APP_ENV=local`, servicios en `localhost:8080` (app), `8081` (openai-service), `8082` (rag-service).
- **Hosting/producción**: mismo código, configurado vía `.env` y subdominios/URLs de microservicios.

## Configurar `.env`
1. Copia `.env.example` a `.env` en la raíz y rellena los placeholders.
2. Repite en microservicios:
   - `openai-service/.env` desde `openai-service/.env.example`
   - `rag-service/.env` desde `rag-service/.env.example`
3. Variables críticas (raíz):
   - `ADMIN_EMAIL` / `ADMIN_PASSWORD_HASH`: credenciales del admin (hash bcrypt, no la contraseña en claro). **Recomendación verificable**: configura `ADMIN_PASSWORD_HASH` en cualquier entorno público. **Comportamiento actual del código**: `src/Config/SecurityConfig.php` solo obliga (lanza excepción) si `APP_ENV` es `prod` o `production`; en `hosting` no se bloquea automáticamente, pero debe configurarse igualmente.
   - `INTERNAL_API_KEY`: firma HMAC interno.
   - `OPENAI_API_KEY`: clave OpenAI (usada por embeddings y paneles).
   - `ELEVENLABS_API_KEY`: TTS.
   - `HEATMAP_API_TOKEN`: token hacia microservicio heatmap.
   - `MARVEL_UPDATE_TOKEN`: webhook n8n (YouTube → backend).
   - `GITHUB_API_KEY`, `WAVE_API_KEY`, `PSI_API_KEY`: paneles GitHub/Accesibilidad/Performance.
   - `SENTRY_DSN` (+ `SENTRY_API_TOKEN`, `SENTRY_ORG_SLUG`, `SENTRY_PROJECT_SLUG`): observabilidad.

## GitHub Actions (secrets)
- Los workflows en `.github/workflows/*.yml` usan `secrets.*` y `vars.*` para credenciales (p. ej., `FTP_HOST`, `FTP_USER`, `FTP_PASS`, `FTP_PORT`).
- Para despliegues, configura los secrets/vars en GitHub antes de ejecutar los jobs.

## Despliegue (buenas prácticas)
- Docroot debe ser `public/`; **no expongas** `storage/` (contiene logs, sesiones y JSON).
- En producción usa `APP_DEBUG=0`. El endurecimiento adicional (HSTS forzado y cookies más estrictas) se documenta como trabajo futuro.

## Comprobaciones rápidas post-deploy
- `composer test:coverage` en local/CI (genera `coverage.xml`, consumido por SonarCloud).
- Cabeceras de seguridad: `vendor/bin/phpunit --colors=always tests/Security/HeadersSecurityTest.php`.
- Acceso básico: curl a `/`, `/login`, `/seccion`, `/secret/sonar`, `/api/rag/heroes` con las cabeceras adecuadas.
