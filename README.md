# Clean Marvel Album – Documentación Técnica

![CI](https://github.com/20Luisma/marvel/actions/workflows/ci.yml/badge.svg)
![Coverage](https://sonarcloud.io/api/project_badges/measure?project=20Luisma_marvel&metric=coverage)
![Maintainability](https://sonarcloud.io/api/project_badges/measure?project=20Luisma_marvel&metric=sqale_rating)
![Pa11y](https://img.shields.io/badge/Pa11y-enabled-brightgreen)
![Playwright E2E](https://img.shields.io/badge/Playwright%20E2E-passing-brightgreen)
![Bundle Size](https://img.shields.io/badge/Bundle%20Size-static-blue)

**Clean Marvel Album** es un proyecto académico en **PHP 8.2** que aplica Arquitectura Limpia para gestionar álbumes y héroes, e integra microservicios (OpenAI/RAG) y herramientas de calidad.

La automatización (tests, análisis y auditorías) se define en `.github/workflows/` y la documentación técnica se organiza en `docs/`.

---

## Objetivo

- Mantener el **dominio** limpio e independiente de frameworks.
- Integrar IA mediante microservicios externos fáciles de sustituir.
- Servir como proyecto demostrativo con tests, calidad y despliegue automatizado.

---

## Arquitectura general

| Capa | Ubicación principal | Responsabilidad |
| --- | --- | --- |
| **Presentación** | `public/`, `src/Controllers`, `views/`, `App\Shared\Http\Router` | Front Controller + Router HTTP; render de vistas y respuestas JSON. |
| **Aplicación** | `src/*/Application`, `src/AI`, `src/Dev` | Casos de uso, orquestadores (comic generator, comparador RAG, seeders). |
| **Dominio** | `src/*/Domain` | Entidades, Value Objects, eventos y contratos de repositorios. |
| **Infraestructura** | `src/*/Infrastructure`, `storage/`, `App\Shared\Infrastructure\Bus` | Repos JSON/DB, EventBus en memoria, adaptadores externos (notificaciones, gateways IA). |

Dependencias: Presentación → Aplicación → Dominio, e Infraestructura implementa contratos de Dominio. `App\Config\ServiceUrlProvider` resuelve los endpoints según entorno (`local` vs `hosting`).

### ¿Por qué Clean Architecture?

Esta arquitectura se eligió por razones técnicas:

**Beneficios observables:**
- **Independencia de frameworks**: El dominio no depende de librerías externas, lo que reduce el acoplamiento.
- **Alta testabilidad**: La lógica de dominio y los casos de uso pueden probarse sin HTTP; la infraestructura se valida con dobles cuando aplica.
- **Mantenibilidad**: Cambios en UI, persistencia o APIs externas se concentran principalmente en la capa de infraestructura.
- **Evolución incremental**: Se pueden incorporar adaptadores (p. ej., microservicios) sin introducir dependencias en el dominio.

La decisión arquitectónica completa está documentada en `docs/architecture/ADR-001-clean-architecture.md`.

---

## Estructura del proyecto

```
clean-marvel/
├── public/
├── src/
├── openai-service/
├── rag-service/
├── docs/ (API, arquitectura, guías, microservicios, UML)
├── tests/
├── docker-compose.yml
└── .env
```

---

## Persistencia: JSON en local, MySQL en hosting

- **Local (`APP_ENV=local`)** → JSON  
- **Hosting (`APP_ENV=hosting`)** → PDO MySQL  
- Si MySQL falla → fallback automático a JSON

Migración manual:

```bash
php bin/migrar-json-a-db.php
```

---

## Microservicios y servicios externos

- **openai-service** (`openai-service/`, puerto 8081)  
  Endpoint `POST /v1/chat` con cURL a OpenAI. Configurable con `OPENAI_API_KEY` y `OPENAI_MODEL`. Tiene fallback JSON sin credencial.
- **rag-service** (`rag-service/`, puerto 8082)  
  Endpoint `POST /rag/heroes`, usa `storage/knowledge/heroes.json` y delega a `openai-service` para la respuesta final.
- **Heatmap service** (Python/Flask externo)  
  Recoge clics reales y alimenta `/secret-heatmap`. Documentación en `docs/microservicioheatmap/README.md`. Incluye contenedor Docker (build/run) para levantar el servicio en local o VM con `HEATMAP_API_TOKEN`.
- **WAVE API** (Accesibilidad)  
  `public/api/accessibility-marvel.php` consulta la API de WebAIM con `WAVE_API_KEY`.
- **ElevenLabs TTS**  
  `public/api/tts-elevenlabs.php` añade narración a cómics y comparaciones RAG usando `ELEVENLABS_API_KEY`.

---

## ⚙️ CI/CD – GitHub Actions

Pipelines: `ci.yml` (PHPUnit, PHPStan, Pa11y, Lighthouse, Playwright E2E, SonarCloud, bundle size estático), `deploy-ftp.yml` (deploy por FTP al hacer push a `main`; recomendado proteger `main` para que solo se pueda mergear si CI pasa), `rollback-ftp.yml` (rollback manual).

- **Bundle size (JS/CSS)**: el job `sonarcloud` ejecuta `php bin/generate-bundle-size.php` y publica `public/assets/bundle-size.json`. La vista `/sonar` consume ese JSON para mostrar totales y top 5 sin necesitar `exec` en hosting.

---

## Puesta en marcha (local)

1. **Instala dependencias**  
   `composer install` en la raíz. Si trabajas en microservicios, repite dentro de `openai-service/` y `rag-service/`.
2. **Configura `.env`**  
   Ajusta `APP_ENV` (`local` usa JSON, `hosting` usa MySQL con fallback a JSON), URLs de servicios (`OPENAI_SERVICE_URL`, `RAG_SERVICE_URL`, `HEATMAP_API_BASE_URL`), tokens (`GITHUB_API_KEY`, `ELEVENLABS_API_KEY`, `WAVE_API_KEY`, PSI, Sentry, SonarCloud).
3. **Arranca la app principal**  
   `composer serve` o `php -S localhost:8080 -t public`.
4. **Arranca microservicios IA**  
   - `php -S localhost:8081 -t public` (dentro de `openai-service/`)  
   - `php -S localhost:8082 -t public` (dentro de `rag-service/`)
5. **Verifica paneles**  
   Navega a `/` y usa las acciones superiores para cómics, RAG, GitHub PRs, SonarCloud, Sentry, accesibilidad, performance, repo y heatmap.

## Calidad y pruebas

El proyecto implementa una **estrategia de testing multinivel** (unit/integration + seguridad + E2E).  
Para verificar el estado de la suite, ejecuta `vendor/bin/phpunit` (PHPUnit imprime el resumen).

### Suite PHPUnit

```bash
# Ejecutar todos los tests
vendor/bin/phpunit --colors=always

# Cobertura (ver `COVERAGE.md` y `coverage.xml`)
composer test:coverage

# Análisis estático (PHPStan; config en `phpstan.neon`)
vendor/bin/phpstan analyse --memory-limit=512M
```

### Tests E2E con Playwright

```bash
# Ejecutar tests E2E en localhost:8080 con navegador visible
npm run test:e2e

# Modo UI interactivo (recomendado)
npm run test:e2e:ui

# Modo debug paso a paso
npm run test:e2e:debug
```

**Flujos E2E incluidos en el repositorio**:
- Home y navegación principal
- Álbumes (renderizado y formularios)
- Héroes (galería y creación)
- Cómics (generación con IA)
- Películas (búsqueda y estados)
- Smoke/Health y autenticación

**Nota sobre `skipped`**: el test de `/health` se marca como `skip` automáticamente si la app principal no expone ese endpoint (retorna 404). Esto evita falsos negativos y mantiene el test estable; si se añade `/health` en el futuro, el test se activará solo.

### Tipos de tests implementados

| Tipo | Herramienta | Alcance |
|------|-------------|---------|
| Unitarios y dominio | PHPUnit | Entidades, Value Objects, eventos |
| Casos de uso | PHPUnit | Capa de aplicación |
| Seguridad | PHPUnit | CSRF, rate limit, sesión, firewall |
| Controladores | PHPUnit | Capa HTTP |
| Infraestructura | PHPUnit | Repositorios, clientes HTTP, bus |
| E2E | Playwright | Flujos críticos de usuario |
| Accesibilidad | Pa11y (CI) | Auditoría WCAG 2.1 AA |
| Performance | Lighthouse (CI) | Auditoría de rendimiento |

### Comandos por Categoría

```bash
# Solo tests de seguridad
vendor/bin/phpunit tests/Security

# Solo tests de dominio de Albums
vendor/bin/phpunit tests/Albums/Domain

# Solo tests de controladores
vendor/bin/phpunit tests/Controllers

# Auditoría de dependencias
composer security:audit

# Validación de composer.json
composer validate
```

**Documentación completa**: Ver `docs/guides/testing-complete.md` para más detalle de cada tipo de test.

## Documentación ampliada

- `docs/architecture/ARCHITECTURE.md`: capas, flujos y microservicios.
- `docs/api/API_REFERENCE.md`: endpoints de la app y microservicios.
- `docs/README.md`: índice de documentación.
- `docs/guides/`: arranque rápido, autenticación, testing.
- `docs/microservicioheatmap/README.md`: integración del heatmap.
- `AGENTS.md` / `docs/development/agent.md`: roles y pautas para agentes de IA.
- UML completo

## Containerización y Kubernetes

### Docker y microservicios

Este repositorio incluye:
- Un `docker-compose.yml` (entorno local) que levanta la aplicación principal con `php:8.2-cli` montando el código como volumen.
- Dockerfiles en `openai-service/Dockerfile` y `rag-service/Dockerfile`.

**Docker Compose** permite levantar la aplicación principal con un solo comando:
```bash
docker-compose up -d
```

### Kubernetes (Orquestación)

El directorio `k8s/` contiene manifiestos de ejemplo para desplegar la aplicación y sus microservicios en un cluster de Kubernetes:

**Componentes descritos en los manifiestos:**
- Deployments
- Services ClusterIP
- Ingress NGINX con enrutamiento por rutas (`/`, `/api/rag/*`, `/api/openai/*`)
- ConfigMaps
- Secrets (placeholders que deben sustituirse fuera del repositorio)
- Health probes
- Resource limits

**Quick Start:**
```bash
# 1. Aplicar manifiestos
kubectl apply -f k8s/

# 2. Verificar estado
kubectl get pods,svc,ing
kubectl rollout status deployment/clean-marvel

# 3. Port-forward para acceso local
kubectl port-forward svc/clean-marvel 8080:80
```

**Documentación completa:**
- `k8s/README.md` - Índice general y guía de uso
- `k8s/DEPLOY_K8S.md` - Despliegue paso a paso
- `k8s/PRODUCTION_CONSIDERATIONS.md` - Consideraciones adicionales
- `k8s/SECURITY_HARDENING.md` - Hardening adicional

**Alcance:** los manifiestos están orientados a despliegues de demostración y a documentación técnica. Los requisitos adicionales (gestión de secrets, TLS, network policies, PSA, image scanning, runtime security, observabilidad avanzada) están documentados como trabajo futuro en `k8s/`.

### Arquitectura Multi-Entorno

El proyecto soporta **múltiples estrategias de despliegue**:

| Entorno | Tecnología | Caso de uso |
|---------|-----------|-------------|
| **Local** | `php -S` | Desarrollo rápido |
| **Hosting tradicional** | Apache/Nginx + FTP | Producción simple |
| **Docker** | docker-compose | Desarrollo con dependencias |
| **Kubernetes** | kubectl | Producción escalable |


---

## Seguridad (resumen corto)

- Cabeceras de hardening (CSP básica, X-Frame-Options, nosniff, Referrer-Policy, Permissions-Policy, COOP/COEP/CORP) y cookies de sesión HttpOnly + SameSite=Lax.
- CSRF en POST críticos, rate-limit/login throttling, firewall de payloads y sanitización de entrada.
- Sesiones con TTL/lifetime, sellado IP/UA y anti-replay en modo observación; rutas sensibles con AuthMiddleware/guards.
- Logs de seguridad con trace_id y secretos vía `.env` (app + microservicios); verificación previa a despliegue con `bin/security-check.sh` y workflow `security-check.yml`.
- Detalle completo, fases y hardening futuro en `docs/security/security.md`.

---

## Refactor estructural v2.0 (diciembre 2025)

Este refactor consolida la arquitectura del proyecto como implementación de Clean Architecture.

### Cambios principales

| Área | Cambio | Impacto |
|------|--------|---------|
| **Namespace** | Migración de namespaces legacy (`Src\*`) → `App\*` | PSR-4 estándar, compatibilidad con IDEs y PHPStan |
| **Autoload** | `"App\\": "src/"` en `composer.json` | Eliminación de ambigüedad en imports |
| **Tests** | Migración completa a namespace `Tests\` | Tests renombrados a `Tests\\` y suite ejecutable en CI |
| **RequestBodyReader** | Lectura única de `php://input` con caché | Evita bug "body vacío" en endpoints POST |
| **ApiFirewall** | Whitelist evaluada antes de leer body | Rutas RAG no consumen el stream |
| **Logging DEBUG** | Variables `DEBUG_API_FIREWALL`, `DEBUG_RAG_PROXY`, `DEBUG_RAW_BODY` | Logs condicionados: activos en dev, opcionales en prod |

### Variables de depuración (`.env`)

```env
# Nota: estos flags se evalúan solo en `APP_ENV=prod`; en `local`/`hosting` los logs pueden quedar activos por defecto.
DEBUG_API_FIREWALL=0   # Logs del firewall de payloads
DEBUG_RAG_PROXY=0      # Logs del proxy RAG
DEBUG_RAW_BODY=0       # Logs del lector de body HTTP
```

### Verificación post-refactor

```bash
composer dump-autoload -o
vendor/bin/phpunit --colors=always
vendor/bin/phpstan analyse
```

---

## Créditos

Proyecto creado por **Martín Pallante** · [Creawebes](https://www.creawebes.com)  
Asistente técnico: **Alfred** (asistente IA)

---

## Arquitectura del bootstrap (Composition Root)

El archivo `bootstrap.php` actúa como **Composition Root** del proyecto, separando responsabilidades en módulos especializados:

### Módulos Bootstrap

| Módulo | Responsabilidad |
|--------|----------------|
| **EnvironmentBootstrap** | Carga de `.env`, inicialización de sesión y generación de Trace ID |
| **PersistenceBootstrap** | Configuración de repositorios (DB/JSON) con fallback automático |
| **SecurityBootstrap** | Auth, CSRF, Rate Limit, Firewall y Anti-Replay |
| **EventBootstrap** | EventBus y suscriptores de eventos de dominio |
| **ObservabilityBootstrap** | Sentry, métricas de tokens y trazabilidad |
| **AppBootstrap** | Orquestador principal que coordina todos los módulos |

### Beneficios de la Modularización

- **Separación de responsabilidades**: Cada módulo tiene una única razón de cambio.
- **Mantenibilidad**: Fácil localizar y modificar configuración específica (seguridad, persistencia, etc.).
- **Testabilidad**: Los módulos pueden probarse de forma aislada.
- **Escalabilidad**: Permite añadir nuevos módulos (cache, queue, etc.) sin afectar los existentes.

Este enfoque documenta de forma explícita el wiring de dependencias y el orden de inicialización de la aplicación.

---

## Router HTTP (`src/Shared/Http/Router.php`)

El Router centraliza el enrutado HTTP y aplica un pipeline de middleware de seguridad antes de despachar a controladores.

### Arquitectura del Router

| Componente | Descripción |
|------------|-------------|
| **Pipeline de Seguridad** | 3 capas secuenciales: `ApiFirewall` → `RateLimitMiddleware` → `AuthMiddleware` |
| **Sistema de Rutas** | Declarativo con soporte para rutas estáticas y dinámicas (regex) |
| **Despacho por Método** | `match` expression para GET, POST, PUT, DELETE |
| **Lazy-Loading** | Controladores instanciados bajo demanda con caché interna |

### Pipeline de Seguridad (orden de ejecución)

```
Petición HTTP
    │
    ▼
┌─────────────────┐
│  1. ApiFirewall │ → Bloquea patrones maliciosos (SQL injection, XSS, etc.)
└────────┬────────┘
         ▼
┌─────────────────────────┐
│ 2. RateLimitMiddleware  │ → Protege contra abusos y DoS
└────────┬────────────────┘
         ▼
┌─────────────────────┐
│ 3. AuthMiddleware   │ → Verifica sesión en rutas /admin/*
└────────┬────────────┘
         ▼
    Controlador
```

### Sistema de Rutas Declarativas

Las rutas se definen en arrays tipados con soporte para patrones estáticos y expresiones regulares:

```php
// Ruta estática
['pattern' => '/albums', 'regex' => false, 'handler' => fn() => $this->albumController()->index()]

// Ruta dinámica con captura de parámetros
['pattern' => '#^/heroes/([A-Za-z0-9\-]+)$#', 'regex' => true, 'handler' => fn($id) => $this->heroController()->show($id)]
```

### Características Clave

- **Inyección de dependencias**: Recibe el contenedor como array asociativo desde `AppBootstrap`
- **Controladores cacheados**: Una vez instanciados, se reutilizan durante la petición
- **Manejo de errores**: Try-catch global con respuesta JSON genérica (sin leak de información)
- **Separación HTML/JSON**: Detecta `Accept: text/html` para renderizar vistas vs respuestas API

Esta implementación permite observar el flujo de una petición HTTP desde el front controller hasta el controlador.

---

## Notas

La documentación prioriza descripciones verificables frente a valoraciones subjetivas.
