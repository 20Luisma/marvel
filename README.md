# Clean Marvel Album – Documentación Técnica

![CI](https://github.com/20Luisma/marvel/actions/workflows/ci.yml/badge.svg)
![Coverage](https://sonarcloud.io/api/project_badges/measure?project=20Luisma_marvel&metric=coverage)
![Maintainability](https://sonarcloud.io/api/project_badges/measure?project=20Luisma_marvel&metric=sqale_rating)
![Pa11y](https://img.shields.io/badge/Pa11y-enabled-brightgreen)
![Playwright E2E](https://img.shields.io/badge/Playwright%20E2E-passing-brightgreen)

**Clean Marvel Album** es una implementación educativa de **Arquitectura Limpia** en **PHP 8.2+** que orquesta:

- Un backend modular (álbumes, héroes, notificaciones, actividad, AI…)
- Dos microservicios desacoplados de IA (`openai-service`, `rag-service`)
- Paneles internos de observabilidad (SonarCloud, Sentry, accesibilidad, performance, GitHub, repo, heatmap, etc.)
- Un pipeline completo de **CI/CD** con tests, calidad y **deploy automático por FTP** a producción.

> ✅ **Accesibilidad WCAG 2.1 AA**: Pa11y reporta `0 issues` en todas las páginas públicas analizadas.

---

## 🎯 Objetivo

- Mantener el **dominio** completamente limpio e independiente de framework, HTTP o infraestructura.
- Integrar IA mediante microservicios PHP desacoplados y fácilmente reemplazables.
- Servir como blueprint realista de proyecto PHP con arquitectura limpia, testing y despliegue automatizado.

---

## 🧠 Arquitectura general

| Capa            | Ubicación principal                                                                 | Responsabilidad |
|-----------------|--------------------------------------------------------------------------------------|-----------------|
| **Presentation**| `public/`, `src/Controllers`, `views/`, `Src\Shared\Http\Router`                    | Entradas HTTP, routing, vistas, respuestas JSON. |
| **Application** | `src/*/Application/UseCase`, `src/AI`, `src/Dev`                                   | Casos de uso, orquestación, servicios de aplicación. |
| **Domain**      | `src/*/Domain` (entidades, repos, eventos, VOs)                                     | Reglas de negocio puras, sin dependencias externas. |
| **Infrastructure** | `src/*/Infrastructure`, `storage/*`, `App\Shared\Infrastructure\Bus`            | Persistencia JSON/DB, EventBus, adaptadores externos. |

```
[Browser / CLI]
      ↓
Presentation
      ↓
Application
      ↓
Domain
      ↓
Infrastructure
      ↓
Microservicios y APIs externas (OpenAI, RAG, GitHub, Sentry, SonarCloud, WAVE, PSI…)
```

---

## 🗂️ Estructura del proyecto

```
clean-marvel/
├── public/
├── src/
├── openai-service/
├── rag-service/
├── storage/
├── docs/
├── tests/
├── .vscode/tasks.json
├── .github/workflows/
├── docker-compose.yml
└── .env
```

---

## 💾 Persistencia: JSON en local y MySQL en hosting

- **Local (`APP_ENV=local`)** → repositorios JSON.
- **Hosting (`APP_ENV=hosting`)** → repositorios PDO (MySQL).  
- Si MySQL falla → **fallback automático** a JSON.

### Migración JSON → DB

```bash
php bin/migrar-json-a-db.php
```

---

## 🧩 Microservicios de IA

### 🤖 `openai-service` (8081)
- Endpoint: `POST /v1/chat`.
- Usa `OPENAI_API_KEY` + `OPENAI_MODEL`.
- Fallback si OpenAI falla.

### 🧠 `rag-service` (8082)
- Endpoint: `POST /rag/heroes`.
- Usa conocimiento local (`heroes.json`).
- Llama al `openai-service` internamente.

---

## 📊 Paneles de observabilidad

### 🔭 SonarCloud
- API interna: `/api/sonar-metrics.php`
- Métricas: coverage, bugs, smells, duplicación…

### 🧯 Sentry
- Captura de errores y panel de eventos recientes.

### 🐙 Panel GitHub
- Listado de PRs, commits, reviewers y actividad.

### 📁 Repo Marvel
- Explorador de archivos del repo GitHub desde la web.

### 📈 Performance Marvel (PageSpeed Insights)
- Scores LCP / FCP / CLS / TBT por página.

### ♿ Accesibilidad (WAVE + Pa11y)
- WAVE analiza errores por página.
- Pa11y ejecuta WCAG2AA automáticamente en CI.

### 🌡️ Heatmap de clics
- Tracker avanzado: X normalizado + Y respecto a página completa (scroll incluido).
- Logs mensuales.
- Panel con canvas + KPIs + Chart.js.

### 🔊 ElevenLabs (Narración)
- Servicio propio `/api/tts-elevenlabs.php`.

---

## ⚙️ CI/CD: GitHub Actions + SonarCloud + FTP Deploy

Pipeline completo ubicado en `.github/workflows/`.

### 1️⃣ `ci.yml` (integración continua)

Se ejecuta en cada push/PR.

Incluye:

#### ✔ build
- Composer install  
- PHPUnit  
- PHPStan  
- Composer validate  

#### ✔ tests  
Placeholder para ejecución extendida.

#### ✔ sonarcloud  
- Ejecuta PHPUnit con cobertura  
- Sube resultados a SonarCloud  

#### ✔ pa11y  
- Levanta servidor local  
- Ejecuta Pa11y en modo **WCAG2AA**  
- Sube artefactos al CI  

#### ✔ lighthouse  
- Ejecuta auditoría completa de performance, accesibilidad, best practices y SEO  

#### ✔ playwright  
- Tests E2E headless  
- Artefactos: trace, vídeo, screenshots  

> Si cualquiera falla → el pipeline se detiene.

---

### 2️⃣ `deploy-ftp.yml` (despliegue automático)

Cuando `ci.yml` está **todo en verde**:

- Se activa `deploy-ftp.yml` (manual o automático).
- Usa:
  - `FTP_HOST`
  - `FTP_USERNAME`
  - `FTP_PASSWORD`
  - `FTP_REMOTE_DIR`

Sube únicamente los cambios necesarios a Hostinger.

### 3️⃣ `rollback-ftp.yml`
Permite volver a la versión previa en segundos.

---

## 🧪 Tests y calidad

```bash
vendor/bin/phpunit --colors=always
vendor/bin/phpstan analyse
composer test:cov
```

VS Code incluye tasks para QA completo.

---

## 🚀 Ejecución

### Localhost

```bash
php -S localhost:8080 -t public
cd openai-service && php -S localhost:8081 -t public
cd rag-service   && php -S localhost:8082 -t public
```

### Hosting

- App: `https://iamasterbigschool.contenido.creawebes.com`
- OpenAI-service: `https://openai-service.contenido.creawebes.com/v1/chat`
- RAG-service: `https://rag-service.contenido.creawebes.com/rag/heroes`

---

## 🔐 Variables de entorno

`.env` raíz:

| Variable | Uso |
|---------|-----|
| `APP_ENV` | auto/local/hosting |
| `APP_URL` | origen para CORS |
| `OPENAI_SERVICE_URL` | microservicio IA |
| `ELEVENLABS_*` | TTS |
| `WAVE_API_KEY` | accesibilidad |
| `PAGESPEED_API_KEY` | performance |
| `TTS_INTERNAL_TOKEN` | seguridad |
| `MARVEL_UPDATE_TOKEN` | webhook n8n |

Medidas extra:
- CORS estricto  
- Bloqueo de `.env`/`.sql` vía `.htaccess`  
- Validación MIME real en uploads  
- Cabeceras de seguridad aplicadas  

---

## 📚 Documentación

En `/docs`:

- `ARCHITECTURE.md`
- `REQUIREMENTS.md`
- `API_REFERENCE.md`
- `USE_CASES.md`
- `ROADMAP.md`
- `CHANGELOG.md`
- `TASKS_AUTOMATION.md`
- UML completo

---

## 👤 Créditos

Proyecto creado por **Martín Pallante** · [Creawebes](https://www.creawebes.com)  
Con soporte técnico de **Alfred**, asistente de IA 🤖

> *“Diseñando tecnología limpia, modular y con propósito.”*
