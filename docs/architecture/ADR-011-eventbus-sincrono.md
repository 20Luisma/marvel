# ADR-011 – EventBus síncrono en memoria (trade-offs)

## Estado
Accepted

## Contexto
El dominio publica eventos que disparan handlers dentro del mismo request.
Se necesita justificar la elección sin añadir infraestructura.

## Decisión
Mantener un **EventBus síncrono en memoria** en la app principal.

## Consecuencias
**Pros:** simplicidad operativa, trazabilidad determinista, tests directos.  
**Contras:** fallos/latencia en handlers afectan al request, sin reintentos automáticos.

## Opciones descartadas
- EventBus asíncrono con cola (Redis/RabbitMQ): overhead para alcance académico.
- Event Store + proyecciones: complejidad innecesaria.

## Evidencia en código
- `src/Shared/Infrastructure/Bus/InMemoryEventBus.php`
- `src/Bootstrap/EventBootstrap.php`
