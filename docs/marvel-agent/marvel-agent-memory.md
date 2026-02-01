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
  - `rag-service`: comparación de héroes mediante RAG ligero y agente técnico.
  - `openai-service`: fachada HTTP a la API de OpenAI para chat/contenido.
  - `heatmap-service`: tracking y visualización de clics en la web Marvel.

- **Secciones clave de la app**:
  - `/albums`, `/heroes`: navegación principal de contenido Marvel.
  - `/comic`: generación de historias/cómics usando OpenAI.
  - `/seccion` (Secret Room): panel central técnico (Sonar, Sentry, Heatmap, etc.).
  - `/agentia`: interfaz del **Marvel Agent** (chat técnico conectado al microservicio RAG).
  - `/presentation`: presentación interactiva del TFM con métricas en tiempo real.

- **Entornos y Despliegue**:
  - **Local**: para desarrollo y pruebas rápidas.
  - **Staging**: entorno de pruebas pre-producción (`staging` subdominio) sincronizado automáticamente con `main`.
  - **Producción**: entorno final estable optimizado para el usuario.
  - **Estrategia de Mirroring (entorno espejo)**: garantiza que staging y producción compartan la misma estructura y lógica, validando cambios antes del despliegue final.

En la práctica, Clean Marvel Album actúa como un sandbox técnico y proyecto de máster: combina generación creativa (cómics), paneles de observabilidad y microservicios IA. La elección de Clean Architecture permite que los microservicios cambien o evolucionen sin alterar la lógica de dominio, y facilita probar y desplegar en hosting o cloud con solo ajustar configuración y URLs.
También funciona como un “laboratorio” para prácticas de CI/CD y observabilidad: el monolito orquesta paneles (Sonar, Sentry, GitHub, accesibilidad, performance) y expone proxies seguros que aíslan tokens y dependencias externas. Desde la v1.3.0, cuenta con un flujo de **Semantic Release** que automatiza el versionado y el despliegue profesional.


---

## 2. Arquitectura técnica unificada

Mapa mental: la app principal PHP sirve vistas y proxies internos; delega en microservicios IA (`openai-service` y `rag-service`) y en servicios externos (heatmap, accesibilidad, performance). La calidad se asegura con PHPUnit/PHPStan + QA frontend. El despliegue se mueve de local a hosting ajustando únicamente variables de entorno y subdominios, manteniendo el mismo código.
Todo convive con automatizaciones externas (n8n) que alimentan contenido (último video de Marvel en YouTube) a través de un endpoint PHP seguro, demostrando cómo integrar servicios SaaS sin acoplarlos al dominio.

### 2.1 App principal (PHP)

- **Routing principal**:
  - `App\Shared\Http\Router` y `PageController` mapean rutas como `/`, `/albums`, `/heroes`, `/comic`, `/seccion`, `/agentia`, etc., y cargan vistas en `views/*.php`.

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

En la práctica, la app principal es el “hub” de navegación: renderiza vistas, lanza fetch a los proxies para mantener tokens a salvo y orquesta la UX (cómic, paneles de calidad, Secret Room, Marvel Agent). Esto permite a un desarrollador cambiar servicios sin tocar el frontend, siempre que respete los contratos de los proxies y los microservicios.
También expone proxies para APIs clave: `/api/github-activity.php` (PRs), `/api/sonar-metrics.php` (calidad), `/api/performance-marvel.php` (PageSpeed), `/api/accessibility-marvel.php` (WAVE), `/api/heatmap/*` (tracking), `/api/tts-elevenlabs.php` (TTS) y `/api/marvel-agent.php` (puerta de entrada al agente).

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
- **RAG (principios aplicados)**:
  - Separación de conocimiento (JSON/embeddings) y lógica de recuperación.
  - Cliente LLM desacoplado (`openai-service`) y configurable por `.env`.
  - Fallback seguro: si faltan embeddings o falla el vectorial, usa el retriever léxico.
  - Dos flujos en el mismo microservicio:
    - `/rag/heroes`: comparación de héroes con KB de héroes (`storage/knowledge/heroes.json`, embeddings opcionales en `storage/embeddings/heroes.json`).
    - `/rag/agent`: RAG técnico de Marvel Agent con KB propia (`storage/marvel_agent_kb.json`, embeddings opcionales en `storage/marvel_agent_embeddings.json`).
    - Ambos comparten cliente LLM y configuración, con KBs independientes.
    - Estado actual: el flujo de héroes opera en modo léxico (no hay `storage/embeddings/heroes.json` generado). El flujo del Marvel Agent es un RAG completo: embeddings generados (`storage/marvel_agent_embeddings.json`), `RAG_USE_EMBEDDINGS=1` activo y recuperación vectorial por defecto, con fallback léxico solo si faltaran vectores.
    - Para activar vectorial en héroes: generar `storage/embeddings/heroes.json` con embeddings y mantener `RAG_USE_EMBEDDINGS=1`; hasta entonces, la comparación de héroes se resuelve con el retriever léxico.
  - Generación de embeddings offline (scripts en `bin/`) y uso opt-in vía flags de entorno para no romper despliegues.

- **Cliente hacia OpenAI**:
  - `OpenAiHttpClient` llama al microservicio `openai-service` (nunca directo a OpenAI).

- **Configuración relevante** (`rag-service/.env`):
  - `OPENAI_SERVICE_URL`
  - `RAG_USE_EMBEDDINGS`
  - `RAG_EMBEDDINGS_AUTOREFRESH`
- **Estado actual (local)**:
  - Embeddings del Marvel Agent generados en `rag-service/storage/marvel_agent_embeddings.json`.
  - `RAG_USE_EMBEDDINGS=1`, modo vectorial activo por defecto; si faltan vectores, cae al retriever léxico.

- **Tests**: suite propia en `rag-service/tests/` con `phpunit.xml`.

**Mantenimiento rápido:** cada vez que agregues memoria en el markdown maestro, regenera KB y embeddings exportando `OPENAI_API_KEY` en la consola y ejecutando: `cd rag-service && ./bin/refresh_marvel_agent.sh`.

En la práctica, `rag-service` opera como doble RAG: uno para comparar héroes y otro (Marvel Agent) para responder preguntas técnicas sobre el propio proyecto. Ambos reutilizan el mismo cliente LLM y se configuran vía `.env`, lo que facilita moverlos entre local y hosting. El flujo típico: el frontend llama a `/rag/heroes` o `/rag/agent`, el servicio recupera contexto (KB de héroes o KB del agente), construye prompt, delega en `openai-service` y devuelve `answer + contexts`.

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

En la práctica, `openai-service` es una capa de seguridad y desac acoplamiento: centraliza la API key y el modelo, aplica CORS y valida payloads antes de hablar con OpenAI. Esto permite cambiar de modelo o endpoint sin tocar el resto del sistema y registrar fallos de forma controlada.

### 2.4 Microservicio `heatmap-service` (clics y analítica)

- **Responsabilidad**: registrar clics y exponer eventos/visualización de heatmap.
- **Tecnología**: Python + Flask + SQLite, dockerizado (VM).
- **Endpoints externos**: `GET /`, `GET /health`, `POST /track` (X-API-Token), `GET /events` (filtros).
- **Integración PHP**: proxies en `public/api/heatmap/*.php` inyectan `HEATMAP_API_TOKEN` y usan `HEATMAP_API_BASE_URL`. Panel en `/secret-heatmap`.

En la práctica, el heatmap registra clics desde todas las vistas y los muestra en un panel técnico. Al estar dockerizado en una VM, se puede refrescar la imagen o mover la instancia sin tocar el backend PHP, siempre que se actualice `HEATMAP_API_BASE_URL` y el token.

### 2.5 Flujos clave

- **Generación de cómic**: frontend en `/comic` → `POST /comics/generate` (app principal) → `openai-service /v1/chat` → respuesta en vista.
- **Comparación RAG**: frontend → `POST /rag/heroes` → retriever léxico/vectorial → `openai-service /v1/chat` → respuesta + contextos.
- **Marvel Agent (/agentia)**: solo frontend; chat simulado con JS placeholder. El botón en Secret Room apunta a `/agentia`.

Un flujo típico de cómic: el usuario en `/comic` envía héroes + prompt → la app llama a `/comics/generate` → `openai-service` responde → se renderiza el texto y opcionalmente se narra con TTS. Para RAG de héroes, el frontend envía `heroIds` a `/rag/heroes`, recibe `answer + contexts` y los muestra con audio si está habilitado. Para Marvel Agent, el frontend envía `question` a `/rag/agent` (vía proxy `/api/marvel-agent.php`) y recibe una respuesta técnica basada en la memoria maestra.
Automatización n8n: un workflow (`n8n/Daily Marvel YouTube Video Fetcher and Backend Sync.json`) ejecuta un trigger horario, consulta el último video de Marvel en YouTube (`https://www.googleapis.com/youtube/v3/search` con `GOOGLE_YT_API_KEY`) y lo envía al backend PHP (`/api/actualizar-video-marvel.php`) con Authorization Bearer `MARVEL_UPDATE_TOKEN`. Así se refresca contenido sin tocar el core.

### 2.6 Infraestructura, despliegue y hosting de microservicios

Clean Marvel Album está pensado para moverse entre local y producción sin cambiar código: basta con ajustar URLs de servicio y flags en `.env`. Así, el mismo código sirve tanto en localhost como en hosting, puede orquestarse en Kubernetes y los microservicios pueden vivir en subdominios o en una VM dockerizada.

- **Mapa de despliegue (local)**:
  - App principal (PHP): `localhost:8080` (también `docker-compose.yml` expone 8080). El dev levanta `php -S ...` o el contenedor de compose.
  - `openai-service` (PHP): `localhost:8081`, endpoint `POST /v1/chat`.
  - `rag-service` (PHP): `localhost:8082`, endpoints `POST /rag/heroes` y `POST /rag/agent`.
  - `heatmap-service` (Python/Flask): según docs, expuesto en `http://34.74.102.123:8080` (VM). Endpoints `/`, `/health`, `POST /track`, `GET /events`. En local se podría apuntar a esa IP o levantar uno propio con Docker.
- **Mapa de despliegue (hosting/producción)**:
  - App principal: dominio principal indicado en `.env` (`APP_PUBLIC_URL`), hosting PHP.
  - `openai-service`: subdominio documentado en bootstrap como fallback hosting: `https://openai-service.contenido.creawebes.com/v1/chat` si no hay `OPENAI_SERVICE_URL`.
  - `rag-service`: subdominio para hosting: `https://rag-service.contenido.creawebes.com` (bootstrap detecta host) con rutas `/rag/heroes` y `/rag/agent`.
  - `heatmap-service`: dockerizado en VM (Google Cloud, según docs) accesible en `http://34.74.102.123:8080`.
- **Docker e imágenes**:
  - `docker-compose.yml` (raíz): levanta la app principal PHP con servidor embebido en 8080 para desarrollo rápido.
  - App principal: no hay `Dockerfile` en la raíz del repositorio. En `k8s/DEPLOY_K8S.md` hay un Dockerfile de referencia para construir una imagen (no versionado).
  - `openai-service` y `rag-service`: incluyen `Dockerfile` en cada carpeta. Las imágenes referenciadas en manifiestos son ejemplos y deben ajustarse al registry del entorno.
  - `heatmap-service`: microservicio externo (fuera de este repositorio); su Dockerfile se documenta en `docs/microservicioheatmap/README.md`.
- **Kubernetes (nuevo)**:
  - Manifiestos en `k8s/*.yaml` definen Deployments (2 réplicas) + Services `ClusterIP` para `clean-marvel`, `rag-service`, `openai-service` y un Ingress NGINX con host `clean-marvel.local`.
  - Ingress: `/` apunta a la app principal; `/api/rag/*` se reescribe a `/rag/$2` hacia `rag-service`; `/api/openai/*` se reescribe a `/$2` hacia `openai-service` (puerto 8081).
  - ConfigMaps: `clean-marvel-config`, `rag-service-config`, `openai-service-config` (URLs internas, flags como `RAG_USE_EMBEDDINGS=0`, CORS y voz ElevenLabs). Secrets `clean-marvel-secrets` agrupa `INTERNAL_API_KEY`, `OPENAI_API_KEY`, `ELEVENLABS_API_KEY` y tokens externos con placeholders `CHANGEME`.
  - Pipeline: flujo sugerido (documentación). No implica que exista un pipeline de build/push configurado para Kubernetes en este repositorio.
  - Debug: `kubectl port-forward svc/clean-marvel 8080:80`, `svc/rag-service 8082:80`, `svc/openai-service 8081:8081`. Host Ingress `clean-marvel.local` es placeholder; ajustar DNS/TLS reales.
- **Subdominios y hosting**:
  - Los microservicios PHP resuelven su URL vía `.env` (`OPENAI_SERVICE_URL`, `RAG_SERVICE_URL`), con fallback a subdominios `openai-service.contenido.creawebes.com` y `rag-service.contenido.creawebes.com` cuando están en hosting.
  - La app principal consume microservicios vía HTTP; proxies PHP (`public/api/*.php`) ocultan tokens y orquestan llamadas externas (heatmap, etc.).
- **Automatización externa (n8n)**:
  - Workflow programado que consulta la API de YouTube y envía el último video Marvel al endpoint PHP `/api/actualizar-video-marvel.php` con Authorization Bearer (`MARVEL_UPDATE_TOKEN`). Usa `GOOGLE_YT_API_KEY` y se ejecuta de forma recurrente.
- **Entornos (resumen)**:
  - Local: App 8080 / `openai-service` 8081 / `rag-service` 8082 / heatmap (VM 34.74.102.123:8080 o localhost si se levanta).
  - Producción: App en hosting PHP (`APP_PUBLIC_URL`), `openai-service` y `rag-service` en subdominios configurados por `.env`/bootstrap, heatmap en VM Docker (IP/URL documentada).
- **RAG-service compartido**:
  - Un único `rag-service` expone dos endpoints:
    - `/rag/heroes` para comparación de héroes con KB de héroes.
    - `/rag/agent` para preguntas técnicas usando la memoria maestra (`storage/marvel_agent_kb.json`).
  - Ambos flujos reutilizan el mismo cliente hacia `openai-service` y se benefician de embeddings opcionales, con fallback léxico.

- **Integridad del Dominio (DDD)**:
  - Se han implementado **Value Objects** (`HeroId`, `AlbumId`) para garantizar la integridad de los datos en toda la aplicación.
  - El sistema utiliza tipado estricto y validaciones de dominio para evitar estados inconsistentes.

- **Flujo de Ingeniería Profesional**:
  - **Versionado Semántico**: Uso de `standard-version` para gestionar etiquetas de Git y el CHANGELOG automáticamente.
  - **CI/CD Robusto**: GitHub Actions ejecuta tests unitarios (PHPUnit), análisis estático (PHPStan nivel 7) y auditorías de seguridad en cada commit.
  - **Mirroring Staging**: Existe una rama `staging` que se despliega automáticamente para validación interna antes de pasar a `main`.


### 2.7 Seguridad (v1.3.2 - HMAC & Mobile Key)

**Estado actual (académico)**: controles de hardening activos (CSP con nonce en `script-src`, CSRF, rate limit, sesión) con tests automatizados y guía de verificación.

- **HMAC Interno**: Las comunicaciones entre la App y los microservicios están firmadas con HMAC usando una `INTERNAL_API_KEY`.
- **X-Mobile-Key**: Se ha añadido una cabecera de seguridad obligatoria (`X-Mobile-Key`) para endpoints sensibles, permitiendo el acceso controlado desde aplicaciones externas o móviles sin comprometer la sesión web.


#### Content Security Policy (CSP) con Nonces Dinámicos

- **Implementación**: CSP estricta eliminando `'unsafe-inline'` de `script-src`.
- **Nonces criptográficos**: 128 bits de entropía, únicos por request.
- **Generador**: `src/Security/Http/CspNonceGenerator.php` usa `random_bytes(16)` + base64.
- **Integración**:
  - `src/bootstrap.php`: genera nonce, lo guarda en `$_SERVER['CSP_NONCE']` y lo pasa a `SecurityHeaders::apply($nonce)`.
  - `public/index.php`: mismo flujo para la página de intro.
  - Vistas (`views/layouts/header.php`, `public/index.php`): scripts tienen atributo `nonce="..."`.

#### Headers CSP Actuales

```
script-src 'self' 'nonce-XXXXX...' https://cdn.tailwindcss.com https://cdn.jsdelivr.net
style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.tailwindcss.com
```

**Nota**: `style-src` mantiene `'unsafe-inline'` por requisito de Tailwind CDN (inyecta estilos dinámicamente). Esto NO compromete la protección XSS, que es el objetivo principal de CSP.

#### Otras Medidas de Seguridad

- **CSRF Protection**: Tokens únicos por sesión, validados en `CsrfMiddleware`.
- **Rate Limiting**: 100 requests/minuto por IP+ruta (`RateLimiter`, `RateLimitMiddleware`).
- **Session Security**: 
  - Validación de IP y User-Agent (`SessionIntegrity`).
  - TTL de sesión (`SessionTtl`).
  - Detección de replay attacks (`SessionReplayMonitor`).
- **Input Sanitization**: `Sanitizer` elimina tags peligrosos (`<script>`, `onerror`, etc.).
- **Security Headers**: HSTS, X-Frame-Options, X-Content-Type-Options, Referrer-Policy, etc.
- **HMAC**: Autenticación de microservicios con `HmacService`.

#### Testing de Seguridad

- Tests de CSP/CSRF/rate limit/sesión/sanitización en `tests/Security/`
- Verificación manual guiada en `docs/security/security_verification.md`

#### Documentación de Seguridad

- `docs/security/security.md`: medidas completas de seguridad
- `docs/security/security_verification.md`: guía de verificación con 10 pruebas prácticas
- `docs/security/README.md`: índice y quick start

#### Verificación Rápida

```bash
# Ver headers CSP
curl -I http://localhost:8080/ | grep -i content-security-policy

# Verificar nonces únicos (ejecutar 3 veces)
curl -I http://localhost:8080/ 2>&1 | grep -o "nonce-[^']*"

# Tests de seguridad
vendor/bin/phpunit tests/Security/ --testdox
```

---

## 3. Calidad, CI y auditorías

- **Pruebas**:
  - App principal: `vendor/bin/phpunit` (suite completa; ver salida de PHPUnit)
  - Microservicios: `rag-service/phpunit.xml`, `openai-service/phpunit.xml`

- **PHPStan**: `vendor/bin/phpstan analyse --memory-limit=1G` (config en `phpstan.neon`, nivel 7, excluye `src/Dev`; bloque `ignoreErrors` comentado).

- **QA frontend**: Playwright (`playwright.config.cjs`), Pa11y (`bin/pa11y-all.sh`), Lighthouse (`lighthouserc.json`), SonarCloud (ver ADR-003).

- **CI/CD (GitHub Actions)**: jobs de PHPUnit, PHPStan, Pa11y, Lighthouse, Playwright, SonarCloud; deploy/rollback FTP según pipelines definidos. Implementa **Semantic Release** para versionado automático (v1.3.x).

- **Seguridad**: CSP con nonce en `script-src` (v1.2.0), CSRF, rate limiting, session security, y autenticación por cabecera `X-Mobile-Key` (v1.3.0).


---

En conjunto, PHPUnit y PHPStan permiten verificar backend; Playwright, Pa11y y Lighthouse cubren flujos de UI y auditorías de navegador; SonarCloud centraliza métricas y reportes según su configuración.

## 4. Documentación y fuentes de conocimiento

- **Índice general**: `docs/README.md` (reorganizado v1.2.0).
- **Arquitectura**: `docs/architecture/ARCHITECTURE.md`; ADRs en `docs/architecture/ADR-*.md`.
- **Seguridad** (nuevo): `docs/security/` con `security.md`, `security_verification.md` y README.
- **APIs**: `docs/api/API_REFERENCE.md` (usa endpoints reales: `/comics/generate`, `/rag/heroes`, `/v1/chat`, proxies heatmap).
- **Microservicios**:
  - `rag-service/README.md`, `rag-service/doc/*`.
  - `openai-service/doc/*`.
  - `docs/microservicioheatmap/README.md`.
- **Gestión de proyecto**: `docs/project-management/` con CHANGELOG.md (v1.2.0), ROADMAP.md, CONTRIBUTING.md, TASKS_AUTOMATION.md.
- **Desarrollo**: `docs/development/` con agent.md, analisis_estructura.md.
- **Guías**: `docs/guides/getting-started.md`, `docs/guides/testing.md`, `docs/guides/authentication.md`.
- **Otros**: `docs/architecture/USE_CASES.md`, `docs/architecture/REQUIREMENTS.md`, `docs/deployment/deploy.md`, `k8s/DEPLOY_K8S.md` (guía de despliegue en Kubernetes y manifiestos en `k8s/`).

---

Un desarrollador nuevo debería empezar por `docs/README.md` para el índice, leer `docs/architecture/ARCHITECTURE.md` y los ADRs para decisiones clave, luego revisar `docs/api/API_REFERENCE.md` y los README de cada microservicio. Las guías de testing y automatización explican cómo ejecutar suites y scripts.

## 5. Reglas para el futuro Marvel Agent

1. **Fuentes permitidas**: este archivo, la documentación listada y el código real. Si hay conflicto, prevalece el código.
2. **No inventar**: no crear endpoints ni microservicios inexistentes. Si falta información, indicarlo.
3. **Citar**: mencionar el archivo de soporte cuando aplique (ej.: `docs/architecture/ARCHITECTURE.md`, `rag-service/README.md`).
4. **Endpoints reales**: `/v1/chat` (openai-service), `/rag/heroes` (rag-service), proxies heatmap (`/api/heatmap/*.php`), rutas HTML según `PageController`.
5. **Configuración**: hablar solo de variables que estén en `.env` o en código (`OPENAI_API_KEY`, `OPENAI_SERVICE_URL`, `RAG_USE_EMBEDDINGS`, `HEATMAP_API_*`, etc.).
6. **Estado del agente**: `/agentia` es frontend con respuestas simuladas; el backend RAG/LLM se añadirá después usando esta memoria.
7. **Infra y despliegue**: el agente puede responder dónde corren microservicios (subdominios, hosting, VM/Google Cloud) y qué está dockerizado, solo si está documentado aquí, en los docs listados o en configuración real. Si falta info, debe decir que no está documentado.
8. **Respuestas explicativas**: debe priorizar respuestas técnicas y explicativas (no solo bullets), usando la info de infraestructura y microservicios para ubicar dónde corre cada componente.

---

### 6. Ejemplos de respuestas del Marvel Agent

- **Pregunta:** “¿Tiene arquitectura clean?”  
  **Respuesta ejemplo:** Sí. La app principal sigue Clean Architecture: Presentación (router/vistas) → Aplicación (casos de uso) → Dominio (entidades/contratos) → Infraestructura (repos, clientes externos). Las dependencias fluyen hacia el dominio, lo que permite cambiar microservicios o proveedores sin romper la lógica central.

- **Pregunta:** “¿Dónde están desplegados los microservicios?”  
  **Respuesta ejemplo:** En local: app en `localhost:8080`, `openai-service` en `8081`, `rag-service` en `8082`. En hosting: la app vive en el dominio principal (`APP_PUBLIC_URL`) y los microservicios PHP se exponen en subdominios (`openai-service.contenido.creawebes.com`, `rag-service.contenido.creawebes.com`). El heatmap está dockerizado en una VM accesible en `http://34.74.102.123:8080`.

- **Pregunta:** “¿Qué hace el microservicio de heatmap?”  
  **Respuesta ejemplo:** Es un servicio Python/Flask con SQLite que registra clics de la web. Exponen `/`, `/health`, `POST /track` y `GET /events`. Está dockerizado en una VM (Google Cloud) y se consume desde la app PHP mediante proxies que inyectan el token `HEATMAP_API_TOKEN`.

- **Pregunta:** “¿Cuál es la diferencia entre /rag/heroes y /rag/agent?”  
  **Respuesta ejemplo:** Ambos viven en el mismo `rag-service`. `/rag/heroes` compara dos héroes usando la KB de héroes (JSON + embeddings opcionales). `/rag/agent` responde preguntas técnicas usando la memoria maestra del proyecto (otra KB). Comparten cliente LLM (`openai-service`) y configuración, con fallback léxico si no hay embeddings.

- **Pregunta:** “¿Qué automatizaciones externas usa el proyecto?”  
  **Respuesta ejemplo:** Hay un workflow n8n (`Daily Marvel YouTube Video Fetcher and Backend Sync.json`) que consulta la API de YouTube con `GOOGLE_YT_API_KEY` y envía el último video al endpoint PHP `/api/actualizar-video-marvel.php` con autorización Bearer (`MARVEL_UPDATE_TOKEN`). Así se refrescan contenidos sin tocar el dominio.

### Fase 5 — Tests rápidos de seguridad
- Existe un grupo `@group security` en PHPUnit para chequear rápidamente CSRF, sanitización del RAG y rate-limit básico por IP+path.
- Se ejecuta con: `vendor/bin/phpunit --group security` o desde VS Code con la tarea “Run Security Tests”.
- Cobertura mínima:
  - CSRF: tokens inválidos se rechazan.
  - Sanitización RAG: elimina `<script>`/`onerror` de preguntas y mantiene el texto útil.
  - Rate limit: bloquea después de superar el máximo de intentos configurado por IP+ruta.

---

 Regla obligatoria:
Cada vez que se actualice este archivo maestro de memoria del Marvel Agent, hay que ejecutar SIEMPRE los dos comandos de regeneración desde la raíz del proyecto:

cd rag-service && php bin/build_marvel_agent_kb.php
cd rag-service && php bin/generate_agent_embeddings.php


Solo así la KB (marvel_agent_kb.json) y los embeddings (marvel_agent_embeddings.json) quedarán alineados y el Marvel Agent usará la memoria correcta en modo vectorial.****
