#!/bin/bash

# Script AGRESIVO para solucionar error persistente de validación WhatsApp
# Este script limpia TODO y reinicia completamente

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

print_header "LIMPIEZA COMPLETA - Error Persistente WhatsApp"

# 1. DETENER TODO
print_status "🛑 Deteniendo TODOS los procesos de Node.js..."
pkill -f "node" 2>/dev/null || true
pkill -f "npm" 2>/dev/null || true
sleep 3

# Forzar cierre si es necesario
if pgrep -f "node" > /dev/null; then
    print_warning "Forzando cierre de procesos restantes..."
    pkill -9 -f "node" 2>/dev/null || true
    sleep 2
fi

# 2. LIMPIAR COMPLETAMENTE
print_status "🧹 Limpieza COMPLETA de archivos..."

# Limpiar sesiones
if [ -d "sessions" ]; then
    rm -rf sessions/*
    print_status "✓ Sesiones eliminadas"
else
    mkdir -p sessions
fi

# Limpiar logs
if [ -d "logs" ]; then
    rm -f logs/*.log 2>/dev/null || true
    print_status "✓ Logs eliminados"
else
    mkdir -p logs
fi

# Limpiar cache de npm
print_status "🧹 Limpiando cache de npm..."
npm cache clean --force 2>/dev/null || true

# 3. VERIFICAR PUERTO
print_status "🔍 Verificando puerto 3003..."
if lsof -i :3003 >/dev/null 2>&1; then
    print_warning "Puerto 3003 ocupado. Liberando..."
    lsof -ti :3003 | xargs kill -9 2>/dev/null || true
    sleep 2
fi

# 4. VERIFICAR ARCHIVO .env
if [ ! -f ".env" ]; then
    print_warning "Creando archivo .env..."
    cp env.example .env
    print_warning "⚠️  CONFIGURA las variables en .env antes de continuar"
    exit 1
fi

# 5. REINSTALAR DEPENDENCIAS (si es necesario)
if [ ! -d "node_modules" ] || [ ! -f "node_modules/.package-lock.json" ]; then
    print_status "📦 Reinstalando dependencias..."
    rm -rf node_modules package-lock.json
    npm install
fi

# 6. CREAR CONFIGURACIÓN TEMPORAL SIN RATE LIMITING
print_status "⚙️  Creando configuración temporal..."
cat > .env.temp << EOF
# Configuración temporal sin rate limiting
NODE_ENV=development
PORT=3003
LOG_LEVEL=debug
WHATSAPP_SESSION_PATH=./sessions
RECONNECT_INTERVAL=30000
QR_REFRESH_INTERVAL=10000
RATE_LIMIT_WINDOW_MS=900000
RATE_LIMIT_MAX_REQUESTS=1000
EOF

# 7. INICIAR CON CONFIGURACIÓN TEMPORAL
print_status "🚀 Iniciando servicio con configuración temporal..."
print_warning "Esto puede tomar 10-15 segundos..."

# Backup del .env original
cp .env .env.backup
cp .env.temp .env

# Iniciar en background
nohup npm start > logs/startup.log 2>&1 &
SERVICE_PID=$!

# Esperar más tiempo para que inicie completamente
sleep 10

# Verificar si está ejecutándose
if ps -p $SERVICE_PID > /dev/null; then
    print_status "✅ Servicio iniciado con PID: $SERVICE_PID"
    
    # Mostrar logs iniciales
    print_status "📋 Logs de inicio:"
    tail -n 30 logs/startup.log
    
    echo ""
    print_header "ESTADO DEL SERVICIO"
    print_status "PID: $SERVICE_PID"
    print_status "Puerto: 3003"
    print_status "Logs: logs/startup.log"
    print_status "Sesiones: sessions/ (limpias)"
    
    echo ""
    print_status "🔍 Para monitorear en tiempo real:"
    print_status "tail -f logs/startup.log"
    
    echo ""
    print_status "🛑 Para detener el servicio:"
    print_status "kill $SERVICE_PID"
    
    echo ""
    print_status "🌐 Para probar la conexión:"
    print_status "curl http://localhost:3003/health"
    print_status "curl https://wschat.bonaventurecclub.com/api/status"
    
else
    print_error "❌ Error iniciando el servicio"
    print_error "Revisa los logs:"
    cat logs/startup.log
    exit 1
fi

print_header "LIMPIEZA COMPLETA FINALIZADA"
echo ""
print_status "✅ El servicio ha sido completamente reiniciado:"
print_status "✓ Todos los procesos detenidos"
print_status "✓ Sesiones completamente limpias"
print_status "✓ Logs limpiados"
print_status "✓ Puerto 3003 liberado"
print_status "✓ Dependencias verificadas"
print_status "✓ Configuración temporal aplicada"
echo ""
print_warning "📱 PRÓXIMOS PASOS:"
print_warning "1. Ve a WordPress y usa [wa_connect_qr]"
print_warning "2. Deberías ver el código QR"
print_warning "3. Escanea el QR con WhatsApp"
print_warning "4. Una vez conectado, podrás cargar grupos"
echo ""
print_status "🔧 Si necesitas restaurar configuración original:"
print_status "cp .env.backup .env"
echo ""
print_status "📊 Monitoreo continuo:"
print_status "tail -f logs/startup.log"
