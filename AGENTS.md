# AGENTS — Clean Marvel Album

## 🎯 Contexto y propósito
- **Clean Marvel Album** es una demo/producto educativo en **PHP 8.2** que aplica Arquitectura Limpia para gestionar álbumes y héroes Marvel, desacoplando la lógica del framework, la UI (`public/`, `views/`) y la infraestructura (`storage/`, adaptadores JSON).
- El backend central orquesta dos microservicios IA propios (`openai-service` y `rag-service`) y expone los casos de uso mediante controladores HTTP y vistas Twig-less. La capa `App\Config\ServiceUrlProvider` resuelve automáticamente los endpoints según entorno (`local`, `hosting`).
- La base de conocimiento y logs viven en `storage/`, lo que facilita semillas reproducibles (`App\Dev\Seed`) y auditoría de eventos/actividades.
- El tráfico hacia microservicios se firma con HMAC usando `INTERNAL_API_KEY` y las cabeceras `X-Internal-*`; el frontend consume `/api/rag/heroes` como proxy sin exponer claves. Rag/OpenAI rechazan peticiones sin firma válida o con timestamp vencido y registran logs en `storage/logs` (sin credenciales).
- El flujo de desarrollo se apoya en Composer scripts (`composer serve`, `composer test`, `composer test:cov`), tasks de VS Code documentadas en `docs/TASKS_AUTOMATION.md`, y un runner HTTP `/dev/tests/run` que dispara PHPUnit desde `App\Dev\Test\PhpUnitTestRunner`.
- Los resultados de texto (cómic y comparación RAG) ahora ofrecen narración con ElevenLabs: los botones del frontend apuntan a `/api/tts-elevenlabs.php`, que usa `ELEVENLABS_API_KEY` desde `.env` sin exponerla al navegador.

### 🧱 Capas Clean Architecture
| Capa | Directorios clave | Responsabilidad |
| --- | --- | --- |
| **Presentación** | `public/index.php`, `src/Controllers`, `views/`, `Src\Shared\Http\Router` | Front Controller + Router HTTP; render de vistas y respuestas JSON. |
| **Aplicación** | `src/*/Application`, `src/AI`, `src/Dev` | Casos de uso, servicios orquestadores (OpenAIComicGenerator, Hero comparators, Seeders). |
| **Dominio** | `src/*/Domain` | Entidades, Value Objects, eventos (`HeroCreated`, `AlbumUpdated`) y contratos de repositorio. |
| **Infraestructura** | `src/*/Infrastructure`, `storage/`, `Src\Shared\Infrastructure\Bus` | Repos JSON (albums/heroes), EventBus en memoria, adaptadores externos (notificaciones, RAG gateway). |

Las dependencias fluyen de Presentación → Aplicación → Dominio, e Infraestructura sólo implementa contratos del dominio para mantener la inversión de dependencias.

### 🛰️ Microservicios IA
- **openai-service (`openai-service/`, puerto 8081):** expone `POST /v1/chat` via `Creawebes\OpenAI\Http\Router`. Usa cURL contra `https://api.openai.com/v1/chat/completions`, maneja fallback JSON cuando falta `OPENAI_API_KEY` y permite configurar `OPENAI_MODEL`.
- **rag-service (`rag-service/`, puerto 8082):** resuelve `POST /rag/heroes` y `/rag/agent`. Conocimiento en `storage/knowledge/*.json` y `storage/marvel_agent_kb.json`. Recupera contextos vía retriever vectorial si `RAG_USE_EMBEDDINGS=1` y existen embeddings (`storage/embeddings/*.json` o `storage/marvel_agent_embeddings.json`); si faltan, cae al retriever léxico. Estado actual: el flujo Marvel Agent está en vectorial (embeddings generados en `storage/marvel_agent_embeddings.json`), el flujo de héroes sigue en léxico porque falta `storage/embeddings/heroes.json`. Delegado a `HeroRagService`/`MarvelAgent` y luego a `openai-service`.
- Ambos servicios cargan `.env` manualmente, mantienen su propio `composer.json` y pueden desplegarse de forma independiente (hosting vs local) usando las URLs definidas en `config/services.php`.

### 🔊 Soporte ElevenLabs TTS
- Endpoint `public/api/tts-elevenlabs.php` recibe `POST { text }`, agrega `ELEVENLABS_API_KEY`, `ELEVENLABS_VOICE_ID` y parámetros opcionales (`ELEVENLABS_MODEL_ID`, `ELEVENLABS_VOICE_STABILITY`, `ELEVENLABS_VOICE_SIMILARITY`) antes de llamar a `https://api.elevenlabs.io/v1/text-to-speech/{voiceId}`.
- La voz por defecto es **Charlie** (`EXAVITQu4vr4xnSDxMaL`) usando el modelo `eleven_multilingual_v2`; cualquier payload sin `voiceId` usará automáticamente esa configuración.
- La vista `views/pages/comic.php` añade botones/audio en los contenedores de la historia generada y del resultado RAG; el JS principal (`public/assets/js/comic.js`) centraliza las llamadas y mantiene oculto el `<audio>` hasta recibir el stream.
- Si `ELEVENLABS_API_KEY` está vacío, los botones quedan deshabilitados y el endpoint responde con un error descriptivo sin filtrar la credencial.

### 🛠️ Flujo de desarrollo recomendado
- Instalar dependencias con `composer install` (raíz) y, si se trabaja en microservicios, repetir en `openai-service/` y `rag-service/`.
- Levantar el servidor principal con `composer serve` o `php -S localhost:8080 -t public`.
- Iniciar microservicios: `php -S localhost:8081 -t public` en `openai-service/` y `php -S localhost:8082 -t public` en `rag-service/`.
- Validar calidad antes de cualquier PR: `vendor/bin/phpunit`, `vendor/bin/phpstan analyse`, `composer validate`.
- Revisar documentación viva en `docs/` (Arquitectura, API, Roadmap, UML) para asegurar consistencia al introducir nuevos casos de uso.

## 👥 Roles de los agentes
- **🔧 Refactorizador:** aplica mejoras estructurales sin romper contratos del dominio. Debe mantener las entidades puras, tocar `src/bootstrap.php` para wiring y actualizar `ServiceUrlProvider`/`config/services.php` cuando se agreguen entornos.
- **🧪 Generador de tests:** crea o ajusta pruebas en `tests/` respetando la convención `*Test.php`. Usa dobles con Mockery cuando sea necesario y actualiza `tests/bootstrap.php` solo si cambia el autoload.
- **📝 Documentador:** mantiene `README.md`, `docs/*.md`, diagramas UML y este `AGENTS.md`. Debe reflejar cualquier cambio de endpoints, comandos o dependencias y asegurar ejemplos reproducibles.
- **🔗 Gestor de microservicios:** sincroniza la app principal con `openai-service` y `rag-service`. Verifica `.env` de cada servicio, puertos y healthchecks; coordina despliegues paralelos (local vs hosting) y controla compatibilidad de payloads.
- **🛡️ Auditor de calidad:** ejecuta pipelines de QA (PHPUnit + PHPStan + Composer Validate), revisa `storage/notifications.log`/`storage/activity/*.json` para detectar regresiones y garantiza cobertura mínima en casos críticos.

## 🧩 Reglas y buenas prácticas (reviews + arquitectura)
- Respetar la inversión de dependencias: nuevos repositorios deben declararse como interfaces en `src/*/Domain/Repository` y concretarse en `Infrastructure`.
- Evitar lógica HTTP/infraestructura en el dominio o los casos de uso; los controladores sólo orquestan y delegan.
- Cada evento (`App\Albums\Domain\Event\AlbumUpdated`, etc.) necesita su handler registrado en `src/bootstrap.php`. Mantener handlers idempotentes y sin efectos secundarios fuera de su responsabilidad.
- No leer/escribir directamente en `storage/` desde la capa de presentación; usar repositorios o servicios dedicados.
- Revisiones de código deben comprobar: naming consistente, cobertura de pruebas, cumplimiento de PHPStan nivel 6, gestión de errores (throwables tipados) y resiliencia frente a fallos en microservicios.
- Documentar cualquier cambio de payload o contrato HTTP en `docs/API_REFERENCE.md` y reflejar nuevos flujos en `docs/ARCHITECTURE.md`.

## 🧪 Auditoría de calidad y configuración PHPUnit
- **PHPUnit:** definido en `phpunit.xml.dist` (bootstrap `tests/bootstrap.php`, cache `.phpunit.cache`). Ejecutar `vendor/bin/phpunit --colors=always` para la suite completa; usar `composer test:cov` para generar `build/coverage.xml`.
- **Escopos de prueba:** carpetas `tests/Albums`, `tests/Heroes`, `tests/Notifications`, `tests/Shared` y `tests/Unit`. Mantener la paridad con `src/`.
- **Herramientas auxiliares:** `vendor/bin/phpstan analyse --memory-limit=512M` (config `phpstan.neon` nivel 6, excluyendo `src/Dev`). Añadir reglas personalizadas allí en lugar de ignorar alertas sin justificación.
- **Logging QA:** conservar artefactos de fallos (salida PHPUnit/PHPStan, `storage/logs/*`) en la descripción del PR; no subir `vendor/` ni archivos generados salvo `build/coverage.xml` si se solicita.
- **Microservicios:** antes de test funcional, confirmar que `openai-service` y `rag-service` responden (`curl -X POST http://localhost:8081/v1/chat`, `curl -X POST http://localhost:8082/rag/heroes`) usando payloads de ejemplo almacenados en `docs/API_REFERENCE.md`.

## 🧯 Safe Mode (dry-run)
- Activar `SAFE_MODE=1` (o variable equivalente solicitada por el usuario) en los comandos para indicar que no se escribirán cambios: `SAFE_MODE=1 vendor/bin/phpunit`.
- Limitarse a comandos de inspección (`ls`, `rg`, `git status`, `cat`) y a simulaciones (`composer test -- --filter ...` sin modificar código). Si se necesita demostrar un cambio, describir el parche en texto sin ejecutarlo.
- Evitar `apply_patch`, editores o scripts que escriban archivos. En su lugar, producir diffs hipotéticos o pseudo-parches dentro de la respuesta.
- Comunicar en cada paso qué se habría hecho fuera de Safe Mode, incluyendo rutas y comandos, para que el equipo decida cuándo salir del modo seguro.

## 💻 Comandos útiles en Codex CLI
| Escenario | Comando desde la raíz (`workdir=/clean-marvel`) |
| --- | --- |
| Instalar dependencias principales | `["bash","-lc","composer install"]` |
| Levantar servidor HTTP | `["bash","-lc","composer serve"]` |
| Ejecutar PHPUnit completo | `["bash","-lc","vendor/bin/phpunit --colors=always"]` |
| Analizar con PHPStan | `["bash","-lc","vendor/bin/phpstan analyse --memory-limit=512M"]` |
| Servir microservicio OpenAI | `["bash","-lc","php -S localhost:8081 -t public","workdir":"openai-service"]` |
| Servir microservicio RAG | `["bash","-lc","php -S localhost:8082 -t public","workdir":"rag-service"]` |
| Comparar ambientes configurados | `["bash","-lc","php -r 'var_export((new App\\Config\\ServiceUrlProvider(require \"config/services.php\"))->toArrayForFrontend());'"]` |
| Refrescar KB + embeddings del Marvel Agent | `["bash","-lc","cd rag-service && ./bin/refresh_marvel_agent.sh"]` (requiere `OPENAI_API_KEY` exportada) |

> Mantén este documento actualizado cada vez que cambie la arquitectura, los comandos soportados o los roles del equipo.***
