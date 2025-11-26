# Microservicio `openai-service`

`openai-service` es un microservicio PHP 8.2 que expone un endpoint HTTP para generar contenido usando la API de OpenAI. Actúa como fachada entre la app principal (Clean Marvel Album) y OpenAI, encapsulando autenticación, construcción del payload y manejo de errores.

## Endpoint HTTP expuesto

- `POST /v1/chat`
  - Body (ejemplo):
    ```json
    {
      "messages": [
        { "role": "system", "content": "Eres un narrador" },
        { "role": "user", "content": "Genera una historia" }
      ]
    }
    ```
  - Respuesta (éxito):
    ```json
    {
      "ok": true,
      "content": "{ \"title\": \"...\", \"summary\": \"...\", \"panels\": [] }"
    }
    ```
  - Respuesta (error):
    ```json
    { "ok": false, "error": "mensaje descriptivo" }
    ```

## Variables de entorno

- `OPENAI_API_KEY` (obligatoria para llamar a OpenAI).
- `OPENAI_API_BASE` (opcional, default `https://api.openai.com/v1`).
- `OPENAI_MODEL` (opcional, default `gpt-4o-mini`).
- `ALLOWED_ORIGINS` (opcional, lista separada por comas para CORS).

## Arranque local

```bash
cd openai-service
php -S localhost:8081 -t public
```

## Tests

```bash
cd openai-service
../vendor/bin/phpunit
```

La suite PHPUnit vive en `openai-service/tests` con configuración en `openai-service/phpunit.xml`.
