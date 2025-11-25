# Clean Marvel Album – Documentación Técnica

![CI](https://github.com/20Luisma/marvel/actions/workflows/ci.yml/badge.svg)
![Coverage](https://sonarcloud.io/api/project_badges/measure?project=20Luisma_marvel&metric=coverage)
![Maintainability](https://sonarcloud.io/api/project_badges/measure?project=20Luisma_marvel&metric=sqale_rating)
![Pa11y](https://img.shields.io/badge/Pa11y-enabled-brightgreen)
![Playwright E2E](https://img.shields.io/badge/Playwright%20E2E-passing-brightgreen)

**Clean Marvel Album** es una demo/producto educativo en **PHP 8.2+** que aplica **Arquitectura Limpia** para gestionar álbumes y héroes Marvel. Orquesta un backend modular y varios paneles de observabilidad conectados a microservicios de IA y utilidades externas.

> ✅ **Accesibilidad WCAG 2.1 AA**: Pa11y reporta `0 issues` en todas las páginas públicas.

---

## 🎯 Objetivo

- Mantener el **dominio** limpio e independiente de frameworks.
- Integrar IA mediante microservicios externos fáciles de sustituir.
- Servir como blueprint de proyecto escalable con tests, calidad y despliegue profesional.

---

## 🧠 Arquitectura General

| Capa | Ubicación principal | Responsabilidad |
| --- | --- | --- |
| **Presentación** | `public/`, `src/Controllers`, `views/`, `Src\Shared\Http\Router` | Front Controller + Router HTTP; render de vistas y respuestas JSON. |
| **Aplicación** | `src/*/Application`, `src/AI`, `src/Dev` | Casos de uso, orquestadores (comic generator, comparador RAG, seeders). |
| **Dominio** | `src/*/Domain` | Entidades, Value Objects, eventos y contratos de repositorios. |
| **Infraestructura** | `src/*/Infrastructure`, `storage/`, `Src\Shared\Infrastructure\Bus` | Repos JSON/DB, EventBus en memoria, adaptadores externos (notificaciones, gateways IA). |

Dependencias: Presentación → Aplicación → Dominio, e Infraestructura implementa contratos de Dominio. `App\Config\ServiceUrlProvider` resuelve los endpoints según entorno (`local` vs `hosting`).

---

## 🗂️ Estructura del Proyecto

```
clean-marvel/
├── public/
├── src/
├── openai-service/
├── rag-service/
├── docs/ (API, arquitectura, guías, microservicios, UML)
├── tests/
├── docker-compose.yml
└── .env
```

---

## 💾 Persistencia: JSON en Local, MySQL en Hosting

- **Local (`APP_ENV=local`)** → JSON  
- **Hosting (`APP_ENV=hosting`)** → PDO MySQL  
- Si MySQL falla → fallback automático a JSON

Migración manual:

```bash
php bin/migrar-json-a-db.php
```

---

## 🧩 Microservicios y servicios externos

- **openai-service** (`openai-service/`, puerto 8081)  
  Endpoint `POST /v1/chat` con cURL a OpenAI. Configurable con `OPENAI_API_KEY` y `OPENAI_MODEL`. Tiene fallback JSON sin credencial.
- **rag-service** (`rag-service/`, puerto 8082)  
  Endpoint `POST /rag/heroes`, usa `storage/knowledge/heroes.json` y delega a `openai-service` para la respuesta final.
- **Heatmap service** (Python/Flask externo)  
  Recoge clics reales y alimenta `/secret-heatmap`. Documentación en `docs/microservicioheatmap/README.md`. Incluye contenedor Docker (build/run) para levantar el servicio en local o VM con `HEATMAP_API_TOKEN`.
- **WAVE API** (Accesibilidad)  
  `public/api/accessibility-marvel.php` consulta la API de WebAIM con `WAVE_API_KEY`.
- **ElevenLabs TTS**  
  `public/api/tts-elevenlabs.php` añade narración a cómics y comparaciones RAG usando `ELEVENLABS_API_KEY`.

---

## ⚙️ CI/CD – GitHub Actions

Pipelines: `ci.yml` (PHPUnit, PHPStan, Pa11y, Lighthouse, Playwright E2E, SonarCloud), `deploy-ftp.yml` (deploy automático si todo pasa), `rollback-ftp.yml` (rollback).

---

## 🚀 Puesta en marcha (local)

1. **Instala dependencias**  
   `composer install` en la raíz. Si trabajas en microservicios, repite dentro de `openai-service/` y `rag-service/`.
2. **Configura `.env`**  
   Ajusta `APP_ENV` (`local` usa JSON, `hosting` usa MySQL con fallback a JSON), URLs de servicios (`OPENAI_SERVICE_URL`, `RAG_SERVICE_URL`, `HEATMAP_API_BASE_URL`), tokens (`GITHUB_API_KEY`, `ELEVENLABS_API_KEY`, `WAVE_API_KEY`, PSI, Sentry, SonarCloud).
3. **Arranca la app principal**  
   `composer serve` o `php -S localhost:8080 -t public`.
4. **Arranca microservicios IA**  
   - `php -S localhost:8081 -t public` (dentro de `openai-service/`)  
   - `php -S localhost:8082 -t public` (dentro de `rag-service/`)
5. **Verifica paneles**  
   Navega a `/` y usa las acciones superiores para cómics, RAG, GitHub PRs, SonarCloud, Sentry, accesibilidad, performance, repo y heatmap.

## 🧪 Calidad y pruebas

- Suite completa: `vendor/bin/phpunit --colors=always`
- Cobertura: `composer test:cov`
- Análisis estático: `vendor/bin/phpstan analyse --memory-limit=512M`
- Validación Composer: `composer validate`

## 📚 Documentación ampliada

- `docs/ARCHITECTURE.md`: capas, flujos y microservicios.
- `docs/API_REFERENCE.md`: endpoints de la app y microservicios.
- `docs/README.md`: índice de documentación.
- `docs/guides/`: arranque rápido, autenticación, testing.
- `docs/microservicioheatmap/README.md`: integración del heatmap.
- `AGENTS.md` / `docs/agent.md`: roles y pautas para agentes de IA.
- UML completo
- Microservicio Heatmap → `/docs/microservicioheatmap/README.md`

---

## 👤 Créditos

Proyecto creado por **Martín Pallante** · [Creawebes](https://www.creawebes.com)  
Asistente técnico: **Alfred**, IA desarrollada con ❤️

> *“Diseñando tecnología limpia, modular y con propósito.”*
