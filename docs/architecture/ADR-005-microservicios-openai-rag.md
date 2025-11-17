# ADR-005 – Elección de microservicios para OpenAI y RAG

## Estado
Accepted

## Contexto
Clean Marvel Album integra IA generativa y RAG para creación de cómics y comparaciones de héroes. Podríamos implementar esas capacidades directamente en el monolito PHP, pero el máster promueve separación de responsabilidades y reutilización.

## Decisión
Crear dos microservicios desacoplados:

1. `openai-service`: expone `POST /v1/chat` y actúa como proxy a la API de OpenAI. Recibe prompts y parámetros, los reenvía a OpenAI, y devuelve las respuestas procesadas (texto, estructura JSON para cómics).  
2. `rag-service`: encapsula lógica Retrieval-Augmented Generation. Maneja conocimiento (`storage/knowledge/heroes.json`), recupera contextos (HeroRetriever) y devuelve comparaciones. También puede invocar `openai-service` si necesita generación adicional.

El monolito PHP orquesta llamando a estos servicios vía HTTP (puertos `8081` y `8082` en local, URLs configuradas en `config/services.php`). No se interactúa directamente con la API de OpenAI desde la aplicación principal.

## Justificación
- Cada microservicio evoluciona independientemente y puede reutilizarse en otros proyectos.  
- Aíslan la complejidad de la IA del core de álbumes/héroes.  
- Muestran un caso real de microservicios + monolito para enseñanza.

## Consecuencias
### Positivas
- Permite escalar OpenAI y RAG por separado.  
- La misma arquitectura corre en local y en hosting solamente cambiando las URLs configuradas.  
### Negativas
- Requiere mantener tres servicios simultáneamente (monolito + microservicios).  
- Aumenta latencia y necesita manejo explícito de errores/circuit breakers.

## Opciones descartadas
- Integrar OpenAI/RAG en el monolito directamente (acoplamiento fuerte).  
- Usar un único microservicio que mezcle OpenAI y RAG (pierde modularidad).

## Supersede
Futuras decisiones podrían dividir aún más los microservicios (TTS, embeddings) o unificarlos si cambia la infraestructura IA.
