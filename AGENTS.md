# AGENTS — Clean Marvel Album

> 📚 **Documentación completa**: Para guía detallada ver `docs/PROJECT_GUIDE.md` (~2800 líneas).

## 🎯 Contexto y propósito
- **Clean Marvel Album** es una demo/producto educativo en **PHP 8.2** que aplica Arquitectura Limpia para gestionar álbumes y héroes Marvel, desacoplando la lógica del framework, la UI (`public/`, `views/`) y la infraestructura (`storage/`, adaptadores JSON).
- El backend central orquesta **3 microservicios externos** propios y expone los casos de uso mediante controladores HTTP y vistas Twig-less.
- La capa `App\Config\ServiceUrlProvider` resuelve automáticamente los endpoints según entorno (`local`, `hosting`).
- El tráfico hacia microservicios se firma con HMAC usando `INTERNAL_API_KEY` y las cabeceras `X-Internal-*`.

### 🧱 Capas Clean Architecture
| Capa | Directorios clave | Responsabilidad |
| --- | --- | --- |
| **Presentación** | `public/index.php`, `src/Controllers`, `views/` | Front Controller + Router HTTP; render de vistas y respuestas JSON. |
| **Aplicación** | `src/*/Application`, `src/AI`, `src/Dev` | Casos de uso, servicios orquestadores. |
| **Dominio** | `src/*/Domain` | Entidades, Value Objects, eventos y contratos de repositorio. |
| **Infraestructura** | `src/*/Infrastructure`, `storage/` | Repos JSON/DB, EventBus, adaptadores externos. |

---

## 🛰️ Los 3 Microservicios (Arquitectura Completa)

| Servicio | Tecnología | Puerto Local | Hosting | Propósito |
|----------|------------|--------------|---------|-----------|
| **OpenAI Service** | PHP 8.2 | 8081 | `openai-service.contenido.creawebes.com` | Generar cómics con GPT |
| **RAG Service** | PHP 8.2 | 8082 | `rag-service.contenido.creawebes.com` | Comparar héroes con RAG |
| **Heatmap Service** | ⚠️ **Python 3.10 + Flask** | 5000 | `http://34.74.102.123:8080` (Google Cloud) | Analytics de clics |

### OpenAI Service (PHP)
- **Endpoint:** `POST /v1/chat`
- **Flujo:** App → HMAC → OpenAI Service → api.openai.com → Respuesta GPT
- **Variables:** `OPENAI_API_KEY`, `OPENAI_MODEL`, `INTERNAL_API_KEY`

### RAG Service (PHP)
- **Endpoints:** `POST /rag/heroes`, `POST /rag/agent`
- **Retrievers:** Léxico (`RAG_USE_EMBEDDINGS=0`) o Vectorial (`RAG_USE_EMBEDDINGS=1`)
- **Knowledge base:** `storage/knowledge/*.json`, `storage/marvel_agent_kb.json`

### Heatmap Service (Python + Flask + Docker + Google Cloud)
> ⚠️ **Diferente tecnología**: Python, no PHP. Desplegado en **Google Cloud VM**, no en hosting compartido.

- **Endpoints:** `POST /track` (registrar clic), `GET /events` (listar clics)
- **Auth:** Header `X-API-Token`
- **DB:** SQLite (`heatmap.db`)
- **URL prod:** `http://34.74.102.123:8080`
- **Proxies PHP:** `/api/heatmap/click.php`, `/api/heatmap/summary.php`, `/api/heatmap/pages.php`
- **Estructura:**
  ```
  heatmap-service/  # En Google Cloud
  ├── app.py        # Flask app
  ├── heatmap.db    # SQLite
  ├── Dockerfile
  └── requirements.txt
  ```

### URLs por entorno (config/services.php)
```php
'local' => [
    'app'    => 'http://localhost:8080',
    'openai' => 'http://localhost:8081/v1/chat',
    'rag'    => 'http://localhost:8082/rag/heroes',
],
'hosting' => [
    'app'    => 'https://iamasterbigschool.contenido.creawebes.com',
    'openai' => 'https://openai-service.contenido.creawebes.com/v1/chat',
    'rag'    => 'https://rag-service.contenido.creawebes.com/rag/heroes',
],
// Heatmap siempre apunta a Google Cloud: http://34.74.102.123:8080
```

---

## 🔐 Las 10 Fases de Seguridad

| Fase | Tema | Estado |
|------|------|--------|
| 1 | Hardening HTTP (cabeceras, cookies) | ✅ Completa |
| 2 | Autenticación (bcrypt, TTL, IP/UA) | ✅ Completa |
| 3 | Autorización (AuthMiddleware, Guards) | ✅ Completa |
| 4 | CSRF y XSS (tokens, escapado) | ✅ Completa |
| 5 | APIs y microservicios (ApiFirewall, rate-limit) | ✅ Completa |
| 6 | Monitorización (SecurityLogger, Sentry) | ✅ Completa |
| 7 | Anti-replay (token sesión) | ✅ Observación |
| 8 | Cabeceras avanzadas (CSP, CORP, COOP) | ✅ Completa |
| 9 | Gestión de secretos | 🚧 En progreso |
| 10 | Tests automáticos seguridad | 🚧 Planificado |

> Detalle completo en `docs/security/security.md`

---

## 🔊 Soporte ElevenLabs TTS
- **Endpoint:** `POST /api/tts-elevenlabs.php`
- **Variables:** `ELEVENLABS_API_KEY`, `ELEVENLABS_VOICE_ID` (default: Charlie)
- **Uso:** Narración de cómics y comparaciones RAG

---

## 🔧 Scripts CLI (bin/)

| Script | Propósito |
|--------|-----------|
| `migrar-json-a-db.php` | Migra datos JSON a MySQL |
| `security-check.sh` | PHPUnit Security + PHPStan + audit |
| `generate-bundle-size.php` | Métricas de assets |
| `pa11y-all.sh` | Auditoría accesibilidad |
| `verify-token-metrics.php` | Verifica métricas tokens IA |
| `analyze_coverage.py` | Analiza cobertura |
| `diagnose-token-metrics.sh` | Diagnóstico de métricas |
| `simulate_web_call.php` | Simula llamadas HTTP |
| `zonar_fix_permisos.sh` | Fix permisos en hosting |

---

## 📊 APIs del Dashboard (panel de métricas)

| Endpoint | Propósito | Auth |
|----------|-----------|------|
| `/api/ai-token-metrics.php` | Uso de tokens IA | Admin |
| `/api/sonar-metrics.php` | SonarCloud | Admin |
| `/api/sentry-metrics.php` | Errores Sentry | Admin |
| `/api/security-metrics.php` | Seguridad | Admin |
| `/api/performance-marvel.php` | PageSpeed | Admin |
| `/api/accessibility-marvel.php` | WAVE | Admin |
| `/api/snyk-scan.php` | Vulnerabilidades | Admin |
| `/api/github-activity.php` | GitHub | Público |
| `/api/marvel-movies.php` | YouTube | Público |
| `/api/marvel-agent.php` | Agente IA | Público |

---

## 👥 Roles de los agentes
- **🔧 Refactorizador:** mejoras estructurales sin romper contratos. Toca `src/bootstrap.php` para wiring.
- **🧪 Generador de tests:** tests en `tests/` con convención `*Test.php`. Usa Mockery.
- **📝 Documentador:** mantiene `README.md`, `docs/*.md`, `AGENTS.md`.
- **🔗 Gestor de microservicios:** sincroniza app principal con los 3 microservicios. Verifica `.env`, puertos, healthchecks.
- **🛡️ Auditor de calidad:** PHPUnit + PHPStan + Composer audit.

---

## 🧩 Reglas y buenas prácticas
- Respetar inversión de dependencias: interfaces en `Domain/Repository`, implementación en `Infrastructure`.
- Evitar lógica HTTP en dominio; controladores solo orquestan.
- Eventos necesitan handler registrado en `bootstrap.php`.
- No leer/escribir `storage/` desde presentación.
- PHPStan nivel 6+, cobertura de tests, throwables tipados.
- Documentar cambios de payload en `docs/API_REFERENCE.md`.

---

## 🧪 Auditoría de calidad
- **PHPUnit:** `vendor/bin/phpunit --colors=always`
- **Coverage:** `composer test:cov` → `coverage.xml`
- **PHPStan:** `vendor/bin/phpstan analyse --memory-limit=1G`
- **Security:** `bash bin/security-check.sh`
- **Composer:** `composer audit --no-interaction`
- **Entorno de tests:** `tests/bootstrap.php` fija `APP_ENV=test` y `DB_DSN=sqlite::memory:` (sin depender de `.env`) y redirige avisos PHP a `sys_get_temp_dir()/phpunit-clean-marvel.log`. Los endpoints/vistas de GitHub aceptan fakes con `__github_client_factory` + banderas `GITHUB_REPO_BROWSER_TEST` / `PANEL_GITHUB_TEST` para correr PHPUnit sin red.

---

## 🧯 Safe Mode (dry-run)
- Activar `SAFE_MODE=1` para comandos sin escribir cambios.
- Limitarse a inspección: `ls`, `rg`, `git status`, `cat`.
- Producir diffs hipotéticos en lugar de aplicar parches.

---

## 💻 Comandos útiles

| Escenario | Comando |
| --- | --- |
| Instalar dependencias | `composer install` |
| Servidor principal | `php -S localhost:8080 -t public` |
| OpenAI Service | `cd openai-service && php -S localhost:8081 -t public` |
| RAG Service | `cd rag-service && php -S localhost:8082 -t public` |
| Tests | `vendor/bin/phpunit --colors=always` |
| PHPStan | `vendor/bin/phpstan analyse --memory-limit=1G` |
| Verificar Heatmap | `curl http://34.74.102.123:8080/health` |

---

## 📁 Variables de entorno clave (.env)

```env
# App
APP_ENV=local|hosting|test
APP_URL=http://localhost:8080

# Microservicios
OPENAI_SERVICE_URL=http://localhost:8081/v1/chat
RAG_SERVICE_URL=http://localhost:8082/rag/heroes
HEATMAP_API_BASE_URL=http://34.74.102.123:8080
HEATMAP_API_TOKEN=your-token

# APIs externas
OPENAI_API_KEY=sk-xxxxx
ELEVENLABS_API_KEY=
INTERNAL_API_KEY=shared-secret

# Seguridad
SENTRY_DSN=
SNYK_API_KEY=
```

> Referencia completa en `.env.example` y `docs/PROJECT_GUIDE.md` (Sección 28).

---

> 🔄 **Mantén este documento actualizado** cada vez que cambie la arquitectura, los microservicios, o los comandos soportados.  
> 📚 **Para documentación exhaustiva**, consultar `docs/PROJECT_GUIDE.md`.

*Última sincronización: 8 Diciembre 2025*
