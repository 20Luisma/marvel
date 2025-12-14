# ADR-007 – Modularización del Sistema de Bootstrap

## Estado
Accepted

## Contexto
El archivo original `src/bootstrap.php` mezclaba múltiples responsabilidades en un único punto de inicialización:
- Carga de variables de entorno (`.env`)
- Generación y gestión de trace-id para observabilidad
- Inicialización de sesión con configuración de seguridad
- Wiring de repositorios de persistencia (DB/JSON) con fallback resiliente
- Setup de servicios de seguridad (CSRF, rate-limit, firewall, anti-replay, auth)
- Configuración de EventBus y handlers de eventos de dominio
- Inicialización de Sentry y métricas de tokens (observabilidad)
- Registro de casos de uso, servicios y controladores
- Configuración de router y contenedor de dependencias

Esta concentración de lógica en un solo archivo dificultaba:
- Testing aislado de cada subsistema
- Comprensión del orden de inicialización
- Mantenimiento y evolución de componentes individuales
- Identificación de dependencias entre módulos

## Decisión
Dividir el sistema de bootstrap en **6 módulos especializados** siguiendo el **Single Responsibility Principle (SRP)**:

1. **`EnvironmentBootstrap`**: Carga `.env`, generación de trace-id, inicio de sesión PHP con configuración segura (HttpOnly, SameSite=Lax).

2. **`PersistenceBootstrap`**: Wiring de repositorios (`AlbumRepository`, `HeroRepository`, `ActivityRepository`) con resolución automática de driver (DB/File) y fallback resiliente MySQL→JSON.

3. **`SecurityBootstrap`**: Setup de servicios de seguridad (`CsrfTokenManager`, `AuthService`, `RateLimiter`, `ApiFirewall`, `SessionReplayMonitor`, `IpBlockerService`, `LoginAttemptService`).

4. **`EventBootstrap`**: Inicialización del `EventBus` en memoria, registro de handlers (`HeroCreatedNotificationHandler`, `AlbumUpdatedNotificationHandler`) y configuración de `NotificationRepository`.

5. **`ObservabilityBootstrap`**: Configuración de Sentry (DSN, environment, error/exception handlers) y `TokenMetricsService` para métricas de consumo de IA.

6. **`AppBootstrap`**: Orquestador central que llama a los demás bootstraps en orden correcto, ensambla el contenedor de dependencias, registra casos de uso y controladores.

El archivo `src/bootstrap.php` queda reducido a un wrapper que invoca `AppBootstrap::init()` y retorna el contenedor.

## Justificación
- **Mantenibilidad**: Cada módulo es fácil de entender, modificar y probar de forma aislada.
- **Testabilidad**: Permite crear tests unitarios específicos para cada subsistema sin inicializar toda la aplicación.
- **Claridad**: El orden de dependencias es explícito en `AppBootstrap::init()` (Environment → Persistence → Security → Events → Observability).
- **Escalabilidad**: Nuevos subsistemas (ej: cache, queue) pueden agregarse como nuevos bootstraps sin modificar código existente.
- **Cohesión**: Cada módulo agrupa responsabilidades relacionadas (ej: toda la seguridad en un lugar).

## Consecuencias

### Positivas
- **Reducción de acoplamiento**: responsabilidades separadas por bootstrap especializado.
- **Testing aislado**: posibilidad de testear `PersistenceBootstrap` sin inicializar seguridad u observabilidad.
- **Onboarding**: nuevos desarrolladores pueden entender el sistema leyendo módulos individuales.
- **Documentación implícita**: los nombres de clases y métodos describen el flujo de inicialización.
- **Separación de concerns**: cada módulo puede evolucionar independientemente (ej.: cambiar estrategia de logging de seguridad sin tocar persistencia).

### Negativas
- **Mayor número de archivos**: 6 archivos nuevos vs 1 monolítico.
- **Indirección adicional**: hay que seguir la cadena de llamadas `bootstrap.php` → `AppBootstrap` → `*Bootstrap`.
- **Deuda técnica residual**:
  - Lógica de anti-replay permanece en `AppBootstrap` en lugar de `SecurityBootstrap` (cohesión mejorable).
  - Función `resolveDriver` duplicada en `AppBootstrap` y `PersistenceBootstrap` (candidato a extracción).

### Consideraciones futuras
- **Patrón Builder**: Si `AppBootstrap::init()` crece más allá de 300 líneas, considerar patrón Builder para construcción fluida del contenedor.
- **Dependency Injection Container**: Para proyectos de mayor escala, evaluar adopción de un contenedor PSR-11 (ej: Symfony DI, PHP-DI).
- **DTOs para configuración**: Reemplazar arrays `array<string, mixed>` por Value Objects tipados (ej: `SecurityConfig`, `PersistenceConfig`).

## Opciones descartadas
- **Mantener monolito con secciones comentadas**: Rechazado por no resolver el problema de testabilidad ni cohesión.
- **Usar container PSR-11 de terceros**: Rechazado para mantener objetivo educativo (mostrar wiring manual).
- **Crear 12+ módulos micro-especializados**: Rechazado por sobre-ingeniería (ej: separar "Sentry" de "Métricas" no aporta valor).

## Métricas de éxito
- **Tests**: suite existente ejecutable tras el refactor.
- **PHPStan**: análisis estático (config en `phpstan.neon`).
- **CI/CD**: workflows ejecutándose tras la modularización.

## Referencias
- ADR-001: Clean Architecture en PHP (base arquitectónica)
- ADR-002: Persistencia con fallback resiliente (lógica en `PersistenceBootstrap`)
- ADR-004: Integración de Sentry (lógica en `ObservabilityBootstrap`)
- ADR-006: Seguridad Fase 2 (lógica en `SecurityBootstrap`)
- Principio SRP: Robert C. Martin, "Clean Architecture" (2017)
- Patrón Composition Root: Mark Seemann, "Dependency Injection in .NET" (2011)

## Supersede
Ninguno. Este ADR documenta la evolución del sistema de bootstrap original sin invalidar decisiones arquitectónicas previas.

## Notas de implementación
- **Fecha de refactor**: Diciembre 2025
- **Impacto en CI/CD**: los pipelines existentes continúan funcionando.
- **Retrocompatibilidad**: se mantiene la interfaz pública del contenedor.
