# ADR-023: Sistema de Backup Automático de Base de Datos MySQL

**Estado:** Aceptado  
**Fecha:** 2026-02-19  
**Autores:** Marvel DevOps Team  

---

## Contexto

El proyecto Marvel utiliza una base de datos MySQL en producción (Hostinger) con tres tablas críticas: `albums`, `heroes` y `activity_logs`. Para cumplir con los estándares de calidad enterprise y garantizar la continuidad del negocio (Business Continuity), es necesario un sistema de backup que proteja los datos ante:

- Fallos del servidor de base de datos
- Errores humanos (borrado accidental)
- Migraciones fallidas
- Ataques o corrupción de datos

Las grandes organizaciones (bancos, aseguradoras, plataformas como Netflix) implementan estrategias formales de backup siguiendo normativas como **ISO 22301** (Business Continuity Management) y la regla **3-2-1**.

## Decisión

Implementamos un sistema de **Full Backup Diario con `mysqldump`**, con las siguientes características:

| Característica | Detalle |
|---------------|---------|
| Tipo | Full Backup comprimido (gzip -9) |
| Herramienta | `mysqldump` (estándar MySQL) |
| Retención | 7 días con rotación automática (~7 archivos máx, <2MB) |
| Integridad | Checksum SHA-256 por cada backup |
| Auditoría | Log de todas las operaciones |
| Modo transaccional | `--single-transaction` (sin bloqueos) |

## Archivos creados

```
scripts/
├── backup-marvel-db.sh    # Script principal de backup
└── restore-marvel-db.sh   # Script de restauración con confirmación

storage/backups/
├── marvel_db_YYYYMMDD_HHMMSS.sql.gz    # Backup comprimido
├── marvel_db_YYYYMMDD_HHMMSS.sql.gz.sha256  # Checksum SHA-256
└── backup-audit.log                    # Log de auditoría
```

## Proceso de Backup (4 pasos)

```
1. Verificar conectividad MySQL
       ↓
2. mysqldump --single-transaction + gzip -9
       ↓
3. Generar SHA-256 checksum (integridad)
       ↓
4. Rotación: eliminar backups > 7 días
```

## Ejecución

### Manual
```bash
./scripts/backup-marvel-db.sh
```

### Cron preparado para producción (24h)
El sistema está preparado para automatizarse. Para activarlo en el servidor:
```cron
# Backup automático cada 24h a las 3:00 AM (horario de bajo tráfico)
0 3 * * * /ruta/al/proyecto/scripts/backup-marvel-db.sh >> /var/log/marvel-backup.log 2>&1
```
> El script ya incluye el comando cron comentado. Solo descomentarlo en `crontab -e` en el servidor Hostinger.

### Restaurar un backup
```bash
./scripts/restore-marvel-db.sh storage/backups/marvel_db_20260219_030000.sql.gz
# Requiere escribir "RESTAURAR" para confirmar (prevención de errores)
```

## Alternativas consideradas

| Opción | Por qué no se eligió |
|--------|---------------------|
| **Percona XtraBackup** | Overkill para este tamaño de BD; requiere instalación extra |
| **Google Cloud SQL automático** | Coste adicional; la BD está en Hostinger |
| **Binlog / Replicación** | Requiere MySQL Master-Slave; infraestructura compleja |
| **phpMyAdmin export manual** | No automatizable; error-prone |

## Consecuencias

✅ **Positivas:**
- RPO (Recovery Point Objective) ≤ 24 horas
- RTO (Recovery Time Objective) ≤ 10 minutos
- Sin coste adicional (usa herramientas MySQL nativas)
- Auditoría completa de todas las operaciones
- Verificación de integridad antes de restaurar

⚠️ **A tener en cuenta:**
- Los backups se almacenan localmente (no offsite todavía)
- Para cumplir la regla 3-2-1 completa, añadir upload a Google Cloud Storage en futuras iteraciones

## Regla 3-2-1 (estado actual)

| Requisito | Estado |
|-----------|--------|
| **3 copias** de datos | ✅ BD activa + backups diarios (múltiples) |
| **2 medios** diferentes | ⚠️ Solo local (mejora futura: + GCS) |
| **1 copia offsite** | ⚠️ Pendiente (mejora futura: Google Cloud Storage) |

## Referencias

- [mysqldump docs](https://dev.mysql.com/doc/refman/8.0/en/mysqldump.html)
- ISO 22301 — Business Continuity Management
- [3-2-1 Backup Rule](https://www.backblaze.com/blog/the-3-2-1-backup-strategy/)
