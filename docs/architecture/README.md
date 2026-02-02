# ADRs y arquitectura decisional

Un *Architectural Decision Record (ADR)* documenta decisiones técnicas que afectan la arquitectura global. Cada ADR sigue este patrón:

1. **Estado:** siempre “Accepted” mientras la decisión esté vigente.  
2. **Contexto:** describe el problema o necesidad que motiva la decisión.  
3. **Decisión:** el curso de acción elegido.  
4. **Justificación:** argumentos que sostienen la decisión.  
5. **Consecuencias:** impactos positivos y negativos.  
6. **Opciones descartadas:** otras alternativas evaluadas y por qué se descartaron.  
7. **Supersede:** referencia a ADRs anteriores que se reemplazan en el futuro.

### Cómo escribir un nuevo ADR

- Crea un archivo `ADR-XXX-titulo.md` en esta carpeta.  
- Mantén el contenido en español claro y con verbos activos.  
- Actualiza la lista de decisiones y la sección “Supersede ADRs” cuando una decisión se reemplaza.

### ADRs actuales

- [ADR-001: Elección de Clean Architecture en PHP](ADR-001-clean-architecture.md)  
- [ADR-002: Persistencia dual JSON + Database fallback](ADR-002-persistencia.md)  
- [ADR-003: Integración con SonarCloud](ADR-003-sonarcloud.md)  
- [ADR-004: Integración con Sentry](ADR-004-sentry.md)  
- [ADR-005: Elección de microservicios para OpenAI y RAG](ADR-005-microservicios-openai-rag.md)  
- [ADR-006: Seguridad Fase 2 - Anti-replay, Rate Limit y Auth](ADR-006-seguridad-fase2.md)  
- [ADR-007: Modularización del Sistema de Bootstrap](ADR-007-bootstrap-modularization.md)
- [ADR-009: Circuit Breaker en la Aplicación Principal](ADR-009-circuit-breaker-app.md)
- [ADR-010: Value Objects para Configuración Tipada](ADR-010-value-objects-config.md)
- [ADR-011: EventBus síncrono en memoria](ADR-011-eventbus-sincrono.md)
- [ADR-012: Monitoreo Continuo Proactivo en Producción](ADR-012-monitoreo-continuo-produccion.md)
- [ADR-013: Security Sentinel Watchdog y Hardening](ADR-013-security-sentinel-watchdog.md)  
- [ADR-014: Filtro Quirúrgico: Quality Gate para Despliegue](ADR-014-filtro-quirurgico-quality-gate.md)  
- [ADR-015: RAG Enterprise con Pinecone (Vector Database)](ADR-015-enterprise-rag-pinecone.md)  

### Supersede ADR

Las decisiones futuras que superen alguna existente deben:

- Referenciar el ADR superado en el título (`Supersede ADR-00X`).  
- Explicar por qué la nueva decisión es necesaria (por ejemplo, cambio de proveedor, nuevas restricciones).  
- Mantener el historial de consecuencias para facilitar auditorías.
