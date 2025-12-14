# Roadmap técnico — Clean Marvel Album

## Fase 1 (completada)
- Separación de controladores y router dedicado (`App\Shared\Http\Router`)
- QA: PHPUnit, PHPStan, `composer validate`, Pa11y/Lighthouse
- Suite de tests operativa

## Fase 2 (completada)
- Microservicio OpenAI (`openai-service`, `POST /v1/chat`)
- Sistema RAG (`rag-service`, `POST /rag/heroes`) con base de conocimiento en JSON
- Paneles técnicos: GitHub PRs, SonarCloud, Sentry, performance, accesibilidad (WAVE), heatmap, repo browser
- TTS con ElevenLabs en cómics y comparación RAG

## Fase 3 (en curso)
- Refinar paneles (estados de carga, accesibilidad)
- Mantener documentación y ADRs
- Hardening de despliegues (entornos `hosting` vs `local`, validaciones de config)

## Fase 4 (planificada)
- Autenticación básica para paneles internos
- Persistencia relacional opt-in (MySQL) con migraciones guiadas
- Métricas y dashboards adicionales (observabilidad y auditoría de actividad)
