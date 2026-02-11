# ADR-016 – Observabilidad: Propagación end-to-end de trace_id entre microservicios

## Estado
Accepted

## Contexto
La arquitectura del proyecto se basa en **3 microservicios** que se comunican entre sí:

```
App Principal (8080) ──HTTP──→ RAG Service (8082)
App Principal (8080) ──HTTP──→ OpenAI Service (8081)
```

Cuando un error ocurre en el RAG Service, desde la App Principal no es posible correlacionar qué petición del usuario causó ese error. Los logs de cada servicio son independientes y no comparten un identificador común. Esto dificulta enormemente el debugging en producción.

## Decisión
Implementar **propagación end-to-end del trace_id** (patrón Distributed Tracing) siguiendo las prácticas de observabilidad de sistemas distribuidos:

### 1. Generación del trace_id
- `TraceIdGenerator` genera un UUID v4 por cada petición entrante (`EnvironmentBootstrap`).
- Se almacena en `$_SERVER['X_TRACE_ID']` para acceso global en el ciclo de vida de la petición.
- Se devuelve como header `X-Trace-Id` en la respuesta HTTP.

### 2. Propagación inter-servicios
- `RagProxyController` (Compare Heroes): inyecta `X-Trace-Id` en los headers de la petición al RAG Service.
- `HeroRagSyncService` (Upsert de héroes): inyecta `X-Trace-Id` en los headers de la petición al RAG Service.
- El RAG Service (`resolve_trace_id()`) acepta el header `X-Trace-Id`, lo valida y lo reutiliza en vez de generar uno nuevo. Así ambos servicios comparten el mismo trace_id.

### 3. Logging con trace_id
- **Router.php**: excepciones no controladas se loguean en `app-errors.log` con trace_id, path, method, archivo y línea.
- **SecurityLogger**: todos los eventos de seguridad incluyen trace_id.
- **ApiFirewall**: eventos de payload sospechoso incluyen trace_id.
- **HeroRagSyncService**: errores de sincronización incluyen trace_id.
- **RAG Service**: logs internos incluyen el trace_id recibido.

## Flujo completo

```
Usuario → App Principal
  1. EnvironmentBootstrap genera trace_id = "abc-123"
  2. $_SERVER['X_TRACE_ID'] = "abc-123"
  3. header('X-Trace-Id: abc-123') → respuesta al usuario
  
  → Si llama al RAG Service:
     4. RagProxyController añade header X-Trace-Id: abc-123
     5. RAG Service recibe abc-123 via resolve_trace_id()
     6. RAG Service loguea con trace_id=abc-123
     7. RAG Service devuelve header X-Trace-Id: abc-123
  
  → Si hay error en cualquier punto:
     8. Los logs de App y RAG contienen trace_id=abc-123
     9. grep "abc-123" app-errors.log rag.log → historia completa
```

## Justificación
- **Debugging en producción**: sin trace_id, un error en el RAG Service no se puede asociar con la petición del usuario que lo causó.
- **Patrón estándar de la industria**: Distributed Tracing es la práctica recomendada para microservicios (OpenTelemetry, Jaeger, Zipkin usan el mismo concepto).
- **Coste mínimo**: solo se añaden headers HTTP y strings en los logs. No hay dependencias externas ni impacto en rendimiento.
- **Escalabilidad**: si se añaden más microservicios en el futuro, basta con propagar el header `X-Trace-Id` para mantener la trazabilidad.

## Consecuencias
### Positivas
- Cualquier error se puede rastrear de principio a fin con un solo `grep`.
- Los logs son correlacionables entre servicios sin necesidad de herramientas externas.
- Compatible con futuras integraciones con sistemas como OpenTelemetry o ELK Stack.
- El header `X-Trace-Id` en la respuesta HTTP permite al frontend mostrar el ID al usuario para soporte técnico.

### Negativas
- No se implementa un sistema de tracing completo (spans, duración, etc.). Es tracing a nivel de correlación de logs, no de performance profiling.
- Los logs se escriben en archivos locales, no en un sistema centralizado (futuro paso: ELK o similar).

## Archivos implicados
- `src/Monitoring/TraceIdGenerator.php` — Generación del trace_id.
- `src/Bootstrap/EnvironmentBootstrap.php` — Asignación al inicio del ciclo.
- `src/Shared/Http/Router.php` — Logging de excepciones con trace_id.
- `src/Controllers/RagProxyController.php` — Propagación al RAG Service (Compare Heroes).
- `src/Heroes/Infrastructure/Rag/HeroRagSyncService.php` — Propagación al RAG Service (Upsert).
- `src/Security/Logging/SecurityLogger.php` — Logging de seguridad con trace_id.
- `rag-service/public/index.php` — Recepción y reutilización del trace_id.

## Opciones descartadas
- **OpenTelemetry SDK**: exceso de complejidad para el alcance actual del proyecto. Se reserva para futuras iteraciones.
- **Logging a base de datos**: añadiría latencia y dependencia. Los archivos de log plano son suficientes para el volumen actual.
