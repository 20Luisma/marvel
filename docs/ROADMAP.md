# 🧭 Roadmap Técnico — Clean Marvel Album

## Fase 1 (Completada)
✅ Separación de controladores y router dedicado (`Src\Shared\Http\Router`)  
✅ QA completo (PHPUnit, PHPStan, Composer validate, Pa11y/Lighthouse)  
✅ Test suite estable

## Fase 2 (Completada)
✅ Microservicio OpenAI (`openai-service`, `POST /v1/chat`)  
✅ Sistema RAG (`rag-service`, `POST /rag/heroes`) con conocimiento en JSON  
✅ Paneles técnicos: GitHub PRs, SonarCloud, Sentry, Performance, Accesibilidad (WAVE), Heatmap, Repo browser  
✅ Narración ElevenLabs en cómics y comparación RAG

## Fase 3 (En curso)
🔄 Refinar paneles (loading states, UX accesible)  
🔄 Documentación viva y ADRs actualizados  
🔄 Hardening de despliegues (entornos `hosting` vs `local`, validaciones de config)

## Fase 4 (Próxima)
🔜 Autenticación básica para paneles internos  
🔜 Persistencia relacional opt-in (MySQL) con migraciones guiadas  
🔜 Métricas y dashboards adicionales (observabilidad y auditoría de actividad)
