# Clean Marvel Album — Security Overview (v1)

## 1. Security Overview Global
Clean Marvel Album sigue arquitectura limpia (presentación, aplicación, dominio, infraestructura) con seguridad centralizada en `src/Security/*`. El arranque (`src/bootstrap.php`) carga `.env`, prepara sesión segura, aplica cabeceras de seguridad, genera `trace_id` por request y expone servicios en un contenedor global. El router (`Src\Shared\Http\Router`) ejecuta en orden `ApiFirewall` → `RateLimitMiddleware` → `AuthMiddleware` antes de despachar controladores o vistas. Almacenamiento local en `storage/` para rate-limit (`storage/rate_limit`), intentos de login (`storage/security/login_attempts.json`) y logs de seguridad (`storage/logs/security.log`). Microservicios externos se resuelven vía `ServiceUrlProvider` según entorno.

## 2. Autenticación y Sesiones
`App\Security\Auth\AuthService` valida el único admin (`seguridadmarvel@gmail.com`) con `password_verify`. En login correcto: asegura sesión activa, llama `session_regenerate_id(true)`, guarda `session_created_at`, `last_activity`, `user_id`, `user_email`, `user_role` y array `auth`. TTL de inactividad: 30 min (`SESSION_TTL_SECONDS`); vida máxima: 8h (`SESSION_MAX_LIFETIME`). `isAuthenticated` renueva `last_activity` y expulsa al superar TTL/lifetime. `logout` borra auth y user_* y destruye cookie si existe. `bootstrap.php` configura cookies de sesión con `httponly`, `samesite=Lax`, `secure` cuando HTTPS y aplica `session_set_cookie_params` antes de `session_start`.

## 3. Roles y Permisos
Rol admin se guarda en `$_SESSION['user_role']` y en `auth['role']`. `AuthMiddleware` protege rutas (`/seccion`, `/secret*`, `/admin*`, paneles, `agentia`, etc.): si no autenticado redirige a `/login`, si no admin responde 403. `App\Infrastructure\Http\AuthGuards` se incluye en vistas sensibles y exige sesión + rol admin. Rutas protegidas incluyen Secret Room, paneles técnicos, agentia y alias definidos en `PageController`.

## 4. CSRF Protection
`CsrfTokenManager` y `CsrfService` generan tokens almacenados en sesión; las vistas incluyen los campos hidden (`csrf_field()` en `views/helpers.php`). `CsrfMiddleware` protege POST en rutas críticas (`/login`, `/logout`, `/agentia`, paneles, `/api/rag/heroes`, etc.); en fallo devuelve 403 JSON y registra evento `csrf_failed` mediante `SecurityLogger`.

## 5. Validaciones y Sanitización
`Sanitizer` elimina scripts/PHP/JNDI y controla longitud. `InputSanitizer` recorta, limpia HTML y detecta patrones sospechosos; se usa en controladores (ej. `AlbumController`) con logging de payload sospechoso. `JsonValidator` verifica tipos y campos requeridos en payloads JSON. `ApiFirewall` valida tamaño y estructura antes de controladores.

## 6. Security Headers
`SecurityHeaders::apply()` añade `X-Frame-Options: SAMEORIGIN`, `X-Content-Type-Options: nosniff`, `Referrer-Policy: no-referrer-when-downgrade`, `Permissions-Policy` (geolocation/microphone/camera vacíos) y `Strict-Transport-Security` en HTTPS. CSP básica: `default-src 'self'`, permite imágenes/media/fonts/styles/scripts necesarios (Google Fonts, Tailwind CDN, jsdelivr), `connect-src` incluye self y puertos locales 8080/8081/8082, `frame-src` Youtube, `frame-ancestors 'self'`. `bootstrap.php` añade cabeceras extra (CSP redundante, X-XSS-Protection:0, Cross-Origin-*). Cookies de sesión `httponly`, `samesite=Lax`, `secure` cuando aplica.

## 7. Rate Limit & IP Blocker
`RateLimiter` (file-based) controla por IP y ruta; `RateLimitMiddleware` aplica a rutas configuradas (login, `/api/rag/heroes`, `agentia`, paneles) devolviendo 429 (JSON/HTML) y loggeando `rate_limit`. `LoginAttemptService` controla intentos de login (5 en 15 min, bloqueo 15 min) y registra `login_failed` y `login_blocked`. `IpBlockerService` envuelve ese servicio: `check` bloquea si excedido, registra `login_blocked` via `SecurityLogger`, limpia intentos tras login exitoso y expone minutos restantes.

## 8. Seguridad entre Microservicios
`ServiceUrlProvider` resuelve endpoints según entorno (`config/services.php`). `INTERNAL_API_KEY` se carga en el contenedor para firmar/proteger tráfico hacia microservicios (`openai-service`, `rag-service`), usando cabeceras `X-Internal-*` y HMAC (implementación en microservicios; la app actúa como proxy).

## 9. Secret Room & Admin Protection
Rutas `/seccion`, `/secret/*`, paneles técnicos (sonar, sentry, heatmap, repo, github, performance, accessibility) y `agentia` se protegen vía `AuthMiddleware` y `AuthGuards` en las vistas. Flujo: anónimo → redirección 302 a `/login`; usuario no admin → 403 con mensaje genérico; admin → acceso 200. `PageController` mapea alias (`/secret/heatmap`, `/secret/sonar`, `/secret/sentry`, `/secret`, etc.) para servir vistas protegidas.

## 10. Endpoints /api/
`ApiFirewall` inspecciona todas las rutas no allowlisted: límite de 1MB, rechaza JSON inválido o con claves duplicadas, valores demasiado largos, tipos no permitidos y patrones de ataque (`<script`, `drop table`, `${jndi:ldap://`, `<?php`). Si falla responde 400 y loggea `payload_suspicious`. `/api/rag/heroes` además está en rate-limit y CSRF (cuando POST).

## 11. CORS
En `public/home.php`, si `APP_ORIGIN`/`APP_URL` coincide con `HTTP_ORIGIN`, se habilita `Access-Control-Allow-Origin` y `Vary: Origin`; si difiere, responde 403. Permite métodos `GET, POST, PUT, DELETE, OPTIONS` y cabeceras `Content-Type, Authorization`. Maneja preflight con 204.

## 12. Logging y Auditoría
`SecurityLogger` escribe en `storage/logs/security.log`, creando directorios si faltan. Eventos incluyen `rate_limit`, `payload_suspicious`, `login_failed`, `login_blocked`, `login_success`, `csrf_failed`. Cada línea incluye timestamp, `trace_id` (de `TraceIdGenerator` en bootstrap), IP, path y contexto (estado, remaining/reset, motivo).

## 13. Tests de Seguridad Existentes
Suite PHPUnit incluye: `ApiFirewallTest`, `RateLimiterTest`, `RateLimitMiddlewareTest`, `SanitizerTest`, `SecuritySmokeTest` (CSRF, sanitizador, rate-limit), `LoginAttemptServiceTest`, `AuthServiceTest` (regeneración de sesión), `AdminRouteProtectionTest` (anon/user/admin sobre ruta secreta), `LoginThrottleTest` (bloqueo y log). Cobertura sobre throttling, firewall, guard admin y sanitización básica.

## 14. Brechas o Áreas Incompletas
Usuario admin hardcodeado (sin multiusuario ni roles avanzados); CSP permisiva (`'unsafe-inline'`, CDNs) sin nonce/SRI; cookie de sesión solo `secure` si HTTPS (en local puede ir sin `secure`); CSRF requiere añadir rutas nuevas manualmente; firma HMAC hacia microservicios documentada pero no verificada en código principal; sin MFA; rate-limit/login-attempts almacenados en disco (no distribuido); faltan tests para headers/CORS/CSP y protección de microservicios; mensajes de error básicos (sin MFA/lockout per-account configurable).

## 15. Fases de Seguridad

### 15.1 Fases completadas (1–6)
F1: Hardening inicial (headers, bootstrap seguro, router con orden de middleware). F2: CSRF tokens + middleware en rutas críticas. F3: Sanitización y validación (Sanitizer, InputSanitizer, JsonValidator) + ApiFirewall. F4: Rate limiting y logging de eventos. F5: Configuración de microservicios y claves internas via `ServiceUrlProvider`/`INTERNAL_API_KEY`. F6: Autenticación con password_hash/verify, regeneración de sesión, roles admin, guards en rutas secretas/paneles, throttling de login, tests de seguridad añadidos.

### 15.2 Fases pendientes (7–10)
Endurecer CSP/cookies (nonce/SRI, `secure` siempre, `SameSite=Strict` donde aplique), incorporar multiusuario/roles avanzados y MFA, validar HMAC/token en el proxy principal, distribuir rate-limit/intent storage, ampliar tests (headers/CORS/CSP/microservicios), y otras mejoras de observabilidad/alertas para seguridad.
