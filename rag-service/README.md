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
