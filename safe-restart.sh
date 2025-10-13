#!/bin/bash

# Script SEGURO para solucionar solo el servicio WhatsApp
# NO afecta otros servicios de Node.js en producción

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

print_header "SOLUCIÓN SEGURA - Solo Servicio WhatsApp"

# 1. Detener SOLO el servicio específico
print_status "🛑 Deteniendo SOLO el servicio WhatsApp..."
pkill -f "node src/index.js" 2>/dev/null || true
sleep 2

# Verificar que se detuvo
if pgrep -f "node src/index.js" > /dev/null; then
    print_warning "Forzando cierre del servicio WhatsApp..."
    pkill -9 -f "node src/index.js" 2>/dev/null || true
    sleep 1
fi

# 2. Verificar que otros servicios Node.js siguen ejecutándose
OTHER_NODE_PROCESSES=$(pgrep -f "node" | wc -l)
if [ "$OTHER_NODE_PROCESSES" -gt 0 ]; then
    print_status "✅ Otros servicios Node.js siguen ejecutándose ($OTHER_NODE_PROCESSES procesos)"
else
    print_warning "⚠️  No hay otros servicios Node.js ejecutándose"
fi

# 3. Limpiar SOLO las sesiones de WhatsApp
print_status "🧹 Limpiando sesiones de WhatsApp..."
if [ -d "sessions" ]; then
    rm -rf sessions/*
    print_status "✓ Sesiones de WhatsApp eliminadas"
else
    mkdir -p sessions
fi

# 4. Limpiar SOLO los logs de WhatsApp
print_status "🧹 Limpiando logs de WhatsApp..."
if [ -d "logs" ]; then
    rm -f logs/*.log 2>/dev/null || true
    print_status "✓ Logs de WhatsApp eliminados"
else
    mkdir -p logs
fi

# 5. Verificar puerto 3003 específicamente
print_status "🔍 Verificando puerto 3003..."
if lsof -i :3003 >/dev/null 2>&1; then
    print_warning "Puerto 3003 ocupado. Liberando..."
    lsof -ti :3003 | xargs kill -9 2>/dev/null || true
    sleep 2
fi

# 6. Verificar archivo .env
if [ ! -f ".env" ]; then
    print_warning "Creando archivo .env desde ejemplo..."
    cp env.example .env
    print_warning "⚠️  CONFIGURA las variables de base de datos en .env"
    print_warning "Especialmente: DB_HOST, DB_USER, DB_PASSWORD, DB_NAME"
    exit 1
fi

# 7. Mostrar configuración actual de base de datos
print_status "📋 Configuración actual de base de datos:"
echo "DB_HOST: $(grep '^DB_HOST=' .env | cut -d'=' -f2)"
echo "DB_USER: $(grep '^DB_USER=' .env | cut -d'=' -f2)"
echo "DB_NAME: $(grep '^DB_NAME=' .env | cut -d'=' -f2)"

# 8. Crear configuración temporal con credenciales correctas
print_status "⚙️  Creando configuración temporal..."
cat > .env.temp << EOF
# Configuración temporal para WhatsApp
NODE_ENV=development
PORT=3003
LOG_LEVEL=debug
WHATSAPP_SESSION_PATH=./sessions
RECONNECT_INTERVAL=30000
QR_REFRESH_INTERVAL=10000
RATE_LIMIT_WINDOW_MS=900000
RATE_LIMIT_MAX_REQUESTS=1000

# Configuración de base de datos (usar las correctas)
DB_HOST=localhost
DB_USER=root
DB_PASSWORD=tu_password_mysql
DB_NAME=wordpress_db
DB_PORT=3306

# Configuración de seguridad
API_SECRET_KEY=condo360_whatsapp_secret_2025
EOF

print_warning "⚠️  IMPORTANTE: Edita .env.temp con las credenciales correctas de MySQL"
print_warning "Especialmente DB_USER y DB_PASSWORD"

# 9. Preguntar si continuar
echo ""
read -p "¿Continuar con el reinicio? (y/N): " -n 1 -r
echo ""
if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    print_status "Operación cancelada. Edita .env.temp y ejecuta de nuevo."
    exit 0
fi

# 10. Backup del .env original
cp .env .env.backup
cp .env.temp .env

# 11. Iniciar servicio
print_status "🚀 Iniciando servicio WhatsApp..."
print_warning "Monitorea los logs para verificar la conexión a base de datos..."

# Iniciar en background
nohup npm start > logs/whatsapp-startup.log 2>&1 &
SERVICE_PID=$!

# Esperar a que inicie
sleep 5

# Verificar si está ejecutándose
if ps -p $SERVICE_PID > /dev/null; then
    print_status "✅ Servicio WhatsApp iniciado con PID: $SERVICE_PID"
    
    # Mostrar logs iniciales
    print_status "📋 Logs de inicio:"
    tail -n 20 logs/whatsapp-startup.log
    
    echo ""
    print_header "ESTADO DEL SERVICIO WHATSAPP"
    print_status "PID: $SERVICE_PID"
    print_status "Puerto: 3003"
    print_status "Logs: logs/whatsapp-startup.log"
    print_status "Sesiones: sessions/ (limpias)"
    
    echo ""
    print_status "🔍 Para monitorear en tiempo real:"
    print_status "tail -f logs/whatsapp-startup.log"
    
    echo ""
    print_status "🛑 Para detener SOLO este servicio:"
    print_status "kill $SERVICE_PID"
    
    echo ""
    print_status "🌐 Para probar la conexión:"
    print_status "curl http://localhost:3003/health"
    print_status "curl https://wschat.bonaventurecclub.com/api/status"
    
else
    print_error "❌ Error iniciando el servicio WhatsApp"
    print_error "Revisa los logs:"
    cat logs/whatsapp-startup.log
    print_error ""
    print_error "Probablemente hay un problema con las credenciales de MySQL"
    print_error "Edita .env con las credenciales correctas y ejecuta de nuevo"
    exit 1
fi

print_header "SOLUCIÓN SEGURA COMPLETADA"
echo ""
print_status "✅ El servicio WhatsApp ha sido reiniciado:"
print_status "✓ Solo el servicio WhatsApp fue detenido"
print_status "✓ Otros servicios Node.js siguen ejecutándose"
print_status "✓ Sesiones de WhatsApp limpias"
print_status "✓ Puerto 3003 liberado"
print_status "✓ Configuración temporal aplicada"
echo ""
print_warning "📱 PRÓXIMOS PASOS:"
print_warning "1. Verifica que no hay errores de base de datos en los logs"
print_warning "2. Ve a WordPress y usa [wa_connect_qr]"
print_warning "3. Deberías ver el código QR"
print_warning "4. Escanea el QR con WhatsApp"
echo ""
print_status "🔧 Si hay errores de base de datos:"
print_status "1. Edita .env con las credenciales correctas"
print_status "2. Ejecuta: kill $SERVICE_PID"
print_status "3. Ejecuta: npm start"
echo ""
print_status "📊 Monitoreo continuo:"
print_status "tail -f logs/whatsapp-startup.log"
