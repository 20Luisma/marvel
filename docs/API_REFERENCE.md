# 🌐 Referencia de API — Clean Marvel Album

## Core REST (álbumes y héroes)
| Método | Endpoint | Descripción |
|---------|-----------|-------------|
| GET | `/albums` | Lista todos los álbumes |
| POST | `/albums` | Crea un álbum nuevo |
| DELETE | `/albums/{albumId}` | Elimina un álbum |
| GET | `/albums/{albumId}/heroes` | Lista héroes del álbum |
| POST | `/albums/{albumId}/heroes` | Crea un héroe nuevo |
| DELETE | `/heroes/{heroId}` | Elimina un héroe |
| GET | `/notifications` | Lista notificaciones |
| DELETE | `/notifications` | Limpia el log |

## IA y panel cómic/RAG
| Método | Endpoint | Descripción |
|---------|-----------|-------------|
| POST | `/comics/generate` | Genera cómic Marvel con héroes elegidos (usa `openai-service`) |
| POST | `/api/rag/heroes.php` | Compara dos héroes con recuperación de contexto (via `rag-service`) |
| POST | `/api/tts-elevenlabs.php` | Narración de texto a audio (ElevenLabs) |

## Observabilidad y herramientas
| Método | Endpoint | Descripción |
|---------|-----------|-------------|
| GET | `/api/github-activity.php` | Actividad de Pull Requests del repo configurado |
| POST | `/api/accessibility-marvel.php` | Ejecuta WAVE API sobre páginas clave |
| POST | `/api/performance-marvel.php` | Lanza PageSpeed Insights para el sitio configurado |
| GET/POST | `/api/heatmap/*` | Reenvío de eventos y lecturas hacia el microservicio de heatmap externo |
| POST | `/api/sentry-test.php` | Inyecta eventos demo en Sentry (errores de prueba) |
| GET | `/api/sentry-metrics.php` | Consulta eventos recientes de Sentry con caché local |
| GET | `/api/sonar-metrics.php` | Métricas de calidad SonarCloud cacheadas |

## Microservicios IA (independientes)
| Servicio | Endpoint principal | Notas |
|----------|--------------------|-------|
| `openai-service` | `POST /v1/chat` | cURL a OpenAI; fallback JSON si falta `OPENAI_API_KEY`. Puerto por defecto: 8081. |
| `rag-service` | `POST /rag/heroes` | Recupera contexto desde `storage/knowledge/heroes.json` y delega a `openai-service`. Puerto por defecto: 8082. |

> Las rutas internas bajo `public/api/*.php` devuelven JSON y son consumidas por los paneles (`/comic`, `/panel-github`, `/accessibility`, `/performance`, `/sentry`, `/secret-heatmap`). Ajusta tokens y URLs en `.env` y `config/services.php`.
