#!/bin/bash

# Script específico para solucionar error de validación de conexión WhatsApp
# Error: "error in validating connection"

set -e

# Colores para output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

print_status() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

print_header() {
    echo -e "${BLUE}=== $1 ===${NC}"
}

print_header "Solución Error de Validación WhatsApp"

# 1. Detener cualquier proceso de Node.js
print_status "Deteniendo procesos de Node.js..."
pkill -f "node src/index.js" 2>/dev/null || true
sleep 2

# 2. Verificar si hay procesos ejecutándose
if pgrep -f "node src/index.js" > /dev/null; then
    print_warning "Forzando cierre de procesos..."
    pkill -9 -f "node src/index.js" 2>/dev/null || true
    sleep 1
fi

# 3. Limpiar completamente las sesiones
print_status "Limpiando sesiones de WhatsApp..."
if [ -d "sessions" ]; then
    rm -rf sessions/*
    print_status "Sesiones eliminadas ✓"
else
    mkdir -p sessions
    print_status "Directorio de sesiones creado ✓"
fi

# 4. Limpiar logs antiguos
print_status "Limpiando logs antiguos..."
if [ -d "logs" ]; then
    rm -f logs/*.log 2>/dev/null || true
    print_status "Logs limpiados ✓"
else
    mkdir -p logs
    print_status "Directorio de logs creado ✓"
fi

# 5. Verificar archivo .env
if [ ! -f ".env" ]; then
    print_warning "Archivo .env no encontrado. Creando desde ejemplo..."
    cp env.example .env
    print_warning "Por favor configura las variables en .env antes de continuar"
    exit 1
fi

# 6. Verificar dependencias
if [ ! -d "node_modules" ]; then
    print_status "Instalando dependencias..."
    npm install
fi

# 7. Verificar que no hay otros servicios usando el puerto 3003
print_status "Verificando puerto 3003..."
if lsof -i :3003 >/dev/null 2>&1; then
    print_warning "Puerto 3003 está en uso. Liberando..."
    lsof -ti :3003 | xargs kill -9 2>/dev/null || true
    sleep 2
fi

# 8. Crear archivo de configuración temporal para debug
print_status "Creando configuración de debug..."
cat > .env.debug << EOF
# Configuración de debug para WhatsApp
NODE_ENV=development
PORT=3003
LOG_LEVEL=debug
WHATSAPP_SESSION_PATH=./sessions
RECONNECT_INTERVAL=30000
QR_REFRESH_INTERVAL=10000
EOF

# 9. Iniciar con configuración de debug
print_status "Iniciando servicio con configuración de debug..."
print_warning "Esto puede tomar unos segundos..."

# Iniciar en background para poder monitorear
nohup npm start > logs/debug.log 2>&1 &
SERVICE_PID=$!

# Esperar a que inicie
sleep 5

# Verificar si está ejecutándose
if ps -p $SERVICE_PID > /dev/null; then
    print_status "✅ Servicio iniciado con PID: $SERVICE_PID"
    
    # Mostrar logs iniciales
    print_status "Logs iniciales:"
    tail -n 20 logs/debug.log
    
    echo ""
    print_header "Estado del Servicio"
    print_status "PID: $SERVICE_PID"
    print_status "Puerto: 3003"
    print_status "Logs: logs/debug.log"
    print_status "Sesiones: sessions/"
    
    echo ""
    print_status "Para monitorear en tiempo real:"
    print_status "tail -f logs/debug.log"
    
    echo ""
    print_status "Para detener el servicio:"
    print_status "kill $SERVICE_PID"
    
    echo ""
    print_status "Para probar la conexión:"
    print_status "curl http://localhost:3003/health"
    
else
    print_error "❌ Error iniciando el servicio"
    print_error "Revisa los logs:"
    cat logs/debug.log
    exit 1
fi

print_header "Solución Aplicada"
echo ""
print_status "El servicio ha sido reiniciado con:"
print_status "✓ Sesiones completamente limpias"
print_status "✓ Logs de debug habilitados"
print_status "✓ Puerto 3003 liberado"
print_status "✓ Configuración optimizada"
echo ""
print_warning "Próximos pasos:"
print_warning "1. Ve a WordPress y usa el shortcode [wa_connect_qr]"
print_warning "2. Deberías ver el código QR para escanear"
print_warning "3. Escanea el QR con WhatsApp"
print_warning "4. Una vez conectado, podrás cargar grupos"
echo ""
print_status "Si el problema persiste, revisa los logs detallados:"
print_status "tail -f logs/debug.log"
