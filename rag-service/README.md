# rag-service

Microservicio PHP encargado de la comparación RAG de héroes para **Clean Marvel Album**.

## Arranque rápido

```bash
composer install
php -S localhost:8082 -t public
```

- Expone `POST /rag/heroes`.
- Depende del microservicio OpenAI (`http://localhost:8081/v1/chat`) para generar la respuesta final.
- El frontend (8080) solo consume el resultado y muestra la tabla + conclusión.

## Configuración

- Con la variable de entorno `OPENAI_SERVICE_URL` puedes cambiar el endpoint del microservicio de OpenAI. Por defecto usa `http://localhost:8081/v1/chat`.
- La base de conocimiento vive en `storage/knowledge/heroes.json`. Actualízala cuando agregues héroes nuevos relevantes para las comparaciones.

## Modos de retrieval (léxico vs vectorial)

- **Léxico (default):** usa bolsa de palabras y similitud coseno en memoria. Es el modo activo por defecto y no necesita embeddings ni claves adicionales.
- **Vectorial (embeddings, opcional):** calcula similitud usando embeddings de los héroes y la pregunta. Se activa con `RAG_USE_EMBEDDINGS=1`. Si faltan vectores o algo falla, el servicio hace fallback automático al retriever léxico para no interrumpir el flujo.
- **Autorefresco opcional:** `RAG_EMBEDDINGS_AUTOREFRESH=1` permite generar y guardar embeddings faltantes en caliente; si no está activo, sigue usando el modo léxico cuando no haya vectores.
- **Generación offline de embeddings:** ejecuta `php generate_embeddings.php` desde la raíz de `rag-service/` (requiere `OPENAI_API_KEY`) para poblar `storage/embeddings/heroes.json` sin gastar tokens en producción.
- **Compatibilidad:** el endpoint `POST /rag/heroes` y el caso de uso `compare()` permanecen idénticos; solo cambia la estrategia de ranking interna según la configuración.

## Tests del microservicio

- Este microservicio tiene su propia batería de tests en `rag-service/tests/` con configuración dedicada `rag-service/phpunit.xml`.
- Ejecuta los tests desde la carpeta raíz del repo con: `cd rag-service && ../vendor/bin/phpunit`.

## Trabajo futuro / mejoras posibles

- Vector store sólido (SQLite/FAISS/Qdrant/Chroma) con adaptador `RetrieverInterface`, manteniendo fallback léxico.
- Observabilidad: logs estructurados, métricas de latencia y trazas (p.ej., OpenTelemetry) para seguir el flujo.
- Resiliencia LLM: circuit breakers, rate limiting y reintentos con backoff hacia el servicio OpenAI.
- Cache de respuestas por combinación de `heroIds` + pregunta normalizada para ahorrar tokens en consultas repetidas.
- Pipeline de embeddings programado que regenere vectores cuando cambie `storage/knowledge/heroes.json`.
- Validaciones extra: limpieza/normalización de inputs, límites de longitud y cantidad de `heroIds`.
- QA continua: CI con PHPUnit + PHPStan, tests contractuales del endpoint `/rag/heroes` y smoke tests del modo vectorial activado.

## Request / Response

**Request**

```json
{
  "question": "Compara sus atributos y resume el resultado",
  "heroIds": ["id-1", "id-2"]
}
```

**Response**

```json
{
  "answer": "Atributo | Valoración\nAtaque | ...\n\n🧩 ...",
  "contexts": [
    { "heroId": "id-1", "nombre": "Iron Man", "contenido": "...", "score": 0.91 }
  ],
  "heroIds": ["id-1", "id-2"]
}
```

Si ocurre un error, devuelve `{ "error": "mensaje" }` con el código HTTP correspondiente.
