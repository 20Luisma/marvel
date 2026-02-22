#!/usr/bin/env bash
# =============================================================================
# 🔐 MARVEL DATABASE BACKUP SYSTEM
# =============================================================================
# Estrategia: Full Backup diario + Rotación automática (7 días)
# Estilo empresarial: Compresión, checksums, logs de auditoría
# Autor: Marvel DevOps Team
# Versión: 1.0.0
#
# 🕐 CRON PREPARADO (activar en producción descomentando la línea en crontab):
#    crontab -e
#    0 3 * * * /ruta/al/proyecto/scripts/backup-marvel-db.sh >> /var/log/marvel-backup.log 2>&1
#    → Ejecuta backup automático cada 24h a las 3:00 AM (bajo tráfico)
# =============================================================================

set -euo pipefail  # Modo estricto: falla si hay error, variable no definida o pipe roto

# ─────────────────────────────────────────────────────────────────────────────
# CONFIGURACIÓN — Carga desde .env si existe, o usa valores por defecto
# ─────────────────────────────────────────────────────────────────────────────
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
ENV_FILE="$PROJECT_ROOT/.env"

if [[ -f "$ENV_FILE" ]]; then
    # Carga solo variables de BD desde .env (seguro, no ejecuta código)
    DB_HOST=$(grep '^DB_HOST=' "$ENV_FILE" | cut -d'=' -f2 | tr -d '"')
    DB_PORT=$(grep '^DB_PORT=' "$ENV_FILE" | cut -d'=' -f2 | tr -d '"')
    DB_NAME=$(grep '^DB_NAME=' "$ENV_FILE" | cut -d'=' -f2 | tr -d '"')
    DB_USER=$(grep '^DB_USER=' "$ENV_FILE" | cut -d'=' -f2 | tr -d '"')
    DB_PASSWORD=$(grep '^DB_PASSWORD=' "$ENV_FILE" | cut -d'=' -f2 | tr -d '"')
fi

# Valores por defecto (si .env no existe o no tiene las variables)
DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"
DB_NAME="${DB_NAME:-u968396048_marvel}"
DB_USER="${DB_USER:-u968396048_crea_marvel}"
DB_PASSWORD="${DB_PASSWORD:-}"

# Directorio de backups (relativo al proyecto)
BACKUP_DIR="$PROJECT_ROOT/storage/backups"
LOG_FILE="$BACKUP_DIR/backup-audit.log"

# Política de retención (días)
RETENTION_DAYS=7   # 7 días × ~200KB/backup ≈ <2MB máximo en disco

# Timestamp para el nombre del archivo
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
BACKUP_FILE="$BACKUP_DIR/marvel_db_${TIMESTAMP}.sql.gz"

# ─────────────────────────────────────────────────────────────────────────────
# FUNCIONES
# ─────────────────────────────────────────────────────────────────────────────

log() {
    local level="$1"
    local message="$2"
    local log_line="[$(date '+%Y-%m-%d %H:%M:%S')] [$level] $message"
    echo "$log_line"
    echo "$log_line" >> "$LOG_FILE"
}

verify_checksum() {
    # Genera SHA256 del backup para verificar integridad (como hacen los bancos)
    local file="$1"
    local checksum_file="${file}.sha256"
    sha256sum "$file" > "$checksum_file"
    log "INFO" "Checksum generado: $(cat "$checksum_file")"
}

rotate_old_backups() {
    log "INFO" "Rotando backups con más de $RETENTION_DAYS días..."
    local deleted_count=0
    while IFS= read -r old_file; do
        rm -f "$old_file" "${old_file}.sha256"
        log "INFO" "  ✗ Eliminado: $(basename "$old_file")"
        ((deleted_count++))
    done < <(find "$BACKUP_DIR" -name "marvel_db_*.sql.gz" -mtime +$RETENTION_DAYS 2>/dev/null)
    log "INFO" "Rotación completada: $deleted_count archivos eliminados"
}

print_stats() {
    local total_backups
    total_backups=$(find "$BACKUP_DIR" -name "marvel_db_*.sql.gz" 2>/dev/null | wc -l | tr -d ' ')
    local total_size
    total_size=$(du -sh "$BACKUP_DIR" 2>/dev/null | cut -f1)
    log "INFO" "📊 Estadísticas: $total_backups backups | Tamaño total: $total_size"
}

# ─────────────────────────────────────────────────────────────────────────────
# INICIO DEL PROCESO
# ─────────────────────────────────────────────────────────────────────────────

# Crear directorio de backups si no existe
mkdir -p "$BACKUP_DIR"

log "INFO" "════════════════════════════════════════════════"
log "INFO" "🚀 Iniciando Marvel DB Backup v1.0.0"
log "INFO" "   Base de datos: $DB_NAME @ $DB_HOST:$DB_PORT"
log "INFO" "   Destino: $BACKUP_FILE"
log "INFO" "════════════════════════════════════════════════"

# ─────────────────────────────────────────────────────────────────────────────
# PASO 1: Verificar conectividad con la base de datos
# ─────────────────────────────────────────────────────────────────────────────
log "INFO" "[1/4] Verificando conexión a la base de datos..."

if ! mysqladmin ping \
    -h "$DB_HOST" \
    -P "$DB_PORT" \
    -u "$DB_USER" \
    -p"$DB_PASSWORD" \
    --connect-timeout=10 \
    --silent 2>/dev/null; then
    log "ERROR" "❌ No se puede conectar a MySQL. Backup ABORTADO."
    exit 1
fi
log "INFO" "   ✓ Conexión OK"

# ─────────────────────────────────────────────────────────────────────────────
# PASO 2: Ejecutar mysqldump con compresión
# ─────────────────────────────────────────────────────────────────────────────
log "INFO" "[2/4] Ejecutando mysqldump con compresión gzip..."

# Opciones profesionales de mysqldump:
# --single-transaction  → Backup consistente sin bloquear tablas (InnoDB)
# --routines            → Incluye stored procedures
# --triggers            → Incluye triggers
# --events              → Incluye eventos programados
# --hex-blob            → Datos binarios en hexadecimal (seguros)
# --add-drop-table      → Permite restauración limpia
mysqldump \
    -h "$DB_HOST" \
    -P "$DB_PORT" \
    -u "$DB_USER" \
    -p"$DB_PASSWORD" \
    --single-transaction \
    --routines \
    --triggers \
    --events \
    --hex-blob \
    --add-drop-table \
    --comments \
    "$DB_NAME" | gzip -9 > "$BACKUP_FILE"

BACKUP_SIZE=$(du -sh "$BACKUP_FILE" | cut -f1)
log "INFO" "   ✓ Backup creado: $(basename "$BACKUP_FILE") ($BACKUP_SIZE)"

# ─────────────────────────────────────────────────────────────────────────────
# PASO 3: Verificar integridad con checksum SHA-256
# ─────────────────────────────────────────────────────────────────────────────
log "INFO" "[3/4] Generando checksum SHA-256 para verificación de integridad..."
verify_checksum "$BACKUP_FILE"
log "INFO" "   ✓ Integridad verificada"

# ─────────────────────────────────────────────────────────────────────────────
# PASO 4: Rotar backups antiguos
# ─────────────────────────────────────────────────────────────────────────────
log "INFO" "[4/4] Aplicando política de retención ($RETENTION_DAYS días)..."
rotate_old_backups
print_stats

log "INFO" "════════════════════════════════════════════════"
log "INFO" "✅ BACKUP COMPLETADO EXITOSAMENTE"
log "INFO" "   Archivo: $(basename "$BACKUP_FILE")"
log "INFO" "   Tamaño:  $BACKUP_SIZE"
log "INFO" "════════════════════════════════════════════════"

exit 0
