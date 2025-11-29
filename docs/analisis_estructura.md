# 📊 Análisis Completo de la Estructura del Proyecto Clean Marvel Album

## 🎯 Resumen Ejecutivo

**Clean Marvel Album** es una aplicación web educativa/demo desarrollada en **PHP 8.2+** que implementa **Arquitectura Limpia (Clean Architecture)** para gestionar álbumes y héroes de Marvel. El proyecto destaca por su arquitectura modular, integración con microservicios de IA, y un robusto sistema de observabilidad y calidad.

---

## 🏗️ Arquitectura General

### Principios Fundamentales

El proyecto sigue estrictamente los principios de **Clean Architecture**:

```
┌─────────────────────────────────────────────────────────┐
│                    PRESENTACIÓN                          │
│  (public/, src/Controllers, views/)                      │
│  - Front Controller + Router HTTP                        │
│  - Renderizado de vistas y respuestas JSON              │
└──────────────────┬──────────────────────────────────────┘
                   │
┌──────────────────▼──────────────────────────────────────┐
│                    APLICACIÓN                            │
│  (src/*/Application, src/AI, src/Dev)                   │
│  - Casos de uso                                          │
│  - Orquestadores (comic generator, RAG, seeders)        │
└──────────────────┬──────────────────────────────────────┘
                   │
┌──────────────────▼──────────────────────────────────────┐
│                      DOMINIO                             │
│  (src/*/Domain)                                          │
│  - Entidades y Value Objects                            │
│  - Eventos de dominio                                    │
│  - Contratos de repositorios (interfaces)               │
└──────────────────┬──────────────────────────────────────┘
                   │
┌──────────────────▼──────────────────────────────────────┐
│                  INFRAESTRUCTURA                         │
│  (src/*/Infrastructure, storage/)                       │
│  - Implementaciones de repositorios (JSON/MySQL)        │
│  - EventBus en memoria                                   │
│  - Adaptadores externos (APIs, gateways)                │
└─────────────────────────────────────────────────────────┘
```

**Flujo de dependencias**: Presentación → Aplicación → Dominio ← Infraestructura

---

## 📁 Estructura de Directorios Detallada

### 🔹 Raíz del Proyecto

```
clean-marvel/
├── .github/              # CI/CD workflows (GitHub Actions)
├── .vscode/              # Configuración del IDE
├── app/                  # Configuración de aplicación
├── bin/                  # Scripts ejecutables
├── config/               # Archivos de configuración
├── context/              # Contexto de la aplicación
├── database/             # Migraciones y esquemas
├── docs/                 # Documentación completa
├── n8n/                  # Integraciones con n8n
├── node_modules/         # Dependencias Node.js
├── openai-service/       # Microservicio de OpenAI (puerto 8081)
├── public/               # Punto de entrada web
├── rag-service/          # Microservicio RAG (puerto 8082)
├── src/                  # Código fuente principal
├── storage/              # Persistencia y logs
├── storage.example/      # Ejemplos de estructura de storage
├── test-results/         # Resultados de pruebas E2E
├── tests/                # Suite de pruebas
├── vendor/               # Dependencias PHP (Composer)
└── views/                # Plantillas de vistas
```

### 🔹 `/src` - Código Fuente Principal

Organizado por **módulos de dominio** siguiendo Clean Architecture:

```
src/
├── Activities/           # Módulo de actividades
│   ├── Application/      # Casos de uso de actividades
│   ├── Domain/           # Entidades y contratos
│   └── Infrastructure/   # Implementaciones
│
├── Albums/               # Módulo de álbumes
│   ├── Application/      # Casos de uso (crear, listar, eliminar)
│   ├── Domain/           # Entidad Album, eventos, repositorio
│   └── Infrastructure/   # AlbumRepositoryJson, AlbumRepositoryDb
│
├── Heroes/               # Módulo de héroes
│   ├── Application/      # Casos de uso de héroes
│   ├── Domain/           # Entidad Hero, Value Objects
│   └── Infrastructure/   # Repositorios concretos
│
├── AI/                   # Servicios de Inteligencia Artificial
│   └── Application/      # OpenAIComicGenerator, RAG comparators
│
├── Controllers/          # Controladores HTTP
│   ├── AlbumController.php
│   ├── HeroController.php
│   ├── ComicController.php
│   ├── RagProxyController.php
│   ├── PageController.php
│   └── ...
│
├── Security/             # Módulo de seguridad
│   ├── CSRF protection
│   ├── Rate limiting
│   ├── Authentication
│   ├── Firewall
│   └── Session management
│
├── Notifications/        # Sistema de notificaciones
│   ├── Application/
│   ├── Domain/
│   └── Infrastructure/
│
├── Monitoring/           # Observabilidad
│   └── Application/      # Integraciones Sentry, SonarCloud
│
├── Heatmap/              # Integración con servicio de heatmap
│
├── Dev/                  # Herramientas de desarrollo
│   ├── Seed/             # Seeders para datos de prueba
│   └── Test/             # Runner de PHPUnit
│
├── Shared/               # Componentes compartidos
│   ├── Domain/           # Interfaces y contratos comunes
│   ├── Http/             # Router HTTP
│   ├── Infrastructure/   # EventBus, utilidades
│   ├── Markdown/         # Procesamiento de markdown
│   └── Util/             # Utilidades generales
│
├── Config/               # Configuración
│   └── ServiceUrlProvider.php  # Resuelve URLs según entorno
│
└── bootstrap.php         # Inicialización y wiring de dependencias
```

### 🔹 `/public` - Punto de Entrada Web

```
public/
├── index.php             # Front Controller principal
├── home.php              # Página de inicio
├── oficial-marvel.php    # Página oficial de Marvel
├── sentry.php            # Configuración Sentry
├── .htaccess             # Configuración Apache
│
├── api/                  # Endpoints API
│   ├── accessibility-marvel.php      # WAVE API
│   ├── github-activity.php           # GitHub PRs
│   ├── performance-marvel.php        # PageSpeed Insights
│   ├── sentry-metrics.php            # Métricas Sentry
│   ├── sonar-metrics.php             # Métricas SonarCloud
│   ├── tts-elevenlabs.php            # Text-to-Speech
│   ├── marvel-agent.php              # Agente Marvel
│   ├── rag/                          # Endpoints RAG
│   └── heatmap/                      # Endpoints heatmap
│
├── assets/               # Recursos estáticos
│   ├── css/              # Estilos
│   ├── js/               # JavaScript
│   └── images/           # Imágenes
│
└── uploads/              # Archivos subidos por usuarios
```

### 🔹 `/storage` - Persistencia y Logs

```
storage/
├── albums.json           # Datos de álbumes (modo local)
├── heroes.json           # Datos de héroes (modo local)
├── notifications.log     # Log de notificaciones
├── sentry-metrics.json   # Caché de métricas Sentry
│
├── actividad/            # Registro de actividades
│   └── *.json
│
├── logs/                 # Logs de la aplicación
│
├── marvel/               # Datos específicos de Marvel
│
├── rate_limit/           # Control de rate limiting
│
├── security/             # Logs de seguridad
│
└── sessions/             # Sesiones de usuario
```

### 🔹 `/tests` - Suite de Pruebas

```
tests/
├── Albums/               # Tests del módulo Albums
├── Heroes/               # Tests del módulo Heroes
├── Activities/           # Tests de actividades
├── Notifications/        # Tests de notificaciones
├── Security/             # Tests de seguridad
├── Controllers/          # Tests de controladores
├── Application/          # Tests de casos de uso
├── Infrastructure/       # Tests de infraestructura
├── Shared/               # Tests de componentes compartidos
├── AI/                   # Tests de servicios IA
├── Monitoring/           # Tests de observabilidad
├── Unit/                 # Tests unitarios
├── Smoke/                # Tests de humo
├── Sonar/                # Tests de integración SonarCloud
├── e2e/                  # Tests end-to-end (Playwright)
├── Doubles/              # Test doubles
├── Fakes/                # Fakes para testing
├── Support/              # Utilidades de testing
└── bootstrap.php         # Bootstrap de tests
```

### 🔹 `/docs` - Documentación

```
docs/
├── README.md             # Índice de documentación
├── ARCHITECTURE.md       # Arquitectura detallada
├── API_REFERENCE.md      # Referencia de API
├── REQUIREMENTS.md       # Requisitos del proyecto
├── ROADMAP.md            # Hoja de ruta
├── CHANGELOG.md          # Registro de cambios
├── CONTRIBUTING.md       # Guía de contribución
├── TASKS_AUTOMATION.md   # Automatización de tareas
├── USE_CASES.md          # Casos de uso
├── security.md           # Documentación de seguridad
├── deploy.md             # Guía de despliegue
├── agent.md              # Guía para agentes IA
│
├── guides/               # Guías específicas
│   ├── quick-start.md
│   ├── authentication.md
│   └── testing.md
│
├── architecture/         # Diagramas de arquitectura
├── api/                  # Documentación de API
├── components/           # Documentación de componentes
├── microservicioheatmap/ # Documentación del microservicio heatmap
├── marvel-agent/         # Documentación del agente Marvel
└── uml/                  # Diagramas UML
```

### 🔹 `/views` - Plantillas de Vistas

```
views/
├── layouts/              # Layouts base
│   ├── main.php
│   └── admin.php
│
├── pages/                # Páginas completas
│   ├── home.php
│   ├── comic.php
│   ├── accessibility.php
│   ├── performance.php
│   ├── panel-github.php
│   ├── sentry.php
│   ├── sonar.php
│   ├── secret-heatmap.php
│   ├── repo-marvel.php
│   └── ...
│
├── partials/             # Componentes reutilizables
│   ├── header.php
│   ├── footer.php
│   ├── navigation.php
│   └── ...
│
├── albums/               # Vistas específicas de álbumes
│
└── helpers.php           # Funciones auxiliares de vistas
```

---

## 🧩 Microservicios

### 1️⃣ OpenAI Service (Puerto 8081)

**Ubicación**: `/openai-service/`

**Responsabilidad**: Gateway hacia la API de OpenAI

**Características**:
- Endpoint principal: `POST /v1/chat`
- Usa cURL para comunicarse con OpenAI
- Fallback a respuestas JSON cuando falta `OPENAI_API_KEY`
- Configurable con `OPENAI_MODEL`
- Tiene su propio `composer.json` y `.env`

**Estructura**:
```
openai-service/
├── public/
│   └── index.php
├── src/
│   └── Creawebes/OpenAI/Http/Router.php
├── composer.json
└── .env
```

### 2️⃣ RAG Service (Puerto 8082)

**Ubicación**: `/rag-service/`

**Responsabilidad**: Recuperación de contexto y comparación de héroes

**Características**:
- Endpoints: `POST /rag/heroes`, `POST /rag/agent`
- Base de conocimiento en `storage/knowledge/*.json`
- Retriever léxico por defecto
- Retriever vectorial opcional con embeddings (`RAG_USE_EMBEDDINGS=1`)
- Delega respuestas finales a `openai-service`
- Servicio `HeroRagService` y `MarvelAgent`

**Estructura**:
```
rag-service/
├── public/
├── src/
├── storage/
│   ├── knowledge/
│   │   └── heroes.json
│   ├── embeddings/
│   └── marvel_agent_kb.json
├── bin/
│   └── refresh_marvel_agent.sh
└── composer.json
```

### 3️⃣ Heatmap Service (Python/Flask - Externo)

**Responsabilidad**: Recolección y análisis de clics de usuarios

**Características**:
- Servicio externo en Python/Flask
- Recoge clics reales para `/secret-heatmap`
- Documentación en `docs/microservicioheatmap/README.md`
- Contenedor Docker disponible
- Requiere `HEATMAP_API_TOKEN`

---

## 🔐 Sistema de Seguridad

El proyecto implementa múltiples capas de seguridad:

### Características de Seguridad

1. **Cabeceras de Hardening**
   - CSP (Content Security Policy)
   - X-Frame-Options
   - X-Content-Type-Options (nosniff)
   - Referrer-Policy
   - Permissions-Policy
   - COOP/COEP/CORP

2. **Protección CSRF**
   - Tokens CSRF en POST críticos
   - Validación en middleware

3. **Rate Limiting**
   - Control de tasa de peticiones
   - Login throttling
   - Almacenamiento en `storage/rate_limit/`

4. **Firewall de Payloads**
   - Sanitización de entrada
   - Validación de datos

5. **Gestión de Sesiones**
   - Cookies HttpOnly + SameSite=Lax
   - TTL/lifetime configurables
   - Sellado IP/UA
   - Anti-replay en modo observación
   - Almacenamiento en `storage/sessions/`

6. **Autenticación**
   - AuthMiddleware para rutas sensibles
   - Guards de autorización
   - Hash de contraseñas seguro

7. **Logging de Seguridad**
   - Trace IDs para auditoría
   - Logs en `storage/security/`
   - Sin exposición de secretos

8. **HMAC para Microservicios**
   - Firma con `INTERNAL_API_KEY`
   - Cabeceras `X-Internal-*`
   - Validación de timestamp

---

## 🧪 Sistema de Calidad y Testing

### Herramientas de Calidad

1. **PHPUnit** (`phpunit.xml.dist`)
   - Suite completa de tests
   - Cobertura de código
   - Bootstrap en `tests/bootstrap.php`
   - Caché en `.phpunit.cache/`

2. **PHPStan** (`phpstan.neon`)
   - Análisis estático nivel 6
   - Excluye `src/Dev`
   - Configuración personalizada

3. **Composer Audit**
   - Auditoría de dependencias
   - Script: `composer security:audit`

4. **Pa11y**
   - Auditoría de accesibilidad WCAG 2.1 AA
   - Script: `pa11y-all.sh`
   - Reporta 0 issues en páginas públicas

5. **Lighthouse**
   - Auditoría de performance
   - Configuración en `lighthouserc.json`

6. **Playwright**
   - Tests E2E
   - Configuración en `playwright.config.cjs`
   - Resultados en `test-results/`

7. **SonarCloud**
   - Análisis de calidad de código
   - Configuración en `sonar-project.properties`
   - Métricas de cobertura y mantenibilidad

### Scripts de Testing

```bash
# Tests completos
vendor/bin/phpunit --colors=always

# Cobertura
composer test:cov

# Análisis estático
vendor/bin/phpstan analyse --memory-limit=512M

# Auditoría de seguridad
composer security:audit

# Validación Composer
composer validate
```

---

## 🔄 CI/CD - GitHub Actions

### Workflows Disponibles

**Ubicación**: `.github/workflows/`

1. **ci.yml** - Pipeline de Integración Continua
   - PHPUnit (tests)
   - PHPStan (análisis estático)
   - Pa11y (accesibilidad)
   - Lighthouse (performance)
   - Playwright E2E
   - SonarCloud (calidad)

2. **deploy-ftp.yml** - Despliegue Automático
   - Se ejecuta si todos los tests pasan
   - Deploy vía FTP

3. **rollback-ftp.yml** - Rollback
   - Reversión de despliegues

4. **security-check.yml** - Verificación de Seguridad
   - Ejecuta `bin/security-check.sh`
   - Pre-despliegue

---

## 🌐 Persistencia Multi-Entorno

### Estrategia de Persistencia

El proyecto soporta dos modos de persistencia según el entorno:

#### Modo Local (`APP_ENV=local`)
- **Repositorios**: JSON
- **Ubicación**: `storage/*.json`
- **Ventajas**: Sin dependencias externas, fácil desarrollo

#### Modo Hosting (`APP_ENV=hosting`)
- **Repositorios**: PDO MySQL
- **Fallback**: JSON automático si falla MySQL
- **Migración**: `php bin/migrar-json-a-db.php`

### Implementación

Cada módulo tiene dos implementaciones de repositorio:

```
src/Albums/Infrastructure/
├── AlbumRepositoryJson.php    # Persistencia en JSON
└── AlbumRepositoryDb.php       # Persistencia en MySQL
```

El `ServiceUrlProvider` resuelve automáticamente qué implementación usar según `APP_ENV`.

---

## 🎨 Sistema de Vistas

### Arquitectura de Vistas

**Sin framework de templates** (Twig-less), usa PHP puro:

```php
// views/layouts/main.php
<!DOCTYPE html>
<html>
<head>
    <?php include __DIR__ . '/../partials/header.php'; ?>
</head>
<body>
    <?php include __DIR__ . '/../partials/navigation.php'; ?>
    <main>
        <?php echo $content; ?>
    </main>
    <?php include __DIR__ . '/../partials/footer.php'; ?>
</body>
</html>
```

### Helpers de Vistas

**Ubicación**: `views/helpers.php`

Funciones auxiliares para renderizado, escape de HTML, formateo, etc.

---

## 🔌 Integraciones Externas

### APIs Integradas

1. **OpenAI API**
   - Generación de cómics
   - Comparación de héroes
   - Variable: `OPENAI_API_KEY`

2. **ElevenLabs TTS**
   - Narración de texto a audio
   - Voz por defecto: Charlie (`EXAVITQu4vr4xnSDxMaL`)
   - Modelo: `eleven_multilingual_v2`
   - Variables: `ELEVENLABS_API_KEY`, `ELEVENLABS_VOICE_ID`

3. **WAVE API (WebAIM)**
   - Auditoría de accesibilidad
   - Variable: `WAVE_API_KEY`
   - Endpoint: `/api/accessibility-marvel.php`

4. **PageSpeed Insights**
   - Análisis de performance
   - Variable: `PSI_API_KEY`
   - Endpoint: `/api/performance-marvel.php`

5. **GitHub API**
   - Actividad de Pull Requests
   - Variable: `GITHUB_API_KEY`
   - Endpoint: `/api/github-activity.php`

6. **Sentry**
   - Monitoreo de errores
   - Variables: `SENTRY_DSN`, `SENTRY_API_TOKEN`
   - Endpoints: `/sentry.php`, `/api/sentry-metrics.php`

7. **SonarCloud**
   - Métricas de calidad
   - Endpoint: `/api/sonar-metrics.php`

8. **YouTube API**
   - Gestión de videos Marvel
   - Variable: `GOOGLE_YT_API_KEY`

---

## 📊 Paneles de Observabilidad

El proyecto incluye múltiples paneles técnicos:

1. **`/comic`** - Generador de Cómics + RAG
   - Genera historias Marvel con IA
   - Compara héroes con contexto
   - Audio con ElevenLabs

2. **`/panel-github`** - Actividad GitHub
   - Pull Requests recientes
   - Estado de CI/CD

3. **`/sonar`** - Métricas SonarCloud
   - Cobertura de código
   - Deuda técnica
   - Code smells

4. **`/sentry`** - Monitoreo Sentry
   - Errores en producción
   - Performance monitoring

5. **`/accessibility`** - Auditoría WCAG
   - Resultados WAVE API
   - Reporte de accesibilidad

6. **`/performance`** - PageSpeed Insights
   - Métricas de rendimiento
   - Core Web Vitals

7. **`/secret-heatmap`** - Heatmap de Clics
   - Visualización de interacciones
   - Datos del microservicio Python

8. **`/repo-marvel`** - Navegador de Repositorio
   - Exploración de código

9. **`/readme`** - README Vivo
   - Documentación en vivo

---

## 🛠️ Herramientas de Desarrollo

### Scripts Composer

```json
{
  "scripts": {
    "serve": "php -S localhost:8080 -t public",
    "test": "phpunit",
    "test:coverage": "phpunit --coverage-clover coverage.xml",
    "security:audit": "composer audit --no-dev"
  }
}
```

### Comandos Útiles

```bash
# Servidor principal
composer serve

# Microservicio OpenAI
cd openai-service && php -S localhost:8081 -t public

# Microservicio RAG
cd rag-service && php -S localhost:8082 -t public

# Tests
vendor/bin/phpunit --colors=always

# Análisis estático
vendor/bin/phpstan analyse --memory-limit=512M

# Migración JSON a DB
php bin/migrar-json-a-db.php

# Refrescar KB Marvel Agent
cd rag-service && ./bin/refresh_marvel_agent.sh
```

### Tareas VS Code

**Documentación**: `docs/TASKS_AUTOMATION.md`

Tareas automatizadas para desarrollo en VS Code.

---

## 🎯 Casos de Uso Principales

### Módulo Albums

1. **Crear Álbum** (`CreateAlbumUseCase`)
2. **Listar Álbumes** (`ListAlbumsUseCase`)
3. **Eliminar Álbum** (`DeleteAlbumUseCase`)
4. **Obtener Álbum** (`GetAlbumUseCase`)

### Módulo Heroes

1. **Crear Héroe** (`CreateHeroUseCase`)
2. **Listar Héroes** (`ListHeroesUseCase`)
3. **Eliminar Héroe** (`DeleteHeroUseCase`)
4. **Buscar Héroe** (`FindHeroUseCase`)

### Módulo AI

1. **Generar Cómic** (`OpenAIComicGenerator`)
2. **Comparar Héroes con RAG** (`HeroRagService`)
3. **Marvel Agent** (`MarvelAgent`)

### Módulo Notifications

1. **Registrar Notificación** (`LogNotificationUseCase`)
2. **Listar Notificaciones** (`ListNotificationsUseCase`)
3. **Limpiar Log** (`ClearNotificationsUseCase`)

### Módulo Activities

1. **Registrar Actividad** (`LogActivityUseCase`)
2. **Obtener Actividades** (`GetActivitiesUseCase`)

---

## 🔔 Sistema de Eventos

### EventBus en Memoria

**Ubicación**: `Src\Shared\Infrastructure\Bus\InMemoryEventBus`

### Eventos de Dominio

1. **Albums**
   - `AlbumCreated`
   - `AlbumUpdated`
   - `AlbumDeleted`

2. **Heroes**
   - `HeroCreated`
   - `HeroUpdated`
   - `HeroDeleted`

### Registro de Handlers

**Ubicación**: `src/bootstrap.php`

Los handlers se registran en el bootstrap y deben ser **idempotentes**.

---

## 📦 Dependencias

### PHP (Composer)

```json
{
  "require": {
    "php": ">=8.2",
    "sentry/sentry": "^4.18"
  },
  "require-dev": {
    "phpunit/phpunit": "^10.5",
    "mockery/mockery": "^1.6",
    "phpstan/phpstan": "^2.1"
  }
}
```

### Node.js (Package.json)

Mínimo, principalmente para herramientas de testing E2E (Playwright).

---

## 🌍 Configuración de Entornos

### Variables de Entorno (`.env`)

**Categorías**:

1. **APP**: Configuración general
   - `APP_ENV` (local/hosting)
   - `APP_DEBUG`
   - `APP_URL`
   - `ADMIN_EMAIL`, `ADMIN_PASSWORD_HASH`

2. **DB**: Base de datos
   - `DB_DSN`, `DB_USER`, `DB_PASSWORD`

3. **INTERNAL**: Claves internas
   - `INTERNAL_API_KEY` (HMAC)

4. **MICROSERVICIOS**:
   - `OPENAI_SERVICE_URL`
   - `RAG_SERVICE_URL`

5. **EXTERNAL APIS**:
   - `OPENAI_API_KEY`
   - `ELEVENLABS_API_KEY`
   - `WAVE_API_KEY`
   - `PSI_API_KEY`
   - `GITHUB_API_KEY`
   - `GOOGLE_YT_API_KEY`

6. **OBSERVABILIDAD**:
   - `SENTRY_DSN`, `SENTRY_API_TOKEN`

7. **HEATMAP**:
   - `HEATMAP_API_BASE_URL`
   - `HEATMAP_API_TOKEN`

### ServiceUrlProvider

**Ubicación**: `App\Config\ServiceUrlProvider`

Resuelve automáticamente URLs de servicios según `APP_ENV`:
- Local: `http://localhost:8081`, `http://localhost:8082`
- Hosting: URLs de producción

---

## 🎓 Patrones y Principios Aplicados

### Clean Architecture
- ✅ Inversión de dependencias
- ✅ Separación de capas
- ✅ Dominio independiente

### SOLID
- ✅ Single Responsibility
- ✅ Open/Closed
- ✅ Liskov Substitution
- ✅ Interface Segregation
- ✅ Dependency Inversion

### Domain-Driven Design (DDD)
- ✅ Entidades y Value Objects
- ✅ Repositorios
- ✅ Eventos de dominio
- ✅ Casos de uso

### Otros Patrones
- ✅ Repository Pattern
- ✅ Event-Driven Architecture
- ✅ Gateway Pattern (microservicios)
- ✅ Adapter Pattern (infraestructura)
- ✅ Strategy Pattern (persistencia multi-entorno)

---

## 🚀 Flujo de Desarrollo Recomendado

### 1. Setup Inicial

```bash
# Clonar repositorio
git clone <repo-url>
cd clean-marvel

# Instalar dependencias
composer install

# Configurar entorno
cp .env.example .env
# Editar .env con tus credenciales

# Instalar dependencias de microservicios
cd openai-service && composer install && cd ..
cd rag-service && composer install && cd ..
```

### 2. Levantar Servicios

```bash
# Terminal 1: App principal
composer serve

# Terminal 2: OpenAI Service
cd openai-service && php -S localhost:8081 -t public

# Terminal 3: RAG Service
cd rag-service && php -S localhost:8082 -t public
```

### 3. Desarrollo

```bash
# Ejecutar tests
vendor/bin/phpunit --colors=always

# Análisis estático
vendor/bin/phpstan analyse --memory-limit=512M

# Validar composer
composer validate

# Auditoría de seguridad
composer security:audit
```

### 4. Pre-Commit

```bash
# Ejecutar suite completa
vendor/bin/phpunit
vendor/bin/phpstan analyse
composer validate
composer security:audit
```

---

## 📝 Documentación Adicional

### Archivos Clave

1. **`README.md`** - Documentación principal
2. **`AGENTS.md`** - Guía para agentes IA
3. **`docs/ARCHITECTURE.md`** - Arquitectura detallada
4. **`docs/API_REFERENCE.md`** - Referencia de API
5. **`docs/security.md`** - Seguridad completa
6. **`docs/REQUIREMENTS.md`** - Requisitos del proyecto
7. **`docs/ROADMAP.md`** - Hoja de ruta
8. **`docs/USE_CASES.md`** - Casos de uso

### Guías

- `docs/guides/quick-start.md`
- `docs/guides/authentication.md`
- `docs/guides/testing.md`

### Microservicios

- `docs/microservicioheatmap/README.md`

---

## 🎯 Puntos Destacables

### ✅ Fortalezas

1. **Arquitectura Limpia Estricta**
   - Separación clara de responsabilidades
   - Inversión de dependencias correcta
   - Dominio puro sin dependencias externas

2. **Calidad de Código**
   - Cobertura de tests completa
   - PHPStan nivel 6
   - CI/CD robusto
   - SonarCloud integrado

3. **Seguridad**
   - Múltiples capas de protección
   - HMAC para microservicios
   - Rate limiting
   - CSRF protection

4. **Observabilidad**
   - Múltiples paneles de monitoreo
   - Integración Sentry
   - Logs estructurados
   - Métricas de calidad

5. **Modularidad**
   - Microservicios independientes
   - Módulos de dominio bien definidos
   - Fácil extensibilidad

6. **Documentación**
   - Documentación exhaustiva
   - Diagramas UML
   - Guías para desarrolladores
   - API reference completa

7. **Accesibilidad**
   - WCAG 2.1 AA compliant
   - Pa11y con 0 issues
   - Auditorías automatizadas

8. **Multi-Entorno**
   - Soporte local y hosting
   - Fallback automático
   - Configuración flexible

### 🔧 Áreas de Mejora Potencial

1. **Templates**
   - Considera usar un motor de templates (Twig, Blade) para mayor seguridad y mantenibilidad

2. **Caché**
   - Implementar caché de aplicación (Redis, Memcached) para mejorar performance

3. **Contenedores**
   - Docker Compose completo para todo el stack (app + microservicios + DB)

4. **API REST**
   - Versioning de API más explícito
   - OpenAPI/Swagger documentation

---

## 🎬 Conclusión

**Clean Marvel Album** es un proyecto **ejemplar** que demuestra:

- ✅ Implementación correcta de **Clean Architecture**
- ✅ Integración de **microservicios de IA**
- ✅ **Calidad de código** profesional
- ✅ **Seguridad** robusta
- ✅ **Observabilidad** completa
- ✅ **Documentación** exhaustiva
- ✅ **Testing** comprehensivo
- ✅ **CI/CD** automatizado

Es un **blueprint perfecto** para proyectos PHP modernos que requieren:
- Arquitectura escalable
- Integración con IA
- Calidad empresarial
- Observabilidad completa

---

## 👤 Créditos

**Proyecto creado por**: Martín Pallante · [Creawebes](https://www.creawebes.com)  
**Asistente técnico**: Alfred, IA desarrollada con ❤️

> *"Diseñando tecnología limpia, modular y con propósito."*
