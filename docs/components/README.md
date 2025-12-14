# Componentes y dependencias

Clean Marvel Album se organiza por capas limpias, pero el diagrama lógico también incluye servicios auxiliares. Esta guía identifica los componentes clave, sus responsabilidades y cómo interactúan.

## Componentes principales

1. **Frontend/Landing (`public/`, `views/`, `public/assets/js/`)**  
   - Vista principal de álbumes (`/albums`), generación de cómic, dashboards (SonarCloud, Sentry, GitHub) y estructura de navegación (`views/layouts/header.php`).  
   - Scripts en `public/assets/js/` solo llaman a la API mediante `fetch()` y no se navega a endpoints que devuelven JSON.  

2. **Monolito PHP (`src/` + `public/index.php`)**  
   - Router HTTP (`App\Shared\Http\Router`) dirige rutas HTML y JSON a controladores.  
   - Controladores (`App\Controllers\AlbumController`, `HeroController`, `ComicController`, etc.) actúan como orquestadores: validan, llaman casos de uso y devuelven `JsonResponse`.  
   - Casos de uso (`src/*/Application`) ejecutan lógica de negocio y se apoyan en repositorios dominados por `src/*/Domain` y `Infrastructure`.  

3. **Microservicio `openai-service`**  
   - Expone `POST /v1/chat` y actúa como proxy a OpenAI.  
   - Mantiene su propio autoload, `.env` y validaciones de origen.  
   - `App\AI\OpenAIComicGenerator` del monolito lo consume para generar cómics y narraciones.

4. **Microservicio `rag-service`**  
   - Proporciona `POST /rag/heroes` con lógica RAG (HeroRetriever + HeroRagService) y bases JSON (`storage/knowledge/heroes.json`).  
   - Sirve comparaciones de héroes y contextos relevantes; la vista de cómics lo consume normalmente a través del proxy de la app principal `POST /api/rag/heroes` (ver `src/Controllers/RagProxyController.php`).

5. **Infraestructura de datos**  
   - Repositorios JSON (`storage/albums.json`, `storage/heroes.json`, carpetas `storage/actividad`, `storage/marvel`).  
   - EventBus en memoria (`App\Shared\Infrastructure\Bus\InMemoryEventBus`).  
   - Factories de PDO en hosting (clase `PdoConnectionFactory`) con fallback automático.

## Flujo de dependencias

Presentación → Aplicación → Dominio → Infraestructura → Microservicios externos.

- Para rutas distintas de `/`, `public/index.php` delega en `public/home.php`, que inicializa el contenedor (`src/bootstrap.php`) y ejecuta `App\Shared\Http\Router`.  
- `Router` despacha a controladores y verifica `Request::wantsHtml()` para renderizar vistas.  
- Los controladores llaman a casos de uso (`App\Albums\Application\ListAlbumsUseCase`) que, a su vez, dependen de repositorios (contratos en `Domain`).  
- Los adaptadores concretos (`FileAlbumRepository`, `DbAlbumRepository`) están en `Infrastructure`.  
- Los microservicios `openai-service` y `rag-service` se consumen mediante `fetch()`; no se mezclan con las capas puras del dominio.

## Instrumentación adicional

- **API docs**: `docs/api/openapi.yaml` describe los endpoints JSON.  
- **Guías**: `docs/guides/` centralizan instrucciones reproducibles (setup, autenticación, tests).  
- **ADRs**: `docs/architecture/` guarda decisiones técnicas (clean architecture, persistencia dual, Sonar, Sentry, microservicios).
