# Arquitectura interna de `openai-service`

- **public/**: punto de entrada HTTP (`public/index.php`) y wiring mínimo.
- **Http/Router**: resuelve rutas y CORS; delega a controladores.
- **Controller/OpenAIController**: parsea el body y delega al caso de uso.
- **Application/UseCase/GenerateContent**: orquesta el caso de uso de chat; extrae el contenido del LLM, limpia fences de código y genera fallback seguro.
- **Infrastructure/Client/OpenAiClient**: cliente HTTP hacia la API de OpenAI (usa Guzzle); resuelve `OPENAI_API_KEY`, base y modelo, valida respuestas y lanza excepciones descriptivas.

Flujo textual:

`public/index.php` → `Http\Router` → `Controller\OpenAIController` → `Application\UseCase\GenerateContent` → `Infrastructure\Client\OpenAiClient` → OpenAI API.
