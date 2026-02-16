#!/bin/bash

# 🚀 DEPLOY INTELIGENTE DINÁMICO (Versión 3.9.5 - Surgical Silence)
# Protección .env, Quality Gate y Exclusión de archivos basura/temporales.

ENTORNO=$1

if [ "$ENTORNO" != "prod" ] && [ "$ENTORNO" != "staging" ]; then
    echo "❌ Error: Debes especificar el entorno (prod o staging)"
    exit 1
fi

PROJECT_ROOT="$( cd "$( dirname "${BASH_SOURCE[0]}" )/.." && pwd )"
cd "$PROJECT_ROOT"

# Usar variables de entorno para evitar credenciales hardcodeadas en el repo
SSH_USER="${DEPLOY_SSH_USER:-}"
SSH_HOST="${DEPLOY_SSH_HOST:-82.29.185.22}"
SSH_PORT="${DEPLOY_SSH_PORT:-65002}"
SSH_PASS="${DEPLOY_SSH_PASS:-}"
HOME_DIR="/home/${SSH_USER:-REDACTED_SSH_USER}"

if [ -z "$SSH_USER" ] || [ -z "$SSH_PASS" ]; then
    echo "❌ Error: Las variables DEPLOY_SSH_USER y DEPLOY_SSH_PASS deben estar definidas en el entorno."
    exit 1
fi

REMOTE_WEB_ROOT="$HOME_DIR/domains/contenido.creawebes.com/public_html"

if [ "$ENTORNO" == "prod" ]; then
    REMOTE_BASE="$REMOTE_WEB_ROOT/iamasterbigschool"
    REMOTE_OPENAI="$REMOTE_WEB_ROOT/openai-service"
    REMOTE_RAG="$REMOTE_WEB_ROOT/rag-service"
else
    REMOTE_BASE="$REMOTE_WEB_ROOT/clean-marvel-staging"
    REMOTE_OPENAI="$REMOTE_BASE/openai-service"
    REMOTE_RAG="$REMOTE_BASE/rag-service"
fi

BACKUP_PATH="$REMOTE_BASE/deploy_backups"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)

echo "=========================================================="
echo " 🛡️  SENTINEL DEPLOY v3.9.5: [$ENTORNO]"
echo "=========================================================="

# --- 0. QUALITY GATE ---
echo "🧪 [QUALITY GATE] Ejecutando batería de tests (Unitarios + E2E)..."
if ./vendor/bin/phpunit > /dev/null 2>&1 && npx playwright test tests/e2e/surgical-production-check.spec.js --config=playwright.config.cjs > /dev/null 2>&1; then
    echo "✅ Tests validados. Estabilidad confirmada."
else
    echo "❌ ERROR: Los tests (PHPUnit o Playwright) han fallado. Despliegue ABORTADO."
    exit 1
fi

# --- 1. CREAR SNAPSHOT ---
echo "📦 Generando Snapshot de seguridad..."
REMOTE_LOGIC="mkdir -p $BACKUP_PATH && cd $REMOTE_BASE && zip -r $BACKUP_PATH/backup_$TIMESTAMP.zip . -x 'deploy_backups/*' 'vendor/*' 'node_modules/*' > /dev/null 2>&1; echo 'SENTINEL_OK'"
sshpass -p "$SSH_PASS" ssh -p "$SSH_PORT" -q -o StrictHostKeyChecking=no "$SSH_USER@$SSH_HOST" "$REMOTE_LOGIC" > /dev/null

# --- 2. RSYNC SYNC ---
echo "🔄 Sincronizando código (Limpieza quirúrgica activa)..."

SSH_CMD="ssh -p $SSH_PORT -o StrictHostKeyChecking=no"

# Exclusiones optimizadas para velocidad y limpieza
EXCLUDES=(
    --exclude='.env*'
    --exclude='.git*'
    --exclude='.github'
    --exclude='.scannerwork'
    --exclude='.phpunit.result.cache'
    --exclude='.phpunit.cache'
    --exclude='.vscode'
    --exclude='.DS_Store'
    --exclude='coverage.xml'
    --exclude='node_modules'
    --exclude='tests'
    --exclude='vendor'
    --exclude='storage/rate_limit/*'
    --exclude='public/uploads/*'
    --exclude='openai-service'
    --exclude='rag-service'
)

echo "[1/3] Sincronizando Lógica Principal..."
sshpass -p "$SSH_PASS" rsync -avz --size-only "${EXCLUDES[@]}" -e "$SSH_CMD" ./ "$SSH_USER@$SSH_HOST:$REMOTE_BASE/"

echo "[2/3] Sincronizando OpenAI Service..."
sshpass -p "$SSH_PASS" rsync -avz --size-only --exclude='.env*' --exclude='vendor' --exclude='.DS_Store' -e "$SSH_CMD" ./openai-service/ "$SSH_USER@$SSH_HOST:$REMOTE_OPENAI/"

echo "[3/3] Sincronizando RAG Service..."
sshpass -p "$SSH_PASS" rsync -avz --size-only --exclude='.env*' --exclude='vendor' --exclude='.DS_Store' -e "$SSH_CMD" ./rag-service/ "$SSH_USER@$SSH_HOST:$REMOTE_RAG/"

echo ""
echo "=========================================================="
echo " ✅ OPERACIÓN COMPLETADA: EL RAYO HA LLEGADO ⚡"
echo "=========================================================="
