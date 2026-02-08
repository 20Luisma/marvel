#!/bin/bash

# 🚀 DEV SERVER MANAGER v1.0 - Surgical Control
# Control total de los servicios: App (8080), OpenAI (8081) y RAG (8082)

PROJECT_ROOT="$( cd "$( dirname "${BASH_SOURCE[0]}" )/.." && pwd )"
CD_OPENAI="$PROJECT_ROOT/openai-service"
CD_RAG="$PROJECT_ROOT/rag-service"

# Configuración de puertos
PORT_APP=8080
PORT_OPENAI=8081
PORT_RAG=8082

# Colores para la terminal
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

log() { echo -e "${BLUE}[DEV-SERVER]${NC} $1"; }
success() { echo -e "${GREEN}✅ $1${NC}"; }
warn() { echo -e "${YELLOW}⚠️  $1${NC}"; }
error() { echo -e "${RED}❌ $1${NC}"; }

stop_service() {
    local port=$1
    local name=$2
    local pid=$(lsof -t -i :$port)
    if [ -n "$pid" ]; then
        log "Deteniendo $name en puerto $port (PID: $pid)..."
        kill -9 $pid 2>/dev/null
        success "$name detenido."
    else
        warn "$name no estaba corriendo en el puerto $port."
    fi
}

start_service() {
    local port=$1
    local name=$2
    local dir=$3
    local cmd=$4

    # Verificar si el puerto está ocupado
    if lsof -Pi :$port -sTCP:LISTEN -t >/dev/null ; then
        warn "Puerto $port ya ocupado por otro proceso. Intentando limpiar..."
        stop_service $port "$name"
    fi

    log "Arrancando $name en puerto $port..."
    cd "$dir"
    # Ejecutar en segundo plano redireccionando logs a un archivo temporal
    nohup $cmd > "/tmp/dev-server-$port.log" 2>&1 &
    
    # Esperar un poco para verificar que arrancó
    sleep 1
    if lsof -Pi :$port -sTCP:LISTEN -t >/dev/null ; then
        success "$name arrancado correctamente."
    else
        error "Error al arrancar $name. Revisa /tmp/dev-server-$port.log"
    fi
}

status() {
    echo "----------------------------------------------------------"
    echo " 📊 ESTADO DE LOS SERVICIOS LOCALES"
    echo "----------------------------------------------------------"
    for port in $PORT_APP $PORT_OPENAI $PORT_RAG; do
        if lsof -Pi :$port -sTCP:LISTEN -t >/dev/null ; then
            pid=$(lsof -t -i :$port)
            case $port in
                8080) name="APP PRINCIPAL   " ;;
                8081) name="OPENAI SERVICE  " ;;
                8082) name="RAG SERVICE     " ;;
            esac
            echo -e "${GREEN}●${NC} $name [PUERTO $port] [PID: $pid] -> ${BLUE}CORRIENDO${NC}"
        else
            case $port in
                8080) name="APP PRINCIPAL   " ;;
                8081) name="OPENAI SERVICE  " ;;
                8082) name="RAG SERVICE     " ;;
            esac
            echo -e "${RED}○${NC} $name [PUERTO $port] -> ${YELLOW}DETENIDO${NC}"
        fi
    done
    echo "----------------------------------------------------------"
}

case "$1" in
    start)
        if [ -n "$2" ]; then
            case "$2" in
                app) start_service $PORT_APP "App Principal" "$PROJECT_ROOT" "php -S localhost:$PORT_APP -t public" ;;
                openai) start_service $PORT_OPENAI "OpenAI Service" "$CD_OPENAI" "php -S localhost:$PORT_OPENAI -t public" ;;
                rag) start_service $PORT_RAG "RAG Service" "$CD_RAG" "php -S localhost:$PORT_RAG -t public" ;;
                *) error "Servicio desconocido: $2 (usa: app|openai|rag)" ;;
            esac
        else
            log "Iniciando todos los servicios..."
            start_service $PORT_APP "App Principal" "$PROJECT_ROOT" "php -S localhost:$PORT_APP -t public"
            start_service $PORT_OPENAI "OpenAI Service" "$CD_OPENAI" "php -S localhost:$PORT_OPENAI -t public"
            start_service $PORT_RAG "RAG Service" "$CD_RAG" "php -S localhost:$PORT_RAG -t public"
            success "Todos los servicios están en marcha."
        fi
        ;;
    stop)
        if [ -n "$2" ]; then
            case "$2" in
                app) stop_service $PORT_APP "App Principal" ;;
                openai) stop_service $PORT_OPENAI "OpenAI Service" ;;
                rag) stop_service $PORT_RAG "RAG Service" ;;
                *) error "Servicio desconocido: $2 (usa: app|openai|rag)" ;;
            esac
        else
            log "Deteniendo todos los servicios..."
            stop_service $PORT_APP "App Principal"
            stop_service $PORT_OPENAI "OpenAI Service"
            stop_service $PORT_RAG "RAG Service"
            success "Limpieza completada."
        fi
        ;;
    restart)
        $0 stop "$2"
        sleep 1
        $0 start "$2"
        ;;
    status)
        status
        ;;
    *)
        echo "Uso: $0 {start|stop|restart|status} [app|openai|rag]"
        exit 1
        ;;
esac

