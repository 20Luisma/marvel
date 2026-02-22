# 🏁 Informe de Cierre: Clean Marvel Album

## 🛡️ Hito de Ingeniería Consolidado
Se ha implementado con éxito el **Filtro de Calidad Quirúrgico** en el pipeline de CI/CD, garantizando la integridad de los microservicios de IA y la persistencia de datos.

## ✅ Lista de Verificación Final
- **Arquitectura**: Desacoplamiento total de capas (Clean Architecture).
- **Infraestructura**: Despliegue automatizado con Puerta de Calidad (Quality Gate).
- **IA**: Agente RAG y Generación de Cómics validados en Staging y Producción.
- **Observabilidad**: Distributed Tracing end-to-end con `trace_id` + Healthchecks proactivos (`/health`).
- **Disponibilidad**: Failover Multi-Cloud (GCP ↔ AWS) para servicios críticos.
- **Seguridad**: HSTS Preload + HMAC Strict Mode + Rate Limiting Granular por endpoint.
- **Documentación**: 13 mejoras identificadas, Roadmap futuro y presentación técnica actualizados.

## 🔍 Observabilidad: Distributed Tracing (trace_id)

### Problema resuelto
En una arquitectura de microservicios, cuando un error ocurre en un servicio interno (RAG, OpenAI), no era posible correlacionar ese error con la petición original del usuario. Los logs de cada servicio eran islas independientes.

### Solución implementada
Cada petición genera un identificador único (`trace_id`) que se propaga a través de todos los microservicios mediante el header `X-Trace-Id`:

```
Usuario → App Principal (genera trace_id=abc-123)
  ├── Log: [19:25:01] trace_id=abc-123 path=/api/rag/heroes
  └── → RAG Service (recibe trace_id=abc-123 via header)
       └── Log: [19:25:01] trace_id=abc-123 action=search_embeddings
```

### Archivos clave
| Archivo | Responsabilidad |
|---------|----------------|
| `src/Monitoring/TraceIdGenerator.php` | Genera UUID v4 por petición |
| `src/Bootstrap/EnvironmentBootstrap.php` | Asigna trace_id al inicio del ciclo |
| `src/Shared/Http/Router.php` | Loguea excepciones con trace_id |
| `src/Controllers/RagProxyController.php` | Propaga trace_id al RAG Service |
| `src/Heroes/Infrastructure/Rag/HeroRagSyncService.php` | Propaga trace_id en sync de héroes |
| `rag-service/public/index.php` | Recibe y reutiliza trace_id |

### Referencia
- **ADR-016**: `docs/architecture/ADR-016-trace-id-observability.md`
- **Patrón**: Distributed Tracing (mismo concepto que OpenTelemetry, Jaeger, Zipkin)

## 🏥 Healthchecks Proactivos

### Problema resuelto
No existía forma de saber si los microservicios estaban funcionando sin que un usuario reportara un error.

### Solución implementada
Endpoint `GET /health` en la App Principal que verifica proactivamente los 3 microservicios:

```json
{
  "status": "healthy",
  "trace_id": "a1b2c3d4-...",
  "environment": "production",
  "response_time_ms": 245,
  "services": {
    "app": { "status": "healthy", "response_time_ms": 0.1 },
    "rag-service": { "status": "healthy", "response_time_ms": 120 },
    "openai-service": { "status": "healthy", "response_time_ms": 124 }
  }
}
```

Siempre devuelve HTTP 200 (patrón AWS/GitHub), con `"status": "degraded"` en el body si algún servicio falla.

### Archivo clave
| Archivo | Responsabilidad |
|---------|----------------|
| `src/Controllers/HealthCheckController.php` | Orquesta verificación de los 3 servicios |

## 🚦 Rate Limiting Granular

### Problema resuelto
Todos los endpoints compartían el mismo límite (60 req/min), permitiendo abuso de endpoints costosos (IA) o destructivos (admin).

### Solución implementada
Límites específicos por categoría de endpoint:

| Categoría | Límite | Ejemplos |
|-----------|--------|----------|
| IA (costosos) | 5-10/min | `/comics/generate`, `/agentia` |
| Login | 10/min | `/login` |
| Admin | 2-3/min | `/admin/seed-all`, `/dev/tests/run` |
| Paneles | 20-30/min | `/secret-heatmap`, `/panel-github` |

### Archivo clave
| Archivo | Responsabilidad |
|---------|----------------|
| `src/Bootstrap/SecurityBootstrap.php` | Configuración de `$routeLimits` por endpoint |

## 🔌 Cliente LLM Desacoplado (Dependency Inversion)

### Problema resuelto
`ComicController` dependía directamente de `OpenAIComicGenerator`. Si se quisiera usar Claude, Gemini o Llama, habría que reescribir el controller y toda la cadena de inyección.

### Solución implementada
Interfaz `ComicGeneratorInterface` que define el contrato (`isConfigured()` + `generateComic()`). `OpenAIComicGenerator` es ahora un adapter que implementa esa interfaz:

```
ComicController → ComicGeneratorInterface → OpenAIComicGenerator (adapter)
                                           → ClaudeAdapter (futuro)
                                           → GeminiAdapter (futuro)
```

### Archivos clave
| Archivo | Responsabilidad |
|---------|----------------|
| `src/AI/ComicGeneratorInterface.php` | Contrato abstracto para cualquier LLM |
| `src/AI/OpenAIComicGenerator.php` | Adapter concreto para OpenAI |
| `src/Controllers/ComicController.php` | Depende de la interfaz, no del concreto |

## 🏗️ Refactor de Capa de Aplicación (GenerateComicUseCase)

### Problema resuelto
El controlador de cómics acumulaba demasiada lógica de negocio (orquestación de búsqueda de héroes + validación + llamadas a infraestructura). Esto violaba el **Single Responsibility Principle (SRP)**.

### Solución implementada
Se ha extraído la lógica a un nuevo **Servicio de Aplicación**: `GenerateComicUseCase`. El controlador ahora es "Skinny", delegando el 100% de la lógica a la capa superior.

## 📂 Abstracción de Filesystem (DIP en Almacenamiento)

### Problema resuelto
La subida de portadas de álbumes estaba acoplada a funciones nativas de PHP (`move_uploaded_file`), impidiendo el testeo unitario limpio y la portabilidad a nubes como AWS S3 sin reescribir la lógica.

### Solución implementada
- **FilesystemInterface**: Define un contrato para guardar archivos y obtener URLs.
- **LocalFilesystem**: Implementación concreta para desarrollo local y hosting tradicional.
- **UploadAlbumCoverUseCase**: Orquesta la subida (validación, nombres seguros, persistencia) desacoplando la lógica de negocio de la infraestructura física.

### Archivos clave
| Archivo | Responsabilidad |
|---------|----------------|
| `src/Application/Comics/GenerateComicUseCase.php` | Orquestación completa de la funcionalidad |
| `src/Controllers/ComicController.php` | Solo maneja HTTP Request/Response (Skinny Controller) |

## 🤖 Recomendador por Similitud: Películas Marvel

### Problema resuelto
El proyecto consumía IA exclusivamente a través de APIs externas (OpenAI), sin implementar ningún modelo de recomendación propio. Para un TFM de un máster de IA, era necesario demostrar capacidad de diseñar e integrar un recomendador basado en similitud.

### Solución implementada
Recomendador de películas Marvel basado en **KNN (K-Nearest Neighbors)** con distancia Euclidiana + **Jaccard Similarity** para comparación textual. Implementado con **PHP-ML**, compatible con hosting compartido.

**Flujo técnico:**
```
Película seleccionada → Feature Extraction → KNN Distance + Jaccard Text → Top-N similares
```

## ☁️ Failover Multi-Cloud: Alta Disponibilidad (DRP)

### Problema resuelto
El microservicio Heatmap (Python/GCP) representaba un "Single Point of Failure". Si Google Cloud o la región de Carolina del Sur caían, la analítica del proyecto dejaba de funcionar.

### Solución implementada
Estrategia de **Disaster Recovery Plan (DRP)** mediante una réplica activa en AWS. El sistema detecta caídas y conmuta automáticamente entre proveedores:

1.  **Réplica en AWS**: Despliegue de una instancia EC2 (Ubuntu + Docker) en la región de París con copia exacta del servicio Heatmap.
2.  **FailoverHeatmapApiClient**: Un nuevo cliente PHP que implementa el patrón de reintento secuencial.
3.  **Lógica Decisional**:
    ```
    App PHP → Reintento 1: GCP (us-east-1)
      └── Fallo (Timeout/5xx) → Reintento 2: AWS (eu-west-3)
    ```

### Beneficios para el TFM
- Demuestra capacidad de orquestación en entornos **Multi-Cloud**.
- Implementación de patrones de resiliencia y tolerancia a fallos.
- Independencia de proveedor (Cloud Agnostic).

### Archivos clave
| Archivo | Responsabilidad |
|---------|----------------|
| `src/Heatmap/Infrastructure/FailoverHeatmapApiClient.php` | Lógica de conmutación automática |
| `src/Bootstrap/AppBootstrap.php` | Inyección de endpoints GCP y AWS |
| `docs/architecture/ADR-023-multi-cloud-failover.md` | Justificación del diseño |
| `docs/architecture/ADR-022-gcp-cloud-optimization.md` | Auditoría y optimización GCP |
| `docs/architecture/ADR-024-aws-cloud-optimization.md` | Auditoría y optimización AWS |

## 🤖 Recomendador por Similitud: Películas Marvel

**Features del modelo:**
| Feature | Tipo | Normalización |
|---------|------|---------------|
| `vote_average` | Numérico | 0-1 (dividido por 10) |
| `release_year` | Numérico | 0-1 (rango 2008-2030) |
| `overview_length` | Numérico | 0-1 (max 500 chars) |
| `overview_words` | Texto | Jaccard similarity con stop words ES/EN |

**Pesos:** 60% features numéricos, 40% similitud textual.

### Arquitectura (Clean Architecture)
| Capa | Archivo | Responsabilidad |
|------|---------|----------------|
| Domain | `src/Movies/Domain/MovieRecommenderInterface.php` | Contrato abstracto |
| Application | `src/Movies/Application/RecommendMoviesUseCase.php` | Orquestación |
| Infrastructure | `src/Movies/Infrastructure/ML/PhpMlMovieRecommender.php` | Implementación ML |
| API | `public/api/movie-recommend.php` | Endpoint REST |
| Tests | `tests/Movies/MovieRecommenderTest.php` | 12 tests, 81 assertions (98.36% coverage) |

### Referencia
- **ADR-021**: `docs/architecture/ADR-021-ml-movie-recommender.md`
- **Librería**: PHP-ML 0.10 (`php-ai/php-ml`)
- **Endpoint**: `GET /api/movie-recommend.php?id={tmdb_id}&limit=5`

## ☁️ FinOps: Auditoría y Optimización de Costes en Google Cloud

### Problema resuelto
El microservicio Heatmap corre sobre una VM `e2-micro` en Google Cloud (proyecto `marvel-479213`). Tras meses de operación, se acumularon **recursos innecesarios** generando costes evitables, **reglas de firewall redundantes** que ampliaban la superficie de ataque, y **APIs habilitadas sin uso real**. No existía una auditoría formal de la infraestructura cloud.

### Auditoría técnica realizada

Se ejecutó una auditoría completa del proyecto GCP con `gcloud CLI`, verificando:

| Recurso auditado | Estado previo | Hallazgo |
|-------------------|--------------|----------|
| VM `headmap` (`e2-micro`) | RUNNING 24/7 (85 días) | ✅ Correcto — free tier eligible |
| Disco 10 GB `pd-balanced` | Asociado a la VM | ✅ Correcto |
| 14 snapshots incrementales | 3.63 GB reales | 🔴 Innecesarios — datos reconstruibles (1.3 MB) |
| Schedule diario (×2 regiones) | Activo desde Nov 2025 | 🔴 Desproporcionado para el caso de uso |
| 8 reglas de firewall | 4 redundantes/peligrosas | 🔴 Superficie de ataque innecesaria |
| 24 APIs habilitadas | 7 BigQuery/Data sin uso | 🟡 Riesgo de coste por compromiso de credenciales |

### Validaciones de seguridad pre-eliminación

Antes de ejecutar cambios, se verificó en la VM vía SSH:
- **Sin dependencia de snapshots**: No hay crontab, scripts, ni pipelines de restore.
- **Sin marcas de criticidad**: Disco sin labels, VM sin deletion protection.
- **Datos reconstruibles**: `heatmap.db` pesa 1.3 MB y se regenera automáticamente.
- **IP efímera**: No hay IPs estáticas reservadas (ahorro implícito: $2.88/mes).

### Optimizaciones ejecutadas

#### 1. Eliminación de snapshots y schedule
- **Eliminados**: 14 snapshots diarios (3.63 GB de almacenamiento incremental).
- **Eliminados**: 2 schedules (`default-schedule-1` en `us-east1` y `europe-west4`).
- **Ahorro**: ~$0.10/mes en almacenamiento de snapshots.
- **Justificación**: Ratio coste/protección absurdo — snapshots de disco de 10 GB para proteger 1.3 MB de datos analíticos no críticos.

#### 2. Hardening de firewall

| Regla eliminada | Puerto | Razón |
|-----------------|--------|-------|
| `allow-8080-everywhere` | tcp:8080 | Duplicada con `allow-heatmap-8080` |
| `default-allow-rdp` | tcp:3389 → 0.0.0.0/0 | Remote Desktop abierto al mundo en VM Linux |
| `default-allow-http` | tcp:80 | Sin uso — servicio solo en 8080 |
| `default-allow-https` | tcp:443 | Sin uso — sin TLS configurado |

**Resultado**: Puertos públicos reducidos de 5 a 2 (8080 + SSH).

#### 3. Desactivación de APIs innecesarias
Desactivadas 7 APIs (BigQuery, Dataplex, Dataform, Analytics Hub) que no son utilizadas por el proyecto. Reduce la superficie de ataque ante compromiso de credenciales.

### Análisis de costes — Antes vs Después

| Recurso | Coste antes | Coste después | Ahorro |
|---------|-------------|---------------|--------|
| VM `e2-micro` (free tier) | $0.00/mes | $0.00/mes | — |
| Disco 10 GB `pd-balanced` | $1.00/mes | $1.00/mes | — |
| Snapshots (14 × incremental) | $0.094/mes | $0.00/mes | $0.094 |
| IP estática (no reservada) | $0.00/mes | $0.00/mes | — |
| APIs sin uso | $0 (riesgo) | Eliminadas | Prevención |
| **Total mensual** | **~$1.10** | **~$1.00** | **$0.10 + seguridad** |

### Decisión arquitectónica: VM vs Cloud Run

Se evaluó migrar el microservicio a Cloud Run (serverless, escala a cero):

| Criterio | VM `e2-micro` | Cloud Run + Cloud SQL |
|----------|--------------|----------------------|
| Coste mensual | ~$1.00 (free tier) | ~$8-12 (Cloud SQL $7/mes mínimo) |
| Persistencia | SQLite nativo | Requiere Cloud SQL/Firestore |
| Cold start | N/A | 2-5s (Python/Flask) |
| Complejidad | Ya funciona | Reescritura de capa de datos |

**Decisión**: Mantener la VM. Migrar a Cloud Run **multiplicaría el coste ×8** sin beneficio tangible. La VM `e2-micro` es free-tier eligible y el servicio tiene 85+ días de uptime sin incidentes.

### Archivos clave
| Archivo | Responsabilidad |
|---------|----------------|
| `docs/architecture/ADR-022-gcp-cloud-optimization.md` | Decisión arquitectónica documentada |
| `public/api/heatmap/summary.php` | Proxy corregido (bug `page` → `page_url`) |
| `public/api/heatmap/pages.php` | Proxy corregido (limit 100 → 50000) |

### Referencia
- **ADR-022**: `docs/architecture/ADR-022-gcp-cloud-optimization.md`
- **Principios FinOps**: Visibilidad, optimización, gobernanza de costes cloud
- **Google Cloud Free Tier**: [cloud.google.com/free](https://cloud.google.com/free)

## 🛡️ Sistema de Backup de Base de Datos (Estrategia Empresarial)

### Problema resuelto
El proyecto carecía de un sistema formal de respaldo de la base de datos MySQL en producción, necesario para cumplir con los estándares de **Business Continuity** que aplican en entornos enterprise (bancos, aseguradoras, plataformas SaaS).

### Solución implementada
Sistema de **Full Backup Automático** con `mysqldump`, al estilo de grandes organizaciones:

| Característica | Detalle |
|---------------|---------|
| Tipo | Full Backup comprimido (`gzip -9`) |
| Retención | 7 días con rotación automática (~7 archivos, <2MB) |
| Integridad | Checksum **SHA-256** por cada backup |
| Auditoría | Log detallado de todas las operaciones |
| Modo transaccional | `--single-transaction` (sin bloqueos en producción) |
| Cron preparado | Cada 24h a las 3:00 AM (listo para activar en Hostinger) |

**Flujo del proceso (4 pasos automáticos):**
```
1. Verificar conectividad MySQL
       ↓
2. mysqldump --single-transaction + gzip -9
       ↓
3. Generar SHA-256 (verificación de integridad)
       ↓
4. Rotación automática: eliminar backups > 7 días
```

### Cron preparado para producción (no activado)
```cron
# Backup automático cada 24h a las 3:00 AM
0 3 * * * /ruta/proyecto/scripts/backup-marvel-db.sh >> /var/log/marvel-backup.log 2>&1
```

### Archivos clave
| Archivo | Responsabilidad |
|---------|----------------|
| `scripts/backup-marvel-db.sh` | Script principal de backup con rotación |
| `scripts/restore-marvel-db.sh` | Restauración con verificación de integridad y confirmación |
| `storage/backups/` | Directorio de almacenamiento de backups |

### Referencia
- **ADR-023**: `docs/architecture/ADR-023-database-backup-strategy.md`
- **Patrones**: ISO 22301 Business Continuity, Regla 3-2-1, RPO/RTO

---
*Proyecto finalizado con criterios académicos sólidos y trazabilidad técnica.*
