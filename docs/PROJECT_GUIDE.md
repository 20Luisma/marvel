# Guía de Proyecto – Método "Marvel"

> **Propósito de este documento**: Esta guía es una receta reutilizable para iniciar nuevos proyectos PHP siguiendo las mismas prácticas y patrones usados en **Clean Marvel Album**. No importa si tu proyecto es sobre tareas, reservas, e-commerce o cursos: los principios aplican igual.

---

## 0. Visión y propósito del proyecto

Antes de escribir una sola línea de código, para. El paso más importante es definir con claridad **qué quieres construir y por qué**.

### Preguntas clave

1. **¿Qué problema resuelve este proyecto?**  
   Puede ser real (un cliente, una necesidad práctica) o educativo (practicar un patrón, experimentar con una tecnología).

2. **¿Qué tipo de proyecto es?**
   - MVP para validar una idea.
   - Demo técnica para mostrar habilidades.
   - Proyecto educativo para aprender algo nuevo.
   - Producto real en producción.

3. **¿Qué quiero practicar o demostrar?**  
   Esto guiará las decisiones arquitectónicas.

### Checklist de propósito

Antes de empezar, completa esta lista:

- [ ] **Una frase de propósito**: "_Este proyecto hace X para que Y puedan Z_".
- [ ] **Tipo de proyecto**: (MVP / Demo / Educativo / Producción).
- [ ] **Qué quiero practicar**: (Arquitectura Limpia / DDD / Seguridad / IA / CI/CD / Testing / Kubernetes / etc.).
- [ ] **Stack tecnológico base**: (PHP 8.2+, MySQL, JSON, etc.).
- [ ] **Complejidad esperada**: (Simple / Moderado / Avanzado).

### Ejemplo (Clean Marvel Album)

```
- Propósito: "Este proyecto gestiona un álbum de héroes Marvel para mostrar un ejemplo de Arquitectura Limpia en PHP."
- Tipo: demo técnica y educativo.
- Enfoque: Clean Architecture, seguridad por capas, CI con tests/análisis, integración con microservicios.
- Stack: PHP 8.2+, JSON en local, MySQL en hosting, microservicios PHP/Python.
- Complejidad: múltiples capas y servicios.
```

---

## 1. Empezar por el núcleo de dominio

La regla de oro del Método Marvel es **siempre empezar desde adentro hacia afuera**:

```
Dominio → Casos de Uso → Infraestructura → Presentación
```

**Nunca** empieces por la base de datos, por las vistas o por el framework. Empieza por responder: **¿qué hace mi aplicación?**

### Pasos concretos

1. **Identificar 2–3 entidades principales**  
   Pregunta: ¿cuáles son los conceptos fundamentales de mi dominio?

2. **Definir las reglas de negocio básicas**  
   ¿Qué validaciones siempre deben cumplirse? ¿Qué estados son válidos?

3. **Crear la estructura de carpetas para ese contexto**  
   Cada contexto tiene su propio `Domain/`, `Application/` e `Infrastructure/`.

4. **Empezar con persistencia simple**  
   Usa repositorios en memoria o archivos JSON. No te compliques con bases de datos aún.

5. **Escribir tests desde el primer día**  
   Si tu dominio tiene reglas, escribe tests que las validen.

### Ejemplo aplicado

**Si fuera un proyecto de gestión de tareas:**

| Concepto | Entidades | Reglas de negocio |
|----------|-----------|-------------------|
| Tareas | `Task`, `TaskList`, `User` | Una tarea no puede estar completada sin fecha de fin. Una lista no puede tener más de 50 tareas activas. |
| Prioridades | `Priority` (VO) | Solo valores: `low`, `medium`, `high`, `urgent`. |
| Eventos | `TaskCreated`, `TaskCompleted` | Se disparan al crear/completar tareas. |

**Estructura inicial:**

```
src/
└── Tasks/
    ├── Domain/
    │   ├── Task.php           # Entidad principal
    │   ├── TaskList.php       # Agregado
    │   ├── Priority.php       # Value Object
    │   ├── TaskRepository.php # Contrato (interfaz)
    │   └── Events/
    │       ├── TaskCreated.php
    │       └── TaskCompleted.php
    ├── Application/
    │   ├── CreateTask.php     # Caso de uso
    │   ├── CompleteTask.php
    │   └── GetTasksByList.php
    └── Infrastructure/
        └── JsonTaskRepository.php  # Implementación temporal
```

### Por qué empezar así

- Tu dominio **no depende de nada externo**.
- Puedes cambiar la base de datos sin tocar las reglas de negocio.
- Los tests de dominio son rápidos y fiables.
- Es más fácil razonar sobre el código.

---

## 2. Estructura de carpetas tipo Marvel

Una vez tienes el dominio, organiza todo el proyecto siguiendo esta estructura probada:

```
mi-proyecto/
├── public/                     # Front Controller + assets públicos
│   ├── index.php              # Punto de entrada (todo pasa por aquí)
│   ├── css/
│   ├── js/
│   └── images/
│
├── src/                        # Todo el código de la aplicación
│   ├── bootstrap.php          # Composition Root (wiring de dependencias)
│   │
│   ├── <Contexto1>/           # Ej: Tasks, Users, Products...
│   │   ├── Domain/
│   │   │   ├── Entities/
│   │   │   ├── ValueObjects/
│   │   │   ├── Events/
│   │   │   └── Repositories/  # Interfaces/contratos
│   │   ├── Application/       # Casos de uso
│   │   └── Infrastructure/    # Implementaciones (repos, adapters)
│   │
│   ├── <Contexto2>/           # Otro contexto acotado
│   │   └── ...
│   │
│   ├── Controllers/           # HTTP Controllers
│   ├── Security/              # Middleware, guards, servicios de seguridad
│   ├── Shared/                # Código compartido entre contextos
│   │   ├── Domain/            # Entidades base, interfaces comunes
│   │   ├── Infrastructure/    # Bus de eventos, HTTP client, etc.
│   │   └── Http/              # Router, Request, Response
│   ├── Config/                # Configuraciones y providers
│   └── AI/                    # Servicios de IA (si aplica)
│
├── views/                      # Vistas/Templates
│   ├── layouts/
│   ├── partials/
│   └── pages/
│
├── tests/                      # Tests unitarios e integración
│   ├── <Contexto1>/
│   ├── <Contexto2>/
│   ├── Security/
│   └── e2e/                   # Tests E2E (Playwright, etc.)
│
├── docs/                       # Documentación
│   ├── README.md
│   ├── ARCHITECTURE.md
│   ├── SECURITY.md
│   └── guides/
│
├── storage/                    # Datos y logs (no versionado)
│   ├── json/                  # Repos JSON
│   ├── logs/
│   └── cache/
│
├── config/                     # Archivos de configuración
│
├── bin/                        # Scripts CLI
│
├── .github/workflows/          # CI/CD
│
├── composer.json
├── phpunit.xml.dist
├── phpstan.neon
└── .env.example
```

### Lo que va en cada capa

| Capa | Contenido | Lo que NO debe tener |
|------|-----------|---------------------|
| **Domain** | Entidades, Value Objects, Eventos, Interfaces de repos, Excepciones de dominio | Referencias a HTTP, SQL, frameworks, I/O externo |
| **Application** | Casos de uso, DTOs de entrada/salida, orquestación | Acceso directo a BD, lógica de presentación |
| **Infrastructure** | Repos concretos, clientes HTTP, adaptadores externos, bus de eventos | Lógica de negocio, validaciones de dominio |
| **Controllers** | Recibir HTTP, validar input, llamar casos de uso, devolver respuesta | Lógica de negocio, acceso directo a BD |

### Configuración de composer.json

```json
{
    "autoload": {
        "psr-4": {
            "App\\": "src/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "Tests\\": "tests/"
        }
    }
}
```

Después de crear el archivo, ejecuta:

```bash
composer dump-autoload
```

### Tip: Empieza simple

No necesitas todas las carpetas desde el día uno. Empieza con:

```
src/
├── bootstrap.php
├── <TuContexto>/
│   ├── Domain/
│   ├── Application/
│   └── Infrastructure/
├── Controllers/
└── Shared/
```

Y ve añadiendo según necesites.

---

## 3. Seguridad por fases (modelo Marvel simplificado)

La seguridad no es "todo o nada". En Marvel usamos un enfoque por fases que te permite **empezar seguro y escalar según crece el proyecto**.

### La regla de oro

> **Fase 1 es obligatoria en CUALQUIER proyecto**. Las demás fases se añaden cuando el proyecto lo justifica.

### Fase 1 — Mínimo viable (SIEMPRE)

Esto lo implementas **desde el primer commit**:

| Control | Qué hace | Cómo implementarlo |
|---------|----------|-------------------|
| **Cabeceras HTTP básicas** | Previene ataques comunes | X-Content-Type-Options, X-Frame-Options, Referrer-Policy |
| **Cookies seguras** | Protege sesiones | HttpOnly + SameSite=Lax (+ Secure si HTTPS) |
| **Autenticación simple** | Controla acceso | Hash bcrypt para contraseñas, nunca en plano |
| **CSRF en POST críticos** | Previene ataques cross-site | Token único por sesión en formularios |
| **Escapado de salida** | Previene XSS | Función `e()` o similar en todas las vistas |

**Código ejemplo para cabeceras:**

```php
// src/Security/SecurityHeaders.php
class SecurityHeaders {
    public static function apply(): void {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: no-referrer-when-downgrade');
        header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    }
}
```

### Fase 2 — Cuando el proyecto crece

Añade estos controles cuando:
- Tengas usuarios reales.
- Manejes datos sensibles.
- Expongas APIs públicas.

| Control | Cuándo lo necesitas |
|---------|-------------------|
| **Rate limiting** | Cuando hay APIs o formularios públicos |
| **Firewall de payloads** | Si aceptas JSON de clientes |
| **Logging con trace_id** | Cuando necesitas debuggear en producción |
| **Sesiones con TTL/lifetime** | Cuando tienes login real |

### Fase 3+ — Hardening avanzado

Solo cuando el proyecto lo requiere (producción real, datos críticos):

- CSP estricta con nonces.
- Anti-replay de sesiones.
- MFA.
- HMAC entre servicios.
- Auditoría y rotación de logs.

### Resumen práctico

| Tipo de proyecto | Fases recomendadas |
|------------------|-------------------|
| Práctica personal | 1 |
| Demo técnica | 1 + Rate limiting básico |
| MVP con usuarios reales | 1 + 2 |
| Producción | 1 + 2 + 3 (según necesidad) |

**Marvel implementa hasta Fase 8** porque es un proyecto de demostración completo. Tu proyecto probablemente no necesite todo eso al principio.

---

## 4. IA y microservicios: cuándo añadirlos

En Marvel, los microservicios de IA (OpenAI para generar cómics, RAG para comparar héroes) **se añadieron después** de tener el dominio funcionando.

### La regla de los 3 pasos

```
1. Dominio y casos de uso con tests
2. Endpoints HTTP básicos funcionando
3. SOLO ENTONCES → IA y microservicios
```

Evitar añadir IA solo por novedad. Añadirla cuando resuelve un problema del dominio.

### Patrones para integrar IA

1. **Encapsula en servicios dedicados**

   ```
   src/AI/
   ├── OpenAIComicGenerator.php   # Genera cómics con OpenAI
   ├── RagClient.php              # Cliente para servicio RAG
   └── Contracts/
       └── ComicGeneratorInterface.php
   ```

2. **Usa contratos/interfaces**

   ```php
   interface ComicGeneratorInterface {
       public function generate(array $heroes): ComicResult;
   }
   ```

   Así puedes tener una implementación real y una fake para tests.

3. **No mezcles IA en entidades ni controladores**

   Ejemplo a evitar:
   ```php
   class HeroController {
       public function compare() {
           $openai = new OpenAI();  // Acoplamiento directo
           return $openai->call(...);
       }
   }
   ```

   Ejemplo recomendado:
   ```php
   class HeroController {
       public function compare(ComicGeneratorInterface $generator) {
           return $generator->generate($heroes);  // Inyección de dependencias
       }
   }
   ```

4. **Microservicios como adaptadores externos**

   Si usas microservicios externos (como Marvel con OpenAI y RAG), trátalos como cualquier otro adaptador de infraestructura:

   ```
   src/<Contexto>/
   └── Infrastructure/
       └── Http/
           ├── OpenAIAdapter.php     # Cliente HTTP al microservicio
           └── RagServiceAdapter.php
   ```

### Cuándo tiene sentido separar en microservicios

| Criterio | Monolito | Microservicio |
|----------|----------|---------------|
| Equipo pequeño (1-3 devs) | Sí | No |
| Tecnología diferente (Python, Node) | No | Sí |
| Escalar independientemente | No | Sí |
| Complejidad de despliegue aceptable | Sí | No |
| Proyecto educativo mostrando patrones | Depende | Sí |

Marvel usa microservicios separados para OpenAI y RAG porque:
- Permite mostrar el patrón.
- Usa diferentes configuraciones (embeddings en RAG).
- Facilita escalar IA independientemente.

---

## 5. CI/CD en niveles (inspirado en el pipeline de Marvel)

No necesitas el pipeline más complejo del mundo desde el día uno. Escala según la madurez del proyecto.

### Nivel 1 — Mínimo recomendable

**Para**: Cualquier proyecto, desde el primer día.

```yaml
# .github/workflows/ci.yml (simplificado)
name: CI
on: [push, pull_request]

jobs:
  build:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
          
      - name: Install dependencies
        run: composer install --no-interaction --prefer-dist
        
      - name: Run tests
        run: vendor/bin/phpunit
        
      - name: Static analysis
        run: vendor/bin/phpstan analyse
```

**Lo que cubre:**
- Ejecución de tests (PHPUnit).
- Análisis estático (PHPStan).
- Instalación de dependencias (Composer).

### Nivel 2 — Intermedio

**Para**: Proyectos con usuarios reales, demos profesionales.

Añade al Nivel 1:

```yaml
      - name: Run tests with coverage
        run: XDEBUG_MODE=coverage vendor/bin/phpunit --coverage-clover=coverage.xml
        
      - name: SonarCloud Scan
        uses: sonarsource/sonarcloud-github-action@v2
        env:
          SONAR_TOKEN: ${{ secrets.SONAR_TOKEN }}
```

**Lo que añade:**
- Cobertura en `coverage.xml` (consumida por SonarCloud).
- Histórico de métricas en SonarCloud (según configuración del proyecto).

### Nivel 3 — Avanzado (como Marvel)

**Para**: Proyectos de producción real, demos completas.

Jobs adicionales al Nivel 2:

| Job | Herramienta | Qué valida |
|-----|-------------|-----------|
| **Pa11y** | [Pa11y](https://pa11y.org/) | Accesibilidad WCAG 2.1 AA |
| **Lighthouse** | [Lighthouse CI](https://github.com/GoogleChrome/lighthouse-ci) | Performance, SEO, accesibilidad |
| **Playwright** | [Playwright](https://playwright.dev/) | Tests E2E reales |
| **Security audit** | `composer audit` | Vulnerabilidades en dependencias |
| **Deploy automático** | FTP/SSH/K8s | Condicionado a checks previos (según configuración) |

**Ejemplo de estructura de jobs:**

```yaml
jobs:
  build:        # Composer + PHPUnit + PHPStan
  tests:        # Placeholder para dependencias
  sonarcloud:   # Quality + Coverage → needs: [build, tests]
  pa11y:        # Accesibilidad → needs: [build, tests]
  lighthouse:   # Performance → needs: [build, tests]
  playwright:   # E2E → needs: [build, tests]
```

### Cómo elegir el nivel

| Escenario | Nivel recomendado |
|-----------|------------------|
| Práctica personal | 1 |
| Portfolio/Demo técnica | 2 |
| Entrevista técnica | 2 |
| MVP con usuarios reales | 2–3 |
| Producción | 3 |
| Educativo (como Marvel) | 3 (para mostrar el patrón completo) |

### Archivos de configuración necesarios

```
.github/
└── workflows/
    ├── ci.yml           # Pipeline principal
    ├── deploy.yml       # Deploy (opcional)
    └── security.yml     # Checks de seguridad periódicos

phpunit.xml.dist        # Config PHPUnit
phpstan.neon            # Config PHPStan
sonar-project.properties # Config SonarCloud (si usas Nivel 2+)
lighthouserc.json       # Config Lighthouse (si usas Nivel 3)
playwright.config.js    # Config Playwright (si usas Nivel 3)
```

---

## 6. Documentación base para cada nuevo proyecto

Todo proyecto debe tener documentación mínima. Aquí está lo esencial:

### README.md (obligatorio)

Estructura recomendada:

```markdown
# Nombre del Proyecto

[Badges de CI/CD, Coverage, etc.]

**Una línea describiendo qué hace el proyecto.**

## Objetivo
Por qué existe este proyecto y qué problema resuelve.

## Arquitectura
Resumen de capas y decisiones arquitectónicas.
(Enlace a `docs/architecture/ARCHITECTURE.md` para más detalle.)

## Estructura
Árbol de carpetas principales explicado.

## Persistencia
Qué base de datos usa y por qué.

## Seguridad
Qué fases de seguridad están activas.
(Enlace a `docs/security/security.md` para más detalle.)

## Puesta en marcha
Pasos para levantar el proyecto localmente.

## Tests y calidad
Cómo ejecutar tests, cobertura, análisis estático.

## Documentación adicional
Enlaces a otros docs relevantes.
```

### `docs/architecture/ARCHITECTURE.md` (recomendado)

Para proyectos con arquitectura no trivial:

- Diagrama de capas.
- Flujo de una petición típica.
- Decisiones arquitectónicas y por qué se tomaron.
- Microservicios y cómo se comunican (si aplica).

### `docs/security/security.md` (según contexto)

Si implementas más que Fase 1:

- Qué controles están activos.
- Qué fases están implementadas.
- Próximos pasos de hardening.

### Otros documentos opcionales

| Documento | Cuándo crearlo |
|-----------|---------------|
| `docs/api/API_REFERENCE.md` | Si tienes endpoints públicos |
| `docs/DOMAIN.md` (opcional) | Para explicar entidades y reglas de negocio complejas (no incluido en este repo) |
| `docs/deployment/deploy.md` | Si el despliegue tiene pasos especiales |
| `docs/guides/` | Para tutoriales específicos (autenticación, testing, etc.) |
| `AGENTS.md` | Si trabajas con asistentes de IA (define reglas para el agente) |

### Patrón de documentación del repositorio

El repositorio incluye un conjunto amplio de documentación. Para tu proyecto:

- **Proyecto simple** → Solo README.md bien hecho.
- **Proyecto medio** → README + ARCHITECTURE.md.
- **Proyecto avanzado** → Todo lo anterior + SECURITY + API_REFERENCE.

---

## 7. Ruta paso a paso (checklist reutilizable)

Usa esta lista cada vez que empieces un nuevo proyecto con el Método Marvel:

### Fase Cero: Definición

- [ ] Escribir la frase de propósito del proyecto.
- [ ] Decidir qué quiero practicar (arquitectura, seguridad, IA, CI/CD...).
- [ ] Elegir el stack tecnológico.
- [ ] Definir el nivel de complejidad esperado.

### Fase Uno: Dominio

- [ ] Identificar 2–3 entidades principales.
- [ ] Definir las reglas de negocio básicas.
- [ ] Crear estructura de carpetas: `src/<Contexto>/{Domain,Application,Infrastructure}`.
- [ ] Implementar entidades y Value Objects.
- [ ] Crear interfaz de repositorio (contrato).
- [ ] Implementar repositorio simple (JSON o memoria).
- [ ] Escribir tests de dominio.

### Fase Dos: Casos de uso

- [ ] Implementar 2–3 casos de uso principales.
- [ ] Escribir tests para cada caso de uso.
- [ ] Verificar que todo funciona sin HTTP ni vistas.

### Fase Tres: Presentación

- [ ] Crear `public/index.php` como Front Controller.
- [ ] Implementar router básico.
- [ ] Crear controlador(es) que llamen a los casos de uso.
- [ ] Crear vista(s) básica(s).
- [ ] Implementar Fase 1 de seguridad (cabeceras, cookies, CSRF).

### Fase Cuatro: Calidad

- [ ] Configurar PHPUnit y escribir tests mínimos.
- [ ] Configurar PHPStan.
- [ ] Crear workflow CI de Nivel 1.
- [ ] Verificar que el pipeline pasa en verde.

### Fase Cinco: Documentación

- [ ] Escribir README.md completo.
- [ ] Documentar arquitectura si es no trivial.
- [ ] Documentar seguridad implementada.

### Fase Seis: Evolución (según necesidad)

- [ ] ¿Necesito IA? → Añadir microservicios siguiendo el patrón Marvel.
- [ ] ¿Necesito más seguridad? → Subir de fase (rate limit, firewall, etc.).
- [ ] ¿Necesito mejor CI? → Añadir SonarCloud, Pa11y, Playwright.
- [ ] ¿Necesito deploy automático? → Configurar workflow de deploy.

---

## Resumen: El Método Marvel en una página

```
┌─────────────────────────────────────────────────────────────────┐
│                     MÉTODO MARVEL                               │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  1. PROPÓSITO PRIMERO                                          │
│     → Define qué construyes y por qué antes de codear.         │
│                                                                 │
│  2. DOMINIO → AFUERA                                            │
│     → Entidades → Casos de uso → Infra → HTTP                  │
│     → Nunca empieces por la BD o las vistas.                   │
│                                                                 │
│  3. ESTRUCTURA CLARA                                            │
│     → src/<Contexto>/{Domain,Application,Infrastructure}       │
│     → Separación estricta de responsabilidades.                │
│                                                                 │
│  4. SEGURIDAD POR FASES                                         │
│     → Fase 1 siempre. Las demás según necesidad.               │
│                                                                 │
│  5. IA/MICROSERVICIOS AL FINAL                                  │
│     → Solo cuando el dominio funciona.                         │
│     → Encapsulados como adaptadores de infraestructura.        │
│                                                                 │
│  6. CI/CD ESCALABLE                                             │
│     → Nivel 1 desde el día 1.                                  │
│     → Escala a Nivel 2-3 según madurez.                        │
│                                                                 │
│  7. DOCUMENTACIÓN VIVA                                          │
│     → README mínimo siempre.                                   │
│     → Arquitectura y Seguridad según complejidad.              │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

---

## 8. Observabilidad y trazabilidad (patrón Marvel)

Marvel implementa un sistema completo de observabilidad. Aquí están los componentes clave:

### Trace ID único por request

Cada petición tiene un identificador único que aparece en todos los logs:

```php
// src/Monitoring/TraceIdGenerator.php
class TraceIdGenerator {
    public function generate(): string {
        return bin2hex(random_bytes(16));
    }
}

// En bootstrap.php
$traceId = $traceGenerator->generate();
$_SERVER['X_TRACE_ID'] = $traceId;
header('X-Trace-Id: ' . $traceId);
```

### Logging de seguridad centralizado

```php
// src/Security/Logging/SecurityLogger.php
class SecurityLogger {
    public function log(string $event, array $context = []): void {
        $context['trace_id'] = $_SERVER['X_TRACE_ID'] ?? 'unknown';
        $context['timestamp'] = date('Y-m-d H:i:s');
        // Sanitiza datos sensibles antes de loggear
        file_put_contents($this->logPath, json_encode($context) . "\n", FILE_APPEND);
    }
}
```

**Eventos que loggea Marvel:**
- `login_failed`, `login_success`, `login_blocked`
- `csrf_failed`
- `rate_limit_exceeded`
- `payload_suspicious`
- `session_hijack_detected`
- `session_expired_ttl`, `session_expired_lifetime`

### Integración con Sentry (errores en producción)

```php
// En bootstrap.php
if ($sentryDsn) {
    $client = ClientBuilder::create([
        'dsn' => $sentryDsn,
        'environment' => $appEnvironment,
        'traces_sample_rate' => 0.2,
    ])->getClient();
    SentrySdk::setCurrentHub(new Hub($client));
}
```

### Métricas de uso de IA (TokenLogger)

```php
// src/Monitoring/TokenLogger.php - Registra uso de tokens OpenAI
// src/Monitoring/TokenMetricsService.php - Agrega y muestra métricas
```

### Cuándo añadir observabilidad

| Nivel proyecto | Observabilidad recomendada |
|---------------|---------------------------|
| Práctica | Ninguna o logs básicos |
| Demo/Portfolio | Trace ID + logs de seguridad |
| MVP con usuarios | Todo lo anterior + Sentry |
| Producción | Todo + métricas de negocio |

---

## 9. Persistencia con fallback resiliente

Marvel usa un patrón de **fallback automático**: intenta usar MySQL; si falla, cae a JSON sin romper la app.

### Cómo funciona

```php
// En bootstrap.php
$pdo = null;
try {
    $pdo = PdoConnectionFactory::create($dbDsn, $dbUser, $dbPass);
} catch (\Exception $e) {
    // No rompe: pdo queda null y se usan repos JSON
}

$albumRepository = ($albumDriver === 'db' && $pdo !== null)
    ? new DbAlbumRepository($pdo)
    : new FileAlbumRepository($storagePath . '/albums.json');
```

### Variables de entorno para persistencia

```env
# .env
DB_DSN=mysql:host=localhost;dbname=marvel;charset=utf8mb4
DB_USER=root
DB_PASSWORD=secret

# O déjalas vacías para usar JSON automáticamente
```

### Estructura de repositorios dual

```
src/<Contexto>/Infrastructure/Persistence/
├── FileHeroRepository.php   # Implementación JSON
└── DbHeroRepository.php     # Implementación MySQL/PDO
```

Ambos implementan la misma interfaz (`HeroRepositoryInterface`), lo que permite intercambiarlos sin cambiar el código de dominio.

---

## 10. EventBus y eventos de dominio

Marvel usa un EventBus en memoria para desacoplar efectos secundarios del flujo principal.

### Estructura

```
src/Shared/
├── Domain/
│   ├── Event.php           # Interfaz base de eventos
│   └── EventHandler.php    # Interfaz de handlers
└── Infrastructure/
    └── Bus/
        └── InMemoryEventBus.php
```

### Cómo se usa

```php
// 1. Definir evento de dominio
class HeroCreated implements Event {
    public function __construct(public readonly Hero $hero) {}
}

// 2. Crear handler
class HeroCreatedNotificationHandler implements EventHandler {
    public function handle(Event $event): void {
        // Enviar notificación, loggear, etc.
    }
}

// 3. Registrar en bootstrap
$eventBus = new InMemoryEventBus();
$eventBus->subscribe(HeroCreated::class, new HeroCreatedNotificationHandler(...));

// 4. Publicar desde caso de uso
$eventBus->publish(new HeroCreated($hero));
```

### Cuándo usar eventos

| Situación | Usar evento |
|-----------|-------------|
| Notificar a otros sistemas | Sí |
| Loggear actividad | Sí |
| Actualizar cachés | Sí |
| Validar reglas de negocio | No (debe estar en dominio) |
| Modificar la entidad principal | No (debe ser síncrono) |

---

## 11. Configuración multi-entorno (patrón ServiceUrlProvider)

Marvel resuelve URLs de servicios según el entorno (`local` vs `hosting`) sin hardcodear.

### Archivo de configuración

```php
// config/services.php
return [
    'default_environment' => 'local',
    'environments' => [
        'local' => [
            'app' => ['base_url' => 'http://localhost:8080'],
            'openai' => ['chat_url' => 'http://localhost:8081/v1/chat'],
            'rag' => ['heroes_url' => 'http://localhost:8082/rag/heroes'],
        ],
        'hosting' => [
            'app' => ['base_url' => 'https://miapp.com'],
            'openai' => ['chat_url' => 'https://openai.miapp.com/v1/chat'],
            'rag' => ['heroes_url' => 'https://rag.miapp.com/rag/heroes'],
        ],
    ],
];
```

### Cómo usarlo

```php
// src/Config/ServiceUrlProvider.php
class ServiceUrlProvider {
    public function getOpenAIChatUrl(): string {
        return $this->config['environments'][$this->env]['openai']['chat_url'];
    }
}
```

### Variables de entorno completas (.env.example)

```env
## APP
APP_ENV=local                    # local | hosting | test
APP_DEBUG=1
APP_URL=http://localhost:8080

## DB (opcional; por defecto JSON)
DB_DSN=
DB_USER=
DB_PASSWORD=

## INTERNAL KEYS / HMAC
INTERNAL_API_KEY=change-me-strong-random

## MICROSERVICIOS IA
OPENAI_SERVICE_URL=http://localhost:8081/v1/chat
RAG_SERVICE_URL=http://localhost:8082/rag/heroes

## EXTERNAL APIS
OPENAI_API_KEY=your-openai-key
ELEVENLABS_API_KEY=
WAVE_API_KEY=
PSI_API_KEY=
GITHUB_API_KEY=

## OBSERVABILIDAD
SENTRY_DSN=

## HEATMAP
HEATMAP_API_BASE_URL=http://localhost:5000
HEATMAP_API_TOKEN=

## DEBUG (solo en prod)
DEBUG_API_FIREWALL=0
DEBUG_RAG_PROXY=0
DEBUG_RAW_BODY=0
```

---

## 12. Scripts CLI útiles (carpeta bin/)

Marvel incluye scripts CLI reutilizables:

| Script | Propósito |
|--------|-----------|
| `bin/migrar-json-a-db.php` | Migra datos de JSON a MySQL |
| `bin/security-check.sh` | Ejecuta `composer audit` + lint de sintaxis |
| `bin/generate-bundle-size.php` | Genera métricas de tamaño de assets |
| `bin/pa11y-all.sh` | Ejecuta auditoría de accesibilidad |
| `bin/verify-token-metrics.php` | Verifica métricas de tokens IA |

### Ejemplo de script de seguridad

```bash
#!/bin/bash
# bin/security-check.sh

echo "=== Auditoría de dependencias ==="
composer audit --no-interaction

echo "=== Lint de sintaxis PHP (src/ y tests/) ==="
find src tests -name '*.php' -print0 | xargs -0 -r -n1 -P4 php -l
```

---

## 13. Testing completo (niveles Marvel)

El repositorio incluye tests unitarios/integración, tests específicos de seguridad y tests E2E con Playwright.

### Estructura de tests

```
tests/
├── <Contexto>/
│   ├── Domain/           # Tests de entidades y VOs
│   ├── Application/      # Tests de casos de uso
│   └── Infrastructure/   # Tests de repos
├── Controllers/          # Tests de controladores
├── Security/             # Tests de seguridad
├── Services/             # Tests de servicios
├── Shared/               # Tests de código compartido
├── Fakes/                # Repositorios fake para tests
├── Doubles/              # Test doubles
└── e2e/                  # Tests E2E (Playwright)
    ├── home.spec.js
    ├── albums.spec.js        # Tests de álbumes
    ├── heroes.spec.js        # Tests de héroes
    ├── comics.spec.js        # Tests de generación de cómics
    └── movies.spec.js        # Tests de películas
```

### Tipos de tests en Marvel

| Tipo | Herramienta | Qué valida |
|------|-------------|------------|
| Unitarios y dominio | PHPUnit | Entidades, VOs, eventos |
| Casos de uso | PHPUnit | Application layer |
| Seguridad | PHPUnit | CSRF, rate limit, sesión, firewall |
| Controladores | PHPUnit | Capa HTTP |
| Infraestructura | PHPUnit | Repos, HTTP clients, bus |
| E2E | Playwright | Flujos críticos de usuario |
| Accesibilidad | Pa11y (CI) | Auditoría WCAG 2.1 AA |
| Performance | Lighthouse (CI) | Core Web Vitals, SEO |

### Configuración PHPUnit

Ver el archivo `phpunit.xml.dist`.

### Configuración PHPStan

```neon
# phpstan.neon
parameters:
    level: 7
    paths:
        - src
    excludePaths:
        - src/Dev
```

### Tests E2E con Playwright

**Configuración** (`playwright.config.cjs`):

```javascript
const { defineConfig } = require('@playwright/test');

module.exports = defineConfig({
  testDir: 'tests/e2e',
  reporter: 'line',
  use: {
    baseURL: 'http://localhost:8080',
    browserName: 'chromium',
    headless: false,  // Navegador visible
    trace: 'on',
    video: 'on',
    screenshot: 'on',
  },
});
```

**Scripts NPM** (`package.json`):

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

**Comandos**:

```bash
# Prerequisito: Servidor PHP corriendo
php -S localhost:8080 -t public

# Ejecutar todos los tests E2E (navegador visible)
npm run test:e2e

# Modo UI interactivo (recomendado para desarrollo)
npm run test:e2e:ui

# Modo debug paso a paso
npm run test:e2e:debug
```

**Tests E2E cubiertos**:

1. **home.spec.js**:
   - Carga de home con título y elementos clave
   - Navegación principal y enlaces del menú

2. **albums.spec.js**:
   - Renderizado de página de álbumes
   - Presencia de cards y botón crear

3. **heroes.spec.js**:
   - Galería de héroes con parámetros
   - Cards de héroes y botón añadir

4. **comics.spec.js**:
   - Formulario de generación de cómics
   - Botones de generar y comparar

5. **movies.spec.js**:
   - Carga de películas
   - Manejo de estados (con/sin datos/sin API key)

**Artefactos generados**:
- Videos de cada test
- Screenshots en caso de error
- Traces para debugging detallado

### Comandos de testing por categoría

```bash
# PHPUnit completo
vendor/bin/phpunit --colors=always

# Solo tests de seguridad
vendor/bin/phpunit tests/Security

# Solo tests de dominio de Albums
vendor/bin/phpunit tests/Albums/Domain

# Solo tests de controladores
vendor/bin/phpunit tests/Controllers

# Cobertura (ver `COVERAGE.md` y `coverage.xml`)
composer test:coverage

# Análisis estático (PHPStan; config en `phpstan.neon`)
vendor/bin/phpstan analyse --memory-limit=512M

# E2E completo
npm run test:e2e

# Auditoría de dependencias
composer audit

# Validación de composer.json
composer validate
```

### Workflow recomendado en desarrollo

1. **Escribir test que falla** (TDD):
   ```bash
   vendor/bin/phpunit --filter test_nueva_feature
   ```

2. **Implementar la feature**

3. **Ver test pasar**:
   ```bash
   vendor/bin/phpunit --filter test_nueva_feature
   ```

4. **Ejecutar suite completa**:
   ```bash
   vendor/bin/phpunit
   vendor/bin/phpstan analyse
   npm run test:e2e
   ```

5. Commit tras ejecutar las comprobaciones principales

**Documentación completa de testing**: Ver `docs/guides/testing-complete.md` para más detalle de cada tipo de test, patrones AAA, mocks vs fakes, debugging y mejores prácticas.

---

## 14. Kubernetes (despliegue opcional)

Marvel incluye manifiestos K8s para orquestación avanzada.

### Estructura de manifiestos

```
k8s/
├── clean-marvel-deployment.yaml    # App principal
├── clean-marvel-service.yaml       # Service ClusterIP
├── openai-service-deployment.yaml  # Microservicio OpenAI
├── openai-service-service.yaml
├── rag-service-deployment.yaml     # Microservicio RAG
├── rag-service-service.yaml
└── ingress.yaml                    # Ingress NGINX
```

### Cuándo usar K8s

| Escenario | ¿K8s? |
|-----------|-------|
| Proyecto personal/educativo | No (preferible hosting simple) |
| Demo técnica | Depende (si se quiere demostrar K8s) |
| MVP | No, salvo requisitos específicos |
| Producción con necesidad de orquestación | Sí |
| Múltiples microservicios | Sí |

### Alternativas más simples

- **Desarrollo local**: `php -S localhost:8080 -t public`
- **Hosting compartido**: FTP + `.htaccess`
- **VPS simple**: Docker Compose
- **PaaS**: Heroku, Railway, Render

---

## 15. Integraciones externas (patrón Marvel)

Marvel integra varios servicios externos. Aquí está el patrón:

### APIs integradas

| Servicio | Propósito | Archivo |
|----------|-----------|---------|
| OpenAI | Generar cómics | `src/AI/OpenAIComicGenerator.php` |
| ElevenLabs | TTS (texto a voz) | `public/api/tts-elevenlabs.php` |
| WAVE | Accesibilidad | `public/api/accessibility-marvel.php` |
| GitHub | PRs y actividad | `src/Services/GithubClient.php` |
| Heatmap | Analytics de clics | `src/Heatmap/Infrastructure/HttpHeatmapApiClient.php` |

### Patrón de integración

```php
// 1. Interfaz en dominio/aplicación
interface HeatmapClient {
    public function trackClick(ClickData $data): void;
}

// 2. Implementación HTTP en infraestructura
class HttpHeatmapApiClient implements HeatmapClient {
    public function __construct(
        private string $baseUrl,
        private ?string $token
    ) {}
    
    public function trackClick(ClickData $data): void {
        // cURL al microservicio
    }
}

// 3. Inyectar en bootstrap
$container['heatmapApiClient'] = new HttpHeatmapApiClient($url, $token);
```

---

## Anexo A: Checklist completo estilo Marvel

### Proyecto mínimo (Nivel 1)

- [ ] Estructura `src/<Contexto>/{Domain,Application,Infrastructure}`
- [ ] `composer.json` con PSR-4 (`"App\\": "src/"`)
- [ ] Front Controller en `public/index.php`
- [ ] Router básico
- [ ] Al menos 1 caso de uso funcionando
- [ ] Repositorio JSON simple
- [ ] Fase 1 de seguridad (cabeceras, cookies)
- [ ] PHPUnit configurado
- [ ] PHPStan nivel 5+
- [ ] CI básico (PHPUnit + PHPStan)
- [ ] README.md completo

### Proyecto intermedio (Nivel 2)

Todo lo anterior, más:
- [ ] Múltiples contextos acotados
- [ ] EventBus con handlers
- [ ] Fallback de persistencia (JSON/DB)
- [ ] Fase 2 de seguridad (rate limit, logging)
- [ ] Trace ID por request
- [ ] SonarCloud integrado
- [ ] Tests de seguridad
- [ ] `docs/architecture/ARCHITECTURE.md`
- [ ] `docs/security/security.md`

### Proyecto avanzado (Nivel Marvel)

Todo lo anterior, más:
- [ ] Microservicios separados (si aplica IA)
- [ ] ServiceUrlProvider multi-entorno
- [ ] Fases 3+ de seguridad
- [ ] Sentry para errores
- [ ] Pa11y (accesibilidad)
- [ ] Lighthouse (performance)
- [ ] Playwright (E2E)
- [ ] Scripts CLI (`bin/`)
- [ ] Deploy automático (FTP/K8s)
- [ ] TokenLogger para IA
- [ ] Múltiples integraciones externas

---

## 16. ADRs (Architecture Decision Records)

Marvel documenta las decisiones arquitectónicas importantes usando **ADRs** (Architecture Decision Records).

### Qué es un ADR

Un ADR es un documento corto que explica **por qué** se tomó una decisión técnica importante, no solo qué se hizo.

### Estructura de un ADR

```markdown
# ADR-XXX – Título de la decisión

## Estado
Accepted | Superseded | Deprecated

## Contexto
¿Cuál era el problema o necesidad que había que resolver?

## Decisión
¿Qué decidimos hacer?

## Justificación
¿Por qué esta opción y no otra?

## Consecuencias
### Positivas
- Beneficio 1
- Beneficio 2

### Negativas
- Trade-off 1
- Trade-off 2

## Opciones descartadas
- Opción A (razón por la que no)
- Opción B (razón por la que no)

## Supersede
ADR-YYY (si reemplaza otro)
```

### ADRs de Marvel (referencia)

| ADR | Decisión |
|-----|----------|
| `ADR-001-clean-architecture.md` | Elección de Clean Architecture en PHP |
| `ADR-002-persistencia.md` | Estrategia JSON/MySQL con fallback |
| `ADR-003-sonarcloud.md` | Integración de SonarCloud para calidad |
| `ADR-004-sentry.md` | Uso de Sentry para errores en producción |
| `ADR-005-microservicios-openai-rag.md` | Separación de IA en microservicios |
| `ADR-006-seguridad-fase2.md` | Implementación de seguridad Fase 2 |

### Cuándo crear un ADR

Crea un ADR cuando:
- Elijas una tecnología importante (framework, BD, servicio externo).
- Cambies la arquitectura significativamente.
- Tomes una decisión con trade-offs claros.
- Descartes alternativas que otros podrían considerar.

### Dónde guardarlos

```
docs/
└── architecture/
    ├── ADR-001-clean-architecture.md
    ├── ADR-002-persistencia.md
    └── ...
```

---

## 17. Configuración de VS Code (automatización local)

Marvel incluye configuraciones de VS Code que automatizan tareas repetitivas.

### Estructura de .vscode/

```
.vscode/
├── settings.json          # Configuración del editor
├── tasks.json             # Tareas automatizadas
├── extensions.json        # Extensiones recomendadas
└── snippets.code-snippets # Fragmentos de código
```

### settings.json (ejemplo Marvel)

```json
{
    "editor.formatOnSave": true,
    "editor.fontFamily": "JetBrains Mono, Menlo, Monaco, monospace",
    "git.enableSmartCommit": true,
    "files.exclude": {
        "**/node_modules": true,
        "**/vendor": true
    },
    "intelephense.environment.phpVersion": "8.2",
    "php.validate.executablePath": "/usr/bin/php",
    "editor.defaultFormatter": "bmewburn.vscode-intelephense-client"
}
```

### tasks.json (tareas automatizadas)

Marvel define tareas para ejecutar con `Ctrl+Shift+P` → "Tasks: Run Task":

| Categoría | Tareas |
|-----------|--------|
| **Servidor** | Iniciar servidor PHP (8080) |
| **QA** | Ejecutar tests PHPUnit, analizar código (PHPStan), QA completo |
| **Seguridad** | Ejecutar checks de seguridad |
| **Git** | `Git \| crear rama`, `Git \| commit rápido`, `Git \| push seguro`, `Git \| crear versión` |
| **Microservicios** | Ejecutar OpenAI Service (8081), ejecutar RAG Service (8082), ejecutar todos los servicios |
| **Calidad** | Ejecutar SonarScanner, analizar accesibilidad (Pa11y), medir bundle size |

### Ejemplo de tarea

```json
{
    "label": "QA completo (tests + phpstan + composer)",
    "dependsOn": [
        "Ejecutar Tests PHPUnit",
        "Analizar código (PHPStan)",
        "Validar composer"
    ],
    "dependsOrder": "sequence"
}
```

### extensions.json (extensiones recomendadas)

```json
{
    "recommendations": [
        "bmewburn.vscode-intelephense-client",
        "xdebug.php-debug",
        "phpstan.phpstan",
        "EditorConfig.EditorConfig"
    ]
}
```

### Cuándo configurar VS Code

- **Siempre**: `settings.json` básico para el proyecto.
- **Proyectos medios+**: Tareas para QA y servidor.
- **Equipos**: `extensions.json` para estandarizar herramientas.

---

## 18. AGENTS.md (contexto para asistentes de IA)

Marvel incluye un archivo `AGENTS.md` en la raíz que define reglas y contexto para asistentes de IA (Copilot, Claude, Codex, etc.).

### Por qué tener un AGENTS.md

Cuando trabajas con asistentes de IA:
- Necesitan **contexto** sobre la arquitectura.
- Deben saber qué **reglas** seguir.
- Deben conocer los **comandos** disponibles.
- Deben entender los **roles** y responsabilidades.

### Estructura del AGENTS.md de Marvel

```markdown
# AGENTS — Nombre del Proyecto

## Contexto y propósito
- Descripción breve del proyecto.
- Arquitectura general.
- Microservicios y cómo se comunican.

## Capas Clean Architecture
| Capa | Directorios | Responsabilidad |
| --- | --- | --- |
| Presentación | `public/`, `src/Controllers` | HTTP |
| Aplicación | `src/*/Application` | Casos de uso |
| Dominio | `src/*/Domain` | Entidades, reglas |
| Infraestructura | `src/*/Infrastructure` | Adaptadores |

## Roles de los agentes
- **Refactorizador**: mejoras sin romper contratos.
- **Generador de tests**: tests en `tests/`.
- **Documentador**: README, docs, ADRs.
- **Gestor de microservicios**: sincroniza servicios.
- **Auditor de calidad**: PHPUnit + PHPStan.

## Reglas y buenas prácticas
- Respetar inversión de dependencias.
- No lógica HTTP en dominio.
- Handlers idempotentes.
- No acceder a `storage/` desde presentación.

## Safe Mode (dry-run)
- Cómo ejecutar en modo seguro sin escribir cambios.

## Comandos útiles
| Escenario | Comando |
| --- | --- |
| Instalar deps | `composer install` |
| Servidor | `composer serve` |
| Tests | `vendor/bin/phpunit` |
| PHPStan | `vendor/bin/phpstan analyse` |
```

### Cuándo crear un AGENTS.md

| Situación | ¿AGENTS.md? |
|-----------|-------------|
| Proyecto personal sin IA | No |
| Usas Copilot/Claude ocasionalmente | Sí (básico) |
| IA es parte del flujo de desarrollo | Sí (completo) |
| Proyecto educativo | Sí (puede servir como documentación) |

### Dónde colocar el archivo

```
mi-proyecto/
├── AGENTS.md          # Raíz del proyecto
├── README.md
└── ...
```

---

## 19. Text-to-Speech con ElevenLabs

Marvel integra **ElevenLabs** para convertir los resultados de IA (cómics generados, comparaciones RAG) en audio narrado.

### Endpoint TTS

```
public/api/tts-elevenlabs.php
```

### Cómo funciona

```php
// 1. El frontend envía texto al proxy TTS
POST /api/tts-elevenlabs.php
Content-Type: application/json

{ "text": "La historia épica de Iron Man..." }

// 2. El proxy añade las credenciales de .env y llama a ElevenLabs
$response = curl_request('https://api.elevenlabs.io/v1/text-to-speech/{voiceId}', [
    'text' => $text,
    'model_id' => $modelId,
    'voice_settings' => [
        'stability' => $stability,
        'similarity_boost' => $similarity
    ]
]);

// 3. Devuelve el audio al frontend
header('Content-Type: audio/mpeg');
echo $response;
```

### Variables de entorno

```env
ELEVENLABS_API_KEY=your-elevenlabs-key
ELEVENLABS_VOICE_ID=EXAVITQu4vr4xnSDxMaL    # Voz por defecto (Charlie)
ELEVENLABS_MODEL_ID=eleven_multilingual_v2  # Modelo multilingüe
ELEVENLABS_VOICE_STABILITY=0.5              # Estabilidad de la voz
ELEVENLABS_VOICE_SIMILARITY=0.75            # Similitud con la voz original
TTS_INTERNAL_TOKEN=                         # Token interno opcional
```

### Integración en el frontend

```javascript
// public/assets/js/comic.js
async function narrateText(text) {
    const response = await fetch('/api/tts-elevenlabs.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ text })
    });
    
    const audioBlob = await response.blob();
    const audioUrl = URL.createObjectURL(audioBlob);
    
    const audio = document.getElementById('narrator-audio');
    audio.src = audioUrl;
    audio.play();
}
```

### Cuándo usar TTS

| Caso de uso | Recomendación |
|-------------|---------------|
| Resultados de IA largos | Recomendable |
| Accesibilidad | Recomendable |
| Contenido corto (< 50 palabras) | Generalmente no necesario |
| Alta frecuencia de uso | Evaluar costes |

---

## 20. Docker y contenedores para microservicios

Marvel usa Docker para empaquetar y desplegar los microservicios de IA de forma aislada.

### Estructura de un microservicio dockerizado

```
openai-service/
├── Dockerfile              # Imagen del contenedor
├── .dockerignore           # Archivos a excluir
├── .env                    # Variables locales
├── .env.example            # Plantilla de variables
├── composer.json           # Dependencias PHP
├── public/
│   └── index.php           # Front Controller del servicio
├── src/                    # Código del servicio
└── tests/                  # Tests del servicio
```

### Dockerfile típico (PHP 8.2)

```dockerfile
# openai-service/Dockerfile
FROM php:8.2-cli

WORKDIR /app

# Instalar extensiones necesarias
RUN apt-get update && apt-get install -y \
    unzip \
    curl \
    && docker-php-ext-install opcache

# Instalar Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copiar código
COPY . .

# Instalar dependencias
RUN composer install --no-dev --optimize-autoloader

# Exponer puerto
EXPOSE 8081

# Comando de inicio
CMD ["php", "-S", "0.0.0.0:8081", "-t", "public"]
```

### .dockerignore

```
# openai-service/.dockerignore
.git
.gitignore
vendor/
tests/
.env
*.md
```

### Docker Compose (desarrollo local)

```yaml
# docker-compose.yml (raíz del proyecto)
version: '3.8'

services:
  app:
    build: .
    ports:
      - "8080:8080"
    volumes:
      - ./storage:/app/storage
    env_file:
      - .env
    depends_on:
      - openai-service
      - rag-service

  openai-service:
    build: ./openai-service
    ports:
      - "8081:8081"
    env_file:
      - ./openai-service/.env

  rag-service:
    build: ./rag-service
    ports:
      - "8082:8082"
    volumes:
      - ./rag-service/storage:/app/storage
    env_file:
      - ./rag-service/.env
```

### Comandos útiles

| Comando | Propósito |
|---------|-----------|
| `docker-compose up -d` | Levantar todos los servicios |
| `docker-compose logs -f` | Ver logs en tiempo real |
| `docker-compose down` | Detener servicios |
| `docker build -t openai-service ./openai-service` | Construir imagen individual |
| `docker run -p 8081:8081 openai-service` | Ejecutar contenedor individual |

### Cuándo usar Docker

| Escenario | ¿Docker? |
|-----------|----------|
| Desarrollo local simple | No (usar `php -S`) |
| Múltiples microservicios | Sí (docker-compose) |
| Equipos grandes | Sí (entorno consistente) |
| Despliegue en Kubernetes | Depende del contexto |
| Hosting compartido | No aplica |

---

## 21. Automatización con n8n

Marvel incluye workflows de **n8n** para automatizar tareas recurrentes.

### Qué es n8n

n8n es una plataforma de automatización de workflows (similar a Zapier) que puede auto-hospedarse.

### Workflows incluidos

```
n8n/
└── Daily Marvel YouTube Video Fetcher and Backend Sync.json
```

### Ejemplo: Sincronización diaria de videos Marvel

Este workflow:
1. Se ejecuta diariamente (cron).
2. Consulta la API de YouTube por nuevos videos de Marvel.
3. Envía el video más reciente al backend Marvel.
4. Actualiza `public/api/ultimo-video-marvel.json`.

### Cómo importar un workflow

1. Instala n8n: `npx n8n` o via Docker.
2. Abre la interfaz web (por defecto `http://localhost:5678`).
3. Ve a **Workflows → Import from File**.
4. Selecciona el archivo JSON de `n8n/`.
5. Configura las credenciales necesarias (API keys).
6. Activa el workflow.

### Variables necesarias en n8n

| Credencial | Para qué |
|------------|----------|
| `GOOGLE_YT_API_KEY` | Consultar videos de YouTube |
| `MARVEL_UPDATE_TOKEN` | Autenticar llamadas al backend |

### Cuándo usar n8n

| Tarea | Mejor opción |
|-------|--------------|
| Cron jobs simples | `crontab` o GitHub Actions |
| Integraciones complejas (múltiples APIs) | n8n |
| Workflows visuales para no-developers | n8n |
| Tareas críticas en producción | Evaluar disponibilidad |

---

## 22. Las 10 Fases de Seguridad Marvel (detalle)

Marvel implementa un modelo de seguridad progresivo en **10 fases**. Aquí está el resumen de cada una:

### Resumen de fases

| Fase | Tema | Estado Marvel | Controles clave |
|------|------|---------------|-----------------|
| 1 | Hardening HTTP básico | Implementado (Máster) | Cabeceras seguras, cookies HttpOnly/SameSite |
| 2 | Autenticación y sesiones | Implementado (Máster) | bcrypt, TTL/lifetime, sellado IP/UA |
| 3 | Autorización y acceso | Implementado (Máster) | AuthMiddleware, AuthGuards, rol admin |
| 4 | CSRF y XSS | Implementado (Máster) | Tokens CSRF, escapado `e()`, sanitización |
| 5 | APIs y microservicios | Implementado (Máster) | ApiFirewall, rate-limit, proxy seguro |
| 6 | Monitorización y logs | Implementado (Máster) | SecurityLogger, trace_id, Sentry |
| 7 | Anti-replay avanzado | Modo observación | Token de sesión, logging de intentos |
| 8 | Endurecimiento cabeceras | Implementado | CSP, CORP, COOP, tests automáticos |
| 9 | Gestión de secretos | En progreso (documentado) | .env por entorno, inventario |
| 10 | Pruebas automáticas seguridad | Trabajo futuro (documentado) | Tests de cabeceras, SAST, audits |

### Fase 1 — Hardening HTTP básico

```php
// Cabeceras aplicadas en bootstrap.php
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: no-referrer-when-downgrade');
header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
header('X-XSS-Protection: 0');  // Desactivado (obsoleto, CSP lo reemplaza)
header('Cross-Origin-Resource-Policy: same-origin');
header('Cross-Origin-Opener-Policy: same-origin');
```

### Fase 2 — Autenticación y sesiones

```php
// AuthService.php - Controles de sesión
- Hash bcrypt $2y$12 para contraseñas
- Regeneración de ID en login
- TTL de inactividad: 30 minutos
- Lifetime máximo: 8 horas
- Sellado IP/UA (detecta hijacking)
- Anti-replay en modo observación
```

### Fase 3 — Autorización

```php
// Rutas protegidas por AuthMiddleware
/seccion, /secret*, /admin*, /agentia
- No logueado → 302 a /login
- Sin rol admin → 403 Forbidden
```

### Fase 4 — CSRF y XSS

```php
// En vistas: campo CSRF oculto
<?= csrf_field() ?>

// En controladores: validación
CsrfMiddleware::validate($request);

// Escapado obligatorio
<h1><?= e($titulo) ?></h1>
```

### Fase 5 — APIs y microservicios

```php
// ApiFirewall.php - Controles
- Tamaño máximo de payload
- Patrones de inyección bloqueados
- Rate-limit por IP/ruta
- Logging de payloads sospechosos
```

### Fase 6 — Monitorización

```php
// SecurityLogger.php - Eventos loggeados
- login_failed, login_success, login_blocked
- csrf_failed
- rate_limit_exceeded
- payload_suspicious
- session_hijack_detected
- session_expired_ttl, session_expired_lifetime
```

### Fases 7-10 — Avanzadas

Consultar `docs/security/security.md` para detalle completo de:
- Anti-replay en modo enforcement
- CSP con nonces dinámicos
- Tests automáticos de seguridad
- Gestión de secretos con vault

### Niveles de implementación

| Proyecto | Fases recomendadas |
|----------|-------------------|
| Práctica personal | 1 |
| Demo/Portfolio | 1 + 2 + 4 |
| MVP con usuarios | 1-6 |
| Producción real | 1-8 + hardening de 9-10 |
| Marvel (educativo) | 1-8 completas, 9-10 en progreso |

---

## 23. Análisis de vulnerabilidades con Snyk

Marvel integra **Snyk** para detectar vulnerabilidades en dependencias.

### Configuración

```env
# .env.example
SNYK_API_KEY=your-snyk-api-key
SNYK_ORG=your-organization-id
```

### Endpoint de escaneo

```
public/api/snyk-scan.php
```

Este endpoint permite ejecutar escaneos bajo demanda y obtener resultados formateados.

### Uso desde CLI

```bash
# Instalar Snyk CLI
npm install -g snyk

# Autenticar
snyk auth

# Escanear dependencias PHP
snyk test --file=composer.lock

# Escanear código (SAST)
snyk code test

# Monitorear proyecto (registrar en dashboard)
snyk monitor
```

### Integración en CI

```yaml
# .github/workflows/security-check.yml
- name: Snyk Security Scan
  uses: snyk/actions/php@master
  env:
    SNYK_TOKEN: ${{ secrets.SNYK_TOKEN }}
  with:
    args: --severity-threshold=high
```

### Alternativas a Snyk

| Herramienta | Tipo | Costo |
|-------------|------|-------|
| `composer audit` | Dependencias PHP | Gratis |
| Snyk | Dependencias + SAST | Freemium |
| GitHub Dependabot | Dependencias | Gratis |
| SonarCloud | Calidad + Security | Freemium |

### Cuándo usar cada herramienta

```bash
# Mínimo obligatorio (gratis)
composer audit --no-interaction

# Recomendado para proyectos serios
composer audit + Snyk (dependencias) + SonarCloud (código)
```

---

## 24. APIs internas del dashboard (panel de métricas)

Marvel expone varios endpoints para métricas y observabilidad, usados por los paneles de administración.

### Lista de endpoints

| Endpoint | Propósito | Autenticación |
|----------|-----------|---------------|
| `/api/ai-token-metrics.php` | Métricas de uso de tokens IA | Admin |
| `/api/sonar-metrics.php` | Métricas de SonarCloud | Admin |
| `/api/sentry-metrics.php` | Errores de Sentry | Admin |
| `/api/security-metrics.php` | Métricas de seguridad | Admin |
| `/api/performance-marvel.php` | PageSpeed Insights | Admin |
| `/api/accessibility-marvel.php` | Accesibilidad WAVE | Admin |
| `/api/github-activity.php` | Actividad de GitHub | Público |
| `/api/github-repo-browser.php` | Navegador de repositorio | Público |
| `/api/marvel-movies.php` | Videos de YouTube | Público |
| `/api/marvel-agent.php` | Agente conversacional | Público |
| `/api/snyk-scan.php` | Escaneo de vulnerabilidades | Admin |

### Ejemplo: API de métricas de tokens IA

```php
// GET /api/ai-token-metrics.php
{
    "status": "success",
    "data": {
        "comic_generator": {
            "total_tokens": 15420,
            "total_requests": 45,
            "avg_tokens_per_request": 342.67
        },
        "compare_heroes": {
            "total_tokens": 8930,
            "total_requests": 28,
            "avg_tokens_per_request": 318.93
        },
        "marvel_agent": {
            "total_tokens": 12500,
            "total_requests": 62,
            "avg_tokens_per_request": 201.61
        }
    },
    "last_updated": "2025-12-08T16:30:00+01:00"
}
```

### Patrón para crear APIs de métricas

```php
// public/api/my-metrics.php
<?php
require_once __DIR__ . '/../../src/bootstrap.php';

// 1. Verificar autenticación si es necesario
if ($requiresAuth && !isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// 2. Obtener datos del servicio correspondiente
$metricsService = $container['services']['metricsService'];
$data = $metricsService->getMetrics();

// 3. Responder en JSON
header('Content-Type: application/json');
echo json_encode([
    'status' => 'success',
    'data' => $data,
    'timestamp' => date('c')
]);
```

---

## 25. Estructura interna de un microservicio

Marvel separa la IA en microservicios independientes. Aquí está la estructura recomendada:

### Estructura completa

```
mi-microservicio/
├── public/
│   └── index.php           # Front Controller (router)
│
├── src/
│   ├── Config/             # Configuración del servicio
│   │   └── Config.php
│   ├── Http/
│   │   ├── Router.php      # Enrutador
│   │   ├── Request.php     # Objeto Request
│   │   └── Response.php    # Objeto Response
│   ├── Service/
│   │   └── MyService.php   # Lógica principal
│   ├── Client/
│   │   └── ExternalApiClient.php  # Clientes HTTP externos
│   └── Exception/
│       └── ServiceException.php
│
├── storage/
│   ├── logs/               # Logs del servicio
│   └── cache/              # Caché local
│
├── tests/
│   └── Service/
│       └── MyServiceTest.php
│
├── doc/
│   ├── README.md
│   └── API.md
│
├── .env.example
├── composer.json
├── phpunit.xml
└── Dockerfile
```

### Front Controller mínimo

```php
<?php
// public/index.php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use MyService\Http\Router;
use MyService\Http\Request;

// Cargar .env
$envPath = dirname(__DIR__) . '/.env';
if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with($line, '#')) continue;
        putenv($line);
    }
}

// Crear request y delegar al router
$request = Request::fromGlobals();
$router = new Router();
$response = $router->dispatch($request);
$response->send();
```

### Validación HMAC entre servicios

```php
// Verificar firma HMAC en el microservicio
public function validateHmac(Request $request): bool
{
    $signature = $request->getHeader('X-Internal-Signature');
    $timestamp = $request->getHeader('X-Internal-Timestamp');
    $apiKey = getenv('INTERNAL_API_KEY');
    
    // Verificar timestamp (máximo 5 minutos de diferencia)
    if (abs(time() - (int)$timestamp) > 300) {
        return false;
    }
    
    // Calcular firma esperada
    $payload = $request->getBody();
    $expectedSignature = hash_hmac('sha256', $timestamp . $payload, $apiKey);
    
    return hash_equals($expectedSignature, $signature);
}
```

### Comunicación entre app principal y microservicio

```php
// En la app principal: enviar request firmado
class RagProxyController
{
    public function forward(array $data): array
    {
        $timestamp = (string) time();
        $payload = json_encode($data);
        $signature = hash_hmac('sha256', $timestamp . $payload, $this->apiKey);
        
        $response = $this->httpClient->post($this->ragUrl, [
            'headers' => [
                'Content-Type' => 'application/json',
                'X-Internal-Signature' => $signature,
                'X-Internal-Timestamp' => $timestamp,
            ],
            'body' => $payload
        ]);
        
        return json_decode($response->getBody(), true);
    }
}
```

---

## 26. Tests E2E con Playwright (ejemplo concreto)

Marvel usa **Playwright** para tests end-to-end que validan flujos completos en el navegador.

### Estructura de tests E2E

```
tests/e2e/
├── album.spec.js           # Tests del álbum
├── auth.spec.js            # Tests de autenticación
├── comic.spec.js           # Tests del generador de cómics
├── navigation.spec.js      # Tests de navegación
└── accessibility.spec.js   # Tests de accesibilidad
```

### Configuración (playwright.config.cjs)

```javascript
// playwright.config.cjs
const { defineConfig } = require('@playwright/test');

module.exports = defineConfig({
    testDir: './tests/e2e',
    timeout: 30000,
    retries: 1,
    use: {
        baseURL: 'http://localhost:8080',
        headless: true,
        screenshot: 'only-on-failure',
        video: 'retain-on-failure',
    },
    webServer: {
        command: 'php -S localhost:8080 -t public',
        port: 8080,
        reuseExistingServer: true,
    },
});
```

### Ejemplo de test E2E

```javascript
// tests/e2e/auth.spec.js
const { test, expect } = require('@playwright/test');

test.describe('Autenticación', () => {
    
    test('Muestra formulario de login', async ({ page }) => {
        await page.goto('/login');
        
        await expect(page.locator('input[name="email"]')).toBeVisible();
        await expect(page.locator('input[name="password"]')).toBeVisible();
        await expect(page.locator('button[type="submit"]')).toBeVisible();
    });
    
    test('Login exitoso redirige a sección', async ({ page }) => {
        await page.goto('/login');
        
        await page.fill('input[name="email"]', 'admin@marvel.com');
        await page.fill('input[name="password"]', 'password123');
        await page.click('button[type="submit"]');
        
        await expect(page).toHaveURL(/\/seccion/);
        await expect(page.locator('.user-menu')).toBeVisible();
    });
    
    test('Login fallido muestra error', async ({ page }) => {
        await page.goto('/login');
        
        await page.fill('input[name="email"]', 'wrong@email.com');
        await page.fill('input[name="password"]', 'wrongpassword');
        await page.click('button[type="submit"]');
        
        await expect(page.locator('.error-message')).toBeVisible();
        await expect(page).toHaveURL(/\/login/);
    });
    
});

test.describe('Navegación protegida', () => {
    
    test('Ruta protegida redirige a login si no autenticado', async ({ page }) => {
        await page.goto('/secret/sonar');
        
        await expect(page).toHaveURL(/\/login/);
    });
    
});
```

### Comandos de Playwright

```bash
# Instalar Playwright
npm init playwright@latest

# Ejecutar todos los tests
npx playwright test

# Ejecutar con interfaz visual
npx playwright test --ui

# Ejecutar un archivo específico
npx playwright test tests/e2e/auth.spec.js

# Generar reporte HTML
npx playwright show-report

# Modo debug (paso a paso)
npx playwright test --debug

# Grabar un nuevo test
npx playwright codegen http://localhost:8080
```

### Integración en CI

```yaml
# .github/workflows/ci.yml
playwright:
  name: E2E Tests
  runs-on: ubuntu-latest
  needs: [build]
  steps:
    - uses: actions/checkout@v4
    
    - uses: actions/setup-node@v4
      with:
        node-version: '20'
        
    - name: Install dependencies
      run: npm ci
      
    - name: Install Playwright browsers
      run: npx playwright install --with-deps
      
    - name: Start PHP server
      run: php -S localhost:8080 -t public &
      
    - name: Wait for server
      run: sleep 3
      
    - name: Run Playwright tests
      run: npx playwright test
      
    - uses: actions/upload-artifact@v4
      if: failure()
      with:
        name: playwright-results
        path: test-results/
```

### Cuándo escribir tests E2E

| Escenario | ¿E2E? |
|-----------|-------|
| Flujo crítico de negocio (login, checkout) | Sí |
| Interacciones complejas de UI | Recomendado |
| Cada nuevo componente | No (excesivo) |
| Regresiones visuales | Sí (con screenshots) |
| APIs sin UI | No (usar tests de integración) |

### Balance recomendado

```
Pirámide de tests:
        /\
       /E2E\         <- Pocos, lentos, críticos
      /------\
     /Integración\   <- Moderados, API + repos
    /--------------\
   /    Unitarios   \ <- Muchos, rápidos, dominio
  /------------------\
```

---

## 27. Scripts CLI adicionales

Además de los scripts documentados, el repositorio incluye utilidades adicionales en `bin/`:

### Lista completa de scripts

| Script | Propósito | Uso |
|--------|-----------|-----|
| `bin/migrar-json-a-db.php` | Migra datos de JSON a MySQL | `php bin/migrar-json-a-db.php` |
| `bin/security-check.sh` | Ejecuta `composer audit` + lint de sintaxis | `bash bin/security-check.sh` |
| `bin/generate-bundle-size.php` | Genera métricas de assets | `php bin/generate-bundle-size.php` |
| `bin/pa11y-all.sh` | Auditoría de accesibilidad | `bash bin/pa11y-all.sh` |
| `bin/verify-token-metrics.php` | Verifica métricas de tokens IA | `php bin/verify-token-metrics.php` |
| `bin/analyze_coverage.py` | Analiza cobertura de tests | `python3 bin/analyze_coverage.py` |
| `bin/diagnose-token-metrics.sh` | Diagnostica problemas de métricas | `bash bin/diagnose-token-metrics.sh` |
| `bin/simulate_web_call.php` | Simula llamadas HTTP para testing | `php bin/simulate_web_call.php` |
| `bin/zonar_fix_permisos.sh` | Corrige permisos en hosting | `bash bin/zonar_fix_permisos.sh` |

### Ejemplo: Script de diagnóstico

```bash
#!/bin/bash
# bin/diagnose-token-metrics.sh

echo "=== Diagnóstico de métricas de tokens IA ==="

echo "1. Verificando archivos de log..."
ls -la storage/ai/*.log 2>/dev/null || echo "   No hay logs de tokens"

echo "2. Verificando permisos..."
stat -f "%Sp %N" storage/ai/ 2>/dev/null || stat -c "%A %n" storage/ai/

echo "3. Últimas entradas de log..."
tail -n 5 storage/ai/tokens.log 2>/dev/null || echo "   Log vacío o no existe"

echo "4. Verificando microservicios..."
curl -s -o /dev/null -w "%{http_code}" http://localhost:8081/health && echo " OpenAI OK" || echo " OpenAI FAIL"
curl -s -o /dev/null -w "%{http_code}" http://localhost:8082/health && echo " RAG OK" || echo " RAG FAIL"

echo "=== Fin del diagnóstico ==="
```

### Patrón para crear nuevos scripts

```php
#!/usr/bin/env php
<?php
// bin/my-script.php

declare(strict_types=1);

// 1. Cargar autoload
require_once dirname(__DIR__) . '/vendor/autoload.php';

// 2. Parsear argumentos
$options = getopt('v', ['verbose', 'dry-run', 'help']);
$verbose = isset($options['v']) || isset($options['verbose']);
$dryRun = isset($options['dry-run']);

if (isset($options['help'])) {
    echo "Uso: php bin/my-script.php [opciones]\n";
    echo "  -v, --verbose   Mostrar detalles\n";
    echo "  --dry-run       No ejecutar cambios\n";
    exit(0);
}

// 3. Ejecutar lógica
echo "Iniciando script...\n";

// Tu código aquí

echo "Completado.\n";
exit(0);
```

---

## 28. Variables de entorno completas (.env.example actualizado)

Referencia completa de todas las variables de entorno del proyecto:

```env
############################
# Clean Marvel Album (.env)
############################

## ============ APP ============
APP_ENV=local                           # local | hosting | test
APP_DEBUG=1                             # 1 = debug activo
APP_URL=http://localhost:8080           # URL base de la app
APP_PUBLIC_URL=http://localhost:8080    # URL pública (puede diferir en proxy)
APP_ORIGIN=http://localhost:8080        # Origen permitido para CORS
ADMIN_EMAIL=admin@marvel.com            # Email del administrador
ADMIN_PASSWORD_HASH=                    # Hash bcrypt de la contraseña

## ============ DATABASE ============
DB_DSN=                                 # mysql:host=localhost;dbname=marvel;charset=utf8mb4
DB_USER=                                # Usuario de BD
DB_PASSWORD=                            # Contraseña de BD
# Dejar vacío para usar JSON automáticamente

## ============ INTERNAL SECURITY ============
INTERNAL_API_KEY=change-me-strong-random  # Clave para firma HMAC entre servicios

## ============ MICROSERVICIOS IA ============
OPENAI_SERVICE_URL=http://localhost:8081/v1/chat    # URL del microservicio OpenAI
RAG_SERVICE_URL=http://localhost:8082/rag/heroes    # URL del microservicio RAG
# RAG_LOG_PATH=/ruta/absoluta/al/rag-service/storage/ai/tokens.log  # Opcional

## ============ EXTERNAL APIS ============
OPENAI_API_KEY=your-openai-key-here     # API key de OpenAI
ELEVENLABS_API_KEY=                     # API key de ElevenLabs (TTS)
ELEVENLABS_VOICE_ID=EXAVITQu4vr4xnSDxMaL  # ID de voz (Charlie por defecto)
ELEVENLABS_MODEL_ID=eleven_multilingual_v2  # Modelo de voz
ELEVENLABS_VOICE_STABILITY=0.5          # Estabilidad de voz
ELEVENLABS_VOICE_SIMILARITY=0.75        # Similitud de voz
TTS_INTERNAL_TOKEN=                     # Token interno para TTS

GOOGLE_YT_API_KEY=                      # API key de YouTube
MARVEL_UPDATE_TOKEN=                    # Token para actualizar videos

WAVE_API_KEY=                           # API key de WAVE (accesibilidad)
PSI_API_KEY=                            # API key de PageSpeed Insights
GITHUB_API_KEY=                         # API key de GitHub

## ============ OBSERVABILIDAD ============
SENTRY_DSN=                             # DSN de Sentry para errores
SENTRY_API_TOKEN=                       # Token API de Sentry
SENTRY_ORG_SLUG=                        # Slug de organización Sentry
SENTRY_PROJECT_SLUG=                    # Slug de proyecto Sentry

## ============ HEATMAP ============
HEATMAP_API_BASE_URL=http://localhost:5000  # URL del microservicio Heatmap
HEATMAP_API_TOKEN=                      # Token de autenticación Heatmap

## ============ SECURITY SCANNING ============
SNYK_API_KEY=                           # API key de Snyk
SNYK_ORG=                               # Organización en Snyk

## ============ DEBUG (solo en producción) ============
# DEBUG_API_FIREWALL=0                  # Logs del firewall API
# DEBUG_RAG_PROXY=0                     # Logs del proxy RAG
# DEBUG_RAW_BODY=0                      # Logs del body HTTP
```

---

## 29. Los 3 Microservicios de Marvel (Arquitectura Completa)

Marvel utiliza **3 microservicios externos** desacoplados del backend PHP principal. Cada uno tiene tecnología, despliegue y propósito diferente.

### Resumen de microservicios

| Servicio | Tecnología | Puerto Local | Despliegue Hosting | Propósito |
|----------|------------|--------------|-------------------|-----------|
| **OpenAI Service** | PHP 8.2 | 8081 | Hosting compartido (Creawebes) | Generar cómics con GPT |
| **RAG Service** | PHP 8.2 | 8082 | Hosting compartido (Creawebes) | Comparar héroes con RAG |
| **Heatmap Service** | Python 3.10 + Flask | 8080 (GCP) | Google Cloud (VM Debian) | Analytics de clics |

---

### OpenAI Service (PHP)

**Propósito**: Proxy seguro hacia la API de OpenAI para generar cómics.

#### Estructura

```
openai-service/
├── public/
│   └── index.php           # Front Controller
├── src/
│   ├── Config/
│   ├── Http/
│   │   └── Router.php      # Enrutador
│   └── Service/
│       └── ChatService.php # Lógica de chat
├── .env.example
├── composer.json
├── phpunit.xml
└── Dockerfile
```

#### Endpoints

| Endpoint | Método | Descripción |
|----------|--------|-------------|
| `/v1/chat` | POST | Genera respuesta con OpenAI |
| `/health` | GET | Health check |

#### Flujo de comunicación

```
Frontend (JS)
    ↓ POST /api/comic/generate
App Principal (PHP)
    ↓ cURL con firma HMAC
OpenAI Service (PHP puerto 8081)
    ↓ cURL con OPENAI_API_KEY
api.openai.com
    ↓ Respuesta GPT
    ← JSON con cómic generado
```

#### URLs por entorno

```php
// config/services.php
'local' => [
    'openai' => [
        'chat_url' => 'http://localhost:8081/v1/chat',
    ],
],
'hosting' => [
    'openai' => [
        'chat_url' => 'https://openai-service.contenido.creawebes.com/v1/chat',
    ],
],
```

#### Variables de entorno (.env)

```env
# openai-service/.env
OPENAI_API_KEY=sk-xxxxx
OPENAI_MODEL=gpt-4o-mini
INTERNAL_API_KEY=shared-secret-with-main-app
```

#### Cómo ejecutar localmente

```bash
cd openai-service
composer install
php -S localhost:8081 -t public
```

---

### RAG Service (PHP)

**Propósito**: Retrieval-Augmented Generation para comparar héroes usando base de conocimiento local.

#### Estructura

```
rag-service/
├── public/
│   └── index.php
├── src/
│   ├── Http/
│   │   └── Router.php
│   ├── Service/
│   │   ├── HeroRagService.php     # Comparación de héroes
│   │   └── MarvelAgent.php        # Agente conversacional
│   ├── Retriever/
│   │   ├── LexicalRetriever.php   # Búsqueda por keywords
│   │   └── VectorRetriever.php    # Búsqueda por embeddings
│   └── Knowledge/
│       └── KnowledgeLoader.php
├── storage/
│   ├── knowledge/                  # Base de conocimiento JSON
│   ├── embeddings/                 # Vectores de embeddings
│   └── ai/
│       └── tokens.log             # Log de uso de tokens
├── bin/
│   └── refresh_marvel_agent.sh    # Regenera embeddings
├── .env.example
├── composer.json
└── Dockerfile
```

#### Endpoints

| Endpoint | Método | Descripción |
|----------|--------|-------------|
| `/rag/heroes` | POST | Compara 2 héroes |
| `/rag/agent` | POST | Marvel Agent conversacional |
| `/health` | GET | Health check |

#### Tipos de retriever

```
RAG_USE_EMBEDDINGS=0  →  Retriever léxico (keywords)
RAG_USE_EMBEDDINGS=1  →  Retriever vectorial (embeddings OpenAI)
```

#### Flujo de comunicación

```
Frontend (JS)
    ↓ POST /api/rag/heroes { heroes: [1, 2], question: "..." }
App Principal (RagProxyController.php)
    ↓ cURL con firma HMAC
RAG Service (PHP puerto 8082)
    ↓ 1. Busca en knowledge/*.json
    ↓ 2. Construye prompt con contexto
    ↓ 3. Llama a OpenAI Service
    ← JSON con comparación
```

#### URLs por entorno

```php
// config/services.php
'local' => [
    'rag' => [
        'heroes_url' => 'http://localhost:8082/rag/heroes',
    ],
],
'hosting' => [
    'rag' => [
        'heroes_url' => 'https://rag-service.contenido.creawebes.com/rag/heroes',
    ],
],
```

#### Variables de entorno (.env)

```env
# rag-service/.env
OPENAI_API_KEY=sk-xxxxx
OPENAI_SERVICE_URL=http://localhost:8081/v1/chat
INTERNAL_API_KEY=shared-secret-with-main-app
RAG_USE_EMBEDDINGS=0
```

#### Cómo ejecutar localmente

```bash
cd rag-service
composer install
php -S localhost:8082 -t public
```

---

### Heatmap Service (Python + Flask + Google Cloud)

**Propósito**: Microservicio de analytics que registra clics del usuario para generar mapas de calor.

> Nota: este microservicio está en Python (no PHP) y corre en Google Cloud (no en hosting compartido).

#### Arquitectura

```
Navegador (JS Tracker)
        ↓ Clic del usuario
PHP Proxy: /api/heatmap/click.php
        ↓ cURL con X-API-Token
Heatmap Service (Python + Flask + Docker)
        ↓
SQLite (heatmap.db)
        ↓
PHP Proxy de Lectura → Panel /secret-heatmap
```

#### Tecnologías

- **Python 3.10**
- **Flask 3** (framework web)
- **Docker** (contenedor aislado)
- **Google Cloud Compute Engine** (VM Debian)
- **SQLite** (base de datos)
- **Token HTTP (X-API-Token)** para autenticación

#### Estructura del microservicio

```
heatmap-service/        # En servidor Google Cloud
├── app.py              # Aplicación Flask
├── heatmap.db          # Base de datos SQLite
├── Dockerfile
├── requirements.txt
└── .env                # API_TOKEN=***
```

#### Endpoints

| Endpoint | Método | Descripción |
|----------|--------|-------------|
| `/` | GET | Health check básico |
| `/health` | GET | Estado del servicio |
| `/track` | POST | Registra un clic |
| `/events` | GET | Lista todos los clics |

#### Payload de /track

```json
{
    "page_url": "/albums/avengers",
    "x": 0.45,
    "y": 0.30,
    "viewport_width": 1440,
    "viewport_height": 900,
    "scroll_y": 350
}
```

#### URLs por entorno

```env
# .env (app principal)

# LOCAL (si tienes el servicio corriendo localmente)
HEATMAP_API_BASE_URL=http://localhost:5000

# HOSTING (siempre apunta a Google Cloud)
HEATMAP_API_BASE_URL=http://34.74.102.123:8080
HEATMAP_API_TOKEN=your-secret-token
```

> Nota: la URL de Google Cloud es la misma tanto en local como en hosting, ya que el servicio corre en GCP.

#### Integración con PHP

```php
// src/Heatmap/Infrastructure/HttpHeatmapApiClient.php
class HttpHeatmapApiClient implements HeatmapApiClient
{
    public function sendClick(array $payload): array
    {
        return $this->request('POST', '/track', $payload);
        // Añade automáticamente: X-API-Token: $this->apiToken
    }
    
    public function getSummary(array $query): array
    {
        return $this->request('GET', '/events', null, $query);
    }
}
```

#### Proxies PHP (abstracción)

| Proxy PHP | Endpoint Heatmap | Propósito |
|-----------|------------------|-----------|
| `/api/heatmap/click.php` | `/track` | Registrar clics |
| `/api/heatmap/summary.php` | `/events` | Obtener resumen |
| `/api/heatmap/pages.php` | `/events` | Ranking de páginas |

#### Despliegue en Google Cloud

```bash
# En la VM de Google Cloud
sudo docker build -t heatmap-service .
sudo docker run -d \
  --name heatmap-container \
  -p 8080:8080 \
  -v /home/user/heatmap-service/heatmap.db:/app/heatmap.db \
  --env-file /home/user/heatmap-service/.env \
  heatmap-service:latest
```

#### Base de datos SQLite

```sql
CREATE TABLE click_events (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    page_url TEXT NOT NULL,
    x REAL NOT NULL,
    y REAL NOT NULL,
    viewport_width INTEGER,
    viewport_height INTEGER,
    scroll_y INTEGER,
    user_agent TEXT,
    created_at TEXT NOT NULL
);
```

---

### Diagrama completo de comunicación

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                              NAVEGADOR                                       │
│  ┌─────────┐  ┌─────────┐  ┌─────────┐  ┌─────────────────────────────────┐ │
│  │ Cómic   │  │ RAG     │  │ Agent   │  │ Tracker Heatmap (cada clic)    │ │
│  └────┬────┘  └────┬────┘  └────┬────┘  └───────────────┬─────────────────┘ │
└───────┼────────────┼────────────┼───────────────────────┼───────────────────┘
        │            │            │                       │
        ▼            ▼            ▼                       ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                    APP PRINCIPAL (PHP - Puerto 8080)                         │
│  ┌──────────────┐  ┌───────────────────┐  ┌─────────────────────────────┐   │
│  │ComicController│  │RagProxyController │  │/api/heatmap/click.php      │   │
│  └──────┬───────┘  └─────────┬─────────┘  └──────────────┬──────────────┘   │
│         │ HMAC              │ HMAC                      │ X-API-Token       │
└─────────┼───────────────────┼───────────────────────────┼───────────────────┘
          │                   │                           │
          ▼                   ▼                           ▼
┌─────────────────┐  ┌─────────────────┐  ┌─────────────────────────────────┐
│ OpenAI Service  │  │  RAG Service    │  │     Heatmap Service             │
│ (PHP :8081)     │  │  (PHP :8082)    │  │     (Python/Flask - GCP)        │
│                 │  │                 │  │                                 │
│ Hosting:        │  │ Hosting:        │  │ Google Cloud:                   │
│ openai-service. │  │ rag-service.    │  │ http://34.74.102.123:8080       │
│ contenido.      │  │ contenido.      │  │                                 │
│ creawebes.com   │  │ creawebes.com   │  │ Docker + SQLite                 │
└────────┬────────┘  └────────┬────────┘  └─────────────────────────────────┘
         │                    │
         │                    ▼
         │           ┌─────────────────┐
         └──────────▶│ OpenAI Service  │ (RAG llama a OpenAI)
                     └────────┬────────┘
                              │
                              ▼
                     ┌─────────────────┐
                     │ api.openai.com  │
                     │ (GPT-4o-mini)   │
                     └─────────────────┘
```

---

### Tabla resumen: Local vs Hosting

| Componente | Local | Hosting |
|------------|-------|---------|
| **App Principal** | `http://localhost:8080` | `https://iamasterbigschool.contenido.creawebes.com` |
| **OpenAI Service** | `http://localhost:8081` | `https://openai-service.contenido.creawebes.com` |
| **RAG Service** | `http://localhost:8082` | `https://rag-service.contenido.creawebes.com` |
| **Heatmap Service** | `http://localhost:5000` (opcional) | `http://34.74.102.123:8080` (Google Cloud) |

---

### Cómo levantar todo en local

```bash
# Terminal 1: App principal
cd clean-marvel
composer install
php -S localhost:8080 -t public

# Terminal 2: OpenAI Service
cd openai-service
composer install
php -S localhost:8081 -t public

# Terminal 3: RAG Service
cd rag-service
composer install
php -S localhost:8082 -t public

# Heatmap: usa directamente Google Cloud (no necesita local)
# O instala Flask localmente si quieres:
# cd heatmap-service && pip install -r requirements.txt && python app.py
```

---

### Verificar que todo funciona

```bash
# App principal
curl http://localhost:8080/

# OpenAI Service
curl -X POST http://localhost:8081/v1/chat \
  -H "Content-Type: application/json" \
  -d '{"messages":[{"role":"user","content":"test"}]}'

# RAG Service
curl -X POST http://localhost:8082/rag/heroes \
  -H "Content-Type: application/json" \
  -d '{"heroes":[1,2],"question":"¿Quién es más fuerte?"}'

# Heatmap (Google Cloud)
curl http://34.74.102.123:8080/health
```

---

## Referencias

- **Clean Marvel Album**: El proyecto que inspira esta guía.
- **Clean Architecture** (Robert C. Martin): La filosofía detrás de las capas.
- **Domain-Driven Design** (Eric Evans): Para modelado de dominios complejos.
- **OWASP Top 10**: Para entender las vulnerabilidades más comunes.
- **Twelve-Factor App**: Para configuración y despliegue moderno.
- **ADR GitHub**: [github.com/joelparkerhenderson/architecture-decision-record](https://github.com/joelparkerhenderson/architecture-decision-record)

---

> Nota: esta guía es un punto de partida. Adaptar al alcance del proyecto y priorizar una implementación simple antes de añadir complejidad.

---

*Última actualización: 8 Diciembre 2025*  
*Basado en Clean Marvel Album v2.1*  
*Secciones 19-29 añadidas: ElevenLabs TTS, Docker, n8n, Fases de Seguridad, Snyk, APIs Dashboard, Microservicios internos, Playwright E2E, Scripts CLI, Variables de entorno, Arquitectura de los 3 Microservicios (OpenAI, RAG, Heatmap/Python/GCP)*
