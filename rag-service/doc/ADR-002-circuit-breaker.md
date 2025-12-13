# ADR-002 — Circuit Breaker propio para llamadas LLM en `rag-service`

## 1. Título

Circuit Breaker propio con persistencia en JSON y observabilidad estructurada para llamadas al microservicio LLM (`openai-service`) desde `rag-service`.

## 2. Estado

Accepted (2025-12-13)

## 3. Contexto

`rag-service` (PHP 8.2) implementa retrieval (léxico y vectorial) y delega la generación de respuestas al microservicio `openai-service` mediante `src/Application/Clients/OpenAiHttpClient.php`.

Antes del cambio existía retry con backoff (3 intentos). En escenarios de degradación del upstream, ese enfoque puede amplificar fallos (tormenta de reintentos), incrementar latencia y consumo de recursos, y dificultar el diagnóstico sin trazabilidad consistente por request.

Restricciones del proyecto:

- Mantener Clean Architecture (separación Application/Contracts/Infrastructure).
- No introducir frameworks externos (p. ej. Genkit, SDK completo de OTel) ni nuevas dependencias.
- No romper contratos públicos existentes ni cambiar el output cuando el upstream funciona correctamente.
- Persistencia sin Redis/DB (entornos simples/hosting).

## 4. Decisión

Se implementa un Circuit Breaker propio para llamadas LLM, con estados `closed`, `open` y `half_open`, configurado por variables de entorno y persistido en un archivo JSON bajo `storage/` con locks (`LOCK_EX`/`LOCK_SH`).

Se integra observabilidad en logs JSON line-delimited reutilizando el `trace_id` existente por request y se habilita un transporte HTTP inyectable para tests sin afectar el comportamiento por defecto (cURL).

## 5. Alternativas consideradas

1) No circuit breaker (mantener solo retry/backoff).
- Pros: menor complejidad.
- Contras: no evita cascadas ni reduce carga cuando el upstream está caído; dificulta recuperación y eleva latencia/coste.

2) Retry-only más agresivo (más intentos y backoff).
- Pros: puede aumentar éxito ante fallos transitorios.
- Contras: aumenta el tiempo bloqueado por request y puede amplificar el problema bajo degradación sostenida.

3) Librería externa / framework (p. ej., Genkit u observabilidad completa con SDK OTel).
- Pros: soluciones estándar y ecosistema (métricas, exportadores, tracing).
- Contras: introduce dependencias y superficie de integración; no compatible con la restricción explícita del proyecto.

4) Circuit breaker con Redis/DB compartido.
- Pros: estado consistente multi-instancia.
- Contras: añade infraestructura, credenciales y operación; fuera del alcance del despliegue objetivo del microservicio.

## 6. Consecuencias

Positivas:
- Resiliencia: reduce llamadas al upstream cuando está fallando; evita tormentas de reintentos y protege la latencia del sistema.
- Recuperación controlada: `half_open` permite sondeo conservador para volver a `closed` tras éxito.
- Observabilidad: eventos JSON correlables por `trace_id` con estado del breaker y latencia cuando hubo llamada real.
- Compatibilidad: `OpenAiHttpClient` conserva la firma pública con parámetros opcionales; el camino “happy-path” produce el mismo output.

Negativas / trade-offs:
- Estado en archivo: adecuado para single-instance; en multi-instancia no hay coordinación (cada instancia gestiona su estado).
- Semántica de “fallo”: se basa en errores de transporte/HTTP>=500/JSON inválido/errores `ok=false`; no sustituye SLOs ni métricas agregadas.
- Observabilidad limitada: logs estructurados son base operativa, pero aún no hay métricas Prometheus ni alerting formal.

## 7. Detalles de implementación

### Funcionamiento del estado

Estados y reglas:
- `closed`: permite llamadas. Si fallos consecutivos >= `failureThreshold`, transiciona a `open` y guarda `opened_at`.
- `open`: si no pasó `openTtlSeconds`, se hace short-circuit (no se realiza HTTP) y se lanza `CircuitBreakerOpenException`. Si pasó TTL, transiciona a `half_open`.
- `half_open`: permite hasta `halfOpenMaxCalls`. Si una llamada tiene éxito, vuelve a `closed` y resetea contadores. Si falla, vuelve a `open` y actualiza `opened_at`.

Parámetros:
- `failureThreshold` (`CB_FAILURE_THRESHOLD`, default 3)
- `openTtlSeconds` (`CB_OPEN_TTL_SECONDS`, default 30)
- `halfOpenMaxCalls` (`CB_HALF_OPEN_MAX_CALLS`, default 1)

### Persistencia JSON y locks

Estado persistido en:
- `CB_STATE_FILE` (default `storage/ai/circuit_breaker.json`)

Formato (clave/valor):
- `state`: `closed|open|half_open`
- `failure_count`: int
- `opened_at`: int (epoch seconds)
- `half_open_calls`: int

Sincronización:
- Lectura: `LOCK_SH` en `JsonFileCircuitBreakerStateStore::load()`
- Escritura: `LOCK_EX` en `JsonFileCircuitBreakerStateStore::save()`

El estado no contiene payloads ni secretos; solo contadores y timestamps.

### Integración en `OpenAiHttpClient`

- Se inyecta opcionalmente `CircuitBreaker` y `StructuredLoggerInterface`.
- Antes de ejecutar la llamada: `CircuitBreaker::beforeCall()` decide permitir o bloquear; en bloqueo lanza `CircuitBreakerOpenException`.
- En éxito: `CircuitBreaker::onSuccess()`.
- En fallo: `CircuitBreaker::onFailure()` (errores de transporte, HTTP>=500, JSON inválido, `ok=false` y casos equivalentes).
- Transporte HTTP inyectable mediante `HttpTransportInterface` para evitar cURL real en tests; por defecto se usa una implementación interna basada en cURL.

### Logging estructurado con `trace_id`

`JsonFileStructuredLogger` emite JSON line-delimited e incluye:
- `timestamp`
- `trace_id` (reutiliza `X-Trace-Id` si existe; si no, el proveedor genera uno)
- `event`
- campos adicionales por evento

Eventos emitidos:
- `llm.request`: `state`, `ok`, `error` (si aplica), `latency_ms` (solo si hubo request real)
- `llm.circuit.opened`
- `llm.circuit.short_circuit`
- `llm.circuit.half_open`

Debug:
- `APP_DEBUG=1` permite eventos `llm.debug` vía logger; `APP_DEBUG=0` no emite logs ad-hoc ni usa rutas hardcodeadas.

## 8. Testing

Archivo:
- `tests/Application/Clients/OpenAiHttpClientCircuitBreakerTest.php`

Cobertura mínima intencional:
- Short-circuit en `open` con TTL no expirado: asegura que no se ejecuta el transporte HTTP (protección efectiva).
- Transición `open -> half_open -> closed` en éxito: valida la recuperación controlada y el reset de contadores.

Suficiencia:
- Los tests validan las propiedades críticas de resiliencia (bloqueo y recuperación) sin depender de red ni de `openai-service`.
- La inyección de `HttpTransportInterface` permite aislar el comportamiento y mantener determinismo.

## 9. Notas operacionales

Variables `.env` relevantes:
- `CB_FAILURE_THRESHOLD` (default 3)
- `CB_OPEN_TTL_SECONDS` (default 30)
- `CB_HALF_OPEN_MAX_CALLS` (default 1)
- `CB_STATE_FILE` (default `storage/ai/circuit_breaker.json`)
- `APP_DEBUG` (default 0)

Ubicación del archivo de estado:
- Por defecto: `rag-service/storage/ai/circuit_breaker.json` (debe ser escribible por el runtime).

Interpretación de eventos de log:
- `llm.circuit.opened`: el upstream muestra fallos sostenidos; se empieza a proteger el sistema.
- `llm.circuit.short_circuit`: llamadas bloqueadas; útil para cuantificar impacto y confirmar protección.
- `llm.circuit.half_open`: ventana de sondeo tras TTL; si se observan fallos repetidos, el upstream sigue degradado.
- `llm.request`: latencia y resultado por llamada real; correlación por `trace_id` para debugging de requests individuales.

## 10. Referencias internas

Archivos principales:
- `src/Application/Clients/OpenAiHttpClient.php`
- `src/Application/Resilience/CircuitBreaker.php`
- `src/Application/Resilience/CircuitBreakerOpenException.php`
- `src/Application/Resilience/CircuitBreakerStateStoreInterface.php`
- `src/Infrastructure/Resilience/JsonFileCircuitBreakerStateStore.php`
- `src/Application/Contracts/HttpTransportInterface.php`
- `src/Application/Contracts/StructuredLoggerInterface.php`
- `src/Infrastructure/Observability/JsonFileStructuredLogger.php`
- `src/Application/Observability/NullStructuredLogger.php`
- `src/bootstrap.php`
- `tests/Application/Clients/OpenAiHttpClientCircuitBreakerTest.php`

