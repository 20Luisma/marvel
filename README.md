# Clean Marvel Album – Documentación Técnica

**Clean Marvel Album** es una implementación educativa de Arquitectura Limpia en **PHP 8.2** que orquesta un backend modular (álbumes + héroes) y dos microservicios de IA desacoplados (`openai-service`, `rag-service`). Además de servir como demo funcional, actúa como blueprint para proyectos PHP que necesiten capas bien delimitadas, pruebas automatizadas y despliegues paralelos en local y hosting.

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
| `.env` (raíz) | `APP_ENV=auto`, `OPENAI_SERVICE_URL=`, `ELEVENLABS_API_KEY`, `ELEVENLABS_VOICE_ID`, `ELEVENLABS_MODEL_ID`, `ELEVENLABS_VOICE_STABILITY`, `ELEVENLABS_VOICE_SIMILARITY` | `ELEVENLABS_*` habilitan el audio en los resultados. Sin API Key, los botones permanecen inactivos. |
| `openai-service/.env` | `APP_ENV`, `OPENAI_API_KEY`, `OPENAI_API_BASE`, `OPENAI_MODEL`, `ALLOWED_ORIGINS` | **Obligatorio** definir `OPENAI_API_KEY`. `ALLOWED_ORIGINS` sincroniza CORS con app y hosting. |
| `rag-service/.env` | `ALLOWED_ORIGINS`, `APP_ENV`, `OPENAI_SERVICE_URL` | Permite que el RAG apunte al OpenAI service apropiado y limite orígenes. |

Todos los `.env` son cargados manualmente con `file()` + `putenv()` para evitar dependencias innecesarias y mantener cada servicio autocontenible.

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
