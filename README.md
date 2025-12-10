# Clean Marvel Album – Documentación Técnica

![CI](https://github.com/20Luisma/marvel/actions/workflows/ci.yml/badge.svg)
![Coverage](https://sonarcloud.io/api/project_badges/measure?project=20Luisma_marvel&metric=coverage)
![Maintainability](https://sonarcloud.io/api/project_badges/measure?project=20Luisma_marvel&metric=sqale_rating)
![Pa11y](https://img.shields.io/badge/Pa11y-enabled-brightgreen)
![Playwright E2E](https://img.shields.io/badge/Playwright%20E2E-passing-brightgreen)
![Bundle Size](https://img.shields.io/badge/Bundle%20Size-static-blue)

**Clean Marvel Album** es un proyecto creado en paralelo a mi formación en el Máster de IA de Big School. Cada módulo del máster inspiró una parte del sistema: arquitectura limpia, seguridad, microservicios, RAG, automatización y buenas prácticas. A medida que avanzaba el curso, fui aplicando lo aprendido directamente en el código, convirtiendo este proyecto en un laboratorio real donde experimentar, equivocarme, mejorar y construir una aplicación profesional de principio a fin.

El resultado es una plataforma completa en **PHP 8.2** con **Arquitectura Limpia**, microservicios IA, métricas, paneles de calidad y un pipeline CI/CD totalmente automatizado. Más que un proyecto, es el reflejo del camino recorrido durante el máster.

> ✅ **Accesibilidad WCAG 2.1 AA**: Pa11y reporta `0 issues` en todas las páginas públicas.

---

## 🎯 Objetivo

- Mantener el **dominio** limpio e independiente de frameworks.
- Integrar IA mediante microservicios externos fáciles de sustituir.
- Servir como blueprint de proyecto escalable con tests, calidad y despliegue profesional.

---

## 🧠 Arquitectura General

| Capa | Ubicación principal | Responsabilidad |
| --- | --- | --- |
| **Presentación** | `public/`, `src/Controllers`, `views/`, `Src\Shared\Http\Router` | Front Controller + Router HTTP; render de vistas y respuestas JSON. |
| **Aplicación** | `src/*/Application`, `src/AI`, `src/Dev` | Casos de uso, orquestadores (comic generator, comparador RAG, seeders). |
| **Dominio** | `src/*/Domain` | Entidades, Value Objects, eventos y contratos de repositorios. |
| **Infraestructura** | `src/*/Infrastructure`, `storage/`, `Src\Shared\Infrastructure\Bus` | Repos JSON/DB, EventBus en memoria, adaptadores externos (notificaciones, gateways IA). |

Dependencias: Presentación → Aplicación → Dominio, e Infraestructura implementa contratos de Dominio. `App\Config\ServiceUrlProvider` resuelve los endpoints según entorno (`local` vs `hosting`).

### ¿Por qué Clean Architecture?

Esta arquitectura se eligió por razones técnicas:

**Beneficios clave:**
- **Independencia de frameworks**: El dominio no depende de librerías externas, facilitando la evolución tecnológica sin reescribir la lógica de negocio.
- **Testabilidad extrema**: Cada capa se prueba aisladamente. El dominio tiene tests puros sin mocks complejos, los casos de uso se testean sin HTTP, y la infraestructura se valida con doubles.
- **Mantenibilidad a largo plazo**: Los cambios en UI, base de datos o APIs externas no afectan las reglas de negocio. Un cambio en persistencia (JSON → MySQL) solo toca `Infrastructure`.
- **Escalabilidad gradual**: Permite añadir microservicios, cache o nuevos contextos sin refactorizar el core. Los microservicios IA (OpenAI, RAG) se integraron como adaptadores sin tocar el dominio.

La decisión arquitectónica completa está documentada en `docs/architecture/ADR-001-clean-architecture.md`.

---

## 🗂️ Estructura del Proyecto

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

## 💾 Persistencia: JSON en Local, MySQL en Hosting

- **Local (`APP_ENV=local`)** → JSON  
- **Hosting (`APP_ENV=hosting`)** → PDO MySQL  
- Si MySQL falla → fallback automático a JSON

Migración manual:

```bash
php bin/migrar-json-a-db.php
```

---

## 🧩 Microservicios y servicios externos

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

Pipelines: `ci.yml` (PHPUnit, PHPStan, Pa11y, Lighthouse, Playwright E2E, SonarCloud, bundle size estático), `deploy-ftp.yml` (deploy automático si todo pasa), `rollback-ftp.yml` (rollback).

- **Bundle size (JS/CSS)**: el job `sonarcloud` ejecuta `php bin/generate-bundle-size.php` y publica `public/assets/bundle-size.json`. La vista `/sonar` consume ese JSON para mostrar totales y top 5 sin necesitar `exec` en hosting.

---

## 🚀 Puesta en marcha (local)

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

## 🧪 Calidad y pruebas

El proyecto implementa una **estrategia de testing multinivel** con más de **120 tests automatizados**:

### Suite PHPUnit (117+ tests)

```bash
# Ejecutar todos los tests
vendor/bin/phpunit --colors=always

# Cobertura (~70%, objetivo: 80%+)
composer test:cov

# Análisis estático (PHPStan nivel 6)
vendor/bin/phpstan analyse --memory-limit=512M
```

### Tests E2E con Playwright (6 tests)

```bash
# Ejecutar tests E2E en localhost:8080 con navegador visible
npm run test:e2e

# Modo UI interactivo (recomendado)
npm run test:e2e:ui

# Modo debug paso a paso
npm run test:e2e:debug
```

**Tests E2E cubiertos**:
- ✅ Home y navegación principal (2 tests)
- ✅ Álbumes (renderizado y formularios)
- ✅ Héroes (galería y creación)
- ✅ Cómics (generación con IA)
- ✅ Películas (búsqueda y estados)

### Tipos de Tests Implementados

| Tipo | Cantidad | Herramienta | Cobertura |
|------|----------|-------------|-----------|
| **Unitarios y Dominio** | ~30 archivos | PHPUnit | Entidades, VOs, Eventos |
| **Casos de Uso** | ~25 archivos | PHPUnit | Application layer |
| **Seguridad** | 22 archivos | PHPUnit | CSRF, Rate Limit, Sessions, Firewall |
| **Controladores** | 21 archivos | PHPUnit | HTTP layer completa |
| **Infraestructura** | ~20 archivos | PHPUnit | Repos, HTTP clients, Bus |
| **E2E** | 5 archivos (6 tests) | Playwright | Flujos críticos |
| **Accesibilidad** | Pipeline CI | Pa11y | WCAG 2.1 AA (0 errores) |
| **Performance** | Pipeline CI | Lighthouse | Métricas de rendimiento |

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

**Documentación completa**: Ver `docs/guides/testing-complete.md` para detalles exhaustivos de cada tipo de test.

## 📚 Documentación ampliada

- `docs/ARCHITECTURE.md`: capas, flujos y microservicios.
- `docs/API_REFERENCE.md`: endpoints de la app y microservicios.
- `docs/README.md`: índice de documentación.
- `docs/guides/`: arranque rápido, autenticación, testing.
- `docs/microservicioheatmap/README.md`: integración del heatmap.
- `AGENTS.md` / `docs/agent.md`: roles y pautas para agentes de IA.
- UML completo

## 🐳 Containerización y Kubernetes

### Docker y Microservicios

El proyecto está **completamente preparado para contenedorización**. Los tres microservicios incluyen Dockerfiles y pueden ejecutarse en contenedores:

```bash
# Aplicación principal (PHP + Apache)
docker build -t 20luisma/clean-marvel:latest .
docker run -p 8080:8080 --env-file .env 20luisma/clean-marvel:latest

# Microservicio OpenAI
cd openai-service
docker build -t 20luisma/openai-service:latest .
docker run -p 8081:8081 --env-file .env 20luisma/openai-service:latest

# Microservicio RAG
cd rag-service
docker build -t 20luisma/rag-service:latest .
docker run -p 8082:80 --env-file .env 20luisma/rag-service:latest
```

**Docker Compose** permite levantar toda la stack con un solo comando:
```bash
docker-compose up -d
```

### Kubernetes (Orquestación)

El directorio `k8s/` contiene **manifiestos completos** para desplegar la aplicación y sus microservicios en un cluster de Kubernetes:

**Componentes incluidos:**
- ✅ **Deployments** escalables (2 réplicas por defecto)
- ✅ **Services ClusterIP** para comunicación interna
- ✅ **Ingress NGINX** con enrutamiento inteligente (`/` → frontend, `/api/rag/*` → RAG, `/api/openai/*` → OpenAI)
- ✅ **ConfigMaps** para configuración no sensible
- ✅ **Secrets** para credenciales (placeholders, deben sustituirse)
- ✅ **Health Probes** (liveness y readiness)
- ✅ **Resource Limits** (CPU/memoria)

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
- 📖 **[k8s/README.md](./k8s/README.md)** - Índice general y guía de uso
- 🚀 **[k8s/DEPLOY_K8S.md](./k8s/DEPLOY_K8S.md)** - Despliegue paso a paso
- 📚 **[k8s/PRODUCTION_CONSIDERATIONS.md](./k8s/PRODUCTION_CONSIDERATIONS.md)** - Mejoras para producción
- 🔒 **[k8s/SECURITY_HARDENING.md](./k8s/SECURITY_HARDENING.md)** - Hardening de seguridad

**Alcance actual:** Los manifiestos están diseñados para:
- ✅ Desarrollo y pruebas en clusters locales (minikube, kind, k3s)
- ✅ Demostración de arquitectura de microservicios
- ✅ Base sólida para evolución a producción

**Mejoras documentadas para producción:** Sealed Secrets, TLS automático (cert-manager), NetworkPolicies, Pod Security Admission, Image scanning, Runtime security (Falco), Observabilidad avanzada (Prometheus/Grafana), y más.

### Arquitectura Multi-Entorno

El proyecto soporta **múltiples estrategias de despliegue**:

| Entorno | Tecnología | Caso de uso |
|---------|-----------|-------------|
| **Local** | `php -S` | Desarrollo rápido |
| **Hosting tradicional** | Apache/Nginx + FTP | Producción simple |
| **Docker** | docker-compose | Desarrollo con dependencias |
| **Kubernetes** | kubectl | Producción escalable |


---

## 🔐 Seguridad (resumen corto)

- Cabeceras de hardening (CSP básica, X-Frame-Options, nosniff, Referrer-Policy, Permissions-Policy, COOP/COEP/CORP) y cookies de sesión HttpOnly + SameSite=Lax.
- CSRF en POST críticos, rate-limit/login throttling, firewall de payloads y sanitización de entrada.
- Sesiones con TTL/lifetime, sellado IP/UA y anti-replay en modo observación; rutas sensibles con AuthMiddleware/guards.
- Logs de seguridad con trace_id y secretos vía `.env` (app + microservicios); verificación previa a despliegue con `bin/security-check.sh` y workflow `security-check.yml`.
- Detalle completo, fases y backlog Enterprise en `docs/security.md`.

---

## 🔧 Refactor Estructural v2.0 (Diciembre 2025)

Este refactor consolida la arquitectura del proyecto como implementación de Clean Architecture.

### Cambios principales

| Área | Cambio | Impacto |
|------|--------|---------|
| **Namespace** | Migración de `Src\` → `App\` | PSR-4 estándar, compatibilidad con IDEs y PHPStan |
| **Autoload** | `"App\\": "src/"` en `composer.json` | Eliminación de ambigüedad en imports |
| **Tests** | Migración completa a namespace `Tests\` | 191 tests pasando sin referencias antiguas |
| **RequestBodyReader** | Lectura única de `php://input` con caché | Evita bug "body vacío" en endpoints POST |
| **ApiFirewall** | Whitelist evaluada antes de leer body | Rutas RAG no consumen el stream |
| **Logging DEBUG** | Variables `DEBUG_API_FIREWALL`, `DEBUG_RAG_PROXY`, `DEBUG_RAW_BODY` | Logs condicionados: activos en dev, opcionales en prod |

### Variables de depuración (`.env`)

```env
# Solo aplican en APP_ENV=prod; en local/dev siempre están activos
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

## 👤 Créditos

Proyecto creado por **Martín Pallante** · [Creawebes](https://www.creawebes.com)  
Asistente técnico: **Alfred**, IA desarrollada con ❤️

> *"Diseñando tecnología limpia, modular y con propósito."*

---

## 🧩 Arquitectura del Bootstrap (Composition Root)

El archivo `bootstrap.php` actúa como **Composition Root** del proyecto, pero con una arquitectura **modular y escalable** que separa responsabilidades en módulos especializados:

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

Esta arquitectura combina claridad en el wiring con las mejores prácticas empresariales (modularización, SRP). El resultado es un sistema que mantiene la **transparencia** del ensamblado completo, pero con una **estructura profesional** basada en **Clean Architecture** con fallback resiliente JSON/BD, seguridad multicapa, microservicios y trazabilidad.

---

## 🛤️ Router HTTP (`src/Shared/Http/Router.php`)

El Router es el **punto de entrada principal** de todas las peticiones HTTP. Implementa un diseño custom que demuestra los principios de un enrutador profesional sin depender de librerías externas.

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

Esta implementación custom permite entender cómo funcionan los routers internamente, manteniendo un nivel profesional de seguridad y mantenibilidad.

---

## 💭 Reflexión Final

> *Este proyecto no pretende definir cómo debe hacerse arquitectura profesional, sino mostrar mi proceso de aprendizaje y experimentación aplicando conceptos del Máster.*

---

> ⚡ *"Como un centauro del universo Marvel, este proyecto fusiona la creatividad humana con la fuerza imparable de la IA: dos mitades, un héroe completo."*
