# Clean Marvel Album – Documentación Técnica
![CI](https://github.com/20Luisma/marvel/actions/workflows/ci.yml/badge.svg)
![Coverage](https://sonarcloud.io/api/project_badges/measure?project=20Luisma_marvel&metric=coverage)
![Maintainability](https://sonarcloud.io/api/project_badges/measure?project=20Luisma_marvel&metric=sqale_rating)
![Pa11y](https://img.shields.io/badge/Pa11y-enabled-brightgreen)
![Playwright E2E](https://img.shields.io/badge/Playwright%20E2E-passing-brightgreen)


**Clean Marvel Album** es una implementación educativa de Arquitectura Limpia en **PHP 8.2** que orquesta un backend modular (álbumes + héroes) y dos microservicios de IA desacoplados (`openai-service`, `rag-service`). Además de servir como demo funcional, actúa como blueprint para proyectos PHP que necesiten capas bien delimitadas, pruebas automatizadas y despliegues paralelos en local y hosting.

> ✅ **Análisis completo terminado (WCAG 2.1 AA): 100% No issues found** (Pa11y) en todas las páginas públicas.

## 🎯 Objetivo

- Mostrar cómo el dominio de álbumes y héroes se mantiene independiente de los detalles web o de infraestructura.  
- Conectar la capa de aplicación con microservicios de IA vía HTTP (8081 y 8082) sin comprometer la pureza del dominio.  
- Documentar flujo, dependencias y comandos para que cualquier desarrollador pueda levantar el stack completo en minutos.

## 🧾 Componentes detectados

- `src/` con módulos por agregado (`Albums`, `Heroes`, `Notifications`, `Activities`, `AI`, `Shared`).  
- `openai-service/` y `rag-service/` como microservicios PHP 8.2 independientes con autoload PSR-4 propio y `.env` manual.  
- `storage/` con persistencias JSON y bitácoras (`albums.json`, `heroes.json`, `actividad/`, `notifications.log`).  
- `views/` (layouts, páginas y parciales) usados por `Src\Controllers\PageController`.  
- `.vscode/tasks.json` para servidores, QA y comandos git automatizados.  
- `app/Services/GithubClient.php`, `views/panel-github.php` y `public/assets/css/panel-github.css` para integrar la actividad de Pull Requests del repo Marvel vía API oficial de GitHub.  
- `docs/` con arquitectura, requerimientos, API reference, roadmap y diagramas UML (`docs/uml`).  
- `docker-compose.yml` mínimo para levantar la app principal en PHP CLI 8.2 dentro de contenedor.

## 🧠 Arquitectura general

| Capa | Ubicación | Responsabilidad |
|------|-----------|-----------------|
| **Presentation** | `public/`, `src/Controllers`, `views/`, `Src\Shared\Http\Router` | Recibe HTTP, renderiza vistas o JSON, mapea rutas a casos de uso. |
| **Application** | `src/*/Application/UseCase`, `src/AI`, `src/Dev` | Casos de uso, servicios de orquestación (OpenAIComicGenerator, Seeders, Activity log). |
| **Domain** | `src/*/Domain` (entidades, repos, eventos) | Reglas de negocio puras, contratos de repositorio, eventos. |
| **Infrastructure** | `src/*/Infrastructure`, `storage/*`, `App\Shared\Infrastructure\Bus` | Persistencia JSON, EventBus en memoria, adaptadores externos. |

```
[Browser / CLI]
      ↓
Presentation (public/, Controllers, Router, views)
      ↓
Application (UseCases, AI services, Dev tools)
      ↓
Domain (Entities, Value Objects, Events, Interfaces)
      ↓
Infrastructure (JSON repositories, EventBus, Notification adapters)
      ↓
External Services (openai-service 8081, rag-service 8082, OpenAI API)
```

`src/bootstrap.php` centraliza DI: carga `.env`, resuelve URLs desde `config/services.php`, registra repositorios de archivos, EventBus y casos de uso. `ServiceUrlProvider` detecta el entorno (local/hosting) según host o `APP_ENV` para apuntar automáticamente a los endpoints correctos.

## 🧭 Documentación unificada

- `docs/README.md`: índice maestro para toda la documentación (API, Componentes, Guías, Arquitectura).  
- `docs/api/openapi.yaml`: especificación OpenAPI que describe los endpoints principales (`/albums`, `/heroes`, `/activity/*`, `/comics/generate`).  
- `docs/components/README.md`: panorama de componentes clave, dependencias externas (OpenAI/RAG) y responsabilidades.  
- `docs/guides/`: guías accionables (`getting-started`, `authentication`, `testing`) para agilizar onboarding.  
- `docs/architecture/`: ADRs (001 a 005) con sección "Supersede ADR" para registrar decisiones futuras y cómo continuarlas.

## 💾 Persistencia de datos: JSON en local, MySQL en hosting

- En **local (`APP_ENV=local`)** se usan repositorios de archivos:  
  - `FileAlbumRepository` → `storage/albums.json`  
  - `FileHeroRepository` → `storage/heroes.json`  
  - `FileActivityLogRepository` → `storage/actividad/`
- En **hosting (`APP_ENV=hosting`)** `src/bootstrap.php` intenta abrir PDO vía `PdoConnectionFactory::fromEnvironment()` con los datos de `.env`. Si la conexión es exitosa se emplean:  
  - `DbAlbumRepository`  
  - `DbHeroRepository`  
  - `DbActivityLogRepository`
- Si PDO lanza excepción (credenciales erróneas o MySQL caído), se registra con `error_log` y se vuelve automáticamente a los repositorios JSON para no romper el arranque. Resultado: en hosting siempre se **intenta** MySQL, pero la app sigue funcionando en modo JSON como paracaídas.
- **Migración** (`bin/migrar-json-a-db.php`):  
  - Lee álbumes, héroes y actividad desde los JSON.  
  - Inserta en las tablas correspondientes, evitando duplicados en `activity_logs` comprobando existencia antes de insertar.  
  - Uso una vez creada la BD y con `.env` correcto:  
    ```bash
    php bin/migrar-json-a-db.php
    ```
  - Pensado para desarrollo sencillo en local con JSON y despliegue robusto en hosting con MySQL + fallback.

## 🗂️ Estructura del proyecto

```text
clean-marvel/
├── public/index.php              # Front Controller + Router HTTP
├── src/
│   ├── Albums|Heroes|Notifications|Activities (Domain/Application/Infra)
│   ├── AI/OpenAIComicGenerator.php
│   ├── Shared/Http (Router, Request, JsonResponse), Shared/Infrastructure (Bus)
│   ├── Config/ServiceUrlProvider.php
│   ├── Dev/Seed + Dev/Test
│   └── bootstrap.php
├── openai-service/               # Microservicio IA (POST /v1/chat)
├── rag-service/                  # Microservicio RAG (POST /rag/heroes)
├── storage/                      # JSON de datos + logs
├── tests/                        # PHPUnit (Application, Domain, Infrastructure)
├── docs/                         # Arquitectura, requerimientos, UML, roadmap
├── docker-compose.yml            # Servicio app 8080
├── composer.json / composer.lock
├── phpunit.xml.dist / phpstan.neon
└── .env                          # APP_ENV + override de OPENAI_SERVICE_URL
```

## 🐙 Panel GitHub integrado

- `views/panel-github.php` renderiza un tablero que consulta `App\Services\GithubClient` para listar Pull Requests abiertos, cerrados y mergeados del repositorio `20Luisma/marvel` (ajustable mediante las constantes `OWNER`/`REPO`).  
- El cliente hace *fan-out* contra `https://api.github.com/repos/{owner}/{repo}/pulls`, `/pulls/{number}/commits` y `/pulls/{number}/reviews` para obtener métricas de commits, reviewers únicos, labels y timestamps, devolviendo un payload homogéneo para la vista.  
- Requiere definir `GITHUB_API_KEY` en `.env` con un token personal que tenga permisos de lectura sobre el repo (scope `repo` o `public_repo`). El servicio lee el `.env` manualmente, arma los headers (`Authorization`, `User-Agent`) y maneja errores/códigos HTTP devolviendo mensajes claros en la UI.  
- El panel soporta filtros `from`/`to` (YYYY-MM-DD) y fallback inteligente: normaliza fechas, muestra advertencias cuando el token falta y conserva enlaces directos a cada PR.  
- Los estilos viven en `public/assets/css/panel-github.css` y mantienen coherencia visual con el resto del dashboard; el panel se agrega como acción superior junto a cómics, héroes y documentación.

## 🔭 Observabilidad: SonarCloud + Sentry

- **SonarCloud**  
  - Endpoint PHP: `public/api/sonar-metrics.php` consulta la API oficial (`/api/measures/component`) con `SONARCLOUD_TOKEN` y `SONARCLOUD_PROJECT_KEY`. Incluye reintentos y mensajes claros si la llamada falla.  
  - Vista: `views/pages/sonar.php` muestra métricas clave (bugs, code smells, cobertura, duplicación, complejidad, rating) y gráficos para tener una foto rápida de la calidad sin salir del proyecto.  
  - Uso: basta con configurar las variables en `.env`; el panel consume el endpoint interno `/api/sonar-metrics.php`.  
- **Sentry**  
  - Inicialización: en `src/bootstrap.php` se registra Sentry con `SENTRY_DSN` y el `APP_ENV`; captura errores y excepciones globales.  
  - Endpoint PHP: `public/api/sentry-metrics.php` consulta eventos recientes del proyecto Sentry usando `SENTRY_API_TOKEN`, `SENTRY_ORG_SLUG` y `SENTRY_PROJECT_SLUG`, con cache/fallback y reintentos.  
  - Vista: `views/pages/sentry.php` lista eventos recientes (niveles, shortId, enlaces) y permite lanzar errores de prueba desde la UI para verificar el flujo.  
- Ambos paneles se integran en la navegación superior y complementan la observabilidad: **SonarCloud** para calidad estática y **Sentry** para errores en tiempo de ejecución/operación.

## 🌡️ Heatmap de clics

- El endpoint ~~`/api/heatmap/click.php`~~ captura cada clic (page, x/y, viewport y scroll) y lo almacena en archivos mensuales (`clicks_YYYY-MM.jsonl`) con backup de logs antiguos gestionados por `HeatmapLogCleaner`.  
- `/api/heatmap/summary.php` reconstruye la matriz NxN para el heatmap y `/api/heatmap/pages.php` devuelve automáticamente las rutas detectadas, por lo que no hace falta configurar manualmente las páginas que se monitorean.  
- La Secret Room ofrece `/secret-heatmap`: canvas con el “Marvel Glow” del heatmap principal, KPIs, una leyenda cromática y gráficos Chart.js (zonas Top/Middle/Bottom + distribución vertical) para entender dónde y cuándo hacen clic los usuarios dentro del proyecto.  
- El tracker (`public/assets/js/heatmap-tracker.js`) se carga en el footer y normaliza las coordenadas `x`/`y` respecto al viewport completo (incluye scroll), así que el heatmap refleja la posición real dentro de cada página; el nuevo script `heatmap-viewer.js` pinta el canvas, actualiza los KPIs y alimenta los gráficos adicionales sin tocar la API PHP.

## ✨ Paneles adicionales

- **Accesibilidad (WAVE):** `public/api/accessibility-marvel.php` y `views/pages/panel-accessibility.php` complementan la observabilidad con métricas de errores, contrastes y alertas detectadas por la API WAVE de WebAIM; la UI emplea tarjetas, resúmenes y una tabla responsive igual que el resto de dashboards.  
- **Repo Marvel:** `public/api/github-repo-browser.php` reutiliza `App\Services\GithubClient` para mostrar carpetas/archivos del repo `20Luisma/marvel`, y la vista `views/pages/repo-marvel.php` con `public/assets/js/panel-repo-marvel.js` ofrece breadcrumb, tabla y navegación sin salir del dashboard.  
- **Performance Marvel:** `public/api/performance-marvel.php` llama a PageSpeed Insights con `PAGESPEED_API_KEY` y las rutas clave del sitio. `views/pages/performance.php` junto a `public/assets/js/panel-performance.js` pintan KPIs con tarjetas coloridas y acordiones de cuellos de botella, brindando un vistazo rápido y accionable al rendimiento.

## ♿ Accesibilidad (WAVE API)

- `public/api/accessibility-marvel.php` consume la API pública `https://wave.webaim.org/api/request` usando `WAVE_API_KEY` (configurada en `.env`). Valida cabeceras `Accept` y devuelve un resumen global con el total de errores, alertas y contraste, más un listado por página.  
- La vista `views/pages/panel-accessibility.php` presenta un hero temático, un botón “Analizar accesibilidad” y muestra tablas con los resultados por URL; el botón se desactiva mientras se ejecuta la llamada y maneja errores del API con alertas claras.  
- El panel aprovecha las mismas clases y helpers de `SonarCloud`, `Sentry` y `Panel GitHub` para mantener el mismo look & feel, y se integra en el menú superior (acción “Accesibilidad”) y en la “Secret Room”.  
- El servicio usa rutas “clave” de la aplicación (`/`, `/albums`, `/heroes`, `/movies`, `/comic`, `/sonar`, `/sentry`, `/panel-github`, `/seccion`, `/oficial-marvel`, `/readme`) cuando no se le pasa un cuerpo JSON; cada URL se analiza en serie, sumando el total de violaciones y mostrando el enlace directo al informe WAVE cuando está disponible.

## 🗂️ Repo Marvel

- `public/api/github-repo-browser.php` reutiliza `App\Services\GithubClient` para consultar `/repos/20Luisma/marvel/contents/{path}` y devuelve un listado normalizado de archivos/carpetas con enlaces `html_url`.  
- La vista `views/pages/repo-marvel.php` y el script `public/assets/js/panel-repo-marvel.js` construyen breadcrumb, tabla y estados de carga mientras navegas la repo desde Clean Marvel Album.  
- El menú superior ahora incluye el botón “Repo Marvel” y la “Secret Room” también enlaza al panel, manteniendo coherencia visual con los demás dashboards de monitoreo y observabilidad.

## 🚀 Performance Marvel

- `public/api/performance-marvel.php` llama a `https://www.googleapis.com/pagespeedonline/v5/runPagespeed` con `PAGESPEED_API_KEY`, analiza las rutas clave (`/`, `/albums`, `/heroes`, `/movies`, `/comic`, `/panel-github`, `/sonar`, `/sentry`, `/seccion`, `/oficial-marvel`, `/readme`) y devuelve un JSON consolidado con score, métricas (LCP/FCP/CLS/TBT) y oportunidades.  
- La vista `views/pages/performance.php` muestra un resumen general de los scores medios, un listado de páginas con sus métricas coloreadas y detalles colapsables de los cuellos de botella; `public/assets/js/panel-performance.js` gestiona los estados “Cargando/Error” y actualiza todo a la primera carga o al pulsar “Actualizar análisis”.  
- Agrega `PAGESPEED_API_KEY=TU_API_KEY_AQUI` al `.env` y define la acción “Performance” en el menú superior para tener visibilidad sobre rendimiento y oportunidades de mejora sin salir del dashboard.

## 🧩 Microservicios

### 🤖 openai-service (`localhost:8081`)

- **Punto de entrada:** `openai-service/public/index.php` carga `vendor/autoload.php`, parsea `.env` (sin phpdotenv) y despacha a `Creawebes\OpenAI\Http\Router`.  
- **Endpoint expuesto:** `POST /v1/chat`. El router valida CORS (`ALLOWED_ORIGINS`) y deriva a `OpenAIController` + `OpenAIChatService`.  
- **Flujo:** recibe `messages[]`, inyecta `OPENAI_API_KEY`, `OPENAI_MODEL` y consume `https://api.openai.com/v1/chat/completions`. Si OpenAI falla, responde un JSON de fallback (`ok: false`).  
- **Dependencias específicas:** `guzzlehttp/guzzle` (para futuras integraciones), `vlucas/phpdotenv` (opcional), cURL nativo.  
- **Uso desde la app:** `App\AI\OpenAIComicGenerator` llama `http://localhost:8081/v1/chat` (o URL de hosting) para generar historias estructuradas.

### 🧠 rag-service (`localhost:8082`)

- **Bootstrap:** `rag-service/src/bootstrap.php` carga `.env`, resuelve `APP_ENV`, instancia `HeroJsonKnowledgeBase` (lee `storage/knowledge/heroes.json`), `HeroRetriever` y `HeroRagService`.  
- **Endpoint:** `POST /rag/heroes` (CORS con lista blanca). Requiere exactamente dos `heroIds` para comparar.  
- **Flujo interno:**  
  1. `HeroRetriever` ordena contextos según similitud.  
  2. `HeroRagService` arma un prompt estructurado (tabla + conclusión) y lo envía al microservicio OpenAI (8081 o URL configurada).  
  3. Devuelve `{ answer, contexts, heroIds }` al frontend de Clean Marvel Album.  
- **Dependencias:** solo PHP estándar. Toda la lógica se apoya en `storage/knowledge/heroes.json`.

### 🔄 Flujo de comunicación

```
[App 8080] --POST /comics/generate--> [openai-service 8081] --→ [OpenAI API]
[App 8080] --POST /rag/heroes--> [rag-service 8082] --POST /v1/chat--> [openai-service 8081] --→ [OpenAI API]
```

`config/services.php` define hosts locales y de hosting (`*.contenido.creawebes.com`). `ServiceUrlProvider` los expone vía `/config/services` para que el frontend conozca los endpoints vigentes.

## ⚙️ Instalación

### Requisitos del entorno

- PHP **8.2+** con extensiones `curl`, `json`, `mbstring`.  
- **Composer 2.x**.  
- **Node.js 18+ / npm 9+** (opcional pero recomendable para tareas de frontend o tooling futuro).  
- Navegador moderno, y opcionalmente VS Code con las tasks incluidas.

### Pasos

```bash
git clone <repo> clean-marvel
cd clean-marvel
composer install

# Microservicios
cd openai-service && composer install && cd ..
cd rag-service   && composer install && cd ..
```

## 🚀 Ejecución (local y hosting)

### Localhost

```bash
# App principal (8080)
php -S localhost:8080 -t public

# Microservicio OpenAI (8081)
cd openai-service
php -S localhost:8081 -t public

# Microservicio RAG (8082)
cd rag-service
php -S localhost:8082 -t public
```

- **VS Code:** usar las tasks `🚀 Iniciar servidor PHP (8080)`, `🤖 Run OpenAI Service (8081)` y `▶️ Run Both (8080 + 8081)`; agregar task análoga para RAG si se desea.  
- **Docker Compose:** `docker-compose up app` levanta el servidor 8080 dentro de un contenedor PHP CLI.  
- **Endpoints de prueba:** `http://localhost:8080`, `http://localhost:8081/v1/chat`, `http://localhost:8082/rag/heroes`.

## 🔊 Narración con ElevenLabs

- **Endpoint dedicado:** `public/api/tts-elevenlabs.php` recibe `POST { text }`, inyecta `ELEVENLABS_API_KEY` (desde `.env`) y reenvía la petición a `https://api.elevenlabs.io/v1/text-to-speech/{voiceId}`.  
- **Seguridad:** la API Key sólo vive en el backend; el frontend nunca la expone. Si la variable no está configurada, el endpoint responde un error descriptivo.  
- **Uso en la UI:** las vistas del generador de cómics y de la comparación RAG muestran un botón `🔊 Escuchar...` debajo del texto. Ambos botones llaman al endpoint anterior y reproducen el audio devuelto directamente en un `<audio>` oculto.  
- **Personalización:** por defecto usamos la voz **Charlie** (`EXAVITQu4vr4xnSDxMaL`) con el modelo multilingüe `eleven_multilingual_v2`, pero puedes ajustar `ELEVENLABS_VOICE_ID`, `ELEVENLABS_MODEL_ID`, `ELEVENLABS_VOICE_STABILITY` y `ELEVENLABS_VOICE_SIMILARITY` en `.env` sin tocar el código. El payload limita el texto a 5000 caracteres para evitar rechazos en la API.

### Hosting

- Dominio app: `https://iamasterbigschool.contenido.creawebes.com`.  
- Microservicio OpenAI: `https://openai-service.contenido.creawebes.com/v1/chat`.  
- Microservicio RAG: `https://rag-service.contenido.creawebes.com/rag/heroes`.  
- `APP_ENV=auto` permite que cada servicio detecte el host y use los endpoints de hosting definidos en `config/services.php` sin tocar el código. Si se requiere forzar entorno en despliegues CI/CD, definir `APP_ENV=hosting`.

## 🔐 Variables de entorno

| Archivo | Variables | Comentario |
|---------|-----------|------------|
| `.env` (raíz) | `APP_ENV=auto`, `APP_ORIGIN`/`APP_URL`, `OPENAI_SERVICE_URL=`, `ELEVENLABS_API_KEY`, `ELEVENLABS_VOICE_ID`, `ELEVENLABS_MODEL_ID`, `ELEVENLABS_VOICE_STABILITY`, `ELEVENLABS_VOICE_SIMILARITY`, `TTS_INTERNAL_TOKEN`, `MARVEL_UPDATE_TOKEN` | `APP_ORIGIN` limita CORS, `TTS_INTERNAL_TOKEN` protege el TTS y `MARVEL_UPDATE_TOKEN` protege el webhook n8n cuando se define; si se deja vacío el endpoint acepta actualizaciones sin token, pero en despliegues públicos se recomienda enviar `Authorization: Bearer <token>`. |
| `openai-service/.env` | `APP_ENV`, `OPENAI_API_KEY`, `OPENAI_API_BASE`, `OPENAI_MODEL`, `ALLOWED_ORIGINS` | **Obligatorio** definir `OPENAI_API_KEY`. `ALLOWED_ORIGINS` sincroniza CORS con app y hosting. |
| `rag-service/.env` | `ALLOWED_ORIGINS`, `APP_ENV`, `OPENAI_SERVICE_URL` | Permite que el RAG apunte al OpenAI service apropiado y limite orígenes. |

Todos los `.env` son cargados manualmente con `file()` + `putenv()` para evitar dependencias innecesarias y mantener cada servicio autocontenible.

### 🔐 Seguridad aplicada

- **CORS restringido** con `APP_ORIGIN`/`APP_URL`; peticiones con origen distinto devuelven 403 en endpoints críticos.  
- **Tokens de protección**: `TTS_INTERNAL_TOKEN` (TTS ElevenLabs) y `MARVEL_UPDATE_TOKEN` (webhook n8n) se exigen vía `Authorization: Bearer ...` siempre que se definan; dejando el token vacío el webhook acepta peticiones sin autenticación, pero en entornos públicos se recomienda establecer uno y enviarlo desde n8n.  
- **Cabeceras**: X-Frame-Options SAMEORIGIN, X-Content-Type-Options nosniff, Referrer-Policy same-origin, Permissions-Policy mínima y CSP que permite sólo self + CDNs necesarios (Tailwind/jsdelivr/Google Fonts), YouTube para iframes y hosts de desarrollo (localhost).  
- **Logs/artefactos fuera de `public/`**: n8n escribe en `storage/marvel/` con rotación; `/api/ultimo-video-marvel.php` lee desde ahí (con fallback al JSON legacy si existe).  
- **Uploads endurecidos**: validación por extensión + MIME real (finfo) y límite 5MB para portadas.  
- **Protección de secretos**: `.htaccess` bloquea `.env` y extensiones sensibles (`ini`, `log`, `sql`, `sqlite`, `yml`, `yaml`).  
- Pendiente para subir a “alto”: tokens CSRF en formularios/POST, CSP sin `'unsafe-inline'` usando nonces/hash y limitar `connect-src` a hosts de producción en despliegue.

## 📦 Dependencias Composer

- **App principal (`composer.json`):**  
  - `php >=8.2`  
  - Dev: `phpunit/phpunit ^10.5`, `mockery/mockery ^1.6`, `phpstan/phpstan ^2.1`.  
  - Scripts: `serve`, `test`, `test:cov`.  
  - Autoload PSR-4: `App\` y `Src\` → `src/`.

- **openai-service:** `guzzlehttp/guzzle ^7.9`, `vlucas/phpdotenv ^5.6`, PHP 8.2. Autoload `Creawebes\OpenAI\`.  
- **rag-service:** solo requiere PHP 8.2; autoload `Creawebes\Rag\`.

## 🧪 Tests y calidad

- **PHPUnit:** configurado en `phpunit.xml.dist` con `tests/bootstrap.php`. Tests ubicados por dominio (`tests/Albums`, `tests/Heroes`, `tests/Notifications`, `tests/Shared`, `tests/Unit/*`).  
- **PHPStan:** `phpstan.neon` nivel 6, excluye `src/Dev`.  
- **DevController (`/dev/tests/run`)** expone un runner HTTP que ejecuta PHPUnit desde `App\Dev\Test\PhpUnitTestRunner`.  
- **Artefactos de QA:** `docs/TASKS_AUTOMATION.md` documenta tasks VS Code para PHPUnit, PHPStan, Composer Validate y QA completa.

### Comandos recomendados

```bash
composer install                     # dependencias app principal
composer serve                       # alias para php -S localhost:8080 -t public
vendor/bin/phpunit --colors=always   # suite de tests
vendor/bin/phpstan analyse           # análisis estático
composer test:cov                    # cobertura (build/coverage.xml)
cd openai-service && php -S localhost:8081 -t public
cd rag-service && php -S localhost:8082 -t public
npm run <script>                     # reservado para tooling/ui cuando se agregue package.json
```

> Nota: aunque hoy no existe `package.json`, se recomienda tener Node/npm listos para scripts de frontend o tooling (linters, bundlers) descritos en la documentación cuando se integren.

## 📈 SonarCloud y tipos de tests

- **SonarCloud:** configurado mediante `sonar-project.properties` apuntando a `coverage.xml` generado por PHPUnit (`composer test:cov`). No se requiere credencial en local; el pipeline remoto sube el reporte consolidado.
- **Tipos de pruebas:** mantenemos suites unitarias (entidades, servicios puros, fakes), de aplicación/integración ligera (casos de uso con repositorios en memoria) y dobles de infraestructura en `tests/Fakes` y `tests/Doubles`. No se tocan archivos reales ni servicios externos en las suites.
- **Herramientas:** PHPUnit 10.5 para ejecución y cobertura, PHPStan nivel 6 para estática. `docs/TASKS_AUTOMATION.md` incluye tasks de VS Code para lanzar ambas en un clic.
- **Artefactos:** la cobertura se guarda en `build/coverage.xml` (incluida en `.gitignore`), y SonarCloud usa ese archivo para calcular el porcentaje de líneas cubiertas.

## 📚 Documentación complementaria

- `docs/ARCHITECTURE.md`: detalle de las capas y flujo Clean.  
- `docs/REQUIREMENTS.md`: checklist de entorno, extensiones y pasos de instalación.  
- `docs/API_REFERENCE.md`: endpoints HTTP y payloads.  
- `docs/USE_CASES.md`: casos de uso funcionales.  
- `docs/ROADMAP.md` y `docs/CHANGELOG.md`: evolución planificada y releases.  
- `docs/TASKS_AUTOMATION.md`: tasks VS Code ya configuradas.  
- `docs/uml/*`: diagramas de paquetes, secuencia y entidades.

## 🧩 Filosofía Clean Architecture aplicada

El proyecto sigue la filosofía de mantener el negocio como núcleo inmutable:

```
[Usuarios / Eventos externos]
        ↓
Presentation: Router + Controllers + Views (HTTP-only)
        ↓   depende de
Application: Casos de uso (Albums/Heroes/Notifications), servicios AI/RAG
        ↓   depende de
Domain: Entidades, Repositorios (interfaces), Eventos y Value Objects
        ↓   depende de
Infrastructure: Persistencia JSON, Bus en memoria, adaptadores externos
        ↓
Servicios externos: Microservicio OpenAI, Microservicio RAG, OpenAI API
```

- Dependencias fluyen de afuera hacia adentro; el dominio no conoce HTTP, OpenAI ni storage.  
- Repositorios son interfaces en el dominio (`App\Albums\Domain\Repository\AlbumRepository`), implementados en `Infrastructure` (archivos JSON).  
- Eventos (`HeroCreated`, `AlbumUpdated`) viajan por `InMemoryEventBus`, permitiendo handlers como `FileNotificationSender` sin acoplar los casos de uso.  
- Integraciones IA se encapsulan en `openai-service` y `rag-service`, por lo que cambiar de proveedor solo afecta a los microservicios.  
- `ServiceUrlProvider` y `APP_ENV=auto` permiten mover la app entre local y hosting sin modificar el dominio ni los controladores.

## 👤 Créditos y autor

Proyecto creado por **Martín Pallante** · [Creawebes](https://www.creawebes.com)  
Con soporte de **Alfred**, asistente de IA 🤖  
> “Diseñando tecnología limpia, modular y con propósito.”
