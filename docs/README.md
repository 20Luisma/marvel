# Documentación técnica de Clean Marvel Album

Este repositorio combina un monolito PHP con microservicios de IA. Aquí encontrarás el índice de documentos vivos que describen arquitectura, API, flujos de calidad y guías prácticas.

## Estructura de la documentación

1. **API** (`docs/api/`): referencia OpenAPI (`openapi.yaml`) con endpoints de la app y microservicios.
2. **Arquitectura & ADRs** (`docs/ARCHITECTURE.md`, `docs/architecture/`): capas, microservicios, decisiones y “Supersede ADR”.
3. **Componentes** (`docs/components/`): mapa de dependencias e integraciones.
4. **Guías** (`docs/guides/`): arranque rápido, autenticación, testing y calidad.
5. **Microservicios** (`docs/microservicioheatmap/README.md`): detalles del servicio de heatmap externo.
6. **OpenAI Service** (`openai-service/doc/README-openai-service.md`): fachada HTTP hacia OpenAI (endpoint, variables y operación).
7. **UML** (`docs/uml/`): diagramas de alto nivel.
8. **Agentes de IA** (`docs/agent.md`, `AGENTS.md`): pautas para Codex y roles.

## Supersede ADR

Cuando una decisión quede obsoleta, crea un nuevo ADR que cite al anterior en “Supersede” y documente el motivo del cambio. Marca el nuevo como “Accepted” y detalla impactos. Guarda los ADR en `docs/architecture/`.
