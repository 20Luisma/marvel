# Marvel Agent Memory (Fuente maestra)

Documento centralizado para el agente RAG de **Clean Marvel Album**.  
Toda la información debe estar alineada con el **código y la configuración actuales** del proyecto.

El objetivo de este documento es:
- Dar una **visión global y coherente** del sistema.
- Servir como **fuente única de verdad** para explicaciones técnicas.
- Ser el **único punto de entrada** para el futuro Marvel Agent (RAG limitado a este contenido).

---

## 1. Resumen general del proyecto

- **Clean Marvel Album** es una aplicación PHP 8.x con arquitectura en capas (Clean Architecture) que:
  - Gestiona álbumes, héroes y secciones especiales (Secret Room).
  - Orquesta microservicios dedicados (RAG, OpenAI, Heatmap).
  - Integra calidad, accesibilidad y pruebas automáticas mediante CI/CD.

- **Tecnologías principales**:
  - PHP 8.x en la app principal y microservicios (`rag-service`, `openai-service`).
  - Python + Flask en el `heatmap-service`.
  - Frontend basado en vistas PHP + HTML + CSS + JS sin framework frontend grande.
  - SQLite en el microservicio de heatmap; ficheros JSON para knowledge base y embeddings.

- **Arquitectura limpia**:
  - **Presentación**: controladores HTTP y vistas (`views/*`, `PageController`, `Router`).
  - **Aplicación**: casos de uso y servicios de orquestación (RAG, generación de contenido, etc.).
  - **Dominio**: entidades, contratos e interfaces.
  - **Infraestructura**: repositorios, clientes HTTP, acceso a ficheros, integración con APIs externas.

- **Microservicios actuales**:
  - `rag-service`: comparación de héroes mediante RAG ligero.
  - `openai-service`: fachada HTTP a la API de OpenAI para chat/contenido.
  - `heatmap-service`: tracking y visualización de clics en la web Marvel.

- **Secciones clave de la app**:
  - `/albums`, `/heroes`: navegación principal de contenido Marvel.
  - `/comic`: generación de historias/cómics usando OpenAI.
  - `/seccion` (Secret Room): panel central técnico (Sonar, Sentry, Heatmap, etc.).
  - `/agentia`: interfaz del **Marvel Agent** (chat técnico, inicialmente simulado).

---

## 2. Arquitectura técnica unificada

### 2.1 App principal (PHP)

- **Routing principal**:
  - `Src\Shared\Http\Router` y `PageController` mapean rutas como `/`, `/albums`, `/heroes`, `/comic`, `/seccion`, `/agentia`, etc., y cargan vistas en `views/*.php`.

- **Vistas principales** (ejemplos):
  - `views/albums.php`: listado y gestión de álbumes.
  - `views/heroes.php`: listado de héroes.
  - `views/comic.php`: interfaz para generación de cómics.
  - `views/seccion.php`: **Secret Room**, panel técnico (SonarCloud, Sentry, Heatmap, GitHub PRs, accesibilidad, performance, repo, etc.).
  - `views/agentia.php`: interfaz del **Marvel Agent**:
    - Cabecera alineada al estilo de Secret Room.
    - Panel de chat con mensajes simulados.
    - Panel lateral con ejemplos de preguntas y explicación del agente.

- **Frontend**:
  - CSS en `public/assets/css/*`, con estilos reutilizables para tarjetas, botones y grids.
  - JS específico por página:
    - `comic.js` (cómic/RAG),
    - scripts de heatmap, Secret Room, etc.
    - `agentia.js` (chat simulado, sin backend real).

- **APIs/proxies internos**:
  - Scripts PHP en `public/api/` (ej. heatmap, accesibilidad, performance, GitHub, Sentry, Sonar, TTS).
  - Objetivo: ocultar credenciales y normalizar payloads antes de llamar a servicios externos o microservicios.

### 2.2 Microservicio `rag-service` (RAG de héroes)

- **Responsabilidad**: comparar héroes usando RAG ligero y devolver una respuesta generada por LLM con contexto.
- **Endpoint**: `POST /rag/heroes`.
- **Request típico**:
  ```json
  {
    "heroIds": ["ironman", "captain-america"],
    "question": "¿En qué se parecen y en qué se diferencian?"
  }
  ```
- **Response típico**:
  ```json
  {
    "answer": "Respuesta generada por el LLM...",
    "contexts": [
      { "heroId": "ironman", "nombre": "Iron Man", "contenido": "...", "score": 0.92 },
      { "heroId": "captain-america", "nombre": "Captain America", "contenido": "...", "score": 0.89 }
    ],
    "heroIds": ["ironman", "captain-america"]
  }
  ```

- **Casos de uso / servicios**:
  - `HeroRagService::compare(array $heroIds, ?string $question)`: valida heroIds/pregunta, recupera contextos, construye prompt narrativo y llama a `openai-service`.

- **Knowledge base**:
  - `storage/knowledge/heroes.json` (heroId, nombre, contenido).
  - Embeddings opcionales en `storage/embeddings/heroes.json`.

- **Retrievers**:
  - Léxico (default): `HeroRetriever` (bolsa de palabras + coseno).
  - Vectorial (opcional): `VectorHeroRetriever` usa embeddings precalculados y cae al léxico si faltan vectores o config.

- **Cliente hacia OpenAI**:
  - `OpenAiHttpClient` llama al microservicio `openai-service` (nunca directo a OpenAI).

- **Configuración relevante** (`rag-service/.env`):
  - `OPENAI_SERVICE_URL`
  - `RAG_USE_EMBEDDINGS`
  - `RAG_EMBEDDINGS_AUTOREFRESH`

- **Tests**: suite propia en `rag-service/tests/` con `phpunit.xml`.

### 2.3 Microservicio `openai-service` (fachada OpenAI)

- **Responsabilidad**: ofrecer un endpoint HTTP controlado hacia OpenAI.
- **Endpoint**: `POST /v1/chat`.
- **Request típico**:
  ```json
  {
    "messages": [
      { "role": "system", "content": "Instrucciones..." },
      { "role": "user", "content": "Pregunta del usuario..." }
    ]
  }
  ```
- **Response típico**:
  ```json
  { "ok": true, "content": "Texto generado por el modelo" }
  ```
  o `{ "ok": false, "error": "Mensaje de error" }`.

- **Flujo interno**:
  - `public/index.php` → `Http\Router` → `OpenAIController` → `Application\UseCase\GenerateContent` → `Infrastructure\Client\OpenAiClient` (Guzzle).
  - `GenerateContent` limpia fences de código y genera fallback JSON en caso de fallo.

- **Configuración** (`openai-service/.env`):
  - `OPENAI_API_KEY` (obligatorio),
  - `OPENAI_API_BASE` (default `https://api.openai.com/v1`),
  - `OPENAI_MODEL` (default `gpt-4o-mini`),
  - `ALLOWED_ORIGINS` para CORS.

- **Tests**: suite propia en `openai-service/tests/` con `phpunit.xml`.

### 2.4 Microservicio `heatmap-service` (clics y analítica)

- **Responsabilidad**: registrar clics y exponer eventos/visualización de heatmap.
- **Tecnología**: Python + Flask + SQLite, dockerizado (VM).
- **Endpoints externos**: `GET /`, `GET /health`, `POST /track` (X-API-Token), `GET /events` (filtros).
- **Integración PHP**: proxies en `public/api/heatmap/*.php` inyectan `HEATMAP_API_TOKEN` y usan `HEATMAP_API_BASE_URL`. Panel en `/secret-heatmap`.

### 2.5 Flujos clave

- **Generación de cómic**: frontend en `/comic` → `POST /comics/generate` (app principal) → `openai-service /v1/chat` → respuesta en vista.
- **Comparación RAG**: frontend → `POST /rag/heroes` → retriever léxico/vectorial → `openai-service /v1/chat` → respuesta + contextos.
- **Marvel Agent (/agentia)**: solo frontend; chat simulado con JS placeholder. El botón en Secret Room apunta a `/agentia`.

### 2.6 Infraestructura, despliegue y hosting de microservicios

- **Mapa de despliegue (local)**:
  - App principal (PHP): `localhost:8080` (también `docker-compose.yml` expone 8080).
  - `openai-service` (PHP): `localhost:8081`, endpoint `POST /v1/chat`.
  - `rag-service` (PHP): `localhost:8082`, endpoints `POST /rag/heroes` y `POST /rag/agent`.
  - `heatmap-service` (Python/Flask): según docs, expuesto en `http://34.74.102.123:8080` (VM). Endpoints `/`, `/health`, `POST /track`, `GET /events`.
- **Mapa de despliegue (hosting/producción)**:
  - App principal: dominio principal indicado en `.env` (`APP_PUBLIC_URL`), hosting PHP.
  - `openai-service`: subdominio documentado en bootstrap como fallback hosting: `https://openai-service.contenido.creawebes.com/v1/chat` si no hay `OPENAI_SERVICE_URL`.
  - `rag-service`: subdominio para hosting: `https://rag-service.contenido.creawebes.com` (en bootstrap se detecta host) con rutas `/rag/heroes` y `/rag/agent`.
  - `heatmap-service`: dockerizado en VM (Google Cloud, según docs) accesible en `http://34.74.102.123:8080`.
- **Docker e imágenes**:
  - `docker-compose.yml` (raíz): levanta la app principal PHP con servidor embebido en 8080.
  - `heatmap-service`: tiene Dockerfile; se construye como imagen `heatmap-service` y se ejecuta en contenedor (`docker run -p 8080:8080 ...`). Volumen montado para `heatmap.db`.
  - No hay Dockerfiles visibles para `rag-service` u `openai-service` en el repo; se sirven con `php -S` en local/hosting.
- **Subdominios y hosting**:
  - Los microservicios PHP resuelven su URL vía `.env` (`OPENAI_SERVICE_URL`, `RAG_SERVICE_URL`), con fallback a subdominios `openai-service.contenido.creawebes.com` y `rag-service.contenido.creawebes.com` cuando están en hosting.
  - La app principal consume microservicios vía HTTP; proxies PHP (`public/api/*.php`) ocultan tokens y orquestan llamadas externas (heatmap, etc.).
- **Entornos (resumen)**:
  - Local: App 8080 / `openai-service` 8081 / `rag-service` 8082 / heatmap (VM 34.74.102.123:8080 o localhost si se levanta).
  - Producción: App en hosting PHP (`APP_PUBLIC_URL`), `openai-service` y `rag-service` en subdominios configurados por `.env`/bootstrap, heatmap en VM Docker (IP/URL documentada).
- **RAG-service compartido**:
  - Un único `rag-service` expone dos endpoints:
    - `/rag/heroes` para comparación de héroes.
    - `/rag/agent` para preguntas técnicas usando la memoria maestra (`storage/marvel_agent_kb.json`).
  - Ambos flujos reutilizan el mismo cliente hacia `openai-service`.

---

## 3. Calidad, CI y auditorías

- **Pruebas**:
  - App principal: `phpunit.xml.dist`.
  - Microservicios: `rag-service/phpunit.xml`, `openai-service/phpunit.xml`.
  - Comandos típicos:
    - `vendor/bin/phpunit`
    - `cd rag-service && ../vendor/bin/phpunit`
    - `cd openai-service && ../vendor/bin/phpunit`

- **PHPStan**: `vendor/bin/phpstan analyse --memory-limit=512M` (config en `phpstan.neon`, excluye `src/Dev`).

- **QA frontend**: Playwright (`playwright.config.cjs`), Pa11y (`pa11y-all.sh`), Lighthouse (`lighthouserc.json`), SonarCloud (ver ADR-003).

- **CI/CD (GitHub Actions)**: jobs de PHPUnit, PHPStan, Pa11y, Lighthouse, Playwright, SonarCloud; deploy/rollback FTP según pipelines definidos.

---

## 4. Documentación y fuentes de conocimiento

- **Índice general**: `docs/README.md`.
- **Arquitectura**: `docs/ARCHITECTURE.md`; ADRs en `docs/architecture/ADR-*.md`.
- **APIs**: `docs/API_REFERENCE.md` (usa endpoints reales: `/comics/generate`, `/rag/heroes`, `/v1/chat`, proxies heatmap).
- **Microservicios**:
  - `rag-service/README.md`, `rag-service/doc/*`.
  - `openai-service/doc/*`.
  - `docs/microservicioheatmap/README.md`.
- **Guías y otros**: `docs/guides/getting-started.md`, `docs/guides/testing.md`, `docs/guides/authentication.md`, `docs/USE_CASES.md`, `docs/ROADMAP.md`, `docs/TASKS_AUTOMATION.md`.

---

## 5. Reglas para el futuro Marvel Agent

1. **Fuentes permitidas**: este archivo, la documentación listada y el código real. Si hay conflicto, prevalece el código.
2. **No inventar**: no crear endpoints ni microservicios inexistentes. Si falta información, indicarlo.
3. **Citar**: mencionar el archivo de soporte cuando aplique (ej.: `docs/ARCHITECTURE.md`, `rag-service/README.md`).
4. **Endpoints reales**: `/v1/chat` (openai-service), `/rag/heroes` (rag-service), proxies heatmap (`/api/heatmap/*.php`), rutas HTML según `PageController`.
5. **Configuración**: hablar solo de variables que estén en `.env` o en código (`OPENAI_API_KEY`, `OPENAI_SERVICE_URL`, `RAG_USE_EMBEDDINGS`, `HEATMAP_API_*`, etc.).
6. **Estado del agente**: `/agentia` es frontend con respuestas simuladas; el backend RAG/LLM se añadirá después usando esta memoria.
7. **Infra y despliegue**: el agente puede responder dónde corren microservicios (subdominios, hosting, VM/Google Cloud) y qué está dockerizado, solo si está documentado aquí, en los docs listados o en configuración real. Si falta info, debe decir que no está documentado.

---

> Cuando se actualice esta memoria, es necesario regenerar la KB ejecutando:  
> `cd rag-service && php bin/build_marvel_agent_kb.php`
