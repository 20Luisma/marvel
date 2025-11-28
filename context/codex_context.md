# 🧠 Contexto Técnico – Codex

## Identidad del Proyecto
- Proyecto: Clean Marvel Album
- Lenguaje: PHP 8.2+
- Paradigma: Clean Architecture (Dominio → Aplicación → Infraestructura → Presentación)
- Persistencia: JSON en local (`APP_ENV=local`), PDO MySQL en hosting con fallback automático a JSON
- Testing: PHPUnit (suite completa + tests de seguridad), PHPStan, composer audit; script `bin/security-check.sh` y workflow `security-check.yml`
- EventBus: en memoria, con sistema de notificaciones

## Microservicios
- openai-service (8081): `POST /v1/chat`, fallback JSON si falta `OPENAI_API_KEY`.
- rag-service (8082): RAG de héroes y flujo agent (KB + embeddings); scripts en `rag-service/bin/*`.
- Heatmap service: Python/Flask en contenedor (GCP); proxys en `/api/heatmap/*` con token `HEATMAP_API_TOKEN`.
- TTS ElevenLabs: `/api/tts-elevenlabs.php` (voz Charlie por defecto), credenciales en `.env`.
- Resolución de endpoints vía `App\Config\ServiceUrlProvider` y `config/services.php`.

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
Proporcionar una aplicación modular de ejemplo basada en Clean Architecture que gestione héroes, álbumes y cómics con integración IA (OpenAI + RAG), paneles de observabilidad y seguridad reforzada (CSRF, rate-limit, headers, sesiones con TTL/IP/UA y anti-replay pasivo).
