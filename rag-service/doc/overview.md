# Visión general

- **Propósito:** microservicio PHP 8.2 que compara héroes Marvel usando RAG y expone un único endpoint HTTP para el frontend de Clean Marvel Album.
- **Arquitectura:** sigue el esquema de Clean Architecture. Presentación (controlador HTTP) delega en casos de uso (servicios de aplicación) que consumen contratos del dominio e implementaciones de infraestructura (repositorio JSON, clientes externos).
- **Endpoint expuesto:** `POST /rag/heroes` recibe `{ heroIds: string[], question?: string }` y devuelve `answer`, `contexts` y `heroIds`.
- **Flujo:** carga héroes desde `storage/knowledge/heroes.json`, ranquea contextos (retriever léxico por defecto o vectorial opcional) y delega la respuesta final a OpenAI vía `OpenAiHttpClient`.
- **Fallback seguro:** si el modo vectorial no está disponible o falla, siempre se usa el retriever léxico y el endpoint se mantiene estable.
