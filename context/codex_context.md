# 🧠 Contexto Técnico – Codex

## Identidad del Proyecto
- Proyecto: Clean Marvel Album
- Lenguaje: PHP 8.2
- Paradigma: Clean Architecture (Domain → Application → Infrastructure → Presentation)
- Persistencia actual: JSON (MVP)
- Próxima fase: Migración a SQLite
- Testing: PHPUnit con cobertura completa
- EventBus: en memoria, con sistema de notificaciones

## Microservicios
- openai-service: gestiona interacción con OpenAI API.
- rag-service: realiza búsquedas contextuales y respuestas RAG.
- Endpoint extra: `/api/tts-elevenlabs.php` transforma texto (cómic y RAG) en audio usando ElevenLabs con voz Charlie (`EXAVITQu4vr4xnSDxMaL`) y modelo `eleven_multilingual_v2`; siempre cargar `ELEVENLABS_*` desde `.env`.
- iamasterbigschool.contenido.creawebes.com → app principal (frontend)
- openai-service.contenido.creawebes.com → backend IA
- rag-service.contenido.creawebes.com → backend búsqueda

## Normas al generar o editar código
1. Mantener PSR-4, namespaces y estructura de carpetas actual.
2. No romper el flujo MVC ni alterar archivos fuera del ámbito solicitado.
3. Mantener compatibilidad entre entorno local (`localhost:8080`) y hosting remoto.
4. Respetar los nombres de entidades, clases y rutas existentes.
5. No sobreescribir código ni eliminar funciones sin confirmación.
6. Incluir comentarios claros y consistentes.
7. Al trabajar con eventos o notificaciones, usar el EventBus ya existente.
8. Siempre basarse en este archivo `codex_context.md` para entender el propósito del código.

## Objetivo del Proyecto
Proporcionar una aplicación modular de ejemplo basada en Clean Architecture que gestione héroes, álbumes y cómics con integración IA (OpenAI + RAG).
