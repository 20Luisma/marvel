# Clean Marvel Album — Seguridad (fuente de verdad)

## 1. Introducción
- Enfoque: arquitectura limpia (Presentación → Aplicación → Dominio; Infra implementa contratos), con capa de seguridad centralizada (`src/Security/*`) y controles adicionales en `src/bootstrap.php`.
- Alcance: app principal PHP (`public/`, `views/`, `src/`), almacenamiento local (`storage/`), microservicios asociados (`openai-service`, `rag-service`, heatmap) configurados vía `config/services.php` y variables `.env`.
- Niveles de madurez: Fases 1–8 implementadas a nivel Máster (controles activos). La verificación se apoya en tests (ver `tests/Security/`) y en la guía `docs/security/security_verification.md`. En las Fases 1–7 quedan tareas de hardening adicional (MFA, HSTS forzado, CSP sin inline, HMAC completo, etc.) documentadas como trabajo futuro.
- Resumen corto disponible en `README.md` y en la vista `views/pages/readme.php`; este documento mantiene el detalle completo y el roadmap.

### Estado por fase (Máster vs hardening adicional)

| Fase | Tema | Estado base (Máster) | Hardening adicional (pendiente) |
| --- | --- | --- | --- |
| 1 | Hardening HTTP básico | Implementado | Pendiente: HSTS forzado/cookies más estrictas |
| 2 | Autenticación y sesiones | Implementado | Pendiente: MFA, rotación credenciales, SameSite/secure siempre |
| 3 | Autorización y control de acceso | Implementado (admin único) | Pendiente: multirol/usuarios |
| 4 | CSRF y XSS | Implementado | Pendiente: CSP sin unsafe-inline, SRI/nonce |
| 5 | APIs y microservicios | Implementado | Pendiente: HMAC completo en proxy, segmentación/red |
| 6 | Monitorización y logs | Implementado | Pendiente: rotación/alertas, anonimización PII |
| 7 | Anti-replay avanzado | Modo observación | Pendiente: modo bloqueo/enforcement |
| 8 | Endurecimiento de cabeceras + tests | Implementado | — |
| 9 | Gestión de secretos y despliegue | En progreso (documentado) | Pendiente: hardening adicional |
| 10 | Hardening adicional (MFA, roles, etc.) | Trabajo futuro (documentado) | Pendiente: consolidar backlog |

## 2. Estado actual de la seguridad
- **Fortalezas:** hardening inicial de cabeceras, CSRF en POST críticos, rate-limit y bloqueo de login por intentos, autenticación con hash bcrypt, sesión con TTL y lifetime, sellado IP/UA, detección de hijack y anti-replay en modo pasivo, firewall de payloads y sanitización básica, logging centralizado con trace_id.
- **Debilidades:** admin único, CSP permisiva (unsafe-inline + CDNs), HSTS solo activo cuando HTTPS (no forzado), storage local para rate-limit/intentos (no distribuido), controles anti-replay solo en observación, falta de pruebas automáticas de CORS avanzadas, claves HMAC entre servicios no validadas en el proxy principal.

## 3. Controles de seguridad implementados
### Autenticación y sesiones
- `AuthService`: email admin configurable (`SecurityConfig`) y password hash vía `ADMIN_PASSWORD_HASH`. Login verifica credenciales, regenera sesión, establece `user_id/user_email/user_role`, `session_created_at`, `last_activity`, `session_ip_hash`, `session_ua_hash`. TTL inactividad 30 min, lifetime 8h. Logout limpia sesión y cookie.
- Anti-hijack: compara IP/UA en cada request; si difiere, invalida y loggea `session_hijack_detected`. Anti-replay soft: token `session_replay_token` generado/rotado, logs de ausencia/mismatch/validez (no bloquea).
- Cookies: `httponly`, `samesite=Lax`, `secure` cuando HTTPS. Trace ID por request.

### Autorización y roles
- Rol único `admin`. `AuthMiddleware` protege `/seccion`, `/secret*`, `/admin*`, paneles, `agentia`; no logueado → 302 /login, no admin → 403. `AuthGuards` en vistas sensibles refuerzan acceso admin. Aliases en `PageController` sirven vistas protegidas.

### CSRF
- Tokens generados por `CsrfTokenManager` / `CsrfService`; `csrf_field()` en vistas. `CsrfMiddleware` valida POST en login/logout/agentia/paneles `/api/rag/heroes`, etc.; fallo → 403 JSON + log `csrf_failed`.

### XSS
- Escapado con helper `e()` en vistas. Sanitización de entradas (`Sanitizer`, `InputSanitizer`) y validación (`JsonValidator`) en controladores. CSP básica ayuda pero es permisiva (unsafe-inline).

### Inyección
- Payload JSON validado antes de uso (JsonValidator). Sanitizadores eliminan scripts/JNDI. Repositorios de archivos JSON; si se usa PDO en hosting, conexión via `PdoConnectionFactory` (debe usarse con consultas preparadas; no se observan interpolaciones directas aquí). Firewall de API bloquea patrones de inyección básicos.

### Cabeceras y configuración HTTP
- `SecurityHeaders::apply()` y bootstrap añaden: `X-Frame-Options: SAMEORIGIN`, `X-Content-Type-Options: nosniff`, `Referrer-Policy: no-referrer-when-downgrade`, `Permissions-Policy: geolocation=(), microphone=(), camera=()`, CSP básica (default-src 'self' + CDNs necesarios), `X-Content-Security-Policy` (fallback), `X-XSS-Protection: 0`, `Cross-Origin-Resource-Policy: same-origin`, `Cross-Origin-Opener-Policy: same-origin`, `Cross-Origin-Embedder-Policy: unsafe-none`, HSTS solo en HTTPS. Intro (`public/index.php`) también aplica headers.
- CORS: en `public/home.php`, si `HTTP_ORIGIN` coincide con `APP_ORIGIN`/`APP_URL` se permite `Access-Control-Allow-Origin` + `Vary: Origin`; métodos `GET, POST, PUT, DELETE, OPTIONS`, cabeceras `Content-Type, Authorization`; OPTIONS devuelve 204; caso contrario 403.

### Gestión de errores y logs
- `SecurityLogger` (sanitiza contexto) escribe en `storage/logs/security.log` eventos `rate_limit`, `payload_suspicious`, `login_failed/block/success`, `csrf_failed`, `session_expired_ttl/lifetime`, `session_hijack_detected`, eventos anti-replay soft, etc., con `trace_id`, IP, path, user_agent. Sentry inicializable si DSN presente; router captura throwables y responde con mensaje genérico.

### Microservicios y APIs externas
- URLs resueltas por `ServiceUrlProvider` según entorno (`config/services.php`). Clave interna `INTERNAL_API_KEY` cargada en contenedor (firma HMAC esperada en microservicios; proxy principal no valida explícitamente en cada llamada). `/api/rag/heroes` proxifica RAG; OpenAI via microservicio; heatmap cliente HTTP con token opcional. Anti-replay/logging no modifica payloads.

### Gestión de secretos
- Variables `.env` por entorno (APP_ENV, claves internas, API keys). `SecurityConfig` lee correo y hash admin, `ServiceUrlProvider` lee endpoints. No hay gestor de secretos externo; claves deben configurarse manualmente en `.env` de app y microservicios.

## 4. Riesgos y brechas detectadas
- Admin único hardcodeado; sin MFA ni cambio de credenciales (Impacto Alto, Prob Media, Prioridad Alta).
- CSP permisiva (`'unsafe-inline'`, CDNs); riesgo XSS/asset hijack (Impacto Alto, Prob Media, Prioridad Alta).
- HSTS solo activo en HTTPS, no forzado en prod; riesgo downgrade en HTTP (Impacto Medio, Prob Baja-Media, Prioridad Media).
- Anti-replay en modo observación; no bloquea reuso de cookies (Impacto Medio, Prob Media, Prioridad Media).
- Rate-limit e intentos en disco local; no distribuido, posible bypass multi-nodo (Impacto Medio, Prob Media, Prioridad Media).
- Clave interna no verificada en proxy principal; confianza en microservicios (Impacto Medio, Prob Baja-Media, Prioridad Media).
- Sin tests automáticos de cabeceras/CORS/CSP; riesgo de regresión silenciosa (Impacto Medio, Prob Media, Prioridad Media).
- Storage de logs puede crecer sin rotación y contener IP/UA (Impacto Bajo-Medio, Prob Media, Prioridad Media-Baja).

## 5. Roadmap de fases de seguridad
Base Máster implementada en 1–8; hardening adicional pendiente en 1–7 (ver tabla de estados).
- **Fase 1 — Hardening HTTP básico**  
  Objetivo: cabeceras y cookies seguras.  
  Hecho: headers listados, cookies HttpOnly/Lax, HSTS en HTTPS.  
  Hardening adicional pendiente: HSTS forzado en prod y refinar cookies (SameSite/secure estrictos). Prioridad: Media-Alta.

- **Fase 2 — Autenticación y sesiones**  
  Hecho: bcrypt, regen ID en login, TTL 30m, lifetime 8h, sellado IP/UA, anti-hijack, anti-replay soft, logout seguro.  
  Falta: MFA, rotación de credenciales admin, endurecer SameSite/secure siempre, anti-replay en modo enforcement. Prioridad: Alta.

- **Fase 3 — Autorización y control de acceso**  
  Hecho: AuthMiddleware y AuthGuards protegen Secret Room/paneles/agentia; rol admin único.  
  Falta: modelo multirol/usuarios, auditoría de privilegios por recurso. Prioridad: Media-Alta.

- **Fase 4 — CSRF y XSS**  
  Hecho: CSRF tokens + middleware en POST críticos; escapado en vistas; sanitización de entrada.  
  Falta: cobertura automática de formularios nuevos, CSP más estricta con nonce/SRI para mitigar XSS. Prioridad: Alta.

- **Fase 5 — Seguridad en APIs y microservicios**  
  Hecho: ApiFirewall (tamaño/patrones), rate-limit en rutas clave, clave interna disponible, RAG/OpenAI detrás de proxy.  
  Falta: validación HMAC en proxy, segmentación/red de confianza, firma/verificación consistente, tests de endpoints. Prioridad: Media-Alta.

- **Fase 6 — Monitorización y logs**  
  Hecho: SecurityLogger con trace_id, eventos de sesión/rate-limit/CSRF/payloads; Sentry opcional.  
  Falta: alertas/rotación de logs, clasificación de severidad, anonimización de PII. Prioridad: Media.

- **Fase 7 — Gestión avanzada de sesión y anti-replay**  
  Hecho: IP/UA binding, TTL/lifetime, anti-replay soft (token emitido/rotado + logging).  
  Falta: modo enforcement con bloqueo, sincronizar con headers de cliente, pruebas funcionales de replay. Prioridad: Media-Alta.

- **Fase 8 — Endurecimiento de cabeceras**  
  Hecho: cabeceras endurecidas (X-Frame-Options, X-Content-Type-Options, Referrer-Policy, Permissions-Policy, Content-Security-Policy y X-Content-Security-Policy, X-Download-Options, X-Permitted-Cross-Domain-Policies, Cross-Origin-Resource-Policy, Cross-Origin-Opener-Policy, Cross-Origin-Embedder-Policy) activas en HTML y APIs; cookies de sesión con HttpOnly + SameSite=Lax. Tests automáticos (`tests/Security/HeadersSecurityTest.php`) cubren rutas clave (`/`, `/login`, `/seccion`, `/secret/sonar`, `/api/rag/heroes`).

### Validación Fase 8 (local/CI)
- Automática: `vendor/bin/phpunit --colors=always tests/Security/HeadersSecurityTest.php`.
- Cobertura: `composer test:coverage` (genera `coverage.xml`, consumido por SonarCloud y por el "coverage gate" en CI).
- Manual (curl):
  - Home: `curl -i http://localhost:8080/ -H "Accept: text/html"`
  - Login: `curl -i http://localhost:8080/login -H "Accept: text/html"`
  - Sección: `curl -i http://localhost:8080/seccion -H "Accept: text/html" -L`
  - Secret: `curl -i http://localhost:8080/secret/sonar -H "Accept: text/html" -L`
  - API RAG: `curl -i http://localhost:8080/api/rag/heroes -H "Content-Type: application/json" -d '{"heroes":[1,2],"question":"test"}'`
- Conclusión: para el alcance del Máster, la Fase 8 queda verificada por tests automatizados y comprobaciones manuales en entorno local.

- **Fase 9 — Gestión de secretos y despliegue**  
  En progreso (Nivel Máster): inventario de secretos, `.env.example` actualizados (app principal + microservicios), workflows sin claves planas y guía de despliegue (`docs/deployment/deploy.md`). Hardening adicional futuro: vault/rotación automática, HSTS forzado tras HTTPS total. Prioridad: Media-Alta.

- **Fase 10 — Pruebas automáticas de seguridad**  
  Plan: agregar tests de cabeceras/CORS/CSP, escaneos `composer audit`/SAST, tests de microservicios y anti-replay en enforcement. Prioridad: Media.

## 6. Próximos pasos
- Endurecer CSP (reducir unsafe-inline, añadir nonce/SRI) y habilitar HSTS en producción tras validar HTTPS.
- Diseñar MFA o al menos rotación de credenciales para el admin; considerar soporte multiusuario/roles.
- Activar anti-replay en modo enforcement de forma progresiva (primero rutas sensibles), coordinando cliente para enviar `X-Session-Replay`.
- Implementar validación de firma/HMAC en el proxy antes de llamar a microservicios y documentar el contrato.
- Añadir pruebas automáticas adicionales para CORS/CSP y monitoreo/rotación de logs.
- Evaluar mover rate-limit/intentos a un backend centralizado si hay múltiples instancias.

> Nota: las tareas pendientes en fases 1–7 son mejoras de hardening adicional; la base de cada fase está implementada y probada para el alcance del Máster.

### Fase 10 — Verificación de seguridad antes del despliegue
- Script local: `bin/security-check.sh`. Ejecuta, en orden:
  - Auditoría de dependencias: `composer audit --no-interaction`
  - Lint de sintaxis PHP: `php -l` sobre `src/` y `tests/`
- Ejecución local: `bash bin/security-check.sh`.
- CI: workflow `.github/workflows/security-check.yml` corre en cada PR a `main` y en cada push a `main`.
