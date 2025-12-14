# ADR-001 — Circuit Breaker y observabilidad para llamadas LLM en `rag-service`

**Estado:** Accepted  
**Fecha:** 2025-12-13

## Contexto

`rag-service` (PHP 8.2) implementa un RAG con retriever léxico y vectorial (embeddings opcionales) y delega las llamadas al LLM al microservicio `openai-service` mediante `src/Application/Clients/OpenAiHttpClient.php`.  
En el estado previo existía un retry con backoff (3 intentos) pero no había un mecanismo para evitar tormentas de reintentos cuando el servicio LLM estaba degradado, ni señales operables para correlacionar errores y latencia por request.

Requisitos del TFM:

- Incrementar resiliencia para entornos operativos sin reescritura ni frameworks externos.
- Mantener contratos públicos y comportamiento funcional cuando el upstream está sano.
- Añadir observabilidad ligera en JSON, correlable por `trace_id`.
- Persistencia sin Redis/DB (hosting compartido / despliegues simples).

## Decisión

Se implementa un circuit breaker simple y determinista para las llamadas al LLM:

- Estados: `closed`, `open`, `half_open`.
- Umbral de fallos consecutivos en `closed` para abrir el circuito.
- TTL en `open` para transicionar a `half_open`.
- Ventana controlada en `half_open` para permitir un número máximo de llamadas de “sondeo”.
- Persistencia del estado en un archivo JSON en `storage/` con escritura atómica usando `LOCK_EX`.

Además, se introduce logging estructurado en JSON reutilizando el `trace_id` por request, y se elimina/condiciona el debugging hardcodeado (rutas absolutas) por `APP_DEBUG`.

## Detalles de implementación

### Componentes

- Circuit breaker:
  - `src/Application/Resilience/CircuitBreaker.php`
  - `src/Infrastructure/Resilience/JsonFileCircuitBreakerStateStore.php`
  - `src/Application/Resilience/CircuitBreakerOpenException.php`
- Observabilidad:
  - `src/Application/Contracts/StructuredLoggerInterface.php`
  - `src/Infrastructure/Observability/JsonFileStructuredLogger.php`
  - `src/Application/Observability/NullStructuredLogger.php`
  - El `trace_id` se obtiene desde `src/Infrastructure/Observability/ServerTraceIdProvider.php` (header `X-Trace-Id` o generado).
- Integración en cliente LLM:
  - `src/Application/Clients/OpenAiHttpClient.php`
  - `src/Application/Contracts/HttpTransportInterface.php` (inyección de transporte HTTP para tests; por defecto usa cURL).
- Wiring:
  - `src/bootstrap.php` crea `CircuitBreaker`, `JsonFileCircuitBreakerStateStore` y `JsonFileStructuredLogger` y los inyecta en `OpenAiHttpClient` sin romper compatibilidad (parámetros opcionales).

### Variables de entorno

- `CB_FAILURE_THRESHOLD` (default `3`): fallos consecutivos en `closed` para abrir.
- `CB_OPEN_TTL_SECONDS` (default `30`): TTL mínimo en `open` antes de intentar `half_open`.
- `CB_HALF_OPEN_MAX_CALLS` (default `1`): máximo de llamadas permitidas en `half_open` (sondeo).
- `CB_STATE_FILE` (default `storage/ai/circuit_breaker.json`): archivo JSON del estado.
- `APP_DEBUG` (default `0`): si `1`, el cliente puede emitir `llm.debug` vía logger; si `0`, no hay logs ad-hoc ni rutas absolutas hardcodeadas.

Defaults se normalizan a valores mínimos seguros (`>= 1`) en `src/bootstrap.php`.

### Eventos de logging (JSON)

Se registran eventos en formato JSON line-delimited, incluyendo siempre `trace_id`:

- `llm.request`
  - Campos: `state`, `ok`, `error` (si aplica), `latency_ms` (cuando hubo request real).
- `llm.circuit.opened`
  - Campos: `state` (`open`).
- `llm.circuit.short_circuit`
  - Campos: `state` (`open` o `half_open`), cuando se bloquea una llamada por breaker.
- `llm.circuit.half_open`
  - Campos: `state` (`half_open`), cuando expira TTL y se habilita el sondeo.

### Semántica del breaker (resumen operativo)

- `closed`:
  - Permite llamadas.
  - Incrementa `failure_count` ante fallos; al alcanzar `CB_FAILURE_THRESHOLD` abre el circuito y registra `opened_at`.
- `open`:
  - Si `now - opened_at < CB_OPEN_TTL_SECONDS`: bloquea y lanza `CircuitBreakerOpenException` sin realizar HTTP.
  - Si expira TTL: transiciona a `half_open` y registra `llm.circuit.half_open`.
- `half_open`:
  - Permite hasta `CB_HALF_OPEN_MAX_CALLS`.
  - Si una llamada tiene éxito: vuelve a `closed` y resetea contadores.
  - Si falla: vuelve a `open` y actualiza `opened_at`.

## Alternativas consideradas

1) **Sin circuit breaker (solo retries)**
   - Ventaja: mínima complejidad.
   - Desventaja: en degradación produce tormenta de reintentos, mayor latencia y coste, y empeora la recuperación (self-amplifying failure).

2) **Circuit breaker con Redis/DB**
   - Ventaja: estado compartido entre instancias; mejor para escalado horizontal.
   - Desventaja: introduce infraestructura adicional (no disponible o no deseada en hosting simple), credenciales, operación y puntos de fallo extra.

3) **Framework externo (p. ej., Genkit / SDK completo de OpenTelemetry)**
   - Ventaja: estándares y ecosistema maduros (métricas, traces, exportadores).
   - Desventaja: añade dependencias y superficie de cambios; no es compatible con la restricción del proyecto (no introducir frameworks nuevos ni reescritura).

## Consecuencias

### Positivas

- Resiliencia: evita llamadas repetidas al upstream cuando está fallando (protección de cascada).
- Coste: reduce consumo de recursos y tiempo de espera en escenarios de degradación.
- Trazabilidad: correlación de eventos por `trace_id` y visibilidad básica de latencia por request.
- Cambios incrementales: `OpenAiHttpClient` mantiene compatibilidad y la ruta “healthy” devuelve el mismo output.

### Negativas / trade-offs

- Estado en archivo: válido para despliegues single-instance; en multi-instancia no hay coordinación (cada instancia “ve” su propio estado).
- Concurrencia: `LOCK_EX` protege escritura, pero no se ofrece un modelo distribuido ni garantías transaccionales fuertes.
- Observabilidad limitada: aún no hay métricas Prometheus ni alerting formal; el log JSON es un primer paso.

## Seguridad

- No se exponen secretos: el estado del breaker solo contiene contadores y timestamps; no registra keys ni payloads.
- Logs sin PII: los eventos no deben incluir datos personales; se registran estados, latencias y errores genéricos.
- Persistencia en `storage/`: rutas bajo control del servicio; se usa `LOCK_EX` para minimizar corrupción concurrente.

## Operación

### Ejemplo de estado JSON

`storage/ai/circuit_breaker.json`
```json
{"state":"open","failure_count":3,"opened_at":1734123456,"half_open_calls":0}
```

### Ejemplo de log JSON

```json
{"timestamp":"2025-12-13T21:10:00+00:00","trace_id":"trace-test-123","event":"llm.request","state":"half_open","ok":true,"latency_ms":842}
```

### Ajustes recomendados para producción

- `APP_DEBUG=0` (por defecto).
- `CB_FAILURE_THRESHOLD=3` (subir a `5` si hay ruido transitorio frecuente).
- `CB_OPEN_TTL_SECONDS=30` (subir a `60` si el upstream suele tardar en recuperarse).
- `CB_HALF_OPEN_MAX_CALLS=1` (mantener bajo para limitar “sondeo” concurrente).
- `CB_STATE_FILE` en una ruta escribible y persistente bajo `storage/` (misma política de backups/permissions que el resto del servicio).

## Criterios de aceptación

- [ ] `vendor/bin/phpunit -c rag-service/phpunit.xml` en verde (incluye `tests/Application/Clients/OpenAiHttpClientCircuitBreakerTest.php`).
- [ ] Se verifica que en estado `open` sin TTL expirado hay short-circuit y no se ejecuta HTTP.
- [ ] Se verifica transición `open -> half_open -> closed` en éxito.
- [ ] No hay rutas absolutas hardcodeadas ni logs ad-hoc cuando `APP_DEBUG=0`.
- [ ] El archivo `CB_STATE_FILE` se escribe con `LOCK_EX` y no contiene secretos.
