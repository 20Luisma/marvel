#!/bin/bash

################################################################################
# Script de Diagnóstico: Token Metrics en Hosting
# Ejecuta este script en el servidor de hosting para diagnosticar problemas
################################################################################

echo "🔍 DIAGNÓSTICO: Token Metrics Service"
echo "======================================="
echo ""

# Colores
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Función para check
check_file() {
    local file=$1
    local description=$2
    
    echo -n "Verificando $description... "
    if [ -f "$file" ]; then
        echo -e "${GREEN}✓ Existe${NC}"
        echo "  → Ruta: $file"
        echo "  → Tamaño: $(du -h "$file" | cut -f1)"
        echo "  → Permisos: $(ls -la "$file" | awk '{print $1}')"
        echo "  → Líneas: $(wc -l < "$file")"
        return 0
    else
        echo -e "${RED}✗ No existe${NC}"
        echo "  → Ruta esperada: $file"
        return 1
    fi
}

echo "1️⃣  ARCHIVOS DE LOG"
echo "-------------------"

# Archivo principal
check_file "storage/ai/tokens.log" "Log principal (comic_generator)"
echo ""

# Archivo RAG - Ruta relativa
check_file "rag-service/storage/ai/tokens.log" "Log RAG (ruta relativa)"
RAG_RELATIVE=$?
echo ""

# Verificar si rag-service es symlink
if [ -L "rag-service" ]; then
    echo -e "${YELLOW}ℹ  rag-service/ es un SYMLINK${NC}"
    echo "  → Apunta a: $(readlink -f rag-service 2>/dev/null || readlink rag-service)"
    
    # Intentar ruta absoluta conocida
    HOSTING_PATH="/home/REDACTED_SSH_USER/rag-service/storage/ai/tokens.log"
    echo ""
    check_file "$HOSTING_PATH" "Log RAG (ruta absoluta hosting)"
    RAG_ABSOLUTE=$?
elif [ -d "rag-service" ]; then
    echo -e "${GREEN}ℹ  rag-service/ es un DIRECTORIO REAL${NC}"
else
    echo -e "${RED}⚠️  rag-service/ NO EXISTE${NC}"
fi

echo ""
echo "2️⃣  VARIABLES DE ENTORNO"
echo "------------------------"

if [ -f ".env" ]; then
    echo -e "${GREEN}✓ Archivo .env existe${NC}"
    
    # Verificar RAG_LOG_PATH
    if grep -q "RAG_LOG_PATH=" .env 2>/dev/null; then
        echo -e "${GREEN}✓ RAG_LOG_PATH configurado:${NC}"
        grep "RAG_LOG_PATH=" .env | sed 's/^/  → /'
    else
        echo -e "${YELLOW}ℹ  RAG_LOG_PATH no configurado (usa detección automática)${NC}"
    fi
else
    echo -e "${RED}✗ Archivo .env no existe${NC}"
fi

echo ""
echo "3️⃣  PERMISOS PHP"
echo "-----------------"

# Crear script PHP temporal para verificar
cat > /tmp/check_rag_log.php << 'PHPEOF'
<?php
$paths = [
    'Relativo' => __DIR__ . '/rag-service/storage/ai/tokens.log',
    'Absoluto' => '/home/REDACTED_SSH_USER/rag-service/storage/ai/tokens.log',
];

foreach ($paths as $name => $path) {
    $exists = file_exists($path);
    $readable = is_readable($path);
    echo "$name: ";
    echo $exists ? "Existe " : "No existe ";
    echo $readable ? "| Legible" : "| No legible";
    echo "\n";
    echo "  → $path\n";
}

$envPath = getenv('RAG_LOG_PATH');
if ($envPath) {
    echo "Variable RAG_LOG_PATH: $envPath\n";
    echo "  → Existe: " . (file_exists($envPath) ? "Sí" : "No") . "\n";
}
PHPEOF

echo "Ejecutando verificación con PHP..."
php /tmp/check_rag_log.php
rm -f /tmp/check_rag_log.php

echo ""
echo "4️⃣  TEST DE SERVICIO"
echo "---------------------"

# Intentar ejecutar el script de verificación si existe
if [ -f "bin/verify-token-metrics.php" ]; then
    echo "Ejecutando script de verificación..."
    php bin/verify-token-metrics.php
else
    echo -e "${YELLOW}⚠️  Script verify-token-metrics.php no encontrado${NC}"
    echo "  Ejecuta: composer install (si no lo has hecho)"
fi

echo ""
echo "5️⃣  RECOMENDACIONES"
echo "--------------------"

# Analizar resultados y dar recomendaciones
if [ $RAG_RELATIVE -eq 0 ]; then
    echo -e "${GREEN}✅ La ruta relativa funciona correctamente${NC}"
    echo "   No necesitas configuración adicional."
elif [ ! -z "$RAG_ABSOLUTE" ] && [ $RAG_ABSOLUTE -eq 0 ]; then
    echo -e "${GREEN}✅ La ruta absoluta funciona${NC}"
    echo "   El sistema usará automáticamente el fallback a ruta absoluta."
else
    echo -e "${RED}❌ No se puede acceder al log RAG${NC}"
    echo ""
    echo "Soluciones posibles:"
    echo "1. Añade esta línea a tu archivo .env:"
    echo "   RAG_LOG_PATH=/home/REDACTED_SSH_USER/rag-service/storage/ai/tokens.log"
    echo ""
    echo "2. Verifica que el archivo existe y tiene permisos correctos:"
    echo "   chmod 644 /home/REDACTED_SSH_USER/rag-service/storage/ai/tokens.log"
    echo ""
    echo "3. Contacta soporte de hosting si los permisos no se pueden cambiar"
fi

echo ""
echo "======================================="
echo "Diagnóstico completado"
echo "======================================="
