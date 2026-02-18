# ADR-023: Implementación de Failover Multi-Cloud para Microservicios Críticos

## Estado
**Actualizado** — Evolucionado a Write-to-Both con sincronización automática (ver ADR-025)

## Contexto
El microservicio Heatmap, aunque no es crítico para el funcionamiento básico de la aplicación, es una pieza clave de la analítica del proyecto. Actualmente reside exclusivamente en Google Cloud Platform (GCP). Una caída del proveedor o de la región dejaría al sistema sin capacidad de registro de interacciones.

## Decisión Original
Implementar un mecanismo de **Failover Activo-Pasivo** utilizando dos proveedores de nube distintos y regiones geográficamente separadas:

1.  **Primario (GCP)**: `us-east1-b` (Carolina del Sur, USA).
2.  **Secundario (AWS)**: `eu-west-3` (París, Francia).

### Detalles Técnicos e Infraestructura Real
- **Nodo Primario (GCP)**: `http://34.74.102.123:8080`
  - Ubicación: `us-east1-b` (Carolina del Sur).
  - Tecnología: VM e2-micro con Docker (Flask + SQLite).
- **Nodo Secundario (AWS)**: `http://35.181.60.162:8080`
  - Ubicación: `eu-west-3` (París).
  - Tecnología: Instancia EC2 t3.micro.

## Evolución — Write-to-Both + Cola de Sincronización (ADR-025)

La implementación inicial con `FailoverHeatmapApiClient` garantizaba **disponibilidad** pero no **consistencia de datos**: si GCP caía y AWS recibía clicks, al recuperarse GCP esos clicks solo existían en AWS.

Se ha evolucionado a `ReplicatedHeatmapApiClient` con estrategia **Write-to-Both**:

- **Escritura**: cada click se envía a **GCP y AWS simultáneamente**.
- **Cola persistente**: si un nodo falla, el click se encola en `storage/heatmap/pending_clicks.json` (sobrevive reinicios del servidor PHP).
- **Sincronización automática**: al inicio de cada request, `flushPendingQueue()` reenvía los clicks pendientes al nodo recuperado.
- **Lectura**: primer nodo disponible (GCP primero, AWS de fallback).

## Consecuencias
- ✅ **Alta Disponibilidad**: el sistema tolera la caída completa de un proveedor.
- ✅ **Consistencia Eventual**: ambos nodos convergen automáticamente al mismo estado de datos sin intervención manual.
- ✅ **Sin pérdida de clicks**: la cola persistente garantiza que ningún click se pierde aunque un nodo esté caído.
- ✅ **Transparente para el usuario**: nunca percibe fallo aunque un nodo esté caído.
- ⚠️ **Consistencia eventual, no inmediata**: durante la caída de un nodo los datos divergen temporalmente hasta la recuperación.

## Comparativa Multi-Cloud
| Característica | Google Cloud (Primario) | Amazon Web Services (Secundario) |
|---|---|---|
| **Región** | us-east1 (USA) | eu-west-3 (París) |
| **Instancia** | e2-micro | t3.micro |
| **Coste** | ~$1.00/mes | $0.00 (Free Tier) |
| **Estrategia** | Write-to-Both (activo) | Write-to-Both (activo) |
| **Sincronización** | Cola persistente automática | Cola persistente automática |
