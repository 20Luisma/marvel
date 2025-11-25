# Diagramas UML — Clean Marvel Album

Resumen de los flujos y módulos clave del proyecto. Cada diagrama está numerado para referencia rápida.

1. **Capas base** — `arch-clean-marvel-capas.png`  
   Vista Presentación → Aplicación → Dominio → Infraestructura y cómo se conectan a servicios externos.
2. **Microservicios IA** — `arch-microservicios-ia.png`  
   Orquestación monolito ↔ `rag-service:8082` ↔ `openai-service:8081` ↔ OpenAI.
3. **Mapa de servicios externos** — `svc-externos-mapa.png`  
   Paneles y microservicios enlazados con OpenAI, PSI, WAVE, GitHub, Sentry, SonarCloud, ElevenLabs, heatmap.
4. **Módulos internos** — `modulos-albums-heroes-notifications-shared.png`  
   Relaciones entre Albums, Heroes, Notifications y utilidades Shared (router, EventBus, helpers).
5. **Paneles Secret Room** — `modulos-paneles-secret-room.png`  
   Paneles (GitHub, PSI, WAVE, Sentry, Sonar, Heatmap, Repo, Comic/RAG) y sus dependencias API/microservicios.
6. **CRUD Álbumes y Héroes** — `albums-heroes.png`  
   Secuencia de casos de uso, repos JSON/DB, eventos y handlers de notificaciones.
7. **Cómic IA** — `Panel :comic.png`  
   `/comic` → `POST /comics/generate` → OpenAIComicGenerator → `openai-service` → OpenAI API.
8. **Comparación RAG** — `flujorag.png`  
   `/rag` → `rag-service` (contexto JSON) con opción de resumen vía `openai-service`.
9. **Heatmap** — `headmap.png`  
   Tracker JS → `/api/heatmap/click.php` → heatmap-service; panel `/secret-heatmap` consulta `/events`.
10. **GitHub PRs** — `panelgithub.png`  
    `/panel-github` lazy, luego fetch PRs via GithubClient → GitHub REST; validación de fechas y errores.
11. **Accesibilidad WAVE** — `wave.png`  
    `/accessibility` → `POST /api/accessibility-marvel.php` → WAVE API; KPIs y manejo de errores.
12. **Performance PSI** — `performance-psi.png`  
    `/performance` → `POST /api/performance-marvel.php` → PageSpeed Insights; métricas LCP/FID/CLS, etc.
13. **Sentry** — `sentri.png`  
    `/sentry` carga métricas con caché y botones demo `/api/sentry-test.php`; errores HTTP controlados.
14. **SonarCloud** — `sonar.png`  
    `/sonar` → `GET /api/sonar-metrics.php` con caché local; métricas coverage, bugs, code smells.
15. **TTS ElevenLabs** — `elevenlab.png`  
    `/comic` → `POST /api/tts-elevenlabs.php` con texto/voz → ElevenLabs API; audio en cliente.
16. **Persistencia dual** — `act-persistencia-dual.png`  
    Selección de repos DB (hosting) con fallback automático a JSON y logs de error.
17. **EventBus + Notificaciones** — `act-eventbus-notificaciones.png`  
    Publicación de eventos de dominio, listeners y guardado en `storage/notifications.log`/activity.
18. **Pipeline QA/CI/CD** — `act-ci-cd-qa.png`  
    QA local → GitHub Actions (PHPUnit, PHPStan, Pa11y, Lighthouse, Playwright) → deploy FTP → rollback.

Todas las imágenes están en `docs/uml/` y reflejan el estado actual del proyecto y sus integraciones.***
