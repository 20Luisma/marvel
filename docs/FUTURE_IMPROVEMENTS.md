# 🚀 Informe de Consultoría Técnica: Futuras Mejoras

> **Proyecto:** Clean Marvel Album  
> **Autor:** Martín Pallante Cardeo  
> **Fecha:** Febrero 2026  
> **Versión:** 1.0  

---

## 📋 Resumen Ejecutivo

Este documento presenta un análisis técnico detallado de las mejoras identificadas para el proyecto Clean Marvel Album. Cada mejora incluye estimación de esfuerzo, impacto esperado y justificación técnica, demostrando capacidad de planificación y visión de producto.

| Prioridad | Mejoras | Horas Estimadas | Impacto Principal |
|-----------|---------|-----------------|-------------------|
| 🔴 Alta | 4 | 21-28h | Arquitectura + IA Scalability |
| 🟠 Media | 3 | 16-24h | Seguridad + Observabilidad |
| 🟡 Baja | 4 | 15-21h | Hardening + Calidad |
| **TOTAL** | **11** | **52-73h** | Sistema productivo |

---

## 📊 Matriz de Mejoras Priorizadas

| # | Mejora | Prioridad | Esfuerzo | Impacto |
|---|--------|-----------|----------|---------|
| 1 | Refactor a Application Layer (Comics) | 🔴 Alta | 4-6h | Arquitectura |
| 2 | Refactor a Application Layer (Album Covers) | 🔴 Alta | 3-4h | Arquitectura |
| 3 | Cliente LLM desacoplado (`ChatClientInterface`) | 🔴 Alta | 6-8h | Testabilidad |
| 4 | **Escalabilidad RAG: de JSON a Vector DB (Enterprise)** | 🔴 Alta | 8-10h | IA Scalability |
| 5 | Healthchecks HTTP para Microservicios | 🟠 Media | 4-6h | Observabilidad |
| 6 | CSP estricta sin `unsafe-inline` para scripts | 🟠 Media | 4-6h | Seguridad |
| 7 | EventBus con persistencia (Outbox Pattern) | 🟠 Media | 8-12h | Resiliencia |
| 8 | Logger centralizado con `trace_id` | 🟡 Baja | 3-4h | Observabilidad |
| 9 | Tests de seguridad ampliados | 🟡 Baja | 6-8h | Seguridad |
| 10 | HSTS Preload + HMAC enforcement | 🟡 Baja | 2-3h | Seguridad |
| 11 | Rate Limiting granular por endpoint | 🟡 Baja | 4-6h | Seguridad |

---

## 🔴 Mejoras de Alta Prioridad

### 1. Refactor `ComicController` → `GenerateComicService`

**Esfuerzo estimado:** 4-6 horas

#### Estado Actual vs Objetivo

| Aspecto | Estado Actual | Estado Objetivo |
|---------|---------------|-----------------|
| Ubicación de lógica | `ComicController.generate()` | `Application/Comics/GenerateComicService.php` |
| Responsabilidad | Controller orquesta todo | Controller solo delega |
| Testabilidad | Difícil de testear aislado | Tests unitarios puros |

#### Referencia en Código

```php
// src/Controllers/ComicController.php:74
// TODO: mover la orquestación de generación a src/Application/Comics/GenerateComicService.
```

#### Justificación Técnica

Actualmente el `ComicController` viola el principio de responsabilidad única (SRP) al contener lógica de:
- Validación de payload
- Búsqueda de héroes
- Orquestación de generación de cómic
- Manejo de respuestas HTTP

Esta lógica debería residir en un servicio de aplicación dedicado que:
- Reciba una lista de IDs de héroes
- Coordine con `FindHeroUseCase` y `OpenAIComicGenerator`
- Retorne un DTO con la historia generada

#### Entregables

- [ ] `src/Application/Comics/GenerateComicService.php`
- [ ] `src/Application/Comics/DTO/GenerateComicRequest.php`
- [ ] `src/Application/Comics/DTO/GenerateComicResponse.php`
- [ ] Tests unitarios del nuevo servicio
- [ ] Refactor de `ComicController` para delegar

---

### 2. Refactor `AlbumController.uploadCover()` → `AlbumCoverUploadService`

**Esfuerzo estimado:** 3-4 horas

#### Estado Actual vs Objetivo

| Aspecto | Estado Actual | Estado Objetivo |
|---------|---------------|-----------------|
| Ubicación | `AlbumController.uploadCover()` (~90 líneas) | `Application/Albums/AlbumCoverUploadService.php` |
| Filesystem | Acoplado a `move_uploaded_file()` | Abstracción `FilesystemInterface` |
| Validación | Inline en controller | `CoverValidator` dedicado |

#### Referencia en Código

```php
// src/Controllers/AlbumController.php:195
// TODO: mover la lógica de artefactos y file-system a src/Application/Albums/AlbumCoverUploadService.
```

#### Justificación Técnica

El método `uploadCover()` actualmente maneja:
- Validación de archivo (tamaño, extensión, MIME)
- Operaciones de filesystem
- Actualización de álbum

Esto dificulta:
- Testing sin filesystem real
- Reutilización de la lógica de upload
- Cambio de estrategia de almacenamiento (S3, CDN)

#### Entregables

- [ ] `src/Application/Albums/AlbumCoverUploadService.php`
- [ ] `src/Shared/Filesystem/FilesystemInterface.php`
- [ ] `src/Shared/Filesystem/LocalFilesystem.php`
- [ ] `src/Application/Albums/Validation/CoverValidator.php`
- [ ] Tests unitarios con `InMemoryFilesystem`

---

### 3. Cliente LLM Desacoplado (`ChatClientInterface`)

**Esfuerzo estimado:** 6-8 horas

#### Estado Actual vs Objetivo

| Aspecto | Estado Actual | Estado Objetivo |
|---------|---------------|-----------------|
| Acoplamiento | `OpenAIComicGenerator` usa cURL directo | Interface `ChatClientInterface` |
| Proveedor | Hardcoded OpenAI | Intercambiable (OpenAI, Anthropic, local) |
| Tests | Requiere mock de cURL o servicio real | Fake `InMemoryChatClient` |

#### Diseño Propuesto

```php
<?php

namespace App\AI\Contract;

interface ChatClientInterface
{
    /**
     * @param array<int, array{role: string, content: string}> $messages
     */
    public function chat(array $messages, ?string $model = null): ChatResponse;
    
    public function isAvailable(): bool;
}
```

```php
<?php

namespace App\AI\Contract;

final readonly class ChatResponse
{
    public function __construct(
        public string $content,
        public ?int $promptTokens = null,
        public ?int $completionTokens = null,
        public ?string $model = null,
    ) {}
}
```

#### Justificación Técnica

El patrón **Dependency Inversion** permite:
- Sustituir OpenAI por otro proveedor sin tocar la lógica de negocio
- Tests unitarios con `FakeChatClient` que retorna respuestas predefinidas
- Fallback a modelos locales (Ollama) en desarrollo
- Cambio de proveedor por configuración (`.env`)

#### Entregables

- [ ] `src/AI/Contract/ChatClientInterface.php`
- [ ] `src/AI/Contract/ChatResponse.php`
- [ ] `src/AI/Infrastructure/OpenAIChatClient.php`
- [ ] `tests/Fakes/FakeChatClient.php`
- [ ] Refactor de `OpenAIComicGenerator` para usar interface
- [ ] Binding en contenedor de servicios

---

### 4. Escalabilidad RAG: de JSON a Vector DB (Nivel Enterprise)

**Esfuerzo estimado:** 8-10 horas

#### Estado Actual vs Objetivo

| Aspecto | Estado Actual (RAG Ligero) | Estado Objetivo (Enterprise) |
|---------|----------------------------|------------------------------|
| Almacenamiento | Archivos JSON en disco | Base de Datos Vectorial (Qdrant, Pinecone, pgvector) |
| Búsqueda | Carga en memoria + bucle lineal (O(n)) | Búsqueda indexada HNSW (O(log n)) |
| Capacidad | Megabytes (pocos docs) | Terabytes (gigas de info, millones de docs) |
| Documentos | Documento completo | Fragmentación (Chunking) con solapamiento |

#### Referencia en Código

```php
// rag-service/src/Infrastructure/Knowledge/MarvelAgentKnowledgeBase.php
// Actualmente carga el JSON completo en el constructor.
```

#### Justificación Técnica

Para escenarios reales como un buffet de abogados con gigas de información, el sistema actual llegaría al `memory_limit` de PHP rápidamente. La transición a un RAG Enterprise permite:
- **Latencia Constante**: Tiempo de respuesta inferior a 100ms independientemente del volumen de datos.
- **Chunking Semántico**: Dividir documentos largos para inyectar solo la parte relevante, ahorrando tokens y mejorando la precisión.
- **Desacoplamiento total**: Gracias a la interfaz `KnowledgeBaseInterface`, solo es necesario crear un adaptador para la nueva DB vectorial.

#### Entregables

- [ ] `rag-service/src/Infrastructure/VectorDb/VectorDbClientInterface.php`
- [ ] Implementación de `QdrantKnowledgeBase` o `PineconeKnowledgeBase`
- [ ] Script de indexación masiva con **Semantic Chunking**
- [ ] Integración en `rag-service` vía Inversión de Dependencias

---

## 🟠 Mejoras de Media Prioridad

### 5. Healthchecks HTTP para Microservicios

**Esfuerzo estimado:** 4-6 horas

#### Estado Actual

| Servicio | Endpoint de salud | Métricas |
|----------|-------------------|----------|
| openai-service | ❌ No existe | ❌ |
| rag-service | ❌ No existe | ❌ |
| heatmap-service | ❌ No existe | ❌ |

#### Estado Objetivo

| Servicio | Liveness | Readiness | Métricas |
|----------|----------|-----------|----------|
| openai-service | `GET /health` | `GET /ready` | ✅ latencia, errores |
| rag-service | `GET /health` | `GET /ready` | ✅ latencia, modo retrieval |
| heatmap-service | `GET /health` | `GET /ready` | ✅ eventos/min |

#### Especificación de Endpoints

**GET /health** (Liveness)
```json
{
  "status": "healthy",
  "timestamp": "2026-02-01T08:00:00Z",
  "uptime_seconds": 3600
}
```

**GET /ready** (Readiness)
```json
{
  "status": "ready",
  "checks": {
    "openai_api": true,
    "database": true
  },
  "latency_ms": 45
}
```

#### Entregables

- [ ] Endpoint `/health` en cada microservicio
- [ ] Endpoint `/ready` en cada microservicio
- [ ] Dashboard consolidado en app principal (`/api/microservices-status`)
- [ ] Alertas en caso de servicios no disponibles

---

### 6. CSP Estricta (eliminar `unsafe-inline` en scripts)

**Esfuerzo estimado:** 4-6 horas

#### Estado Actual

```php
// src/Security/Http/SecurityHeaders.php:69
$scriptDirective = $nonce !== null ? " 'nonce-{$nonce}'" : " 'unsafe-inline'";
```

El fallback a `unsafe-inline` debilita la protección CSP cuando no hay nonce disponible.

#### Estado Objetivo

- Nonce obligatorio en todas las páginas
- Eliminar fallback `unsafe-inline`
- Scripts inline sin nonce → error visible en desarrollo

#### Plan de Implementación

1. Auditar todas las vistas que usan `<script>` inline
2. Asegurar que todas pasan el nonce desde el controller
3. Eliminar el fallback permisivo
4. Añadir tests que verifiquen CSP headers

#### Entregables

- [ ] Auditoría de vistas con scripts inline
- [ ] Refactor de vistas para usar nonce
- [ ] Eliminar fallback `unsafe-inline` para scripts
- [ ] Tests de headers CSP en cada endpoint

---

### 7. EventBus con Persistencia (Outbox Pattern)

**Esfuerzo estimado:** 8-12 horas

#### Estado Actual

```php
// src/Shared/Infrastructure/Bus/InMemoryEventBus.php
// - Eventos se pierden si falla el handler
// - No hay reintentos automáticos
// - Latencia acumulada en handlers síncronos
```

#### Estado Objetivo: Outbox Pattern

```
┌─────────────┐    ┌──────────────┐    ┌─────────────┐    ┌─────────┐
│ Domain Event│───▶│ Outbox Table │───▶│ Background  │───▶│ Handler │
└─────────────┘    └──────────────┘    │   Worker    │    └─────────┘
                                       └─────────────┘
```

#### Beneficios

| Aspecto | Síncrono (actual) | Outbox Pattern |
|---------|-------------------|----------------|
| Pérdida de eventos | Posible | Imposible (persistido) |
| Reintentos | Manual | Automático |
| Latencia | Acumulativa | Asíncrona |
| Trazabilidad | Logs únicamente | Tabla auditable |

#### Entregables

- [ ] Migración SQL para tabla `domain_events_outbox`
- [ ] `src/Shared/Infrastructure/Bus/OutboxEventBus.php`
- [ ] Worker de procesamiento (`bin/process-outbox`)
- [ ] Configuración para modo síncrono/asíncrono
- [ ] Tests de integración

---

## 🟡 Mejoras de Baja Prioridad

### 8. Logger Centralizado con `trace_id`

**Esfuerzo estimado:** 3-4 horas

#### Referencia en Código

```php
// src/Shared/Http/Router.php:101
// TODO: log de la excepción con trace_id en un logger centralizado
```

#### Entregables

- [ ] `src/Shared/Logging/CentralLogger.php`
- [ ] Middleware que inyecta `trace_id` en cada request
- [ ] Formato JSON estructurado para logs
- [ ] Integración con Sentry via `trace_id`

---

### 9. Tests de Seguridad Ampliados

**Esfuerzo estimado:** 6-8 horas

| Test | Estado Actual | Objetivo |
|------|---------------|----------|
| CSP headers | ❌ | ✅ Verificar nonce presente |
| HSTS headers | ❌ | ✅ Verificar max-age correcto |
| CSRF en forms | ✅ Bypass en test | ✅ Modo estricto también |
| Rate Limit | ❌ | ✅ Verificar respuestas 429 |
| XSS sanitization | ✅ Básico | ✅ Casos edge |

#### Entregables

- [ ] `tests/Security/CspHeadersTest.php`
- [ ] `tests/Security/HstsHeadersTest.php`
- [ ] `tests/Security/RateLimitTest.php`
- [ ] `tests/Security/XssSanitizationTest.php`
- [ ] Integración en CI/CD pipeline

---

### 10. HSTS Preload + HMAC Enforcement

**Esfuerzo estimado:** 2-3 horas

| Mejora | Descripción | Impacto |
|--------|-------------|---------|
| HSTS Preload | Añadir directiva `preload` y solicitar inclusión en navegadores | Seguridad desde primer request |
| HMAC Enforcement | Modo estricto que rechace requests sin firma válida | Protección inter-servicios |

#### Entregables

- [ ] Añadir `preload` a header HSTS
- [ ] Enviar solicitud a hstspreload.org
- [ ] Flag `HMAC_STRICT_MODE` en `.env`
- [ ] Documentar proceso de rotación de keys

---

### 11. Rate Limiting Granular por Endpoint

**Esfuerzo estimado:** 4-6 horas

| Endpoint | Límite Actual | Límite Propuesto | Justificación |
|----------|---------------|-----------------|---------------|
| `/api/comic` | Global | 10 req/min/IP | Costoso (tokens IA) |
| `/api/rag/*` | Global | 20 req/min/IP | Uso intensivo |
| `/api/reset-demo` | Ninguno | 1 req/min/IP | Prevenir abuso |
| `/api/heroes` | Global | 60 req/min/IP | Lectura frecuente |

#### Entregables

- [ ] Configuración de límites por ruta en `RateLimiter`
- [ ] Respuestas 429 con `Retry-After` header
- [ ] Dashboard de requests bloqueados
- [ ] Tests de rate limiting

---

## 💰 Estimación Económica (Referencia Consultoría)

> Esta sección demuestra capacidad de estimación profesional, no representa un presupuesto real.

| Bloque | Horas | Tarifa Referencia | Subtotal |
|--------|-------|-------------------|----------|
| Alta Prioridad | 18h | €80/h | €1,440 |
| Media Prioridad | 24h | €80/h | €1,920 |
| Baja Prioridad | 21h | €80/h | €1,680 |
| **TOTAL** | **63h** | - | **€5,040** |

---

## 📅 Roadmap Sugerido

### Fase 1: Arquitectura (Sprint 1-2)
- Mejoras #1, #2, #3
- Resultado: Código más testeable y mantenible

### Fase 2: Observabilidad (Sprint 3)
- Mejoras #5, #8
- Resultado: Visibilidad del estado del sistema

### Fase 3: Seguridad (Sprint 4-5)
- Mejoras #6, #9, #10, #11
- Resultado: Sistema hardened

### Fase 4: Resiliencia (Sprint 6)
- Mejora #7
- Resultado: Sistema tolerante a fallos

---

## 🎯 Conclusión

Este análisis demuestra:

1. **Autocrítica técnica**: Identificación honesta de áreas de mejora
2. **Visión de producto**: Roadmap claro con fases definidas
3. **Capacidad de estimación**: Horas y costes realistas
4. **Conocimiento de patrones**: Outbox, DI, Clean Architecture
5. **Enfoque profesional**: Priorización basada en impacto

> *"El software nunca está terminado, solo entregado. Un ingeniero maduro sabe identificar qué mejoraría con más tiempo."*

---

## 📚 Referencias

- [ADR-006: Seguridad Fase 2](./architecture/ADR-006-seguridad-fase2.md)
- [ADR-011: EventBus síncrono](./architecture/ADR-011-eventbus-sincrono.md)
- [SECURITY.md](../SECURITY.md)
- [Clean Architecture - Robert C. Martin](https://blog.cleancoder.com/uncle-bob/2012/08/13/the-clean-architecture.html)
- [Outbox Pattern - Microservices.io](https://microservices.io/patterns/data/transactional-outbox.html)

---

*Documento generado como parte del Trabajo Fin de Máster — Clean Marvel Album*  
*Última actualización: Febrero 2026*
