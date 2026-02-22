#!/usr/bin/env bash
# =============================================================================
# 🔄 MARVEL DATABASE RESTORE SYSTEM
# =============================================================================
# Restaura un backup específico con verificación de integridad previa
# Uso: ./restore-marvel-db.sh <archivo_backup.sql.gz>
# Ejemplo: ./restore-marvel-db.sh storage/backups/marvel_db_20260219_210000.sql.gz
# =============================================================================

set -euo pipefail

# ─────────────────────────────────────────────────────────────────────────────
# CONFIGURACIÓN
# ─────────────────────────────────────────────────────────────────────────────
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
ENV_FILE="$PROJECT_ROOT/.env"
LOG_FILE="$PROJECT_ROOT/storage/backups/restore-audit.log"

if [[ -f "$ENV_FILE" ]]; then
    DB_HOST=$(grep '^DB_HOST=' "$ENV_FILE" | cut -d'=' -f2 | tr -d '"')
    DB_PORT=$(grep '^DB_PORT=' "$ENV_FILE" | cut -d'=' -f2 | tr -d '"')
    DB_NAME=$(grep '^DB_NAME=' "$ENV_FILE" | cut -d'=' -f2 | tr -d '"')
    DB_USER=$(grep '^DB_USER=' "$ENV_FILE" | cut -d'=' -f2 | tr -d '"')
    DB_PASSWORD=$(grep '^DB_PASSWORD=' "$ENV_FILE" | cut -d'=' -f2 | tr -d '"')
fi

DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"
DB_NAME="${DB_NAME:-u968396048_marvel}"
DB_USER="${DB_USER:-u968396048_crea_marvel}"
DB_PASSWORD="${DB_PASSWORD:-}"

# ─────────────────────────────────────────────────────────────────────────────
# FUNCIONES
# ─────────────────────────────────────────────────────────────────────────────

log() {
    local level="$1"; local message="$2"
    local log_line="[$(date '+%Y-%m-%d %H:%M:%S')] [$level] $message"
    echo "$log_line"
    echo "$log_line" >> "$LOG_FILE"
}

verify_checksum() {
    local file="$1"
    local checksum_file="${file}.sha256"
    if [[ ! -f "$checksum_file" ]]; then
        log "WARN" "⚠️  No se encontró archivo de checksum. Continuando sin verificación."
        return 0
    fi
    if sha256sum --check "$checksum_file" --quiet 2>/dev/null; then
        log "INFO" "   ✓ Integridad verificada: checksum SHA-256 correcto"
    else
        log "ERROR" "❌ ¡CHECKSUM INVÁLIDO! El archivo puede estar corrupto. Restauración ABORTADA."
        exit 1
    fi
}

# ─────────────────────────────────────────────────────────────────────────────
# VALIDACIONES
# ─────────────────────────────────────────────────────────────────────────────

# Verificar argumento
if [[ $# -eq 0 ]]; then
    echo ""
    echo "❌ ERROR: Debes especificar el archivo de backup a restaurar."
    echo ""
    echo "Uso: $0 <archivo_backup.sql.gz>"
    echo ""
    echo "Backups disponibles:"
    find "$PROJECT_ROOT/storage/backups" -name "marvel_db_*.sql.gz" 2>/dev/null | sort -r | head -10
    echo ""
    exit 1
fi

BACKUP_FILE="$1"

# Verificar que el archivo existe
if [[ ! -f "$BACKUP_FILE" ]]; then
    echo "❌ ERROR: Archivo no encontrado: $BACKUP_FILE"
    exit 1
fi

# ─────────────────────────────────────────────────────────────────────────────
# CONFIRMACIÓN DE SEGURIDAD (como hacen los bancos)
# ─────────────────────────────────────────────────────────────────────────────
echo ""
echo "⚠️  ════════════════════════════════════════════════"
echo "⚠️  ADVERTENCIA: Esta operación SOBRESCRIBIRÁ la BD"
echo "⚠️  Base de datos: $DB_NAME"
echo "⚠️  Archivo:       $(basename "$BACKUP_FILE")"
echo "⚠️  ════════════════════════════════════════════════"
echo ""
read -r -p "¿Confirmas la restauración? Escribe 'RESTAURAR' para continuar: " CONFIRM

if [[ "$CONFIRM" != "RESTAURAR" ]]; then
    echo "Operación cancelada."
    exit 0
fi

# ─────────────────────────────────────────────────────────────────────────────
# PROCESO DE RESTAURACIÓN
# ─────────────────────────────────────────────────────────────────────────────
mkdir -p "$(dirname "$LOG_FILE")"

log "INFO" "════════════════════════════════════════════════"
log "INFO" "🔄 Iniciando Marvel DB Restore"
log "INFO" "   Archivo: $(basename "$BACKUP_FILE")"
log "INFO" "   BD destino: $DB_NAME @ $DB_HOST:$DB_PORT"
log "INFO" "════════════════════════════════════════════════"

log "INFO" "[1/3] Verificando integridad del backup..."
verify_checksum "$BACKUP_FILE"

log "INFO" "[2/3] Verificando conexión a la base de datos..."
if ! mysqladmin ping -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" -p"$DB_PASSWORD" --connect-timeout=10 --silent 2>/dev/null; then
    log "ERROR" "❌ No se puede conectar a MySQL. Restauración ABORTADA."
    exit 1
fi
log "INFO" "   ✓ Conexión OK"

log "INFO" "[3/3] Restaurando base de datos..."
gunzip -c "$BACKUP_FILE" | mysql \
    -h "$DB_HOST" \
    -P "$DB_PORT" \
    -u "$DB_USER" \
    -p"$DB_PASSWORD" \
    "$DB_NAME"

log "INFO" "════════════════════════════════════════════════"
log "INFO" "✅ RESTAURACIÓN COMPLETADA EXITOSAMENTE"
log "INFO" "   Backup usado: $(basename "$BACKUP_FILE")"
log "INFO" "════════════════════════════════════════════════"

exit 0
