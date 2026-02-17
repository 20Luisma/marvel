# ADR-022 — Google Cloud Platform: Auditoría FinOps y Security Hardening

**Fecha:** 17 de Febrero de 2026  
**Estado:** ✅ Implementada  
**Categoría:** Infraestructura / FinOps / Seguridad  
**Impacto:** Reducción de coste, eliminación de superficie de ataque, optimización de recursos

---

## Contexto

El microservicio **Heatmap** (Python 3.10 + Flask + Docker) corre sobre una VM `e2-micro` en Google Cloud Compute Engine (`us-east1-b`). Este servicio registra los clics reales de los usuarios para generar mapas de calor de interacción en el panel `/secret-heatmap`.

Tras una auditoría técnica exhaustiva del proyecto GCP `marvel-479213`, se identificaron **recursos innecesarios** generando coste, **reglas de firewall redundantes o peligrosas**, y **APIs habilitadas sin uso real**.

---

## Auditoría realizada

### Inventario previo

| Recurso | Estado antes | Problema |
|---------|-------------|----------|
| VM `headmap` (`e2-micro`) | RUNNING 24/7, 85 días uptime | ✅ Correcto (free tier eligible) |
| Disco `headmap` (10 GB, `pd-balanced`) | Asociado a la VM | ✅ Correcto |
| 14 snapshots incrementales | 3.63 GB reales (~$0.10/mes) | 🔴 Innecesarios para un servicio reconstruible |
| Schedule `default-schedule-1` × 2 regiones | Diario a las 21:00, retención 14 días | 🔴 Desproporcionado para 1.3 MB de datos útiles |
| 8 reglas de firewall | 4 redundantes/peligrosas | 🔴 Superficie de ataque innecesaria |
| 24 APIs habilitadas | 7 APIs BigQuery/Data sin uso | 🟡 Superficie de ataque ampliada sin beneficio |

### Validaciones de seguridad pre-eliminación

Antes de ejecutar cualquier cambio, se verificó:

1. **Sin dependencia de snapshots**: No existe crontab, systemd timer, startup script, ni pipeline de restore que use los snapshots.
2. **Sin restore pipeline**: La policy `default-schedule-1` fue creada automáticamente al crear la VM — nunca fue integrada en un proceso de recuperación.
3. **Sin marcas de criticidad**: El disco no tiene labels, description, ni deletion protection habilitada.
4. **Datos reconstruibles**: La DB SQLite (`heatmap.db`) pesa 1.3 MB y se regenera automáticamente al arrancar el contenedor. Los datos de clics son analíticos, no transaccionales.
5. **IP efímera**: No hay IPs estáticas reservadas. La IP `34.74.102.123` es efímera (gratuita mientras la VM corra).

---

## Decisiones

### Decisión 1: Eliminar snapshots e schedule diario

**Contexto**: Un disco de 10 GB genera snapshots diarios incrementales de ~70-440 MB cada uno, para proteger una DB de 1.3 MB.

**Análisis coste/beneficio**:

| Concepto | Valor |
|----------|-------|
| Tamaño real total snapshots | 3.63 GB (incrementales) |
| Coste mensual snapshots | ~$0.094 |
| Datos críticos en el disco | 1.3 MB (heatmap.db) |
| Tiempo de reconstrucción total | < 5 minutos (docker build + run) |
| Alternativa viable | Backup manual de `heatmap.db` (1.3 MB) bajo demanda |

**Decisión**: Eliminar el schedule y los 14 snapshots.  
**Justificación**: El ratio coste/protección es absurdo — snapshots de 3.63 GB para 1.3 MB de datos no críticos y completamente reconstruibles.

### Decisión 2: Hardening de firewall

**Reglas eliminadas**:

| Regla | Puerto | Razón de eliminación |
|-------|--------|---------------------|
| `allow-8080-everywhere` | tcp:8080 → 0.0.0.0/0 | **Duplicada** con `allow-heatmap-8080` |
| `default-allow-rdp` | tcp:3389 → 0.0.0.0/0 | **Riesgo crítico**: Remote Desktop abierto al mundo en una VM Linux |
| `default-allow-http` | tcp:80 → 0.0.0.0/0 | **Sin uso**: el servicio solo escucha en 8080 |
| `default-allow-https` | tcp:443 → 0.0.0.0/0 | **Sin uso**: el servicio no tiene TLS |

**Reglas conservadas**:

| Regla | Puerto | Razón |
|-------|--------|-------|
| `allow-heatmap-8080` | tcp:8080 | Tráfico web → microservicio |
| `default-allow-ssh` | tcp:22 | Administración remota |
| `default-allow-icmp` | icmp | Diagnóstico (ping) |
| `default-allow-internal` | all → 10.128.0.0/9 | Tráfico interno GCP |

**Resultado**: Superficie de ataque reducida de **5 puertos abiertos al mundo** a **2** (8080 + 22).

### Decisión 3: Desactivar APIs innecesarias

**APIs desactivadas** (7):

- `analyticshub.googleapis.com`
- `bigquerydatatransfer.googleapis.com`
- `bigquerymigration.googleapis.com`
- `bigqueryconnection.googleapis.com`
- `bigquerydatapolicy.googleapis.com`
- `dataform.googleapis.com`
- `dataplex.googleapis.com`

**Justificación**: Ninguna de estas APIs es utilizada por el proyecto. Mantenerlas habilitadas amplía la superficie de ataque — un compromiso de credenciales podría crear recursos BigQuery/Dataplex que generarían coste.

### Decisión 4: No migrar a Cloud Run

**Análisis de alternativa serverless**:

| Criterio | VM `e2-micro` | Cloud Run |
|----------|--------------|-----------|
| Coste mensual | ~$1.00 (free tier + disco) | ~$0-0.50 (sin DB) / ~$8-12 (con Cloud SQL) |
| Persistencia | SQLite nativo | Requiere Cloud SQL (~$7/mes) o Firestore |
| Cold start | N/A | 2-5s (Python/Flask) |
| Complejidad | Ya funciona | Reescritura de capa de datos |
| Disponibilidad | 24/7 | Escala a cero (latencia primer request) |

**Decisión**: Mantener la VM `e2-micro`.  
**Justificación**: El coste mensual total es ~$1.00 con free tier. Migrar a Cloud Run requeriría reescribir la capa de datos (SQLite → Cloud SQL/Firestore), lo que introduce complejidad y potencialmente mayor coste sin beneficio tangible. El servicio actual tiene 85 días de uptime continuo sin incidentes.

---

## Estado final del proyecto GCP

### Recursos activos

```
marvel-479213/
├── Compute Engine
│   └── headmap (e2-micro, us-east1-b, RUNNING)
│       ├── IP: 34.74.102.123 (efímera)
│       ├── Disco: 10 GB pd-balanced
│       └── Docker: heatmap-service:latest (Flask + SQLite)
├── Networking
│   ├── VPC: default (regional)
│   └── Firewall: 4 reglas (heatmap-8080, ssh, icmp, internal)
└── APIs: 17 servicios esenciales
```

### Costes mensuales

| Recurso | Antes | Después |
|---------|-------|---------|
| VM `e2-micro` | $0 (free tier) | $0 (free tier) |
| Disco 10 GB `pd-balanced` | $1.00 | $1.00 |
| Snapshots (14 × incremental) | $0.10 | $0.00 |
| IPs estáticas | $0.00 | $0.00 |
| APIs sin uso | $0 (riesgo) | Eliminadas |
| **Total** | **~$1.10/mes** | **~$1.00/mes** |

---

## Consecuencias

### Positivas
- Proyecto GCP limpio y auditado a nivel profesional
- Superficie de ataque reducida (firewall: 5 → 2 puertos públicos)
- Sin recursos huérfanos ni schedules olvidados
- Decisión de no migrar a Cloud Run documentada con análisis técnico
- Coste optimizado al mínimo posible (~$1.00/mes)

### Riesgos aceptados
- La IP `34.74.102.123` es efímera — podría cambiar si la VM se reinicia (mitigación: el código PHP tiene fallback configurable vía `HEATMAP_API_BASE_URL`)
- Sin backups automáticos — aceptable dado que `heatmap.db` contiene datos analíticos reconstruibles
- SSH abierto a `0.0.0.0/0` — idealmente debería restringirse a IPs conocidas (mejora futura)

---

## Referencias

- [Google Cloud Free Tier](https://cloud.google.com/free/docs/free-cloud-features#compute)
- [Snapshot Pricing](https://cloud.google.com/compute/disks-image-pricing#persistentdisk)
- [FinOps Foundation](https://www.finops.org/)
- Panel interno: `/secret-cloud-ops`
