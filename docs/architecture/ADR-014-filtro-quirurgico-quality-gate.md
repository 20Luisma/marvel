# ADR-014 – Filtro Quirúrgico: Quality Gate para Despliegue de Alta Confianza

## Estado
Accepted

## Contexto
En un entorno de despliegue continuo (CD), el riesgo de introducir regresiones críticas en producción es elevado, especialmente cuando se integran servicios externos de IA con latencias variables. Se necesita un mecanismo que garantice que solo las versiones que cumplen con el "mínimo de negocio vital" sean promocionadas.

## Decisión
Implementar el patrón **"Filtro Quirúrgico"** (Surgical Quality Gate). Se trata de una suite de tests E2E ultra-específicos ejecutados con Playwright que actúan como "porteros" en el pipeline de CI/CD. Si un solo test de esta suite falla, el despliegue a producción se aborta automáticamente.

## Justificación
A diferencia de los tests unitarios, el Filtro Quirúrgico valida la **integración real** de todos los componentes:
1. Conectividad con el Microservicio de IA.
2. Capacidad de razonamiento del RAG.
3. Ciclo de vida completo del Dominio (CRUD).
4. Persistencia efectiva en la infraestructura de destino.

## Consecuencias
**Pros:** Confianza total en cada release, prevención de caídas de servicio por fallos en APIs externas, documentación viva del flujo crítico.  
**Contras:** Incremento ligero en el tiempo de ejecución del pipeline (aprox. 2-3 minutos).

## Evidencia en código
- `.github/workflows/ci.yml` (paso `Quality Gate`)
- `tests/e2e/surgical-production-check.spec.js`
