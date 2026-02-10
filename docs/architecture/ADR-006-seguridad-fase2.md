# ADR-006 – Seguridad Fase 2 (autenticación ligera + CSRF)

## Estado
Accepted

## Contexto
Se necesitan controles mínimos para los paneles internos (heatmap, GitHub, repo, accesibilidad, performance, agentia) sin romper la navegación pública ni depender de base de datos. La intro, la Home y Secret Room deben seguir accesibles para demo y no se quiere introducir infraestructura adicional.

## Decisión
- Autenticación por sesión con un usuario único (`marvel@gmail.com` / hash de `marvel2025`) implementada en `AuthService`.
- Middleware `AuthMiddleware` protege solo rutas internas: `/secret-heatmap`, `/panel-github`, `/panel-repo-marvel`, `/panel-accessibility`, `/panel-performance`, `/performance`, `/agentia`. Resto de rutas permanecen públicas (incluida `/seccion` y la intro).
- CSRF por sesión con `CsrfTokenManager` para formularios HTML existentes; bypass automático en `APP_ENV=test`.
- Rutas `/login` (GET/POST) y `/logout` (POST) gestionadas por `AuthController`, sin alterar `intro.php` ni `intro.js`.
- Inicio de sesión PHP con cookie `httponly` y `samesite=lax` configurado en `src/bootstrap.php`.

## Justificación
- Proteger paneles sensibles sin agregar dependencias ni bases de datos.
- Mantener la experiencia pública y la intro intactas (demo-friendly).
- Reducir fricción en QA: el bypass CSRF en tests evita romper PHPUnit.

## Consecuencias
### Positivas
- Paneles internos quedan detrás de login; credenciales no se exponen al frontend.
- CSRF básico mitiga envíos forzados en formularios HTML.
- Rutas públicas y la intro siguen libres; no se rompe la navegabilidad previa.
### Negativas
- Usuario único y sesión en memoria: no hay gestión multiusuario ni roles adicionales.
- Login simple puede requerir endurecimiento futuro (más usuarios, 2FA, rotación de hashes).

## Opciones descartadas
- Autenticación con base de datos o OAuth (exceso de complejidad para la demo actual).
- Proteger todas las rutas (se descartó para no afectar la experiencia pública/intro).

## Endurecimiento incremental (Fase 2.1)

### HSTS Preload
- Se añadió la directiva `preload` al header `Strict-Transport-Security` en `SecurityHeaders.php`.
- Header resultante: `max-age=63072000; includeSubDomains; preload`.
- Cumple con los requisitos de [hstspreload.org](https://hstspreload.org) y las recomendaciones OWASP.
- Impacto: ninguno en funcionalidad existente. Puramente declarativo.

### HMAC Strict Mode (opt-in)
- Se implementó un modo estricto opcional en `rag-service/public/index.php` para la validación HMAC inter-microservicios.
- Variable de entorno: `HMAC_STRICT_MODE=true` activa el modo fail-closed.
- **Con strict mode activado:** si `INTERNAL_API_KEY` está vacía, se rechaza la petición con `401 (hmac-strict-no-key)`.
- **Sin strict mode (por defecto):** comportamiento idéntico al anterior (fail-open si no hay clave configurada).
- Patrón de seguridad Zero Trust: garantiza que no se puedan procesar peticiones sin firma HMAC en entornos donde se exija.
- `InternalRequestSigner::isValid()` ya implementaba validación fail-closed para firmas proporcionadas; este cambio cubre el caso edge de clave ausente.

## Relacionado
- Implementación en `src/Security/*`, `src/Controllers/AuthController.php`, `src/Shared/Http/Router.php`, `views/pages/login.php`, `views/helpers.php`, `src/bootstrap.php`.
- Endurecimiento: `src/Security/Http/SecurityHeaders.php`, `rag-service/public/index.php`, `src/Shared/Infrastructure/Security/InternalRequestSigner.php`.
- Actualizaciones de documentación: `README.md`, `AGENTS.md`, `docs/project-management/ROADMAP.md`, `docs/architecture/ARCHITECTURE.md`, `docs/api/API_REFERENCE.md`, `docs/marvel-agent/marvel-agent-memory.md`.
