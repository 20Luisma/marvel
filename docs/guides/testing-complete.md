# Guía Completa de Testing — Clean Marvel Album

## Resumen ejecutivo

Clean Marvel Album implementa una **estrategia de testing multinivel** que cubre tests unitarios, integración, seguridad, E2E, accesibilidad y rendimiento.

### Cobertura Actual

- **Tests PHPUnit**: ver ejecución de `vendor/bin/phpunit`
- **Tests E2E (Playwright)**: ver `tests/e2e/`
- **Cobertura de código**: ver `COVERAGE.md` y `coverage.xml` (Clover)
- **Análisis estático**: PHPStan (config en `phpstan.neon`)
- **Accesibilidad**: Pa11y WCAG 2.1 AA
- **Performance**: Lighthouse CI

---

## Tipos de tests implementados

### 1. Tests Unitarios de Dominio

**Ubicación**: `tests/{Contexto}/Domain/`

**Propósito**: Validar la lógica de negocio pura sin dependencias externas.

**Ejemplos**:
```
tests/
├── Albums/Domain/
│   ├── AlbumTest.php
│   ├── Event/AlbumCreatedTest.php
│   └── Event/AlbumUpdatedTest.php
├── Heroes/Domain/
│   ├── HeroTest.php
│   └── Event/HeroCreatedTest.php
└── Shared/Domain/Bus/
    └── DomainEventTest.php
```

**Comandos**:
```bash
# Ejecutar solo tests de dominio de Albums
vendor/bin/phpunit tests/Albums/Domain

# Ejecutar todos los tests de dominio
vendor/bin/phpunit --testsuite domain
```

---

### 2. Tests de Casos de Uso (Application)

**Ubicación**: `tests/{Contexto}/Application/`

**Propósito**: Validar la orquestación de casos de uso con repositorios en memoria.

**Ejemplos**:
```
tests/
├── Albums/Application/
│   ├── CreateAlbumUseCaseTest.php
│   ├── UpdateAlbumUseCaseTest.php
│   ├── DeleteAlbumUseCaseTest.php
│   └── ListAlbumsUseCaseTest.php
├── Heroes/Application/
│   ├── CreateHeroUseCaseTest.php
│   └── SeedHeroesServiceTest.php
└── Application/
    ├── CreateAndListAlbumTest.php
    └── CreateHeroPublishesEventTest.php
```

**Características**:
- Usan **repositorios en memoria** (no tocan disco ni BD)
- Validan que los **eventos de dominio se publican**
- Prueban flujos completos de casos de uso

---

### 3. Tests de Infraestructura

**Ubicación**: `tests/{Contexto}/Infrastructure/`

**Propósito**: Validar adaptadores externos, repositorios reales, clientes HTTP.

**Ejemplos**:
```
tests/
├── Albums/Infrastructure/
│   └── DbAlbumRepositoryTest.php
├── Heroes/Infrastructure/
│   └── DbHeroRepositoryTest.php
├── Heatmap/
│   └── HttpHeatmapApiClientTest.php
├── Infrastructure/Http/
│   ├── CurlHttpClientTest.php
│   └── AuthGuardsTest.php
├── Shared/Infrastructure/
│   ├── Bus/EventBusTest.php
│   └── Resilience/
│       └── CircuitBreakerTest.php
└── Bootstrap/Config/
    └── SecurityConfigTest.php
```

**Casos cubiertos**:
- Persistencia JSON/DB
- Clientes HTTP (con mocks)
- Bus de eventos en memoria
- Rate limiters con timestamp
- **Circuit Breaker** (estados closed/open/half-open, fallbacks)
- **SecurityConfig Value Object** (validación, factory methods)

---

### 4. Tests de Controladores

**Ubicación**: `tests/Controllers/`

**Propósito**: Validar la capa HTTP sin servidor real.

**Ejemplos**:
```
tests/Controllers/
├── AlbumControllerTest.php
├── AlbumControllerExtendedTest.php
├── AlbumControllerUpdateTest.php
├── AlbumControllerUploadTest.php
├── HeroControllerTest.php
├── HeroControllerExtendedTest.php
├── ComicControllerTest.php
├── AuthControllerTest.php
├── RagProxyControllerTest.php
├── RagProxyControllerSecurityTest.php
├── ActivityControllerTest.php
├── NotificationControllerTest.php
├── PageControllerTest.php
├── AdminControllerTest.php
├── ConfigControllerTest.php
└── DevControllerTest.php
```

**Validan**:
- Respuestas HTTP correctas
- Validación de input
- Manejo de errores
- Autenticación y autorización

---

### 5. Tests de Seguridad ⚔️

**Ubicación**: `tests/Security/`

**Propósito**: Validar todos los controles de seguridad implementados.

**Estructura** (22 archivos):
```
tests/Security/
├── AdminRouteProtectionTest.php
├── ApiFirewallTest.php
├── AuthServiceTest.php
├── HeadersSecurityTest.php
├── CspStrictTest.php
├── LoginAttemptServiceTest.php
├── RateLimiterTest.php
├── RateLimitMiddlewareTest.php
├── SessionIntegrityTest.php
├── SessionLifetimeTest.php
├── SessionTtlTest.php
├── SessionReplayMonitorTest.php
├── LogSanitizerTest.php
├── SanitizerTest.php
├── SanitizerExtendedTest.php
├── SecuritySmokeTest.php
├── Config/
│   └── SecurityConfigTest.php
├── Csrf/
│   └── CsrfMiddlewareTest.php
├── Http/
│   ├── CsrfMiddlewareTest.php
│   └── RequestBodyReaderTest.php
└── Validation/
    ├── InputValidatorTest.php
    └── PayloadValidatorTest.php
```

**Controles testeados**:
- Cabeceras de seguridad (X-Frame-Options, CSP, COOP, COEP, etc.)
- CSRF en POST críticos
- Rate limiting y login throttling
- Sesiones con TTL y lifetime
- Anti-replay de sesiones
- Firewall de payloads maliciosos
- Sanitización de logs y entrada
- Protección de rutas admin
- Validación de configuración de seguridad

**Ejecutar solo tests de seguridad**:
```bash
vendor/bin/phpunit tests/Security
```

---

### 6. Tests de IA y Microservicios

**Ubicación**: `tests/AI/`, `tests/Heatmap/`

**Propósito**: Validar integración con servicios de IA.

**Ejemplos**:
```
tests/
├── AI/
│   ├── OpenAIComicGeneratorTest.php
│   └── OpenAIComicGeneratorExtendedTest.php
├── Heatmap/
│   └── HttpHeatmapApiClientTest.php
└── Services/
    └── GithubClientTest.php
```

**Patrones usados**:
- Mocks de respuestas HTTP
- Fallbacks cuando no hay credenciales
- Validación de payloads enviados

---

### 6.5. Tests de Contrato y Schema OpenAPI

**Ubicación**: `tests/Contracts/`

**Propósito**: Validar que los contratos entre servicios sean consistentes y que el schema OpenAPI esté actualizado.

**Tests implementados**:
```
tests/Contracts/
├── OpenAiServiceContractTest.php     # Contrato con OpenAI service
├── RagServiceContractTest.php        # Contrato con RAG service
└── OpenApiSchemaValidationTest.php   # Validación del schema OpenAPI
```

#### OpenAPI Schema Validation

Valida que `docs/api/openapi.yaml` contenga:
- Endpoints requeridos (`/albums`, `/heroes`, `/rag/heroes`, `/comics/generate`)
- Schemas de componentes (`AlbumSummary`, `HeroSummary`, `CreateAlbumRequest`, etc.)
- Campos obligatorios en cada schema
- Estructura de request/response para cada endpoint

**Ejecutar tests de schema**:
```bash
vendor/bin/phpunit tests/Contracts/OpenApiSchemaValidationTest.php
```

#### **Contract Tests con Servicios Reales**

Los tests de contrato (`RagServiceContractTest`, `OpenAiServiceContractTest`) validan comunicación real con microservicios. Por defecto están **deshabilitados** para no requerir servicios activos en CI.

**Ejecutar tests de contrato (requiere servicios activos)**:
```bash
RUN_CONTRACT_TESTS=1 vendor/bin/phpunit tests/Contracts/RagServiceContractTest.php
```

---

### 7. Tests E2E con Playwright

**Ubicación**: `tests/e2e/`

**Propósito**: Validar flujos completos en navegador real.

**Tests implementados**:
```
tests/e2e/
├── home.spec.js
├── albums.spec.js
├── heroes.spec.js
├── comics.spec.js
├── movies.spec.js
├── health.spec.js
└── auth-render.spec.js
```

#### **Detalle de cada test**:

##### home.spec.js
```javascript
Test 1: "La home carga correctamente"
   - Verifica status HTTP 200
   - Valida título "Clean Marvel Album"
   - Comprueba frase icónica visible
   - Verifica imagen principal

Test 2: "El menú principal muestra enlaces clave"
   - Navega a /readme
   - Valida heading "Clean Architecture with Marvel"
   - Verifica enlaces del menú (Inicio, Crear Cómic, Movies, Secret Room)
```

##### **albums.spec.js**
```javascript
Test: "La página de álbumes se renderiza correctamente"
   - Navega a /albums
   - Valida headings principales
   - Verifica presencia de cards de álbumes
   - Comprueba botón "Crear Álbum"
```

##### **heroes.spec.js**
```javascript
Test: "La página de héroes lista contenido"
   - Navega a /heroes con parámetros (albumId, albumName)
   - Valida headings de galería
   - Verifica cards de héroes
   - Comprueba botón "Añadir Héroe"
```

##### **comics.spec.js**
```javascript
Test: "La página de cómics muestra el formulario de generación"
   - Navega a /comic
   - Valida heading "Crear tu cómic"
   - Verifica sección "Héroes disponibles"
   - Comprueba botones "Generar cómic" y "Comparar héroes"
```

##### **movies.spec.js**
```javascript
Test: "La página de películas carga correctamente"
   - Navega a /movies
   - Valida heading "Marvel Movies"
   - Verifica buscador de películas
   - Maneja estados: con películas / sin películas / sin API key
```

##### **health.spec.js**
```javascript
Test: "Landing (/) responde 200 y renderiza elementos base"
   - Smoke de / (status 200, title y main visible)

Test (condicional): "Si existe `/health`, responde 200 y devuelve JSON..."
   - Se marca como `skip` automáticamente si `/health` devuelve 404 en la app principal
   - Si el monolito añade `/health` en el futuro, el test se activará sin cambios
```

##### **auth-render.spec.js**
```javascript
Test: "La página de login renderiza el formulario"
   - Navega a /login
   - Verifica inputs (Correo/Contraseña) y botón submit

Test: "Enviar login vacío no redirige y marca inputs inválidos"
   - Envía formulario sin datos
   - Verifica que la URL sigue siendo /login y que hay inputs invalid
```

#### **Configuración**:

**Archivo**: `playwright.config.cjs`
```javascript
module.exports = defineConfig({
  testDir: 'tests/e2e',
  reporter: 'line',
  use: {
    baseURL: 'http://localhost:8080',
    browserName: 'chromium',
    headless: false,  // ← Navegador visible
    trace: 'on',
    video: 'on',
    screenshot: 'on',
  },
});
```

#### **Comandos disponibles**:

```bash
# Ejecutar todos los tests E2E con navegador visible
npm run test:e2e

# Modo UI interactivo (recomendado para desarrollo)
npm run test:e2e:ui

# Modo debug paso a paso
npm run test:e2e:debug

# Con navegador visible explícito
npm run test:e2e:headed
```

#### **Prerequisitos para E2E**:

1. **Servidor PHP corriendo**:
   ```bash
   php -S localhost:8080 -t public
   ```

2. **Instalación de Playwright**:
   ```bash
   npm install
   npx playwright install chromium
   ```

#### **Artefactos generados**:
- **Videos**: Grabación de cada test
- **Screenshots**: Capturas en caso de error
- **Traces**: Archivos de trazabilidad para debugging

---

### 8. Tests de Accesibilidad (Pa11y)

**Ubicación**: Pipeline CI (`.github/workflows/ci.yml`)

**Propósito**: Evaluar el cumplimiento de WCAG 2.1 AA mediante auditoría automatizada.

**Rutas testeadas**:
- `/` (Home)
- `/albums` (Álbumes)
- `/readme` (Documentación)
- `/sonar` (Calidad)
- `/sentry` (Errores)

**Comandos locales**:
```bash
# Instalar Pa11y
npm install -g pa11y

# Ejecutar en una URL
pa11y http://localhost:8080/

# Con reporte JSON
pa11y --reporter json http://localhost:8080/ > pa11y-report.json
```

**Validaciones**:
- Contraste de colores
- Etiquetas ARIA
- Estructura semántica HTML
- Navegación por teclado
- Textos alternativos

---

### 9. Tests de Performance (Lighthouse)

**Ubicación**: Pipeline CI

**Métricas analizadas**:
- **Performance**: FCP, LCP, TTI, TBT, CLS
- **Accesibilidad**: puntuación (0-100)
- **SEO**: Meta tags, títulos, estructura
- **Best Practices**: HTTPS, console errors, etc.

**Ejecutar localmente**:
```bash
# Lighthouse CLI
npm install -g lighthouse
lighthouse http://localhost:8080/ --output=html,json

# Ver reporte
open lighthouse-report.html
```

---

### 10. Tests de Smoke (Smoke)

**Ubicación**: `tests/Smoke/`

**Propósito**: Tests rápidos de "la app funciona básicamente".

**Ejemplo**:
```
tests/Smoke/
└── BasicSmokeTest.php
```

Valida que:
- La aplicación arranca
- Las rutas principales responden
- No hay errores fatales

---

## Comandos de testing

### Comandos Principales

```bash
# TODOS los tests PHPUnit
vendor/bin/phpunit --colors=always

	# Tests con cobertura
	composer test:coverage

# Análisis estático (PHPStan)
vendor/bin/phpstan analyse --memory-limit=512M

# Tests E2E
npm run test:e2e

# Validación completa (composer)
composer validate
```

### Tests por Categoría

```bash
# Solo tests de seguridad
vendor/bin/phpunit tests/Security

# Solo tests de dominio de Albums
vendor/bin/phpunit tests/Albums/Domain

# Solo tests de controladores
vendor/bin/phpunit tests/Controllers

# Solo tests de infraestructura
vendor/bin/phpunit tests/*/Infrastructure
```

### Scripts NPM Completos

```json
{
  "scripts": {
    "test:e2e": "playwright test",
    "test:e2e:ui": "playwright test --ui",
    "test:e2e:headed": "playwright test --headed",
    "test:e2e:debug": "playwright test --debug"
  }
}
```

---

## Organización de tests

### Estructura Completa

```
tests/
├── bootstrap.php                  # Setup PHPUnit
│
├── Activities/
├── AI/
├── Albums/
├── Application/
├── Config/
├── Controllers/
├── Dev/
├── Heatmap/
├── Heroes/
├── Monitoring/
├── Notifications/
├── Security/
├── Services/
├── Shared/
├── Smoke/
├── Support/
├── Unit/
│
├── e2e/
│   ├── home.spec.js
│   ├── albums.spec.js
│   ├── heroes.spec.js
│   ├── comics.spec.js
│   └── movies.spec.js
│
├── Doubles/                      # Test doubles (3 archivos)
└── Fakes/                        # Fakes para tests (3 archivos)
```

### Verificaciones incluidas

| Tipo de verificación | Herramienta | Evidencia |
|----------------------|-------------|----------|
| Tests backend | PHPUnit | `tests/` |
| Tests E2E | Playwright | `tests/e2e/` |
| Análisis estático | PHPStan | `phpstan.neon` |
| Accesibilidad | Pa11y (CI) | workflows de CI |
| Performance | Lighthouse (CI) | workflows de CI |

---

## Configuración de testing

### phpunit.xml.dist
Ver el archivo `phpunit.xml.dist`.

### phpstan.neon

Ver el archivo `phpstan.neon`.

### playwright.config.cjs

Ver el archivo `playwright.config.cjs`.

---

## Estrategia de testing por nivel

### Nivel 1: Proyecto Simple
```
- Tests unitarios de dominio
- PHPStan básico
- CI con GitHub Actions básico
```

### Nivel 2: Demo Técnica
```
- Todo lo anterior, más:
  - Tests de casos de uso
  - Tests de controladores
  - Cobertura con SonarCloud
```

### Nivel 3: Marvel (Completo)
```
- Todo lo anterior, más:
  - Tests de seguridad (suite `tests/Security/`)
  - Tests E2E con Playwright (`tests/e2e/`)
  - Pa11y + Lighthouse
  - Tests de microservicios
```

---

## Mejores prácticas

### 1. Aislamiento
- Cada test debe ser **independiente**
- No compartir estado entre tests
- Usar `setUp()` y `tearDown()` correctamente

### 2. Naming
```php
// Ejemplo recomendado
public function test_create_album_with_valid_name()

// Ejemplo a evitar
public function testAlbum()
```

### 3. AAA Pattern
```php
public function test_example()
{
    // Arrange (preparar)
    $album = new Album('Avengers');
    
    // Act (actuar)
    $result = $album->getName();
    
    // Assert (verificar)
    $this->assertSame('Avengers', $result);
}
```

### 4. Mocks vs Fakes
- **Mocks**: Para verificar interacciones
- **Fakes**: Para simular comportamiento real simplificado

```php
// Fake repository en tests/Fakes/
class InMemoryAlbumRepository implements AlbumRepository
{
    private array $albums = [];
    
    public function save(Album $album): void
    {
        $this->albums[$album->getId()] = $album;
    }
}
```

---

## Debugging tests

### Ver logs de tests
```bash
# Con más verbose
vendor/bin/phpunit --testdox

# Solo un test específico
vendor/bin/phpunit --filter test_create_album_with_valid_name

# Ver coverage HTML
composer test:coverage
open build/coverage/index.html
```

### Debug de E2E
```bash
# Modo debug interactivo
npm run test:e2e:debug

# Ver trace de un test fallido
npx playwright show-trace trace.zip
```

---

## Métricas de calidad

### Objetivos del Proyecto

| Métrica | Objetivo | Actual |
|---------|----------|--------|
| Cobertura | Umbral en CI | ver `COVERAGE.md` / `coverage.xml` |
| PHPStan | Configurado | ver `phpstan.neon` |
| Tests E2E | Flujos críticos | ver `tests/e2e/` |
| Pa11y | Auditoría WCAG | ver logs/artefactos de CI |
| Lighthouse | Auditoría | ver logs/artefactos de CI |

---

## Workflow recomendado

### Para nuevas features

1. **Escribir test que falla** (TDD)
   ```bash
   vendor/bin/phpunit --filter test_new_feature
   ```

2. **Implementar la feature**

3. **Ver test pasar**
   ```bash
   vendor/bin/phpunit --filter test_new_feature
   ```

4. **Ejecutar suite completa**
   ```bash
   vendor/bin/phpunit
   vendor/bin/phpstan analyse
   ```

5. Commit tras ejecutar las comprobaciones principales

---

## Recursos adicionales

- **PHPUnit**: https://phpunit.de/
- **PHPStan**: https://phpstan.org/
- **Playwright**: https://playwright.dev/
- **Pa11y**: https://pa11y.org/
- **Lighthouse**: https://developers.google.com/web/tools/lighthouse

---

## Cierre

Clean Marvel Album incluye una estrategia de testing que valida:

- **Lógica de negocio** (tests unitarios)  
- **Casos de uso** (tests de aplicación)  
- **Integraciones** (tests de infraestructura)  
- **Seguridad** (tests en `tests/Security/`)  
- **Experiencia de usuario** (E2E con Playwright)  
- **Accesibilidad** (Pa11y WCAG 2.1 AA)  
- **Performance** (Lighthouse CI)  
