#!/bin/bash

# Script para limpiar sesiones de WhatsApp y reiniciar conexión
# Útil cuando hay problemas de conexión

set -e

# Colores para output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
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
    echo -e "${GREEN}=== $1 ===${NC}"
}

print_header "Limpieza de Sesiones WhatsApp"

# Verificar si el servicio está ejecutándose
if pgrep -f "node src/index.js" > /dev/null; then
    print_warning "El servicio está ejecutándose. Deteniendo..."
    pkill -f "node src/index.js" || true
    sleep 2
fi

# Crear directorio de sesiones si no existe
mkdir -p sessions

# Limpiar sesiones existentes
if [ -d "sessions" ] && [ "$(ls -A sessions)" ]; then
    print_status "Limpiando sesiones existentes..."
    rm -rf sessions/*
    print_status "Sesiones limpiadas ✓"
else
    print_status "No hay sesiones para limpiar"
fi

# Limpiar logs antiguos (opcional)
if [ -d "logs" ] && [ "$(ls -A logs)" ]; then
    print_status "Limpiando logs antiguos..."
    find logs -name "*.log" -mtime +7 -delete 2>/dev/null || true
    print_status "Logs limpiados ✓"
fi

# Verificar archivo .env
if [ ! -f ".env" ]; then
    print_warning "Archivo .env no encontrado. Copiando desde ejemplo..."
    cp env.example .env
    print_warning "Por favor configura las variables en .env"
fi

# Verificar dependencias
if [ ! -d "node_modules" ]; then
    print_status "Instalando dependencias..."
    npm install
fi

print_header "Reiniciando Servicio"

# Iniciar el servicio
print_status "Iniciando servicio de WhatsApp..."
npm start &

# Esperar un momento para que inicie
sleep 3

# Verificar que esté ejecutándose
if pgrep -f "node src/index.js" > /dev/null; then
    print_status "✅ Servicio iniciado correctamente"
    print_status "📱 Puerto: 3003"
    print_status "🔗 URL: https://wschat.bonaventurecclub.com"
    echo ""
    print_status "Para ver los logs en tiempo real:"
    print_status "tail -f logs/whatsapp-service.log"
    echo ""
    print_status "Para detener el servicio:"
    print_status "pkill -f 'node src/index.js'"
else
    print_error "❌ Error iniciando el servicio"
    exit 1
fi

print_header "Limpieza Completada"
echo ""
print_status "El servicio ha sido reiniciado con sesiones limpias."
print_status "Ahora deberías poder escanear un nuevo QR desde WordPress."
echo ""
print_warning "Nota: Si sigues teniendo problemas, verifica:"
print_warning "1. Las credenciales de base de datos en .env"
print_warning "2. Que el puerto 3003 esté disponible"
print_warning "3. La configuración de Nginx Proxy Manager"
