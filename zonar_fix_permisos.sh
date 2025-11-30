#!/bin/bash

# ZONAR FIX 1.3 - Script de permisos para hosting
# Ejecutar en el servidor de hosting vía SSH

echo "🔧 ZONAR - Configurando permisos para storage/ai/tokens.log"

# Navegar al directorio del proyecto principal
cd /path/to/clean-marvel || exit 1

echo "📁 Verificando directorio storage/ai en proyecto principal..."
mkdir -p storage/ai
chmod 755 storage/ai
touch storage/ai/tokens.log
chmod 666 storage/ai/tokens.log

echo "✅ Proyecto principal configurado"

# Navegar al directorio del microservicio RAG
cd rag-service || exit 1

echo "📁 Verificando directorio storage/ai en rag-service..."
mkdir -p storage/ai
chmod 755 storage/ai
touch storage/ai/tokens.log
chmod 666 storage/ai/tokens.log

echo "✅ RAG Service configurado"

echo ""
echo "🔍 Verificando permisos finales..."
echo ""
echo "=== Proyecto Principal ==="
ls -la ../storage/ai/
echo ""
echo "=== RAG Service ==="
ls -la storage/ai/
echo ""

echo "✅ Script completado. Si ves errores de permisos, ejecuta:"
echo "   chown -R tuUsuario:www-data ../storage/ai/"
echo "   chown -R tuUsuario:www-data storage/ai/"
echo ""
echo "   (Reemplaza 'tuUsuario' y 'www-data' según tu configuración de hosting)"
