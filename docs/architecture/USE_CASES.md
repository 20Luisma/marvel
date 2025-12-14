# Casos de uso — Clean Marvel Album

| Caso de Uso | Descripción | Entrada | Salida | Evento |
|--------------|-------------|----------|---------|---------|
| Crear álbum | Crea un nuevo álbum con nombre y portada | `name`, `coverUrl` | JSON con álbum creado | `AlbumCreatedEvent` |
| Listar álbumes | Devuelve todos los álbumes registrados | — | Lista JSON | — |
| Actualizar álbum | Cambia nombre o portada de un álbum | `albumId`, `data` | JSON actualizado | `AlbumUpdatedEvent` |
| Eliminar álbum | Elimina un álbum y sus héroes | `albumId` | Mensaje de éxito | `AlbumDeletedEvent` |
| Crear héroe | Agrega héroe a un álbum | `albumId`, `name`, `imageUrl` | JSON con héroe | `HeroCreatedEvent` |
| Eliminar héroe | Elimina héroe específico | `heroId` | Mensaje de éxito | `HeroDeletedEvent` |
| Generar cómic IA | Genera historia Marvel con héroes seleccionados (OpenAI) | Héroes + prompt opcional | Texto del cómic | — (notifica actividad) |
| Comparar héroes RAG | Compara dos héroes usando contexto (`rag-service`) | `heroIds` (2 IDs), `question?` | Texto comparativo + contextos con score | — (notifica actividad) |
| Narrar texto (TTS) | Devuelve audio con voz ElevenLabs | `text`, `voiceId?` | Stream de audio | — |
| Consultar accesibilidad | Ejecuta WAVE sobre páginas clave | URLs predefinidas | Resumen WCAG (errores/contraste/alertas) | — |
| Métricas performance | Lanza PageSpeed Insights | URL objetivo | JSON con Core Web Vitals | — |
| Actividad GitHub | Lista PRs recientes del repo configurado | Fechas opcionales | Lista de PRs con meta | — |
