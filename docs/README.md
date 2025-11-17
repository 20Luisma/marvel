# Documentación técnica de Clean Marvel Album

Este repositorio combina un monolito PHP con microservicios de inteligencia artificial. La documentación unificada respeta las buenas prácticas del máster: cada capa tiene su carpeta, la API está descrita con OpenAPI y las decisiones relevantes se rastrean en ADRs con contexto y consecuencias claras.

## Estructura de la documentación

1. **API** (`docs/api/`): referencia OpenAPI (`openapi.yaml`) con los endpoints públicos de la app (álbumes, héroes, actividad, cómics).
2. **Componentes** (`docs/components/`): mapa de componentes y dependencias, incluyendo microservicios `openai-service` y `rag-service` y cómo se conectan.
3. **Guías** (`docs/guides/`): instrucciones prácticas para iniciar el entorno, autenticar con tokens, ejecutar pruebas y mantener la calidad.
4. **Arquitectura & ADRs** (`docs/architecture/`): repositorio de Architectural Decision Records numerados para justificar decisiones como clean architecture, persistencia dual y observabilidad; incluye una sección “Supersede ADR” para futuras revisiones.

## Supersede ADR

Cuando una decisión ya documentada necesite reemplazarse, crea un nuevo ADR que cite el número anterior en “Supersede” y explique por qué la nueva decisión se impone. Mantén el estado en “Accepted” y registra los impactos en la carpeta `docs/architecture/`.
