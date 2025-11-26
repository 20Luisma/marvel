# Operación y configuración

## Levantar el servicio

```bash
cd rag-service
php -S localhost:8082 -t public
```

## Variables de entorno clave
- `OPENAI_SERVICE_URL`: endpoint del microservicio OpenAI (por defecto `http://localhost:8081/v1/chat` o hosting equivalente).
- `RAG_USE_EMBEDDINGS`: activa el retriever vectorial (`1` para activar, vacío o `0` para mantener el léxico).
- `RAG_EMBEDDINGS_AUTOREFRESH`: si está en `1`, intenta generar embeddings faltantes en caliente; si falla, usa fallback léxico.
- `OPENAI_API_KEY`: necesaria solo para generar embeddings (offline o en autorefresco).

## Endpoint
- `POST /rag/heroes`
  - Body: `{ "heroIds": ["id-1", "id-2"], "question": "texto opcional" }`
  - Respuesta: `{ "answer": string, "contexts": [...], "heroIds": [...] }`
  - Errores devuelven `{ "error": "mensaje" }` con código HTTP correspondiente.
