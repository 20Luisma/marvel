# ADR-025: Replicación de Clicks del Heatmap — Write-to-Both con Cola de Sincronización

- **Estado**: Aceptado
- **Fecha**: 2026-02-18
- **Autor**: Luis Manuel

---

## Contexto

El sistema de heatmap almacena los clicks de los usuarios en un microservicio externo.
Existen dos nodos independientes:

- **Nodo 1 (GCP)** — `http://34.74.102.123:8080` — Servidor principal (Google Cloud, South Carolina)
- **Nodo 2 (AWS)** — `HEATMAP_API_SECONDARY_URL` — Servidor de failover (Amazon EC2, París)

El cliente anterior (`FailoverHeatmapApiClient`) implementaba **lectura con failover**:
intentaba GCP, y si fallaba, usaba AWS. Esto garantizaba disponibilidad pero **no consistencia de datos**:
si GCP caía con 300 clicks registrados y AWS recibía 50 clicks más durante la caída,
al recuperarse GCP esos 50 clicks solo existían en AWS.

## Decisión

Implementar `ReplicatedHeatmapApiClient` con estrategia **Write-to-Both + Cola de Sincronización**:

### Escritura (sendClick)
- Cada click se envía a **todos los nodos disponibles simultáneamente**.
- Si un nodo falla, el click se encola en disco (`/tmp/heatmap_pending_clicks.json`).
- La cola tiene un máximo de 5.000 entradas (FIFO, descarta los más antiguos si se llena).
- Cada entrada en cola registra: nodo destino, payload completo, timestamp y número de intentos.

### Sincronización automática (flushPendingQueue)
- Al inicio de cada request HTTP, el cliente intenta vaciar la cola.
- Para cada click pendiente, busca el nodo correspondiente y reintenta el envío.
- Si el reintento tiene éxito → el click se elimina de la cola (sincronizado ✅).
- Si sigue fallando → permanece en cola (máximo 10 intentos antes de descartar).

### Lectura (getSummary / getPages)
- Sin cambios: primer nodo disponible (GCP primero, AWS de fallback).

## Consecuencias

### Positivas
- ✅ **Consistencia eventual**: ambos nodos convergen al mismo estado de datos.
- ✅ **Sin pérdida de clicks**: los clicks se encolan si un nodo falla y se sincronizan al recuperarse.
- ✅ **Transparente**: el usuario nunca ve un error aunque un nodo esté caído.
- ✅ **Sin dependencia externa**: la cola usa el filesystem local, sin Redis ni base de datos adicional.
- ✅ **Auto-recuperación**: no requiere intervención manual para sincronizar.

### Negativas / Limitaciones
- ⚠️ **Consistencia eventual, no inmediata**: durante la caída de un nodo, los datos divergen temporalmente.
- ⚠️ **Cola en /tmp**: si el servidor PHP se reinicia, la cola persiste (LOCK_EX), pero si /tmp se limpia se pierden los clicks encolados.
- ⚠️ **Sin deduplicación**: si un click llega a ambos nodos pero uno responde tarde, podría duplicarse (improbable con timeout de 4s).

## Alternativas consideradas

| Alternativa | Motivo de descarte |
|-------------|-------------------|
| Base de datos compartida (PlanetScale/Supabase) | Introduce dependencia externa y coste adicional |
| Replicación maestro-esclavo (MySQL replication) | Requiere acceso SSH y configuración de red entre nodos |
| Redis Pub/Sub | Infraestructura adicional no justificada para el volumen actual |
| Write-to-Both sin cola | Pérdida de clicks si un nodo falla durante la escritura |

## Diagrama de flujo

```
Click usuario
     │
     ▼
ReplicatedHeatmapApiClient
     │
     ├──► GCP (Nodo 1) ──► OK ✅
     │
     └──► AWS (Nodo 2) ──► FALLA ❌
               │
               ▼
         Cola en disco
         /tmp/heatmap_pending_clicks.json
               │
               ▼ (próximo request)
         flushPendingQueue()
               │
               └──► AWS (Nodo 2) ──► OK ✅ (sincronizado)
```
