# Guía de autenticación y tokens

Clean Marvel Album no requiere un sistema de usuarios complejo, pero sí integra varios tokens y cabeceras para proteger recursos críticos.

## Tokens obligatorios/optativos

- `APP_ORIGIN` / `APP_URL`: definan el dominio permitido. El router solo sirve respuestas cuando el origen coincide en producción.  
- `SENTRY_DSN`, `SENTRY_API_TOKEN`, `SENTRY_ORG_SLUG`, `SENTRY_PROJECT_SLUG`: habilitan captura de errores y visualización desde `public/api/sentry-metrics.php`.  
- `SONARCLOUD_TOKEN`, `SONARCLOUD_PROJECT_KEY`: permiten consultar SonarCloud desde `public/api/sonar-metrics.php`.  
- `GITHUB_API_KEY`: se usa en `App\Services\GithubClient` para llamar a `https://api.github.com/repos/20Luisma/marvel`.  
- `ELEVENLABS_API_KEY` y ajustes opcionales (`ELEVENLABS_VOICE_ID`, `ELEVENLABS_MODEL_ID`, `ELEVENLABS_VOICE_STABILITY`, `ELEVENLABS_VOICE_SIMILARITY`) para el proxy TTS `public/api/tts-elevenlabs.php`.  
- `TTS_INTERNAL_TOKEN`: protege el endpoint TTS adicionalmente; `parseBearer` valida el token antes de solicitar ElevenLabs.  
- `MARVEL_UPDATE_TOKEN`: valida el webhook entrante en `public/api/actualizar-video-marvel.php` para evitar escrituras no autorizadas en `storage/marvel/`.

## Uso en cabeceras

- Todos los endpoints JSON usan `Authorization: Bearer <token>` cuando el token está configurado. Si el token está vacío, el endpoint solo aplica restricciones mínimas pero mantiene un mensaje claro.  
- `TTS_INTERNAL_TOKEN` se valida en `public/api/tts-elevenlabs.php`.  
- `MARVEL_UPDATE_TOKEN` se valida en `public/api/actualizar-video-marvel.php` antes de sobrescribir el JSON del último video oficial.

## Consejos de seguridad

- No expongas `.env` ni `storage/` en `public/`. Usa `.htaccess` (ya incluido) para bloquear accesos directos.  
- Usa conexiones HTTPS cuando despliegues en hosting; los microservicios IA también deben ejecutarse bajo HTTPS si los expones públicamente.  
- Actualiza `GITHUB_API_KEY` y los tokens opcionales cuando roten para mantener el acceso al panel GitHub, SonarCloud y Sentry.
