# Modos de retrieval

## Léxico (default)
- Usa bolsa de palabras y similitud coseno en memoria.
- No requiere embeddings ni claves adicionales.
- Es el modo activo por defecto; no actives ninguna variable para usarlo.

## Vectorial (opcional, con fallback)
- Usa embeddings de héroes y de la pregunta para ranquear contextos.
- Se activa con `RAG_USE_EMBEDDINGS=1`.
- Si faltan vectores o hay errores, el servicio cae automáticamente al modo léxico.
- Autorefresco opcional: `RAG_EMBEDDINGS_AUTOREFRESH=1` permite generar y guardar embeddings faltantes en caliente (usa el cliente de embeddings configurado).

## Generación offline de embeddings
- Desde `rag-service/` ejecuta: `php generate_embeddings.php`
  - Requiere `OPENAI_API_KEY`.
  - Guarda los vectores en `storage/embeddings/heroes.json`.
  - Evita gastar tokens en cada request: se generan una vez y se reutilizan.

## Compatibilidad
- El endpoint `POST /rag/heroes` y el caso de uso `compare()` no cambian; solo cambia la estrategia de ranking según la configuración.
