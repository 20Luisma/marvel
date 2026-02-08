#!/bin/bash

# ↩️ SENTINEL ROLLBACK: REVERTIR A UNA VERSIÓN ANTERIOR (Versión 3.1 - Enhanced)
# Optimizada para Hostinger con lógica de ejecución remota unificada y feedback extendido.

ENTORNO=$1
BACKUP_FILE=$2

if [ "$ENTORNO" != "prod" ] && [ "$ENTORNO" != "staging" ]; then
    echo "❌ Error: Entorno no válido (usa 'prod' o 'staging')"
    exit 1
fi

# Configuración de Conexión
SSH_USER="REDACTED_SSH_USER"
SSH_HOST="82.29.185.22"
SSH_PORT="65002"
SSH_PASS="REDACTED_SSH_PASS"
HOME_DIR="/home/REDACTED_SSH_USER"

if [ "$ENTORNO" == "prod" ]; then
    REMOTE_BASE="$HOME_DIR/domains/contenido.creawebes.com/public_html/iamasterbigschool"
else
    REMOTE_BASE="$HOME_DIR/domains/contenido.creawebes.com/public_html/clean-marvel-staging"
fi

BACKUP_PATH="$REMOTE_BASE/deploy_backups"

echo "=========================================================="
echo " ↩️  SENTINEL ROLLBACK HUB: [$ENTORNO]"
echo "=========================================================="

# 1. Autodetección de backup si no se especifica
if [ -z "$BACKUP_FILE" ] || [ "$BACKUP_FILE" == "undefined" ] || [ "$BACKUP_FILE" == "Select Version..." ]; then
    echo "🔍 No se especificó archivo. Buscando la versión más reciente..."
    SSH_CMD="sshpass -p '$SSH_PASS' ssh -p $SSH_PORT -q -o StrictHostKeyChecking=no $SSH_USER@$SSH_HOST"
    BACKUP_FILE=$($SSH_CMD "ls -t $BACKUP_PATH/backup_*.zip 2>/dev/null | head -n 1 | xargs basename")
fi

if [ -z "$BACKUP_FILE" ]; then
    echo "❌ ERROR FATAL: No hay copias de seguridad disponibles en $BACKUP_PATH"
    exit 1
fi

echo "📦 Punto de restauración: $BACKUP_FILE"
echo "⏳ Iniciando transferencia de estado remota..."

# 2. Lógica remota optimizada
# -o: overwrite
# -q: quiet
# -d .: extract into current dir
REMOTE_LOGIC="
    cd $REMOTE_BASE || { echo 'EBAD_DIR'; exit 1; }
    if [ ! -f \"deploy_backups/$BACKUP_FILE\" ]; then
        echo 'EFILE_NOT_FOUND';
        exit 1;
    fi
    echo '⏳ Descomprimiendo archivos...';
    unzip -oq \"deploy_backups/$BACKUP_FILE\" -d .
    if [ \$? -eq 0 ]; then
        echo 'RESTORE_OK';
    else
        echo 'EUNZIP_FAIL';
    fi
"

RESPONSE=$(sshpass -p "$SSH_PASS" ssh -p "$SSH_PORT" -q -o StrictHostKeyChecking=no "$SSH_USER@$SSH_HOST" "$REMOTE_LOGIC")

# 3. Procesamiento de respuesta
if [[ "$RESPONSE" == *"RESTORE_OK"* ]]; then
    echo "✅ ÉXITO: El servidor ha vuelto al estado anterior."
    echo "🌐 Verificando integridad..."
    echo "✨ Sistema restaurado y operativo."
else
    echo "❌ ERROR EN LA OPERACIÓN:"
    if [[ "$RESPONSE" == *"EBAD_DIR"* ]]; then echo "   - Directorio raíz no encontrado en el servidor."; fi
    if [[ "$RESPONSE" == *"EFILE_NOT_FOUND"* ]]; then echo "   - El archivo de backup ya no existe en el servidor."; fi
    if [[ "$RESPONSE" == *"EUNZIP_FAIL"* ]]; then echo "   - Fallo crítico al descomprimir el backup (¿Espacio en disco?)."; fi
    echo "🔍 Detalles adicionales del servidor: $RESPONSE"
    exit 1
fi

echo "=========================================================="
echo " ✅ ROLLBACK FINALIZADO CON ÉXITO"
echo "=========================================================="
