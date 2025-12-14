# ADR-009 – Circuit Breaker en la Aplicación Principal

## Estado
Accepted

## Contexto
La aplicación principal realiza llamadas HTTP a servicios externos (OpenAI, RAG, Heatmap, TMDB) que pueden fallar o experimentar latencia elevada. Sin un mecanismo de protección, estos fallos pueden:

- Causar timeouts que degradan la experiencia del usuario
- Consumir recursos del servidor esperando respuestas que nunca llegan
- Propagar fallos en cascada cuando un servicio está caído

El microservicio `rag-service` ya implementa un Circuit Breaker (`rag-service/src/Application/Resilience/CircuitBreaker.php`), pero la app principal no tenía este patrón.

## Decisión
Implementar un **Circuit Breaker** en la aplicación principal (`src/Shared/Infrastructure/Resilience/CircuitBreaker.php`) que pueda ser utilizado por cualquier cliente HTTP externo.

### Características implementadas

| Característica | Valor por defecto | Descripción |
|----------------|-------------------|-------------|
| `failureThreshold` | 3 | Número de fallos antes de abrir el circuito |
| `openTtlSeconds` | 30 | Tiempo de espera antes de probar recuperación |
| `halfOpenMaxCalls` | 1 | Llamadas permitidas en estado half-open |

### Estados del Circuit Breaker

```
      ┌─────────────────────────────────────┐
      │                                     │
      ▼                                     │
┌──────────┐                          ┌───────────┐
│  CLOSED  │──── N failures ─────────►│   OPEN    │
│ (normal) │                          │ (blocked) │
└──────────┘                          └───────────┘
      ▲                                     │
      │                               TTL expires
      │                                     │
      │                                     ▼
      │                             ┌───────────────┐
      └────── success ──────────────│  HALF_OPEN    │
                                    │ (testing)     │
                                    └───────────────┘
                                          │
                              failure     │
                                          ▼
                                    ┌───────────┐
                                    │   OPEN    │
                                    └───────────┘
```

## Justificación

- **Fail-fast**: Cuando un servicio está caído, el Circuit Breaker evita esperar timeouts repetidos.
- **Recuperación automática**: El estado half-open permite probar si el servicio se recuperó.
- **Aislamiento de fallos**: Cada servicio tiene su propio Circuit Breaker (por nombre).
- **Fallbacks opcionales**: El método `execute()` acepta un callable de fallback.
- **Consistencia**: Mismo patrón que `rag-service`, facilitando mantenimiento.

## Uso

```php
use App\Shared\Infrastructure\Resilience\CircuitBreaker;
use App\Shared\Infrastructure\Resilience\CircuitBreakerOpenException;

$circuitBreaker = new CircuitBreaker(
    name: 'openai-service',
    failureThreshold: 3,
    openTtlSeconds: 30
);

// Con excepción si está abierto
try {
    $result = $circuitBreaker->execute(fn() => $this->callOpenAI($payload));
} catch (CircuitBreakerOpenException $e) {
    // Servicio temporalmente no disponible
    return ['error' => 'Service unavailable, please retry later'];
}

// Con fallback
$result = $circuitBreaker->execute(
    fn() => $this->callOpenAI($payload),
    fn() => $this->getCachedResponse()  // Fallback
);
```

## Consecuencias

### Positivas
- ✅ Protección contra fallos en cascada
- ✅ Mejor experiencia de usuario (fail-fast en vez de timeout largo)
- ✅ Reducción de carga en servicios que ya están fallando
- ✅ Métricas implícitas de estado del servicio (archivo JSON)
- ✅ Tests completos (`tests/Shared/Infrastructure/Resilience/CircuitBreakerTest.php`)

### Negativas
- ⚠️ Estado persistido en archivos JSON (no distribuido)
- ⚠️ Para multi-instancia, considerar Redis u otro store distribuido
- ⚠️ Añade complejidad a las llamadas HTTP

## Archivos creados

- `src/Shared/Infrastructure/Resilience/CircuitBreaker.php`
- `src/Shared/Infrastructure/Resilience/CircuitBreakerOpenException.php`
- `tests/Shared/Infrastructure/Resilience/CircuitBreakerTest.php`
- `docs/architecture/ADR-009-circuit-breaker-app.md` (este archivo)

## Relacionado

- `rag-service/src/Application/Resilience/CircuitBreaker.php` — Implementación original en microservicio
- `rag-service/docs/adr/ADR-001-circuit-breaker-llm-observability.md` — ADR del RAG service
- ADR-005: Microservicios OpenAI/RAG

## Migración futura

Para entornos multi-nodo considerar:
1. Migrar store a Redis (`CircuitBreakerRedisStore`)
2. Añadir métricas Prometheus para estados del circuito
3. Dashboards de observabilidad por servicio
