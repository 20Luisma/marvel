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

## Relacionado
- Implementación en `src/Security/*`, `src/Controllers/AuthController.php`, `src/Shared/Http/Router.php`, `views/pages/login.php`, `views/helpers.php`, `src/bootstrap.php`.
- Actualizaciones de documentación: `README.md`, `AGENTS.md`, `docs/project-management/ROADMAP.md`, `docs/architecture/ARCHITECTURE.md`, `docs/api/API_REFERENCE.md`, `docs/marvel-agent/marvel-agent-memory.md`.
